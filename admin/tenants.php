<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../public/login.php");
    exit();
}
require_once "../config/db.php";

// To store error or success messages for UI
$operation_error = "";
$operation_success = "";

// Add tenant
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["add_tenant"])) {
    $name = $conn->real_escape_string($_POST["name"]);
    $email = $conn->real_escape_string($_POST["email"]);
    $phone = $conn->real_escape_string($_POST["phone"]);
    $apartment_id = intval($_POST["apartment_id"]);
    $username = $conn->real_escape_string($_POST["username"]);
    $password = password_hash($_POST["password"], PASSWORD_DEFAULT);
    $sql = "INSERT INTO tenants (name, email, phone, apartment_id, username, password) VALUES ('$name', '$email', '$phone', $apartment_id, '$username', '$password')";
    if ($conn->query($sql)) {
        $_SESSION['flash_message'] = "Tenant added successfully!";
        $_SESSION['flash_type'] = "success";
        header("Location: tenants.php");
        exit();
    } else {
        $operation_error = "Failed to add tenant: " . $conn->error;
    }
}

// Edit tenant
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["edit_tenant"])) {
    $tenant_id = intval($_POST["tenant_id"]);
    $name = $conn->real_escape_string($_POST["name"]);
    $email = $conn->real_escape_string($_POST["email"]);
    $phone = $conn->real_escape_string($_POST["phone"]);
    $apartment_id = intval($_POST["apartment_id"]);
    $username = $conn->real_escape_string($_POST["username"]);
    $update_password = "";
    if (!empty($_POST["password"])) {
        $password = password_hash($_POST["password"], PASSWORD_DEFAULT);
        $update_password = ", password='$password'";
    }
    $sql = "UPDATE tenants SET name='$name', email='$email', phone='$phone', apartment_id=$apartment_id, username='$username'$update_password WHERE id=$tenant_id";
    if ($conn->query($sql)) {
        $_SESSION['flash_message'] = "Tenant updated successfully!";
        $_SESSION['flash_type'] = "success";
        header("Location: tenants.php");
        exit();
    } else {
        $operation_error = "Failed to update tenant: " . $conn->error;
    }
}

// Delete tenant (delete all related records in payments, maintenance_requests, etc.)
if (isset($_GET["delete_id"])) {
    $delete_id = intval($_GET["delete_id"]);
    // Delete related payments
    $conn->query("DELETE FROM payments WHERE tenant_id = $delete_id");
    // Delete related maintenance requests
    $conn->query("DELETE FROM maintenance_requests WHERE tenant_id = $delete_id");
    // Now delete the tenant
    $conn->query("DELETE FROM tenants WHERE id=$delete_id");
    $_SESSION['flash_message'] = "Tenant and all related records deleted successfully!";
    $_SESSION['flash_type'] = "success";
    header("Location: tenants.php");
    exit();
}

// Delete tenant credentials
if (isset($_GET["delete_credentials_id"])) {
    $tenant_id = intval($_GET["delete_credentials_id"]);
    $conn->query("UPDATE tenants SET username=NULL, password=NULL WHERE id=$tenant_id");
    $_SESSION['flash_message'] = "Credentials deleted successfully!";
    $_SESSION['flash_type'] = "success";
    header("Location: tenants.php");
    exit();
}

// Reset password form and logic
$reset_password_tenant = null;
if (isset($_GET["reset_password_id"])) {
    $tenant_id = intval($_GET["reset_password_id"]);
    $result = $conn->query("SELECT id, username FROM tenants WHERE id=$tenant_id");
    if ($result && $result->num_rows > 0) {
        $reset_password_tenant = $result->fetch_assoc();
    }
}
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["reset_password_submit"])) {
    $tenant_id = intval($_POST["tenant_id"]);
    $new_password = password_hash($_POST["new_password"], PASSWORD_DEFAULT);
    $conn->query("UPDATE tenants SET password='$new_password' WHERE id=$tenant_id");
    $_SESSION['flash_message'] = "Password reset successfully!";
    $_SESSION['flash_type'] = "success";
    header("Location: tenants.php");
    exit();
}

// Edit info
$edit_tenant = null;
if (isset($_GET["edit_id"])) {
    $edit_id = intval($_GET["edit_id"]);
    $result = $conn->query("SELECT * FROM tenants WHERE id=$edit_id");
    if ($result && $result->num_rows > 0) {
        $edit_tenant = $result->fetch_assoc();
    }
}

