<?php
ob_start();
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user']['id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once 'db.php'; // Include your database connection

// Check user role for permission
$role = $_SESSION['user']['role'];
if (!in_array($role, ['captain', 'official', 'resident'])) {
    echo json_encode(['success' => false, 'message' => 'Insufficient permissions']);
    exit;
}

try {
    // Include TCPDF library
    require_once('TCPDF-main/tcpdf.php'); // Adjust path if needed

    // Extend TCPDF to create custom Header and Footer
    class MYPDF extends TCPDF {
        // Page header
        public function Header() {
            // Colored header background
            $this->SetFillColor(0, 51, 102); // Dark blue
            $this->Rect(0, 0, $this->getPageWidth(), 30, 'F');

            // Logo
            $logoFile = __DIR__ . '/../frontend/assets/logo.jpg';
            if (file_exists($logoFile)) {
                $this->Image($logoFile, 15, 5, 25, 25, '', '', '', false, 300, '', false, false, 0);
            }

            // Title
            $this->SetFont('times', 'B', 18);
            $this->SetTextColor(255, 255, 255);
            $this->SetXY(50, 10);
            $this->Cell(0, 15, 'Brgy. Sawat, Tagudin, Ilocos Sur', 0, false, 'L', 0, '', 0, false, 'M', 'M');
            $this->SetFont('times', '', 14);
            $this->SetXY(50, 25);
            $this->Cell(0, 10, 'Complete Reports Summary', 0, false, 'L', 0, '', 0, false, 'M', 'M');
            $this->SetTextColor(0, 0, 0);
        }

        // Page footer
        public function Footer() {
            $this->SetY(-15);
            $this->SetFont('times', 'I', 10);
            $this->SetTextColor(100, 100, 100);
            $this->Cell(0, 10, 'Page '.$this->getAliasNumPage().' of '.$this->getAliasNbPages(), 0, false, 'C', 0, '', 0, false, 'T', 'M');
        }
    }

    // Create new PDF document
    $pdf = new MYPDF('P', 'mm', 'A4', true, 'UTF-8', false);
    $pdf->SetCreator('Issue Tracker');
    $pdf->SetAuthor('Barangay Captain');
    $pdf->SetTitle('Complete Reports');
    $pdf->SetMargins(15, 35, 15);
    $pdf->SetAutoPageBreak(TRUE, 25);

    // Add title page
    $pdf->AddPage();
    $pdf->SetFont('times', 'B', 18);
    $pdf->Cell(0, 20, 'Complete Report', 0, 1, 'C');
    $pdf->SetFont('times', '', 12);
    $pdf->Ln(10);
    $pdf->MultiCell(0, 0, "This report provides a comprehensive summary of all issues reported in Brgy. Sawat, Tagudin, Ilocos Sur. It includes detailed statistics and a list of all reports submitted by residents. The purpose of this report is to assist the barangay officials in monitoring and addressing community concerns effectively.", 0, 'J', 0, 1, '', '', true, 0, false, true, 0, 1.5, false);
    $pdf->Ln(10);
    $pdf->SetFont('times', 'I', 11);
    $pdf->Cell(0, 10, 'Generated on: ' . date('F j, Y'), 0, 1, 'C');

    // Query total reports count
    $totalSql = "SELECT COUNT(*) AS total_reports FROM reports";
    $totalResult = $conn->query($totalSql);
    $totalData = $totalResult->fetch_assoc();

    // Query counts grouped by type
    $typeSql = "SELECT type, COUNT(*) AS count FROM reports GROUP BY type";
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

    // Display summary with descriptive sentences
    $pdf->SetFont('times', '', 12);
    $pdf->MultiCell(0, 0, "A total of " . ($totalData['total_reports'] ?? 0) . " reports have been submitted by residents. The breakdown of reported issues is as follows:", 0, 'J', 0, 1);
    $pdf->Ln(5);

    $pdf->SetFont('times', '', 11);
    $pdf->Cell(0, 6, "- Garbage issues: " . $counts['Garbage'], 0, 1);
    $pdf->Cell(0, 6, "- Road damage reports: " . $counts['Road Damage'], 0, 1);
    $pdf->Cell(0, 6, "- Flooding incidents: " . $counts['Flooding'], 0, 1);
    $pdf->Cell(0, 6, "- Other types of reports: " . $counts['Other'], 0, 1);
    $pdf->Cell(0, 6, "- Unknown types: " . $counts['Unknown'], 0, 1);
    $pdf->Ln(8);

    // Query all reports
    $reportsSql = "
        SELECT r.id, r.title, r.type, r.status, r.created_at, u.name AS resident_name
        FROM reports r
        JOIN users u ON r.user_id = u.id
        ORDER BY r.created_at DESC
    ";
    $reportsResult = $conn->query($reportsSql);

    // Table header with styling
    $pdf->SetFillColor(0, 51, 102);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont('times', 'B', 14);
    $pdf->Cell(25, 10, 'Report ID', 1, 0, 'C', 1);
    $pdf->Cell(45, 10, 'Resident', 1, 0, 'C', 1);
    $pdf->Cell(40, 10, 'Type', 1, 0, 'C', 1);
    $pdf->Cell(40, 10, 'Date Reported', 1, 0, 'C', 1);
    $pdf->Cell(30, 10, 'Status', 1, 1, 'C', 1);

    // Table rows with alternating colors
    $pdf->SetFillColor(224, 235, 255);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetFont('times', '', 12);
    $fill = 0;
    while ($row = $reportsResult->fetch_assoc()) {
        $pdf->Cell(25, 9, $row['id'], 'LR', 0, 'C', $fill);
        $pdf->Cell(45, 9, $row['resident_name'], 'LR', 0, 'L', $fill);
        $pdf->Cell(40, 9, $row['type'], 'LR', 0, 'L', $fill);
        $pdf->Cell(40, 9, date('Y-m-d', strtotime($row['created_at'])), 'LR', 0, 'C', $fill);
        $pdf->Cell(30, 9, $row['status'], 'LR', 1, 'C', $fill);
        $fill = !$fill;
    }
    // Closing line
    $pdf->Cell(array_sum([20,50,40,40,30]), 0, '', 'T');

    // Save PDF to file
    $filename = "Complete_Report_" . date('Ymd_His') . ".pdf";
    $filepath = __DIR__ . "/../reports/" . $filename;
    if (!is_dir(__DIR__ . "/../reports")) {
        mkdir(__DIR__ . "/../reports", 0755, true);
    }
    $pdf->Output($filepath, 'F');

    echo json_encode(['success' => true, 'message' => 'Complete report generated', 'report_file' => 'reports/' . $filename]);
    exit;
} catch (Exception $e) {
    error_log('PDF generation error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Failed to generate complete report']);
    exit;
}
?>
