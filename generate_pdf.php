<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();
require_once 'db.php';
require_once 'fpdf/fpdf.php';

// --- Authenticate User ---
if (!isset($_SESSION['loggedin']) || !$_SESSION['loggedin']) {
    die('Access Denied. Please log in.');
}

$user_id = $_SESSION['user_id'];
$doc_type = $_GET['type'] ?? '';
$doc_id = intval($_GET['id'] ?? 0);

if (empty($doc_type) || $doc_id <= 0) {
    die('Invalid document request.');
}

// --- Helper function for currency symbols ---
function getCurrencySymbol($currency_code) {
    $symbols = ['USD' => '$', 'EUR' => '€', 'GBP' => '£', 'RWF' => 'FRw'];
    return $symbols[$currency_code] ?? ($currency_code . ' ');
}

// --- The Professional PDF Class (No changes needed here, it's already correct) ---
class ProfessionalPDF extends FPDF
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
            // Check local assets first
            if (file_exists('assets/logo.png')) {
                $logoLoaded = true;
                $logoPath = 'assets/logo.png';
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
        $this->SetY(-30);
        $this->SetFont('Arial', 'I', 8);
        $this->SetTextColor(150);
        $this->SetDrawColor($this->borderColor[0], $this->borderColor[1], $this->borderColor[2]);
        $this->SetLineWidth(0.2);
        $this->Line($this->GetX(), $this->GetY(), $this->GetPageWidth() - $this->GetX(), $this->GetY());
        $this->Ln(5);
        $this->Cell(0, 5, 'Thank you for your business!', 0, 1, 'C');
        $this->Cell(0, 5, 'Page ' . $this->PageNo() . ' of {nb}', 0, 0, 'C');
    }
    function DocTitle($title) {
        // Center the document title below the header
        $this->Ln(5); // Add some space after header
        $this->SetFont('Arial', 'B', 18);
        $this->SetTextColor($this->primaryColor[0], $this->primaryColor[1], $this->primaryColor[2]);
        $this->Cell(0, 15, strtoupper($title), 0, 1, 'C');
        $this->Ln(5);
    }
    function InfoBlock($billTo, $details) {
        $this->SetY(60);
        $this->SetFont('Arial', 'B', 10);
        $this->SetTextColor($this->secondaryColor[0], $this->secondaryColor[1], $this->secondaryColor[2]);
        $this->Cell(95, 7, 'BILL TO', 0, 0, 'L');
        $this->Cell(95, 7, 'DETAILS', 0, 1, 'R');
        $this->SetDrawColor($this->borderColor[0], $this->borderColor[1], $this->borderColor[2]);
        $this->Line(10, $this->GetY(), $this->GetPageWidth() - 10, $this->GetY());
        $this->Ln(2);
        $y_start = $this->GetY();
        $this->SetFont('Arial', '', 10);
        $this->MultiCell(95, 5, implode("\n", $billTo), 0, 'L');
        $this->SetY($y_start);
        foreach ($details as $detail) {
            $this->SetX(105);
            $this->SetFont('Arial', 'B', 10);
            $this->Cell(35, 5, $detail[0] . ':', 0, 0, 'R');
            $this->SetFont('Arial', '', 10);
            $this->Cell(60, 5, $detail[1], 0, 1, 'R');
        }
        $this->Ln(10);
    }
    function ItemTable($header, $data, $currencySymbol) {
        $this->SetFont('Arial', 'B', 10);
        $this->SetFillColor($this->primaryColor[0], $this->primaryColor[1], $this->primaryColor[2]);
        $this->SetTextColor(255);
        $this->SetDrawColor($this->primaryColor[0], $this->primaryColor[1], $this->primaryColor[2]);
        $this->SetLineWidth(0.3);
        $w = [100, 25, 35, 30];
        for ($i = 0; $i < count($header); $i++) { $this->Cell($w[$i], 9, $header[$i], 1, 0, 'C', true); }
        $this->Ln();
        $this->SetFillColor(245, 245, 245);
        $this->SetTextColor($this->secondaryColor[0], $this->secondaryColor[1], $this->secondaryColor[2]);
        $this->SetFont('');
        $fill = false;
        foreach ($data as $row) {
            $this->Cell($w[0], 8, $row[0], 'LR', 0, 'L', $fill);
            $this->Cell($w[1], 8, $row[1], 'LR', 0, 'C', $fill);
            $this->Cell($w[2], 8, $currencySymbol . number_format($row[2], 2), 'LR', 0, 'R', $fill);
            $this->Cell($w[3], 8, $currencySymbol . number_format($row[3], 2), 'LR', 0, 'R', $fill);
            $this->Ln();
            $fill = !$fill;
        }
        $this->Cell(array_sum($w), 0, '', 'T');
    }
    function TotalsBlock($totals, $currencySymbol) {
        $this->Ln(5);
        $this->SetX(120);
        $this->SetFont('Arial', '', 10);
        foreach ($totals as $total) {
            $this->SetX(120);
            $this->Cell(40, 7, $total[0] . ':', 0, 0, 'R');
            if(isset($total[2]) && $total[2] === 'bold') { $this->SetFont('', 'B'); }
            $this->Cell(40, 7, $currencySymbol . $total[1], 0, 1, 'R');
            $this->SetFont('');
        }
    }
}

// --- Main Logic ---
$pdf = new ProfessionalPDF();
$pdf->AliasNbPages();
$pdf->AddPage();

