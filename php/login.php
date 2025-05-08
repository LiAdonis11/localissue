<?php
session_start();
header('Content-Type: application/json');
include 'db.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        throw new Exception("Invalid request method");
    }

    if (!isset($_POST['email']) || !isset($_POST['password'])) {
        http_response_code(400);
        throw new Exception("Email and password are required");
    }

    $email = trim($_POST['email']);
    $password = $_POST['password'];

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        throw new Exception("Invalid email format");
    }

    $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
    if (!$stmt) {
        http_response_code(500);
        throw new Exception("Database error");
    }

    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        http_response_code(401);
        throw new Exception("Invalid credentials");
    }

    $user = $result->fetch_assoc();
    
    if (!password_verify($password, $user['password'])) {
        http_response_code(401);
        throw new Exception("Invalid credentials");
    }

    // Check if password needs rehashing
    if (password_needs_rehash($user['password'], PASSWORD_DEFAULT)) {
        $newHash = password_hash($password, PASSWORD_DEFAULT);
        $conn->query("UPDATE users SET password = '$newHash' WHERE id = {$user['id']}");
    }

    $_SESSION['user'] = [
        'id' => $user['id'],
        'unique_id' => $user['unique_id'],
        'name' => $user['name'],
        'role' => $user['role'],
        'ip' => $_SERVER['REMOTE_ADDR']
    ];

    echo json_encode([
        "success" => true,
        "message" => "Login successful",
        "user" => $_SESSION['user']
    ]);

} catch (Exception $e) {
    error_log($e->getMessage());
    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
} finally {
    $stmt->close();
    $conn->close();
}
?>