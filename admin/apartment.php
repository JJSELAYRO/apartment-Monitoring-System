<?php
session_start();
require_once "../config/db.php";

// Only allow access for admin users
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../public/login.php");
    exit();
}

$add_success = $add_error = "";

// Handle new apartment (number, floor, status, image)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_apartment'])) {
    $number = $conn->real_escape_string($_POST['number']);
    $floor = isset($_POST['floor']) ? (int)$_POST['floor'] : 1;
    $status = $conn->real_escape_string($_POST['status']);

    // Handle file upload (image_path)
    $imagePath = '';
    if (isset($_FILES['apartment_image']) && $_FILES['apartment_image']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = '../uploads/apartments/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        $fileExt = strtolower(pathinfo($_FILES['apartment_image']['name'], PATHINFO_EXTENSION));
        $allowedTypes = ['jpg', 'jpeg', 'png', 'webp'];
        if (in_array($fileExt, $allowedTypes)) {
            $fileName   = 'apartment_' . time() . '_' . uniqid() . '.' . $fileExt;
            $targetPath = $uploadDir . $fileName;
            if (move_uploaded_file($_FILES['apartment_image']['tmp_name'], $targetPath)) {
                $imagePath = 'uploads/apartments/' . $fileName;
            } else {
                $add_error = "Image upload failed.";
            }
        } else {
            $add_error = "Invalid image type. Allowed: jpg, jpeg, png, webp.";
        }
    }

    if (!$add_error) {
        $sql = "INSERT INTO apartments (number, floor, status, image_path) VALUES ('$number', $floor, '$status', " . ($imagePath ? "'$imagePath'" : "NULL") . ")";
        if ($conn->query($sql)) {
            $add_success = "Apartment added successfully!";
            $_SESSION['flash_message'] = $add_success;
            $_SESSION['flash_type'] = 'success';
            header("Location: apartment.php");
            exit();
        } else {
            $add_error = "Database error: " . $conn->error;
        }
    }
}

// Edit apartment (number/floor/status)
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["edit_apartment"])) {
    if (
        isset($_POST["apartment_id"]) &&
        isset($_POST["apartment_number"]) &&
        isset($_POST["apartment_floor"]) &&
        isset($_POST["apartment_status"])
    ) {
        $apartment_id = intval($_POST["apartment_id"]);
        $apartment_number = $conn->real_escape_string($_POST["apartment_number"]);
        $apartment_floor = intval($_POST["apartment_floor"]);
        $apartment_status = $conn->real_escape_string($_POST["apartment_status"]);

        // Optionally allow updating image on edit
        $imageSql = "";
        if (isset($_FILES['apartment_image']) && $_FILES['apartment_image']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = '../uploads/apartments/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            $fileExt = strtolower(pathinfo($_FILES['apartment_image']['name'], PATHINFO_EXTENSION));
            $allowedTypes = ['jpg', 'jpeg', 'png', 'webp'];
            if (in_array($fileExt, $allowedTypes)) {
                $fileName   = 'apartment_' . time() . '_' . uniqid() . '.' . $fileExt;
                $targetPath = $uploadDir . $fileName;
                if (move_uploaded_file($_FILES['apartment_image']['tmp_name'], $targetPath)) {
                    $imagePath = 'uploads/apartments/' . $fileName;
                    $imageSql = ", image_path='$imagePath'";
                }
            }
        }

        $sql = "UPDATE apartments SET number='$apartment_number', floor=$apartment_floor, status='$apartment_status' $imageSql WHERE id=$apartment_id";
        $conn->query($sql);
        $_SESSION['flash_message'] = "Apartment updated successfully!";
        $_SESSION['flash_type'] = 'success';
        header("Location: apartment.php");
        exit();
    }
}

// Delete apartment (prevent delete if tenants are assigned)
if (isset($_GET["delete_id"])) {
    $delete_id = intval($_GET["delete_id"]);
    // Check for tenants
    $tenant_check = $conn->query("SELECT COUNT(*) as count FROM tenants WHERE apartment_id = $delete_id");
    $tenant_row = $tenant_check ? $tenant_check->fetch_assoc() : ['count' => 0];
    if ($tenant_row['count'] > 0) {
        $_SESSION['flash_message'] = "Cannot delete this apartment. There are tenants assigned to it!";
        $_SESSION['flash_type'] = 'danger';
    } else {
        $conn->query("DELETE FROM apartments WHERE id=$delete_id");
        $_SESSION['flash_message'] = "Apartment deleted successfully!";
        $_SESSION['flash_type'] = 'success';
    }
    header("Location: apartment.php");
    exit();
}

