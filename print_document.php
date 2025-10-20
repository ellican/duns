<?php
// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    die("Access Denied. Please log in.");
}

require 'db.php';
require_once 'fpdf/fpdf.php';

// Helper function for currency symbols
function getCurrencySymbol($currency_code) {
    $symbols = ['USD' => '$', 'EUR' => '€', 'GBP' => '£', 'RWF' => 'FRw'];
    return $symbols[$currency_code] ?? ($currency_code . ' ');
}

// A complete function to convert numbers to words in English
function numberToWords($number) {
    $hyphen      = '-';
    $conjunction = ' and ';
    $separator   = ', ';
    $negative    = 'negative ';
    $decimal     = ' point ';
    $dictionary  = array( 0 => 'zero', 1 => 'one', 2 => 'two', 3 => 'three', 4 => 'four', 5 => 'five', 6 => 'six', 7 => 'seven', 8 => 'eight', 9 => 'nine', 10 => 'ten', 11 => 'eleven', 12 => 'twelve', 13 => 'thirteen', 14 => 'fourteen', 15 => 'fifteen', 16 => 'sixteen', 17 => 'seventeen', 18 => 'eighteen', 19 => 'nineteen', 20 => 'twenty', 30 => 'thirty', 40 => 'forty', 50 => 'fifty', 60 => 'sixty', 70 => 'seventy', 80 => 'eighty', 90 => 'ninety', 100 => 'hundred', 1000 => 'thousand', 1000000 => 'million', 1000000000 => 'billion');
    if (!is_numeric($number)) { return false; }
    if ($number < 0) { return $negative . numberToWords(abs($number)); }
    $string = $fraction = null;
    if (strpos($number, '.') !== false) { list($number, $fraction) = explode('.', $number); }
    switch (true) {
        case $number < 21: $string = $dictionary[$number]; break;
        case $number < 100: $tens   = ((int) ($number / 10)) * 10; $units  = $number % 10; $string = $dictionary[$tens]; if ($units) { $string .= $hyphen . $dictionary[$units]; } break;
        case $number < 1000: $hundreds  = (int) floor($number / 100); $remainder = $remainder = ((int) $number) % 100;$string = $dictionary[$hundreds] . ' ' . $dictionary[100]; if ($remainder) { $string .= $conjunction . numberToWords($remainder); } break;
        default: $baseUnit = pow(1000, floor(log($number, 1000))); $numBaseUnits = (int) ($number / $baseUnit); $remainder = $number % $baseUnit; $string = numberToWords($numBaseUnits) . ' ' . $dictionary[$baseUnit]; if ($remainder) { $string .= $remainder < 100 ? $conjunction : $separator; $string .= numberToWords($remainder); } break;
    }
    return $string;
}

// Professional PDF Class for Client Documents
class ClientDocumentPDF extends FPDF
{
    private $primaryColor = [0, 113, 206]; // Feza Logistics Blue: #0071ce
    private $secondaryColor = [73, 80, 87];  // Dark Gray for text: #495057
    private $borderColor = [222, 226, 230]; // Light gray for borders: #dee2e6

