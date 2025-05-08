<?php
include 'db.php';

header('Content-Type: application/json');

$user_unique_id = $_GET['user_id'] ?? null;

if (!$user_unique_id) {
    echo json_encode(['success' => false, 'message' => 'User ID is required']);
    exit;
}

// Get user ID from unique_id
$stmt = $conn->prepare("SELECT id FROM users WHERE unique_id = ?");
$stmt->bind_param("s", $user_unique_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid user']);
    exit;
}
$user = $result->fetch_assoc();
$user_id = $user['id'];

// Fetch notifications for user
$stmt = $conn->prepare("SELECT id, message, is_read, sent_at FROM notifications WHERE user_id = ? ORDER BY sent_at DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$notifications = [];
while ($row = $result->fetch_assoc()) {
    $notifications[] = $row;
}

echo json_encode(['success' => true, 'notifications' => $notifications]);
?>
