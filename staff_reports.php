<?php
session_start();
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'staff') {
    header('Location: staff_login.php');
    exit;
}

$page_title = "Staff Reports - Rimbunan Cafe";
include 'config/database.php';

// Get current week and day
$week_start = date('Y-m-d', strtotime('monday this week'));
$week_end = date('Y-m-d', strtotime('sunday this week'));
$today = date('Y-m-d');

// 1. Total Orders This Week
$stmt = $pdo->prepare("SELECT DATE(order_date) as order_day, COUNT(*) as orders 
                      FROM orders 
                      WHERE DATE(order_date) BETWEEN ? AND ? 
                      GROUP BY DATE(order_date) 
                      ORDER BY order_day");
$stmt->execute([$week_start, $week_end]);
$weekly_orders = $stmt->fetchAll();

// 2. Orders by Status
$stmt = $pdo->prepare("SELECT order_status, COUNT(*) as count FROM orders GROUP BY order_status");
$stmt->execute();
$orders_by_status = $stmt->fetchAll();

// 3. Top Ordered Food This Week
$stmt = $pdo->prepare("SELECT p.product_name, SUM(od.qty) as total_ordered 
                      FROM order_details od 
                      JOIN product p ON od.product_id = p.product_id 
                      JOIN orders o ON od.orders_id = o.order_id 
                      WHERE DATE(o.order_date) BETWEEN ? AND ? 
                      GROUP BY p.product_id 
                      ORDER BY total_ordered DESC LIMIT 10");
$stmt->execute([$week_start, $week_end]);
$top_foods = $stmt->fetchAll();

// 4. Daily Kitchen Load
$stmt = $pdo->prepare("SELECT DATE(order_date) as order_day, COUNT(*) as orders 
                      FROM orders 
                      WHERE DATE(order_date) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) 
                      GROUP BY DATE(order_date) 
                      ORDER BY order_day");
$stmt->execute();
$daily_load = $stmt->fetchAll();

// 5. Orders Assigned to Riders
$stmt = $pdo->prepare("SELECT r.rider_username, COUNT(o.order_id) as assigned_orders 
                      FROM rider r 
                      LEFT JOIN orders o ON r.rider_id = o.rider_id 
                      GROUP BY r.rider_id 
                      ORDER BY assigned_orders DESC");
$stmt->execute();
$rider_assignments = $stmt->fetchAll();

// 6. Key Metrics
$stmt = $pdo->prepare("SELECT COUNT(*) as pending_today FROM orders WHERE DATE(order_date) = ? AND order_status = 'Pending'");
$stmt->execute([$today]);
$pending_today = $stmt->fetch()['pending_today'];

$stmt = $pdo->prepare("SELECT COUNT(*) as cancelled FROM orders WHERE order_status = 'Cancelled'");
$stmt->execute();
$cancelled_orders = $stmt->fetch()['cancelled'];

$stmt = $pdo->prepare("SELECT COUNT(*) / 7 as avg_daily FROM orders WHERE DATE(order_date) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)");
$stmt->execute();
$avg_daily = $stmt->fetch()['avg_daily'];
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
                <div class="logo">📊 Staff Reports</div>
                <div class="nav-actions">
                    <a href="staff_dashboard.php" class="btn btn-secondary">← Back to Dashboard</a>
                    <a href="logout.php" class="btn btn-danger">Logout</a>
                </div>
            </div>
        </div>
    </div>
    
    <div class="container">
        <div class="dashboard-content">
            <!-- Key Metrics -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
                <div style="background: linear-gradient(135deg, #dc3545, #c82333); color: white; padding: 2rem; border-radius: 15px; text-align: center;">
                    <h3>Pending Today</h3>
                    <p style="font-size: 2rem; font-weight: 600;"><?php echo $pending_today; ?></p>
                </div>
                <div style="background: linear-gradient(135deg, #ffc107, #e0a800); color: white; padding: 2rem; border-radius: 15px; text-align: center;">
                    <h3>Cancelled Orders</h3>
                    <p style="font-size: 2rem; font-weight: 600;"><?php echo $cancelled_orders; ?></p>
                </div>
                <div style="background: linear-gradient(135deg, #28a745, #20c997); color: white; padding: 2rem; border-radius: 15px; text-align: center;">
                    <h3>Avg Orders/Day</h3>
                    <p style="font-size: 2rem; font-weight: 600;"><?php echo number_format($avg_daily, 1); ?></p>
                </div>
            </div>

            <!-- Charts Grid -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 2rem;">
                
                <!-- Weekly Orders Chart -->
                <div style="background: white; padding: 2rem; border-radius: 15px; box-shadow: 0 5px 15px rgba(0,0,0,0.1);">
                    <h3 style="margin-bottom: 1rem;">📊 Orders This Week</h3>
                    <canvas id="weeklyOrdersChart"></canvas>
                </div>

                <!-- Orders by Status Chart -->
                <div style="background: white; padding: 2rem; border-radius: 15px; box-shadow: 0 5px 15px rgba(0,0,0,0.1);">
                    <h3 style="margin-bottom: 1rem;">📦 Orders by Status</h3>
                    <canvas id="orderStatusChart"></canvas>
                </div>

                <!-- Top Foods Chart -->
                <div style="background: white; padding: 2rem; border-radius: 15px; box-shadow: 0 5px 15px rgba(0,0,0,0.1);">
                    <h3 style="margin-bottom: 1rem;">🍔 Top Foods This Week</h3>
                    <canvas id="topFoodsChart"></canvas>
                </div>

                <!-- Daily Kitchen Load Chart -->
                <div style="background: white; padding: 2rem; border-radius: 15px; box-shadow: 0 5px 15px rgba(0,0,0,0.1);">
                    <h3 style="margin-bottom: 1rem;">👨‍🍳 Daily Kitchen Load</h3>
                    <canvas id="dailyLoadChart"></canvas>
                </div>
            </div>

            <!-- Rider Assignments Table -->
            <div style="background: white; padding: 2rem; border-radius: 15px; box-shadow: 0 5px 15px rgba(0,0,0,0.1); margin-top: 2rem;">
                <h3 style="margin-bottom: 1rem;">🛵 Orders Assigned to Riders</h3>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Rider</th>
                            <th>Assigned Orders</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rider_assignments as $assignment): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($assignment['rider_username']); ?></td>
                                <td><?php echo $assignment['assigned_orders']; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
// Weekly Orders Chart
const weeklyOrdersCtx = document.getElementById('weeklyOrdersChart').getContext('2d');
new Chart(weeklyOrdersCtx, {
    type: 'bar',
    data: {
        labels: <?php echo json_encode(array_column($weekly_orders, 'order_day')); ?>,
        datasets: [{
            label: 'Orders',
            data: <?php echo json_encode(array_column($weekly_orders, 'orders')); ?>,
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

// Top Foods Chart
const topFoodsCtx = document.getElementById('topFoodsChart').getContext('2d');
new Chart(topFoodsCtx, {
    type: 'bar',
    data: {
        labels: <?php echo json_encode(array_column($top_foods, 'product_name')); ?>,
        datasets: [{
            label: 'Quantity Ordered',
            data: <?php echo json_encode(array_column($top_foods, 'total_ordered')); ?>,
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

// Daily Load Chart
const dailyLoadCtx = document.getElementById('dailyLoadChart').getContext('2d');
new Chart(dailyLoadCtx, {
    type: 'line',
    data: {
        labels: <?php echo json_encode(array_column($daily_load, 'order_day')); ?>,
        datasets: [{
            label: 'Orders',
            data: <?php echo json_encode(array_column($daily_load, 'orders')); ?>,
            borderColor: 'rgba(255, 193, 7, 1)',
            backgroundColor: 'rgba(255, 193, 7, 0.2)',
            tension: 0.4
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