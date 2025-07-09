<?php
$page_title = "Staff Login - Rimbunan Cafe";
include 'includes/header.php';
include 'config/database.php';

$error = '';

if ($_POST) {
    $username = $_POST['username'];
    $password = $_POST['password'];
    
    $stmt = $pdo->prepare("SELECT * FROM staff WHERE staff_name = ?");
    $stmt->execute([$username]);
    $staff = $stmt->fetch();
    
    if ($staff && password_verify($password, $staff['staff_password'])) {
        $_SESSION['user_id'] = $staff['staff_id'];
        $_SESSION['user_type'] = 'staff';
        $_SESSION['username'] = $staff['staff_name'];
        header('Location: staff_dashboard.php');
        exit;
    }
    
    $error = 'Invalid username or password';
}
?>

<div class="auth-container">
    <div class="auth-card">
        <div class="auth-header">
            <h1>👨‍🍳 Staff Login</h1>
            <p>Staff portal access</p>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-group">
                <label for="username">Staff Name</label>
                <input type="text" id="username" name="username" class="form-control" required>
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" class="form-control" required>
            </div>
            
            <button type="submit" class="btn btn-primary" style="width: 100%;">Staff Login</button>
        </form>
        
        <div class="auth-links">
            <p><a href="login.php">← Back to main login</a></p>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>