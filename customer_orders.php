<?php
session_start();
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'customer') {
    header('Location: login.php');
    exit;
}

$page_title = "My Orders - Rimbunan Cafe";
include 'config/database.php';

// Handle order cancellation
if (isset($_POST['cancel_order'])) {
    $order_id = $_POST['order_id'];
    
    // Only allow cancellation if order is still pending
    $stmt = $pdo->prepare("SELECT order_status FROM orders WHERE order_id = ? AND cust_id = ?");
    $stmt->execute([$order_id, $_SESSION['user_id']]);
    $order = $stmt->fetch();
    
    if ($order && $order['order_status'] === 'Pending') {
        $stmt = $pdo->prepare("UPDATE orders SET order_status = 'Cancelled' WHERE order_id = ? AND cust_id = ?");
        $stmt->execute([$order_id, $_SESSION['user_id']]);
        $success_message = "Order #$order_id has been cancelled successfully.";
    } else {
        $error_message = "Cannot cancel this order. It may have already been processed.";
    }
}

// Get user orders with delivery address
$stmt = $pdo->prepare("SELECT o.*, o.delivery_address,
                      GROUP_CONCAT(CONCAT(p.product_name, ' (', od.qty, ')') SEPARATOR ', ') as items 
                      FROM orders o 
                      LEFT JOIN order_details od ON o.order_id = od.orders_id 
                      LEFT JOIN product p ON od.product_id = p.product_id 
                      WHERE o.cust_id = ? 
                      GROUP BY o.order_id 
                      ORDER BY o.order_date DESC");
$stmt->execute([$_SESSION['user_id']]);
$orders = $stmt->fetchAll();
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
                <div class="logo">📋 My Orders</div>
                <div class="nav-actions">
                    <a href="customer_dashboard.php" class="btn btn-secondary">← Back to Dashboard</a>
                    <a href="logout.php" class="btn btn-danger">Logout</a>
                </div>
            </div>
        </div>
    </div>
    
    <div class="container">
        <div class="dashboard-content">
            <?php if (isset($success_message)): ?>
                <div class="alert alert-success"><?php echo $success_message; ?></div>
            <?php endif; ?>
            
            <?php if (isset($error_message)): ?>
                <div class="alert alert-error"><?php echo $error_message; ?></div>
            <?php endif; ?>
            
            <h2>📋 Order History</h2>
            
            <?php if (empty($orders)): ?>
                <div style="background: white; padding: 3rem; border-radius: 15px; text-align: center; box-shadow: 0 5px 15px rgba(0,0,0,0.1);">
                    <div style="font-size: 4rem; margin-bottom: 1rem;">🍽️</div>
                    <h3>No orders yet</h3>
                    <p>Start by browsing our delicious menu!</p>
                    <a href="customer_dashboard.php" class="btn btn-primary" style="margin-top: 1rem;">
                        Browse Menu
                    </a>
                </div>
            <?php else: ?>
                <div style="display: grid; gap: 1.5rem;">
                    <?php foreach ($orders as $order): ?>
                        <div class="order-status">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                                <h3>Order #<?php echo $order['order_id']; ?></h3>
                                <div style="display: flex; align-items: center; gap: 1rem;">
                                    <span class="status-badge status-<?php echo strtolower(str_replace(' ', '', $order['order_status'])); ?>">
                                        <?php echo $order['order_status']; ?>
                                    </span>
                                    <?php if ($order['order_status'] === 'Pending'): ?>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="order_id" value="<?php echo $order['order_id']; ?>">
                                            <button type="submit" name="cancel_order" class="btn btn-danger" style="font-size: 0.875rem;" onclick="return confirm('Are you sure you want to cancel this order?')">
                                                Cancel Order
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1rem; margin-bottom: 1rem;">
                                <div>
                                    <p><strong>📦 Items:</strong> <?php echo $order['items']; ?></p>
                                    <p><strong>💰 Total:</strong> RM <?php echo number_format($order['total_price'], 2); ?></p>
                                </div>
                                <div>
                                    <p><strong>📅 Order Date:</strong> <?php echo date('M d, Y H:i', strtotime($order['order_date'])); ?></p>
                                    <p><strong>💳 Payment:</strong> 
                                        <span class="status-badge <?php echo ($order['payment_method'] ?? 'cod') === 'cod' ? 'status-pending' : 'status-completed'; ?>">
                                            <?php echo ($order['payment_method'] ?? 'cod') === 'cod' ? '💵 COD' : '📱 QR Paid'; ?>
                                        </span>
                                    </p>
                                </div>
                            </div>
                            
                            <?php if ($order['delivery_address']): ?>
                                <div style="margin-top: 1rem; padding: 1rem; background: #f8f9fa; border-radius: 8px; border-left: 4px solid #28a745;">
                                    <p style="margin: 0;"><strong style="color: #155724;">📍 Delivery Address:</strong></p>
                                    <p style="margin: 0.5rem 0 0 0; color: #155724; font-weight: 500;">
                                        <?php echo htmlspecialchars($order['delivery_address']); ?>
                                    </p>
                                </div>
                            <?php elseif ($order['cust_address']): ?>
                                <div style="margin-top: 1rem; padding: 1rem; background: #fff3cd; border-radius: 8px; border-left: 4px solid #ffc107;">
                                    <p style="margin: 0;"><strong style="color: #856404;">📍 Default Address Used:</strong></p>
                                    <p style="margin: 0.5rem 0 0 0; color: #856404; font-weight: 500;">
                                        <?php echo htmlspecialchars($order['cust_address']); ?>
                                    </p>
                                </div>
                            <?php endif; ?>
                            
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
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="js/main.js"></script>

</body>
</html>