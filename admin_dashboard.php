<?php
session_start();
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: login.php');
    exit;
}

$page_title = "Admin Dashboard - Rimbunan Cafe";
include 'config/database.php';

// Handle product operations
if (isset($_POST['add_product'])) {
    $name = $_POST['product_name'];
    $category = $_POST['product_category'];
    $info = $_POST['product_info'];
    $price = $_POST['product_price'];
    $status = $_POST['product_status'];
    $image_path = null;
    
    // Handle image upload
    if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = 'uploads/products/';
        $file_extension = strtolower(pathinfo($_FILES['product_image']['name'], PATHINFO_EXTENSION));
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
        
        if (in_array($file_extension, $allowed_extensions)) {
            $unique_name = uniqid() . '_' . time() . '.' . $file_extension;
            $upload_path = $upload_dir . $unique_name;
            
            if (move_uploaded_file($_FILES['product_image']['tmp_name'], $upload_path)) {
                $image_path = $upload_path;
            }
        }
    }
    
    $stmt = $pdo->prepare("INSERT INTO product (product_name, product_category, product_info, product_price, product_status, product_image) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$name, $category, $info, $price, $status, $image_path]);
}

if (isset($_POST['update_product'])) {
    $id = $_POST['product_id'];
    $name = $_POST['product_name'];
    $category = $_POST['product_category'];
    $info = $_POST['product_info'];
    $price = $_POST['product_price'];
    $status = $_POST['product_status'];
    $existing_image = $_POST['existing_image'];
    $image_path = $existing_image;
    
    // Handle new image upload
    if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = 'uploads/products/';
        $file_extension = strtolower(pathinfo($_FILES['product_image']['name'], PATHINFO_EXTENSION));
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
        
        if (in_array($file_extension, $allowed_extensions)) {
            $unique_name = uniqid() . '_' . time() . '.' . $file_extension;
            $upload_path = $upload_dir . $unique_name;
            
            if (move_uploaded_file($_FILES['product_image']['tmp_name'], $upload_path)) {
                // Delete old image if exists
                if ($existing_image && file_exists($existing_image)) {
                    unlink($existing_image);
                }
                $image_path = $upload_path;
            }
        }
    }
    
    $stmt = $pdo->prepare("UPDATE product SET product_name = ?, product_category = ?, product_info = ?, product_price = ?, product_status = ?, product_image = ? WHERE product_id = ?");
    $stmt->execute([$name, $category, $info, $price, $status, $image_path, $id]);
}

if (isset($_POST['delete_product'])) {
    $id = $_POST['product_id'];
    
    // Get image path before deleting
    $stmt = $pdo->prepare("SELECT product_image FROM product WHERE product_id = ?");
    $stmt->execute([$id]);
    $product = $stmt->fetch();
    
    // Delete image file if exists
    if ($product && $product['product_image'] && file_exists($product['product_image'])) {
        unlink($product['product_image']);
    }
    
    $stmt = $pdo->prepare("DELETE FROM product WHERE product_id = ?");
    $stmt->execute([$id]);
}

// Handle category operations
if (isset($_POST['add_category'])) {
    $name = $_POST['category_name'];
    $icon = $_POST['category_icon'];
    
    $stmt = $pdo->prepare("INSERT INTO categories (category_name, category_icon) VALUES (?, ?)");
    $stmt->execute([$name, $icon]);
}

if (isset($_POST['delete_category'])) {
    $id = $_POST['category_id'];
    $stmt = $pdo->prepare("DELETE FROM categories WHERE category_id = ?");
    $stmt->execute([$id]);
}

// Handle staff operations
if (isset($_POST['add_staff'])) {
    $name = $_POST['staff_name'];
    $email = $_POST['staff_email'];
    $phone = $_POST['staff_phone'];
    $password = password_hash($_POST['staff_password'], PASSWORD_DEFAULT);
    
    $stmt = $pdo->prepare("INSERT INTO staff (staff_name, staff_email, staff_phonenumber, staff_password) VALUES (?, ?, ?, ?)");
    $stmt->execute([$name, $email, $phone, $password]);
}