// Edit info (for status/number/floor/image)
$edit_apartment = null;
if (isset($_GET["edit_id"])) {
    $edit_id = intval($_GET["edit_id"]);
    $result = $conn->query("SELECT * FROM apartments WHERE id=$edit_id");
    if ($result && $result->num_rows > 0) {
        $edit_apartment = $result->fetch_assoc();
    }
}

// List apartments
$apartments = [];
$result = $conn->query("SELECT * FROM apartments ORDER BY id DESC");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $apartments[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Apartments Management | PropertyPro</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
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
            max-width: 1000px;
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
        
        .status-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.35rem 0.75rem;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: capitalize;
        }
        
        .status-vacant {
            background-color: rgba(67, 97, 238, 0.1);
            color: var(--primary);
        }
        
        .status-occupied {
            background-color: rgba(16, 185, 129, 0.1);
            color: var(--success);
        }
        
        .status-maintenance {
            background-color: rgba(245, 158, 11, 0.1);
            color: var(--warning);
        }
        
        .apartment-img {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 8px;
            border: 1px solid var(--light-gray);
            transition: all 0.3s ease;
        }
        
        .apartment-img:hover {
            transform: scale(1.8);
            z-index: 10;
            box-shadow: var(--shadow-lg);
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
        
        .img-preview {
            max-width: 150px;
            max-height: 150px;
            border-radius: 8px;
            border: 1px solid var(--light-gray);
            margin-top: 0.5rem;
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
    </style>
</head>
<body>
<div class="container fade-in">
    <a href="dashboard.php" class="back-button btn btn-outline-primary">
        <i class="fas fa-arrow-left me-2"></i> Back to Dashboard
    </a>
    <h2><i class="fas fa-building me-2"></i>Apartment Management</h2>
    
    <?php if(isset($_SESSION['flash_message'])): ?>
        <div class="flash-message alert alert-<?php echo $_SESSION['flash_type']; ?> alert-dismissible fade show" role="alert">
            <?php echo $_SESSION['flash_message']; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php unset($_SESSION['flash_message']); unset($_SESSION['flash_type']); ?>
    <?php endif; ?>
    
    <!-- Add New Apartment Form -->
    <div class="card mb-4 border-0 shadow-sm">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0"><i class="fas fa-plus-circle me-2"></i>Add New Apartment</h5>
        </div>
        <div class="card-body">
            <form method="POST" enctype="multipart/form-data" id="add-apartment-form">
                <input type="hidden" name="add_apartment" value="1">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="number" class="form-label">Apartment Number</label>
                        <input type="text" id="number" name="number" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label for="floor" class="form-label">Floor</label>
                        <input type="number" id="floor" name="floor" class="form-control" min="1" value="1" required>
                    </div>
                    <div class="col-md-6">
                        <label for="status" class="form-label">Status</label>
                        <select name="status" id="status" class="form-select" required>
                            <option value="vacant">Vacant</option>
                            <option value="occupied">Occupied</option>
                            <option value="maintenance">Maintenance</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label for="apartment_image" class="form-label">Apartment Image</label>
                        <input type="file" id="apartment_image" name="apartment_image" class="form-control" accept="image/*">
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary px-4 pulse">
                            <i class="fas fa-save me-2"></i>Add Apartment
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
    
    <?php if ($edit_apartment): ?>
        <!-- Edit Apartment Form -->
        <div class="card mb-4 border-0 shadow-sm">
            <div class="card-header bg-warning text-dark">
                <h5 class="mb-0"><i class="fas fa-edit me-2"></i>Edit Apartment</h5>
            </div>
            <div class="card-body">
                <form method="post" enctype="multipart/form-data">
                    <input type="hidden" name="apartment_id" value="<?php echo $edit_apartment['id']; ?>">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Number</label>
                            <input type="text" name="apartment_number" class="form-control" 
                                   value="<?php echo htmlspecialchars($edit_apartment['number']); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Floor</label>
                            <input type="number" name="apartment_floor" class="form-control" 
                                   value="<?php echo htmlspecialchars($edit_apartment['floor']); ?>" min="1" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Status</label>
                            <select name="apartment_status" class="form-select">
                                <option value="vacant" <?php if($edit_apartment['status']=="vacant") echo "selected"; ?>>Vacant</option>
                                <option value="occupied" <?php if($edit_apartment['status']=="occupied") echo "selected"; ?>>Occupied</option>
                                <option value="maintenance" <?php if($edit_apartment['status']=="maintenance") echo "selected"; ?>>Maintenance</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="apartment_image" class="form-label">Apartment Image</label>
                            <input type="file" id="apartment_image" name="apartment_image" class="form-control" accept="image/*">
                            <?php if (!empty($edit_apartment['image_path'])): ?>
                                <div class="mt-2">
                                    <p class="small text-muted mb-1">Current Image:</p>
                                    <img src="../<?php echo htmlspecialchars($edit_apartment['image_path']); ?>" class="img-preview" alt="Apartment Image">
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="col-12">
                            <button type="submit" name="edit_apartment" class="btn btn-warning me-2 px-4">
                                <i class="fas fa-save me-2"></i>Update
                            </button>
                            <a href="apartment.php" class="btn btn-outline-secondary px-4">
                                <i class="fas fa-times me-2"></i>Cancel
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>
    
    <!-- Apartments List -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="fas fa-list me-2"></i>All Apartments</h5>
            <div>
                <span class="badge bg-light text-primary me-2">
                    Vacant: <?php echo count(array_filter($apartments, fn($a) => $a['status'] === 'vacant')); ?>
                </span>
                <span class="badge bg-light text-success me-2">
                    Occupied: <?php echo count(array_filter($apartments, fn($a) => $a['status'] === 'occupied')); ?>
                </span>
                <span class="badge bg-light text-warning">
                    Maintenance: <?php echo count(array_filter($apartments, fn($a) => $a['status'] === 'maintenance')); ?>
                </span>
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Details</th>
                            <th>Status</th>
                            <th>Image</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($apartments as $apartment): ?>
                        <tr class="fade-in">
                            <td class="fw-semibold">#<?php echo $apartment['id']; ?></td>
                            <td>
                                <div class="fw-semibold"><?php echo htmlspecialchars($apartment['number']); ?></div>
                                <small class="text-muted">Floor <?php echo htmlspecialchars($apartment['floor']); ?></small>
                            </td>
                            <td>
                                <span class="status-badge status-<?php echo $apartment['status']; ?>">
                                    <i class="fas fa-circle"></i>
                                    <?php echo htmlspecialchars(ucfirst($apartment['status'])); ?>
                                </span>
                            </td>
                            <td>
                                <?php if (!empty($apartment['image_path'])): ?>
                                    <img src="../<?php echo htmlspecialchars($apartment['image_path']); ?>" 
                                         class="apartment-img" 
                                         alt="Apartment <?php echo htmlspecialchars($apartment['number']); ?>">
                                <?php else: ?>
                                    <span class="text-muted">No image</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <a href="apartment.php?edit_id=<?php echo $apartment['id']; ?>" 
                                   class="action-btn btn btn-sm btn-info me-1"
                                   title="Edit">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <a href="apartment.php?delete_id=<?php echo $apartment['id']; ?>" 
                                   class="action-btn btn btn-sm btn-danger" 
                                   onclick="return confirm('Are you sure you want to delete this apartment?');"
                                   title="Delete">
                                    <i class="fas fa-trash-alt"></i>
                                </a>
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
<a href="#add-apartment-form" class="fab d-lg-none">
    <i class="fas fa-plus"></i>
</a>

<!-- Bootstrap JS -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.6/dist/umd/popper.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.min.js"></script>
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
        
        // Image preview for file uploads
        $('input[type="file"]').change(function(e) {
            if (this.files && this.files[0]) {
                var reader = new FileReader();
                
                reader.onload = function(e) {
                    var previewContainer = $(e.target).closest('.col-md-6').find('.img-preview-container');
                    if (previewContainer.length === 0) {
                        previewContainer = $('<div class="img-preview-container mt-2"></div>');
                        $(e.target).closest('.col-md-6').append(previewContainer);
                    }
                    
                    var preview = previewContainer.find('.img-preview');
                    if (preview.length === 0) {
                        preview = $('<img class="img-preview">');
                        previewContainer.append('<p class="small text-muted mb-1">New Image Preview:</p>');
                        previewContainer.append(preview);
                    }
                    
                    preview.attr('src', e.target.result);
                }
                
                reader.readAsDataURL(this.files[0]);
            }
        });
        
        // Smooth scroll for FAB
        $('.fab').on('click', function(e) {
            e.preventDefault();
            $('html, body').animate({
                scrollTop: $('#add-apartment-form').offset().top - 20
            }, 500);
        });
    });
</script>
</body>
</html>