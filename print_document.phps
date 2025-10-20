<?php
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    die("Access Denied. Please log in.");
}

require 'db.php';

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
        case $number < 1000: $hundreds  = $number / 100; $remainder = $number % 100; $string = $dictionary[$hundreds] . ' ' . $dictionary[100]; if ($remainder) { $string .= $conjunction . numberToWords($remainder); } break;
        default: $baseUnit = pow(1000, floor(log($number, 1000))); $numBaseUnits = (int) ($number / $baseUnit); $remainder = $number % $baseUnit; $string = numberToWords($numBaseUnits) . ' ' . $dictionary[$baseUnit]; if ($remainder) { $string .= $remainder < 100 ? $conjunction : $separator; $string .= numberToWords($remainder); } break;
    }
    return $string;
}

$clientId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$docType = isset($_GET['type']) ? $_GET['type'] : 'invoice';
$tin = isset($_GET['tin']) ? htmlspecialchars($_GET['tin']) : 'N/A';

if ($clientId === 0) die("Invalid Client ID.");

$stmt = $conn->prepare("SELECT * FROM clients WHERE id = ?");
$stmt->bind_param("i", $clientId);
$stmt->execute();
$result = $stmt->get_result();
$client = $result->fetch_assoc();
$stmt->close();
$conn->close();

if (!$client) die("Client not found.");

if ($docType === 'receipt' && (float)$client['paid_amount'] <= 0) {
    echo "<div style='font-family: Arial, sans-serif; text-align: center; padding: 50px;'><h1>No Payment Made</h1><p>A receipt cannot be generated because no payment has been recorded for this client.</p><script>setTimeout(window.close, 5000);</script></div>";
    exit;
}

$documentTitle = ($docType === 'invoice') ? 'INVOICE' : 'PAYMENT RECEIPT';
$amount = (float)$client['amount'];
$paidAmount = (float)$client['paid_amount'];
$dueAmount = $amount - $paidAmount;

