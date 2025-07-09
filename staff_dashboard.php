<?php
session_start();
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'staff') {
    header('Location: staff_login.php');
    exit;
}

$page_title = "Staff Dashboard - Rimbunan Cafe";
include 'config/database.php';

// Handle order status update
if (isset($_POST['update_status'])) {
    $order_id = $_POST['order_id'];
    $new_status = $_POST['new_status'];
    $rider_id = $_POST['rider_id'] ?? null;
    
    // If status is being changed to "Delivered", clear rider assignment for future availability
    if ($new_status === 'Delivered') {
        $stmt = $pdo->prepare("UPDATE orders SET order_status = ? WHERE order_id = ?");
        $stmt->execute([$new_status, $order_id]);
        
        // Clear rider from staff assignment after completion
        $stmt = $pdo->prepare("UPDATE staff SET orders_id = NULL, status_updated = NULL, assigned_rider_id = NULL WHERE staff_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
    } else {
        $stmt = $pdo->prepare("UPDATE orders SET order_status = ?, rider_id = ? WHERE order_id = ?");
        $stmt->execute([$new_status, $rider_id, $order_id]);
        
        // Update staff record
        $stmt = $pdo->prepare("UPDATE staff SET orders_id = ?, status_updated = ?, assigned_rider_id = ? WHERE staff_id = ?");
        $stmt->execute([$order_id, $new_status, $rider_id, $_SESSION['user_id']]);
    }
}

// Handle order deletion
if (isset($_POST['delete_order'])) {
    $order_id = $_POST['order_id'];
    
    // Delete order details first (foreign key constraint)
    $stmt = $pdo->prepare("DELETE FROM order_details WHERE orders_id = ?");
    $stmt->execute([$order_id]);
    
    // Delete the order
    $stmt = $pdo->prepare("DELETE FROM orders WHERE order_id = ?");
    $stmt->execute([$order_id]);
}

// Handle profile update
if (isset($_POST['update_profile'])) {
    $name = $_POST['staff_name'];
    $email = $_POST['staff_email'];
    $phone = $_POST['staff_phone'];
    $password = $_POST['staff_password'];
    
    if (!empty($password)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE staff SET staff_name = ?, staff_email = ?, staff_phonenumber = ?, staff_password = ? WHERE staff_id = ?");
        $stmt->execute([$name, $email, $phone, $hashed_password, $_SESSION['user_id']]);
    } else {
        $stmt = $pdo->prepare("UPDATE staff SET staff_name = ?, staff_email = ?, staff_phonenumber = ? WHERE staff_id = ?");
        $stmt->execute([$name, $email, $phone, $_SESSION['user_id']]);
    }
    
    $_SESSION['username'] = $name;
}

// Get staff info
$stmt = $pdo->prepare("SELECT * FROM staff WHERE staff_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$staff = $stmt->fetch();

// Get all orders
$stmt = $pdo->prepare("SELECT o.*, c.cust_username, c.cust_address, c.cust_phonenumber, r.rider_username, o.payment_method,
                      GROUP_CONCAT(CONCAT(p.product_name, ' (', od.qty, ')') SEPARATOR ', ') as items
                      FROM orders o 
                      LEFT JOIN customer c ON o.cust_id = c.cust_id
                      LEFT JOIN rider r ON o.rider_id = r.rider_id
                      LEFT JOIN order_details od ON o.order_id = od.orders_id 
                      LEFT JOIN product p ON od.product_id = p.product_id 
                      GROUP BY o.order_id 
                      ORDER BY o.order_date DESC");
$stmt->execute();
$orders = $stmt->fetchAll();

// Get available riders
$stmt = $pdo->prepare("SELECT * FROM rider WHERE rider_status = 1");
$stmt->execute();
$riders = $stmt->fetchAll();
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
                <div class="logo">👨‍🍳 Staff Dashboard</div>
                <div class="nav-actions">
                    <button class="btn btn-secondary" onclick="refreshPage()" style="margin-right: 1rem;">🔄 Refresh</button>
                    <a href="staff_reports.php" class="btn btn-secondary" style="margin-right: 1rem;">📊 Reports</a>
                    <button class="btn btn-secondary" onclick="showTab('profile')" style="margin-right: 1rem;">👤 Profile</button>
                    <span>Welcome, <?php echo $_SESSION['username']; ?>!</span>
                    <a href="logout.php" class="btn btn-danger">Logout</a>
                </div>
            </div>
        </div>
    </div>
    
    <div class="container">
        <div class="dashboard-content">
            <div class="dashboard-tabs">
                <button class="tab-btn active" onclick="showTab('orders')">📦 Order Management</button>
                <button class="tab-btn" onclick="showTab('completed')">✅ Completed Orders</button>
                <button class="tab-btn" onclick="showTab('profile')">👤 Profile</button>
            </div>
            
            <!-- Orders Tab -->
            <div id="orders" class="tab-content active">
                <h2>Active Order Management</h2>
                
                <div style="background: white; border-radius: 15px; overflow: hidden; box-shadow: 0 5px 15px rgba(0,0,0,0.1);">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Order ID</th>
                                <th>Customer</th>
                                <th>Items</th>
                                <th style="width: 150px;">Payment</th>
                                <th>Total</th>
                                <th>Status</th>
                                <th>Rider</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($orders as $order): 
                                // Only show non-completed orders in active management
                                if ($order['order_status'] === 'Delivered' || $order['order_status'] === 'Cancelled') continue;
                            ?>
                                <tr>
                                    <td>#<?php echo $order['order_id']; ?></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($order['cust_username']); ?></strong><br>
                                        <small><?php echo htmlspecialchars($order['cust_phonenumber']); ?></small><br>
                                        <small style="color: #28a745; font-weight: 500;">📍 Delivery Address:</small><br>
                                        <small style="background: #f8f9fa; padding: 0.25rem 0.5rem; border-radius: 4px; display: inline-block; margin-top: 0.25rem; border-left: 3px solid #28a745;">
                                            <?php echo htmlspecialchars($order['delivery_address'] ?? $order['cust_address']); ?>
                                        </small>
                                    </td>
                                    <td><?php echo htmlspecialchars($order['items']); ?></td>
                                    <td>
                                        <div style="white-space: nowrap;">
                                            <span class="status-badge <?php echo ($order['payment_method'] ?? 'cod') === 'cod' ? 'status-pending' : 'status-completed'; ?>">
                                                <?php echo ($order['payment_method'] ?? 'cod') === 'cod' ? '💵 COD' : '📱 QR Paid'; ?>
                                            </span>
                                            <?php if (($order['payment_method'] ?? 'cod') === 'qr' && $order['payment_proof']): ?>
                                                <br><button class="btn" onclick="viewPaymentProof('<?php echo $order['payment_proof']; ?>')" style="background: #17a2b8; color: white; font-size: 0.75rem; padding: 0.25rem 0.5rem; margin-top: 0.25rem;">
                                                    📷 View Proof
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>RM <?php echo number_format($order['total_price'], 2); ?></td>
                                    <td>
                                        <span class="status-badge status-<?php echo strtolower(str_replace(' ', '', $order['order_status'])); ?>">
                                            <?php echo $order['order_status']; ?>
                                        </span>
                                    </td>
                                    <td><?php echo $order['rider_username'] ?? 'Not assigned'; ?></td>
                                    <td>
                                        <form method="POST" style="display: inline-block;">
                                            <input type="hidden" name="order_id" value="<?php echo $order['order_id']; ?>">
                                            
                                            <select name="new_status" class="form-control" style="margin-bottom: 0.5rem;">
                                                <option value="Pending" <?php echo $order['order_status'] === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                                                <option value="Preparing" <?php echo $order['order_status'] === 'Preparing' ? 'selected' : ''; ?>>Preparing</option>
                                            </select>
                                            
                                            <?php if ($order['order_status'] === 'Preparing'): ?>
                                                <select name="rider_id" class="form-control" style="margin-bottom: 0.5rem;">
                                                    <option value="">Select Rider</option>
                                                    <?php foreach ($riders as $rider): ?>
                                                        <?php 
                                                        // Check if rider is available and not currently on delivery
                                                        $stmt_check = $pdo->prepare("SELECT COUNT(*) as active_deliveries FROM orders WHERE rider_id = ? AND order_status = 'In Delivery'");
                                                        $stmt_check->execute([$rider['rider_id']]);
                                                        $active_deliveries = $stmt_check->fetch()['active_deliveries'];
                                                        $is_available = $rider['rider_status'] == 1 && $active_deliveries == 0;
                                                        ?>
                                                        <option value="<?php echo $rider['rider_id']; ?>" 
                                                                <?php echo $order['rider_id'] == $rider['rider_id'] ? 'selected' : ''; ?>
                                                                <?php echo !$is_available ? 'disabled' : ''; ?>>
                                                            <?php echo htmlspecialchars($rider['rider_username']); ?>
                                                            <?php echo !$is_available ? ' (Unavailable)' : ''; ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            <?php endif; ?>
                                            
                                            <button type="submit" name="update_status" class="btn btn-primary" style="font-size: 0.875rem;">
                                                Update
                                            </button>
                                            
                                            <button type="submit" name="delete_order" class="btn btn-danger" style="font-size: 0.875rem; margin-left: 0.5rem;" onclick="return confirm('Are you sure you want to delete this order? This action cannot be undone.')">
                                                Delete
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Completed Orders Tab -->
            <div id="completed" class="tab-content">
                <h2>✅ Completed Orders (Read-Only)</h2>
                
                <div style="background: white; border-radius: 15px; overflow: hidden; box-shadow: 0 5px 15px rgba(0,0,0,0.1);">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Order ID</th>
                                <th>Date</th>
                                <th>Customer</th>
                                <th>Items</th>
                                <th>Payment</th>
                                <th>Total</th>
                                <th>Status</th>
                                <th>Rider</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($orders as $order): 
                                // Only show completed/cancelled orders
                                if ($order['order_status'] !== 'Delivered' && $order['order_status'] !== 'Cancelled') continue;
                            ?>
                                <tr style="opacity: 0.8;">
                                    <td>#<?php echo $order['order_id']; ?></td>
                                    <td><?php echo date('M d, Y H:i', strtotime($order['order_date'])); ?></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($order['cust_username']); ?></strong><br>
                                        <small><?php echo htmlspecialchars($order['cust_phonenumber']); ?></small><br>
                                        <small>📍 <?php echo htmlspecialchars($order['delivery_address'] ?? $order['cust_address']); ?></small>
                                    </td>
                                    <td><?php echo htmlspecialchars($order['items']); ?></td>
                                    <td>
                                        <span class="status-badge <?php echo ($order['payment_method'] ?? 'cod') === 'cod' ? 'status-pending' : 'status-completed'; ?>">
                                            <?php echo ($order['payment_method'] ?? 'cod') === 'cod' ? '💵 COD' : '📱 QR Paid'; ?>
                                        </span>
                                        <?php if (($order['payment_method'] ?? 'cod') === 'qr' && $order['payment_proof']): ?>
                                            <br><button class="btn" onclick="viewPaymentProof('<?php echo $order['payment_proof']; ?>')" style="background: #17a2b8; color: white; font-size: 0.75rem; padding: 0.25rem 0.5rem; margin-top: 0.25rem;">
                                                📷 View Proof
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                    <td>RM <?php echo number_format($order['total_price'], 2); ?></td>
                                    <td>
                                        <span class="status-badge status-<?php echo strtolower(str_replace(' ', '', $order['order_status'])); ?>">
                                            <?php echo $order['order_status']; ?>
                                        </span>
                                    </td>
                                    <td><?php echo $order['rider_username'] ?? 'Not assigned'; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Profile Tab -->
            <div id="profile" class="tab-content">
                <h2>👤 My Profile</h2>
                <div style="background: white; padding: 2rem; border-radius: 15px; box-shadow: 0 5px 15px rgba(0,0,0,0.1);">
                    <form method="POST" id="staff-profile-form">
                        <div class="form-group">
                            <label for="staff_name">Staff Name</label>
                            <input type="text" id="staff_name" name="staff_name" class="form-control" value="<?php echo htmlspecialchars($staff['staff_name']); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="staff_email">Email</label>
                            <input type="email" id="staff_email" name="staff_email" class="form-control" value="<?php echo htmlspecialchars($staff['staff_email']); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="staff_phone">Phone Number</label>
                            <input type="tel" id="staff_phone" name="staff_phone" class="form-control" value="<?php echo htmlspecialchars($staff['staff_phonenumber']); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="staff_password">New Password (leave blank to keep current)</label>
                            <input type="password" id="staff_password" name="staff_password" class="form-control">
                        </div>
                        
                        <button type="submit" name="update_profile" class="btn btn-primary" style="width: 100%;" onclick="return validateStaffProfile()">Update Profile</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Payment Proof Modal -->
<div id="payment-proof-modal" class="payment-proof-modal">
    <div class="payment-proof-content">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
            <h3>💳 Payment Proof</h3>
            <span class="close" onclick="closePaymentProof()" style="font-size: 2rem; cursor: pointer;">&times;</span>
        </div>
        <img id="payment-proof-image" src="" alt="Payment Proof" style="max-width: 100%; max-height: 400px; border-radius: 10px;">
    </div>
</div>

<script>
function viewPaymentProof(imagePath) {
    document.getElementById('payment-proof-image').src = imagePath;
    document.getElementById('payment-proof-modal').style.display = 'block';
}

function closePaymentProof() {
    document.getElementById('payment-proof-modal').style.display = 'none';
}

// Staff profile validation function
function validateStaffProfile() {
    const name = document.getElementById('staff_name').value.trim();
    const email = document.getElementById('staff_email').value.trim();
    const phone = document.getElementById('staff_phone').value.trim();
    
    if (!name || !email || !phone) {
        alert('Please fill in all required fields.');
        return false;
    }
    
    // Basic email validation
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailRegex.test(email)) {
        alert('Please enter a valid email address.');
        return false;
    }
    
    return true;
}

// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById('payment-proof-modal');
    if (event.target === modal) {
        modal.style.display = 'none';
    }
}
</script>

</body>
<script src="js/main.js"></script>
</html>