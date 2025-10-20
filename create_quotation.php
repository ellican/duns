<?php
require_once 'header.php';
require_once 'db.php';

$user_id = $_SESSION['user_id'];
$message = '';
$message_type = '';
$last_quotation_id = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ... (All the data saving PHP logic is the same as before, no changes needed here)
    $customer_name = $_POST['customer_name'] ?? ''; $customer_address = $_POST['customer_address'] ?? ''; $customer_email = $_POST['customer_email'] ?? ''; $quote_date = $_POST['quote_date'] ?? date('Y-m-d'); $expiry_date = $_POST['expiry_date'] ?? date('Y-m-d', strtotime('+30 days')); $notes = $_POST['notes'] ?? ''; $item_descriptions = $_POST['item_description'] ?? []; $quantities = $_POST['quantity'] ?? []; $unit_prices = $_POST['unit_price'] ?? [];
    if (empty($customer_name) || empty($item_descriptions)) {
        $message = 'Customer name and at least one item are required.'; $message_type = 'error';
    } else {
        $pdo->beginTransaction();
        try {
            $quote_number = 'QUO-' . time(); 
            $currency = $_POST['currency'] ?? 'RWF';
            $subtotal = 0;
            for ($i = 0; $i < count($item_descriptions); $i++) { $subtotal += ($quantities[$i] * $unit_prices[$i]); }
            $tax_rate = floatval($_POST['tax_rate'] ?? 0); $tax_amount = $subtotal * ($tax_rate / 100); $total = $subtotal + $tax_amount;
            $stmt = $pdo->prepare("INSERT INTO quotations (user_id, quote_number, customer_name, customer_address, customer_email, quote_date, expiry_date, subtotal, tax_rate, tax_amount, total, currency, notes) VALUES (:user_id, :quote_number, :customer_name, :customer_address, :customer_email, :quote_date, :expiry_date, :subtotal, :tax_rate, :tax_amount, :total, :currency, :notes)");
            $stmt->execute([':user_id' => $user_id, ':quote_number' => $quote_number, ':customer_name' => $customer_name, ':customer_address' => $customer_address, ':customer_email' => $customer_email, ':quote_date' => $quote_date, ':expiry_date' => $expiry_date, ':subtotal' => $subtotal, ':tax_rate' => $tax_rate, ':tax_amount' => $tax_amount, ':total' => $total, ':currency' => $currency, ':notes' => $notes]);
            $last_quotation_id = $pdo->lastInsertId(); // ** NEW: Get the ID **
            $item_stmt = $pdo->prepare("INSERT INTO quotation_items (quotation_id, item_description, quantity, unit_price, total) VALUES (:quotation_id, :item_description, :quantity, :unit_price, :total)");
            for ($i = 0; $i < count($item_descriptions); $i++) {
                if (!empty($item_descriptions[$i])) {
                    $item_total = $quantities[$i] * $unit_prices[$i];
                    $item_stmt->execute([':quotation_id' => $last_quotation_id, ':item_description' => $item_descriptions[$i], ':quantity' => $quantities[$i], ':unit_price' => $unit_prices[$i], ':total' => $item_total]);
                }
            }
            $pdo->commit();
            $message = "Quotation {$quote_number} created successfully!"; $message_type = 'success';
        } catch (Exception $e) {
            $pdo->rollBack(); $message = 'Error creating quotation. Please try again.'; $message_type = 'error';
        }
    }
}
?>
<title>Create Quotation - Feza Logistics</title>
<style>
    /* All CSS is the same, no changes needed */
    .content-wrapper { max-width: 900px; margin: 40px auto; padding: 0 20px; } .page-header h1 { font-size: 2rem; color: var(--text-color); margin-bottom: 20px; } .form-container { background: var(--white-color); padding: 30px; border-radius: 8px; box-shadow: 0 4px 20px rgba(0,0,0,0.05); } .form-section { border-bottom: 1px solid var(--border-color); padding-bottom: 20px; margin-bottom: 20px; } .form-section:last-child { border-bottom: none; margin-bottom: 0; padding-bottom: 0; } .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; } label { display: block; margin-bottom: 8px; font-weight: 600; color: #555; font-size: 0.9rem; } input, textarea, select { width: 100%; padding: 10px; border: 1px solid var(--border-color); border-radius: 5px; box-sizing: border-box; font-size: 1rem; } .items-table { width: 100%; border-collapse: collapse; } .items-table th, .items-table td { padding: 12px; border: 1px solid var(--border-color); text-align: left; } .items-table th { background-color: #f8f9fa; } .items-table input { border: none; background: transparent; } .btn-remove { background: #dc3545; color: white; border: none; border-radius: 50%; width: 25px; height: 25px; cursor: pointer; } .btn-add { background-color: #28a745; color: white; padding: 8px 15px; border: none; border-radius: 5px; cursor: pointer; } .totals-section { display: flex; justify-content: flex-end; } .totals-table { width: 300px; } .btn { padding: 12px 25px; background-color: var(--primary-color); color: white; border: none; border-radius: 5px; cursor: pointer; } .btn-pdf { background-color: var(--success-color); text-decoration: none; display: inline-block; margin-left: 15px; } .message { padding: 15px; margin-bottom: 20px; border-radius: 5px; text-align: center; } .message.success { background-color: #d1e7dd; color: #0f5132; } .message.error { background-color: #f8d7da; color: #842029; }
</style>

<div class="content-wrapper">
    <div class="page-header"><h1>Create Quotation</h1></div>

    <?php if ($message): ?>
        <div class="message <?php echo $message_type; ?>">
            <?php echo htmlspecialchars($message); ?>
            <!-- ** NEW: PDF Download Button on Success ** -->
            <?php if ($message_type === 'success' && $last_quotation_id): ?>
                <a href="generate_pdf.php?type=quotation&id=<?php echo $last_quotation_id; ?>" target="_blank" class="btn btn-pdf">Download PDF</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <form action="create_quotation.php" method="POST" class="form-container">
        <!-- Customer Details -->
        <div class="form-section">
            <div class="grid-2">
                <div class="form-group"><label for="customer_name">Customer Name</label><input type="text" id="customer_name" name="customer_name" required></div>
                <div class="form-group"><label for="customer_email">Customer Email</label><input type="email" id="customer_email" name="customer_email"></div>
            </div>
            <div class="form-group"><label for="customer_address">Customer Address</label><textarea id="customer_address" name="customer_address"></textarea></div>
        </div>

        <!-- Quotation Details -->
        <div class="form-section">
            <div class="grid-2">
                <div class="form-group"><label for="quote_date">Quotation Date</label><input type="date" id="quote_date" name="quote_date" value="<?php echo date('Y-m-d'); ?>"></div>
                <div class="form-group"><label for="expiry_date">Expiry Date</label><input type="date" id="expiry_date" name="expiry_date" value="<?php echo date('Y-m-d', strtotime('+30 days')); ?>"></div>
            </div>
            <div class="form-group">
                <label for="currency">Currency</label>
                <select id="currency" name="currency" style="width: 200px;">
                    <option value="RWF">RWF - Rwandan Franc</option>
                    <option value="USD">USD - US Dollar</option>
                    <option value="EUR">EUR - Euro</option>
                </select>
            </div>
        </div>

        <!-- Line Items -->
        <div class="form-section">
            <h3>Items</h3>
            <table class="items-table" id="items-table"><thead><tr><th style="width: 50%;">Description</th><th>Quantity</th><th>Unit Price</th><th>Total</th><th></th></tr></thead><tbody></tbody></table>
            <button type="button" class="btn-add" id="add-item-btn" style="margin-top: 15px;">+ Add Item</button>
        </div>

        <!-- Totals -->
        <div class="form-section totals-section"><table class="totals-table">
            <tr><td>Subtotal</td><td style="text-align: right;"><span id="subtotal-currency">FRw </span><span id="subtotal">0.00</span></td></tr>
            <tr><td>Tax Rate (%)</td><td><input type="number" name="tax_rate" id="tax_rate" value="0" min="0" step="0.01" style="width: 80px;"></td></tr>
            <tr><td>Tax Amount</td><td style="text-align: right;"><span id="tax-currency">FRw </span><span id="tax_amount">0.00</span></td></tr>
            <tr style="font-size: 1.2rem; font-weight: bold;"><td>Total</td><td style="text-align: right;"><span id="total-currency">FRw </span><span id="grand_total">0.00</span></td></tr>
        </table></div>

        <!-- Notes and Submit -->
        <div class="form-section">
            <div class="form-group"><label for="notes">Notes / Terms</label><textarea id="notes" name="notes"></textarea></div>
            <button type="submit" class="btn">Save Quotation</button>
        </div>
    </form>
</div>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const itemsTableBody = document.querySelector('#items-table tbody');
        const addItemBtn = document.getElementById('add-item-btn');
        const taxRateInput = document.getElementById('tax_rate');
        const currencySelect = document.getElementById('currency');
        
        // Get currency symbols
        function getCurrencySymbol(code) {
            const symbols = {'USD': '$', 'EUR': '€', 'RWF': 'FRw '};
            return symbols[code] || code + ' ';
        }
        
        let currentCurrency = getCurrencySymbol(currencySelect.value);

        function addItemRow() {
            const row = document.createElement('tr');
            row.innerHTML = `<td><input type="text" name="item_description[]" placeholder="Item or service description" required></td><td><input type="number" name="quantity[]" class="quantity" value="1" min="0" step="0.01"></td><td><input type="number" name="unit_price[]" class="unit-price" value="0.00" min="0" step="0.01"></td><td><span class="item-total">${currentCurrency}0.00</span></td><td class="actions"><button type="button" class="btn-remove">X</button></td>`;
            itemsTableBody.appendChild(row);
        }
        
        // Update currency display when currency selector changes
        currencySelect.addEventListener('change', function() {
            currentCurrency = getCurrencySymbol(this.value);
            updateTotals();
            // Update all existing item totals
            document.querySelectorAll('.item-total').forEach(element => {
                const value = element.textContent.replace(/[^\d.,]/g, '');
                element.textContent = currentCurrency + value;
            });
        });

        itemsTableBody.addEventListener('click', function(e) {
            if (e.target.classList.contains('btn-remove')) {
                e.target.closest('tr').remove();
                updateTotals();
            }
        });

        itemsTableBody.addEventListener('input', function(e) {
            if (e.target.classList.contains('quantity') || e.target.classList.contains('unit-price')) {
                const row = e.target.closest('tr');
                const quantity = parseFloat(row.querySelector('.quantity').value) || 0;
                const unitPrice = parseFloat(row.querySelector('.unit-price').value) || 0;
                row.querySelector('.item-total').textContent = currentCurrency + (quantity * unitPrice).toFixed(2);
                updateTotals();
            }
        });

        taxRateInput.addEventListener('input', updateTotals);
        addItemBtn.addEventListener('click', addItemRow);

        function updateTotals() {
            let subtotal = 0;
            document.querySelectorAll('#items-table tbody tr').forEach(row => {
                const quantity = parseFloat(row.querySelector('.quantity').value) || 0;
                const unitPrice = parseFloat(row.querySelector('.unit-price').value) || 0;
                subtotal += quantity * unitPrice;
            });
            const taxRate = parseFloat(taxRateInput.value) || 0;
            const taxAmount = subtotal * (taxRate / 100);
            const grandTotal = subtotal + taxAmount;
            
            // Update currency symbols
            document.getElementById('subtotal-currency').textContent = currentCurrency;
            document.getElementById('tax-currency').textContent = currentCurrency;
            document.getElementById('total-currency').textContent = currentCurrency;
            
            // Update amounts
            document.getElementById('subtotal').textContent = subtotal.toFixed(2);
            document.getElementById('tax_amount').textContent = taxAmount.toFixed(2);
            document.getElementById('grand_total').textContent = grandTotal.toFixed(2);
        }

        addItemRow();
    });
</script>