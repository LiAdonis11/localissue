<?php
ob_start();
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
include 'db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_unique_id = $_POST['user_id'] ?? null;
    $issue_type = $_POST['issue_type'] ?? null;
    $custom_issue = $_POST['custom_issue'] ?? null;
    $description = $_POST['description'] ?? null;
    $location = $_POST['location'] ?? null;
    $image_path = null;

    if (!$user_unique_id || !$issue_type || !$description || !$location) {
        echo json_encode(['success' => false, 'message' => 'Missing required fields']);
        exit;
    }

    // If issue_type is "Other" and custom_issue is provided, use custom_issue as issue_type
    if ($issue_type === 'Other' && $custom_issue && trim($custom_issue) !== '') {
        $issue_type = trim($custom_issue);
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

    // Handle image upload if exists
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../uploads/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        $file_tmp = $_FILES['image']['tmp_name'];
        $file_name = basename($_FILES['image']['name']);
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        $allowed_ext = ['jpg', 'jpeg', 'png', 'gif'];

        if (!in_array($file_ext, $allowed_ext)) {
            echo json_encode(['success' => false, 'message' => 'Invalid image format']);
            exit;
        }

        $new_file_name = uniqid('IMG_') . '.' . $file_ext;
        $target_file = $upload_dir . $new_file_name;

        if (move_uploaded_file($file_tmp, $target_file)) {
            $image_path = 'uploads/' . $new_file_name;
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to upload image']);
            exit;
        }
    }

    // Insert report into database
    $stmt = $conn->prepare("INSERT INTO reports (user_id, title, description, type, image_path, latitude, longitude, location_text, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Pending')");
    // For title, use issue_type + first 30 chars of description
    $title = $issue_type . ' - ' . substr($description, 0, 30);
    // Set latitude and longitude to null, store location text in new column
    $latitude = null;
    $longitude = null;
    $location_text = $location;

$stmt->bind_param("issssdds", $user_id, $title, $description, $issue_type, $image_path, $latitude, $longitude, $location_text);

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Report submitted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $stmt->error]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
ob_end_flush();
