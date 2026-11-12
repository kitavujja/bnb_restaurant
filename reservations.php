<?php
require_once 'config/database.php';
require_once 'config/auth.php';

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $customer_name = $_POST['customer_name'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $reservation_date = $_POST['reservation_date'] ?? '';
    $reservation_time = $_POST['reservation_time'] ?? '';
    $num_guests = $_POST['num_guests'] ?? 1;
    $notes = $_POST['notes'] ?? '';
    $deposit_amount = floatval($_POST['deposit_amount'] ?? 0);
    $payment_method = $_POST['payment_method'] ?? 'cash';
    $mobile_network = trim($_POST['mobile_network'] ?? '');
    $mobile_number = preg_replace('/\D+/', '', $_POST['mobile_number'] ?? '');
    $card_number = preg_replace('/\D+/', '', $_POST['card_number'] ?? '');
    $user_id = isLoggedIn() ? $_SESSION['user_id'] : null;
    
    if (empty($customer_name) || empty($phone) || empty($reservation_date) || empty($reservation_time)) {
        $error = 'Please fill all required fields';
    } elseif ($deposit_amount <= 0) {
        $error = 'Please enter a valid deposit amount.';
    } elseif ($payment_method === 'mobile' && ($mobile_network === '' || $mobile_number === '')) {
        $error = 'Please provide mobile network and number for Mobile Money.';
    } elseif ($payment_method === 'card' && (strlen($card_number) < 12 || strlen($card_number) > 19)) {
        $error = 'Please enter a valid card number (12-19 digits).';
    } else {
        $conn = getDBConnection();
        
        $customer_name = mysqli_real_escape_string($conn, $customer_name);
        $phone = mysqli_real_escape_string($conn, $phone);
        $reservation_date = mysqli_real_escape_string($conn, $reservation_date);
        $reservation_time = mysqli_real_escape_string($conn, $reservation_time);
        $num_guests = intval($num_guests);
        $notes = mysqli_real_escape_string($conn, $notes);
        $payment_method = mysqli_real_escape_string($conn, $payment_method);
        $mobile_network = mysqli_real_escape_string($conn, $mobile_network);
        $mobile_number = mysqli_real_escape_string($conn, $mobile_number);
        $card_last4 = $payment_method === 'card' ? substr($card_number, -4) : null;

        // Ensure optional columns exist on reservations
        $resCols = mysqli_query($conn, "SHOW COLUMNS FROM reservations");
        $rcols = [];
        while ($resCols && $c = mysqli_fetch_assoc($resCols)) { $rcols[] = $c['Field']; }
        if (!in_array('deposit_amount', $rcols)) {
            @mysqli_query($conn, "ALTER TABLE reservations ADD COLUMN deposit_amount DECIMAL(10,2) NULL DEFAULT 0");
        }
        if (!in_array('payment_status', $rcols)) {
            @mysqli_query($conn, "ALTER TABLE reservations ADD COLUMN payment_status ENUM('pending','paid') DEFAULT 'pending'");
        }
        if (!in_array('payment_method', $rcols)) {
            @mysqli_query($conn, "ALTER TABLE reservations ADD COLUMN payment_method VARCHAR(20) NULL");
        }
        if (!in_array('mobile_network', $rcols)) {
            @mysqli_query($conn, "ALTER TABLE reservations ADD COLUMN mobile_network VARCHAR(20) NULL");
        }
        if (!in_array('mobile_number', $rcols)) {
            @mysqli_query($conn, "ALTER TABLE reservations ADD COLUMN mobile_number VARCHAR(20) NULL");
        }
        if (!in_array('card_last4', $rcols)) {
            @mysqli_query($conn, "ALTER TABLE reservations ADD COLUMN card_last4 VARCHAR(4) NULL");
        }
        if (!in_array('admin_confirmed', $rcols)) {
            @mysqli_query($conn, "ALTER TABLE reservations ADD COLUMN admin_confirmed TINYINT(1) NOT NULL DEFAULT 0");
        }
        if (!in_array('admin_confirmed_at', $rcols)) {
            @mysqli_query($conn, "ALTER TABLE reservations ADD COLUMN admin_confirmed_at TIMESTAMP NULL DEFAULT NULL");
        }
        
        $user_id_sql = $user_id ? intval($user_id) : 'NULL';
        $query = "INSERT INTO reservations (user_id, customer_name, phone, reservation_date, reservation_time, num_guests, notes, deposit_amount, payment_status, payment_method, mobile_network, mobile_number, card_last4) VALUES ($user_id_sql, '$customer_name', '$phone', '$reservation_date', '$reservation_time', $num_guests, '$notes', " . number_format($deposit_amount, 2, '.', '') . ", 'pending', '$payment_method', " .
                 ($payment_method === 'mobile' ? "'$mobile_network'" : 'NULL') . ", " .
                 ($payment_method === 'mobile' ? "'$mobile_number'" : 'NULL') . ", " .
                 ($payment_method === 'card' && $card_last4 ? "'$card_last4'" : 'NULL') .
                 ")";
        
        if (mysqli_query($conn, $query)) {
            $success = 'Reservation submitted with deposit. Awaiting cashier payment confirmation and admin approval.';
        } else {
            $error = 'Reservation failed. Please try again.';
        }
        mysqli_close($conn);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reservations - BnB Restaurant</title>
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
                <li><a href="reservations.php" class="active">Reservations</a></li>
                <li><a href="contact.php">Contact</a></li>
                <?php if (isLoggedIn()): ?>
                    <li><a href="profile.php">Profile</a></li>
                    <?php if (getUserRole() === 'admin'): ?>
                        <li><a href="admin/dashboard.php">Admin</a></li>
                    <?php elseif (getUserRole() === 'cashier'): ?>
                        <li><a href="cashier/dashboard.php">Cashier</a></li>
                    <?php endif; ?>
                    <li><a href="logout.php">Logout</a></li>
                <?php else: ?>
                    <li><a href="login.php">Login</a></li>
                    <li><a href="register.php">Register</a></li>
                <?php endif; ?>
            </ul>
        </div>
    </nav>

    <section class="features" style="padding: 3rem 0;">
        <div class="container">
            <div class="form-container">
                <h2 class="text-center">Make a Reservation</h2>
                
                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                    <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>

                <form method="POST" action="" onsubmit="return validateReservation()">
                    <div class="form-group">
                        <label for="customer_name">Full Name *</label>
                        <input type="text" id="customer_name" name="customer_name" value="<?php echo isLoggedIn() ? htmlspecialchars($_SESSION['full_name']) : ''; ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="phone">Phone Number *</label>
                        <input type="tel" id="phone" name="phone" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="reservation_date">Date *</label>
                        <input type="date" id="reservation_date" name="reservation_date" min="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="reservation_time">Time *</label>
                        <input type="time" id="reservation_time" name="reservation_time" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="num_guests">Number of Guests *</label>
                        <input type="number" id="num_guests" name="num_guests" min="1" max="20" value="2" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="notes">Special Requests</label>
                        <textarea id="notes" name="notes" rows="3" placeholder="Any special requests or dietary requirements..."></textarea>
                    </div>
                    <div class="form-group">
                        <label>Deposit Amount (UGX) *</label>
                        <input type="number" name="deposit_amount" min="1000" step="0.01" required placeholder="5000">
                    </div>
                    <div class="form-group">
                        <label>Payment Method *</label>
                        <div style="display:flex; gap:1rem; flex-wrap:wrap;">
                            <label><input type="radio" name="payment_method" value="mobile" checked> Mobile Money</label>
                            <label><input type="radio" name="payment_method" value="cash"> Cash</label>
                            <label><input type="radio" name="payment_method" value="card"> Card</label>
                        </div>
                    </div>
                    <div id="mobileFields" class="form-group" style="display:block;">
                        <div style="display:flex; gap:1rem; flex-wrap:wrap;">
                            <div style="flex:1; min-width:180px;">
                                <label>Network</label>
                                <select name="mobile_network" id="resMobileNetwork" style="width:100%; padding:10px; border:1px solid #ddd; border-radius:5px;">
                                    <option value="mtn">MTN</option>
                                    <option value="airtel">Airtel</option>
                                </select>
                            </div>
                            <div style="flex:2; min-width:220px;">
                                <label>Mobile Number</label>
                                <input type="tel" name="mobile_number" id="resMobileNumber" placeholder="e.g. 0771234567" style="width:100%; padding:10px; border:1px solid #ddd; border-radius:5px;" />
                            </div>
                        </div>
                        <small style="color:#666;">Enter your registered mobile money number.</small>
                    </div>
                    <div id="cardFields" class="form-group" style="display:none;">
                        <label>Card Number</label>
                        <input type="text" name="card_number" id="resCardNumber" inputmode="numeric" autocomplete="cc-number" placeholder="1234 5678 9012 3456" style="width:100%; padding:10px; border:1px solid #ddd; border-radius:5px;" />
                        <small style="color:#666;">We only store the last 4 digits. Simulation only.</small>
                    </div>
                    
                    <button type="submit" class="btn btn-primary" style="width: 100%;">Submit Reservation</button>
                </form>
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
        function validateReservation() {
            const date = document.getElementById('reservation_date').value;
            const time = document.getElementById('reservation_time').value;
            const today = new Date();
            const selectedDate = new Date(date);
            
            if (selectedDate < today) {
                alert('Please select a future date!');
                return false;
            }
            const deposit = parseFloat(document.querySelector('input[name="deposit_amount"]').value || '0');
            if (!(deposit > 0)) {
                alert('Please enter a valid deposit amount.');
                return false;
            }
            const pm = document.querySelector('input[name="payment_method"]:checked')?.value || 'mobile';
            if (pm === 'mobile') {
                const msisdn = (document.getElementById('resMobileNumber').value || '').replace(/\D+/g, '');
                if (msisdn.length < 9 || msisdn.length > 12) {
                    alert('Please enter a valid mobile number.');
                    return false;
                }
            }
            if (pm === 'card') {
                const card = (document.getElementById('resCardNumber').value || '').replace(/\D+/g, '');
                if (card.length < 12 || card.length > 19) {
                    alert('Please enter a valid card number (12-19 digits).');
                    return false;
                }
            }
            
            return true;
        }
        
        // Set minimum date to today
        document.getElementById('reservation_date').setAttribute('min', new Date().toISOString().split('T')[0]);
        // Toggle payment fields
        function toggleResPayment() {
            const pm = document.querySelector('input[name="payment_method"]:checked')?.value || 'mobile';
            document.getElementById('mobileFields').style.display = pm === 'mobile' ? 'block' : 'none';
            document.getElementById('cardFields').style.display = pm === 'card' ? 'block' : 'none';
        }
        document.querySelectorAll('input[name="payment_method"]').forEach(el => el.addEventListener('change', toggleResPayment));
        toggleResPayment();
    </script>
    <script src="assets/js/main.js"></script>
</body>
</html>


