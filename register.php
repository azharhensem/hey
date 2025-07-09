<?php
$page_title = "Register - Rimbunan Cafe";
include 'includes/header.php';
include 'config/database.php';

$error = '';
$success = '';

if ($_POST) {
    $username = $_POST['username'];
    $email = $_POST['email'];
    $phone = $_POST['phone'];
    $address = $_POST['address'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    
    // Check if username exists
    $stmt = $pdo->prepare("SELECT * FROM customer WHERE cust_username = ? OR cust_email = ?");
    $stmt->execute([$username, $email]);
    
    if ($stmt->fetch()) {
        $error = 'Username or email already exists';
    } else {
        $stmt = $pdo->prepare("INSERT INTO customer (cust_username, cust_password, cust_email, cust_phonenumber, cust_address) VALUES (?, ?, ?, ?, ?)");
        
        if ($stmt->execute([$username, $password, $email, $phone, $address])) {
            $success = 'Account created successfully! You can now login.';
        } else {
            $error = 'Registration failed. Please try again.';
        }
    }
}
?>

<div class="auth-container">
    <div class="auth-card">
        <div class="auth-header">
            <h1>🍔 Join Rimbunan Cafe</h1>
            <p>Create your account to start ordering delicious food!</p>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" class="form-control" required>
            </div>
            
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" class="form-control" required>
            </div>
            
            <div class="form-group">
                <label for="phone">Phone Number</label>
                <input type="tel" id="phone" name="phone" class="form-control" required>
            </div>
            
            <div class="form-group">
                <label for="address">Address</label>
                <textarea id="address" name="address" class="form-control" rows="3" required></textarea>
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" class="form-control" required>
            </div>
            
            <button type="submit" class="btn btn-primary" style="width: 100%;">Create Account</button>
        </form>
        
        <div class="auth-links">
            <p>Already have an account? <a href="login.php">Sign in here</a></p>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>