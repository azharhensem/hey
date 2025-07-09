<?php
session_start();
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'rider') {
    header('Location: rider_login.php');
    exit;
}

$page_title = "Rider Reports - Rimbunan Cafe";
include 'config/database.php';

$rider_id = $_SESSION['user_id'];
$current_month = date('Y-m');

// 1. Total Deliveries This Month
$stmt = $pdo->prepare("SELECT DATE(order_date) as delivery_day, COUNT(*) as deliveries 
                      FROM orders 
                      WHERE rider_id = ? AND DATE_FORMAT(order_date, '%Y-%m') = ? AND order_status = 'Delivered'
                      GROUP BY DATE(order_date) 
                      ORDER BY delivery_day");
$stmt->execute([$rider_id, $current_month]);
$monthly_deliveries = $stmt->fetchAll();

// 2. Deliveries Per Day (Last 7 days)
$stmt = $pdo->prepare("SELECT DATE(order_date) as delivery_day, COUNT(*) as deliveries 
                      FROM orders 
                      WHERE rider_id = ? AND DATE(order_date) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) AND order_status = 'Delivered'
                      GROUP BY DATE(order_date) 
                      ORDER BY delivery_day");
$stmt->execute([$rider_id]);
$daily_deliveries = $stmt->fetchAll();

// 3. Delivery Status Overview
$stmt = $pdo->prepare("SELECT 
    COUNT(CASE WHEN delivery_status = 1 AND order_status != 'Delivered' THEN 1 END) as accepted,
    COUNT(CASE WHEN order_status = 'In Delivery' THEN 1 END) as in_transit,
    COUNT(CASE WHEN order_status = 'Delivered' THEN 1 END) as delivered
    FROM orders WHERE rider_id = ?");
$stmt->execute([$rider_id]);
$delivery_status = $stmt->fetch();

// 4. Pending Deliveries
$stmt = $pdo->prepare("SELECT o.*, c.cust_username, c.cust_address 
                      FROM orders o 
                      JOIN customer c ON o.cust_id = c.cust_id 
                      WHERE o.rider_id = ? AND o.order_status IN ('Preparing', 'In Delivery')
                      ORDER BY o.order_date DESC");
$stmt->execute([$rider_id]);
$pending_deliveries = $stmt->fetchAll();

// 5. Completed vs Cancelled
$stmt = $pdo->prepare("SELECT 
    COUNT(CASE WHEN order_status = 'Delivered' THEN 1 END) as completed,
    COUNT(CASE WHEN order_status = 'Cancelled' THEN 1 END) as cancelled
    FROM orders WHERE rider_id = ?");
$stmt->execute([$rider_id]);
$completion_stats = $stmt->fetch();

// 6. Top Delivery Zones
$stmt = $pdo->prepare("SELECT c.cust_address, COUNT(*) as deliveries 
                      FROM orders o 
                      JOIN customer c ON o.cust_id = c.cust_id 
                      WHERE o.rider_id = ? AND o.order_status = 'Delivered'
                      GROUP BY c.cust_address 
                      ORDER BY deliveries DESC LIMIT 10");
$stmt->execute([$rider_id]);
$delivery_zones = $stmt->fetchAll();

// 7. Weekly Delivery Trend
$stmt = $pdo->prepare("SELECT WEEK(order_date) as week_num, COUNT(*) as deliveries 
                      FROM orders 
                      WHERE rider_id = ? AND order_status = 'Delivered' AND YEAR(order_date) = YEAR(CURDATE())
                      GROUP BY WEEK(order_date) 
                      ORDER BY week_num DESC LIMIT 8");
$stmt->execute([$rider_id]);
$weekly_trend = $stmt->fetchAll();

// 8. Key Metrics
$stmt = $pdo->prepare("SELECT 
    COUNT(*) as total_assigned,
    COUNT(CASE WHEN delivery_status >= 1 THEN 1 END) as accepted,
    AVG(total_price * 0.1) as avg_earning
    FROM orders WHERE rider_id = ?");
$stmt->execute([$rider_id]);
$metrics = $stmt->fetch();

$acceptance_rate = $metrics['total_assigned'] > 0 ? ($metrics['accepted'] / $metrics['total_assigned']) * 100 : 0;
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
                <div class="logo">📊 Rider Reports</div>
                <div class="nav-actions">
                    <a href="rider_dashboard.php" class="btn btn-secondary">← Back to Dashboard</a>
                    <a href="logout.php" class="btn btn-danger">Logout</a>
                </div>
            </div>
        </div>
    </div>
    
    <div class="container">
        <div class="dashboard-content">
            <!-- Key Metrics -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
                <div style="background: linear-gradient(135deg, #28a745, #20c997); color: white; padding: 2rem; border-radius: 15px; text-align: center;">
                    <h3>Acceptance Rate</h3>
                    <p style="font-size: 2rem; font-weight: 600;"><?php echo number_format($acceptance_rate, 1); ?>%</p>
                </div>
                <div style="background: linear-gradient(135deg, #17a2b8, #138496); color: white; padding: 2rem; border-radius: 15px; text-align: center;">
                    <h3>Avg Earning/Order</h3>
                    <p style="font-size: 2rem; font-weight: 600;">RM <?php echo number_format($metrics['avg_earning'], 2); ?></p>
                </div>
                <div style="background: linear-gradient(135deg, #8B4513, #A0522D); color: white; padding: 2rem; border-radius: 15px; text-align: center;">
                    <h3>Total Assigned</h3>
                    <p style="font-size: 2rem; font-weight: 600;"><?php echo $metrics['total_assigned']; ?></p>
                </div>
            </div>

            <!-- Charts Grid -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 2rem;">
                
                <!-- Monthly Deliveries Chart -->
                <div style="background: white; padding: 2rem; border-radius: 15px; box-shadow: 0 5px 15px rgba(0,0,0,0.1);">
                    <h3 style="margin-bottom: 1rem;">📊 Deliveries This Month</h3>
                    <canvas id="monthlyDeliveriesChart"></canvas>
                </div>

                <!-- Daily Deliveries Chart -->
                <div style="background: white; padding: 2rem; border-radius: 15px; box-shadow: 0 5px 15px rgba(0,0,0,0.1);">
                    <h3 style="margin-bottom: 1rem;">📈 Daily Deliveries (Last 7 Days)</h3>
                    <canvas id="dailyDeliveriesChart"></canvas>
                </div>

                <!-- Delivery Status Chart -->
                <div style="background: white; padding: 2rem; border-radius: 15px; box-shadow: 0 5px 15px rgba(0,0,0,0.1);">
                    <h3 style="margin-bottom: 1rem;">📦 Delivery Status Overview</h3>
                    <canvas id="deliveryStatusChart"></canvas>
                </div>

                <!-- Completion Stats Chart -->
                <div style="background: white; padding: 2rem; border-radius: 15px; box-shadow: 0 5px 15px rgba(0,0,0,0.1);">
                    <h3 style="margin-bottom: 1rem;">✅ Completed vs Cancelled</h3>
                    <canvas id="completionChart"></canvas>
                </div>

                <!-- Weekly Trend Chart -->
                <div style="background: white; padding: 2rem; border-radius: 15px; box-shadow: 0 5px 15px rgba(0,0,0,0.1);">
                    <h3 style="margin-bottom: 1rem;">📊 Weekly Delivery Trend</h3>
                    <canvas id="weeklyTrendChart"></canvas>
                </div>
            </div>

            <!-- Tables -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 2rem; margin-top: 2rem;">
                
                <!-- Pending Deliveries -->
                <div style="background: white; padding: 2rem; border-radius: 15px; box-shadow: 0 5px 15px rgba(0,0,0,0.1);">
                    <h3 style="margin-bottom: 1rem;">🚚 Pending Deliveries</h3>
                    <div style="max-height: 300px; overflow-y: auto;">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Order ID</th>
                                    <th>Customer</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pending_deliveries as $delivery): ?>
                                    <tr>
                                        <td>#<?php echo $delivery['order_id']; ?></td>
                                        <td><?php echo htmlspecialchars($delivery['cust_username']); ?></td>
                                        <td>
                                            <span class="status-badge status-<?php echo strtolower(str_replace(' ', '', $delivery['order_status'])); ?>">
                                                <?php echo $delivery['order_status']; ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Top Delivery Zones -->
                <div style="background: white; padding: 2rem; border-radius: 15px; box-shadow: 0 5px 15px rgba(0,0,0,0.1);">
                    <h3 style="margin-bottom: 1rem;">📍 Top Delivery Zones</h3>
                    <div style="max-height: 300px; overflow-y: auto;">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Address</th>
                                    <th>Deliveries</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($delivery_zones as $zone): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars(substr($zone['cust_address'], 0, 50)) . (strlen($zone['cust_address']) > 50 ? '...' : ''); ?></td>
                                        <td><?php echo $zone['deliveries']; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Monthly Deliveries Chart
const monthlyDeliveriesCtx = document.getElementById('monthlyDeliveriesChart').getContext('2d');
new Chart(monthlyDeliveriesCtx, {
    type: 'bar',
    data: {
        labels: <?php echo json_encode(array_column($monthly_deliveries, 'delivery_day')); ?>,
        datasets: [{
            label: 'Deliveries',
            data: <?php echo json_encode(array_column($monthly_deliveries, 'deliveries')); ?>,
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

// Daily Deliveries Chart
const dailyDeliveriesCtx = document.getElementById('dailyDeliveriesChart').getContext('2d');
new Chart(dailyDeliveriesCtx, {
    type: 'line',
    data: {
        labels: <?php echo json_encode(array_column($daily_deliveries, 'delivery_day')); ?>,
        datasets: [{
            label: 'Deliveries',
            data: <?php echo json_encode(array_column($daily_deliveries, 'deliveries')); ?>,
            borderColor: 'rgba(40, 167, 69, 1)',
            backgroundColor: 'rgba(40, 167, 69, 0.2)',
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

// Delivery Status Chart
const deliveryStatusCtx = document.getElementById('deliveryStatusChart').getContext('2d');
new Chart(deliveryStatusCtx, {
    type: 'pie',
    data: {
        labels: ['Accepted', 'In Transit', 'Delivered'],
        datasets: [{
            data: [<?php echo $delivery_status['accepted']; ?>, <?php echo $delivery_status['in_transit']; ?>, <?php echo $delivery_status['delivered']; ?>],
            backgroundColor: ['#FFC107', '#17A2B8', '#28A745']
        }]
    },
    options: {
        responsive: true
    }
});

// Completion Chart
const completionCtx = document.getElementById('completionChart').getContext('2d');
new Chart(completionCtx, {
    type: 'doughnut',
    data: {
        labels: ['Completed', 'Cancelled'],
        datasets: [{
            data: [<?php echo $completion_stats['completed']; ?>, <?php echo $completion_stats['cancelled']; ?>],
            backgroundColor: ['#28A745', '#DC3545']
        }]
    },
    options: {
        responsive: true
    }
});

// Weekly Trend Chart
const weeklyTrendCtx = document.getElementById('weeklyTrendChart').getContext('2d');
new Chart(weeklyTrendCtx, {
    type: 'line',
    data: {
        labels: <?php echo json_encode(array_map(function($item) { return 'Week ' . $item['week_num']; }, array_reverse($weekly_trend))); ?>,
        datasets: [{
            label: 'Deliveries',
            data: <?php echo json_encode(array_reverse(array_column($weekly_trend, 'deliveries'))); ?>,
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