if (isset($_POST['delete_staff'])) {
    $id = $_POST['staff_id'];
    $stmt = $pdo->prepare("DELETE FROM staff WHERE staff_id = ?");
    $stmt->execute([$id]);
}

// Handle rider operations
if (isset($_POST['add_rider'])) {
    $username = $_POST['rider_username'];
    $email = $_POST['rider_email'];
    $phone = $_POST['rider_phone'];
    $vehicle = $_POST['rider_vehicle'];
    $password = password_hash($_POST['rider_password'], PASSWORD_DEFAULT);
    
    $stmt = $pdo->prepare("INSERT INTO rider (rider_username, rider_email, rider_phonenumber, rider_vehicleinfo, rider_password, rider_status) VALUES (?, ?, ?, ?, ?, 1)");
    $stmt->execute([$username, $email, $phone, $vehicle, $password]);
}

if (isset($_POST['delete_rider'])) {
    $id = $_POST['rider_id'];
    $stmt = $pdo->prepare("DELETE FROM rider WHERE rider_id = ?");
    $stmt->execute([$id]);
}

// Handle rider status toggle
if (isset($_POST['toggle_rider_status'])) {
    $rider_id = $_POST['rider_id'];
    $new_status = $_POST['new_rider_status'];
    $stmt = $pdo->prepare("UPDATE rider SET rider_status = ? WHERE rider_id = ?");
    $stmt->execute([$new_status, $rider_id]);
}

// Get data
$stmt = $pdo->prepare("SELECT * FROM product ORDER BY product_id DESC");
$stmt->execute();
$products = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT * FROM staff ORDER BY staff_id DESC");
$stmt->execute();
$staff = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT * FROM rider ORDER BY rider_id DESC");
$stmt->execute();
$riders = $stmt->fetchAll();

