<?php
session_start();
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'customer') {
    header('Location: login.php');
    exit;
}

include 'config/database.php';

$success = false;
$error = '';

if ($_POST) {
    try {
        $username = trim($_POST['username']);
        $email = trim($_POST['email']);
        $phone = trim($_POST['phone']);
        $address = trim($_POST['address']);
        $new_password = $_POST['new_password'];
        
        // Validate required fields
        if (empty($username) || empty($email) || empty($phone) || empty($address)) {
            throw new Exception('All fields are required.');
        }
        
        // Validate email format
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Please enter a valid email address.');
        }
        
        // Check if username or email already exists for other users
        $stmt = $pdo->prepare("SELECT cust_id FROM customer WHERE (cust_username = ? OR cust_email = ?) AND cust_id != ?");
        $stmt->execute([$username, $email, $_SESSION['user_id']]);
        if ($stmt->fetch()) {
            throw new Exception('Username or email already exists.');
        }
        
        if (!empty($new_password)) {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE customer SET cust_username = ?, cust_email = ?, cust_phonenumber = ?, cust_address = ?, cust_password = ? WHERE cust_id = ?");
            $stmt->execute([$username, $email, $phone, $address, $hashed_password, $_SESSION['user_id']]);
        } else {
            $stmt = $pdo->prepare("UPDATE customer SET cust_username = ?, cust_email = ?, cust_phonenumber = ?, cust_address = ? WHERE cust_id = ?");
            $stmt->execute([$username, $email, $phone, $address, $_SESSION['user_id']]);
        }
        
        $_SESSION['username'] = $username;
        $success = true;
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

if ($success) {
    header('Location: customer_dashboard.php?updated=1');
    exit;
} elseif ($error) {
    header('Location: customer_dashboard.php?error=' . urlencode($error));
    exit;
}
?>