// Apartments for dropdown
$apartments = [];
$res = $conn->query("SELECT a.id, a.number, t.id as tenant_id FROM apartments a LEFT JOIN tenants t ON a.id = t.apartment_id");
while ($row = $res->fetch_assoc()) $apartments[] = $row;

// List tenants
$tenants = [];
$res = $conn->query("SELECT tenants.*, apartments.number as apartment_number FROM tenants LEFT JOIN apartments ON tenants.apartment_id = apartments.id");
while ($row = $res->fetch_assoc()) $tenants[] = $row;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tenants Management</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary: #4361ee;
            --primary-light: #e0e7ff;
            --secondary: #3f37c9;
            --success: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
            --info: #3b82f6;
            --light: #f8fafc;
            --dark: #1e293b;
            --gray: #64748b;
            --light-gray: #e2e8f0;
            
            --border-radius: 12px;
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --shadow-md: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            --shadow-lg: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f1f5f9;
            color: var(--dark);
            line-height: 1.6;
        }
        
        .container {
            max-width: 1200px;
            margin: 40px auto;
            background: #fff;
            padding: 30px;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-lg);
            transition: all 0.3s ease;
        }
        
        .container:hover {
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.15);
        }
        
        h2 {
            margin-top: 0;
            color: var(--primary);
            font-weight: 600;
            position: relative;
            padding-bottom: 10px;
        }
        
        h2::after {
            content: '';
            position: absolute;
            left: 0;
            bottom: 0;
            width: 50px;
            height: 4px;
            background: var(--primary);
            border-radius: 2px;
        }
        
        .back-button {
            margin-bottom: 20px;
            transition: all 0.3s ease;
        }
        
        .back-button:hover {
            transform: translateX(-3px);
        }
        
        .card {
            border: none;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            transition: all 0.3s ease;
            margin-bottom: 25px;
        }
        
        .card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-lg);
        }
        
        .card-header {
            background-color: var(--primary);
            color: white;
            border-radius: var(--border-radius) var(--border-radius) 0 0 !important;
            padding: 15px 20px;
            font-weight: 600;
        }
        
        .card-header-warning {
            background-color: var(--warning);
        }
        
        .table {
            margin-top: 20px;
            border-radius: var(--border-radius);
            overflow: hidden;
            box-shadow: var(--shadow);
        }
        
        .table th {
            background-color: var(--primary);
            color: white;
            font-weight: 500;
            padding: 15px;
        }
        
        .table td {
            vertical-align: middle;
            padding: 12px 15px;
            transition: all 0.2s ease;
        }
        
        .table tr:hover td {
            background-color: var(--primary-light);
        }
        
        .btn-sm {
            padding: 5px 10px;
            font-size: 0.8rem;
            border-radius: 8px;
        }
        
        .form-select, .form-control {
            border-radius: 8px;
            border: 1px solid var(--light-gray);
            transition: all 0.3s ease;
        }
        
        .form-select:focus, .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 0.25rem rgba(67, 97, 238, 0.25);
        }
        
        .flash-message {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
            animation: slideIn 0.5s forwards, fadeOut 0.5s 3s forwards;
        }
        
        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        
        @keyframes fadeOut {
            from {
                opacity: 1;
            }
            to {
                opacity: 0;
            }
        }
        
        .fade-in {
            animation: fadeIn 0.5s ease-in;
        }
        
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .pulse {
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% {
                box-shadow: 0 0 0 0 rgba(67, 97, 238, 0.4);
            }
            70% {
                box-shadow: 0 0 0 10px rgba(67, 97, 238, 0);
            }
            100% {
                box-shadow: 0 0 0 0 rgba(67, 97, 238, 0);
            }
        }
        
        .action-btn {
            width: 36px;
            height: 36px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: all 0.2s;
        }
        
        .action-btn:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-sm);
        }
        
        .badge-count {
            background-color: var(--primary);
            color: white;
            border-radius: 50%;
            width: 24px;
            height: 24px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
            margin-left: 0.5rem;
        }
        
        /* Floating action button */
        .fab {
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            width: 56px;
            height: 56px;
            border-radius: 50%;
            background-color: var(--primary);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: var(--shadow-md);
            z-index: 100;
            transition: all 0.3s;
        }
        
        .fab:hover {
            transform: translateY(-3px) scale(1.05);
            background-color: var(--secondary);
            box-shadow: var(--shadow-lg);
            color: white;
        }
        
        .section-divider {
            margin: 30px 0;
            border: 0;
            height: 1px;
            background-image: linear-gradient(to right, rgba(67, 97, 238, 0.1), rgba(67, 97, 238, 0.6), rgba(67, 97, 238, 0.1));
        }
    </style>
