<?php
// Start session and restrict access to logged-in tenants only
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'tenant') {
    header("Location: ../public/login.php");
    exit();
}

require_once "../config/db.php";
$tenant_id = $_SESSION['user_id'];

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["request_text"])) {
    $request_text = $conn->real_escape_string($_POST["request_text"]);
    $sql = "INSERT INTO maintenance_requests (tenant_id, request_text) VALUES ($tenant_id, '$request_text')";
    if ($conn->query($sql)) {
        $_SESSION['flash_message'] = "Maintenance request submitted successfully!";
        $_SESSION['flash_type'] = "success";
        header("Location: maintenance.php");
        exit();
    } else {
        $_SESSION['flash_message'] = "Error submitting request: " . $conn->error;
        $_SESSION['flash_type'] = "danger";
    }
}

// Fetch all maintenance requests by this tenant
$requests = [];
$res = $conn->query("SELECT * FROM maintenance_requests WHERE tenant_id = $tenant_id ORDER BY request_date DESC");
while ($row = $res->fetch_assoc()) {
    $requests[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Maintenance Requests</title>

    <!-- Bootstrap & Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">

    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- Internal Styling -->
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
            background-color: var(--light);
            color: var(--dark);
            line-height: 1.6;
        }

        /* Top Navigation Bar */
        .topbar {
            background-color: white;
            box-shadow: var(--shadow-sm);
            padding: 15px 30px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .topbar h1 {
            font-size: 1.5rem;
            color: var(--primary);
            margin: 0;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        /* Back Button Style */
        .back-btn {
            color: var(--primary);
            text-decoration: none;
            font-size: 16px;
            display: flex;
            align-items: center;
            gap: 6px;
            transition: all 0.3s;
            font-weight: 500;
        }

        .back-btn:hover {
            color: var(--secondary);
            transform: translateX(-3px);
        }

        /* Main Container */
        .container {
            padding: 30px;
            max-width: 1200px;
            margin: 0 auto;
            animation: fadeInUp 0.6s ease;
        }

        @keyframes fadeInUp {
            from {
                transform: translateY(20px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        /* Request Form Section */
        .form-area {
            background: white;
            padding: 25px;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            margin-bottom: 30px;
            transition: all 0.3s ease;
        }

        .form-area:hover {
            box-shadow: var(--shadow-md);
            transform: translateY(-2px);
        }

        .form-area h4 {
            color: var(--primary);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        textarea {
            resize: none;
            min-height: 120px;
            border-radius: var(--border-radius) !important;
            border: 1px solid var(--light-gray);
            transition: all 0.3s;
        }

        textarea:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 0.25rem rgba(67, 97, 238, 0.15);
        }

        .btn-submit {
            background-color: var(--primary);
            color: white;
            transition: all 0.3s ease;
            border-radius: var(--border-radius);
            padding: 10px 20px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .btn-submit:hover {
            background-color: var(--secondary);
            transform: translateY(-2px);
            box-shadow: var(--shadow-sm);
        }

        /* Status Badges */
        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-weight: 500;
            font-size: 0.8rem;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .status-badge i {
            font-size: 0.6rem;
        }

        .status-pending {
            background-color: rgba(245, 158, 11, 0.15);
            color: var(--warning);
        }

        .status-inprogress {
            background-color: rgba(59, 130, 246, 0.15);
            color: var(--info);
        }

        .status-resolved {
            background-color: rgba(16, 185, 129, 0.15);
            color: var(--success);
        }

        /* Table Styling */
        .table-container {
            background-color: white;
            border-radius: var(--border-radius);
            overflow: hidden;
            box-shadow: var(--shadow);
            transition: all 0.3s ease;
        }

        .table-container:hover {
            box-shadow: var(--shadow-md);
        }

        .table {
            margin-bottom: 0;
        }

        .table thead th {
            background-color: var(--primary);
            color: white;
            font-weight: 500;
            padding: 15px;
            border: none;
        }

        .table tbody tr {
            transition: all 0.2s;
        }

        .table tbody tr:hover {
            background-color: var(--primary-light);
        }

        .table tbody td {
            vertical-align: middle;
            padding: 15px;
            border-color: var(--light-gray);
        }

        /* Flash Message */
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

        /* Empty State */
        .empty-state {
            padding: 40px;
            text-align: center;
            color: var(--gray);
        }

        .empty-state i {
            font-size: 3rem;
            color: var(--light-gray);
            margin-bottom: 15px;
        }

        /* Responsive Adjustments */
        @media (max-width: 768px) {
            .topbar {
                padding: 15px;
            }
            
            .topbar h1 {
                font-size: 1.2rem;
            }
            
            .container {
                padding: 20px;
            }
            
            .form-area {
                padding: 20px;
            }
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
            animation: pulse 2s infinite;
        }

        .fab:hover {
            transform: translateY(-3px) scale(1.05);
            background-color: var(--secondary);
            box-shadow: var(--shadow-lg);
            color: white;
        }

        @keyframes pulse {
            0% {
                box-shadow: 0 0 0 0 rgba(67, 97, 238, 0.4);
            }
            70% {
                box-shadow: 0 0 0 12px rgba(67, 97, 238, 0);
            }
            100% {
                box-shadow: 0 0 0 0 rgba(67, 97, 238, 0);
            }
        }

        /* Row animations */
        .fade-in-row {
            animation: fadeInRow 0.5s ease forwards;
        }

        @keyframes fadeInRow {
            from {
                opacity: 0;
                transform: translateX(20px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }
    </style>
</head>
<body>

<!-- Top Navigation Bar -->
<div class="topbar">
    <a href="dashboard.php" class="back-btn">
        <i class="bi bi-arrow-left-circle-fill"></i> Back
    </a>
    <h1><i class="bi bi-tools"></i> Maintenance Requests</h1>
</div>

<!-- Main Container -->
<div class="container">

    <?php if(isset($_SESSION['flash_message'])): ?>
        <div class="flash-message alert alert-<?php echo $_SESSION['flash_type']; ?> alert-dismissible fade show" role="alert">
            <i class="bi <?php echo $_SESSION['flash_type'] === 'success' ? 'bi-check-circle-fill' : 'bi-exclamation-triangle-fill'; ?> me-2"></i>
            <?php echo $_SESSION['flash_message']; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php unset($_SESSION['flash_message']); unset($_SESSION['flash_type']); ?>
    <?php endif; ?>

    <!-- Maintenance Request Form -->
    <div class="form-area">
        <h4><i class="bi bi-tools"></i> Submit Maintenance Request</h4>
        <form method="post" class="mt-3">
            <div class="mb-3">
                <textarea name="request_text" class="form-control" rows="4" placeholder="Describe your issue in detail..." required></textarea>
            </div>
            <button type="submit" class="btn btn-submit">
                <i class="bi bi-send-fill"></i> Submit Request
            </button>
        </form>
    </div>

    <!-- Request History Table -->
    <div class="mb-4">
        <h5 class="mb-3"><i class="bi bi-list-check"></i> My Request History</h5>
        <div class="table-container">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Description</th>
                        <th>Status</th>
                        <th>Date Submitted</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($requests)): ?>
                        <tr>
                            <td colspan="3" class="empty-state">
                                <i class="bi bi-inbox"></i>
                                <h5>No maintenance requests yet</h5>
                                <p>Submit your first request using the form above</p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach($requests as $index => $r): ?>
                            <tr class="fade-in-row" style="animation-delay: <?php echo $index * 0.05; ?>s">
                                <td><?= htmlspecialchars($r['request_text']) ?></td>
                                <td>
                                    <?php
                                        $status = strtolower($r['status']);
                                        $badgeClass = 'status-pending';
                                        $icon = 'bi-hourglass-top';
                                        if ($status === 'in progress') {
                                            $badgeClass = 'status-inprogress';
                                            $icon = 'bi-arrow-repeat';
                                        } elseif ($status === 'resolved' || $status === 'completed') {
                                            $badgeClass = 'status-resolved';
                                            $icon = 'bi-check-circle';
                                        }
                                    ?>
                                    <span class="status-badge <?= $badgeClass ?>">
                                        <i class="bi <?= $icon ?>"></i>
                                        <?= ucfirst($r['status']) ?>
                                    </span>
                                </td>
                                <td><?= date('M d, Y h:i A', strtotime($r['request_date'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Floating action button for mobile -->
<a href="#" class="fab d-lg-none" onclick="document.querySelector('textarea').focus(); return false;">
    <i class="bi bi-plus-lg"></i>
</a>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
    // Add animation to table rows
    document.addEventListener('DOMContentLoaded', function() {
        // Close flash message after 3 seconds
        const flashMessage = document.querySelector('.flash-message');
        if (flashMessage) {
            setTimeout(() => {
                flashMessage.style.display = 'none';
            }, 3000);
        }
        
        // Add focus effect to textarea when clicked
        const textarea = document.querySelector('textarea');
        if (textarea) {
            textarea.addEventListener('focus', function() {
                this.parentElement.classList.add('focus');
            });
            textarea.addEventListener('blur', function() {
                this.parentElement.classList.remove('focus');
            });
        }
    });
</script>
</body>
</html>