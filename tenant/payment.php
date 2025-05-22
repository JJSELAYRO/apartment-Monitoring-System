<?php
// Start session and verify that user is logged in as a tenant
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'tenant') {
    header("Location: ../public/login.php");
    exit();
}

require_once "../config/db.php";
$tenant_id = $_SESSION['user_id'];

$date_column = 'date'; // Set the column name for sorting
$payments = [];

// Prepare and execute the query to fetch tenant payment records
$stmt = $conn->prepare("SELECT * FROM payments WHERE tenant_id = ? ORDER BY $date_column DESC");
$stmt->bind_param("i", $tenant_id);
$stmt->execute();
$res = $stmt->get_result();

// Store results in an array
while ($row = $res->fetch_assoc()) $payments[] = $row;
$stmt->close();

// Calculate summary statistics from the database
$total_paid = 0;
$completed_payments = 0;
$pending_payments = 0;
$next_payment_due = null;

foreach ($payments as $payment) {
    if (strtolower($payment['status']) === 'paid' || strtolower($payment['status']) === 'completed') {
        $total_paid += $payment['amount'];
        $completed_payments++;
    } elseif (strtolower($payment['status']) === 'pending') {
        $pending_payments++;
    }
    
    // Find the earliest upcoming payment
    if (strtolower($payment['status']) === 'pending' || strtolower($payment['status']) === 'unpaid') {
        $payment_date = strtotime($payment['date']);
        if ($payment_date > time() && ($next_payment_due === null || $payment_date < $next_payment_due)) {
            $next_payment_due = $payment_date;
        }
    }
}

// Format next payment due date
$next_payment_text = "No upcoming payments";
$days_remaining = "";
if ($next_payment_due !== null) {
    $next_payment_text = date('M d, Y', $next_payment_due);
    $days_diff = ($next_payment_due - time()) / (60 * 60 * 24);
    $days_remaining = ceil($days_diff) . " days remaining";
    
    if ($days_diff < 0) {
        $days_remaining = abs(ceil($days_diff)) . " days overdue";
    } elseif ($days_diff < 1) {
        $days_remaining = "Due today";
    }
}

