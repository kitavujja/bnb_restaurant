<?php
require_once 'config/database.php';
require_once 'config/auth.php';

// Require customer to be logged in
if (!isLoggedIn() || getUserRole() !== 'customer') {
    header('Location: login.php');
    exit();
}

$error = '';
$success = '';
$cart = [];

// Ensure wallet support and fetch balance
$connWB = getDBConnection();
$colsWB = mysqli_query($connWB, "SHOW COLUMNS FROM users");
$hasWallet = false;
while ($colsWB && $cw = mysqli_fetch_assoc($colsWB)) { if ($cw['Field'] === 'wallet_balance') { $hasWallet = true; break; } }
if (!$hasWallet) { @mysqli_query($connWB, "ALTER TABLE users ADD COLUMN wallet_balance DECIMAL(10,2) NOT NULL DEFAULT 0"); }
$wallet_balance = 0.0;
$uidWB = intval($_SESSION['user_id']);
$wbQ = mysqli_query($connWB, "SELECT wallet_balance FROM users WHERE user_id = $uidWB");
if ($wbQ) { $wbR = mysqli_fetch_assoc($wbQ); $wallet_balance = (float)($wbR['wallet_balance'] ?? 0); }

// Get cart from localStorage via JavaScript, or session
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['place_order'])) {
    $cart_items = json_decode($_POST['cart_items'], true);
    $total_amount = floatval($_POST['total_amount']);
    $payment_method = $_POST['payment_method'] ?? 'cash';
    $fulfillment_method = $_POST['fulfillment_method'] ?? 'pickup';
    $delivery_address = trim($_POST['delivery_address'] ?? '');
    $mobile_network = trim($_POST['mobile_network'] ?? '');
    $mobile_number = trim($_POST['mobile_number'] ?? '');
    $card_number = preg_replace('/\D+/', '', $_POST['card_number'] ?? '');
    
    if (empty($cart_items) || $total_amount <= 0) {
        $error = 'Cart is empty!';
    } else if ($fulfillment_method === 'delivery' && $delivery_address === '') {
        $error = 'Please provide a delivery address.';
    } else if ($payment_method === 'mobile' && ($mobile_network === '' || $mobile_number === '')) {
        $error = 'Please provide mobile network and number.';
    } else if ($payment_method === 'card' && (strlen($card_number) < 12 || strlen($card_number) > 19)) {
        $error = 'Please enter a valid card number (12-19 digits).';
    } else {
        $conn = getDBConnection();
        $user_id = intval($_SESSION['user_id']);
        // Whitelist payment and fulfillment values
        $allowed_pm = ['mobile','cash','card','wallet'];
        if (!in_array($payment_method, $allowed_pm, true)) { $payment_method = 'cash'; }
        $allowed_fm = ['delivery','pickup'];
        if (!in_array($fulfillment_method, $allowed_fm, true)) { $fulfillment_method = 'pickup'; }
        
        // Ensure optional columns exist (payment_method, fulfillment_method, delivery_address)
        $columns = [];
        $colRes = mysqli_query($conn, "SHOW COLUMNS FROM orders");
        while ($colRes && $c = mysqli_fetch_assoc($colRes)) { $columns[] = $c['Field']; }
        if (!in_array('payment_method', $columns)) {
            @mysqli_query($conn, "ALTER TABLE orders ADD COLUMN payment_method VARCHAR(20) NULL");
        }
        if (!in_array('fulfillment_method', $columns)) {
            @mysqli_query($conn, "ALTER TABLE orders ADD COLUMN fulfillment_method ENUM('delivery','pickup') NULL");
        }
        if (!in_array('delivery_address', $columns)) {
            @mysqli_query($conn, "ALTER TABLE orders ADD COLUMN delivery_address VARCHAR(255) NULL");
        }
        if (!in_array('mobile_network', $columns)) {
            @mysqli_query($conn, "ALTER TABLE orders ADD COLUMN mobile_network VARCHAR(20) NULL");
        }
        if (!in_array('mobile_number', $columns)) {
            @mysqli_query($conn, "ALTER TABLE orders ADD COLUMN mobile_number VARCHAR(20) NULL");
        }
        if (!in_array('card_last4', $columns)) {
            @mysqli_query($conn, "ALTER TABLE orders ADD COLUMN card_last4 VARCHAR(4) NULL");
        }
        
        // Start transaction
        mysqli_autocommit($conn, false);
        
        // Simulate payment status: card/wallet -> paid, others -> pending
        $simulated_payment_status = ($payment_method === 'card' || $payment_method === 'wallet') ? 'paid' : 'pending';
        $card_last4 = $payment_method === 'card' ? substr($card_number, -4) : null;
        
        // If paying with wallet, ensure sufficient balance first (lock row)
        if ($payment_method === 'wallet') {
            $wbLock = mysqli_query($conn, "SELECT wallet_balance FROM users WHERE user_id = $user_id FOR UPDATE");
            $wbalance = $wbLock ? (float)mysqli_fetch_assoc($wbLock)['wallet_balance'] : 0.0;
            if ($wbalance < $total_amount) {
                mysqli_rollback($conn);
                mysqli_autocommit($conn, true);
                mysqli_close($conn);
                $error = 'Insufficient wallet balance.';
            }
        }

        // Create order including simulation fields
        if (empty($error)) {
        $order_query = "INSERT INTO orders (user_id, total_amount, status, payment_status, payment_method, fulfillment_method, delivery_address, mobile_network, mobile_number, card_last4) VALUES (" .
            "$user_id, $total_amount, 'pending', '" . mysqli_real_escape_string($conn, $simulated_payment_status) . "', '" . mysqli_real_escape_string($conn, $payment_method) . "', '" . mysqli_real_escape_string($conn, $fulfillment_method) . "', " .
            ($fulfillment_method === 'delivery' ? "'" . mysqli_real_escape_string($conn, $delivery_address) . "'" : 'NULL') . ", " .
            ($payment_method === 'mobile' ? "'" . mysqli_real_escape_string($conn, $mobile_network) . "'" : 'NULL') . ", " .
            ($payment_method === 'mobile' ? "'" . mysqli_real_escape_string($conn, $mobile_number) . "'" : 'NULL') . ", " .
            ($payment_method === 'card' && $card_last4 !== null ? "'" . mysqli_real_escape_string($conn, $card_last4) . "'" : 'NULL') .
        ")";
        
        if (mysqli_query($conn, $order_query)) {
            $order_id = mysqli_insert_id($conn);
            $successFlag = true;
            
            // Insert order items
            foreach ($cart_items as $item) {
                $item_id = intval($item['id']);
                $quantity = intval($item['quantity']);
                $price = floatval($item['price']);
                
                $item_query = "INSERT INTO order_items (order_id, item_id, quantity, price) VALUES ($order_id, $item_id, $quantity, $price)";
                
                if (!mysqli_query($conn, $item_query)) {
                    $successFlag = false;
                    break;
                }
            }
            
            if ($successFlag) {
                // Deduct wallet if selected
                if ($payment_method === 'wallet') {
                    if (!mysqli_query($conn, "UPDATE users SET wallet_balance = wallet_balance - $total_amount WHERE user_id = $user_id")) {
                        $successFlag = false;
                    }
                }
                if ($successFlag) { mysqli_commit($conn); }
                $success = 'Order placed successfully! Order ID: #' . $order_id;
                if ($payment_method === 'mobile') {
                    $success .= ' — Mobile Money initiated (' . htmlspecialchars($mobile_network) . ' ' . htmlspecialchars($mobile_number) . '). Please confirm on your phone.';
                } elseif ($payment_method === 'cash') {
                    $success .= ' — Pay cash on delivery/pickup.';
                } elseif ($payment_method === 'card') {
                    $success .= ' — Card payment approved (**** ' . htmlspecialchars($card_last4 ?? '') . ').';
                } else {
                    $success .= ' — Paid with wallet.';
                }
                if ($fulfillment_method === 'delivery') {
                    $success .= ' Delivery to: ' . htmlspecialchars($delivery_address);
                } else {
                    $success .= ' — Pickup at the restaurant.';
                }
                // Clear cart after successful order
                echo '<script>localStorage.removeItem("cart");</script>';
            } else {
                mysqli_rollback($conn);
                $error = 'Failed to place order. Please try again.';
            }
        } else {
            mysqli_rollback($conn);
            $error = 'Failed to create order. Please try again.';
        }
        
        mysqli_autocommit($conn, true);
        mysqli_close($conn);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - BnB Restaurant</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <nav class="navbar">
        <div class="container">
            <div class="logo">
                <a href="index.php" class="brand">
                    <img src="assets/images/logo.svg" alt="BnB Restaurant logo">
                    <h1>BnB Restaurant</h1>
                </a>
            </div>
            <ul class="nav-menu">
                <li><a href="index.php">Home</a></li>
                <li><a href="menu.php">Menu</a></li>
                <li><a href="contact.php">Contact</a></li>
                <li><a href="reservations.php">Reservations</a></li>
                <li><a href="profile.php">Profile</a></li>
                <li><a href="logout.php">Logout</a></li>
            </ul>
        </div>
    </nav>

    <section class="features" style="padding: 3rem 0;">
        <div class="container">
            <div class="form-container" style="max-width: 800px;">
                <h2 class="text-center">Checkout</h2>
                
                <?php if ($error): ?>
                    <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <?php echo htmlspecialchars($success); ?>
                        <br><br>
                        <a href="menu.php" class="btn btn-primary">Continue Shopping</a>
                        <a href="profile.php" class="btn btn-secondary">View Orders</a>
                    </div>
                <?php else: ?>
                
                <!-- Order Summary -->
                <div id="orderSummary" style="margin-bottom: 2rem;">
                    <h3>Order Summary</h3>
                    <div id="cartItemsDisplay" style="border: 1px solid #ddd; padding: 1rem; border-radius: 5px; margin-bottom: 1rem;">
                        <p>Loading cart...</p>
                    </div>
                    <div style="display: flex; justify-content: space-between; font-size: 1.3rem; font-weight: bold; padding: 1rem; background: #f5f5f5; border-radius: 5px;">
                        <span>Total:</span>
                        <span id="orderTotal">UGX 0</span>
                    </div>
                </div>

                <!-- Customer Information -->
                <div style="margin-bottom: 2rem;">
                    <h3>Customer Information</h3>
                    <div style="background: #f5f5f5; padding: 1rem; border-radius: 5px;">
                        <p><strong>Name:</strong> <?php echo htmlspecialchars($_SESSION['full_name']); ?></p>
                        <p><strong>Email:</strong> <?php echo htmlspecialchars($_SESSION['email']); ?></p>
                        <p><strong>Username:</strong> <?php echo htmlspecialchars($_SESSION['username']); ?></p>
                    </div>
                </div>

                <!-- Payment & Fulfillment Information -->
                <div style="margin-bottom: 2rem;">
                    <h3>Payment & Delivery</h3>
                    <div style="background: #f5f5f5; padding: 1rem; border-radius: 5px; display: grid; gap: 1rem;">
                        <div>
                            <label style="display:block; font-weight:600; margin-bottom: .5rem;">Payment Method</label>
                            <div style="display:flex; gap:1rem; flex-wrap: wrap; align-items:center;">
                                <label><input type="radio" name="payment_method_ui" value="mobile" checked> Mobile Money</label>
                                <label><input type="radio" name="payment_method_ui" value="cash"> Cash</label>
                                <label><input type="radio" name="payment_method_ui" value="card"> Card</label>
                                <label><input type="radio" name="payment_method_ui" value="wallet"> Wallet</label>
                                <span style="font-size:0.9rem; color:#666;">Balance: UGX <?php echo number_format($wallet_balance, 0); ?></span>
                            </div>
                        </div>
                        <div id="mobileFields" style="display:block;">
                            <div style="display:flex; gap:1rem; flex-wrap: wrap;">
                                <div style="flex:1; min-width:180px;">
                                    <label style="display:block; font-weight:600; margin-bottom:.5rem;">Network</label>
                                    <select id="mobileNetworkSelect" style="width:100%; padding:10px; border:1px solid #ddd; border-radius:5px;">
                                        <option value="mtn">MTN</option>
                                        <option value="airtel">Airtel</option>
                                    </select>
                                </div>
                                <div style="flex:2; min-width:220px;">
                                    <label style="display:block; font-weight:600; margin-bottom:.5rem;">Mobile Number</label>
                                    <input type="tel" id="mobileNumberInput" placeholder="e.g. 0771234567" style="width:100%; padding:10px; border:1px solid #ddd; border-radius:5px;" />
                                </div>
                            </div>
                            <small style="color:#666;">Enter your registered mobile money number.</small>
                        </div>
                        <div id="cardFields" style="display:none;">
                            <label style="display:block; font-weight:600; margin-bottom:.5rem;">Card Number</label>
                            <input type="text" id="cardNumberInput" inputmode="numeric" autocomplete="cc-number" placeholder="1234 5678 9012 3456" style="width:100%; padding:10px; border:1px solid #ddd; border-radius:5px;" />
                            <small style="color:#666;">We only store the last 4 digits. Simulation only.</small>
                        </div>
                        <div>
                            <label style="display:block; font-weight:600; margin-bottom: .5rem;">Fulfillment</label>
                            <div style="display:flex; gap:1rem; flex-wrap: wrap;">
                                <label><input type="radio" name="fulfillment_method_ui" value="delivery" checked> Delivery</label>
                                <label><input type="radio" name="fulfillment_method_ui" value="pickup"> Pickup</label>
                            </div>
                        </div>
                        <div id="deliveryAddressGroup">
                            <label style="display:block; font-weight:600; margin-bottom: .5rem;">Delivery Address</label>
                            <input type="text" id="deliveryAddressInput" placeholder="Enter delivery address" style="width:100%; padding:10px; border:1px solid #ddd; border-radius:5px;" />
                            <small style="color:#666;">Required for delivery</small>
                        </div>
                    </div>
                </div>

                <!-- Place Order Form -->
                <form method="POST" action="" id="checkoutForm" onsubmit="return validateCheckout()">
                    <input type="hidden" name="cart_items" id="cartItemsInput">
                    <input type="hidden" name="total_amount" id="totalAmountInput">
                    <input type="hidden" name="payment_method" id="paymentMethodInput" value="mobile">
                    <input type="hidden" name="fulfillment_method" id="fulfillmentMethodInput" value="delivery">
                    <input type="hidden" name="delivery_address" id="deliveryAddressHidden">
                    <input type="hidden" name="mobile_network" id="mobileNetworkHidden">
                    <input type="hidden" name="mobile_number" id="mobileNumberHidden">
                    <input type="hidden" name="card_number" id="cardNumberHidden">
                    <input type="hidden" name="place_order" value="1">
                    
                    <div style="display: flex; gap: 1rem; margin-top: 2rem;">
                        <a href="menu.php" class="btn btn-secondary" style="flex: 1; text-align: center;">Back to Menu</a>
                        <button type="submit" class="btn btn-primary" style="flex: 1;">Place Order</button>
                    </div>
                </form>
                
                <?php endif; ?>
            </div>
        </div>
    </section>

    <footer class="footer">
        <div class="container">
            <div class="footer-bottom">
                <p>&copy; 2025 BnB Restaurant. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <script>
        // Load cart from localStorage
        let cart = JSON.parse(localStorage.getItem('cart')) || [];
        
        function displayCart() {
            const itemsDiv = document.getElementById('cartItemsDisplay');
            const totalSpan = document.getElementById('orderTotal');
            const cartInput = document.getElementById('cartItemsInput');
            const totalInput = document.getElementById('totalAmountInput');
            
            if (cart.length === 0) {
                itemsDiv.innerHTML = '<p style="text-align: center; color: #999;">Your cart is empty. <a href="menu.php">Go to menu</a></p>';
                totalSpan.textContent = 'UGX 0';
                cartInput.value = '[]';
                totalInput.value = '0';
                return false;
            }
            
            let html = '';
            let total = 0;
            
            cart.forEach(item => {
                const itemTotal = item.price * item.quantity;
                total += itemTotal;
                html += `
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.75rem 0; border-bottom: 1px solid #ddd;">
                        <div>
                            <strong>${item.name}</strong>
                            <br>
                            <small>Quantity: ${item.quantity} × UGX ${item.price.toLocaleString()}</small>
                        </div>
                        <div style="font-weight: bold; color: var(--accent-color);">
                            UGX ${itemTotal.toLocaleString()}
                        </div>
                    </div>
                `;
            });
            
            itemsDiv.innerHTML = html;
            totalSpan.textContent = 'UGX ' + total.toLocaleString();
            cartInput.value = JSON.stringify(cart);
            totalInput.value = total;
            
            return true;
        }
        
        function validateCheckout() {
            if (cart.length === 0) {
                alert('Your cart is empty! Please add items to cart first.');
                return false;
            }
            // Sync selected options to hidden inputs
            const payment = document.querySelector('input[name="payment_method_ui"]:checked')?.value || 'mobile';
            const fulfillment = document.querySelector('input[name="fulfillment_method_ui"]:checked')?.value || 'delivery';
            document.getElementById('paymentMethodInput').value = payment;
            document.getElementById('fulfillmentMethodInput').value = fulfillment;
            const address = document.getElementById('deliveryAddressInput')?.value.trim() || '';
            document.getElementById('deliveryAddressHidden').value = address;
            if (fulfillment === 'delivery' && address.length === 0) {
                alert('Please enter a delivery address.');
                return false;
            }
            // Payment specific validation and sync
            if (payment === 'mobile') {
                const network = document.getElementById('mobileNetworkSelect')?.value || '';
                const msisdnRaw = (document.getElementById('mobileNumberInput')?.value || '').replace(/\D+/g, '');
                if (!network || msisdnRaw.length < 9 || msisdnRaw.length > 12) {
                    alert('Please enter a valid mobile money number.');
                    return false;
                }
                document.getElementById('mobileNetworkHidden').value = network;
                document.getElementById('mobileNumberHidden').value = msisdnRaw;
                document.getElementById('cardNumberHidden').value = '';
            } else if (payment === 'card') {
                const cardRaw = (document.getElementById('cardNumberInput')?.value || '').replace(/\D+/g, '');
                if (cardRaw.length < 12 || cardRaw.length > 19) {
                    alert('Please enter a valid card number (12-19 digits).');
                    return false;
                }
                document.getElementById('cardNumberHidden').value = cardRaw;
                document.getElementById('mobileNetworkHidden').value = '';
                document.getElementById('mobileNumberHidden').value = '';
            } else if (payment === 'wallet') {
                const total = parseFloat(document.getElementById('totalAmountInput').value || '0');
                const walletBalance = <?php echo json_encode((float)$wallet_balance); ?>;
                if (total > walletBalance) {
                    alert('Insufficient wallet balance.');
                    return false;
                }
                document.getElementById('mobileNetworkHidden').value = '';
                document.getElementById('mobileNumberHidden').value = '';
                document.getElementById('cardNumberHidden').value = '';
            } else {
                document.getElementById('mobileNetworkHidden').value = '';
                document.getElementById('mobileNumberHidden').value = '';
                document.getElementById('cardNumberHidden').value = '';
            }

            return true;
        }
        
        // Display cart on page load
        window.onload = function() {
            if (!displayCart()) {
                document.getElementById('checkoutForm').style.display = 'none';
            }
            // Toggle address visibility based on fulfillment method
            const toggleAddress = () => {
                const fulfillment = document.querySelector('input[name="fulfillment_method_ui"]:checked')?.value || 'delivery';
                const group = document.getElementById('deliveryAddressGroup');
                if (group) {
                    group.style.display = fulfillment === 'delivery' ? 'block' : 'none';
                }
            };
            const togglePaymentFields = () => {
                const payment = document.querySelector('input[name="payment_method_ui"]:checked')?.value || 'mobile';
                const mobileFields = document.getElementById('mobileFields');
                const cardFields = document.getElementById('cardFields');
                if (mobileFields && cardFields) {
                    mobileFields.style.display = payment === 'mobile' ? 'block' : 'none';
                    cardFields.style.display = payment === 'card' ? 'block' : 'none';
                }
            };
            document.querySelectorAll('input[name="fulfillment_method_ui"]').forEach(el => {
                el.addEventListener('change', toggleAddress);
            });
            document.querySelectorAll('input[name="payment_method_ui"]').forEach(el => {
                el.addEventListener('change', togglePaymentFields);
            });
            toggleAddress();
            togglePaymentFields();
        };
    </script>
    <script src="assets/js/main.js"></script>
</body>
</html>

