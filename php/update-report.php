<?php
// Disable display errors to browser, enable error logging to file
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/php-error.log');

error_reporting(E_ALL);
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user']['id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once 'db.php'; // Include your database connection

// Clear any previous output buffer to avoid corrupting JSON response
if (ob_get_length()) {
    ob_end_clean();
}

// Get the JSON input
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['id'], $data['title'], $data['description'], $data['status'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid input']);
    exit;
}

$id = $data['id'];
$title = $data['title'];
$description = $data['description'];
$status = $data['status'];

// Get current status before update
$stmtCurrent = $conn->prepare("SELECT status FROM reports WHERE id = ?");
$stmtCurrent->bind_param("i", $id);
$stmtCurrent->execute();
$resultCurrent = $stmtCurrent->get_result();
if ($resultCurrent->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Report not found']);
    exit;
}
$currentReport = $resultCurrent->fetch_assoc();
$currentStatus = $currentReport['status'];

// Update the report in the database
$sql = "UPDATE reports SET title = ?, description = ?, status = ?, updated_at = NOW() WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param('sssi', $title, $description, $status, $id);

if ($stmt->execute()) {
    // If status changed to Resolved, generate PDF report
    if ($currentStatus !== 'Resolved' && $status === 'Resolved') {
        $role = $_SESSION['user']['role'];
        if ($role === 'captain' || $role === 'official') {
            try {
                // Include TCPDF library
                require_once('TCPDF-main/tcpdf.php'); // Adjusted path to actual TCPDF location

                // Create new PDF document
                $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
                $pdf->SetCreator('Issue Tracker');
                $pdf->SetAuthor('Barangay Captain');
                $pdf->SetTitle('Monthly Report');
                $pdf->SetMargins(15, 20, 15);
                $pdf->SetAutoPageBreak(TRUE, 20);
                $pdf->AddPage();

                // Barangay name header and logo
                $barangayName = "Brgy. Sawat, Tagudin, Ilocos Sur";
                $logoFile = __DIR__ . '/../frontend/assets/logo.jpg'; // Adjust path to logo image

                if (file_exists($logoFile)) {
                    $pdf->Image($logoFile, 15, 10, 30, 30, '', '', '', false, 300, '', false, false, 0);
                    $pdf->SetXY(50, 15);
                } else {
                    $pdf->SetXY(15, 15);
                }

                $pdf->SetFont('helvetica', 'B', 16);
                $pdf->Cell(0, 10, $barangayName, 0, 1, 'C');
                $pdf->SetFont('helvetica', '', 12);
                $pdf->Cell(0, 8, 'Monthly Report Summary', 0, 1, 'C');
                $pdf->Ln(5);

            // Query total reports count for the month
            $totalSql = "
                SELECT COUNT(*) AS total_reports,
                       DATE_FORMAT(created_at, '%Y-%m') AS month
                FROM reports
                WHERE created_at BETWEEN DATE_FORMAT(CURDATE() - INTERVAL 1 MONTH, '%Y-%m-01 00:00:00')
                  AND LAST_DAY(CURDATE() - INTERVAL 1 MONTH) + INTERVAL 1 DAY - INTERVAL 1 SECOND
            ";
            $totalResult = $conn->query($totalSql);
            $totalData = $totalResult->fetch_assoc();

            // Query counts grouped by type for the month
            $typeSql = "
                SELECT type, COUNT(*) AS count
                FROM reports
                WHERE created_at BETWEEN DATE_FORMAT(CURDATE() - INTERVAL 1 MONTH, '%Y-%m-01 00:00:00')
                  AND LAST_DAY(CURDATE() - INTERVAL 1 MONTH) + INTERVAL 1 DAY - INTERVAL 1 SECOND
                GROUP BY type
            ";
            $typeResult = $conn->query($typeSql);

            // Initialize counts
            $counts = [
                'Garbage' => 0,
                'Road Damage' => 0,
                'Flooding' => 0,
                'Other' => 0,
                'Unknown' => 0
            ];

            while ($row = $typeResult->fetch_assoc()) {
                $type = $row['type'];
                $count = (int)$row['count'];
                if (array_key_exists($type, $counts)) {
                    $counts[$type] = $count;
                } else {
                    $counts['Unknown'] += $count;
                }
            }

            // Debug logging for diagnosis
            error_log('Monthly Summary Total Reports: ' . ($totalData['total_reports'] ?? 'N/A'));
            error_log('Monthly Summary Counts: ' . print_r($counts, true));

            // Display summary
            $pdf->SetFont('helvetica', 'B', 12);
            $pdf->Cell(0, 8, 'Summary for ' . ($totalData['month'] ?? date('Y-m')), 0, 1);
            $pdf->SetFont('helvetica', '', 11);
            $pdf->Cell(60, 6, 'Total Reports: ' . ($totalData['total_reports'] ?? 0), 0, 1);
            $pdf->Cell(60, 6, 'Garbage: ' . $counts['Garbage'], 0, 1);
            $pdf->Cell(60, 6, 'Road Damage: ' . $counts['Road Damage'], 0, 1);
            $pdf->Cell(60, 6, 'Flooding: ' . $counts['Flooding'], 0, 1);
            $pdf->Cell(60, 6, 'Other: ' . $counts['Other'], 0, 1);
            $pdf->Ln(8);

                // Query all reports for the month
                $reportsSql = "
                    SELECT r.id, r.title, r.type, r.status, r.created_at, u.name AS resident_name
                    FROM reports r
                    JOIN users u ON r.user_id = u.id
                    WHERE r.created_at BETWEEN DATE_FORMAT(CURDATE() - INTERVAL 1 MONTH, '%Y-%m-01 00:00:00')
                      AND LAST_DAY(CURDATE() - INTERVAL 1 MONTH) + INTERVAL 1 DAY - INTERVAL 1 SECOND
                    ORDER BY r.created_at DESC
                ";
                $reportsResult = $conn->query($reportsSql);

                // Table header
                $pdf->SetFont('helvetica', 'B', 12);
                $pdf->Cell(20, 7, 'Report ID', 1);
                $pdf->Cell(50, 7, 'Resident', 1);
                $pdf->Cell(40, 7, 'Type', 1);
                $pdf->Cell(40, 7, 'Date Reported', 1);
                $pdf->Cell(30, 7, 'Status', 1);
                $pdf->Ln();

                // Table rows
                $pdf->SetFont('helvetica', '', 11);
                while ($row = $reportsResult->fetch_assoc()) {
                    $pdf->Cell(20, 6, $row['id'], 1);
                    $pdf->Cell(50, 6, $row['resident_name'], 1);
                    $pdf->Cell(40, 6, $row['type'], 1);
                    $pdf->Cell(40, 6, date('Y-m-d', strtotime($row['created_at'])), 1);
                    $pdf->Cell(30, 6, $row['status'], 1);
                    $pdf->Ln();
                }

                // Save PDF to file
                $filename = "Monthly_Report_" . date('Ymd_His') . ".pdf";
                $filepath = __DIR__ . "/../reports/" . $filename;
                if (!is_dir(__DIR__ . "/../reports")) {
                    mkdir(__DIR__ . "/../reports", 0755, true);
                }
                $pdf->Output($filepath, 'F');

                echo json_encode(['success' => true, 'message' => 'Report updated and PDF report generated', 'report_file' => 'reports/' . $filename]);
                exit;
            } catch (Exception $e) {
                error_log('PDF generation error: ' . $e->getMessage());
                echo json_encode(['success' => false, 'message' => 'Failed to generate PDF report']);
                exit;
            }
        } else {
            // User role not authorized to generate report
            echo json_encode(['success' => true, 'message' => 'Report updated. Report generation skipped due to insufficient permissions']);
            exit;
        }
    } else {
        // Status not changed to Resolved, just update success
        echo json_encode(['success' => true, 'message' => 'Report updated']);
        exit;
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to update report']);
    exit;
}
?>
