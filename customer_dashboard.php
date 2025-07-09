<?php
session_start();
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'customer') {
    header('Location: login.php');
    exit;
}

$page_title = "Dashboard - Rimbunan Cafe";
include 'config/database.php';

// Handle cart checkout
if (isset($_POST['checkout'])) {
    // Prevent duplicate submissions
    if (isset($_SESSION['last_checkout_time']) && (time() - $_SESSION['last_checkout_time']) < 5) {
        echo "<script>alert('Please wait before placing another order.'); location.reload();</script>";
        exit;
    }
    
    $_SESSION['last_checkout_time'] = time();
    
    $address = trim($_POST['delivery_address']);
    $payment_method = $_POST['payment_method'];
    $cart_items = json_decode($_POST['cart_items'], true);
    $total_price = $_POST['total_price'];
    $payment_proof = null;
    
    // Validate delivery address format
    if (empty($address)) {
        echo "<script>alert('Delivery address is required.'); history.back();</script>";
        exit;
    }
    
    // Basic address format validation
    $address_parts = explode(',', $address);
    if (count($address_parts) < 3 || !preg_match('/\b\d{5}\b/', $address)) {
        echo "<script>alert('Please enter address in correct format: [Street Number] [Street Name], [Area/District], [City], [Postal Code]'); history.back();</script>";
        exit;
    }
    
    // Total price already includes delivery fee from frontend calculation
    $total_price = floatval($total_price);
    
    // Validate cart items exist and are selected
    $selected_items = array_filter($cart_items, function($item) {
        return isset($item['selected']) && $item['selected'] === true;
    });
    
    if (empty($selected_items)) {
        echo "<script>alert('Please select items to checkout.'); location.reload();</script>";
        exit;
    }
    
    // Create order
    $stmt = $pdo->prepare("INSERT INTO orders (cust_id, order_date, order_status, total_price, delivery_status, payment_method, payment_proof, delivery_address) VALUES (?, NOW(), 'Pending', ?, 0, ?, ?, ?)");
    $stmt->execute([$_SESSION['user_id'], $total_price, $payment_method, $payment_proof, $address]);
    $order_id = $pdo->lastInsertId();
    
    // Add order details
    foreach ($selected_items as $item) {
        if (isset($item['id']) && isset($item['quantity']) && $item['quantity'] > 0) {
            $stmt = $pdo->prepare("INSERT INTO order_details (orders_id, product_id, qty) VALUES (?, ?, ?)");
            $stmt->execute([$order_id, $item['id'], $item['quantity']]);
        }
    }
    
    // Handle QR payment proof upload
    if ($payment_method === 'qr' && isset($_FILES['payment_proof'])) {
        $upload_dir = 'uploads/payment_proofs/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        $file_name = $order_id . '_' . time() . '_' . $_FILES['payment_proof']['name'];
        $upload_path = $upload_dir . $file_name;
        
        if (move_uploaded_file($_FILES['payment_proof']['tmp_name'], $upload_path)) {
            // Update order with payment proof path
            $stmt = $pdo->prepare("UPDATE orders SET payment_proof = ? WHERE order_id = ?");
            $stmt->execute([$upload_path, $order_id]);
        }
    }
    
    // Clear the checkout session flag after successful order
    unset($_SESSION['last_checkout_time']);
    
    // Redirect to prevent form resubmission
    header('Location: customer_dashboard.php?order_placed=1');
    exit;
}

// Check if order was just placed
$order_placed = isset($_GET['order_placed']);