// Calculate trends (this would ideally compare with previous month data)
$previous_month_completed = 0; // You would query this from database
$previous_month_pending = 0;   // You would query this from database
$completed_trend = ($completed_payments - $previous_month_completed) > 0 ? "up" : "down";
$pending_trend = ($pending_payments - $previous_month_pending) > 0 ? "up" : "down";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Payments</title>
    <!-- Bootstrap and Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
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

        /* Summary Cards */
        .summary-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .summary-card {
            background: white;
            border-radius: var(--border-radius);
            padding: 20px;
            box-shadow: var(--shadow);
            transition: all 0.3s ease;
            display: flex;
            flex-direction: column;
        }

        .summary-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-md);
        }

        .summary-card .title {
            font-size: 0.9rem;
            color: var(--gray);
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .summary-card .value {
            font-size: 1.8rem;
            font-weight: 600;
            margin-bottom: 5px;
        }

        .summary-card .trend {
            font-size: 0.8rem;
            display: flex;
            align-items: center;
            gap: 5px;
            margin-top: auto;
        }

        .trend.up {
            color: var(--success);
        }

        .trend.down {
            color: var(--danger);
        }

        /* Status badges */
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

        .status-paid {
            background-color: rgba(16, 185, 129, 0.15);
            color: var(--success);
        }

        .status-pending {
            background-color: rgba(245, 158, 11, 0.15);
            color: var(--warning);
        }

        .status-unpaid {
            background-color: rgba(239, 68, 68, 0.15);
            color: var(--danger);
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

        /* Payment receipt preview */
        .receipt-preview {
            cursor: pointer;
            color: var(--primary);
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .receipt-preview:hover {
            color: var(--secondary);
            transform: translateX(3px);
        }
    </style>
</head>
<body>

<!-- Top Navigation Bar -->
<div class="topbar">
    <a href="dashboard.php" class="back-btn">
        <i class="bi bi-arrow-left-circle-fill"></i> Back
    </a>
    <h1><i class="bi bi-receipt-cutoff"></i> Payment Records</h1>
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

 
      <!-- Payment Summary Cards with Real Data -->
    <div class="summary-cards">
        <div class="summary-card">
            <div class="title"><i class="bi bi-currency-exchange"></i> Total Paid</div>
            <div class="value">₱<?= number_format($total_paid, 2) ?></div>
            <div class="trend up">
                <i class="bi bi-arrow-up"></i> All completed payments
            </div>
        </div>
        
        <div class="summary-card">
            <div class="title"><i class="bi bi-check-circle"></i> Completed Payments</div>
            <div class="value"><?= $completed_payments ?></div>
            <div class="trend <?= $completed_trend ?>">
                <i class="bi bi-arrow-<?= $completed_trend ?>"></i> 
                <?= abs($completed_payments - $previous_month_completed) ?> from last month
            </div>
        </div>
        
        <div class="summary-card">
            <div class="title"><i class="bi bi-hourglass"></i> Pending Payments</div>
            <div class="value"><?= $pending_payments ?></div>
            <div class="trend <?= $pending_trend ?>">
                <i class="bi bi-arrow-<?= $pending_trend ?>"></i> 
                <?= abs($pending_payments - $previous_month_pending) ?> from last month
            </div>
        </div>
        
        <div class="summary-card">
            <div class="title"><i class="bi bi-calendar-check"></i> Next Payment Due</div>
            <div class="value"><?= $next_payment_text ?></div>
            <div class="trend">
                <i class="bi bi-alarm"></i> <?= $days_remaining ?>
            </div>
        </div>
    </div>

    <!-- Payment Records Table -->
    <div class="mb-4">
        <h5 class="mb-3"><i class="bi bi-list-check"></i> Payment History</h5>
        <div class="table-container">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th><i class="bi bi-file-text"></i> Description</th>
                        <th><i class="bi bi-calendar-event"></i> Date</th>
                        <th><i class="bi bi-cash-coin"></i> Amount</th>
                        <th><i class="bi bi-receipt"></i> Receipt</th>
                        <th><i class="bi bi-circle-half"></i> Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($payments)): ?>
                        <tr>
                            <td colspan="5" class="empty-state">
                                <i class="bi bi-inbox"></i>
                                <h5>No payment records found</h5>
                                <p>Your payment history will appear here</p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach($payments as $index => $p): ?>
                            <tr class="fade-in-row" style="animation-delay: <?php echo $index * 0.05; ?>s">
                                <td><?= htmlspecialchars($p['description'] ?? 'N/A') ?></td>
                                <td>
                                    <?php
                                        $date_raw = $p[$date_column] ?? '';
                                        echo ($date_raw && $date_raw !== '0000-00-00') ?
                                            date('M d, Y', strtotime($date_raw)) : 'N/A';
                                    ?>
                                </td>
                                <td>₱<?= number_format($p['amount'] ?? 0, 2) ?></td>
                                <td>
                                    <?php if(!empty($p['receipt_url'])): ?>
                                        <a href="<?= $p['receipt_url'] ?>" class="receipt-preview" target="_blank">
                                            <i class="bi bi-eye-fill"></i> View
                                        </a>
                                    <?php else: ?>
                                        N/A
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                        $status = strtolower($p['status']);
                                        $badgeClass = 'status-pending';
                                        $icon = 'bi-hourglass-top';
                                        if ($status === 'paid' || $status === 'completed') {
                                            $badgeClass = 'status-paid';
                                            $icon = 'bi-check-circle';
                                        } elseif ($status === 'unpaid') {
                                            $badgeClass = 'status-unpaid';
                                            $icon = 'bi-x-circle';
                                        }
                                    ?>
                                    <span class="status-badge <?= $badgeClass ?>">
                                        <i class="bi <?= $icon ?>"></i>
                                        <?= ucfirst($p['status']) ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Close flash message after 3 seconds
        const flashMessage = document.querySelector('.flash-message');
        if (flashMessage) {
            setTimeout(() => {
                flashMessage.style.display = 'none';
            }, 3000);
        }
        
        // Add hover effects to table rows
        const tableRows = document.querySelectorAll('.table tbody tr');
        tableRows.forEach(row => {
            row.addEventListener('mouseenter', function() {
                this.style.transform = 'scale(1.01)';
                this.style.boxShadow = 'var(--shadow-sm)';
            });
            row.addEventListener('mouseleave', function() {
                this.style.transform = '';
                this.style.boxShadow = '';
            });
        });
    });
</script>
</body>
</html>