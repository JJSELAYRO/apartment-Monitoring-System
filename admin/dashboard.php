<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../public/login.php");
    exit();
}

include '../config/db.php';

// Handle delete actions
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // DELETE maintenance request by ID (admin action)
    if (isset($_GET['delete_maintenance_id'])) {
        $delete_id = intval($_GET['delete_maintenance_id']);
        $conn->query("DELETE FROM maintenance_requests WHERE id=$delete_id");
        $_SESSION['success'] = "Maintenance request deleted successfully";
        header("Location: dashboard.php");
        exit();
    }
    
    // DELETE tenant by ID (admin action)
    if (isset($_GET['delete_tenant_id'])) {
        $delete_id = intval($_GET['delete_tenant_id']);
        // Use transactions for data integrity
        $conn->begin_transaction();
        try {
            $conn->query("DELETE FROM payments WHERE tenant_id=$delete_id");
            $conn->query("DELETE FROM maintenance_requests WHERE tenant_id=$delete_id");
            $conn->query("DELETE FROM tenants WHERE id=$delete_id");
            $conn->commit();
            $_SESSION['success'] = "Tenant and all related data deleted successfully";
            header("Location: dashboard.php");
            exit();
        } catch (Exception $e) {
            $conn->rollback();
            // Log error and show message
            error_log("Delete tenant failed: " . $e->getMessage());
            $_SESSION['error'] = "Failed to delete tenant. Please try again.";
            header("Location: dashboard.php");
            exit();
        }
    }
}

// Function to fetch dashboard data with consistent status checking
function fetchDashboardData($conn) {
    $data = [];
    
    // Fetch tenants
    $data['tenants'] = $conn->query("SELECT * FROM tenants ORDER BY created_at DESC LIMIT 5")->fetch_all(MYSQLI_ASSOC);
    
    // Fetch apartments
    $data['apartments'] = $conn->query("SELECT * FROM apartments")->fetch_all(MYSQLI_ASSOC);
    
    // Fetch maintenance requests with tenant names
    $data['maintenance_requests'] = $conn->query(
        "SELECT mr.*, t.name as tenant_name, a.number as apartment_number
        FROM maintenance_requests mr
        LEFT JOIN tenants t ON mr.tenant_id = t.id
        LEFT JOIN apartments a ON mr.apartment_id = a.id
        ORDER BY request_date DESC LIMIT 5"
    )->fetch_all(MYSQLI_ASSOC);
    
 // Fetch payments with tenant names
$data['payments'] = $conn->query(
    "SELECT p.*, t.name as tenant_name
 FROM payments p
 LEFT JOIN tenants t ON p.tenant_id = t.id
 WHERE p.status = 'completed'
 ORDER BY p.date DESC LIMIT 5"
)->fetch_all(MYSQLI_ASSOC);
    
    return $data;
}

// Get all dashboard data
$dashboardData = fetchDashboardData($conn);

// Extract data for easier access
$tenants = $dashboardData['tenants'];
$apartments = $dashboardData['apartments'];
$maintenance_requests = $dashboardData['maintenance_requests'];
$payments = $dashboardData['payments'];

// Counts for summary cards
$tenant_count = $conn->query("SELECT COUNT(*) as count FROM tenants")->fetch_assoc()['count'];
$apartment_count = $conn->query("SELECT COUNT(*) as count FROM apartments")->fetch_assoc()['count'];

// Count active maintenance requests (pending or in progress)
$active_maintenance_result = $conn->query(
    "SELECT COUNT(*) as count FROM maintenance_requests 
     WHERE status IN ('pending', 'in_progress', 'inprogress')"
)->fetch_assoc();
$active_maintenance = $active_maintenance_result['count'];

// Maintenance status counts
$maintenance_status = [
    'pending' => $conn->query("SELECT COUNT(*) as count FROM maintenance_requests WHERE status = 'pending'")->fetch_assoc()['count'],
    'in_progress' => $conn->query("SELECT COUNT(*) as count FROM maintenance_requests WHERE status IN ('in_progress', 'inprogress')")->fetch_assoc()['count'],
    'completed' => $conn->query("SELECT COUNT(*) as count FROM maintenance_requests WHERE status = 'completed'")->fetch_assoc()['count']
];

// Revenue calculation
$total_payments_result = $conn->query(
    "SELECT SUM(amount) as total FROM payments WHERE status IN ('completed', 'paid')"
)->fetch_assoc();
$total_payments = $total_payments_result['total'] ?? 0;