// Get user info
$stmt = $pdo->prepare("SELECT * FROM customer WHERE cust_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

// Get user orders
$stmt = $pdo->prepare("SELECT o.*, GROUP_CONCAT(CONCAT(p.product_name, ' (', od.qty, ')') SEPARATOR ', ') as items 
                      FROM orders o 
                      LEFT JOIN order_details od ON o.order_id = od.orders_id 
                      LEFT JOIN product p ON od.product_id = p.product_id 
                      WHERE o.cust_id = ? 
                      GROUP BY o.order_id 
                      ORDER BY o.order_date DESC");
$stmt->execute([$_SESSION['user_id']]);
$orders = $stmt->fetchAll();

// Get products for menu
$stmt = $pdo->prepare("SELECT * FROM product WHERE product_status = 'Available' ORDER BY product_category, product_name");
$stmt->execute();
$products = $stmt->fetchAll();

// Get categories
$stmt = $pdo->prepare("SELECT * FROM categories WHERE category_status = 'Active' ORDER BY category_name");
$stmt->execute();
$categories = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link rel="stylesheet" href="css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>

<div class="dashboard">
    <div class="dashboard-header">
        <div class="container">
            <div class="dashboard-nav">
                <div class="logo">🍔 Rimbunan Cafe</div>
                <div class="nav-actions">
                    <button class="btn btn-secondary" onclick="refreshPage()" style="margin-right: 1rem;">🔄 Refresh</button>
                    <button class="btn btn-secondary" onclick="showTab('profile')" style="margin-right: 1rem;">👤 Profile</button>
                    <a href="customer_orders.php" class="btn btn-secondary" style="margin-right: 1rem;">📋 My Orders</a>
                    <span>Welcome, <?php echo $_SESSION['username']; ?>!</span>
                    <span class="btn btn-secondary" onclick="showTab('cart')">🛒 Cart (<span id="cart-count">0</span>)</span>
                    <a href="logout.php" class="btn btn-danger">Logout</a>
                </div>
            </div>
        </div>
    </div>
    
    <div class="container">
        <div class="dashboard-content">
            <div class="dashboard-tabs">
                <button class="tab-btn active" onclick="showTab('home')">🏠 Home</button>
                <button class="tab-btn" onclick="showTab('menu')">📋 Menu</button>
                <button class="tab-btn" onclick="showTab('about')">ℹ️ About Us</button>
                <button class="tab-btn" onclick="showTab('cart')">🛒 Cart</button>
            </div>
            
            <!-- Home Tab -->
            <div id="home" class="tab-content active">
                <!-- Hero Section -->
                <div style="background: linear-gradient(135deg, #8B4513 0%, #A0522D 100%); color: white; padding: 4rem 2rem; border-radius: 20px; text-align: center; margin-bottom: 3rem; position: relative; overflow: hidden;">
                    <div style="position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: url('https://images.pexels.com/photos/1633578/pexels-photo-1633578.jpeg?auto=compress&cs=tinysrgb&w=1260&h=750&dpr=1') center/cover; opacity: 0.2;"></div>
                    <div style="position: relative; z-index: 2;">
                        <h1 style="font-size: 3rem; margin-bottom: 1rem; text-shadow: 2px 2px 4px rgba(0,0,0,0.3);">🍔 Welcome to Rimbunan Cafe</h1>
                        <p style="font-size: 1.25rem; margin-bottom: 2rem; opacity: 0.9;">Serving the finest burgers, coffee, and comfort food since 2020</p>
                        <button class="btn btn-primary" onclick="showTab('menu')" style="font-size: 1.1rem; padding: 1rem 2rem; background: #FFD700; color: #8B4513; border: none; box-shadow: 0 4px 15px rgba(255,215,0,0.3);">
                            🍽️ Order Now
                        </button>
                    </div>
                </div>
                
                <!-- Features Section -->
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 2rem; margin-bottom: 3rem;">
                    <div style="background: white; padding: 2rem; border-radius: 15px; text-align: center; box-shadow: 0 5px 15px rgba(0,0,0,0.1);">
                        <div style="font-size: 3rem; margin-bottom: 1rem;">🚚</div>
                        <h3 style="color: #8B4513; margin-bottom: 1rem;">Fast Delivery</h3>
                        <p style="color: #666;">Quick and reliable delivery to your doorstep</p>
                    </div>
                    <div style="background: white; padding: 2rem; border-radius: 15px; text-align: center; box-shadow: 0 5px 15px rgba(0,0,0,0.1);">
                        <div style="font-size: 3rem; margin-bottom: 1rem;">🌟</div>
                        <h3 style="color: #8B4513; margin-bottom: 1rem;">Quality Food</h3>
                        <p style="color: #666;">Fresh ingredients and authentic flavors</p>
                    </div>
                    <div style="background: white; padding: 2rem; border-radius: 15px; text-align: center; box-shadow: 0 5px 15px rgba(0,0,0,0.1);">
                        <div style="font-size: 3rem; margin-bottom: 1rem;">💳</div>
                        <h3 style="color: #8B4513; margin-bottom: 1rem;">Easy Payment</h3>
                        <p style="color: #666;">Cash on delivery or QR code payment</p>
                    </div>
                </div>
                
                <!-- Social Media Section -->
                <div style="background: white; padding: 2rem; border-radius: 15px; box-shadow: 0 5px 15px rgba(0,0,0,0.1); margin-bottom: 3rem;">
                    <h2 style="text-align: center; color: #8B4513; margin-bottom: 2rem;">🌐 Connect With Us</h2>
                    <div style="display: flex; justify-content: center; gap: 2rem; flex-wrap: wrap;">
                        <a href="https://www.instagram.com/rimbunan.kafe?igsh=MWM4MHNyZjVmeGJjYQ==" target="_blank" style="display: flex; align-items: center; gap: 0.5rem; padding: 1rem 2rem; background: linear-gradient(45deg, #f09433 0%,#e6683c 25%,#dc2743 50%,#cc2366 75%,#bc1888 100%); color: white; text-decoration: none; border-radius: 10px; transition: transform 0.3s;" onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform='translateY(0)'">
                            <span style="font-size: 1.5rem;">📷</span>
                            Instagram
                        </a>
                        <a href="https://wa.link/psbeam" target="_blank" style="display: flex; align-items: center; gap: 0.5rem; padding: 1rem 2rem; background: #25d366; color: white; text-decoration: none; border-radius: 10px; transition: transform 0.3s;" onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform='translateY(0)'">
                            <span style="font-size: 1.5rem;">💬</span>
                            WhatsApp
                        </a>
                        <a href="https://www.tiktok.com/@rimbunan.kafe?_t=ZS-8xtDjBLaPGp&_r=1" target="_blank" style="display: flex; align-items: center; gap: 0.5rem; padding: 1rem 2rem; background: #000; color: white; text-decoration: none; border-radius: 10px; transition: transform 0.3s;" onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform='translateY(0)'">
                            <span style="font-size: 1.5rem;">🎵</span>
                            TikTok
                        </a>
                    </div>
                </div>
                
                <!-- Order Status Section -->
                <div style="background: white; padding: 2rem; border-radius: 15px; box-shadow: 0 5px 15px rgba(0,0,0,0.1);">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
                        <h2 style="color: #8B4513; margin: 0;">📋 Recent Orders</h2>
                        <a href="customer_orders.php" class="btn btn-primary">View All Orders</a>
                    </div>
                    <?php if (empty($orders)): ?>
                        <div style="text-align: center; padding: 2rem; color: #666;">
                            <div style="font-size: 4rem; margin-bottom: 1rem;">🍽️</div>
                            <h3>No orders yet</h3>
                            <p>Start by browsing our delicious menu!</p>
                            <button class="btn btn-primary" onclick="showTab('menu')" style="margin-top: 1rem;">
                                🍽️ Browse Menu
                            </button>
                        </div>
                    <?php else: ?>
                        <?php foreach (array_slice($orders, 0, 3) as $order): ?>
                            <div class="order-status">
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                                    <h3>Order #<?php echo $order['order_id']; ?></h3>
                                    <span class="status-badge status-<?php echo strtolower(str_replace(' ', '', $order['order_status'])); ?>">
                                        <?php echo $order['order_status']; ?>
                                    </span>
                                </div>
                                <p><strong>Items:</strong> <?php echo $order['items']; ?></p>
                                <p><strong>Total:</strong> RM <?php echo number_format($order['total_price'], 2); ?></p>
                                <p><strong>Order Date:</strong> <?php echo date('M d, Y H:i', strtotime($order['order_date'])); ?></p>
                                
                                <div class="status-timeline">
                                    <div class="status-step <?php echo in_array($order['order_status'], ['Pending', 'Preparing', 'In Delivery', 'Delivered']) ? 'active' : ''; ?>">
                                        <div class="status-icon">📝</div>
                                        <span>Pending</span>
                                    </div>
                                    <div class="status-step <?php echo in_array($order['order_status'], ['Preparing', 'In Delivery', 'Delivered']) ? 'active' : ''; ?>">
                                        <div class="status-icon">👨‍🍳</div>
                                        <span>Preparing</span>
                                    </div>
                                    <div class="status-step <?php echo in_array($order['order_status'], ['In Delivery', 'Delivered']) ? 'active' : ''; ?>">
                                        <div class="status-icon">🛵</div>
                                        <span>In Delivery</span>
                                    </div>
                                    <div class="status-step <?php echo $order['order_status'] === 'Delivered' ? 'active' : ''; ?>">
                                        <div class="status-icon">✅</div>
                                        <span>Delivered</span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        
                        <?php if (count($orders) > 3): ?>
                            <div style="text-align: center; margin-top: 1rem;">
                                <a href="customer_orders.php" class="btn btn-secondary">View All <?php echo count($orders); ?> Orders</a>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Menu Tab -->
            <div id="menu" class="tab-content">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
                    <h2>🍽️ Choose Your Category</h2>
                </div>
                
                <!-- Category Selection -->
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
                    <?php foreach ($categories as $category): ?>
                        <div class="category-card" onclick="showTab('category-<?php echo $category['category_id']; ?>')" style="background: linear-gradient(135deg, #8B4513, #A0522D); color: white; padding: 2rem; border-radius: 15px; text-align: center; cursor: pointer; transition: transform 0.3s, box-shadow 0.3s; box-shadow: 0 5px 15px rgba(139,69,19,0.3);" onmouseover="this.style.transform='translateY(-5px)'; this.style.boxShadow='0 10px 25px rgba(139,69,19,0.4)'" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 5px 15px rgba(139,69,19,0.3)'">
                            <div style="font-size: 3rem; margin-bottom: 1rem;"><?php echo $category['category_icon']; ?></div>
                            <h3 style="margin: 0; font-weight: 600;"><?php echo $category['category_name']; ?></h3>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <!-- Dynamic Category Tabs -->
            <?php foreach ($categories as $category): ?>
                <div id="category-<?php echo $category['category_id']; ?>" class="tab-content">
                    <h2><?php echo $category['category_icon']; ?> <?php echo $category['category_name']; ?></h2>
                    <div class="menu-grid">
                        <?php foreach ($products as $product): ?>
                            <?php if (($product['product_category'] ?? '') === $category['category_name']): ?>
                                <div class="menu-item">
                                    <div class="menu-item-image">
                                        <?php if ($product['product_image'] && file_exists($product['product_image'])): ?>
                                            <img src="<?php echo $product['product_image']; ?>" alt="<?php echo htmlspecialchars($product['product_name']); ?>" style="width: 100%; height: 100%; object-fit: cover;">
                                        <?php else: ?>
                                            <?php echo $category['category_icon']; ?>
                                        <?php endif; ?>
                                    </div>
                                    <div class="menu-item-content">
                                        <h3><?php echo htmlspecialchars($product['product_name']); ?></h3>
                                        <p><?php echo htmlspecialchars($product['product_info']); ?></p>
                                        <div class="menu-item-price">RM <?php echo number_format($product['product_price'], 2); ?></div>
                                        <div class="menu-item-actions">
                                            <button class="btn btn-primary" onclick="addToCart('<?php echo $product['product_id']; ?>', '<?php echo htmlspecialchars($product['product_name']); ?>', '<?php echo $product['product_price']; ?>')">
                                                Add to Cart
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
            
            <!-- Cart Tab -->
            <div id="cart" class="tab-content">
                <h2>Shopping Cart</h2>
                <div id="cart-items"></div>
                
                <div class="cart-summary">
                    <div class="cart-total">
                        <span>Subtotal:</span>
                        <span id="cart-subtotal">RM 0.00</span>
                    </div>
                    <div class="cart-total">
                        <span>Delivery Fee:</span>
                        <span id="delivery-fee">RM 5.00</span>
                    </div>
                    <div class="cart-total">
                        <span>Total:</span>
                        <span id="cart-total">RM 0.00</span>
                    </div>
                    
                    <button class="btn btn-primary" onclick="openModal('checkout-modal')" style="width: 100%; margin-top: 1rem;">
                        Proceed to Checkout
                    </button>
                </div>
            </div>
            
            <!-- About Tab -->
            <div id="about" class="tab-content">
                <h2>About Rimbunan Cafe</h2>
                <div style="background: white; padding: 2rem; border-radius: 15px; box-shadow: 0 5px 15px rgba(0,0,0,0.1);">
                    <h3>🍔 Welcome to Rimbunan Cafe</h3>
                    <p>Since 2020, Rimbunan Cafe has been serving the community with the finest burgers, coffee, and comfort food. Our commitment to quality ingredients and exceptional service has made us a beloved local destination.</p>
                    
                    <h4>🌟 Our Story</h4>
                    <p>Founded with a passion for great food and community connection, Rimbunan Cafe combines traditional recipes with modern culinary techniques to create unforgettable dining experiences.</p>
                    
                    <h4>🎯 Our Mission</h4>
                    <p>To provide delicious, high-quality food with exceptional service, creating a warm and welcoming environment where every customer feels at home.</p>
                    
                    <h4>📍 Location & Hours</h4>
                    <p><strong>Address:</strong> 123 Cafe Street, Food District<br>
                    <strong>Phone:</strong> +60 12-345-6789<br>
                    <strong>Email:</strong> info@rimbunancafe.com</p>
                    
                    <p><strong>Opening Hours:</strong><br>
                    Monday - Friday: 8:00 AM - 10:00 PM<br>
                    Saturday - Sunday: 9:00 AM - 11:00 PM</p>
                </div>
            </div>
            
            <!-- Profile Tab -->
            <div id="profile" class="tab-content">
                <h2>👤 My Profile</h2>
                <div style="background: white; padding: 2rem; border-radius: 15px; box-shadow: 0 5px 15px rgba(0,0,0,0.1);">
                    <form method="POST" action="update_profile.php" id="profile-form">
                        <div class="form-group">
                            <label for="username">Username</label>
                            <input type="text" id="username" name="username" class="form-control" value="<?php echo htmlspecialchars($user['cust_username']); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="email">Email</label>
                            <input type="email" id="email" name="email" class="form-control" value="<?php echo htmlspecialchars($user['cust_email']); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="phone">Phone Number</label>
                            <input type="tel" id="phone" name="phone" class="form-control" value="<?php echo htmlspecialchars($user['cust_phonenumber']); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="address">Default Address</label>
                            <textarea id="address" name="address" class="form-control" rows="3" required><?php echo htmlspecialchars($user['cust_address']); ?></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label for="new_password">New Password (leave blank to keep current)</label>
                            <input type="password" id="new_password" name="new_password" class="form-control">
                        </div>
                        
                        <button type="submit" class="btn btn-primary" style="width: 100%;" onclick="return validateProfile()">Update Profile</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Checkout Modal -->
<div id="checkout-modal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Checkout</h2>
            <span class="close" onclick="closeModal('checkout-modal')">&times;</span>
        </div>
        
        <form method="POST" enctype="multipart/form-data">
            <div class="form-group">
                <label for="delivery_address">Delivery Address</label>
                <div style="margin-bottom: 0.5rem;">
                    <small style="color: #666; font-style: italic;">Format: [Street Number] [Street Name], [Area/District], [City], [Postal Code]</small>
                </div>
                <textarea id="delivery_address" name="delivery_address" class="form-control" rows="4" placeholder="Example: 123 Jalan Merdeka, Taman Sejahtera, Kuala Lumpur, 50000" required><?php echo htmlspecialchars($user['cust_address']); ?></textarea>
                <div style="margin-top: 0.5rem;">
                    <button type="button" class="btn" onclick="useDefaultAddress()" style="background: #6c757d; color: white; font-size: 0.875rem; padding: 0.5rem 1rem;">
                        📍 Use My Default Address
                    </button>
                </div>
            </div>
            
            <div class="form-group">
                <label for="payment_method">Payment Method</label>
                <select id="payment_method" name="payment_method" class="form-control" onchange="togglePaymentMethod()" required>
                    <option value="cod">Cash on Delivery</option>
                    <option value="qr">QR Code Payment</option>
                </select>
            </div>
            
            <div id="qr-payment-section" style="display: none;">
                <div class="form-group">
                    <label>QR Code for Payment</label>
                    <div style="text-align: center; margin: 1rem 0;">
                        <img src="https://via.placeholder.com/200x200/8B4513/FFFFFF?text=QR+CODE" alt="QR Code" style="border-radius: 10px;">
                    </div>
                    <p style="text-align: center; color: #666;">Scan this QR code to make payment</p>
                </div>
                
                <div class="form-group">
                    <label for="payment_proof">Upload Payment Proof</label>
                    <input type="file" id="payment_proof" name="payment_proof" class="form-control" accept="image/*">
                </div>
            </div>
            
            <input type="hidden" name="cart_items" id="checkout-cart-items">
            <input type="hidden" name="total_price" id="checkout-total-price">
            
            <button type="submit" name="checkout" class="btn btn-primary" style="width: 100%;">Place Order</button>
        </form>
    </div>
</div>

<script src="js/main.js"></script>
<script>
<?php if ($order_placed): ?>
// Clear cart and show success message after order placement
localStorage.removeItem('cart');
updateCartDisplay();
showTab('home');
alert('Order placed successfully! You can track your order in the home section.');
<?php endif; ?>

<?php if (isset($_GET['updated'])): ?>
// Show profile update success message
alert('Profile updated successfully!');
<?php endif; ?>

<?php if (isset($_GET['error'])): ?>
// Show error message
alert('Error: <?php echo addslashes($_GET['error']); ?>');
<?php endif; ?>

// Use default address function
function useDefaultAddress() {
    const defaultAddress = `<?php echo addslashes($user['cust_address']); ?>`;
    document.getElementById('delivery_address').value = defaultAddress;
}

// Address validation function
function validateAddress(address) {
    // Check if address follows the format: [Street] [Area] [City] [Postal]
    const addressParts = address.split(',');
    if (addressParts.length < 3) {
        return false;
    }
    
    // Check for postal code (5 digits)
    const postalCodeRegex = /\b\d{5}\b/;
    if (!postalCodeRegex.test(address)) {
        return false;
    }
    
    return true;
}

// Override checkout function to populate hidden fields
function openModal(modalId) {
    if (modalId === 'checkout-modal') {
        const selectedItems = cart.filter(item => item.selected);
        if (selectedItems.length === 0) {
            alert('Please select items to checkout');
            return;
        }
        
        // Validate delivery address format
        const deliveryAddress = document.getElementById('delivery_address').value.trim();
        if (!deliveryAddress) {
            alert('Please enter a delivery address');
            return;
        }
        
        if (!validateAddress(deliveryAddress)) {
            alert('Please enter address in format: [Street Number] [Street Name], [Area/District], [City], [Postal Code]\n\nExample: 123 Jalan Merdeka, Taman Sejahtera, Kuala Lumpur, 50000');
            return;
        }
        
        document.getElementById('checkout-cart-items').value = JSON.stringify(cart);
        
        const subtotal = selectedItems.reduce((sum, item) => sum + (item.price * item.quantity), 0);
        
        // Calculate progressive delivery fee based on quantity
        const totalQuantity = selectedItems.reduce((sum, item) => sum + item.quantity, 0);
        let deliveryFee = calculateDeliveryFee(totalQuantity);
        
        const total = subtotal + deliveryFee;
        document.getElementById('checkout-total-price').value = total.toFixed(2);
    }
    
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'block';
    }
}

// Calculate progressive delivery fee
function calculateDeliveryFee(totalQuantity) {
    if (totalQuantity <= 4) {
        return 5.00;
    } else if (totalQuantity <= 8) {
        return 10.00;
    } else {
        // For 9+ items: RM 15 base + RM 5 for each additional 5 items
        const additionalGroups = Math.floor((totalQuantity - 9) / 5);
        return 15.00 + (additionalGroups * 5.00);
    }
}
</script>

</body>
</html>