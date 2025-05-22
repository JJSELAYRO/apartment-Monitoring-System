<?php

session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../public/login.php");
    exit();
}
require_once "../config/db.php";

// Handle form submissions
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Add new payment
    if (isset($_POST["add_payment"])) {
        $tenant_id = intval($_POST["tenant_id"]);
        $amount = floatval($_POST["amount"]);
        $payment_date = $conn->real_escape_string($_POST["payment_date"]);
        $description = $conn->real_escape_string($_POST["description"] ?? '');
        $status = $conn->real_escape_string($_POST["status"]);

        $sql = "INSERT INTO payments (tenant_id, amount, payment_date, description, status) 
                VALUES (?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("idsss", $tenant_id, $amount, $payment_date, $description, $status);

        if ($stmt->execute()) {
            $_SESSION['flash_message'] = "Payment added successfully!";
            $_SESSION['flash_type'] = "success";
        } else {
            $_SESSION['flash_message'] = "Error adding payment: " . $conn->error;
            $_SESSION['flash_type'] = "danger";
        }
        $stmt->close();
        header("Location: payment.php");
        exit();
    } 
    // Update existing payment
    elseif (isset($_POST["update_payment"])) {
        $payment_id = intval($_POST["payment_id"]);
        $tenant_id = intval($_POST["tenant_id"]);
        $amount = floatval($_POST["amount"]);
        $payment_date = $conn->real_escape_string($_POST["payment_date"]);
        $description = $conn->real_escape_string($_POST["description"] ?? '');
        $status = $conn->real_escape_string($_POST["status"]);

        $sql = "UPDATE payments SET 
                    tenant_id = ?, 
                    amount = ?, 
                    payment_date = ?, 
                    description = ?,
                    status = ?
                WHERE id = ?";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("idsssi", $tenant_id, $amount, $payment_date, $description, $status, $payment_id);

        if ($stmt->execute()) {
            $_SESSION['flash_message'] = "Payment updated successfully!";
            $_SESSION['flash_type'] = "success";
        } else {
            $_SESSION['flash_message'] = "Error updating payment: " . $conn->error;
            $_SESSION['flash_type'] = "danger";
        }
        $stmt->close();

        header("Location: payment.php");
        exit();
    }
}

// Delete payment
if (isset($_GET["delete_id"])) {
    $delete_id = intval($_GET["delete_id"]);
    $sql = "DELETE FROM payments WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $delete_id);

    if ($stmt->execute()) {
        $_SESSION['flash_message'] = "Payment deleted successfully!";
        $_SESSION['flash_type'] = "success";
    } else {
        $_SESSION['flash_message'] = "Error deleting payment: " . $conn->error;
        $_SESSION['flash_type'] = "danger";
    }
    $stmt->close();

    header("Location: payment.php");
    exit();
}

// Get all tenants for dropdown
$tenants = [];
$res = $conn->query("SELECT id, name FROM tenants ORDER BY name");
while ($row = $res->fetch_assoc()) {
    $tenants[] = $row;
}

