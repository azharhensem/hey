<?php
$page_title = "Login - Rimbunan Cafe";
include 'includes/header.php';
include 'config/database.php';

$error = '';

if ($_POST) {
    $username = $_POST['username'];
    $password = $_POST['password'];
    
    // Check customer/admin login
    $stmt = $pdo->prepare("SELECT * FROM customer WHERE cust_username = ?");
    $stmt->execute([$username]);
    $customer = $stmt->fetch();
    
    if ($customer && password_verify($password, $customer['cust_password'])) {
        $_SESSION['user_id'] = $customer['cust_id'];
        $_SESSION['user_type'] = 'customer';
        $_SESSION['username'] = $customer['cust_username'];
        header('Location: customer_dashboard.php');
        exit;
    }
    
    // Check admin login
    $stmt = $pdo->prepare("SELECT * FROM admin WHERE admin_username = ?");
    $stmt->execute([$username]);
    $admin = $stmt->fetch();
    
    if ($admin && password_verify($password, $admin['admin_password'])) {
        $_SESSION['user_id'] = $admin['admin_id'];
        $_SESSION['user_type'] = 'admin';
        $_SESSION['username'] = $admin['admin_username'];
        header('Location: admin_dashboard.php');
        exit;
    }
    
    $error = 'Invalid username or password';
}
?>

<div class="auth-container">
    <div class="auth-card">
        <div class="auth-header">
            <h1>🍔 Rimbunan Cafe</h1>
            <p>Welcome back! Please sign in to your account.</p>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" class="form-control" required>
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" class="form-control" required>
            </div>
            
            <button type="submit" class="btn btn-primary" style="width: 100%;">Sign In</button>
        </form>
        
        <div class="role-buttons">
            <a href="staff_login.php" class="btn" style="background: #28a745; color: white; flex: 1; text-align: center; font-weight: 600; text-shadow: 1px 1px 2px rgba(0,0,0,0.3);">👨‍🍳 Staff Login</a>
            <a href="rider_login.php" class="btn" style="background: #17a2b8; color: white; flex: 1; text-align: center; font-weight: 600; text-shadow: 1px 1px 2px rgba(0,0,0,0.3);">🛵 Rider Login</a>
        </div>
        
        <div class="auth-links">
            <p>Don't have an account? <a href="register.php">Sign up here</a></p>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>