$completed_payments_result = $conn->query(
    "SELECT COUNT(*) as count FROM payments WHERE status IN ('completed', 'paid')"
)->fetch_assoc();
$completed_payments = $completed_payments_result['count'];

// Monthly payments data
$monthly_payments = array_fill(1, 12, 0);
$monthly_result = $conn->query(
    "SELECT MONTH(date) as month, SUM(amount) as total 
     FROM payments 
     WHERE status IN ('completed', 'paid') AND YEAR(date) = YEAR(CURDATE())
     GROUP BY MONTH(date)"
);

while ($row = $monthly_result->fetch_assoc()) {
    $month = intval($row['month']);
    if ($month >= 1 && $month <= 12) {
        $monthly_payments[$month] = floatval($row['total']);
    }
}

// Prepare chart data (0-based array)
$monthly_payments_chart = array_values($monthly_payments);

// Apartment status counts
$apartment_status_counts = [
    'occupied' => $conn->query("SELECT COUNT(*) as count FROM apartments WHERE status = 'occupied'")->fetch_assoc()['count'],
    'vacant' => $conn->query("SELECT COUNT(*) as count FROM apartments WHERE status = 'vacant'")->fetch_assoc()['count'],
    'maintenance' => $conn->query("SELECT COUNT(*) as count FROM apartments WHERE status = 'maintenance'")->fetch_assoc()['count']
];

// Prepare recent activity data
$recent_activities = [];

// Add recent tenants
foreach ($tenants as $tenant) {
    $recent_activities[] = [
        'type' => 'tenant',
        'title' => 'New Tenant Added',
        'description' => $tenant['name'] . ' joined',
        'time' => date('M j, g:i a', strtotime($tenant['created_at'])),
        'id' => $tenant['id']
    ];
}

// Add recent payments
foreach ($payments as $payment) {
    $recent_activities[] = [
        'type' => 'payment',
        'title' => 'Payment Received',
        'description' => '₱' . number_format($payment['amount'], 2) . ' from ' . $payment['tenant_name'] . ' (Apt ' . $payment['apartment_number'] . ')',
        'time' => date('M j, g:i a', strtotime($payment['date'])),
        'id' => $payment['id']
    ];
}

// Add recent maintenance requests
foreach ($maintenance_requests as $request) {
    $recent_activities[] = [
        'type' => 'maintenance',
        'title' => 'Maintenance Request',
        'description' => 'From ' . $request['tenant_name'] . ' (Apt ' . $request['apartment_number'] . '): ' . substr($request['request_text'], 0, 30) . (strlen($request['request_text']) > 30 ? '...' : ''),
        'time' => date('M j, g:i a', strtotime($request['request_date'])),
        'id' => $request['id']
    ];
}

// Sort activities by time (newest first)
usort($recent_activities, function($a, $b) {
    return strtotime($b['time']) - strtotime($a['time']);
});