// Get categories
$stmt = $pdo->prepare("SELECT * FROM categories WHERE category_status = 'Active' ORDER BY category_name");
$stmt->execute();
$categories = $stmt->fetchAll();
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
                <div class="logo">🧑‍💼 Admin Dashboard</div>
                <div class="nav-actions">
                    <button class="btn btn-secondary" onclick="refreshPage()" style="margin-right: 1rem;">🔄 Refresh</button>
                    <a href="admin_reports.php" class="btn btn-secondary" style="margin-right: 1rem;">📊 Reports</a>
                    <a href="address_tracking_report.php" class="btn btn-secondary" style="margin-right: 1rem;">📍 Address Tracking</a>
                    <span>Welcome, <?php echo $_SESSION['username']; ?>!</span>
                    <a href="logout.php" class="btn btn-danger">Logout</a>
                </div>
            </div>
        </div>
    </div>
    
    <div class="container">
        <div class="dashboard-content">
            <div class="dashboard-tabs">
                <button class="tab-btn active" onclick="showTab('menu')">🍔 Menu Management</button>
                <button class="tab-btn" onclick="showTab('categories')">📂 Category Management</button>
                <?php foreach ($categories as $category): ?>
                    <button class="tab-btn" onclick="showTab('category-<?php echo $category['category_id']; ?>')"><?php echo $category['category_icon']; ?> <?php echo $category['category_name']; ?></button>
                <?php endforeach; ?>
                <button class="tab-btn" onclick="showTab('staff')">👨‍🍳 Staff Management</button>
                <button class="tab-btn" onclick="showTab('riders')">🛵 Rider Management</button>
            </div>
            
            <!-- Menu Management Tab -->
            <div id="menu" class="tab-content active">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
                    <h2>Menu Management</h2>
                    <button class="btn btn-primary" onclick="openModal('add-product-modal')">Add New Item</button>
                </div>
                
                <div style="background: white; border-radius: 15px; overflow: hidden; box-shadow: 0 5px 15px rgba(0,0,0,0.1);">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Image</th>
                                <th>Name</th>
                                <th>Description</th>
                                <th>Price</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($products as $product): ?>
                                <tr>
                                    <td><?php echo $product['product_id']; ?></td>
                                    <td>
                                        <?php if ($product['product_image'] && file_exists($product['product_image'])): ?>
                                            <img src="<?php echo $product['product_image']; ?>" alt="Product Image" style="width: 50px; height: 50px; object-fit: cover; border-radius: 8px;">
                                        <?php else: ?>
                                            <div style="width: 50px; height: 50px; background: #f8f9fa; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem;">🍔</div>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($product['product_name']); ?></td>
                                    <td><?php echo htmlspecialchars($product['product_info']); ?></td>
                                    <td>RM <?php echo number_format($product['product_price'], 2); ?></td>
                                    <td>
                                        <span class="status-badge <?php echo $product['product_status'] === 'Available' ? 'status-completed' : 'status-pending'; ?>">
                                            <?php echo $product['product_status']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <button class="btn" onclick="editProduct(<?php echo htmlspecialchars(json_encode($product)); ?>)" style="background: #28a745; color: white; font-size: 0.875rem; margin-right: 0.5rem; border-radius: 6px; border: none; padding: 0.5rem 1rem;">
                                            ✏️ Edit
                                        </button>
                                        <form method="POST" style="display: inline-block;">
                                            <input type="hidden" name="product_id" value="<?php echo $product['product_id']; ?>">
                                            <button type="submit" name="delete_product" class="btn btn-danger" style="font-size: 0.875rem; padding: 0.5rem 1rem;" onclick="return confirm('Are you sure?')">
                                                🗑️ Delete
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Category Management Tab -->
            <div id="categories" class="tab-content">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
                    <h2>📂 Category Management</h2>
                    <button class="btn btn-primary" onclick="openModal('add-category-modal')">Add New Category</button>
                </div>
                
                <div style="background: white; border-radius: 15px; overflow: hidden; box-shadow: 0 5px 15px rgba(0,0,0,0.1);">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Icon</th>
                                <th>Category Name</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($categories as $category): ?>
                                <tr>
                                    <td><?php echo $category['category_id']; ?></td>
                                    <td style="font-size: 1.5rem;"><?php echo $category['category_icon']; ?></td>
                                    <td><?php echo htmlspecialchars($category['category_name']); ?></td>
                                    <td>
                                        <span class="status-badge <?php echo $category['category_status'] === 'Active' ? 'status-completed' : 'status-pending'; ?>">
                                            <?php echo $category['category_status']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <form method="POST" style="display: inline-block;">
                                            <input type="hidden" name="category_id" value="<?php echo $category['category_id']; ?>">
                                            <button type="submit" name="delete_category" class="btn btn-danger" style="font-size: 0.875rem; padding: 0.5rem 1rem;" onclick="return confirm('Are you sure?')">
                                                🗑️ Delete
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Dynamic Category Tabs -->
            <?php foreach ($categories as $category): ?>
                <div id="category-<?php echo $category['category_id']; ?>" class="tab-content">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
                        <h2><?php echo $category['category_icon']; ?> <?php echo $category['category_name']; ?></h2>
                        <button class="btn btn-primary" onclick="openAddModal('<?php echo $category['category_name']; ?>')">Add <?php echo $category['category_name']; ?> Item</button>
                    </div>
                    
                    <div style="background: white; border-radius: 15px; overflow: hidden; box-shadow: 0 5px 15px rgba(0,0,0,0.1);">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Image</th>
                                    <th>Name</th>
                                    <th>Description</th>
                                    <th>Price</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($products as $product): ?>
                                    <?php if (($product['product_category'] ?? '') === $category['category_name']): ?>
                                        <tr>
                                            <td><?php echo $product['product_id']; ?></td>
                                            <td>
                                                <?php if ($product['product_image'] && file_exists($product['product_image'])): ?>
                                                    <img src="<?php echo $product['product_image']; ?>" alt="Product Image" style="width: 50px; height: 50px; object-fit: cover; border-radius: 8px;">
                                                <?php else: ?>
                                                    <div style="width: 50px; height: 50px; background: #f8f9fa; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem;"><?php echo $category['category_icon']; ?></div>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($product['product_name']); ?></td>
                                            <td><?php echo htmlspecialchars($product['product_info']); ?></td>
                                            <td>RM <?php echo number_format($product['product_price'], 2); ?></td>
                                            <td>
                                                <span class="status-badge <?php echo $product['product_status'] === 'Available' ? 'status-completed' : 'status-pending'; ?>">
                                                    <?php echo $product['product_status']; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <button class="btn" onclick="editProduct(<?php echo htmlspecialchars(json_encode($product)); ?>)" style="background: #28a745; color: white; font-size: 0.875rem; margin-right: 0.5rem; border-radius: 6px; border: none; padding: 0.5rem 1rem;">
                                                    ✏️ Edit
                                                </button>
                                                <form method="POST" style="display: inline-block;">
                                                    <input type="hidden" name="product_id" value="<?php echo $product['product_id']; ?>">
                                                    <button type="submit" name="delete_product" class="btn btn-danger" style="font-size: 0.875rem; padding: 0.5rem 1rem;" onclick="return confirm('Are you sure?')">
                                                        🗑️ Delete
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endforeach; ?>
            
            <!-- Staff Management Tab -->
            <div id="staff" class="tab-content">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
                    <h2>Staff Management</h2>
                    <button class="btn btn-primary" onclick="openModal('add-staff-modal')">Add New Staff</button>
                </div>
                
                <div style="background: white; border-radius: 15px; overflow: hidden; box-shadow: 0 5px 15px rgba(0,0,0,0.1);">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($staff as $member): ?>
                                <tr>
                                    <td><?php echo $member['staff_id']; ?></td>
                                    <td><?php echo htmlspecialchars($member['staff_name']); ?></td>
                                    <td><?php echo htmlspecialchars($member['staff_email']); ?></td>
                                    <td><?php echo htmlspecialchars($member['staff_phonenumber']); ?></td>
                                    <td>
                                        <form method="POST" style="display: inline-block;">
                                            <input type="hidden" name="staff_id" value="<?php echo $member['staff_id']; ?>">
                                            <button type="submit" name="delete_staff" class="btn btn-danger" style="font-size: 0.875rem; padding: 0.5rem 1rem;" onclick="return confirm('Are you sure?')">
                                                Delete
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Rider Management Tab -->
            <div id="riders" class="tab-content">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
                    <h2>Rider Management</h2>
                    <button class="btn btn-primary" onclick="openModal('add-rider-modal')">Add New Rider</button>
                </div>
                
                <div style="background: white; border-radius: 15px; overflow: hidden; box-shadow: 0 5px 15px rgba(0,0,0,0.1);">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Username</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>Vehicle</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($riders as $rider): ?>
                                <tr>
                                    <td><?php echo $rider['rider_id']; ?></td>
                                    <td><?php echo htmlspecialchars($rider['rider_username']); ?></td>
                                    <td><?php echo htmlspecialchars($rider['rider_email']); ?></td>
                                    <td><?php echo htmlspecialchars($rider['rider_phonenumber']); ?></td>
                                    <td><?php echo htmlspecialchars($rider['rider_vehicleinfo']); ?></td>
                                    <td>
                                        <form method="POST" style="display: inline-block;">
                                            <input type="hidden" name="rider_id" value="<?php echo $rider['rider_id']; ?>">
                                            <input type="hidden" name="toggle_rider_status" value="1">
                                            <input type="hidden" name="new_rider_status" value="<?php echo $rider['rider_status'] == 1 ? 0 : 1; ?>">
                                            <button type="submit" class="btn" style="background: <?php echo $rider['rider_status'] == 1 ? '#28a745' : '#dc3545'; ?>; color: white; font-size: 0.875rem; padding: 0.5rem 1rem;">
                                                <?php echo $rider['rider_status'] == 1 ? '🟢 Available' : '🔴 Unavailable'; ?>
                                            </button>
                                        </form>
                                    </td>
                                    <td>
                                        <form method="POST" style="display: inline-block;">
                                            <input type="hidden" name="rider_id" value="<?php echo $rider['rider_id']; ?>">
                                            <button type="submit" name="delete_rider" class="btn btn-danger" style="font-size: 0.875rem; padding: 0.5rem 1rem;" onclick="return confirm('Are you sure?')">
                                                Delete
                                            </button>
                                        </form>
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

