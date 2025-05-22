<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'tenant') {
    header("Location: ../public/login.php");
    exit();
}

require_once '../config/db.php';

$tenant_id = $_SESSION['user_id'];

// Fetch tenant details
$tenant_query = $conn->prepare("SELECT name, email, phone, apartment_id FROM tenants WHERE id = ?");
$tenant_query->bind_param("i", $tenant_id);
$tenant_query->execute();
$tenant = $tenant_query->get_result()->fetch_assoc();
$tenant_query->close();

// Fetch apartment details if assigned
$apartment = null;
if ($tenant['apartment_id']) {
    $apartment_query = $conn->prepare("SELECT * FROM apartments WHERE id = ?");
    $apartment_query->bind_param("i", $tenant['apartment_id']);
    $apartment_query->execute();
    $apartment = $apartment_query->get_result()->fetch_assoc();
    $apartment_query->close();
}

// Payment statistics
$payment_stats = [
    'total_paid' => 0,
    'total_unpaid' => 0,
    'total_payments' => 0,
    'monthly_payments' => array_fill(0, 12, 0) // Initialize all months to 0
];

// Total paid payments
$paid_query = $conn->prepare("SELECT SUM(amount) as total FROM payments WHERE tenant_id = ? AND status = 'paid'");
$paid_query->bind_param("i", $tenant_id);
$paid_query->execute();
$payment_stats['total_paid'] = (float)($paid_query->get_result()->fetch_assoc()['total'] ?? 0);
$paid_query->close();

// Total unpaid payments
$unpaid_query = $conn->prepare("SELECT SUM(amount) as total FROM payments WHERE tenant_id = ? AND status = 'pending'");
$unpaid_query->bind_param("i", $tenant_id);
$unpaid_query->execute();
$payment_stats['total_unpaid'] = (float)($unpaid_query->get_result()->fetch_assoc()['total'] ?? 0);
$unpaid_query->close();

$payment_stats['total_payments'] = $payment_stats['total_paid'] + $payment_stats['total_unpaid'];

// Monthly payments data for chart
$monthly_query = $conn->prepare("SELECT MONTH(payment_date) as month, SUM(amount) as total FROM payments WHERE tenant_id = ? AND status = 'paid' GROUP BY MONTH(payment_date)");
$monthly_query->bind_param("i", $tenant_id);
$monthly_query->execute();
$result = $monthly_query->get_result();
while ($row = $result->fetch_assoc()) {
    $payment_stats['monthly_payments'][$row['month'] - 1] = (float)$row['total'];
}
$monthly_query->close();

// Maintenance requests
$maintenance_stats = [
    'pending' => 0,
    'in_progress' => 0,
    'completed' => 0,
    'recent_requests' => []
];

// Count by status (normalize status values)
$maint_count_query = $conn->prepare("SELECT status, COUNT(*) as count FROM maintenance_requests WHERE tenant_id = ? GROUP BY status");
$maint_count_query->bind_param("i", $tenant_id);
$maint_count_query->execute();
$result = $maint_count_query->get_result();
while ($row = $result->fetch_assoc()) {
    $status = str_replace(' ', '_', strtolower($row['status']));
    if (in_array($status, ['pending', 'in_progress', 'completed'])) {
        $maintenance_stats[$status] = (int)$row['count'];
    }
}
$maint_count_query->close();

