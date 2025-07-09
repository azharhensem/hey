<?php
session_start();
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: login.php');
    exit;
}

$page_title = "Address Tracking Report - Rimbunan Cafe";
include 'config/database.php';
include 'create_address_validation.php';

// Get address usage statistics
$stmt = $pdo->prepare("SELECT 
    COUNT(*) as total_orders,
    COUNT(CASE WHEN delivery_address IS NOT NULL AND delivery_address != '' THEN 1 END) as custom_address_orders,
    COUNT(CASE WHEN delivery_address IS NULL OR delivery_address = '' THEN 1 END) as default_address_orders
    FROM orders");
$stmt->execute();
$address_stats = $stmt->fetch();

// Get recent orders with address details
$stmt = $pdo->prepare("SELECT o.order_id, o.order_date, o.delivery_address, c.cust_username, c.cust_address, o.order_status
                      FROM orders o 
                      LEFT JOIN customer c ON o.cust_id = c.cust_id 
                      ORDER BY o.order_date DESC 
                      LIMIT 50");
$stmt->execute();
$recent_orders = $stmt->fetchAll();

// Address format compliance check
$compliant_addresses = 0;
$non_compliant_addresses = 0;

foreach ($recent_orders as $order) {
    $address_to_check = $order['delivery_address'] ?? $order['cust_address'];
    if ($address_to_check) {
        $validation = AddressValidator::validateFormat($address_to_check);
        if ($validation['valid']) {
            $compliant_addresses++;
        } else {
            $non_compliant_addresses++;
        }
    }
}
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
                <div class="logo">📍 Address Tracking Report</div>
                <div class="nav-actions">
                    <a href="admin_dashboard.php" class="btn btn-secondary">← Back to Dashboard</a>
                    <a href="logout.php" class="btn btn-danger">Logout</a>
                </div>
            </div>
        </div>
    </div>
    
    <div class="container">
        <div class="dashboard-content">
            <!-- Address Statistics -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
                <div style="background: linear-gradient(135deg, #28a745, #20c997); color: white; padding: 2rem; border-radius: 15px; text-align: center;">
                    <h3>Total Orders</h3>
                    <p style="font-size: 2rem; font-weight: 600;"><?php echo $address_stats['total_orders']; ?></p>
                </div>
                <div style="background: linear-gradient(135deg, #17a2b8, #138496); color: white; padding: 2rem; border-radius: 15px; text-align: center;">
                    <h3>Custom Addresses</h3>
                    <p style="font-size: 2rem; font-weight: 600;"><?php echo $address_stats['custom_address_orders']; ?></p>
                    <small><?php echo $address_stats['total_orders'] > 0 ? round(($address_stats['custom_address_orders'] / $address_stats['total_orders']) * 100, 1) : 0; ?>% of orders</small>
                </div>
                <div style="background: linear-gradient(135deg, #ffc107, #e0a800); color: white; padding: 2rem; border-radius: 15px; text-align: center;">
                    <h3>Default Addresses</h3>
                    <p style="font-size: 2rem; font-weight: 600;"><?php echo $address_stats['default_address_orders']; ?></p>
                    <small><?php echo $address_stats['total_orders'] > 0 ? round(($address_stats['default_address_orders'] / $address_stats['total_orders']) * 100, 1) : 0; ?>% of orders</small>
                </div>
                <div style="background: linear-gradient(135deg, #8B4513, #A0522D); color: white; padding: 2rem; border-radius: 15px; text-align: center;">
                    <h3>Format Compliant</h3>
                    <p style="font-size: 2rem; font-weight: 600;"><?php echo $compliant_addresses; ?></p>
                    <small>Out of <?php echo count($recent_orders); ?> recent orders</small>
                </div>
            </div>

            <!-- Address Format Guide -->
            <div style="background: white; padding: 2rem; border-radius: 15px; box-shadow: 0 5px 15px rgba(0,0,0,0.1); margin-bottom: 2rem;">
                <h3 style="color: #8B4513; margin-bottom: 1rem;">📋 Address Format Standard</h3>
                <div style="background: #f8f9fa; padding: 1rem; border-radius: 8px; border-left: 4px solid #28a745;">
                    <p style="margin: 0; font-weight: 500;">Required Format:</p>
                    <p style="margin: 0.5rem 0; font-family: monospace; background: white; padding: 0.5rem; border-radius: 4px;">
                        [Street Number] [Street Name], [Area/District], [City], [Postal Code]
                    </p>
                    <p style="margin: 0; color: #666;">
                        <strong>Example:</strong> <?php echo AddressValidator::getExample(); ?>
                    </p>
                </div>
            </div>

            <!-- Recent Orders Address Tracking -->
            <div style="background: white; border-radius: 15px; overflow: hidden; box-shadow: 0 5px 15px rgba(0,0,0,0.1);">
                <div style="background: #8B4513; color: white; padding: 1rem;">
                    <h3 style="margin: 0;">📦 Recent Orders Address Tracking</h3>
                </div>
                
                <div style="max-height: 600px; overflow-y: auto;">
                    <table class="data-table" style="margin: 0;">
                        <thead>
                            <tr>
                                <th>Order ID</th>
                                <th>Date</th>
                                <th>Customer</th>
                                <th>Address Type</th>
                                <th>Delivery Address</th>
                                <th>Format Status</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_orders as $order): ?>
                                <?php
                                $delivery_address = $order['delivery_address'] ?? $order['cust_address'];
                                $is_custom = !empty($order['delivery_address']) && $order['delivery_address'] !== $order['cust_address'];
                                $validation = AddressValidator::validateFormat($delivery_address);
                                ?>
                                <tr>
                                    <td>#<?php echo $order['order_id']; ?></td>
                                    <td><?php echo date('M d, Y', strtotime($order['order_date'])); ?></td>
                                    <td><?php echo htmlspecialchars($order['cust_username']); ?></td>
                                    <td>
                                        <span class="status-badge <?php echo $is_custom ? 'status-completed' : 'status-pending'; ?>">
                                            <?php echo $is_custom ? '📍 Custom' : '🏠 Default'; ?>
                                        </span>
                                    </td>
                                    <td style="max-width: 250px; word-wrap: break-word;">
                                        <?php echo htmlspecialchars($delivery_address); ?>
                                    </td>
                                    <td>
                                        <span class="status-badge <?php echo $validation['valid'] ? 'status-completed' : 'status-pending'; ?>">
                                            <?php echo $validation['valid'] ? '✅ Valid' : '⚠️ Invalid'; ?>
                                        </span>
                                        <?php if (!$validation['valid']): ?>
                                            <br><small style="color: #dc3545;"><?php echo $validation['message']; ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="status-badge status-<?php echo strtolower(str_replace(' ', '', $order['order_status'])); ?>">
                                            <?php echo $order['order_status']; ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="js/main.js"></script>

</body>
</html>