<!-- Add Product Modal -->
<div id="add-product-modal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Add New Menu Item</h2>
            <span class="close" onclick="closeModal('add-product-modal')">&times;</span>
        </div>
        
        <form method="POST" enctype="multipart/form-data">
            <div class="form-group">
                <label for="product_name">Product Name</label>
                <input type="text" id="product_name" name="product_name" class="form-control" required>
            </div>
            
            <div class="form-group">
                <label for="product_category">Category</label>
                <select id="product_category" name="product_category" class="form-control" required>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?php echo $category['category_name']; ?>"><?php echo $category['category_icon']; ?> <?php echo $category['category_name']; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label for="product_image">Product Image</label>
                <input type="file" id="product_image" name="product_image" class="form-control" accept="image/*">
                <small style="color: #666; font-size: 0.875rem;">Supported formats: JPG, JPEG, PNG, GIF</small>
            </div>
            
            <div class="form-group">
                <label for="product_info">Description</label>
                <textarea id="product_info" name="product_info" class="form-control" rows="3" required></textarea>
            </div>
            
            <div class="form-group">
                <label for="product_price">Price (RM)</label>
                <input type="number" id="product_price" name="product_price" class="form-control" step="0.01" required>
            </div>
            
            <div class="form-group">
                <label for="product_status">Status</label>
                <select id="product_status" name="product_status" class="form-control" required>
                    <option value="Available">Available</option>
                    <option value="Unavailable">Unavailable</option>
                </select>
            </div>
            
            <button type="submit" name="add_product" class="btn btn-primary" style="width: 100%;">Add Product</button>
        </form>
    </div>