// Recent maintenance requests (last 5)
$recent_maint_query = $conn->prepare("SELECT id, request_date, request_text, status FROM maintenance_requests WHERE tenant_id = ? ORDER BY request_date DESC LIMIT 5");
$recent_maint_query->bind_param("i", $tenant_id);
$recent_maint_query->execute();
$result = $recent_maint_query->get_result();
while ($row = $result->fetch_assoc()) {
    $maintenance_stats['recent_requests'][] = [
        'id' => $row['id'],
        'date' => date('M j, Y', strtotime($row['request_date'])),
        'title' => 'Maintenance Request',
        'description' => $row['request_text'],
        'status' => $row['status']
    ];
}
$recent_maint_query->close();
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tenant Dashboard | PropertyPro</title>
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
        
        .card-payment::before { background: var(--primary-color); }
        .card-paid::before { background: var(--success-color); }
        .card-unpaid::before { background: var(--danger-color); }
        .card-maintenance::before { background: var(--warning-color); }
        
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
        
        .status-badge {
            padding: 0.25rem 0.5rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .status-pending {
            background-color: rgba(248, 150, 30, 0.1);
            color: var(--warning-color);
        }
        
        .status-in-progress {
            background-color: rgba(6, 182, 212, 0.1);
            color: var(--info-color);
        }
        
        .status-completed {
            background-color: rgba(16, 185, 129, 0.1);
            color: var(--success-color);
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

        /* Delete Button */
        .delete-maintenance-btn {
            transition: all 0.2s ease;
            opacity: 0.7;
        }
        
        .delete-maintenance-btn:hover {
            opacity: 1;
            transform: scale(1.1);
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 2rem 0;
            color: #6c757d;
        }
        
        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }
    </style>
</head>
<body>
    <div class="d-flex">
        <!-- Sidebar -->
        <div class="sidebar">
            <a href="tenant_dashboard.php" class="sidebar-brand d-flex align-items-center">
                <i class="bi bi-building me-2"></i>
                <span>PropertyPro</span>
            </a>
            
            <ul class="sidebar-nav">
                <li class="nav-item">
                    <a href="tenant_dashboard.php" class="nav-link active">
                        <i class="bi bi-speedometer2"></i>
                        <span>Dashboard</span>
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
                    <span class="navbar-brand mb-0 h1 fw-bold text-primary">Tenant Dashboard</span>
                    <div class="user-profile">
                        <img src="https://ui-avatars.com/api/?name=<?= urlencode($tenant['name']) ?>&background=random" alt="<?= htmlspecialchars($tenant['name']) ?>">
                        <span class="fw-medium"><?= htmlspecialchars($tenant['name']) ?></span>
                        <?php if ($apartment): ?>
                            <span class="badge bg-primary ms-2"><?= htmlspecialchars($apartment['number']) ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            </nav>
            
            <div class="container-fluid py-3">
                <!-- Welcome Message -->
                <div class="welcome-message mb-4 slide-in">
                    <h4 class="fw-bold text-dark mb-2">Welcome back, <?= htmlspecialchars($tenant['name']) ?> 👋</h4>
                    <p class="text-muted">
                        <?php if ($apartment): ?>
                            You're staying in apartment <?= htmlspecialchars($apartment['number']) ?>
                        <?php else: ?>
                            You haven't been assigned to an apartment yet
                        <?php endif; ?>
                    </p>
                </div>
                
                <!-- Summary Cards -->
                <div class="row g-4 mb-4" id="summary-cards">
                    <!-- Total Payments Card -->
                    <div class="col-md-6 col-lg-3">
                        <div class="modern-card summary-card card-payment fade-in">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div class="bg-primary bg-opacity-10 rounded-lg p-3 me-3">
                                        <i class="bi bi-cash-stack fs-4 text-primary"></i>
                                    </div>
                                    <div>
                                        <h6 class="card-subtitle mb-1 text-muted">Total Payments</h6>
                                        <h3 class="card-title mb-0 text-dark">₱<?= number_format($payment_stats['total_payments'], 2) ?></h3>
                                        <small class="text-muted">
                                            <?= ($payment_stats['total_paid'] > 0 ? round(($payment_stats['total_paid'] / $payment_stats['total_payments']) * 100) : 0) ?>% paid
                                        </small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Paid Payments Card -->
                    <div class="col-md-6 col-lg-3">
                        <div class="modern-card summary-card card-paid fade-in">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div class="bg-success bg-opacity-10 rounded-lg p-3 me-3">
                                        <i class="bi bi-check-circle fs-4 text-success"></i>
                                    </div>
                                    <div>
                                        <h6 class="card-subtitle mb-1 text-muted">Paid</h6>
                                        <h3 class="card-title mb-0 text-dark">₱<?= number_format($payment_stats['total_paid'], 2) ?></h3>
                                        <small class="text-muted">
                                            <?= ($payment_stats['total_paid'] > 0 ? round($payment_stats['total_paid'] / max($payment_stats['total_payments'], 1) * 100) : 0) ?>% of total
                                        </small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Unpaid Payments Card -->
                    <div class="col-md-6 col-lg-3">
                        <div class="modern-card summary-card card-unpaid fade-in">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div class="bg-danger bg-opacity-10 rounded-lg p-3 me-3">
                                        <i class="bi bi-exclamation-circle fs-4 text-danger"></i>
                                    </div>
                                    <div>
                                        <h6 class="card-subtitle mb-1 text-muted">Unpaid</h6>
                                        <h3 class="card-title mb-0 text-dark">₱<?= number_format($payment_stats['total_unpaid'], 2) ?></h3>
                                        <small class="text-muted">
                                            <?= $payment_stats['total_unpaid'] > 0 ? 'Payment due' : 'All paid up' ?>
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
                                        <h6 class="card-subtitle mb-1 text-muted">Maintenance</h6>
                                        <h3 class="card-title mb-0 text-dark"><?= $maintenance_stats['pending'] + $maintenance_stats['in_progress'] ?></h3>
                                        <small class="text-muted">
                                            <?= $maintenance_stats['pending'] ?> pending, <?= $maintenance_stats['in_progress'] ?> in progress
                                        </small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Charts Section -->
                <div class="row g-4 mb-4">
                    <!-- Payment History Chart -->
                    <div class="col-lg-6">
                        <div class="modern-card chart-container h-100 slide-in">
                            <div class="chart-title">
                                <h5 class="fw-bold">Payment History</h5>
                                <div>
                                    <a href="payment.php" class="btn btn-sm btn-outline-primary btn-ripple">View All</a>
                                </div>
                            </div>
                            <canvas id="paymentHistoryChart" height="250"></canvas>
                            <div class="text-center mt-3">
                                <span class="badge bg-primary">Total Paid: ₱<?= number_format($payment_stats['total_paid'], 2) ?></span>
                                <span class="badge bg-danger ms-2">Total Unpaid: ₱<?= number_format($payment_stats['total_unpaid'], 2) ?></span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Maintenance Status Chart -->
                    <div class="col-lg-6">
                        <div class="modern-card chart-container h-100 slide-in">
                            <div class="chart-title">
                                <h5 class="fw-bold">Maintenance Requests</h5>
                                <div>
                                    <a href="maintenance.php" class="btn btn-sm btn-outline-primary btn-ripple">View All</a>
                                </div>
                            </div>
                            <canvas id="maintenanceStatusChart" height="250"></canvas>
                            <div class="quick-stats mt-3 d-flex justify-content-around text-center">
                                <div>
                                    <div class="text-warning fw-bold"><?= $maintenance_stats['pending'] ?></div>
                                    <small class="text-muted">Pending</small>
                                </div>
                                <div>
                                    <div class="text-info fw-bold"><?= $maintenance_stats['in_progress'] ?></div>
                                    <small class="text-muted">In Progress</small>
                                </div>
                                <div>
                                    <div class="text-success fw-bold"><?= $maintenance_stats['completed'] ?></div>
                                    <small class="text-muted">Completed</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Recent Activity Section -->
                <div class="row g-4">
                    <div class="col-lg-12">
                        <div class="modern-card chart-container h-100 slide-in">
                            <div class="chart-title">
                                <h5 class="fw-bold">Recent Maintenance Requests</h5>
                                <div>
                                    <a href="maintenance.php" class="btn btn-sm btn-outline-primary btn-ripple">View All</a>
                                </div>
                            </div>
                            <div id="recent-activity-container" style="max-height: 400px; overflow-y: auto;">
                                <?php if (!empty($maintenance_stats['recent_requests'])): ?>
                                    <ul class="recent-activity" id="recent-activity-list">
                                        <?php foreach ($maintenance_stats['recent_requests'] as $request): 
                                            $status = str_replace(' ', '_', strtolower($request['status']));
                                            if ($status === 'pending') {
                                                $status_class = 'status-pending';
                                                $status_text = 'Pending';
                                            } elseif ($status === 'in_progress') {
                                                $status_class = 'status-in-progress';
                                                $status_text = 'In Progress';
                                            } else {
                                                $status_class = 'status-completed';
                                                $status_text = 'Completed';
                                            }
                                        ?>
                                        <li class="animate__animated animate__fadeIn d-flex align-items-center">
                                            <div class="activity-icon text-warning">
                                                <i class="bi bi-tools"></i>
                                            </div>
                                            <div class="activity-details flex-grow-1">
                                                <strong><?= htmlspecialchars($request['title']) ?></strong>
                                                <div><?= htmlspecialchars($request['description']) ?></div>
                                                <div class="activity-time">
                                                    <span><?= $request['date'] ?></span>
                                                    <span class="status-badge <?= $status_class ?>"><?= $status_text ?></span>
                                                </div>
                                            </div>
                                            <button 
                                                class="btn btn-sm btn-danger ms-3 delete-maintenance-btn" 
                                                data-id="<?= $request['id'] ?>">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php else: ?>
                                    <div class="empty-state">
                                        <i class="bi bi-inbox"></i>
                                        <p>No recent maintenance requests</p>
                                    </div>
                                <?php endif; ?>
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
    let paymentChart, maintenanceChart;

    // Function to initialize charts
    function initCharts() {
        // Payment History Chart
        const paymentCtx = document.getElementById('paymentHistoryChart').getContext('2d');
        paymentChart = new Chart(paymentCtx, {
            type: 'bar',
            data: {
                labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
                datasets: [{
                    label: 'Payments (₱)',
                    data: <?= json_encode($payment_stats['monthly_payments']) ?>,
                    backgroundColor: 'rgba(67, 97, 238, 0.7)',
                    borderColor: 'rgba(67, 97, 238, 1)',
                    borderWidth: 1,
                    borderRadius: 6,
                    hoverBackgroundColor: 'rgba(67, 97, 238, 0.9)'
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
                            }
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return '₱' + context.raw.toLocaleString();
                            }
                        }
                    }
                }
            }
        });

        // Maintenance Status Chart
        const maintenanceCtx = document.getElementById('maintenanceStatusChart').getContext('2d');
        maintenanceChart = new Chart(maintenanceCtx, {
            type: 'doughnut',
            data: {
                labels: ['Pending', 'In Progress', 'Completed'],
                datasets: [{
                    data: [
                        <?= $maintenance_stats['pending'] ?>,
                        <?= $maintenance_stats['in_progress'] ?>,
                        <?= $maintenance_stats['completed'] ?>
                    ],
                    backgroundColor: [
                        'rgba(248, 150, 30, 0.8)',
                        'rgba(6, 182, 212, 0.8)',
                        'rgba(16, 185, 129, 0.8)'
                    ],
                    borderColor: [
                        'rgba(248, 150, 30, 1)',
                        'rgba(6, 182, 212, 1)',
                        'rgba(16, 185, 129, 1)'
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
                            padding: 20
                        }
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

        // Toggle sidebar on mobile
        document.getElementById('sidebarToggle').addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('active');
            document.querySelector('.main-content').classList.toggle('full-width');
        });

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

        // Delete maintenance request button (fixed version)
        document.querySelectorAll('.delete-maintenance-btn').forEach(function(btn) {
            btn.addEventListener('click', function() {
                if (confirm('Are you sure you want to delete this request?')) {
                    const requestId = this.getAttribute('data-id');
                    fetch('ajax/delete_maintenance_request.php', { // fixed path
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: 'id=' + encodeURIComponent(requestId)
                    })
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            this.closest('li').remove();
                        } else {
                            alert('Failed to delete request.');
                        }
                    });
                }
            });
        });
    });

    // Function to update dashboard data periodically (optional)
    function updateDashboard() {
        fetch('ajax/get_tenant_stats.php?tenant_id=<?= $tenant_id ?>')
            .then(response => response.json())
            .then(data => {
                // Update summary cards
                document.getElementById('tenant-count').textContent = data.tenant_count;
                document.getElementById('occupancy-rate').textContent = `${data.occupancy_rate}% occupancy`;
                document.getElementById('apartment-count').textContent = data.apartment_count;
                document.getElementById('vacant-count').textContent = `${data.vacant_count} available`;
                document.getElementById('active-maintenance').textContent = data.active_maintenance;
                document.getElementById('maintenance-stats').textContent = `${data.active_maintenance} active, ${data.completed_maintenance} completed`;
                document.getElementById('total-revenue').textContent = `₱${data.total_revenue.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}`;
                document.getElementById('payment-count').textContent = `${data.completed_payments} payments`;

                // Update charts
                updateChartData(paymentChart, data.monthly_payments);
                updateChartData(maintenanceChart, [data.pending_maintenance, data.in_progress_maintenance, data.completed_maintenance]);
            })
            .catch(error => {
                console.error('Error updating dashboard:', error);
            });
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