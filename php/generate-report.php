<?php
session_start();
include 'db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$role = $_SESSION['role'];

if ($role !== 'captain') {
    echo json_encode(['success' => false, 'message' => 'Permission denied']);
    exit;
}

$sql = "SELECT r.id, r.title, r.description, r.type, r.status, u.name as reporter_name, r.created_at
        FROM reports r
        JOIN users u ON r.user_id = u.id
        ORDER BY r.created_at DESC";

$result = $conn->query($sql);

if (!$result) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
    exit;
}

$filename = "reports_" . date('Ymd_His') . ".csv";
$filepath = "../reports/" . $filename;

if (!is_dir("../reports")) {
    mkdir("../reports", 0755, true);
}

$fp = fopen($filepath, 'w');
if (!$fp) {
    echo json_encode(['success' => false, 'message' => 'Failed to create report file']);
    exit;
}

// Write CSV headers
fputcsv($fp, ['Report ID', 'Title', 'Description', 'Type', 'Status', 'Reporter Name', 'Created At']);

// Write data rows
while ($row = $result->fetch_assoc()) {
    fputcsv($fp, [
        $row['id'],
        $row['title'],
        $row['description'],
        $row['type'],
        $row['status'],
        $row['reporter_name'],
        $row['created_at']
    ]);
}

fclose($fp);

echo json_encode(['success' => true, 'message' => 'Report generated', 'file' => 'reports/' . $filename]);
?>
