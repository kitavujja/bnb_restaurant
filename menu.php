<?php
require_once 'config/database.php';
require_once 'config/auth.php';

$conn = getDBConnection();
$projectRoot = __DIR__;

function resolveImagePath($rawPath, $projectRoot) {
    $placeholder = 'assets/images/placeholder.jpg';

    if (empty($rawPath)) {
        return $placeholder;
    }

    // Absolute URL or already inside assets/
    if (strpos($rawPath, 'http') === 0 || strpos($rawPath, 'assets/') === 0) {
        $candidateWeb = $rawPath;
        $candidateFs  = $projectRoot . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $rawPath);
        if (@file_exists($candidateFs)) {
            return $candidateWeb;
        }
    }

    $baseName = basename($rawPath);
    $webDir   = 'assets/images/';
    $fsDir    = $projectRoot . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'images' . DIRECTORY_SEPARATOR;

    $candidates = [
        $baseName,
        pathinfo($baseName, PATHINFO_FILENAME) . '.png',
        pathinfo($baseName, PATHINFO_FILENAME) . '.jpg',
        pathinfo($baseName, PATHINFO_FILENAME) . '.jpeg',
        $baseName . '.png',
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

// Filters
$category = $_GET['category'] ?? 'all';
$search   = $_GET['search'] ?? '';

$sql = "SELECT item_id, name, description, price, image_path, category 
        FROM menu_items 
        WHERE available = 1";

if ($category !== 'all') {
    $category = mysqli_real_escape_string($conn, $category);
    $sql .= " AND category = '$category'";
}

if (!empty($search)) {
    $search = mysqli_real_escape_string($conn, $search);
    $sql .= " AND (name LIKE '%$search%' OR description LIKE '%$search%')";
}

$sql .= " ORDER BY category, name";
$result = mysqli_query($conn, $sql);

// Get categories
$categories = [];
$categories_result = mysqli_query($conn, "SELECT DISTINCT category FROM menu_items WHERE available = 1 ORDER BY category");
if ($categories_result) {
    while ($row = mysqli_fetch_assoc($categories_result)) {
        $categories[] = $row['category'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Menu - BnB Restaurant</title>
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
                <li><a href="menu.php" class="active">Menu</a></li>
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
        </div>
    </nav>

    <section class="features" style="padding: 2rem 0;">
        <div class="container">
            <h2>Our Menu</h2>

            <!-- Search and Filter -->
            <div style="margin: 2rem 0; display: flex; gap: 1rem; flex-wrap: wrap; align-items: center;">
                <form method="GET" style="flex: 1; min-width: 200px; display: flex; gap: 0.5rem;">
                    <input type="hidden" name="category" value="<?= htmlspecialchars($category) ?>">
                    <input type="text" name="search" placeholder="Search menu..." 
                           value="<?= htmlspecialchars($search) ?>"
                           style="flex: 1; padding: 10px; border: 1px solid #ddd; border-radius: 5px;">
                    <button type="submit" class="btn btn-small">Search</button>
                    <?php if (!empty($search) || $category !== 'all'): ?>
                        <a href="menu.php" class="btn btn-secondary" style="padding: 10px 15px;">Clear</a>
                    <?php endif; ?>
                </form>

                <select id="categoryFilter" onchange="filterCategory()" 
                        style="padding: 10px; border: 1px solid #ddd; border-radius: 5px; min-width: 150px;">
                    <option value="all" <?= $category === 'all' ? 'selected' : '' ?>>All Categories</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= htmlspecialchars($cat) ?>" <?= $category === $cat ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cat) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Menu Items -->
            <div class="menu-grid" id="menuGrid">
                <?php
                $item_count = 0;
                if ($result && mysqli_num_rows($result) > 0) {
                    while ($item = mysqli_fetch_assoc($result)) {
                        $item_count++;
                        $imagePath = resolveImagePath($item['image_path'] ?? '', $projectRoot);
                        ?>
                        <div class="menu-item" id="item-<?= (int)$item['item_id'] ?>">
                            <img src="<?= htmlspecialchars($imagePath) ?>" 
                                 alt="<?= htmlspecialchars($item['name']) ?>"
                                 onerror="this.onerror=null; this.src='assets/images/placeholder.jpg'"
                                 style="width: 100%; height: 200px; object-fit: cover; background-color: #f5f5f5;">
                            <h3><?= htmlspecialchars($item['name']) ?></h3>
                            <p><?= htmlspecialchars($item['description']) ?></p>
                            <div class="item-footer">
                                <span class="price">UGX <?= number_format($item['price'], 0) ?></span>
                                <?php if (isLoggedIn() && getUserRole() === 'customer'): ?>
                                    <button onclick="addToCart(<?= (int)$item['item_id'] ?>, '<?= addslashes(htmlspecialchars($item['name'])) ?>', <?= (float)$item['price'] ?>)" 
                                            class="btn btn-small">Add to Cart</button>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php
                    }
                } else {
                    echo '<p style="grid-column: 1/-1; text-align: center; padding: 2rem; color: #666;">No items found. Try a different search or category.</p>';
                }
                ?>
            </div>

            <?php if ($item_count > 0): ?>
                <p style="text-align: center; margin-top: 2rem; color: #666;">Showing <?= $item_count ?> item(s)</p>
            <?php endif; ?>
        </div>
    </section>

    <!-- Cart Modal -->
    <div id="cartModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 2000;">
        <div style="background: white; max-width: 500px; margin: 5% auto; padding: 2rem; border-radius: 10px;">
            <h2>Shopping Cart</h2>
            <div id="cartItems"></div>
            <div style="margin-top: 1rem; display: flex; justify-content: space-between; align-items: center;">
                <strong>Total: <span id="cartTotal">UGX 0</span></strong>
                <button onclick="checkout()" class="btn btn-primary">Checkout</button>
            </div>
            <button onclick="closeCart()" class="btn btn-secondary" style="margin-top: 1rem; width: 100%;">Close</button>
        </div>
    </div>

    <footer class="footer">
        <div class="container">
            <div class="footer-bottom">
                <p>&copy; 2026 BnB Restaurant. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <script>
        let cart = JSON.parse(localStorage.getItem('cart')) || [];

        function filterCategory() {
            const category = document.getElementById('categoryFilter').value;
            const search = new URLSearchParams(window.location.search).get('search') || '';
            let url = 'menu.php?category=' + encodeURIComponent(category);
            if (search) {
                url += '&search=' + encodeURIComponent(search);
            }
            window.location.href = url;
        }

        function addToCart(itemId, name, price) {
            const existingItem = cart.find(item => item.id === itemId);
            if (existingItem) {
                existingItem.quantity++;
            } else {
                cart.push({id: itemId, name: name, price: price, quantity: 1});
            }
            localStorage.setItem('cart', JSON.stringify(cart));
            alert(name + ' added to cart!');
        }

        function showCart() {
            const modal = document.getElementById('cartModal');
            const itemsDiv = document.getElementById('cartItems');
            let html = '';
            let total = 0;

            cart.forEach(item => {
                total += item.price * item.quantity;
                html += `<div style="display: flex; justify-content: space-between; padding: 0.5rem 0; border-bottom: 1px solid #ddd;">
                    <span>${item.name} x${item.quantity}</span>
                    <span>UGX ${(item.price * item.quantity).toLocaleString()}</span>
                </div>`;
            });

            itemsDiv.innerHTML = html || '<p>Cart is empty</p>';
            document.getElementById('cartTotal').textContent = 'UGX ' + total.toLocaleString();
            modal.style.display = 'block';
        }

        function closeCart() {
            document.getElementById('cartModal').style.display = 'none';
        }

        function checkout() {
            if (cart.length === 0) {
                alert('Cart is empty!');
                return;
            }
            window.location.href = 'checkout.php';
        }

        <?php if (isLoggedIn() && getUserRole() === 'customer'): ?>
            document.body.insertAdjacentHTML('beforeend', 
                '<button onclick="showCart()" style="position: fixed; bottom: 20px; right: 20px; background: var(--accent-color); color: white; border: none; padding: 15px; border-radius: 50%; cursor: pointer; box-shadow: 0 3px 10px rgba(0,0,0,0.3); font-size: 1.2rem;">🛒</button>');
        <?php endif; ?>
    </script>
    <script src="assets/js/main.js"></script>
</body>
</html>