// Get all payments with tenant names
$payments = [];
$res = $conn->query("
    SELECT p.*, t.name AS tenant_name 
    FROM payments p
    JOIN tenants t ON p.tenant_id = t.id
    ORDER BY p.payment_date DESC
");
while ($row = $res->fetch_assoc()) {
    $payments[] = $row;
}

// Calculate summary statistics
$stats = [
    'total_paid' => 0,
    'completed_payments' => 0,
    'pending_payments' => 0
];

foreach ($payments as $payment) {
    if (strtolower($payment['status']) === 'paid') {
        $stats['total_paid'] += $payment['amount'];
        $stats['completed_payments']++;
    } elseif (strtolower($payment['status']) === 'pending') {
        $stats['pending_payments']++;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payments Management</title>
    
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
        
        .summary-card {
            background: white;
            border-radius: var(--border-radius);
            padding: 20px;
            box-shadow: var(--shadow);
            transition: all 0.3s ease;
        }
        
        .summary-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-md);
        }
        
        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-weight: 500;
            font-size: 0.8rem;
            display: inline-flex;
            align-items: center;
            gap: 6px;
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
        
        .table th {
            background-color: var(--primary);
            color: white;
            font-weight: 500;
            padding: 15px;
        }
        
        .table td {
            vertical-align: middle;
            padding: 12px 15px;
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
        
        .flash-message {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
            animation: slideIn 0.5s forwards, fadeOut 0.5s 3s forwards;
        }
        
        @keyframes slideIn {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        
        @keyframes fadeOut {
            from { opacity: 1; }
            to { opacity: 0; }
        }
        
        .fade-in {
            animation: fadeIn 0.5s ease-in;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body>
<div class="container fade-in">
    <a href="dashboard.php" class="btn btn-outline-primary mb-3">
        <i class="fas fa-arrow-left me-2"></i> Back to Dashboard
    </a>
    
    <h2><i class="fas fa-money-bill-wave me-2"></i> Payments Management</h2>
    
    <?php if(isset($_SESSION['flash_message'])): ?>
        <div class="flash-message alert alert-<?php echo $_SESSION['flash_type']; ?> alert-dismissible fade show" role="alert">
            <?php echo $_SESSION['flash_message']; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php unset($_SESSION['flash_message']); unset($_SESSION['flash_type']); ?>
    <?php endif; ?>
    
    <!-- Summary Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="summary-card">
                <div class="title"><i class="fas fa-wallet me-2"></i> Total Paid</div>
                <div class="value">₱<?= number_format($stats['total_paid'], 2) ?></div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="summary-card">
                <div class="title"><i class="fas fa-check-circle me-2"></i> Completed</div>
                <div class="value"><?= $stats['completed_payments'] ?></div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="summary-card">
                <div class="title"><i class="fas fa-hourglass-half me-2"></i> Pending</div>
                <div class="value"><?= $stats['pending_payments'] ?></div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="summary-card">
                <div class="title"><i class="fas fa-list me-2"></i> Total Records</div>
                <div class="value"><?= count($payments) ?></div>
            </div>
        </div>
    </div>
    
    <!-- Add Payment Form -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0"><i class="fas fa-plus-circle me-2"></i> Add New Payment</h5>
        </div>
        <div class="card-body">
            <form method="post">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Tenant</label>
                        <select name="tenant_id" class="form-select" required>
                            <option value="">Select Tenant</option>
                            <?php foreach($tenants as $t): ?>
                            <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Amount</label>
                        <div class="input-group">
                            <span class="input-group-text">₱</span>
                            <input type="number" name="amount" class="form-control" step="0.01" min="0" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Date</label>
                        <input type="date" name="payment_date" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select" required>
                            <option value="Paid">Paid</option>
                            <option value="Pending">Pending</option>
                            <option value="Unpaid">Unpaid</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Description</label>
                        <input type="text" name="description" class="form-control" placeholder="Payment description">
                    </div>
                    <div class="col-12">
                        <button type="submit" name="add_payment" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i> Save Payment
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Payments Table -->
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0"><i class="fas fa-list me-2"></i> Payment Records</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Tenant</th>
                            <th>Description</th>
                            <th>Amount</th>
                            <th>Date</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($payments as $p): ?>
                        <tr class="fade-in">
                            <td><?= $p['id'] ?></td>
                            <td><?= htmlspecialchars($p['tenant_name']) ?></td>
                            <td><?= htmlspecialchars($p['description'] ?? 'N/A') ?></td>
                            <td>₱<?= number_format($p['amount'], 2) ?></td>
                            <td><?= date('M d, Y', strtotime($p['date'])) ?></td>
                            <td>
                                <span class="status-badge status-<?= strtolower($p['status']) ?>">
                                    <?= htmlspecialchars($p['status']) ?>
                                </span>
                            </td>
                            <td class="text-end">
                              <!-- Edit Button - Triggers modal -->
<button class="btn btn-sm btn-primary me-1" 
        data-bs-toggle="modal" 
        data-bs-target="#editPaymentModal"
        data-payment-id="<?= $p['id'] ?? '' ?>"
        data-tenant-id="<?= $p['tenant_id'] ?? '' ?>"
        data-amount="<?= $p['amount'] ?? '' ?>"
        data-date="<?= $p['payment_date'] ?? ($p['date'] ?? '') ?>"
        data-description="<?= htmlspecialchars($p['description'] ?? '') ?>"
        data-status="<?= $p['status'] ?? '' ?>"
        title="Edit">
    <i class="fas fa-edit"></i>
</button>
                                <!-- Delete Button -->
                                <a href="payment.php?delete_id=<?= $p['id'] ?>" 
                                   class="btn btn-sm btn-danger" 
                                   onclick="return confirm('Are you sure you want to delete this payment?');"
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

<!-- Edit Payment Modal -->
<div class="modal fade" id="editPaymentModal" tabindex="-1" aria-labelledby="editPaymentModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editPaymentModalLabel">Edit Payment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post">
                <div class="modal-body">
                    <input type="hidden" name="payment_id" id="edit_payment_id">
                    
                    <div class="mb-3">
                        <label class="form-label">Tenant</label>
                        <select name="tenant_id" class="form-select" required id="edit_tenant_id">
                            <option value="">Select Tenant</option>
                            <?php foreach($tenants as $t): ?>
                            <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Amount</label>
                        <div class="input-group">
                            <span class="input-group-text">₱</span>
                            <input type="number" name="amount" class="form-control" step="0.01" min="0" required id="edit_amount">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Date</label>
                        <input type="date" name="payment_date" class="form-control" required id="edit_date">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select" required id="edit_status">
                            <option value="Paid">Paid</option>
                            <option value="Pending">Pending</option>
                            <option value="Unpaid">Unpaid</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <input type="text" name="description" class="form-control" id="edit_description">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="update_payment" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Initialize edit modal with payment data
    document.addEventListener('DOMContentLoaded', function() {
        const editModal = document.getElementById('editPaymentModal');
        
        editModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            
            // Extract payment data from button attributes
            const paymentId = button.getAttribute('data-payment-id');
            const tenantId = button.getAttribute('data-tenant-id');
            const amount = button.getAttribute('data-amount');
            const date = button.getAttribute('data-date');
            const description = button.getAttribute('data-description');
            const status = button.getAttribute('data-status');
            
            // Update modal fields
            document.getElementById('edit_payment_id').value = paymentId;
            document.getElementById('edit_tenant_id').value = tenantId;
            document.getElementById('edit_amount').value = amount;
            document.getElementById('edit_date').value = date;
            document.getElementById('edit_description').value = description;
            document.getElementById('edit_status').value = status;
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