<?php
// filepath: c:\xampp\htdocs\barangay-app\php\get-user-reports.php
session_start();
header('Content-Type: application/json');

// Check if the user is logged in
if (!isset($_SESSION['user']['id'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

require_once 'db.php'; // Include your database connection

$user_id = $_SESSION['user']['id']; // Get the logged-in user's ID

$sql = "SELECT r.id, r.title, r.description, r.type, r.status, r.created_at, r.updated_at, 
               f.rating, f.comment
        FROM reports r
        LEFT JOIN feedback f ON r.id = f.report_id
        WHERE r.user_id = ? AND r.status = 'Resolved'
        ORDER BY r.created_at DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$reports = [];
while ($row = $result->fetch_assoc()) {
    $reports[] = $row;
}

echo json_encode(['success' => true, 'reports' => $reports]);
?>