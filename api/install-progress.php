<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

session_start();

$sessionId = session_id();

// Check if installation data exists
if (!isset($_SESSION['install_data'])) {
    echo json_encode([
        'status' => 'error',
        'message' => 'No installation session found',
        'progress' => 0
    ]);
    exit;
}

$progress = $_SESSION['install_progress'] ?? 0;
$logs = $_SESSION['install_logs'] ?? [];
$status = $_SESSION['install_status'] ?? 'running';

// Clear logs after sending them
$_SESSION['install_logs'] = [];

echo json_encode([
    'status' => $status,
    'progress' => $progress,
    'logs' => $logs
]);
?>
