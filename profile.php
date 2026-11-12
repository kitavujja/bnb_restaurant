<?php
require_once 'config/database.php';
require_once 'config/auth.php';

requireLogin();

$conn = getDBConnection();
$user_id = intval($_SESSION['user_id']);

// Ensure wallet_balance column exists
$cols = mysqli_query($conn, "SHOW COLUMNS FROM users");
$hasWallet = false;
while ($cols && $c = mysqli_fetch_assoc($cols)) { if ($c['Field'] === 'wallet_balance') { $hasWallet = true; break; } }
if (!$hasWallet) {
    @mysqli_query($conn, "ALTER TABLE users ADD COLUMN wallet_balance DECIMAL(10,2) NOT NULL DEFAULT 0");
}

// Handle reservation cancel with wallet refund of deposit if paid
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_reservation'])) {
    $rid = intval($_POST['reservation_id']);
    if ($rid > 0) {
        mysqli_autocommit($conn, false);
        // Lock and read reservation
        $res = mysqli_query($conn, "SELECT * FROM reservations WHERE reservation_id = $rid AND (user_id = $user_id OR (user_id IS NULL AND customer_name = '" . mysqli_real_escape_string($conn, $_SESSION['full_name']) . "')) FOR UPDATE");
        $r = $res ? mysqli_fetch_assoc($res) : null;
        $canCancel = false;
        if ($r) {
            $status = $r['status'] ?? 'pending';
            // Allow cancel if not already cancelled or completed
            if ($status !== 'cancelled' && $status !== 'completed') {
                $canCancel = true;
            }
        }
        if ($canCancel) {
            $refund = 0.0;
            if (($r['payment_status'] ?? 'pending') === 'paid') {
                $refund = floatval($r['deposit_amount'] ?? 0);
            }
            $ok = true;
            // Update reservation status to cancelled
            if (!mysqli_query($conn, "UPDATE reservations SET status = 'cancelled' WHERE reservation_id = $rid")) {
                $ok = false;
            }
            // Credit wallet if refundable
            if ($ok && $refund > 0) {
                if (!mysqli_query($conn, "UPDATE users SET wallet_balance = wallet_balance + " . number_format($refund, 2, '.', '') . " WHERE user_id = $user_id")) {
                    $ok = false;
                }
            }
            if ($ok) {
                mysqli_commit($conn);
                header('Location: profile.php?res_cancelled=1');
                exit();
            } else {
                mysqli_rollback($conn);
                header('Location: profile.php?res_cancel_error=1');
                exit();
            }
        } else {
            mysqli_rollback($conn);
            header('Location: profile.php?res_cancel_error=invalid');
            exit();
        }
    }
}

// Handle order cancel with wallet refund if already paid
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_order'])) {
    $oid = intval($_POST['order_id']);
    if ($oid > 0) {
        mysqli_autocommit($conn, false);
        $res = mysqli_query($conn, "SELECT * FROM orders WHERE order_id = $oid AND user_id = $user_id FOR UPDATE");
        $o = $res ? mysqli_fetch_assoc($res) : null;
        $canCancel = false;
        if ($o) {
            $status = $o['status'] ?? 'pending';
            if ($status !== 'cancelled' && $status !== 'completed') {
                $canCancel = true;
            }
        }
        if ($canCancel) {
            $refund = 0.0;
            if (($o['payment_status'] ?? 'pending') === 'paid') {
                $refund = floatval($o['total_amount'] ?? 0);
            }
            $ok = true;
            if (!mysqli_query($conn, "UPDATE orders SET status = 'cancelled' WHERE order_id = $oid")) {
                $ok = false;
            }
            if ($ok && $refund > 0) {
                if (!mysqli_query($conn, "UPDATE users SET wallet_balance = wallet_balance + " . number_format($refund, 2, '.', '') . " WHERE user_id = $user_id")) {
                    $ok = false;
                }
            }
            if ($ok) {
                mysqli_commit($conn);
                header('Location: profile.php?order_cancelled=1');
                exit();
            } else {
                mysqli_rollback($conn);
                header('Location: profile.php?order_cancel_error=1');
                exit();
            }
        } else {
            mysqli_rollback($conn);
            header('Location: profile.php?order_cancel_error=invalid');
            exit();
        }
    }
}

// Get user orders
$orders_query = "SELECT * FROM orders WHERE user_id = $user_id ORDER BY order_date DESC";
$orders_result = mysqli_query($conn, $orders_query);

