<?php
session_start();
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: login.php');
    exit;
}

$page_title = "Admin Reports - Rimbunan Cafe";
include 'config/database.php';

// Get current date ranges
$current_month = date('Y-m');
$current_year = date('Y');
$last_month = date('Y-m', strtotime('-1 month'));

// 1. Monthly Revenue
$stmt = $pdo->prepare("SELECT DATE_FORMAT(order_date, '%Y-%m') as month, SUM(total_price) as revenue 
                      FROM orders WHERE order_status = 'Delivered' 
                      GROUP BY DATE_FORMAT(order_date, '%Y-%m') 
                      ORDER BY month DESC LIMIT 12");
$stmt->execute();
$monthly_revenue = $stmt->fetchAll();

// Daily and Monthly Order Values (excluding delivery fees)
$today = date('Y-m-d');
$current_month = date('Y-m');

// Function to calculate delivery fee based on quantity
function calculateOrderDeliveryFee($total_qty) {
    if ($total_qty <= 4) {
        return 5.00;
    } else if ($total_qty <= 8) {
        return 10.00;
    } else {
        // For 9+ items: RM 15 base + RM 5 for each additional group of 5 items
        $additionalItems = $total_qty - 9;
        $additionalGroups = floor($additionalItems / 5);
        return 15.00 + ($additionalGroups * 5.00);
    }
}

$stmt = $pdo->prepare("SELECT 
    o.order_id,
    o.total_price,
    (SELECT SUM(qty) FROM order_details WHERE orders_id = o.order_id) as total_qty
    FROM orders o WHERE DATE(order_date) = ? AND order_status = 'Delivered'");
$stmt->execute([$today]);
$daily_orders = $stmt->fetchAll();
$daily_order_value = 0;
foreach ($daily_orders as $order) {
    $delivery_fee = calculateOrderDeliveryFee($order['total_qty']);
    $daily_order_value += ($order['total_price'] - $delivery_fee);
}

$stmt = $pdo->prepare("SELECT 
    o.order_id,
    o.total_price,
    (SELECT SUM(qty) FROM order_details WHERE orders_id = o.order_id) as total_qty
    FROM orders o WHERE DATE_FORMAT(order_date, '%Y-%m') = ? AND order_status = 'Delivered'");
$stmt->execute([$current_month]);
$monthly_orders = $stmt->fetchAll();
$monthly_order_value = 0;
foreach ($monthly_orders as $order) {
    $delivery_fee = calculateOrderDeliveryFee($order['total_qty']);
    $monthly_order_value += ($order['total_price'] - $delivery_fee);
}

// 2. Most Popular Items
$stmt = $pdo->prepare("SELECT p.product_name, SUM(od.qty) as total_sold 
                      FROM order_details od 
                      JOIN product p ON od.product_id = p.product_id 
                      JOIN orders o ON od.orders_id = o.order_id 
                      WHERE o.order_status = 'Delivered' 
                      GROUP BY p.product_id 
                      ORDER BY total_sold DESC LIMIT 10");
$stmt->execute();
$popular_items = $stmt->fetchAll();

// 3. Top Riders
$stmt = $pdo->prepare("SELECT r.rider_username, COUNT(o.order_id) as deliveries 
                      FROM rider r 
                      LEFT JOIN orders o ON r.rider_id = o.rider_id 
                      WHERE o.order_status = 'Delivered' 
                      GROUP BY r.rider_id 
                      ORDER BY deliveries DESC LIMIT 10");
$stmt->execute();
$top_riders = $stmt->fetchAll();

// 4. Orders by Status
$stmt = $pdo->prepare("SELECT order_status, COUNT(*) as count FROM orders GROUP BY order_status");
$stmt->execute();
$orders_by_status = $stmt->fetchAll();

// 5. New Customers per Month
$stmt = $pdo->prepare("SELECT DATE_FORMAT(cust_id, '%Y-%m') as month, COUNT(*) as new_customers 
                      FROM customer 
                      GROUP BY DATE_FORMAT(cust_id, '%Y-%m') 
                      ORDER BY month DESC LIMIT 12");
$stmt->execute();
$new_customers = $stmt->fetchAll();

// 6. Staff & Riders Count
$stmt = $pdo->prepare("SELECT COUNT(*) as staff_count FROM staff");
$stmt->execute();
$staff_count = $stmt->fetch()['staff_count'];

$stmt = $pdo->prepare("SELECT COUNT(*) as rider_count FROM rider");
$stmt->execute();
$rider_count = $stmt->fetch()['rider_count'];

// 7. Revenue Comparison
$stmt = $pdo->prepare("SELECT 
    SUM(CASE WHEN DATE_FORMAT(order_date, '%Y-%m') = ? THEN total_price ELSE 0 END) as current_month,
    SUM(CASE WHEN DATE_FORMAT(order_date, '%Y-%m') = ? THEN total_price ELSE 0 END) as last_month
    FROM orders WHERE order_status = 'Delivered'");
$stmt->execute([$current_month, $last_month]);
$revenue_comparison = $stmt->fetch();

// 8. Top Selling Day
$stmt = $pdo->prepare("SELECT DAYNAME(order_date) as day_name, COUNT(*) as orders 
                      FROM orders WHERE order_status = 'Delivered' 
                      GROUP BY DAYOFWEEK(order_date), DAYNAME(order_date) 
                      ORDER BY orders DESC");
$stmt->execute();
$top_days = $stmt->fetchAll();

// 9. Payment Method Breakdown
$stmt = $pdo->prepare("SELECT payment_method, COUNT(*) as count 
                      FROM orders WHERE order_status = 'Delivered' 
                      GROUP BY payment_method");
$stmt->execute();
$payment_methods = $stmt->fetchAll();

// 10. Average Order Value
$stmt = $pdo->prepare("SELECT AVG(total_price) as avg_value FROM orders WHERE order_status = 'Delivered'");
$stmt->execute();
$avg_order_value = $stmt->fetch()['avg_value'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link rel="stylesheet" href="css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>

<div class="dashboard">
    <div class="dashboard-header">
        <div class="container">
            <div class="dashboard-nav">
                <div class="logo">📊 Admin Reports</div>
                <div class="nav-actions">
                    <a href="admin_dashboard.php" class="btn btn-secondary">← Back to Dashboard</a>
                    <a href="logout.php" class="btn btn-danger">Logout</a>
                </div>
            </div>
        </div>
    </div>
    
    <div class="container">
        <div class="dashboard-content">
            <!-- Key Metrics -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
                <div style="background: linear-gradient(135deg, #ffc107, #e0a800); color: white; padding: 2rem; border-radius: 15px; text-align: center;">
                    <h3>Today's Order Value</h3>
                    <p style="font-size: 2rem; font-weight: 600;">RM <?php echo number_format($daily_order_value, 2); ?></p>
                    <small>Excluding delivery fees</small>
                </div>
                <div style="background: linear-gradient(135deg, #17a2b8, #138496); color: white; padding: 2rem; border-radius: 15px; text-align: center;">
                    <h3>Monthly Order Value</h3>
                    <p style="font-size: 2rem; font-weight: 600;">RM <?php echo number_format($monthly_order_value, 2); ?></p>
                    <small>Excluding delivery fees</small>
                </div>
                <div style="background: linear-gradient(135deg, #28a745, #20c997); color: white; padding: 2rem; border-radius: 15px; text-align: center;">
                    <h3>Average Order Value</h3>
                    <p style="font-size: 2rem; font-weight: 600;">RM <?php echo number_format($avg_order_value, 2); ?></p>
                </div>
                <div style="background: linear-gradient(135deg, #6f42c1, #5a32a3); color: white; padding: 2rem; border-radius: 15px; text-align: center;">
                    <h3>Total Staff</h3>
                    <p style="font-size: 2rem; font-weight: 600;"><?php echo $staff_count; ?></p>
                </div>
                <div style="background: linear-gradient(135deg, #8B4513, #A0522D); color: white; padding: 2rem; border-radius: 15px; text-align: center;">
                    <h3>Total Riders</h3>
                    <p style="font-size: 2rem; font-weight: 600;"><?php echo $rider_count; ?></p>
                </div>
            </div>

            <!-- Charts Grid -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 2rem;">
                
                <!-- Monthly Revenue Chart -->
                <div style="background: white; padding: 2rem; border-radius: 15px; box-shadow: 0 5px 15px rgba(0,0,0,0.1);">
                    <h3 style="margin-bottom: 1rem;">📈 Monthly Revenue</h3>
                    <canvas id="monthlyRevenueChart"></canvas>
                </div>

                <!-- Most Popular Items Chart -->
                <div style="background: white; padding: 2rem; border-radius: 15px; box-shadow: 0 5px 15px rgba(0,0,0,0.1);">
                    <h3 style="margin-bottom: 1rem;">🍔 Most Popular Items</h3>
                    <canvas id="popularItemsChart"></canvas>
                </div>

                <!-- Top Riders Chart -->
                <div style="background: white; padding: 2rem; border-radius: 15px; box-shadow: 0 5px 15px rgba(0,0,0,0.1);">
                    <h3 style="margin-bottom: 1rem;">🛵 Top Riders</h3>
                    <canvas id="topRidersChart"></canvas>
                </div>

                <!-- Orders by Status Chart -->
                <div style="background: white; padding: 2rem; border-radius: 15px; box-shadow: 0 5px 15px rgba(0,0,0,0.1);">
                    <h3 style="margin-bottom: 1rem;">📦 Orders by Status</h3>
                    <canvas id="orderStatusChart"></canvas>
                </div>

                <!-- Payment Method Chart -->
                <div style="background: white; padding: 2rem; border-radius: 15px; box-shadow: 0 5px 15px rgba(0,0,0,0.1);">
                    <h3 style="margin-bottom: 1rem;">💳 Payment Methods</h3>
                    <canvas id="paymentMethodChart"></canvas>
                </div>

                <!-- Top Selling Days Chart -->
                <div style="background: white; padding: 2rem; border-radius: 15px; box-shadow: 0 5px 15px rgba(0,0,0,0.1);">
                    <h3 style="margin-bottom: 1rem;">📅 Top Selling Days</h3>
                    <canvas id="topDaysChart"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Monthly Revenue Chart
const monthlyRevenueCtx = document.getElementById('monthlyRevenueChart').getContext('2d');
new Chart(monthlyRevenueCtx, {
    type: 'bar',
    data: {
        labels: <?php echo json_encode(array_reverse(array_column($monthly_revenue, 'month'))); ?>,
        datasets: [{
            label: 'Revenue (RM)',
            data: <?php echo json_encode(array_reverse(array_column($monthly_revenue, 'revenue'))); ?>,
            backgroundColor: 'rgba(139, 69, 19, 0.8)',
            borderColor: 'rgba(139, 69, 19, 1)',
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        scales: {
            y: {
                beginAtZero: true
            }
        }
    }
});

// Popular Items Chart
const popularItemsCtx = document.getElementById('popularItemsChart').getContext('2d');
new Chart(popularItemsCtx, {
    type: 'doughnut',
    data: {
        labels: <?php echo json_encode(array_column($popular_items, 'product_name')); ?>,
        datasets: [{
            data: <?php echo json_encode(array_column($popular_items, 'total_sold')); ?>,
            backgroundColor: [
                '#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF',
                '#FF9F40', '#FF6384', '#C9CBCF', '#4BC0C0', '#FF6384'
            ]
        }]
    },
    options: {
        responsive: true
    }
});

// Top Riders Chart
const topRidersCtx = document.getElementById('topRidersChart').getContext('2d');
new Chart(topRidersCtx, {
    type: 'bar',
    data: {
        labels: <?php echo json_encode(array_column($top_riders, 'rider_username')); ?>,
        datasets: [{
            label: 'Deliveries',
            data: <?php echo json_encode(array_column($top_riders, 'deliveries')); ?>,
            backgroundColor: 'rgba(40, 167, 69, 0.8)',
            borderColor: 'rgba(40, 167, 69, 1)',
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        indexAxis: 'y',
        scales: {
            x: {
                beginAtZero: true
            }
        }
    }
});

// Order Status Chart
const orderStatusCtx = document.getElementById('orderStatusChart').getContext('2d');
new Chart(orderStatusCtx, {
    type: 'doughnut',
    data: {
        labels: <?php echo json_encode(array_column($orders_by_status, 'order_status')); ?>,
        datasets: [{
            data: <?php echo json_encode(array_column($orders_by_status, 'count')); ?>,
            backgroundColor: ['#FFC107', '#28A745', '#17A2B8', '#DC3545']
        }]
    },
    options: {
        responsive: true
    }
});

// Payment Method Chart
const paymentMethodCtx = document.getElementById('paymentMethodChart').getContext('2d');
new Chart(paymentMethodCtx, {
    type: 'pie',
    data: {
        labels: <?php echo json_encode(array_map(function($item) { return $item['payment_method'] === 'cod' ? 'Cash on Delivery' : 'QR Payment'; }, $payment_methods)); ?>,
        datasets: [{
            data: <?php echo json_encode(array_column($payment_methods, 'count')); ?>,
            backgroundColor: ['#FFC107', '#28A745']
        }]
    },
    options: {
        responsive: true
    }
});

// Top Days Chart
const topDaysCtx = document.getElementById('topDaysChart').getContext('2d');
new Chart(topDaysCtx, {
    type: 'bar',
    data: {
        labels: <?php echo json_encode(array_column($top_days, 'day_name')); ?>,
        datasets: [{
            label: 'Orders',
            data: <?php echo json_encode(array_column($top_days, 'orders')); ?>,
            backgroundColor: 'rgba(255, 193, 7, 0.8)',
            borderColor: 'rgba(255, 193, 7, 1)',
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        scales: {
            y: {
                beginAtZero: true
            }
        }
    }
});
</script>

</body>
</html>