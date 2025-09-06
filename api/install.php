<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Get POST data
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    echo json_encode(['success' => false, 'message' => 'No data received']);
    exit;
}

// Create installation session
session_start();
$sessionId = session_id();

// Store installation data in session
$_SESSION['install_data'] = $input;
$_SESSION['install_progress'] = 0;
$_SESSION['install_logs'] = [];
$_SESSION['install_status'] = 'running';

// Start installation in background
$installScript = __DIR__ . '/install-background.php';
$command = "php $installScript $sessionId > /dev/null 2>&1 &";
exec($command);

echo json_encode([
    'success' => true,
    'message' => 'Installation started',
    'session_id' => $sessionId
]);
?>
