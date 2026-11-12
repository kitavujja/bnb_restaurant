<?php
require_once 'config/database.php';
require_once 'config/auth.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About Us - BnB Restaurant</title>
    <link rel="stylesheet" href="assets/css/style.css">
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
                <li><a href="index.php">Home</a></li>
                <li><a href="about.php" class="active">About</a></li>
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

    <!-- About Section -->
    <section class="features" style="padding: 3rem 0;">
        <div class="container">
            <div style="max-width: 900px; margin: 0 auto;">
                <h1 class="text-center" style="font-size: 2.5rem; margin-bottom: 2rem;">About BnB Restaurant</h1>
                
                <div style="background: white; padding: 2rem; border-radius: 10px; box-shadow: 0 3px 10px rgba(0,0,0,0.1); margin-bottom: 2rem;">
                    <h2 style="color: var(--primary-color); margin-bottom: 1rem;">Our Story</h2>
                    <p style="line-height: 1.8; font-size: 1.1rem; color: #555;">
                        BnB Restaurant is a small dining house on Plot 14, Ntinda Road, Kampala. It opened to give nearby
                        offices and families a dependable place for matoke, luwombo, rolex, and other Ugandan meals.
                        For several years the floor team wrote orders in notebooks, called reservations across the counter,
                        and totalled the day's sales after closing. Those paper records could not keep up once lunch and
                        weekend traffic grew.
                    </p>
                    <p style="line-height: 1.8; font-size: 1.1rem; color: #555; margin-top: 1rem;">
                        This case study follows that bottleneck. The public website now explains the restaurant, the menu,
                        and how to reach the team. The web application, reached from Login and Register, is the working
                        system: customers place orders and book tables, cashier Samuel Kato updates payments, and
                        administrator Patricia Namuli maintains the menu, reads public messages, and exports sales reports.
                    </p>
                </div>

                <div style="background: white; padding: 2rem; border-radius: 10px; box-shadow: 0 3px 10px rgba(0,0,0,0.1); margin-bottom: 2rem;">
                    <h2 style="color: var(--primary-color); margin-bottom: 1rem;">Leadership</h2>
                    <p style="line-height: 1.8; font-size: 1.1rem; color: #555;">
                        Patricia Namuli manages the restaurant and uses the administrator account to confirm records,
                        edit the menu, and review enquiries. Samuel Kato works the counter and uses the cashier account
                        to take walk-in orders, mark payments, and clear unpaid tickets. Guests use their own accounts
                        for the menu, checkout, profile, and reservations.
                    </p>
                </div>

                <div style="background: white; padding: 2rem; border-radius: 10px; box-shadow: 0 3px 10px rgba(0,0,0,0.1); margin-bottom: 2rem;">
                    <h2 style="color: var(--primary-color); margin-bottom: 1rem;">Our Mission</h2>
                    <p style="line-height: 1.8; font-size: 1.1rem; color: #555;">
                        To serve authentic Ugandan dishes made with fresh, locally sourced ingredients. We are committed to:
                    </p>
                    <ul style="line-height: 2; font-size: 1.1rem; color: #555; margin-left: 2rem; margin-top: 1rem;">
                        <li>Preserving traditional Ugandan recipes and cooking methods</li>
                        <li>Using fresh, locally sourced ingredients</li>
                        <li>Providing excellent customer service</li>
                        <li>Offering fair and affordable prices</li>
                        <li>Creating a welcoming atmosphere for all guests</li>
                    </ul>
                </div>

                <div style="background: white; padding: 2rem; border-radius: 10px; box-shadow: 0 3px 10px rgba(0,0,0,0.1); margin-bottom: 2rem;">
                    <h2 style="color: var(--primary-color); margin-bottom: 1rem;">Our Menu</h2>
                    <p style="line-height: 1.8; font-size: 1.1rem; color: #555;">
                        Our menu features a wide variety of authentic Ugandan dishes, from traditional favorites like 
                        <strong>Matoke</strong> and <strong>Luwombo</strong> to popular street food like <strong>Rolex</strong>. 
                        Each dish is prepared with care and served with pride. We offer everything from hearty main courses 
                        to quick bites, refreshing beverages, and delicious breakfast options.
                    </p>
                </div>

                <div style="background: white; padding: 2rem; border-radius: 10px; box-shadow: 0 3px 10px rgba(0,0,0,0.1); margin-bottom: 2rem;">
                    <h2 style="color: var(--primary-color); margin-bottom: 1rem;">Why Choose Us?</h2>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem; margin-top: 1rem;">
                        <div>
                            <div style="font-size: 2rem; margin-bottom: 0.5rem;"></div>
                            <h3>Authentic Food</h3>
                            <p>Traditional recipes passed down through generations</p>
                        </div>
                        <div>
                            <div style="font-size: 2rem; margin-bottom: 0.5rem;"></div>
                            <h3>Fair Prices</h3>
                            <p>Affordable meals for everyone</p>
                        </div>
                        <div>
                            <div style="font-size: 2rem; margin-bottom: 0.5rem;"></div>
                            <h3>Fast Service</h3>
                            <p>Quick and efficient ordering and delivery</p>
                        </div>
                        <div>
                            <div style="font-size: 2rem; margin-bottom: 0.5rem;"></div>
                            <h3>Quality Assured</h3>
                            <p>Fresh ingredients, great taste</p>
                        </div>
                    </div>
                </div>

                <div style="background: white; padding: 2rem; border-radius: 10px; box-shadow: 0 3px 10px rgba(0,0,0,0.1); margin-bottom: 2rem;">
                    <h2 style="color: var(--primary-color); margin-bottom: 1rem;">Contact Us</h2>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem; margin-top: 1rem;">
                        <div>
                            <h3> Location</h3>
                            <p>Plot 14, Ntinda Road, Kampala</p>
                            <p>Visit us for a memorable dining experience</p>
                        </div>
                        <div>
                            <h3> Email</h3>
                            <p>info@bnbrestaurant.com</p>
                            <p>We'd love to hear from you!</p>
                        </div>
                        <div>
                            <h3> Phone</h3>
                            <p>+256 414 672 180</p>
                            <p>Call us for reservations</p>
                        </div>
                        <div>
                            <h3>Hours</h3>
                            <p>Monday - Sunday</p>
                            <p>7:00 AM - 10:00 PM</p>
                        </div>
                    </div>
                </div>

                 
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

