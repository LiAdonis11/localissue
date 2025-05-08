<?php
// Continue from previous part

// Add content page
$pdf->AddPage();

// Query monthly summary
$summarySql = "
    SELECT 
        DATE_FORMAT(created_at, '%Y-%m') AS month,
        COUNT(*) AS total_reports,
        SUM(CASE WHEN type = 'Garbage' THEN 1 ELSE 0 END) AS garbage,
        SUM(CASE WHEN type = 'Road Damage' THEN 1 ELSE 0 END) AS road_damage,
        SUM(CASE WHEN type = 'Flooding' THEN 1 ELSE 0 END) AS flooding,
        SUM(CASE WHEN type = 'Other' THEN 1 ELSE 0 END) AS other
    FROM reports
    WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH)
    GROUP BY month
    ORDER BY month DESC
    LIMIT 1
";
$summaryResult = $conn->query($summarySql);
if (!$summaryResult) {
    http_response_code(500);
    echo "Failed to fetch summary: " . $conn->error;
    exit;
}
$summary = $summaryResult->fetch_assoc();

// Display summary with descriptive sentences
$pdf->SetFont('times', '', 12);
$monthText = $summary['month'] ?? date('Y-m');
$pdf->MultiCell(0, 0, "For the month of $monthText, a total of " . ($summary['total_reports'] ?? 0) . " reports were submitted by residents. The breakdown of reported issues is as follows:", 0, 'J', 0, 1, '', '', true, 0, false, true, 0, 1.5, false);
$pdf->Ln(5);

$pdf->SetFont('times', '', 11);
$pdf->Cell(0, 6, "- Garbage issues: " . ($summary['garbage'] ?? 0), 0, 1);
$pdf->Cell(0, 6, "- Road damage reports: " . ($summary['road_damage'] ?? 0), 0, 1);
$pdf->Cell(0, 6, "- Flooding incidents: " . ($summary['flooding'] ?? 0), 0, 1);
$pdf->Cell(0, 6, "- Other types of reports: " . ($summary['other'] ?? 0), 0, 1);
$pdf->Ln(8);

// Query all reports for the month
$reportsSql = "
    SELECT r.id, r.title, r.type, r.status, r.created_at, u.name AS resident_name
    FROM reports r
    JOIN users u ON r.user_id = u.id
    WHERE r.created_at >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH)
    ORDER BY r.created_at DESC
";
$reportsResult = $conn->query($reportsSql);
if (!$reportsResult) {
    http_response_code(500);
    echo "Failed to fetch reports: " . $conn->error;
    exit;
}

// Table header with styling
$pdf->SetFillColor(0, 51, 102);
$pdf->SetTextColor(255, 255, 255);
$pdf->SetFont('times', 'B', 14);
$pdf->Cell(20, 10, 'Report ID', 1, 0, 'C', 1);
$pdf->Cell(50, 10, 'Resident', 1, 0, 'C', 1);
$pdf->Cell(40, 10, 'Type', 1, 0, 'C', 1);
$pdf->Cell(40, 10, 'Date Reported', 1, 0, 'C', 1);
$pdf->Cell(30, 10, 'Status', 1, 1, 'C', 1);

// Table rows with alternating colors
$pdf->SetFillColor(224, 235, 255);
$pdf->SetTextColor(0, 0, 0);
$pdf->SetFont('times', '', 12);
$fill = 0;
while ($row = $reportsResult->fetch_assoc()) {
    $pdf->Cell(20, 9, $row['id'], 'LR', 0, 'C', $fill);
    $pdf->Cell(50, 9, $row['resident_name'], 'LR', 0, 'L', $fill);
    $pdf->Cell(40, 9, $row['type'], 'LR', 0, 'L', $fill);
    $pdf->Cell(40, 9, date('Y-m-d', strtotime($row['created_at'])), 'LR', 0, 'C', $fill);
    $pdf->Cell(30, 9, $row['status'], 'LR', 1, 'C', $fill);
    $fill = !$fill;
}
// Closing line
$pdf->Cell(array_sum([20,50,40,40,30]), 0, '', 'T');

// Output PDF inline to browser
$pdf->Output('Monthly_Report_' . date('Ymd') . '.pdf', 'I');
?>
