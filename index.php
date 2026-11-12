<?php
require_once 'config/database.php';
require_once 'config/auth.php';
$projectRoot = __DIR__;
$cssVersion = @filemtime($projectRoot . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'css' . DIRECTORY_SEPARATOR . 'style.css') ?: time();

// Resolve an image path to an existing file under assets/images or return placeholder
function resolveImagePathHome($rawPath, $projectRoot) {
    $placeholder = 'assets/images/placeholder.jpg';
    if (empty($rawPath)) {
        return $placeholder;
    }
    if (strpos($rawPath, 'http') === 0 || strpos($rawPath, 'assets/') === 0) {
        $candidateWeb = $rawPath;
        $candidateFs = $projectRoot . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $rawPath);
        if (@file_exists($candidateFs)) {
            return $candidateWeb;
        }
    }
    $baseName = basename($rawPath);
    $webDir = 'assets/images/';
    $fsDir = $projectRoot . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'images' . DIRECTORY_SEPARATOR;
    $candidates = [
        $baseName,
        pathinfo($baseName, PATHINFO_FILENAME) . '.jpg',
        pathinfo($baseName, PATHINFO_FILENAME) . '.jpeg',
        pathinfo($baseName, PATHINFO_FILENAME) . '.jpg.jpeg',
        $baseName . '.jpg',
        $baseName . '.jpeg',
    ];
    foreach ($candidates as $name) {
        $fsPath = $fsDir . $name;
        if (@file_exists($fsPath)) {
            return $webDir . $name;
        }
    }
    return $placeholder;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BnB Restaurant - Authentic Ugandan Cuisine</title>
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo $cssVersion; ?>">
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar">
        <div class="container">
            <div class="logo">
                <a href="index.php" class="brand">
                    <img src="assets/images/logo.svg" alt="BnB Restaurant logo">
                    <h1>BnB Restaurant</h1>
                </a>
            </div>
            <ul class="nav-menu">
                <li><a href="index.php" class="active">Home</a></li>
                <li><a href="about.php">About</a></li>
                <li><a href="menu.php">Menu</a></li>
                <li><a href="reservations.php">Reservations</a></li>
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
            <div class="hamburger">
                <span></span>
                <span></span>
                <span></span>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero">
        <div class="hero-content">
            <h1>Welcome to BnB Restaurant</h1>
            <p>Fresh local Ugandan meals, table bookings, and a staff workspace in one place</p>
            <a href="menu.php" class="btn btn-primary">View Menu</a>
            <a href="reservations.php" class="btn btn-secondary">Make Reservation</a>
            <a href="login.php" class="btn btn-secondary">Login</a>
        </div>
    </section>

    <section class="features" style="padding-bottom: 1rem;">
        <div class="container">
            <h2>Our Mission</h2>
            <p class="text-center" style="max-width: 760px; margin: 0 auto 1rem;">
                BnB Restaurant serves reliable Ugandan food from Plot 14, Ntinda Road, Kampala.
                The public website tells our story. The web application lets guests order and book tables,
                while Patricia Namuli (administrator) and Samuel Kato (cashier) run orders, menu records, messages, and sales reports.
            </p>
            <p class="text-center">
                <a href="about.php" class="btn btn-primary">About Us</a>
                <a href="contact.php" class="btn btn-secondary" >Contact & support</a>
            </p>
        </div>
    </section>

    <!-- Features Section -->
    <section class="features">
        <div class="container">
            <h2>Why Choose Us?</h2>
            <div class="features-grid">
                <div class="feature-card">
                    <div class="feature-icon"></div>
                    <h3>Authentic Food</h3>
                    <p>Traditional Ugandan dishes prepared with love</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon"></div>
                    <h3>Fair Prices</h3>
                    <p>Affordable meals for everyone</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon"></div>
                    <h3>Fast Service</h3>
                    <p>Quick and efficient service</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon"></div>
                    <h3>Quality Assured</h3>
                    <p>Fresh ingredients, great taste</p>
                </div>
            </div>
        </div>
    </section>

<section class="popular-items">
    <div class="container">
        <h2>Popular Dishes</h2>
        <div class="menu-grid">
            <?php
            $conn = getDBConnection();

            if (!$conn) {
                echo '<p>Unable to load popular dishes at the moment.</p>';
            } else {
                $sql = "SELECT item_id, name, description, price, image_path 
                        FROM menu_items 
                        WHERE available = 1 
                        ORDER BY item_id 
                        LIMIT 6";

                $result = mysqli_query($conn, $sql);

                if ($result && mysqli_num_rows($result) > 0) {
                    // Make sure $projectRoot is defined (adjust path as needed)
                    $projectRoot = $projectRoot ?? dirname(__DIR__); // or define it higher up

                    while ($item = mysqli_fetch_assoc($result)) {
                        $imagePath = resolveImagePathHome($item['image_path'] ?? '', $projectRoot);

                        echo '<div class="menu-item">';
                        echo '<img src="' . htmlspecialchars($imagePath) . '" alt="' . htmlspecialchars($item['name']) . '">';
                        echo '<h3>' . htmlspecialchars($item['name']) . '</h3>';
                        echo '<p>' . htmlspecialchars($item['description']) . '</p>';
                        echo '<div class="item-footer">';
                        echo '<span class="price">UGX ' . number_format($item['price'], 0) . '</span>';
                        echo '<a href="menu.php#item-' . (int)$item['item_id'] . '" class="btn btn-small">View</a>';
                        echo '</div>';
                        echo '</div>';
                    }
                } else {
                    echo '<p>No popular dishes available right now.</p>';
                }
            }
            ?>
        </div>
    </div>
</section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-section">
                    
                 
                </div>
                
                <div class="footer-section">
             
                </div>
            </div>
            <div class="footer-bottom">
                <p style="text-align: center;"> &copy; 2026 BnB Restaurant. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <script src="assets/js/main.js"></script>
</body>
</html>


