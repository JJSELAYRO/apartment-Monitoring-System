<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../public/login.php");
    exit();
}
require_once "../config/db.php";

// Handle status update
if (isset($_POST['update_status'])) {
    $id = intval($_POST['request_id']);
    $status = $conn->real_escape_string($_POST['status']);
    $conn->query("UPDATE maintenance_requests SET status='$status' WHERE id=$id");
}

// Handle delete request (only if status is Completed)
if (isset($_GET['delete_id'])) {
    $delete_id = intval($_GET['delete_id']);
    // Only allow delete if request is completed
    $check = $conn->query("SELECT status FROM maintenance_requests WHERE id=$delete_id");
    if ($check && $row = $check->fetch_assoc()) {
        if ($row['status'] === 'Completed') {
            $conn->query("DELETE FROM maintenance_requests WHERE id=$delete_id");
            $_SESSION['flash_message'] = "Request deleted successfully!";
            $_SESSION['flash_type'] = "success";
        } else {
            $_SESSION['flash_message'] = "Only completed requests can be deleted!";
            $_SESSION['flash_type'] = "danger";
        }
    }
    header("Location: maintenance.php");
    exit();
}

$requests = [];
$res = $conn->query("SELECT maintenance_requests.*, tenants.name AS tenant_name 
                     FROM maintenance_requests 
                     JOIN tenants ON maintenance_requests.tenant_id = tenants.id
                     ORDER BY maintenance_requests.request_date DESC");
while ($row = $res->fetch_assoc()) $requests[] = $row;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Maintenance Requests</title>
    
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
        
        .status-pending {
            color: var(--warning);
            font-weight: 500;
        }
        
        .status-in-progress {
            color: var(--info);
            font-weight: 500;
        }
        
        .status-completed {
            color: var(--success);
            font-weight: 500;
        }
        
        .btn-sm {
            padding: 5px 10px;
            font-size: 0.8rem;
            border-radius: 8px;
        }
        
        .form-select {
            border-radius: 8px;
            border: 1px solid var(--light-gray);
            transition: all 0.3s ease;
        }
        
        .form-select:focus {
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
    </style>
</head>
<body>
<div class="container fade-in">
    <a href="dashboard.php" class="back-button btn btn-outline-primary">
        <i class="fas fa-arrow-left me-2"></i> Back to Dashboard
    </a>
    <h2><i class="fas fa-tools me-2"></i>Maintenance Requests</h2>
    
    <?php if(isset($_SESSION['flash_message'])): ?>
        <div class="flash-message alert alert-<?php echo $_SESSION['flash_type']; ?> alert-dismissible fade show" role="alert">
            <?php echo $_SESSION['flash_message']; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php unset($_SESSION['flash_message']); unset($_SESSION['flash_type']); ?>
    <?php endif; ?>
    
    <div class="table-responsive">
        <table class="table table-hover">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Tenant</th>
                    <th>Description</th>
                    <th>Status</th>
                    <th>Date</th>
                    <th>Update Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($requests as $r): ?>
                <tr>
                    <td><?php echo $r['id']; ?></td>
                    <td><?php echo htmlspecialchars($r['tenant_name']); ?></td>
                    <td><?php echo htmlspecialchars($r['request_text']); ?></td>
                    <td class="status-<?php echo strtolower(str_replace(' ', '-', $r['status'])); ?>">
                        <?php echo htmlspecialchars(ucfirst($r['status'])); ?>
                    </td>
                    <td><?php echo date('M j, Y', strtotime($r['request_date'])); ?></td>
                    <td>
                        <form method="post" style="margin:0;">
                            <input type="hidden" name="request_id" value="<?php echo $r['id']; ?>">
                            <select name="status" class="form-select">
                                <option value="Pending" <?php if($r['status']=="Pending") echo "selected"; ?>>Pending</option>
                                <option value="In Progress" <?php if($r['status']=="In Progress") echo "selected"; ?>>In Progress</option>
                                <option value="Completed" <?php if($r['status']=="Completed") echo "selected"; ?>>Completed</option>
                            </select>
                            <button type="submit" name="update_status" class="btn btn-primary btn-sm mt-2 pulse">
                                <i class="fas fa-sync-alt me-1"></i> Update
                            </button>
                        </form>
                    </td>
                    <td>
                        <?php if ($r['status'] == "Completed"): ?>
                            <a href="maintenance.php?delete_id=<?php echo $r['id']; ?>"
                               class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to delete this completed request?');">
                                <i class="fas fa-trash-alt me-1"></i> Delete
                            </a>
                        <?php else: ?>
                            <span class="text-muted">Not completed</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

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
    });
</script>
</body>
</html>