</head>
<body>
<div class="container fade-in">
    <a href="dashboard.php" class="back-button btn btn-outline-primary">
        <i class="fas fa-arrow-left me-2"></i> Back to Dashboard
    </a>
    <h2><i class="fas fa-users me-2"></i>Tenant Management</h2>
    
    <?php if(isset($_SESSION['flash_message'])): ?>
        <div class="flash-message alert alert-<?php echo $_SESSION['flash_type']; ?> alert-dismissible fade show" role="alert">
            <?php echo $_SESSION['flash_message']; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php unset($_SESSION['flash_message']); unset($_SESSION['flash_type']); ?>
    <?php endif; ?>
    
    <?php if ($reset_password_tenant): ?>
        <!-- Reset Password Form -->
        <div class="card">
            <div class="card-header card-header-warning">
                <h5 class="mb-0"><i class="fas fa-key me-2"></i>Reset Password for <?php echo htmlspecialchars($reset_password_tenant['username']); ?></h5>
            </div>
            <div class="card-body">
                <form method="post">
                    <input type="hidden" name="tenant_id" value="<?php echo $reset_password_tenant['id']; ?>">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">New Password</label>
                            <input type="password" name="new_password" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Confirm Password</label>
                            <input type="password" name="confirm_password" class="form-control" required>
                        </div>
                        <div class="col-12">
                            <button type="submit" name="reset_password_submit" class="btn btn-warning me-2">
                                <i class="fas fa-save me-2"></i>Reset Password
                            </button>
                            <a href="tenants.php" class="btn btn-outline-secondary">
                                <i class="fas fa-times me-2"></i>Cancel
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    <?php elseif ($edit_tenant): ?>
        <!-- Edit Tenant Form -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-edit me-2"></i>Edit Tenant</h5>
            </div>
            <div class="card-body">
                <form method="post">
                    <input type="hidden" name="tenant_id" value="<?php echo $edit_tenant['id']; ?>">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Name</label>
                            <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($edit_tenant['name']); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($edit_tenant['email']); ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Phone</label>
                            <input type="text" name="phone" class="form-control" value="<?php echo htmlspecialchars($edit_tenant['phone']); ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Apartment</label>
                            <select name="apartment_id" class="form-select" required>
                                <option value="">-- Select Apartment --</option>
                                <?php foreach($apartments as $apartment): ?>
                                    <?php
                                    $selected = ($edit_tenant['apartment_id'] == $apartment['id']) ? "selected" : "";
                                    $disabled = ($apartment['tenant_id'] && $edit_tenant['apartment_id'] != $apartment['id']) ? "disabled" : "";
                                    ?>
                                    <option value="<?php echo $apartment['id']; ?>" <?php echo $selected; ?> <?php echo $disabled; ?>>
                                        <?php echo htmlspecialchars($apartment['number']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Username</label>
                            <input type="text" name="username" class="form-control" value="<?php echo htmlspecialchars($edit_tenant['username']); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Password</label>
                            <input type="password" name="password" class="form-control" placeholder="Leave blank to keep current password">
                        </div>
                        <div class="col-12">
                            <button type="submit" name="edit_tenant" class="btn btn-primary me-2">
                                <i class="fas fa-save me-2"></i>Update Tenant
                            </button>
                            <a href="tenants.php" class="btn btn-outline-secondary">
                                <i class="fas fa-times me-2"></i>Cancel
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    <?php else: ?>
        <!-- Add Tenant Form -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-plus-circle me-2"></i>Add New Tenant</h5>
            </div>
            <div class="card-body">
                <form method="post" id="add-tenant-form">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Name</label>
                            <input type="text" name="name" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Phone</label>
                            <input type="text" name="phone" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Apartment</label>
                            <select name="apartment_id" class="form-select" required>
                                <option value="">-- Select Apartment --</option>
                                <?php foreach($apartments as $apartment): ?>
                                    <?php if (!$apartment['tenant_id']) { ?>
                                        <option value="<?php echo $apartment['id']; ?>">
                                            <?php echo htmlspecialchars($apartment['number']); ?>
                                        </option>
                                    <?php } ?>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Username</label>
                            <input type="text" name="username" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Password</label>
                            <input type="password" name="password" class="form-control" required>
                        </div>
                        <div class="col-12">
                            <button type="submit" name="add_tenant" class="btn btn-primary pulse">
                                <i class="fas fa-user-plus me-2"></i>Add Tenant
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <hr class="section-divider">

    <!-- Tenants List -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="fas fa-list me-2"></i>All Tenants</h5>
            <span class="badge bg-primary">
                Total: <?php echo count($tenants); ?>
            </span>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Apartment</th>
                            <th>Username</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tenants as $tenant): ?>
                        <tr class="fade-in">
                            <td>#<?php echo $tenant['id']; ?></td>
                            <td><?php echo htmlspecialchars($tenant['name']); ?></td>
                            <td><?php echo htmlspecialchars($tenant['email']); ?></td>
                            <td><?php echo htmlspecialchars($tenant['phone']); ?></td>
                            <td><?php echo htmlspecialchars($tenant['apartment_number']); ?></td>
                            <td>
                                <?php if ($tenant['username']): ?>
                                    <span class="badge bg-success"><?php echo htmlspecialchars($tenant['username']); ?></span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">No credentials</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <a href="tenants.php?edit_id=<?php echo $tenant['id']; ?>" 
                                   class="action-btn btn btn-sm btn-info me-1"
                                   title="Edit"
                                   data-bs-toggle="tooltip">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <a href="tenants.php?delete_id=<?php echo $tenant['id']; ?>" 
                                   class="action-btn btn btn-sm btn-danger me-1"
                                   onclick="return confirm('Are you sure you want to delete this tenant and all related records?');"
                                   title="Delete"
                                   data-bs-toggle="tooltip">
                                    <i class="fas fa-trash-alt"></i>
                                </a>
                                <?php if ($tenant['username']): ?>
                                    <a href="tenants.php?delete_credentials_id=<?php echo $tenant['id']; ?>" 
                                       class="action-btn btn btn-sm btn-warning me-1"
                                       onclick="return confirm('Remove username and password for this tenant?');"
                                       title="Delete Credentials"
                                       data-bs-toggle="tooltip">
                                        <i class="fas fa-user-times"></i>
                                    </a>
                                    <a href="tenants.php?reset_password_id=<?php echo $tenant['id']; ?>" 
                                       class="action-btn btn btn-sm btn-secondary"
                                       title="Reset Password"
                                       data-bs-toggle="tooltip">
                                        <i class="fas fa-key"></i>
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Floating action button -->
<a href="#add-tenant-form" class="fab d-lg-none">
    <i class="fas fa-plus"></i>
