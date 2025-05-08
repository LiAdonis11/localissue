<?php
include 'db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $report_id = $_POST['report_id'] ?? null;
    $rating = $_POST['rating'] ?? null;
    $comment = $_POST['comment'] ?? '';

    if (!$report_id || !$rating || $rating < 1 || $rating > 5) {
        echo json_encode(['success' => false, 'message' => 'Invalid input']);
        exit;
    }

    // Check if feedback already exists for this report
    $checkStmt = $conn->prepare("SELECT id FROM feedback WHERE report_id = ?");
    $checkStmt->bind_param("i", $report_id);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();

    if ($checkResult->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'This report has already been rated.']);
        exit;
    }

    $stmt = $conn->prepare("INSERT INTO feedback (report_id, rating, comment) VALUES (?, ?, ?)");
    $stmt->bind_param("iis", $report_id, $rating, $comment);

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Feedback submitted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $stmt->error]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>
