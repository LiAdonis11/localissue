<?php
error_reporting(0);
header('Content-Type: application/json');
include 'db.php';

// Get and sanitize input
$name = trim($_POST['name'] ?? '');
$email = trim($_POST['email'] ?? '');
$password_raw = $_POST['password'] ?? '';
$role = $_POST['role'] ?? 'resident';

// Validate required fields
if (!$name || !$email || !$password_raw) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "All fields are required"]);
    exit;
}

// Validate email format
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Invalid email format"]);
    exit;
}

// Check password strength (min 8 chars)
if (strlen($password_raw) < 8) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Password must be at least 8 characters"]);
    exit;
}

try {
    // Check if email already exists
    $check_stmt = $conn->prepare("SELECT email FROM users WHERE email = ?");
    $check_stmt->bind_param("s", $email);
    $check_stmt->execute();
    $check_stmt->store_result();
    
    if ($check_stmt->num_rows > 0) {
        http_response_code(409);
        echo json_encode(["success" => false, "message" => "Email already registered"]);
        exit;
    }
    $check_stmt->close();

    // Generate unique ID with more entropy
    $unique_id = 'USR_' . bin2hex(random_bytes(8));
    $password = password_hash($password_raw, PASSWORD_DEFAULT);

    // Insert new user
    $insert_stmt = $conn->prepare("INSERT INTO users (unique_id, name, email, password, role) VALUES (?, ?, ?, ?, ?)");
    if (!$insert_stmt) {
        throw new Exception($conn->error);
    }
    
    $insert_stmt->bind_param("sssss", $unique_id, $name, $email, $password, $role);
    
    if ($insert_stmt->execute()) {
        http_response_code(201);
        echo json_encode([
            "success" => true,
            "message" => "Registration successful",
            "unique_id" => $unique_id
        ]);
    } else {
        throw new Exception($insert_stmt->error);
    }
    
    $insert_stmt->close();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Registration failed. Please try again later."
    ]);
    // Log the error for debugging: error_log($e->getMessage());
} finally {
    $conn->close();
}
?>