</a>

<!-- Bootstrap JS -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.6/dist/umd/popper.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/js/all.min.js"></script>

<script>
    // Add animation to table rows
    document.addEventListener('DOMContentLoaded', function() {
        const rows = document.querySelectorAll('tbody tr');
        rows.forEach((row, index) => {
            row.style.animationDelay = `${index * 0.05}s`;
            row.classList.add('fade-in');
        });
        
        // Close flash message after 3 seconds
        const flashMessage = document.querySelector('.flash-message');
        if (flashMessage) {
            setTimeout(() => {
                flashMessage.style.display = 'none';
            }, 3000);
        }
        
        // Initialize tooltips
        $('[data-bs-toggle="tooltip"]').tooltip();
        
        // Smooth scroll for FAB
        $('.fab').on('click', function(e) {
            e.preventDefault();
            $('html, body').animate({
                scrollTop: $('#add-tenant-form').offset().top - 20
            }, 500);
        });
        
        // Password confirmation validation
        $('form').on('submit', function() {
            if ($('input[name="new_password"]').length && $('input[name="confirm_password"]').length) {
                if ($('input[name="new_password"]').val() !== $('input[name="confirm_password"]').val()) {
                    alert('Passwords do not match!');
                    return false;
                }
            }
            return true;
        });
    });
</script>
</body>
</html>