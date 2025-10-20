<?php
require_once 'header.php';
require_once 'db.php';

$user_id = $_SESSION['user_id'];
$message = '';
$message_type = '';
$last_receipt_id = null; // ** NEW **

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ... (The entire PHP block for saving the receipt is the same, with one addition)
    $invoice_number = $_POST['invoice_number'] ?? ''; $amount_paid = $_POST['amount_paid'] ?? 0; $payment_date = $_POST['payment_date'] ?? date('Y-m-d'); $payment_method = $_POST['payment_method'] ?? '';
    if (empty($invoice_number) || empty($amount_paid) || empty($payment_method)) {
        $message = 'Invoice Number, Amount Paid, and Payment Method are required.'; $message_type = 'error';
    } else {
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("SELECT * FROM invoices WHERE invoice_number = :invoice_number AND user_id = :user_id");
            $stmt->execute([':invoice_number' => $invoice_number, ':user_id' => $user_id]);
            $invoice = $stmt->fetch();
            if (!$invoice) { throw new Exception("Invoice '{$invoice_number}' not found."); }
            $invoice_id = $invoice['id'];
            $receipt_number = 'RCPT-' . time();
            $rec_stmt = $pdo->prepare("INSERT INTO receipts (user_id, invoice_id, receipt_number, payment_date, payment_method, amount_paid) VALUES (:user_id, :invoice_id, :receipt_number, :payment_date, :payment_method, :amount_paid)");
            $rec_stmt->execute([':user_id' => $user_id, ':invoice_id' => $invoice_id, ':receipt_number' => $receipt_number, ':payment_date' => $payment_date, ':payment_method' => $payment_method, ':amount_paid' => $amount_paid]);
            $last_receipt_id = $pdo->lastInsertId(); // ** THE ONE ADDITION **
            $new_total_paid = $invoice['amount_paid'] + $amount_paid;
            $new_status = ($new_total_paid >= $invoice['total']) ? 'paid' : (($new_total_paid > 0) ? 'partially paid' : 'unpaid');
            $update_inv_stmt = $pdo->prepare("UPDATE invoices SET amount_paid = :amount_paid, status = :status WHERE id = :id");
            $update_inv_stmt->execute([':amount_paid' => $new_total_paid, ':status' => $new_status, ':id' => $invoice_id]);
            $pdo->commit();
            $message = "Receipt {$receipt_number} created and invoice {$invoice_number} updated.";
            $message_type = 'success';
        } catch (Exception $e) {
            $pdo->rollBack(); $message = 'Error creating receipt: ' . $e->getMessage(); $message_type = 'error';
        }
    }
}
?>

<title>Create Receipt - Feza Logistics</title>
<style>
    /* All CSS is the same */
    .content-wrapper { max-width: 600px; margin: 40px auto; padding: 0 20px; } .page-header h1 { font-size: 2rem; color: var(--text-color); margin-bottom: 20px; } .form-container { background: var(--white-color); padding: 30px; border-radius: 8px; box-shadow: 0 4px 20px rgba(0,0,0,0.05); } .form-group { margin-bottom: 20px; } label { display: block; margin-bottom: 8px; font-weight: 600; color: #555; font-size: 0.9rem; } input, select { width: 100%; padding: 12px; border: 1px solid var(--border-color); border-radius: 5px; box-sizing: border-box; font-size: 1rem; } .btn { padding: 12px 25px; background-color: var(--primary-color); color: white; border: none; border-radius: 5px; cursor: pointer; width: 100%; } .btn-pdf { background-color: var(--success-color); text-decoration: none; display: inline-block; margin-top: 10px; width: auto; } .message { padding: 15px; margin-bottom: 20px; border-radius: 5px; text-align: center; } .message.success { background-color: #d1e7dd; color: #0f5132; } .message.error { background-color: #f8d7da; color: #842029; }
</style>

<div class="content-wrapper">
    <div class="page-header"><h1>Create Receipt</h1></div>

    <?php if ($message): ?>
        <div class="message <?php echo $message_type; ?>">
            <?php echo htmlspecialchars($message); ?>
            <!-- ** NEW: PDF Download Button on Success ** -->
            <?php if ($message_type === 'success' && $last_receipt_id): ?>
                <br>
                <a href="generate_pdf.php?type=receipt&id=<?php echo $last_receipt_id; ?>" target="_blank" class="btn btn-pdf">Download Receipt PDF</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <!-- The form HTML is identical -->
    <form action="create_receipt.php" method="POST" class="form-container">
        <div class="form-group"><label for="invoice_number">Invoice Number</label><input type="text" id="invoice_number" name="invoice_number" placeholder="e.g., INV-168..." required></div>
        <div class="form-group"><label for="amount_paid">Amount Paid</label><input type="number" id="amount_paid" name="amount_paid" step="0.01" min="0.01" required></div>
        <div class="form-group"><label for="payment_date">Payment Date</label><input type="date" id="payment_date" name="payment_date" value="<?php echo date('Y-m-d'); ?>" required></div>
        <div class="form-group"><label for="payment_method">Payment Method</label><select id="payment_method" name="payment_method" required><option value="" disabled selected>Select a method...</option><option value="Bank Transfer">Bank Transfer</option><option value="Cash">Cash</option><option value="Credit Card">Credit Card</option><option value="Mobile Money">Mobile Money</option><option value="Other">Other</option></select></div>
        <button type="submit" class="btn">Save Receipt</button>
    </form>
</div>