$amountForWords = ($docType === 'invoice') ? $amount : $paidAmount;
$amountInWords = ucwords(numberToWords($amountForWords));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= $documentTitle ?> - <?= htmlspecialchars($client['client_name']) ?></title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Lato:wght@400;700&display=swap');
        body { font-family: 'Lato', sans-serif; font-size: 12px; color: #333; }
        .page { width: 210mm; min-height: 297mm; padding: 20mm; margin: 10mm auto; border: 1px solid #d3d3d3; border-radius: 5px; background: white; box-shadow: 0 0 5px rgba(0, 0, 0, 0.1); position: relative; }
        .header { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid #333; padding-bottom: 10px; }
        .header .logo { max-width: 180px; height: auto; }
        .company-details h1 { margin: 0; color: #0071ce; font-size: 24px; }
        .info-section { display: flex; justify-content: space-between; margin-top: 30px; }
        .info-section div h3 { border-bottom: 1px solid #eee; padding-bottom: 5px; font-size: 14px; margin-bottom: 10px; }
        .items-table { width: 100%; border-collapse: collapse; margin-top: 30px; font-size: 11px; }
        .items-table th, .items-table td { border: 1px solid #ccc; padding: 8px; text-align: left; }
        .items-table th { background-color: #f2f2f2; font-weight: 700; }
        .text-right { text-align: right; }
        .summary-section { display: flex; margin-top: 20px; justify-content: space-between; align-items: flex-start; }
        .payment-details { width: 55%; font-size: 11px; }
        .payment-details p { margin: 2px 0; }
        .payment-details h3 { font-size: 13px; margin-bottom: 10px; }
        .stamp-container { margin: 20px 0; }
        .stamp-image { max-width: 140px; height: auto; }
        .totals { width: 40%; }
        .totals table { width: 100%; font-size: 12px; }
        .totals td { padding: 8px; border-bottom: 1px solid #eee; }
        .totals .grand-total td { font-weight: 700; font-size: 14px; background-color: #f2f2f2; }
        .footer { position: absolute; bottom: 20mm; width: calc(210mm - 40mm); text-align: center; font-size: 10px; color: #777; border-top: 1px solid #ccc; padding-top: 10px; }
        @media print { body, .page { margin: 0; box-shadow: none; border: none; } }
    </style>
</head>
<body>
    <div class="page">
        <div class="header">
            <div>
                <img src="https://www.fezalogistics.com/wp-content/uploads/2025/06/SQUARE-SIZEXX-FEZA-LOGO.png" alt="Company Logo" class="logo">
            </div>
            <div class="company-details" style="text-align: right;">
                <h1>FEZA LOGISTICS LTD</h1>
                <p>
                    KN 5 Rd, KG 16 AVe 31, Kigali International Airport, Rwanda<br>
                    TIN: 121933433 | Phone: (+250) 788 616 117<br>
                    Email: info@fezalogistics.com | Web: www.fezalogistics.com
                </p>
            </div>
        </div>

        <div class="info-section">
            <div>
                <h3>BILLED TO:</h3>
                <p>
                    <strong><?= htmlspecialchars($client['client_name']) ?></strong><br>
                    TIN: <?= $tin ?>
                </p>
            </div>
            <div style="text-align: right;">
                <h3><?= $documentTitle ?></h3>
                <p>
                    <strong>Number:</strong> <?= htmlspecialchars($client['reg_no']) ?><br>
                    <strong>Date:</strong> <?= date("F j, Y", strtotime($client['date'])) ?>
                </p>
            </div>
        </div>

        <table class="items-table">
             <thead>
                <tr>
                    <th>Description</th>
                    <th>Service</th>
                    <th class="text-right">Amount</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><?= htmlspecialchars($client['service']) ?> (Reg No: <?= htmlspecialchars($client['reg_no']) ?>)</td>
                    <td>Clearing Services</td>
                    <td class="text-right"><?= number_format(($docType === 'invoice' ? $amount : $paidAmount), 2) ?></td>
                </tr>
            </tbody>
        </table>

        <div class="summary-section">
            <div class="payment-details">
                <p><strong>Amount in Words:</strong> <?= htmlspecialchars($amountInWords) . " " . htmlspecialchars($client['currency']) ?>.</p>
                
                <?php if ($docType === 'receipt'): ?>
                <div class="stamp-container">
                    <img src="https://www.fezalogistics.com/wp-content/uploads/2025/06/stamp.png" alt="Paid Stamp" class="stamp-image">
                </div>
                <?php else: ?>
                <div style="margin-top: 20px;">
                    <h3>PAYMENT DETAILS</h3>
                    <hr style="margin: 10px 0;">
                    <p><strong>ACCOUNT NAME:</strong> FEZA LOGISTICS</p>
                    <p><strong>ACCOUNT NUMBER:</strong> 100155249662</p>
                    <p><strong>BANK:</strong> BANK OF KIGALI</p>
                    <p><strong>MOMO PAY:</strong> *182*8*1*52890#</p>
                </div>
                <?php endif; ?>
            </div>
            <div class="totals">
                <table>
                    <?php if ($docType === 'invoice'): ?>
                    <tr>
                        <td>TOTAL AMOUNT:</td>
                        <td class="text-right"><?= number_format($amount, 2) ?> <?= htmlspecialchars($client['currency']) ?></td>
                    </tr>
                     <tr style="background-color: #eaf6ff;">
                        <td>Amount Paid:</td>
                        <td class="text-right"><?= number_format($paidAmount, 2) ?> <?= htmlspecialchars($client['currency']) ?></td>
                    </tr>
                     <tr class="grand-total" style="background-color: #fff0f0;">
                        <td>AMOUNT DUE:</td>
                        <td class="text-right"><?= number_format($dueAmount, 2) ?> <?= htmlspecialchars($client['currency']) ?></td>
                    </tr>
                    <?php else: ?>
                    <tr class="grand-total">
                        <td>AMOUNT PAID:</td>
                        <td class="text-right"><?= number_format($paidAmount, 2) ?> <?= htmlspecialchars($client['currency']) ?></td>
                    </tr>
                    <tr>
                        <td>Remaining Balance:</td>
                        <td class="text-right"><?= number_format($dueAmount, 2) ?> <?= htmlspecialchars($client['currency']) ?></td>
                    </tr>
                    <?php endif; ?>
                </table>
            </div>
        </div>

        <div class="footer">
            <p>Thank you for your business! Feza Logistics Ltd - Your Trusted Partner in Logistics.</p>
        </div>
    </div>
    <script> window.onload = () => window.print(); </script>
</body>
</html>