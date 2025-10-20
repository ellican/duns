<?php
require_once 'header.php';
require_once 'db.php';

$user_id = $_SESSION['user_id'];

// Fetch all quotations and invoices for the logged-in user
$quotations = $pdo->prepare("SELECT * FROM quotations WHERE user_id = :user_id ORDER BY quote_date DESC");
$quotations->execute([':user_id' => $user_id]);

$invoices = $pdo->prepare("SELECT * FROM invoices WHERE user_id = :user_id ORDER BY invoice_date DESC");
$invoices->execute([':user_id' => $user_id]);
?>
<title>My Documents - Feza Logistics</title>
<style>
    .content-wrapper { max-width: 1200px; margin: 40px auto; padding: 0 20px; }
    .page-header h1 { font-size: 2rem; color: var(--text-color); margin-bottom: 20px; }
    .tabs { display: flex; border-bottom: 1px solid var(--border-color); margin-bottom: 20px; }
    .tab-link { padding: 12px 25px; cursor: pointer; font-weight: 600; color: #555; border-bottom: 3px solid transparent; font-size: 1.1rem; }
    .tab-link.active { color: var(--primary-color); border-bottom-color: var(--primary-color); }
    .tab-pane { display: none; }
    .tab-pane.active { display: block; }
    .docs-table { width: 100%; border-collapse: collapse; background: var(--white-color); box-shadow: 0 4px 20px rgba(0,0,0,0.05); border-radius: 8px; overflow: hidden; }
    .docs-table th, .docs-table td { padding: 15px; border-bottom: 1px solid var(--border-color); text-align: left; }
    .docs-table th { background-color: #f8f9fa; font-weight: 700; color: #555; }
    .docs-table tbody tr:last-child td { border-bottom: none; }
    .docs-table tbody tr:hover { background-color: #f1f5f9; }
    .status-paid { color: #16a34a; font-weight: bold; }
    .status-partially-paid { color: #f59e0b; font-weight: bold; }
    .status-unpaid { color: #dc2626; font-weight: bold; }
    .status-draft { color: #64748b; font-weight: bold; }
    .btn-pdf { background-color: var(--primary-color); color: white; padding: 6px 12px; border-radius: 5px; text-decoration: none; font-size: 0.9rem; }
</style>

<div class="content-wrapper">
    <div class="page-header">
        <h1>My Documents</h1>
    </div>

    <div class="tabs">
        <div class="tab-link active" data-tab="quotations">Quotations</div>
        <div class="tab-link" data-tab="invoices">Invoices</div>
    </div>

    <!-- Quotations Tab -->
    <div id="quotations" class="tab-pane active">
        <table class="docs-table">
            <thead>
                <tr>
                    <th>Number</th><th>Customer</th><th>Date</th><th>Total</th><th>Status</th><th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($quotations as $row): ?>
                <tr>
                    <td><?php echo htmlspecialchars($row['quote_number']); ?></td>
                    <td><?php echo htmlspecialchars($row['customer_name']); ?></td>
                    <td><?php echo date('M d, Y', strtotime($row['quote_date'])); ?></td>
                    <td><?php $currency = $row['currency'] ?? 'RWF'; echo htmlspecialchars($currency . ' ' . number_format($row['total'], 2)); ?></td>
                    <td><span class="status-<?php echo str_replace(' ', '-', $row['status']); ?>"><?php echo ucwords($row['status']); ?></span></td>
                    <td><a href="generate_pdf.php?type=quotation&id=<?php echo $row['id']; ?>" class="btn-pdf" target="_blank">Download PDF</a></td>
                </tr>
                <?php endforeach; ?>
                <?php if ($quotations->rowCount() === 0): ?>
                    <tr><td colspan="6" style="text-align: center; padding: 30px;">No quotations found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Invoices Tab -->
    <div id="invoices" class="tab-pane">
        <table class="docs-table">
            <thead>
                <tr>
                    <th>Number</th><th>Customer</th><th>Date</th><th>Due Date</th><th>Total</th><th>Status</th><th>Action</th>
                </tr>
            </thead>
            <tbody>
                 <?php foreach ($invoices as $row): ?>
                <tr>
                    <td><?php echo htmlspecialchars($row['invoice_number']); ?></td>
                    <td><?php echo htmlspecialchars($row['customer_name']); ?></td>
                    <td><?php echo date('M d, Y', strtotime($row['invoice_date'])); ?></td>
                    <td><?php echo date('M d, Y', strtotime($row['due_date'])); ?></td>
                    <td><?php $currency = $row['currency'] ?? 'RWF'; echo htmlspecialchars($currency . ' ' . number_format($row['total'], 2)); ?></td>
                    <td><span class="status-<?php echo str_replace(' ', '-', $row['status']); ?>"><?php echo ucwords($row['status']); ?></span></td>
                    <td><a href="generate_pdf.php?type=invoice&id=<?php echo $row['id']; ?>" class="btn-pdf" target="_blank">Download PDF</a></td>
                </tr>
                <?php endforeach; ?>
                <?php if ($invoices->rowCount() === 0): ?>
                    <tr><td colspan="7" style="text-align: center; padding: 30px;">No invoices found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const tabLinks = document.querySelectorAll('.tab-link');
    const tabPanes = document.querySelectorAll('.tab-pane');

    tabLinks.forEach(link => {
        link.addEventListener('click', function() {
            const tabId = this.dataset.tab;
            tabLinks.forEach(l => l.classList.remove('active'));
            this.classList.add('active');
            tabPanes.forEach(p => p.classList.remove('active'));
            document.getElementById(tabId).classList.add('active');
        });
    });
});
</script>