    function Header() {
        // Company logo integration with improved error handling - positioned on the right
        $logoUrl = 'https://www.fezalogistics.com/wp-content/uploads/2025/06/SQUARE-SIZEXX-FEZA-LOGO.png';
        $logoPath = '/tmp/feza_logo.png';
        
        // Try to download and cache logo with better error handling
        $logoLoaded = false;
        
        // Check if logo is already cached
        if (file_exists($logoPath) && filesize($logoPath) > 0) {
            $logoLoaded = true;
        } else {
            // Attempt to download logo
            $context = stream_context_create([
                'http' => [
                    'timeout' => 10, // 10 second timeout
                    'user_agent' => 'Mozilla/5.0 (compatible; FPDF Logo Fetcher)'
                ]
            ]);
            
            $logoContent = @file_get_contents($logoUrl, false, $context);
            if ($logoContent !== false && strlen($logoContent) > 0) {
                // Ensure tmp directory is writable
                if (is_writable(dirname($logoPath))) {
                    if (@file_put_contents($logoPath, $logoContent) !== false) {
                        $logoLoaded = true;
                    }
                }
            }
        }

        // Left side: Company information
        $this->SetFont('Arial', 'B', 12);
        $this->SetTextColor($this->primaryColor[0], $this->primaryColor[1], $this->primaryColor[2]);
        $this->SetXY(10, 12);
        $this->Cell(0, 6, 'FEZA LOGISTICS LTD', 0, 1);
        
        $this->SetFont('Arial', '', 9);
        $this->SetTextColor($this->secondaryColor[0], $this->secondaryColor[1], $this->secondaryColor[2]);
        $this->SetX(10);
        $this->Cell(0, 4, 'KN 5 Rd, KG 16 AVe 31, Kigali International Airport, Rwanda', 0, 1);
        $this->SetX(10);
        $this->Cell(0, 4, 'TIN: 121933433 | Phone: (+250) 788 616 117', 0, 1);
        $this->SetX(10);
        $this->Cell(0, 4, 'Email: info@fezalogistics.com | Web: www.fezalogistics.com', 0, 1);

        // Right side: Logo positioned on the right
        if ($logoLoaded && file_exists($logoPath)) {
            try {
                // Position logo on the right side of the header
                $this->Image($logoPath, 150, 10, 50);
            } catch (Exception $e) {
                // If image loading fails, fall back to text on the right
                $this->SetXY(150, 12);
                $this->SetFont('Arial', 'B', 10);
                $this->SetTextColor($this->primaryColor[0], $this->primaryColor[1], $this->primaryColor[2]);
                $this->Cell(50, 10, 'FEZA LOGISTICS', 0, 0, 'R');
            }
        } else {
            // Fallback: show company name on the right if logo fails to load
            $this->SetXY(150, 12);
            $this->SetFont('Arial', 'B', 10);
            $this->SetTextColor($this->primaryColor[0], $this->primaryColor[1], $this->primaryColor[2]);
            $this->Cell(50, 10, 'FEZA LOGISTICS', 0, 0, 'R');
        }
        
        $this->Ln(15);
    }

    function Footer() {
        $this->SetY(-25);
        $this->SetFont('Arial', 'I', 8);
        $this->SetTextColor($this->secondaryColor[0], $this->secondaryColor[1], $this->secondaryColor[2]);
        $this->Cell(0, 5, 'Generated on ' . date('F j, Y \a\t g:i A'), 0, 1, 'C');
        $this->Cell(0, 5, 'Thank you for choosing Feza Logistics', 0, 0, 'C');
    }
}

// Input validation and sanitization
$clientId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$docType = isset($_GET['type']) ? trim($_GET['type']) : 'invoice';
$tin = isset($_GET['tin']) ? htmlspecialchars(trim($_GET['tin'])) : 'N/A';

// Validate input parameters
if ($clientId === 0) {
    die("Error: Invalid Client ID provided.");
}

// Validate document type
$allowedDocTypes = ['invoice', 'receipt'];
if (!in_array($docType, $allowedDocTypes)) {
    die("Error: Invalid document type. Only 'invoice' or 'receipt' are allowed.");
}

try {
    // Fetch client data using PDO
    $stmt = $pdo->prepare("SELECT * FROM clients WHERE id = :id");
    $stmt->bindParam(':id', $clientId, PDO::PARAM_INT);
    $stmt->execute();
    $client = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$client) {
        die("Error: Client not found with ID: " . $clientId);
    }
} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}

// Check if receipt can be generated (paid amount > 0)
if ($docType === 'receipt' && (float)$client['paid_amount'] <= 0) {
    die("Error: A receipt cannot be generated because no payment has been recorded for this client.");
}

