<?php
// Include the database connection and FPDF library
include '../../../../connect.php';
require('lib/fpdf186/fpdf.php'); // Make sure this path is correct

session_start();

// Security check: ensure an admin is logged in
if (!isset($_SESSION['admin_id'])) {
    die("Access denied. You must be logged in as an admin.");
}

// Custom PDF class to create a Header
class PDF extends FPDF
{
    private $reportTitle = '';

    function setReportTitle($title) {
        $this->reportTitle = $title;
    }

    // Page header
    function Header()
    {
        $this->Image('../../../../images/PESO_Logo.png', 10, 6, 20);
        $this->SetFont('Arial', 'B', 15);
        $this->Cell(80);
        $this->Cell(30, 10, $this->reportTitle, 0, 0, 'C');
        $this->SetFont('Arial', '', 10);
        $this->Cell(80, 10, 'Generated on: ' . date('Y-m-d'), 0, 0, 'R');
        $this->Ln(20);
    }

    function NbLines($w, $txt)
    {
        $cw = &$this->CurrentFont['cw'];
        if ($w == 0) $w = $this->w - $this->rMargin - $this->x;
        $wmax = ($w - 2 * $this->cMargin) * 1000 / $this->FontSize;
        $s = str_replace("\r", '', $txt);
        $nb = strlen($s);
        if ($nb > 0 && $s[$nb - 1] == "\n") $nb--;
        $sep = -1; $i = 0; $j = 0; $l = 0; $nl = 1;
        while ($i < $nb) {
            $c = $s[$i];
            if ($c == "\n") {
                $i++; $sep = -1; $j = $i; $l = 0; $nl++;
                continue;
            }
            if ($c == ' ') $sep = $i;
            $l += $cw[$c];
            if ($l > $wmax) {
                if ($sep == -1) {
                    if ($i == $j) $i++;
                } else $i = $sep + 1;
                $sep = -1; $j = $i; $l = 0; $nl++;
            } else $i++;
        }
        return $nl;
    }
}

$reportType = $_GET['report'] ?? '';
$data = [];
$title = '';
$filename = 'report.pdf';

// --- MODIFIED START: Scholarship report now requires a specific ID ---
if ($reportType === 'scholarship') {
    if (!isset($_GET['id'])) {
        die("Error: Scholarship ID is required.");
    }
    $scholarshipId = intval($_GET['id']);

    $sql = "
        SELECT u.Fname, u.Lname, u.valid_id, a.documents, s.title 
        FROM user u 
        JOIN applications a ON u.user_id = a.user_id 
        JOIN scholarships s ON a.scholarship_id = s.scholarship_id 
        WHERE a.status = 'approved' AND s.status = 'active' AND s.scholarship_id = ?
        ORDER BY u.Lname ASC, u.Fname ASC
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $scholarshipId);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result->fetch_all(MYSQLI_ASSOC);

    if (empty($data)) {
        // Use a generic title if no data is found but still generate a PDF
        $title = 'Scholarship Applicants Documents';
    } else {
        // Use the title from the first record for the report
        $title = $data[0]['title'] . ' Report';
    }
    // Sanitize title for filename
    $safeTitle = preg_replace('/[^a-zA-Z0-9-]/', '', $data[0]['title'] ?? 'Scholarship');
    $filename = $safeTitle . '_awardees_report.pdf';

// --- MODIFIED END ---
} elseif ($reportType === 'spes') {
    // SPES logic remains unchanged
    $title = 'SPES Applicants Documents';
    $filename = 'spes_awardees_report.pdf';
    $activeBatchStartDate = null;
    $activeBatchSql = "SELECT start_date FROM spes_batches WHERE status = 'active' LIMIT 1";
    if ($activeBatchResult = $conn->query($activeBatchSql)) {
        if ($activeBatchRow = $activeBatchResult->fetch_assoc()) {
            $activeBatchStartDate = $activeBatchRow['start_date'];
        }
    }
    $sql = "
        SELECT u.Fname, u.Lname, sa.id_image_paths, sa.spes_documents_path 
        FROM user u JOIN spes_applications sa ON u.user_id = sa.user_id 
        WHERE sa.status = 'approved'";
    if ($activeBatchStartDate) {
        $sql .= " AND sa.created_at >= '" . $conn->real_escape_string($activeBatchStartDate) . "'";
    } else {
        $sql .= " AND 1=0";
    }
    $sql .= " ORDER BY u.Lname ASC, u.Fname ASC";
    $result = $conn->query($sql);
    $data = $result->fetch_all(MYSQLI_ASSOC);
} else {
    die("Invalid report type specified.");
}

// PDF Generation
$pdf = new PDF();
$pdf->setReportTitle($title);
$pdf->AddPage();
$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell(40, 10, 'Last Name', 1);
$pdf->Cell(40, 10, 'First Name', 1);
$pdf->Cell(80, 10, 'Documents', 1);
$pdf->Cell(30, 10, 'Status', 1);
$pdf->Ln();
$pdf->SetFont('Arial', '', 9);

foreach ($data as $row) {
    // ... (rest of the PDF generation logic is unchanged)
    $documentFiles = [];
    $status = 'Incomplete';
    if ($reportType === 'scholarship') {
        $validIdPaths = json_decode($row['valid_id'], true);
        if (is_array($validIdPaths)) {
            foreach($validIdPaths as $path) $documentFiles[] = basename($path);
        }
        $docPaths = json_decode($row['documents'], true);
        if (is_array($docPaths) && !empty($docPaths)) {
            foreach($docPaths as $path) $documentFiles[] = basename($path);
            $status = 'Complete';
        }
    } elseif ($reportType === 'spes') {
        $idPaths = json_decode($row['id_image_paths'], true);
        if (is_array($idPaths)) {
            foreach($idPaths as $path) $documentFiles[] = basename($path);
        }
        $reqPaths = json_decode($row['spes_documents_path'], true);
        if (is_array($reqPaths) && !empty($reqPaths)) {
            foreach($reqPaths as $path) $documentFiles[] = basename($path);
            $status = 'Complete';
        } elseif (!empty($row['spes_documents_path']) && !is_array($reqPaths)) {
             $documentFiles[] = basename($row['spes_documents_path']);
             $status = 'Complete';
        }
    }
    $docsString = !empty($documentFiles) ? implode(', ', $documentFiles) : 'None';
    $lineHeight = 6;
    $nbLines = $pdf->NbLines(80, $docsString);
    $cellHeight = $nbLines * $lineHeight;
    $yPos = $pdf->GetY();
    $pdf->Cell(40, $cellHeight, $row['Lname'], 1, 0, 'L');
    $pdf->Cell(40, $cellHeight, $row['Fname'], 1, 0, 'L');
    $xPos = $pdf->GetX();
    $pdf->MultiCell(80, $lineHeight, $docsString, 1, 'L');
    $pdf->SetXY($xPos + 80, $yPos); 
    $pdf->Cell(30, $cellHeight, $status, 1, 1, 'L');
}

$pdf->Output('D', $filename);
exit;
?>