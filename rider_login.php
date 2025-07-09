<?php
$page_title = "Rider Login - Rimbunan Cafe";
include 'includes/header.php';
include 'config/database.php';

$error = '';

if ($_POST) {
    $username = $_POST['username'];
    $password = $_POST['password'];
    
    $stmt = $pdo->prepare("SELECT * FROM rider WHERE rider_username = ?");
    $stmt->execute([$username]);
    $rider = $stmt->fetch();
    
    if ($rider && password_verify($password, $rider['rider_password'])) {
        $_SESSION['user_id'] = $rider['rider_id'];
        $_SESSION['user_type'] = 'rider';
        $_SESSION['username'] = $rider['rider_username'];
        header('Location: rider_dashboard.php');
        exit;
    }
    
    $error = 'Invalid username or password';
}
?>

<div class="auth-container">
    <div class="auth-card">
        <div class="auth-header">
            <h1>🛵 Rider Login</h1>
            <p>Delivery rider portal access</p>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-group">
                <label for="username">Rider Username</label>
                <input type="text" id="username" name="username" class="form-control" required>
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" class="form-control" required>
            </div>
            
            <button type="submit" class="btn btn-primary" style="width: 100%;">Rider Login</button>
        </form>
        
        <div class="auth-links">
            <p><a href="login.php">← Back to main login</a></p>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>