try {
    // Calculate amounts
    $documentTitle = ($docType === 'invoice') ? 'INVOICE' : 'PAYMENT RECEIPT';
    $amount = (float)$client['amount'];
    $paidAmount = (float)$client['paid_amount'];
    $dueAmount = $amount - $paidAmount;

    // Amount for words conversion
    $amountForWords = ($docType === 'invoice') ? $amount : $paidAmount;
    $amountInWords = ucwords(numberToWords($amountForWords));
    $currencySymbol = getCurrencySymbol($client['currency']);

    // Create PDF
    $pdf = new ClientDocumentPDF();
    $pdf->AddPage();

    // Document title - centered below header
    $pdf->Ln(5); // Add some space after header
    $pdf->SetFont('Arial', 'B', 18);
    $pdf->SetTextColor(0, 113, 206);
    $pdf->Cell(0, 15, $documentTitle, 0, 1, 'C');
    $pdf->Ln(5);

// Document info and client info side by side
$pdf->SetFont('Arial', 'B', 10);
$pdf->SetTextColor(73, 80, 87);
$pdf->Cell(95, 7, 'Document Information', 0, 0);
$pdf->Cell(95, 7, 'Client Information', 0, 1);
$pdf->SetFont('Arial', '', 9);

// Document info (left side)
$pdf->Cell(95, 5, 'Number: ' . $client['reg_no'], 0, 0);
$pdf->Cell(95, 5, 'Client: ' . $client['client_name'], 0, 1);
$pdf->Cell(95, 5, 'Date: ' . date("F j, Y", strtotime($client['date'])), 0, 0);
$pdf->Cell(95, 5, 'Phone: ' . $client['phone_number'], 0, 1);
if ($docType === 'invoice') {
    $pdf->Cell(95, 5, 'TIN: ' . $tin, 0, 0);
} else {
    $pdf->Cell(95, 5, 'Payment Date: ' . date("F j, Y"), 0, 0);
}
$pdf->Cell(95, 5, 'Service: ' . $client['service'], 0, 1);
$pdf->Ln(10);

// Services table
$pdf->SetFont('Arial', 'B', 10);
$pdf->SetFillColor(240, 240, 240);
$pdf->Cell(120, 8, 'Description', 1, 0, 'L', true);
$pdf->Cell(35, 8, 'Service', 1, 0, 'C', true);
$pdf->Cell(35, 8, 'Amount', 1, 1, 'R', true);

$pdf->SetFont('Arial', '', 9);
$pdf->Cell(120, 8, $client['service'] . ' (Reg No: ' . $client['reg_no'] . ')', 1, 0);
$pdf->Cell(35, 8, 'Clearing Services', 1, 0, 'C');
$displayAmount = ($docType === 'invoice') ? $amount : $paidAmount;
$pdf->Cell(35, 8, $currencySymbol . number_format($displayAmount, 2), 1, 1, 'R');
$pdf->Ln(5);

// Amount in words
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(0, 7, 'Amount in Words:', 0, 1);
$pdf->SetFont('Arial', '', 9);
$pdf->MultiCell(0, 5, $amountInWords . ' ' . $client['currency'] . ' only.');
$pdf->Ln(5);

// Payment details or totals
if ($docType === 'invoice') {
    // Payment instructions for invoice
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(0, 7, 'PAYMENT DETAILS', 0, 1);
    $pdf->SetFont('Arial', '', 9);
    $pdf->Cell(0, 5, 'ACCOUNT NAME: FEZA LOGISTICS', 0, 1);
    $pdf->Cell(0, 5, 'ACCOUNT NUMBER: 100155249662', 0, 1);
    $pdf->Cell(0, 5, 'BANK: BANK OF KIGALI', 0, 1);
    $pdf->Cell(0, 5, 'MOMO PAY: *182*8*1*52890#', 0, 1);
    $pdf->Ln(5);
    
    // Totals for invoice
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(155, 8, 'TOTAL AMOUNT:', 1, 0, 'R');
    $pdf->Cell(35, 8, $currencySymbol . number_format($amount, 2), 1, 1, 'R');
    $pdf->Cell(155, 8, 'AMOUNT PAID:', 1, 0, 'R');
    $pdf->Cell(35, 8, $currencySymbol . number_format($paidAmount, 2), 1, 1, 'R');
    $pdf->Cell(155, 8, 'AMOUNT DUE:', 1, 0, 'R');
    $pdf->Cell(35, 8, $currencySymbol . number_format($dueAmount, 2), 1, 1, 'R');
} else {
    // Receipt stamp area
    $pdf->SetFont('Arial', 'B', 16);
    $pdf->SetTextColor(0, 150, 0);
    $pdf->Cell(0, 15, 'PAID', 0, 1, 'C');
    $pdf->SetTextColor(73, 80, 87);
    
    // Total for receipt
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(155, 8, 'AMOUNT PAID:', 1, 0, 'R');
    $pdf->Cell(35, 8, $currencySymbol . number_format($paidAmount, 2), 1, 1, 'R');
}

    // Output PDF - Open in new tab instead of forcing download
    $filename = $documentTitle . '-' . $client['reg_no'] . '.pdf';
    $pdf->Output('I', $filename);
    
} catch (Exception $e) {
    die("PDF Generation Error: " . $e->getMessage());
}
?>