<?php
include 'db.php';

header('Content-Type: application/json');

// Get Authorization header
$headers = getallheaders();
if (!isset($headers['Authorization'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized: Missing Authorization header']);
    exit;
}

$authHeader = $headers['Authorization'];
if (strpos($authHeader, 'Bearer ') !== 0) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized: Invalid Authorization header']);
    exit;
}

$token = substr($authHeader, 7);

// TODO: Validate token and get user info from database or token payload
// For now, assume token is user unique_id and fetch user info from users table

$stmtUser = $conn->prepare("SELECT id, role FROM users WHERE unique_id = ?");
$stmtUser->bind_param("s", $token);
$stmtUser->execute();
$resultUser = $stmtUser->get_result();

if ($resultUser->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized: User not found']);
    exit;
}

$user = $resultUser->fetch_assoc();
$user_id = $user['id'];
$role = $user['role'];

if ($role === 'resident') {
$stmt = $conn->prepare("SELECT r.id, r.title, r.description, r.type, r.image_path, r.latitude, r.longitude, r.location_text, r.status, r.created_at, r.updated_at, u.name as reporter_name,
        f.rating, f.comment
        FROM reports r
        JOIN users u ON r.user_id = u.id
        LEFT JOIN feedback f ON r.id = f.report_id
        WHERE r.user_id = ?
        ORDER BY r.created_at DESC");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
} else if ($role === 'official' || $role === 'captain') {
    // Apply filters if provided
    $filters = [];
    $params = [];
    $types = '';

    if (isset($_GET['status']) && in_array($_GET['status'], ['Pending', 'In Progress', 'Resolved'])) {
        $filters[] = 'r.status = ?';
        $params[] = $_GET['status'];
        $types .= 's';
    }
    if (isset($_GET['type']) && in_array($_GET['type'], ['Garbage', 'Road Damage', 'Flooding', 'Other'])) {
        $filters[] = 'r.type = ?';
        $params[] = $_GET['type'];
        $types .= 's';
    }
    if (isset($_GET['start_date']) && isset($_GET['end_date'])) {
        $filters[] = 'r.created_at BETWEEN ? AND ?';
        $params[] = $_GET['start_date'];
        $params[] = $_GET['end_date'];
        $types .= 'ss';
    }
    $whereClause = '';
    if (count($filters) > 0) {
        $whereClause = 'WHERE ' . implode(' AND ', $filters);
    }

$sql = "SELECT r.id, r.title, r.description, r.type, r.image_path, r.latitude, r.longitude, r.location_text, r.status, r.created_at, r.updated_at, u.name as reporter_name,
        f.rating, f.comment
        FROM reports r
        JOIN users u ON r.user_id = u.id
        LEFT JOIN feedback f ON r.id = f.report_id
        $whereClause
        ORDER BY r.created_at DESC";

    if (count($params) > 0) {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
        $result = $conn->query($sql);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Unauthorized role']);
    exit;
}

if ($result) {
    $reports = [];
    while ($row = $result->fetch_assoc()) {
        $reports[] = $row;
    }
    echo json_encode(['success' => true, 'reports' => $reports]);
} else {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
}
?>
