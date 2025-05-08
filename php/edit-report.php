<?php
session_start();
include 'db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user']) || !isset($_SESSION['user']['role'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SESSION['user']['role'] !== 'captain') {
    echo json_encode(['success' => false, 'message' => 'Permission denied']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

$report_id = $data['report_id'] ?? null;
$title = $data['title'] ?? null;
$description = $data['description'] ?? null;
$type = $data['type'] ?? null;
$location_text = $data['location_text'] ?? null;
$status = $data['status'] ?? null;

if (!$report_id || !$title || !$description || !$type || !$location_text || !$status) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

$stmt = $conn->prepare("UPDATE reports SET title = ?, description = ?, type = ?, location_text = ?, status = ? WHERE id = ?");
$stmt->bind_param("sssssi", $title, $description, $type, $location_text, $status, $report_id);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Report updated successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $stmt->error]);
}
?>