// Get wallet balance
$wallet_q = mysqli_query($conn, "SELECT wallet_balance FROM users WHERE user_id = $user_id");
$wallet = $wallet_q ? (float)mysqli_fetch_assoc($wallet_q)['wallet_balance'] : 0.0;

// Get user reservations
$reservations = mysqli_query($conn, "SELECT * FROM reservations WHERE user_id = $user_id ORDER BY reservation_date DESC, reservation_time DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - BnB Restaurant</title>
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
                <li><a href="about.php">About</a></li>
                <li><a href="menu.php">Menu</a></li>
                <li><a href="reservations.php">Reservations</a></li>
                <li><a href="contact.php">Contact</a></li>
                <li><a href="profile.php" class="active">Profile</a></li>
                <li><a href="logout.php">Logout</a></li>
            </ul>
        </div>
    </nav>

    <section class="features" style="padding: 3rem 0;">
        <div class="container">
            <div class="form-container" style="max-width: 900px;">
                <h2 class="text-center">My Profile</h2>
                
                <!-- User Information -->
                <div style="background: #f5f5f5; padding: 2rem; border-radius: 10px; margin-bottom: 2rem;">
                    <h3>Account Information</h3>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-top: 1rem;">
                        <div>
                            <p><strong>Full Name:</strong> <?php echo htmlspecialchars($_SESSION['full_name']); ?></p>
                            <p><strong>Username:</strong> <?php echo htmlspecialchars($_SESSION['username']); ?></p>
                        </div>
                        <div>
                            <p><strong>Email:</strong> <?php echo htmlspecialchars($_SESSION['email']); ?></p>
                            <p><strong>Role:</strong> <?php echo ucfirst($_SESSION['role']); ?></p>
                        </div>
                    </div>
                </div>

                <!-- Order History -->
                <div style="margin-top: 2rem;">
                    <h3>Wallet</h3>
                    <div style="background:#f5f5f5; padding:1rem; border-radius:5px; margin-bottom:1.5rem;">
                        <strong>Balance:</strong> UGX <?php echo number_format($wallet, 0); ?>
                    </div>

                    <h3>Offers</h3>
                    <div style="background:#f5f5f5; padding:1rem; border-radius:5px; margin-bottom:1.5rem;">
                        <p style="color:#666;">No offers available at the moment.</p>
                    </div>

                    <h3>My Reservations</h3>
                    <?php if ($reservations && mysqli_num_rows($reservations) > 0): ?>
                        <table style="width: 100%; margin-top: 1rem;">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Date/Time</th>
                                    <th>Guests</th>
                                    <th>Deposit</th>
                                    <th>Status</th>
                                    <th>Payment</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while($r = mysqli_fetch_assoc($reservations)): ?>
                                <tr>
                                    <td>#<?php echo $r['reservation_id']; ?></td>
                                    <td><?php echo htmlspecialchars($r['reservation_date'] . ' ' . $r['reservation_time']); ?></td>
                                    <td><?php echo intval($r['num_guests']); ?></td>
                                    <td>UGX <?php echo number_format($r['deposit_amount'] ?? 0, 0); ?></td>
                                    <td><?php echo ucfirst($r['status']); ?></td>
                                    <td><?php echo ucfirst($r['payment_status'] ?? 'pending'); ?></td>
                                    <td>
                                        <?php if ($r['status'] !== 'cancelled' && $r['status'] !== 'completed'): ?>
                                            <form method="POST" onsubmit="return confirm('Cancel this reservation? Deposit will be refunded to wallet if already paid.');" style="display:inline;">
                                                <input type="hidden" name="reservation_id" value="<?php echo $r['reservation_id']; ?>">
                                                <button type="submit" name="cancel_reservation" class="btn btn-small" style="background:#dc3545;">Cancel</button>
                                            </form>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div style="text-align: center; padding: 1rem; background: #f5f5f5; border-radius: 10px; margin-top: 1rem;">
                            <p style="color: #666;">No reservations yet.</p>
                        </div>
                    <?php endif; ?>

                    <h3>My Orders</h3>
                    
                    <?php if (mysqli_num_rows($orders_result) > 0): ?>
                        <table style="width: 100%; margin-top: 1rem;">
                            <thead>
                                <tr>
                                    <th>Order ID</th>
                                    <th>Date</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                    <th>Payment</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while($order = mysqli_fetch_assoc($orders_result)): ?>
                                <tr>
                                    <td>#<?php echo $order['order_id']; ?></td>
                                    <td><?php echo date('M d, Y H:i', strtotime($order['order_date'])); ?></td>
                                    <td>UGX <?php echo number_format($order['total_amount'], 0); ?></td>
                                    <td>
                                        <span style="padding: 5px 10px; border-radius: 5px; background: 
                                            <?php 
                                                if ($order['status'] === 'completed') echo '#d4edda';
                                                elseif ($order['status'] === 'ready') echo '#d1ecf1';
                                                elseif ($order['status'] === 'preparing') echo '#fff3cd';
                                                else echo '#f8d7da';
                                            ?>; color: 
                                            <?php 
                                                if ($order['status'] === 'completed') echo '#155724';
                                                elseif ($order['status'] === 'ready') echo '#0c5460';
                                                elseif ($order['status'] === 'preparing') echo '#856404';
                                                else echo '#721c24';
                                            ?>;">
                                            <?php echo ucfirst($order['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span style="padding: 5px 10px; border-radius: 5px; background: 
                                            <?php echo $order['payment_status'] === 'paid' ? '#d4edda' : '#f8d7da'; ?>; color: 
                                            <?php echo $order['payment_status'] === 'paid' ? '#155724' : '#721c24'; ?>;">
                                            <?php echo ucfirst($order['payment_status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <button onclick="viewOrderDetails(<?php echo $order['order_id']; ?>)" class="btn btn-small">View Details</button>
                                        <?php if ($order['status'] !== 'cancelled' && $order['status'] !== 'completed'): ?>
                                            <form method="POST" onsubmit="return confirm('Cancel this order? If already paid, the amount will be refunded to your wallet.');" style="display:inline;">
                                                <input type="hidden" name="order_id" value="<?php echo $order['order_id']; ?>">
                                                <button type="submit" name="cancel_order" class="btn btn-small" style="background:#dc3545;">Cancel</button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div style="text-align: center; padding: 3rem; background: #f5f5f5; border-radius: 10px; margin-top: 1rem;">
                            <p style="font-size: 1.2rem; color: #666;">You haven't placed any orders yet.</p>
                            <a href="menu.php" class="btn btn-primary" style="margin-top: 1rem;">Browse Menu</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>

    <!-- Order Details Modal -->
    <div id="orderModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 2000; overflow-y: auto;">
        <div style="background: white; max-width: 600px; margin: 5% auto; padding: 2rem; border-radius: 10px;">
            <h2>Order Details</h2>
            <div id="orderDetails"></div>
            <button onclick="closeOrderModal()" class="btn btn-secondary" style="margin-top: 1rem; width: 100%;">Close</button>
        </div>
    </div>

    <footer class="footer">
        <div class="container">
            <div class="footer-bottom">
                <p>&copy; 2025 BnB Restaurant. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <script>
        function viewOrderDetails(orderId) {
            // Fetch order details via AJAX or show in modal
            fetch('get_order_details.php?id=' + orderId)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        let html = '<h3>Order #' + data.order.order_id + '</h3>';
                        html += '<p><strong>Date:</strong> ' + data.order.date + '</p>';
                        html += '<p><strong>Total:</strong> UGX ' + parseFloat(data.order.total).toLocaleString() + '</p>';
                        html += '<p><strong>Status:</strong> ' + data.order.status + '</p>';
                        html += '<p><strong>Payment:</strong> ' + data.order.payment + '</p>';
                        html += '<h4>Items:</h4><ul>';
                        data.items.forEach(item => {
                            html += '<li>' + item.name + ' × ' + item.quantity + ' = UGX ' + parseFloat(item.total).toLocaleString() + '</li>';
                        });
                        html += '</ul>';
                        document.getElementById('orderDetails').innerHTML = html;
                        document.getElementById('orderModal').style.display = 'block';
                    } else {
                        alert('Failed to load order details');
                    }
                })
                .catch(error => {
                    // Fallback: just show order ID
                    document.getElementById('orderDetails').innerHTML = '<p>Order #' + orderId + '</p><p>Details loading...</p>';
                    document.getElementById('orderModal').style.display = 'block';
                });
        }
        
        function closeOrderModal() {
            document.getElementById('orderModal').style.display = 'none';
        }
    </script>
    <script src="assets/js/main.js"></script>
</body>
</html>



