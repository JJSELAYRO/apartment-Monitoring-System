<?php
session_start();
require_once '../../config/db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id']) && isset($_SESSION['user_id'])) {
    $tenant_id = $_SESSION['user_id'];
    $id = intval($_POST['id']);
    // Only allow tenant to delete their own request
    $stmt = $conn->prepare("DELETE FROM maintenance_requests WHERE id = ? AND tenant_id = ?");
    $stmt->bind_param('ii', $id, $tenant_id);
    $success = $stmt->execute();
    $stmt->close();
    http_response_code(200);
    echo json_encode(['success' => $success]);
    exit;
} else {
    http_response_code(400);
    echo json_encode(['success' => false]);
    exit;
}