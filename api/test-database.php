<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Get POST data
$input = json_decode(file_get_contents('php://input'), true);

// Fallback pokud JSON není poslán
if (!$input) {
    $input = $_POST;
}

if (!$input) {
    echo json_encode(['success' => false, 'message' => 'No data received']);
    exit;
}

$host = $input['db_host'] ?? 'localhost';
$port = $input['db_port'] ?? 3306;
$rootUser = $input['db_root_user'] ?? 'root';
$rootPass = $input['db_root_pass'] ?? '';
$travianUser = $input['db_user'] ?? 'travian';
$travianPass = $input['db_pass'] ?? '';

try {
    // Test root connection
    $dsn = "mysql:host=$host;port=$port;charset=utf8mb4";
    $pdo = new PDO($dsn, $rootUser, $rootPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    // Test if we can create databases
    $pdo->exec("CREATE DATABASE IF NOT EXISTS test_installer_connection");
    $pdo->exec("DROP DATABASE test_installer_connection");

    // Test if travian user can be created
    $pdo->exec("CREATE USER IF NOT EXISTS '$travianUser'@'localhost' IDENTIFIED BY '$travianPass'");
    $pdo->exec("DROP USER IF EXISTS '$travianUser'@'localhost'");

    echo json_encode([
        'success' => true,
        'message' => 'Database connection successful and permissions verified'
    ]);

} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database connection failed: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Unexpected error: ' . $e->getMessage()
    ]);
}
?>