</div>

<!-- Edit Product Modal -->
<div id="edit-product-modal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Edit Menu Item</h2>
            <span class="close" onclick="closeModal('edit-product-modal')">&times;</span>
        </div>
        
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" id="edit_product_id" name="product_id">
            <input type="hidden" id="edit_existing_image" name="existing_image">
            
            <div class="form-group">
                <label for="edit_product_name">Product Name</label>
                <input type="text" id="edit_product_name" name="product_name" class="form-control" required>
            </div>
            
            <div class="form-group">
                <label for="edit_product_category">Category</label>
                <select id="edit_product_category" name="product_category" class="form-control" required>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?php echo $category['category_name']; ?>"><?php echo $category['category_icon']; ?> <?php echo $category['category_name']; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label>Current Image</label>
                <div id="current_image_preview" style="margin-bottom: 1rem;"></div>
                <label for="edit_product_image">Upload New Image (optional)</label>
                <input type="file" id="edit_product_image" name="product_image" class="form-control" accept="image/*">
                <small style="color: #666; font-size: 0.875rem;">Leave empty to keep current image</small>
            </div>
            
            <div class="form-group">
                <label for="edit_product_info">Description</label>
                <textarea id="edit_product_info" name="product_info" class="form-control" rows="3" required></textarea>
            </div>
            
            <div class="form-group">
                <label for="edit_product_price">Price (RM)</label>
                <input type="number" id="edit_product_price" name="product_price" class="form-control" step="0.01" required>
            </div>
            
            <div class="form-group">
                <label for="edit_product_status">Status</label>
                <select id="edit_product_status" name="product_status" class="form-control" required>
                    <option value="Available">Available</option>
                    <option value="Unavailable">Unavailable</option>
                </select>
            </div>
            
            <button type="submit" name="update_product" class="btn btn-primary" style="width: 100%;">Update Product</button>
        </form>
    </div>
</div>

<!-- Add Staff Modal -->
<div id="add-staff-modal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Add New Staff</h2>
            <span class="close" onclick="closeModal('add-staff-modal')">&times;</span>
        </div>
        
        <form method="POST">
            <div class="form-group">
                <label for="staff_name">Staff Name</label>
                <input type="text" id="staff_name" name="staff_name" class="form-control" required>
            </div>
            
            <div class="form-group">
                <label for="staff_email">Email</label>
                <input type="email" id="staff_email" name="staff_email" class="form-control" required>
            </div>
            
            <div class="form-group">
                <label for="staff_phone">Phone Number</label>
                <input type="tel" id="staff_phone" name="staff_phone" class="form-control" required>
            </div>
            
            <div class="form-group">
                <label for="staff_password">Password</label>
                <input type="password" id="staff_password" name="staff_password" class="form-control" required>
            </div>
            
            <button type="submit" name="add_staff" class="btn btn-primary" style="width: 100%;">Add Staff</button>
        </form>
    </div>
