<?php
require_once 'config/database.php';
require_once 'config/auth.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $full_name = $_POST['full_name'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $role = $_POST['role'] ?? 'customer';
    
    if (empty($username) || empty($email) || empty($password) || empty($full_name)) {
        $error = 'Please fill all required fields';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters';
    } else {
        $conn = getDBConnection();
        $username = trim($username);
        $email = trim($email);
        $full_name = trim($full_name);
        $phone = trim($phone);
        $role = in_array($role, ['customer', 'cashier'], true) ? $role : 'customer';

        $check = $conn->prepare("SELECT user_id FROM users WHERE username = ? OR email = ? LIMIT 1");
        $check->bind_param('ss', $username, $email);
        $check->execute();
        $check->store_result();

        if ($check->num_rows > 0) {
            $error = 'Username or email already exists';
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $insert = $conn->prepare("INSERT INTO users (username, email, password, full_name, phone, role) VALUES (?, ?, ?, ?, ?, ?)");
            $insert->bind_param('ssssss', $username, $email, $hashed_password, $full_name, $phone, $role);
            if ($insert->execute()) {
                $success = 'Registration successful! You can now login.';
                header('Location: login.php?registered=1');
                exit();
            } else {
                $error = 'Registration failed. Please try again.';
            }
            $insert->close();
        }
        $check->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - BnB Restaurant</title>
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
                <li><a href="contact.php">Contact</a></li>
                <li><a href="login.php">Login</a></li>
            </ul>
        </div>
    </nav>

    <div class="form-container">
        <h2 class="text-center">Register</h2>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <form method="POST" action="" onsubmit="return validateForm()">
            <div class="form-group">
                <label for="full_name">Full Name *</label>
                <input type="text" id="full_name" name="full_name" required>
            </div>
            
            <div class="form-group">
                <label for="username">Username *</label>
                <input type="text" id="username" name="username" required>
            </div>
            
            <div class="form-group">
                <label for="email">Email *</label>
                <input type="email" id="email" name="email" required>
            </div>
            
            <div class="form-group">
                <label for="phone">Phone</label>
                <input type="tel" id="phone" name="phone">
            </div>
            
            <div class="form-group">
                <label for="password">Password *</label>
                <input type="password" id="password" name="password" required minlength="6">
            </div>
            
            <div class="form-group">
                <label for="confirm_password">Confirm Password *</label>
                <input type="password" id="confirm_password" name="confirm_password" required>
            </div>
            
            <div class="form-group">
                <label for="role">Account Type</label>
                <select id="role" name="role">
                    <option value="customer">Customer</option>
                    <option value="cashier">Cashier (Staff)</option>
                </select>
            </div>
            
            <button type="submit" class="btn btn-primary" style="width: 100%;">Register</button>
        </form>
        
        <p class="text-center mt-2">
            Already have an account? <a href="login.php">Login here</a>
        </p>
    </div>

    <script>
        function validateForm() {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            if (password !== confirmPassword) {
                alert('Passwords do not match!');
                return false;
            }
            
            if (password.length < 6) {
                alert('Password must be at least 6 characters!');
                return false;
            }
            
            return true;
        }
    </script>
    <script src="assets/js/main.js"></script>
</body>
</html>