// Get top 5 activities
$recent_activities = array_slice($recent_activities, 0, 5);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | PropertyPro</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --primary-color: #4361ee;
            --secondary-color: #3f37c9;
            --accent-color: #4895ef;
            --success-color: #4cc9f0;
            --danger-color: #f72585;
            --warning-color: #f8961e;
            --info-color: #43aa8b;
            --light-color: #f8f9fa;
            --dark-color: #212529;
            --sidebar-width: 280px;
            --transition-speed: 0.3s;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f7fa;
            overflow-x: hidden;
        }
        
        /* Sidebar */
        .sidebar {
            width: var(--sidebar-width);
            height: 100vh;
            position: fixed;
            left: 0;
            top: 0;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 1.5rem 0;
            transition: all var(--transition-speed) ease;
            z-index: 1000;
            box-shadow: 4px 0 15px rgba(0, 0, 0, 0.1);
        }
        
        .sidebar.active {
            left: calc(-1 * var(--sidebar-width));
        }
        
        .sidebar-brand {
            color: white;
            font-size: 1.5rem;
            font-weight: 700;
            padding: 0 1.5rem;
            margin-bottom: 2rem;
            text-decoration: none;
        }
        
        .sidebar-brand i {
            font-size: 1.8rem;
        }
        
        .sidebar-nav {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .nav-item {
            margin-bottom: 0.5rem;
        }
        
        .nav-link {
            display: flex;
            align-items: center;
            padding: 0.75rem 1.5rem;
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            transition: all 0.2s ease;
            border-left: 4px solid transparent;
        }
        
        .nav-link:hover, .nav-link.active {
            background: rgba(255, 255, 255, 0.1);
            color: white;
            border-left-color: white;
        }
        
        .nav-link i {
            font-size: 1.1rem;
            margin-right: 0.75rem;
            width: 20px;
            text-align: center;
        }
        
        /* Main Content */
        .main-content {
            margin-left: var(--sidebar-width);
            padding: 2rem;
            transition: all var(--transition-speed) ease;
            min-height: 100vh;
        }
        
        .main-content.full-width {
            margin-left: 0;
        }
        
        /* Navbar */
        .navbar-custom {
            background-color: white;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            padding: 1rem 2rem;
            position: sticky;
            top: 0;
            z-index: 100;
        }
        
        .user-profile {
            display: flex;
            align-items: center;
        }
        
        .user-profile img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            margin-right: 0.75rem;
            object-fit: cover;
            border: 2px solid var(--primary-color);
        }
        
        /* Cards */
        .modern-card {
            background: white;
            border-radius: 12px;
            border: none;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.03);
            transition: all 0.3s ease;
            overflow: hidden;
            margin-bottom: 1.5rem;
        }
        
        .modern-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.08);
        }
        
        .summary-card {
            position: relative;
            overflow: hidden;
        }
        
        .summary-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 5px;
            height: 100%;
            background: var(--primary-color);
        }
        
        .card-tenant::before { background: var(--primary-color); }
        .card-apartment::before { background: var(--success-color); }
        .card-maintenance::before { background: var(--warning-color); }
        .card-payment::before { background: var(--info-color); }
        
        .card-body {
            padding: 1.5rem;
        }
        
        .chart-container {
            padding: 1.5rem;
        }
        
        .chart-title {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }
        
        /* Animations */
        .fade-in {
            animation: fadeIn 0.5s ease-in-out;
        }
        
        .slide-in {
            animation: slideIn 0.5s ease-out;
        }
        
        .pulse {
            animation: pulse 1.5s infinite;
        }
        
        .pulse-update {
            animation: pulseUpdate 1s ease;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        @keyframes slideIn {
            from { transform: translateY(20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        
        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(67, 97, 238, 0.4); }
            70% { box-shadow: 0 0 0 10px rgba(67, 97, 238, 0); }
            100% { box-shadow: 0 0 0 0 rgba(67, 97, 238, 0); }
        }
        
        @keyframes pulseUpdate {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }
        
        /* Recent Activity */
        .recent-activity {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .recent-activity li {
            padding: 1rem 0;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            display: flex;
            align-items: flex-start;
        }
        
        .recent-activity li:last-child {
            border-bottom: none;
        }
        
        .activity-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1rem;
            background: rgba(67, 97, 238, 0.1);
            flex-shrink: 0;
        }
        
        .activity-icon i {
            font-size: 1.2rem;
        }
        
        .activity-details {
            flex-grow: 1;
        }
        
        .activity-time {
            font-size: 0.8rem;
            color: #6c757d;
            margin-top: 0.25rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .delete-btn {
            color: #dc3545;
            opacity: 0.5;
            transition: all 0.2s ease;
        }
        
        .delete-btn:hover {
            opacity: 1;
            transform: scale(1.1);
        }
        
        /* Toast Notifications */
        .toast-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1100;
        }
        
        .toast {
            border: none;
            border-radius: 8px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
            overflow: hidden;
        }
        
        /* Responsive */
        @media (max-width: 992px) {
            .sidebar {
                left: calc(-1 * var(--sidebar-width));
            }
            
            .sidebar.active {
                left: 0;
            }
            
            .main-content {
                margin-left: 0;
            }
        }
        
        /* Custom Scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }
        
        ::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }
        
        ::-webkit-scrollbar-thumb {
            background: var(--primary-color);
            border-radius: 10px;
        }
        
        ::-webkit-scrollbar-thumb:hover {
            background: var(--secondary-color);
        }
        
        /* Gradient Background for Summary Cards */
        .summary-card .card-body {
            position: relative;
            z-index: 1;
        }
        
        .summary-card::after {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 100px;
            height: 100px;
            background: radial-gradient(circle, rgba(255,255,255,0.2) 0%, rgba(255,255,255,0) 70%);
            z-index: 0;
        }
        
        /* Floating Animation for Summary Cards */
        @keyframes float {
            0% { transform: translateY(0px); }
            50% { transform: translateY(-10px); }
            100% { transform: translateY(0px); }
        }
        
        .summary-card:hover {
            animation: float 3s ease-in-out infinite;
        }
        
        /* Ripple Effect for Buttons */
        .btn-ripple {
            position: relative;
            overflow: hidden;
        }
        
        .btn-ripple:after {
            content: "";
            display: block;
            position: absolute;
            width: 100%;
            height: 100%;
            top: 0;
            left: 0;
            pointer-events: none;
            background-image: radial-gradient(circle, #fff 10%, transparent 10.01%);
            background-repeat: no-repeat;
            background-position: 50%;
            transform: scale(10, 10);
            opacity: 0;
            transition: transform .5s, opacity 1s;
        }
        
        .btn-ripple:active:after {
            transform: scale(0, 0);
            opacity: 0.3;
            transition: 0s;
        }
        
    </style>
</head>
<body>
    <!-- Toast Notifications -->
    <div class="toast-container">
        <?php if (isset($_SESSION['success'])): ?>
            <div class="toast show" role="alert" aria-live="assertive" aria-atomic="true">
                <div class="toast-header bg-success text-white">
                    <strong class="me-auto">Success</strong>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast" aria-label="Close"></button>
                </div>
                <div class="toast-body bg-light">
                    <i class="fas fa-check-circle me-2 text-success"></i>
                    <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                </div>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="toast show" role="alert" aria-live="assertive" aria-atomic="true">
                <div class="toast-header bg-danger text-white">
                    <strong class="me-auto">Error</strong>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast" aria-label="Close"></button>
                </div>
                <div class="toast-body bg-light">
                    <i class="fas fa-exclamation-circle me-2 text-danger"></i>
                    <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <div class="d-flex">
        <!-- Sidebar -->
        <div class="sidebar">
            <a href="dashboard.php" class="sidebar-brand d-flex align-items-center">
                <i class="bi bi-building me-2"></i>
                <span>PropertyPro</span>
            </a>
    
            
            <ul class="sidebar-nav">
                <li class="nav-item">
                    <a href="dashboard.php" class="nav-link active">
                        <i class="bi bi-speedometer2"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="tenants.php" class="nav-link">
                        <i class="bi bi-people"></i>
                        <span>Tenants</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="apartment.php" class="nav-link">
                        <i class="bi bi-building"></i>
                        <span>Apartments</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="payment.php" class="nav-link">
                        <i class="bi bi-credit-card"></i>
                        <span>Payments</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="maintenance.php" class="nav-link">
                        <i class="bi bi-tools"></i>
                        <span>Maintenance</span>
                    </a>
                </li>
                <li class="nav-item mt-4">
                    <a href="../public/website.php" class="nav-link">
                        <i class="bi bi-box-arrow-right"></i>
                        <span>Logout</span>
                    </a>
                </li>
            </ul>
        </div>
        
        <!-- Main Content -->
        <div class="main-content flex-grow-1">
            <!-- Top Navbar -->
            <nav class="navbar navbar-custom navbar-expand-lg">
                <div class="container-fluid">
                    <button class="btn btn-sm d-lg-none" type="button" id="sidebarToggle">
                        <i class="bi bi-list"></i>
                    </button>
                    <span class="navbar-brand mb-0 h1 fw-bold text-primary">Dashboard Overview</span>
                    <div class="user-profile">
                        <img src="https://ui-avatars.com/api/?name=Admin&background=random" alt="Admin">
                        <span class="fw-medium">Admin</span>
                        <span id="connection-status" class="ms-2" title="Connection status">
                            <i class="bi bi-circle-fill text-success"></i>
                            <small class="ms-1">Live</small>
                        </span>
                    </div>
                </div>
            </nav>
            
            <div class="container-fluid py-3">
                <!-- Welcome Message -->
                <div class="welcome-message mb-4 slide-in">
                    <h4 class="fw-bold text-dark mb-2">Welcome back, Admin 👋</h4>
                    <p class="text-muted">Here's what's happening with your property today.</p>
                </div>
                
                <!-- Summary Cards -->
                <div class="row g-4 mb-4" id="summary-cards">
                    <!-- Tenant Card -->
                    <div class="col-md-6 col-lg-3">
                        <div class="modern-card summary-card card-tenant fade-in">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div class="bg-primary bg-opacity-10 rounded-lg p-3 me-3">
                                        <i class="bi bi-people fs-4 text-primary"></i>
                                    </div>
                                    <div>
                                        <h6 class="card-subtitle mb-1 text-muted">Tenants</h6>
                                        <h3 class="card-title mb-0 text-dark" id="tenant-count"><?php echo $tenant_count; ?></h3>
                                        <small class="text-muted" id="occupancy-rate">
                                            <?php echo $apartment_count > 0 ? round(($tenant_count / $apartment_count) * 100) : 0; ?>% occupancy
                                        </small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Apartment Card -->
                    <div class="col-md-6 col-lg-3">
                        <div class="modern-card summary-card card-apartment fade-in">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div class="bg-success bg-opacity-10 rounded-lg p-3 me-3">
                                        <i class="bi bi-building fs-4 text-success"></i>
                                    </div>
                                    <div>
                                        <h6 class="card-subtitle mb-1 text-muted">Apartments</h6>
                                        <h3 class="card-title mb-0 text-dark" id="apartment-count"><?php echo $apartment_count; ?></h3>
                                        <small class="text-muted" id="vacant-count">
                                            <?php echo $apartment_status_counts['vacant']; ?> available
                                        </small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Maintenance Card -->
                    <div class="col-md-6 col-lg-3">
                        <div class="modern-card summary-card card-maintenance fade-in">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div class="bg-warning bg-opacity-10 rounded-lg p-3 me-3">
                                        <i class="bi bi-tools fs-4 text-warning"></i>
                                    </div>
                                    <div>
                                        <h6 class="card-subtitle mb-1 text-muted">Active Requests</h6>
                                        <h3 class="card-title mb-0 text-dark" id="active-maintenance"><?php echo $active_maintenance; ?></h3>
                                        <small class="text-muted" id="maintenance-stats">
                                            <?php echo $maintenance_status['pending']+$maintenance_status['in_progress']; ?> active, <?php echo $maintenance_status['completed']; ?> completed
                                        </small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Payment Card -->
                    <div class="col-md-6 col-lg-3">
                        <div class="modern-card summary-card card-payment fade-in">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div class="bg-info bg-opacity-10 rounded-lg p-3 me-3">
                                        <i class="bi bi-currency-dollar fs-4 text-info"></i>
                                    </div>
                                    <div>
                                        <h6 class="card-subtitle mb-1 text-muted">Total Revenue</h6>
                                        <h3 class="card-title mb-0 text-dark" id="total-revenue">₱<?php echo number_format($total_payments, 2); ?></h3>
                                        <small class="text-muted" id="payment-count">
                                            <?php echo $completed_payments; ?> payments
                                        </small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Charts Section -->
                <div class="row g-4 mb-4">
                    <!-- Apartment Status Chart -->
                    <div class="col-lg-6">
                        <div class="modern-card chart-container h-100 slide-in">
                            <div class="chart-title">
                                <h5 class="fw-bold">Apartment Status</h5>
                                <div>
                                    <span class="badge bg-light text-dark me-2" id="apartment-last-update">Updated: Just now</span>
                                    <a href="apartment.php" class="btn btn-sm btn-outline-primary btn-ripple">View All</a>
                                </div>
                            </div>
                            <canvas id="apartmentStatusChart" height="250"></canvas>
                            <div class="quick-stats mt-3 d-flex justify-content-around text-center">
                                <div>
                                    <div class="text-success fw-bold"><?php echo $apartment_status_counts['occupied']; ?></div>
                                    <small class="text-muted">Occupied</small>
                                </div>
                                <div>
                                    <div class="text-primary fw-bold"><?php echo $apartment_status_counts['vacant']; ?></div>
                                    <small class="text-muted">Vacant</small>
                                </div>
                                <div>
                                    <div class="text-warning fw-bold"><?php echo $apartment_status_counts['maintenance']; ?></div>
                                    <small class="text-muted">Maintenance</small>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Monthly Revenue Chart -->
                    <div class="col-lg-6">
                        <div class="modern-card chart-container h-100 slide-in">
                            <div class="chart-title">
                                <h5 class="fw-bold">Monthly Revenue</h5>
                                <div>
                                    <span class="badge bg-light text-dark me-2" id="revenue-last-update">Updated: Just now</span>
                                    <a href="payment.php" class="btn btn-sm btn-outline-primary btn-ripple">View All</a>
                                </div>
                            </div>
                            <canvas id="monthlyRevenueChart" height="250"></canvas>
                            <div class="text-center mt-3">
                                <span class="badge bg-success" id="total-revenue-badge">Total: ₱<?php echo number_format($total_payments, 2); ?></span>
                                <span class="badge bg-primary ms-2" id="avg-revenue-badge">Avg: ₱<?php echo number_format($completed_payments > 0 ? $total_payments / $completed_payments : 0, 2); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Maintenance and Activity Section -->
                <div class="row g-4">
                    <!-- Maintenance Status -->
                    <div class="col-lg-6">
                        <div class="modern-card chart-container h-100 slide-in">
                            <div class="chart-title">
                                <h5 class="fw-bold">Maintenance Requests</h5>
                                <div>
                                    <span class="badge bg-light text-dark me-2" id="maintenance-last-update">Updated: Just now</span>
                                    <a href="maintenance.php" class="btn btn-sm btn-outline-primary btn-ripple">View All</a>
                                </div>
                            </div>
                            <canvas id="maintenanceStatusChart" height="250"></canvas>
                            <div class="quick-stats mt-3 d-flex justify-content-around text-center">
                                <div>
                                    <div class="text-warning fw-bold"><?php echo $maintenance_status['pending']; ?></div>
                                    <small class="text-muted">Pending</small>
                                </div>
                                <div>
                                    <div class="text-info fw-bold"><?php echo $maintenance_status['in_progress']; ?></div>
                                    <small class="text-muted">In Progress</small>
                                </div>
                                <div>
                                    <div class="text-success fw-bold"><?php echo $maintenance_status['completed']; ?></div>
                                    <small class="text-muted">Completed</small>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Recent Activity -->
                    <div class="col-lg-6">
                        <div class="modern-card chart-container h-100 slide-in">
                            <div class="chart-title">
                                <h5 class="fw-bold">Recent Activity</h5>
                                <div>
                                    <span class="badge bg-light text-dark me-2" id="activity-last-update">Updated: Just now</span>
                                    <a href="#" class="btn btn-sm btn-outline-primary btn-ripple">View All</a>
                                </div>
                            </div>
                            <div id="recent-activity-container" style="max-height: 400px; overflow-y: auto;">
                                <ul class="recent-activity" id="recent-activity-list">
                                    <?php foreach ($recent_activities as $activity): 
                                        // Fix: Add parentheses for ternary operator associativity
                                        $iconClass = $activity['type'] === 'tenant' ? 'text-primary' : 
                                                    ($activity['type'] === 'payment' ? 'text-success' : 'text-warning');
                                        $icon = $activity['type'] === 'tenant' ? 'bi-people' : 
                                                ($activity['type'] === 'payment' ? 'bi-credit-card' : 'bi-tools');
                                        $deleteLink = $activity['type'] === 'tenant'
                                            ? "<a class='delete-btn' href='?delete_tenant_id={$activity['id']}' title='Delete tenant and all related data' onclick='return confirm(\"Are you sure you want to delete this tenant and all related data?\")'>
                                                    <i class='bi bi-trash'></i>
                                                </a>"
                                            : ($activity['type'] === 'maintenance'
                                                ? "<a class='delete-btn' href='?delete_maintenance_id={$activity['id']}' title='Delete maintenance request' onclick='return confirm(\"Are you sure you want to delete this maintenance request?\")'>
                                                    <i class='bi bi-trash'></i>
                                                </a>"
                                                : ''
                                            );
                                    ?>
                                    <li class="animate__animated animate__fadeIn">
                                        <div class="activity-icon <?php echo $iconClass; ?>">
                                            <i class="bi <?php echo $icon; ?>"></i>
                                        </div>
                                        <div class="activity-details">
                                            <strong><?php echo htmlspecialchars($activity['title']); ?></strong>
                                            <div><?php echo htmlspecialchars($activity['description']); ?></div>
                                            <div class="activity-time">
                                                <?php echo $activity['time']; ?>
                                                <?php echo $deleteLink; ?>
                                            </div>
                                        </div>
                                    </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Initialize charts
        let apartmentChart, revenueChart, maintenanceChart;
        
        // Function to initialize or update charts
        function initCharts() {
            // Apartment Status Chart
            const apartmentCtx = document.getElementById('apartmentStatusChart').getContext('2d');
            apartmentChart = new Chart(apartmentCtx, {
                type: 'doughnut',
                data: {
                    labels: ['Occupied', 'Vacant', 'Maintenance'],
                    datasets: [{
                        data: [
                            <?php echo $apartment_status_counts['occupied']; ?>,
                            <?php echo $apartment_status_counts['vacant']; ?>,
                            <?php echo $apartment_status_counts['maintenance']; ?>
                        ],
                        backgroundColor: [
                            'rgba(16, 185, 129, 0.8)',
                            'rgba(59, 130, 246, 0.8)',
                            'rgba(245, 158, 11, 0.8)'
                        ],
                        borderColor: [
                            'rgba(16, 185, 129, 1)',
                            'rgba(59, 130, 246, 1)',
                            'rgba(245, 158, 11, 1)'
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    cutout: '70%',
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                usePointStyle: true,
                                padding: 20,
                                font: {
                                    family: "'Segoe UI', Tahoma, Geneva, Verdana, sans-serif"
                                }
                            }
                        },
                        tooltip: {
                            backgroundColor: 'rgba(0, 0, 0, 0.8)',
                            titleFont: {
                                size: 14,
                                weight: 'bold'
                            },
                            bodyFont: {
                                size: 12
                            },
                            padding: 12,
                            cornerRadius: 8
                        }
                    },
                    animation: {
                        animateScale: true,
                        animateRotate: true
                    }
                }
            });

            // Monthly Revenue Chart
            const revenueCtx = document.getElementById('monthlyRevenueChart').getContext('2d');
            revenueChart = new Chart(revenueCtx, {
                type: 'bar',
                data: {
                    labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
                    datasets: [{
                        label: 'Revenue (₱)',
                        data: <?php echo json_encode($monthly_payments_chart); ?>,
                        backgroundColor: 'rgba(59, 130, 246, 0.7)',
                        borderColor: 'rgba(59, 130, 246, 1)',
                        borderWidth: 1,
                        borderRadius: 6,
                        hoverBackgroundColor: 'rgba(59, 130, 246, 0.9)'
                    }]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: {
                                drawBorder: false,
                                color: 'rgba(0, 0, 0, 0.05)'
                            },
                            ticks: {
                                callback: function(value) {
                                    return '₱' + value.toLocaleString();
                                },
                                font: {
                                    family: "'Segoe UI', Tahoma, Geneva, Verdana, sans-serif"
                                }
                            }
                        },
                        x: {
                            grid: {
                                display: false
                            },
                            ticks: {
                                font: {
                                    family: "'Segoe UI', Tahoma, Geneva, Verdana, sans-serif"
                                }
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            backgroundColor: 'rgba(0, 0, 0, 0.8)',
                            titleFont: {
                                size: 14,
                                weight: 'bold'
                            },
                            bodyFont: {
                                size: 12
                            },
                            padding: 12,
                            cornerRadius: 8,
                            callbacks: {
                                label: function(context) {
                                    return '₱' + context.raw.toLocaleString();
                                }
                            }
                        }
                    },
                    animation: {
                        duration: 1000,
                        easing: 'easeOutQuart'
                    }
                }
            });

            // Maintenance Status Chart
            const maintenanceCtx = document.getElementById('maintenanceStatusChart').getContext('2d');
            maintenanceChart = new Chart(maintenanceCtx, {
                type: 'pie',
                data: {
                    labels: ['Pending', 'In Progress', 'Completed'],
                    datasets: [{
                        data: [
                            <?php echo $maintenance_status['pending']; ?>,
                            <?php echo $maintenance_status['in_progress']; ?>,
                            <?php echo $maintenance_status['completed']; ?>
                        ],
                        backgroundColor: [
                            'rgba(245, 158, 11, 0.8)',
                            'rgba(6, 182, 212, 0.8)',
                            'rgba(16, 185, 129, 0.8)'
                        ],
                        borderColor: [
                            'rgba(245, 158, 11, 1)',
                            'rgba(6, 182, 212, 1)',
                            'rgba(16, 185, 129, 1)'
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                usePointStyle: true,
                                padding: 20,
                                font: {
                                    family: "'Segoe UI', Tahoma, Geneva, Verdana, sans-serif"
                                }
                            }
                        },
                        tooltip: {
                            backgroundColor: 'rgba(0, 0, 0, 0.8)',
                            titleFont: {
                                size: 14,
                                weight: 'bold'
                            },
                            bodyFont: {
                                size: 12
                            },
                            padding: 12,
                            cornerRadius: 8
                        }
                    },
                    animation: {
                        animateScale: true,
                        animateRotate: true
                    }
                }
            });
        }

        // Initialize charts on page load
        document.addEventListener('DOMContentLoaded', function() {
            initCharts();
            
            // Initialize Bootstrap toasts
            const toastElList = [].slice.call(document.querySelectorAll('.toast'))
            const toastList = toastElList.map(function (toastEl) {
                return new bootstrap.Toast(toastEl)
            });
            toastList.forEach(toast => toast.show());
            
            // Toggle sidebar on mobile
            document.getElementById('sidebarToggle').addEventListener('click', function() {
                document.querySelector('.sidebar').classList.toggle('active');
                document.querySelector('.main-content').classList.toggle('full-width');
            });
            
            // Add ripple effect to buttons
            document.querySelectorAll('.btn-ripple').forEach(button => {
                button.addEventListener('click', function(e) {
                    let x = e.clientX - e.target.getBoundingClientRect().left;
                    let y = e.clientY - e.target.getBoundingClientRect().top;
                    
                    let ripples = document.createElement('span');
                    ripples.style.left = x + 'px';
                    ripples.style.top = y + 'px';
                    this.appendChild(ripples);
                    
                    setTimeout(() => {
                        ripples.remove();
                    }, 1000);
                });
            });
            
            // Start real-time updates
            startRealTimeUpdates();
            
            // Add animation to cards on scroll
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('animate__animated', 'animate__fadeInUp');
                    }
                });
            }, { threshold: 0.1 });
            
            document.querySelectorAll('.modern-card').forEach(card => {
                observer.observe(card);
            });
        });

        // Function to start real-time updates
        function startRealTimeUpdates() {
            // Update dashboard every 30 seconds
            setInterval(updateDashboard, 30000);
        }

        // Function to update dashboard data
        function updateDashboard() {
            fetch('ajax/get_dashboard_stats.php')
                .then(response => response.json())
                .then(data => {
                    // Update summary cards with animation
                    updateCardWithAnimation('tenant-count', data.tenant_count);
                    updateCardWithAnimation('occupancy-rate', `${data.occupancy_rate}% occupancy`);
                    updateCardWithAnimation('apartment-count', data.apartment_count);
                    updateCardWithAnimation('vacant-count', `${data.vacant_count} available`);
                    updateCardWithAnimation('active-maintenance', data.active_maintenance);
                    updateCardWithAnimation('maintenance-stats', `${data.active_maintenance} active, ${data.completed_maintenance} completed`);
                    updateCardWithAnimation('total-revenue', `₱${data.total_revenue.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}`);
                    updateCardWithAnimation('payment-count', `${data.completed_payments} payments`);
                    updateCardWithAnimation('total-revenue-badge', `Total: ₱${data.total_revenue.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}`);
                    updateCardWithAnimation('avg-revenue-badge', `Avg: ₱${data.avg_revenue.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}`);
                    
                    // Update charts
                    updateChartData(apartmentChart, [data.occupied_count, data.vacant_count, data.maintenance_count]);
                    updateChartData(revenueChart, data.monthly_revenue);
                    updateChartData(maintenanceChart, [data.pending_maintenance, data.in_progress_maintenance, data.completed_maintenance]);
                    
                    // Update last update times
                    const now = new Date();
                    const updateTime = now.toLocaleTimeString();
                    document.querySelectorAll('[id$="-last-update"]').forEach(el => {
                        el.textContent = `Updated: ${updateTime}`;
                    });
                    
                    // Update connection status
                    document.getElementById('connection-status').innerHTML = 
                        '<i class="bi bi-circle-fill text-success"></i><small class="ms-1">Live</small>';
                })
                .catch(error => {
                    console.error('Error updating dashboard:', error);
                    document.getElementById('connection-status').innerHTML = 
                        '<i class="bi bi-circle-fill text-danger"></i><small class="ms-1">Offline</small>';
                });
        }

        // Function to update a card with animation
        function updateCardWithAnimation(elementId, newValue) {
            const element = document.getElementById(elementId);
            if (element && element.textContent !== newValue) {
                element.parentElement.classList.add('pulse-update');
                element.textContent = newValue;
                
                // Remove animation class after animation completes
                setTimeout(() => {
                    element.parentElement.classList.remove('pulse-update');
                }, 1000);
            }
        }

        // Function to update chart data
        function updateChartData(chart, newData) {
            if (chart && chart.data.datasets) {
                chart.data.datasets.forEach(dataset => {
                    dataset.data = newData;
                });
                chart.update();
            }
        }
    </script>
</body>
</html>