switch ($doc_type) {
    case 'quotation':
    case 'invoice':
        $table_name = $doc_type === 'quotation' ? 'quotations' : 'invoices';
        $items_table_name = $doc_type === 'quotation' ? 'quotation_items' : 'invoice_items';
        $id_column = $doc_type === 'quotation' ? 'quotation_id' : 'invoice_id';

        // **FIX**: Fetch the main document first, reliably.
        $stmt = $pdo->prepare("SELECT * FROM {$table_name} WHERE id = :id AND user_id = :user_id");
        $stmt->execute([':id' => $doc_id, ':user_id' => $user_id]);
        $doc = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$doc) {
            die(ucfirst($doc_type) . ' not found or you do not have permission to view it.');
        }

        // Then, fetch the line items.
        $item_stmt = $pdo->prepare("SELECT * FROM {$items_table_name} WHERE {$id_column} = :id");
        $item_stmt->execute([':id' => $doc_id]);
        $items = $item_stmt->fetchAll(PDO::FETCH_ASSOC);

        // --- Build PDF ---
        // Handle missing currency field gracefully
        $currency = $doc['currency'] ?? 'RWF';
        $currencySymbol = getCurrencySymbol($currency);
        $pdf->DocTitle($doc_type);

        $billTo = [$doc['customer_name']];
        if(!empty($doc['customer_address'])) $billTo[] = $doc['customer_address'];
        if(!empty($doc['customer_email'])) $billTo[] = $doc['customer_email'];
        
        $details = [];
        // **FIX**: Use the correct column names for each document type.
        if ($doc_type === 'quotation') {
            $details[] = ['Quotation Number', $doc['quote_number']];
            $details[] = ['Date', date('M d, Y', strtotime($doc['quote_date']))];
            $details[] = ['Expiry Date', date('M d, Y', strtotime($doc['expiry_date']))];
        } else { // Invoice
            $details[] = ['Invoice Number', $doc['invoice_number']];
            $details[] = ['Date', date('M d, Y', strtotime($doc['invoice_date']))];
            $details[] = ['Due Date', date('M d, Y', strtotime($doc['due_date']))];
        }
        
        $pdf->InfoBlock($billTo, $details);

        $table_header = ['Item Description', 'Quantity', 'Unit Price', 'Total'];
        $table_data = [];
        foreach ($items as $item) {
            $table_data[] = [$item['item_description'], $item['quantity'], $item['unit_price'], $item['total']];
        }
        $pdf->ItemTable($table_header, $table_data, $currencySymbol);

        $totals = [
            ['Subtotal', number_format($doc['subtotal'], 2)],
            ['Tax (' . $doc['tax_rate'] . '%)', number_format($doc['tax_amount'], 2)],
            ['Total', number_format($doc['total'], 2), 'bold']
        ];
        $pdf->TotalsBlock($totals, $currencySymbol);

        if(!empty($doc['notes'])) {
            $pdf->Ln(10);
            $pdf->SetFont('Arial', 'B', 10);
            $pdf->Cell(0, 7, 'Notes', 0, 1);
            $pdf->SetFont('', '');
            $pdf->MultiCell(0, 5, $doc['notes']);
        }
        
        $file_name_prefix = ucfirst($doc_type);
        $file_name_number = $doc_type === 'quotation' ? $doc['quote_number'] : $doc['invoice_number'];
        $file_name = "{$file_name_prefix}-{$file_name_number}.pdf";
        break;

    case 'receipt':
        // The receipt logic remains correct.
        $stmt = $pdo->prepare("SELECT r.*, i.invoice_number, i.currency, i.total as invoice_total, i.customer_name FROM receipts r JOIN invoices i ON r.invoice_id = i.id WHERE r.id = :id AND r.user_id = :user_id");
        $stmt->execute([':id' => $doc_id, ':user_id' => $user_id]);
        $doc = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$doc) die('Receipt not found.');
        
        $currencySymbol = getCurrencySymbol($doc['currency']);
        $pdf->DocTitle('Receipt');
        
        $billTo = [$doc['customer_name']];
        $details = [
            ['Receipt Number', $doc['receipt_number']],
            ['Payment Date', date('M d, Y', strtotime($doc['payment_date']))],
            ['Payment Method', $doc['payment_method']]
        ];
        $pdf->InfoBlock($billTo, $details);

        $pdf->SetFont('Arial', 'B', 11);
        $pdf->Cell(0, 10, 'Payment Details', 0, 1);
        $pdf->SetFont('Arial', '', 10);
        $pdf->Cell(95, 8, 'Original Invoice Number', 1, 0);
        $pdf->Cell(95, 8, $doc['invoice_number'], 1, 1, 'R');
        $pdf->Cell(95, 8, 'Amount Paid', 1, 0);
        $pdf->Cell(95, 8, $currencySymbol . number_format($doc['amount_paid'], 2), 1, 1, 'R');
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->SetFillColor(230, 247, 230);
        $pdf->Cell(95, 10, 'Invoice Total', 1, 0, 'L', true);
        $pdf->Cell(95, 10, $currencySymbol . number_format($doc['invoice_total'], 2), 1, 1, 'R', true);

        if(!empty($doc['notes'])) {
            $pdf->Ln(10);
            $pdf->SetFont('Arial', 'B', 10);
            $pdf->Cell(0, 7, 'Notes', 0, 1);
            $pdf->SetFont('', '');
            $pdf->MultiCell(0, 5, $doc['notes']);
        }
        $file_name = 'Receipt-' . $doc['receipt_number'] . '.pdf';
        break;

    default:
        die('Invalid document type provided.');
}

$pdf->Output('I', $file_name);
?>