</div>

<!-- Add Rider Modal -->
<div id="add-rider-modal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Add New Rider</h2>
            <span class="close" onclick="closeModal('add-rider-modal')">&times;</span>
        </div>
        
        <form method="POST">
            <div class="form-group">
                <label for="rider_username">Username</label>
                <input type="text" id="rider_username" name="rider_username" class="form-control" required>
            </div>
            
            <div class="form-group">
                <label for="rider_email">Email</label>
                <input type="email" id="rider_email" name="rider_email" class="form-control" required>
            </div>
            
            <div class="form-group">
                <label for="rider_phone">Phone Number</label>
                <input type="tel" id="rider_phone" name="rider_phone" class="form-control" required>
            </div>
            
            <div class="form-group">
                <label for="rider_vehicle">Vehicle Information</label>
                <input type="text" id="rider_vehicle" name="rider_vehicle" class="form-control" placeholder="e.g., Honda Wave 125, ABC1234" required>
            </div>
            
            <div class="form-group">
                <label for="rider_password">Password</label>
                <input type="password" id="rider_password" name="rider_password" class="form-control" required>
            </div>
            
            <button type="submit" name="add_rider" class="btn btn-primary" style="width: 100%;">Add Rider</button>
        </form>
    </div>
</div>

<!-- Add Category Modal -->
<div id="add-category-modal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Add New Category</h2>
            <span class="close" onclick="closeModal('add-category-modal')">&times;</span>
        </div>
        
        <form method="POST">
            <div class="form-group">
                <label for="category_name">Category Name</label>
                <input type="text" id="category_name" name="category_name" class="form-control" required>
            </div>
            
            <div class="form-group">
                <label for="category_icon">Category Icon (Emoji)</label>
                <input type="text" id="category_icon" name="category_icon" class="form-control" placeholder="🍽️" required>
            </div>
            
            <button type="submit" name="add_category" class="btn btn-primary" style="width: 100%;">Add Category</button>
        </form>
    </div>
</div>

<script src="js/main.js"></script>
<script>
function openAddModal(category) {
    if (category) {
        document.getElementById('product_category').value = category;
    }
    openModal('add-product-modal');
}

function editProduct(product) {
    document.getElementById('edit_product_id').value = product.product_id;
    document.getElementById('edit_product_name').value = product.product_name;
    document.getElementById('edit_product_info').value = product.product_info;
    document.getElementById('edit_product_price').value = product.product_price;
    document.getElementById('edit_product_status').value = product.product_status;
    document.getElementById('edit_existing_image').value = product.product_image || '';
    document.getElementById('edit_product_category').value = product.product_category || 'food';
    document.getElementById('edit_product_category').value = product.product_category || '';
    
    // Show current image preview
    const imagePreview = document.getElementById('current_image_preview');
    if (product.product_image) {
        imagePreview.innerHTML = `
            <div style="text-align: center;">
                <p style="margin-bottom: 0.5rem; font-weight: 500;">Current Image:</p>
                <img src="${product.product_image}" alt="Current Product Image" style="max-width: 150px; max-height: 150px; object-fit: cover; border-radius: 8px; border: 2px solid #e9ecef;">
            </div>
        `;
    } else {
        imagePreview.innerHTML = `
            <div style="text-align: center; padding: 2rem; background: #f8f9fa; border-radius: 8px; border: 2px dashed #dee2e6;">
                <div style="font-size: 3rem; margin-bottom: 0.5rem;">🍔</div>
                <p style="color: #666; margin: 0;">No image uploaded</p>
            </div>
        `;
    }
    
    openModal('edit-product-modal');
}
</script>

</body>
</html>