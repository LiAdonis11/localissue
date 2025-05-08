<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
require 'db.php';
header('Content-Type: application/json');

// Authorization check
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'captain') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized access']);
    exit;
}

try {
    $metrics = [];

    // 1. Total and resolved report counts
    $result = $conn->query("SELECT COUNT(*) FROM reports");
    if (!$result) {
        error_log('Query error (total_reports): ' . $conn->error);
        http_response_code(500);
        echo json_encode(['error' => 'Database query error for total reports']);
        exit;
    }
    $metrics['total_reports'] = $result->fetch_row()[0];

    $result = $conn->query("SELECT COUNT(*) FROM reports WHERE status = 'Resolved'");
    if (!$result) {
        error_log('Query error (resolved_reports): ' . $conn->error);
        http_response_code(500);
        echo json_encode(['error' => 'Database query error for resolved reports']);
        exit;
    }
    $metrics['resolved_reports'] = $result->fetch_row()[0];

    // 1a. Counts by status
    $statusCountsResult = $conn->query("
        SELECT status, COUNT(*) AS count
        FROM reports
        GROUP BY status
    ");
    if (!$statusCountsResult) {
        error_log('Query error (status_counts): ' . $conn->error);
        http_response_code(500);
        echo json_encode(['error' => 'Database query error for status counts']);
        exit;
    }
    $statusCounts = $statusCountsResult->fetch_all(MYSQLI_ASSOC);
    $metrics['status_counts'] = [
        'Pending' => 0,
        'In Progress' => 0,
        'Resolved' => 0
    ];
    foreach ($statusCounts as $row) {
        $metrics['status_counts'][$row['status']] = (int)$row['count'];
    }

    // 2. Monthly trends (using updated_at as resolution proxy)
    $stmt = $conn->prepare("
        SELECT 
            DATE_FORMAT(created_at, '%Y-%m') AS month,
            COUNT(*) AS count,
            AVG(
                CASE 
                    WHEN status = 'Resolved' 
                    THEN TIMESTAMPDIFF(HOUR, created_at, updated_at) 
                    ELSE NULL 
                END
            ) AS avg_resolve_hours,
            SUM(status = 'Resolved') AS resolved_count
        FROM reports
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
        GROUP BY DATE_FORMAT(created_at, '%Y-%m')
        ORDER BY month DESC
    ");
    if (!$stmt) {
        error_log('Prepare statement error (monthly_trends): ' . $conn->error);
        http_response_code(500);
        echo json_encode(['error' => 'Failed to prepare monthly trends query']);
        exit;
    }
    if (!$stmt->execute()) {
        error_log('Execute statement error (monthly_trends): ' . $stmt->error);
        http_response_code(500);
        echo json_encode(['error' => 'Failed to execute monthly trends query']);
        exit;
    }
    $metrics['monthly_trends'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // 2a. Overdue reports trend over last 6 months
    $stmt = $conn->prepare("
        SELECT 
            DATE_FORMAT(created_at, '%Y-%m') AS month,
            COUNT(*) AS overdue_count
        FROM reports
        WHERE TIMESTAMPDIFF(HOUR, created_at, NOW()) > 72
          AND status != 'Resolved'
          AND created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
        GROUP BY DATE_FORMAT(created_at, '%Y-%m')
        ORDER BY month DESC
    ");
    if (!$stmt) {
        error_log('Prepare statement error (overdue_trend): ' . $conn->error);
        http_response_code(500);
        echo json_encode(['error' => 'Failed to prepare overdue reports trend query']);
        exit;
    }
    if (!$stmt->execute()) {
        error_log('Execute statement error (overdue_trend): ' . $stmt->error);
        http_response_code(500);
        echo json_encode(['error' => 'Failed to execute overdue reports trend query']);
        exit;
    }
    $metrics['overdue_trend'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // 3. Category analysis (using status-based calculations)
    $stmt = $conn->prepare("
        SELECT 
            type,
            COUNT(*) AS total,
            SUM(status = 'Resolved') AS resolved,
            AVG(
                CASE 
                    WHEN status = 'Resolved' 
                    THEN TIMESTAMPDIFF(HOUR, created_at, updated_at) 
                    ELSE NULL 
                END
            ) AS avg_resolve_time,
            SUM(
                TIMESTAMPDIFF(HOUR, created_at, NOW()) > 72 
                AND status != 'Resolved'
            ) AS overdue
        FROM reports
        GROUP BY type
    ");
    if (!$stmt) {
        error_log('Prepare statement error (category_analysis): ' . $conn->error);
        http_response_code(500);
        echo json_encode(['error' => 'Failed to prepare category analysis query']);
        exit;
    }
    if (!$stmt->execute()) {
        error_log('Execute statement error (category_analysis): ' . $stmt->error);
        http_response_code(500);
        echo json_encode(['error' => 'Failed to execute category analysis query']);
        exit;
    }
    $metrics['category_analysis'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // 4. Geographical distribution (handle string coordinates)
    // Removed as map feature is removed from captain dashboard
    $metrics['geo_distribution'] = [];

    // 5. Performance metrics (using updated_at for resolution time)
    $stmt = $conn->prepare("
        SELECT 
            COALESCE(AVG(TIMESTAMPDIFF(HOUR, created_at, updated_at)), 0) AS global_avg_resolve,
            COALESCE(MAX(TIMESTAMPDIFF(HOUR, created_at, updated_at)), 0) AS longest_resolve,
            COALESCE(COUNT(DISTINCT user_id), 0) AS active_users
        FROM reports
        WHERE status = 'Resolved'
    ");
    if (!$stmt) {
        error_log('Prepare statement error (performance_metrics): ' . $conn->error);
        http_response_code(500);
        echo json_encode(['error' => 'Failed to prepare performance metrics query']);
        exit;
    }
    if (!$stmt->execute()) {
        error_log('Execute statement error (performance_metrics): ' . $stmt->error);
        http_response_code(500);
        echo json_encode(['error' => 'Failed to execute performance metrics query']);
        exit;
    }
    $performance = $stmt->get_result()->fetch_assoc();
    $metrics['performance'] = $performance ?: [
        'global_avg_resolve' => 0,
        'longest_resolve' => 0,
        'active_users' => 0
    ];

    // 6. Reports resolved per user
    $stmt = $conn->prepare("
        SELECT u.id AS user_id, u.name, COUNT(r.id) AS resolved_count
        FROM users u
        LEFT JOIN reports r ON u.id = r.user_id AND r.status = 'Resolved'
        WHERE u.role = 'resident'
        GROUP BY u.id, u.name
        ORDER BY resolved_count DESC
        LIMIT 10
    ");
    if (!$stmt) {
        error_log('Prepare statement error (resolved_per_user): ' . $conn->error);
        http_response_code(500);
        echo json_encode(['error' => 'Failed to prepare reports resolved per user query']);
        exit;
    }
    if (!$stmt->execute()) {
        error_log('Execute statement error (resolved_per_user): ' . $stmt->error);
        http_response_code(500);
        echo json_encode(['error' => 'Failed to execute reports resolved per user query']);
        exit;
    }
    $metrics['resolved_per_user'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    echo json_encode(['success' => true, 'metrics' => $metrics]);

} catch (Exception $e) {
    error_log('get-report-analytics.php error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
}
?>
