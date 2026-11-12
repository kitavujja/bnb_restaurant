<?php
require_once 'config/database.php';
require_once 'config/auth.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');

    if (strlen($full_name) < 3) {
        $error = 'Please enter your full name (at least 3 characters).';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif ($phone !== '' && !preg_match('/^[0-9+\s-]{7,20}$/', $phone)) {
        $error = 'Please enter a valid phone number or leave it blank.';
    } elseif (strlen($subject) < 3) {
        $error = 'Please enter a subject.';
    } elseif (strlen($message) < 10) {
        $error = 'Please enter a message of at least 10 characters.';
    } else {
        $conn = getDBConnection();
        $conn->query("CREATE TABLE IF NOT EXISTS messages (
            message_id INT AUTO_INCREMENT PRIMARY KEY,
            full_name VARCHAR(100) NOT NULL,
            email VARCHAR(100) NOT NULL,
            phone VARCHAR(20),
            subject VARCHAR(150) NOT NULL,
            message TEXT NOT NULL,
            status ENUM('new','read','replied') DEFAULT 'new',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
        $stmt = $conn->prepare("INSERT INTO messages (full_name, email, phone, subject, message) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param('sssss', $full_name, $email, $phone, $subject, $message);
        if ($stmt->execute()) {
            $success = 'Thank you. Your message has been sent to BnB Restaurant. We will reply by email.';
            $full_name = $email = $phone = $subject = $message = '';
        } else {
            $error = 'We could not save your message. Please try again.';
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact &amp; Support - BnB Restaurant</title>
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
                <li><a href="reservations.php">Reservations</a></li>
                <li><a href="about.php">About</a></li>
                <li><a href="contact.php" class="active">Contact</a></li>
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
            <div class="hamburger">
                <span></span>
                <span></span>
                <span></span>
            </div>
        </div>
    </nav>

    <section class="features" style="padding: 3rem 0;">
        <div class="container">
            <h1 class="text-center" style="margin-bottom: 0.5rem;">Contact &amp; Support</h1>
            <p class="text-center" style="margin-bottom: 2rem;">Send a public enquiry. Staff read messages inside the admin workspace.</p>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 2rem; align-items: start;">
                <div style="background: white; padding: 2rem; border-radius: 10px; box-shadow: 0 3px 10px rgba(0,0,0,0.1);">
                    <h2 style="color: var(--primary-color); margin-bottom: 1rem;">Visit BnB Restaurant</h2>
                    <p><strong>Address:</strong> Plot 14, Ntinda Road, Kampala</p>
                    <p><strong>Phone:</strong> +256 414 672 180</p>
                    <p><strong>Email:</strong> info@bnbrestaurant.com</p>
                    <p><strong>Hours:</strong> Monday – Sunday, 7:00 AM – 10:00 PM</p>
                    <p style="margin-top: 1rem;">For orders and table bookings, use the <a href="login.php">login</a> after you register.</p>
                </div>

                <div class="form-container" style="margin: 0; max-width: none;">
                    <h2 class="text-center">Send a message</h2>
                    <?php if ($error): ?>
                        <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
                    <?php endif; ?>
                    <?php if ($success): ?>
                        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                    <?php endif; ?>
                    <form id="contactForm" method="POST" action="contact.php" novalidate>
                        <div class="form-group">
                            <label for="full_name">Full name *</label>
                            <input type="text" id="full_name" name="full_name" value="<?php echo htmlspecialchars($full_name ?? ''); ?>" required>
                            <small class="field-error" id="full_name_error"></small>
                        </div>
                        <div class="form-group">
                            <label for="email">Email *</label>
                            <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($email ?? ''); ?>" required>
                            <small class="field-error" id="email_error"></small>
                        </div>
                        <div class="form-group">
                            <label for="phone">Phone</label>
                            <input type="tel" id="phone" name="phone" value="<?php echo htmlspecialchars($phone ?? ''); ?>" placeholder="07XXXXXXXX">
                            <small class="field-error" id="phone_error"></small>
                        </div>
                        <div class="form-group">
                            <label for="subject">Subject *</label>
                            <input type="text" id="subject" name="subject" value="<?php echo htmlspecialchars($subject ?? ''); ?>" required>
                            <small class="field-error" id="subject_error"></small>
                        </div>
                        <div class="form-group">
                            <label for="message">Message *</label>
                            <textarea id="message" name="message" rows="5" required><?php echo htmlspecialchars($message ?? ''); ?></textarea>
                            <small class="field-error" id="message_error"></small>
                        </div>
                        <button type="submit" class="btn btn-primary" style="width: 100%;">Send message</button>
                    </form>
                </div>
            </div>
        </div>
    </section>

    <footer class="footer">
        <div class="container">
            <div class="footer-bottom">
                <p>&copy; 2026 BnB Restaurant. All rights reserved.</p>
            </div>
        </div>
    </footer>
    <script src="assets/js/main.js"></script>
    <script>
        document.getElementById('contactForm').addEventListener('submit', validateContactForm);
    </script>
</body>
</html>
