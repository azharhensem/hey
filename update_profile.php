<?php
session_start();
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'customer') {
    header('Location: login.php');
    exit;
}

include 'config/database.php';

if ($_POST) {
    $username = $_POST['username'];
    $email = $_POST['email'];
    $phone = $_POST['phone'];
    $address = $_POST['address'];
    $new_password = $_POST['new_password'];
    
    if (!empty($new_password)) {
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE customer SET cust_username = ?, cust_email = ?, cust_phonenumber = ?, cust_address = ?, cust_password = ? WHERE cust_id = ?");
        $stmt->execute([$username, $email, $phone, $address, $hashed_password, $_SESSION['user_id']]);
    } else {
        $stmt = $pdo->prepare("UPDATE customer SET cust_username = ?, cust_email = ?, cust_phonenumber = ?, cust_address = ? WHERE cust_id = ?");
        $stmt->execute([$username, $email, $phone, $address, $_SESSION['user_id']]);
    }
    
    $_SESSION['username'] = $username;
    
    header('Location: customer_dashboard.php?updated=1');
    exit;
}
?>