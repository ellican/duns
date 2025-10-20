<?php
session_start();

// --- Authenticate User ---
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: login.php");
    exit;
}

// --- Get user info from session ---
$username = $_SESSION['username'] ?? 'User';
$email = $_SESSION['email'] ?? '';
$first_name = $_SESSION['first_name'] ?? $username; // Use first_name if available

// --- Avatar generation using initials ---
$initials = strtoupper(substr($first_name, 0, 2));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Financial Dashboard - Feza Logistics</title>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/design-system.css">
    <link rel="stylesheet" href="assets/css/application.css">
    
    <style>
        /* Additional styles specific to dashboard that extend the design system */
        .page-title {
            color: var(--text-primary);
            text-align: center;
            font-weight: var(--font-weight-bold);
            margin: 0;
            padding-top: var(--space-8);
        }
        
        /* Enhanced financial summary cards */
        .financial-summary-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: var(--space-6);
            margin: var(--space-8);
            margin-bottom: var(--space-6);
        }
        
        .summary-card {
            background: linear-gradient(135deg, var(--bg-primary), var(--bg-muted));
            border-radius: var(--radius-xl);
            padding: var(--space-6);
            border: 1px solid var(--border-primary);
            box-shadow: var(--shadow-lg);
            transition: all var(--transition-base);
            position: relative;
            overflow: hidden;
        }
        
        .summary-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary-500), var(--primary-400));
        }
        
        .summary-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-2xl);
        }
        
        .summary-card-title {
            font-size: var(--font-size-sm);
            font-weight: var(--font-weight-semibold);
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: var(--space-2);
        }
        
        .summary-card-value {
            font-size: var(--font-size-3xl);
            font-weight: var(--font-weight-bold);
            color: var(--text-primary);
            margin-bottom: var(--space-1);
            transition: opacity 0.4s ease-in-out;
            opacity: 1;
        }

        .summary-card-value.fading {
            opacity: 0;
        }
        
        .summary-card-change {
            font-size: var(--font-size-sm);
            font-weight: var(--font-weight-medium);
        }
        .summary-card-change.positive { color: var(--success-700); }
        .summary-card-change.negative { color: var(--error-700); }
        
        /* Redesigned Summary Bar */
        .top-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: var(--space-4);
            flex-wrap: wrap; /* Allow wrapping on smaller screens */
        }
        .summary-bar {
            display: flex;
            gap: var(--space-5);
            padding: var(--space-3) var(--space-5);
            border-radius: var(--radius-lg);
            border: 1px solid transparent;
            transition: all var(--transition-base);
        }
        .summary-item {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
        }
        .summary-item .label {
            font-size: var(--font-size-xs);
            color: var(--text-inverse);
            font-weight: var(--font-weight-medium);
            opacity: 0.8;
            white-space: nowrap;
        }
        .summary-item .value {
            font-size: var(--font-size-lg);
            color: var(--text-inverse);
            font-weight: var(--font-weight-bold);
            font-family: var(--font-family-mono);
            white-space: nowrap;
        }
        /* Visual styling for summary bar states */
        .summary-bar.summary-current { background: linear-gradient(135deg, var(--gray-600), var(--gray-700)); border-color: var(--gray-500); }
        .summary-bar.summary-rwf { background: linear-gradient(135deg, var(--primary-600), var(--primary-700)); border-color: var(--primary-500); }
        .summary-bar.summary-usd { background: linear-gradient(135deg, var(--success-600), var(--success-700)); border-color: var(--success-500); }
        .summary-bar.summary-eur { background: linear-gradient(135deg, var(--warning-500), var(--warning-600)); border-color: var(--warning-400); }


        /* Horizontal & Responsive Forms */
        .form-section {
            background-color: var(--bg-primary);
            border-radius: var(--radius-lg);
            padding: var(--space-6);
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border-primary);
        }
        .form-section h3 {
            margin: 0 0 var(--space-4) 0;
            color: var(--text-primary);
            font-size: var(--font-size-lg);
        }
        .form-grid-horizontal {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: var(--space-4);
            align-items: end;
        }
        .form-grid-horizontal .form-group {
            margin-bottom: 0;
        }
        .form-grid-horizontal .form-group.submit-group {
            grid-column: -2 / -1; /* Align to the end */
        }

        /* **FIXED**: Full width table container styling */
        .full-width-table-container {
            width: 100%;
            padding: 0; /* Remove side padding to allow card to go edge-to-edge */
        }
        .table-card {
            margin: 0;
            border-radius: 0; /* No radius for a seamless edge-to-edge look */
            border-left: none; /* Remove side borders */
            border-right: none;
        }
        .table-responsive-wrapper {
            overflow-x: auto; /* This enables the horizontal scrollbar on the table wrapper */
        }

        /* **FIXED**: Professional Table Actions styling */
        .table-actions {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: var(--space-2); /* Increased gap for better spacing */
        }
        /* General style for all action icon buttons */
        .table-actions .btn-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 36px;   /* Slightly larger for easier clicking */
            height: 36px;  /* Maintain aspect ratio */
            border-radius: var(--radius-full); /* Make them circular */
            transition: all 0.2s ease-in-out;
            border: 1px solid transparent;
        }
        .table-actions .btn-icon:hover {
            transform: scale(1.1); /* Add a subtle zoom effect on hover */
        }
        /* Edit Button */
        .table-actions .btn-edit {
            color: var(--warning-600);
            background-color: var(--warning-100);
        }
        .table-actions .btn-edit:hover {
            background-color: var(--warning-200);
            border-color: var(--warning-300);
        }
        /* Delete Button */
        .table-actions .btn-delete {
            color: var(--error-600);
            background-color: var(--error-100);
        }
        .table-actions .btn-delete:hover {
            background-color: var(--error-200);
            border-color: var(--error-300);
        }
        /* More Actions Button (...) */
        .table-actions .actions-menu-btn {
            color: var(--gray-600);
            background-color: var(--gray-100);
        }
        .table-actions .actions-menu-btn:hover {
            background-color: var(--gray-200);
            border-color: var(--gray-300);
        }
        /* Dropdown menu styling (remains largely the same) */
        .table-actions .actions-menu { position: relative; }
        .table-actions .actions-dropdown {
            display: none; position: absolute; right: 0; top: calc(100% + 4px);
            background-color: var(--bg-primary); border-radius: var(--radius-base); box-shadow: var(--shadow-lg);
            border: 1px solid var(--border-primary); z-index: 10; width: 150px; padding: var(--space-2) 0; overflow: hidden;
        }
        .table-actions .actions-dropdown.show { display: block; }
        .table-actions .actions-dropdown a {
            display: flex; align-items: center; gap: var(--space-3); padding: var(--space-2) var(--space-4);
            font-size: var(--font-size-sm); color: var(--text-secondary); text-decoration: none;
        }
        .table-actions .actions-dropdown a:hover { background-color: var(--bg-muted); color: var(--primary); text-decoration: none; }
        .table-actions .actions-dropdown a.disabled { 
            color: #ccc; cursor: not-allowed; opacity: 0.5; 
        }
        .table-actions .actions-dropdown a.disabled:hover { 
            background-color: transparent; color: #ccc; 
        }
        .table-actions .actions-dropdown a svg { width: 16px; height: 16px; }

        /* Responsive improvements */
        @media (max-width: 992px) {
            .top-actions, .full-width-table-container { padding-left: var(--space-4); padding-right: var(--space-4); }
            .top-actions { flex-direction: column; align-items: stretch; }
            .summary-bar { flex-wrap: wrap; justify-content: center; }
        }
    </style>
</head>
<body>

<script>
    // Inactivity timer script is preserved
    (function() {
        const LOGOUT_TIME = 5 * 60 * 1000; const WARNING_TIME = 4 * 60 * 1000;
        let logoutTimer, warningTimer;
        const logoutUser = () => { window.location.href = 'logout.php'; };
        const showWarning = () => { if (document.getElementById('inactivity-warning')) return; const warningDiv = document.createElement('div'); warningDiv.id = 'inactivity-warning'; warningDiv.innerHTML = 'You will be logged out in 1 minute due to inactivity. '; Object.assign(warningDiv.style, { position: 'fixed', top: '20px', left: '50%', transform: 'translateX(-50%)', padding: '15px 25px', backgroundColor: '#dc3545', color: 'white', borderRadius: '5px', zIndex: '9999', boxShadow: '0 4px 8px rgba(0,0,0,0.2)' }); const stayButton = document.createElement('button'); stayButton.innerText = 'Stay Logged In'; Object.assign(stayButton.style, { marginLeft: '15px', padding: '5px 10px', cursor: 'pointer', border: '1px solid white', backgroundColor: '#0071ce', color: 'white' }); stayButton.onclick = () => resetTimers(); warningDiv.appendChild(stayButton); document.body.appendChild(warningDiv); };
        const resetTimers = () => { clearTimeout(warningTimer); clearTimeout(logoutTimer); const warningDiv = document.getElementById('inactivity-warning'); if (warningDiv) warningDiv.remove(); warningTimer = setTimeout(showWarning, WARNING_TIME); logoutTimer = setTimeout(logoutUser, LOGOUT_TIME); };
        ['click', 'mousemove', 'keydown', 'scroll'].forEach(event => document.addEventListener(event, resetTimers, true));
        resetTimers();
    })();
</script>

<div class="app-container">
    <header class="header-container">
        <a href="index.php" class="logo">Feza Logistics</a>
        <div id="forex-channel" class="forex-channel" title="Live FOREX Rates (Base: USD)">
            <div class="forex-list-wrapper"><ul class="forex-list"><li>Loading...</li></ul></div>
        </div>
        <div class="user-menu">
            <div class="user-avatar" id="avatar-button"><?php echo htmlspecialchars($initials); ?></div>
            <ul class="dropdown-menu" id="dropdown-menu">
                <li><a href="profile.php">Manage Profile</a></li>
                <li><a href="document_list.php">My Documents</a></li>
                <li><a href="transactions.php">Transactions</a></li>
                <li class="divider"></li>
                <li><a href="create_quotation.php">Create Quotation</a></li>
                <li><a href="create_invoice.php">Create Invoice</a></li>
                <li><a href="create_receipt.php">Create Receipt</a></li>
                <li class="divider"></li>
                <li><a href="logout.php">Logout</a></li>
            </ul>
        </div>
    </header>

    <main>
        <h1 class="page-title">Financial Dashboard</h1>

        <!-- Financial Summary Cards -->
        <div class="financial-summary-cards px-8">
            <div class="summary-card">
                <div class="summary-card-title">Total Revenue</div>
                <div class="summary-card-value" id="totalRevenue">Loading...</div>
                <div class="summary-card-change" id="revenueChange">&nbsp;</div>
            </div>
            <div class="summary-card">
                <div class="summary-card-title">Outstanding Amount</div>
                <div class="summary-card-value" id="outstandingAmount">Loading...</div>
                <div class="summary-card-change" id="outstandingChange">&nbsp;</div>
            </div>
            <div class="summary-card">
                <div class="summary-card-title">Total Clients</div>
                <div class="summary-card-value" id="totalClients">Loading...</div>
                <div class="summary-card-change" id="clientsChange">&nbsp;</div>
            </div>
        </div>

        <!-- Top Action Bar -->
        <div class="top-actions px-8 mb-6">
            <div class="action-buttons-group">
                <button id="showAddFormBtn" class="btn btn-primary">Add New Client</button>
                <button id="importExcelBtn" class="btn btn-secondary btn-icon" title="Import from Excel"><svg fill="currentColor" viewBox="0 0 24 24" width="18" height="18"><path d="M14,2H6A2,2 0 0,0 4,4V20A2,2 0 0,0 6,22H18A2,2 0 0,0 20,20V8L14,2M13.5,16V19H11.5V16H8.5V14H11.5V11H13.5V14H16.5V16H13.5M13,9V3.5L18.5,9H13Z" /></svg></button>
                <input type="file" id="importExcelFile" style="display: none;" accept=".xlsx, .xls">
                <button id="downloadExcelBtn" class="btn btn-secondary btn-icon" title="Download as Excel"><svg fill="currentColor" viewBox="0 0 24 24" width="18" height="18"><path d="M5,20H19V18H5M19,9H15V3H9V9H5L12,16L19,9Z" /></svg></button>
                <button id="printTableBtn" class="btn btn-secondary btn-icon" title="Print Table"><svg fill="currentColor" viewBox="0 0 24 24" width="18" height="18"><path d="M19,8H5A3,3 0 0,0 2,11V17H6V21H18V17H22V11A3,3 0 0,0 19,8M16,19H8V14H16M19,12A1,1 0 0,1 20,13A1,1 0 0,1 19,14A1,1 0 0,1 18,13A1,1 0 0,1 19,12M18,3H6V7H18V3Z" /></svg></button>
                <button id="viewAllBtn" class="btn btn-secondary">View All</button>
            </div>
            <div id="summaryBar" class="summary-bar">
                <!-- Content generated by JS -->
            </div>
        </div>
        
        <!-- Add Client Form -->
        <div id="addClientCard" class="form-section mx-8 mb-6" style="display: none;">
            <h3>Add New Client</h3>
            <form id="clientForm">
                <div class="form-grid-horizontal">
                    <div class="form-group"><label class="form-label">Reg No</label><input type="text" name="reg_no" class="form-control" required></div>
                    <div class="form-group"><label class="form-label">Client Name</label><input type="text" name="client_name" class="form-control" required></div>
                    <div class="form-group"><label class="form-label">Date</label><input type="date" name="date" class="form-control" required></div>
                    <div class="form-group"><label class="form-label">Responsible</label><input type="text" name="Responsible" class="form-control" required></div>
                    <div class="form-group"><label class="form-label">TIN</label><input type="text" name="TIN" class="form-control" maxlength="9" pattern="[0-9]{1,9}" title="Enter up to 9 digits" placeholder="9 digits max"></div>
                    <div class="form-group"><label class="form-label">Service</label><input type="text" name="service" class="form-control" required></div>
                    <div class="form-group"><label class="form-label">Amount</label><input type="number" name="amount" class="form-control" step="0.01" required></div>
                    <div class="form-group"><label class="form-label">Currency</label><select name="currency" class="form-control form-select" required><option value="USD" selected>USD</option><option value="EUR">EUR</option><option value="RWF">RWF</option></select></div>
                    <div class="form-group"><label class="form-label">Paid</label><input type="number" name="paid_amount" class="form-control" step="0.01" required></div>
                    <div class="form-group"><label class="form-label">Due</label><input type="number" name="due_amount" class="form-control" readonly></div>
                    <div class="form-group submit-group"><button type="submit" class="btn btn-primary w-full">Save Client</button></div>
                </div>
            </form>
        </div>

        <!-- Filter Section -->
        <div class="form-section mx-8 mb-6">
            <h3>Filter & Search</h3>
            <div id="filterContainer" class="form-grid-horizontal">
                <div class="form-group"><label for="search" class="form-label">Search</label><input type="text" id="search" class="form-control" placeholder="Reg No, Name, Phone..."></div>
                <div class="form-group"><label for="filterDateFrom" class="form-label">From</label><input type="date" id="filterDateFrom" class="form-control"></div>
                <div class="form-group"><label for="filterDateTo" class="form-label">To</label><input type="date" id="filterDateTo" class="form-control"></div>
                <div class="form-group"><label for="filterPaidStatus" class="form-label">Status</label><select id="filterPaidStatus" class="form-control form-select"><option value="">All</option><option value="PAID">Paid</option><option value="PARTIALLY PAID">Partially Paid</option><option value="NOT PAID">Not Paid</option></select></div>
                <div class="form-group"><label for="filterCurrency" class="form-label">Currency</label><select id="filterCurrency" class="form-control form-select"><option value="">All</option><option value="RWF">RWF</option><option value="USD">USD</option><option value="EUR">EUR</option></select></div>
            </div>
        </div>
    
        <!-- Data Table -->
        <div class="full-width-table-container">
            <div class="enhanced-card table-card">
                <div class="table-responsive-wrapper">
                    <table id="clientTable" class="enhanced-table">
                        <thead>
                            <tr>
                                <th>#</th><th>Reg No</th><th>Client Name</th><th>Date</th><th>Responsible</th><th>TIN</th><th>Service</th><th>Amount</th>
                                <th>Currency</th><th>Paid</th><th>Due</th><th>Status</th><th>Actions</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- Modals -->
<div id="historyModal" class="enhanced-modal" aria-hidden="true"><div class="modal-content"><div class="modal-header"><h3 id="historyModal-title" class="modal-title">Client History</h3></div><div id="historyModal-body"><p>Loading...</p></div><div class="modal-footer"><button type="button" id="closeHistoryModalBtn" class="btn btn-secondary">Close</button></div></div></div>
<div id="tinModal" class="enhanced-modal" aria-hidden="true"><div class="modal-content container-sm"><div class="modal-header"><h3 id="tinModal-title" class="modal-title">Enter TIN</h3></div><form id="tinForm" novalidate><input type="hidden" id="tin-clientId"><input type="hidden" id="tin-docType"><div class="form-group"><label for="tin-number" class="form-label">TIN Number</label><input type="text" id="tin-number" class="form-control" placeholder="Enter 9 digits" required><div class="form-error tin-error-message"></div></div><div class="modal-footer"><button type="button" id="closeTinModalBtn" class="btn btn-secondary">Cancel</button><button type="submit" class="btn btn-primary">Generate</button></div></form></div></div>
<div id="confirmModal" class="enhanced-modal" aria-hidden="true"><div class="modal-content container-sm text-center"><div class="modal-header"><h3 id="confirmModal-title" class="modal-title">Confirm Deletion</h3></div><p id="confirmModal-text">Are you sure?</p><div class="modal-footer"><button id="confirmModal-cancel" class="btn btn-secondary">Cancel</button><button id="confirmModal-confirm" class="btn btn-danger">Delete</button></div></div></div>
<div id="notification-toast" class="notification-toast"></div>
<div id="loading-overlay" class="loading-overlay"></div>

<script>
$(document).ready(function() {
    // --- Global State ---
    let dashboardStatsInterval, summaryBarInterval;
    let currencySummaries = {};

    // --- Helper Functions ---
    const showLoading = (show) => $('#loading-overlay').toggleClass('show', show);
    const showToast = (message, type = 'success') => {
        const toast = $('#notification-toast');
        toast.text(message).removeClass('success error show').addClass(type).addClass('show');
        setTimeout(() => toast.removeClass('show'), type === 'error' ? 5000 : 3000);
    };
    
    /**
     * **IMPROVED ERROR HANDLING**
     * This function provides more detailed error messages to help with debugging.
     * If a PHP error occurs, it will log the full response to the console.
     */
    const handleAjaxError = (jqXHR, defaultMessage) => {
        let error = defaultMessage;
        // Check for a structured JSON error response first
        if (jqXHR.responseJSON && jqXHR.responseJSON.error) {
            error = jqXHR.responseJSON.error;
            if (jqXHR.responseJSON.details) {
                console.error("Server Error Details:", jqXHR.responseJSON.details);
                error += " (See console for details)";
            }
        } 
        // If no JSON, it might be a fatal PHP error outputting HTML
        else if (jqXHR.responseText) {
            console.error("An unexpected server error occurred. Full server response:", jqXHR.responseText);
            error = "An unexpected server error occurred. Check browser console (F12) for details.";
        }
        showToast(error, 'error');
    };

    const showConfirm = (callback) => {
        $('#confirmModal').addClass('show').attr('aria-hidden', 'false');
        $('#confirmModal-confirm').off('click').one('click', () => {
            $('#confirmModal').removeClass('show').attr('aria-hidden', 'true');
            callback();
        });
        $('#confirmModal-cancel').off('click').one('click', () => $('#confirmModal').removeClass('show'));
    };
    const formatCurrency = (amount) => amount.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });

    // --- Data Fetching and Rendering ---

    /**
     * Fetches data from the server. Handles both initial load and filtered requests.
     */
    function loadData() {
        showLoading(true);
        const ajaxData = { 
            searchQuery: $('#search').val(), 
            filterDateFrom: $('#filterDateFrom').val(), 
            filterDateTo: $('#filterDateTo').val(), 
            filterPaidStatus: $('#filterPaidStatus').val(), 
            filterCurrency: $('#filterCurrency').val() 
        };

        $.ajax({
            url: 'fetch_dashboard_data.php', // Always call the single, consolidated endpoint
            type: 'GET',
            dataType: 'json',
            data: ajaxData,
            success: function(response) {
                if (response.success) {
                    renderTable(response.clients);
                    // Stats are only returned on the initial load (when no filters are active).
                    // This prevents cards from updating on every search keystroke.
                    if (response.stats) {
                        currencySummaries = response.stats.currencySummaries;
                        updateDashboardCards(response.stats);
                        startSummaryBarCycler();
                    }
                } else {
                    handleAjaxError({ responseJSON: response }, 'Failed to load data.');
                }
            },
            error: (jqXHR) => handleAjaxError(jqXHR, 'Server error while fetching data.'),
            complete: () => showLoading(false)
        });
    }

    function renderTable(clients) {
        cancelEditing();
        const tableBody = $('#clientTable tbody');
        if (!clients || !Array.isArray(clients)) {
            tableBody.html('<tr><td colspan="12" class="text-center p-8">Could not load client data.</td></tr>');
            return;
        }
        if (clients.length === 0) {
            tableBody.html('<tr><td colspan="12" class="text-center p-8">No clients found matching your criteria.</td></tr>');
            updateSummaryBarForFilteredView();
            return;
        }

        let rowsHtml = '';
        clients.forEach((client, index) => {
            const statusClass = client.status.toLowerCase().replace(/ /g, '-');
            const statusIndicator = `<span class="status-indicator status-${statusClass}">${client.status}</span>`;
            // Conditional receipt link based on paid amount
            const receiptLink = parseFloat(client.paid_amount) > 0 
                ? `<a href="#" class="print-link" data-type="receipt"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 15v-1a4 4 0 00-4-4H8m0 0l4 4m-4-4l4-4m-1 12H7a2 2 0 01-2-2V7a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V15a2 2 0 01-2 2z"></path></svg><span>Receipt</span></a>`
                : `<a href="#" class="print-link disabled" data-type="receipt" title="No payment made"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 15v-1a4 4 0 00-4-4H8m0 0l4 4m-4-4l4-4m-1 12H7a2 2 0 01-2-2V7a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V15a2 2 0 01-2 2z"></path></svg><span>Receipt</span></a>`;

            // Action buttons HTML with new classes for styling
            const actionButtons = `
                <div class="table-actions">
                    <button class="editBtn btn btn-icon btn-edit" title="Edit Row"><svg viewBox="0 0 20 20" fill="currentColor" width="16" height="16"><path d="M17.414 2.586a2 2 0 00-2.828 0L7 10.172V13h2.828l7.586-7.586a2 2 0 000-2.828z"></path><path fill-rule="evenodd" d="M2 6a2 2 0 012-2h4a1 1 0 010 2H4v10h10v-4a1 1 0 112 0v4a2 2 0 01-2 2H4a2 2 0 01-2-2V6z" clip-rule="evenodd"></path></svg></button>
                    <button class="deleteBtn btn btn-icon btn-delete" title="Delete Row"><svg viewBox="0 0 20 20" fill="currentColor" width="16" height="16"><path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd"></path></svg></button>
                    <div class="actions-menu">
                        <button class="actions-menu-btn btn btn-icon" title="More Actions"><svg viewBox="0 0 20 20" fill="currentColor" width="16" height="16"><path d="M10 6a2 2 0 110-4 2 2 0 010 4zM10 12a2 2 0 110-4 2 2 0 010 4zM10 18a2 2 0 110-4 2 2 0 010 4z"></path></svg></button>
                        <div class="actions-dropdown">
                            <a href="#" class="print-link" data-type="invoice"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg><span>Invoice</span></a>
                            ${receiptLink}
                            <a href="#" class="historyBtn"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg><span>History</span></a>
                        </div>
                    </div>
                </div>`;

            rowsHtml += `
                <tr data-id="${client.id}">
                    <td>${index + 1}</td>
                    <td title="${client.reg_no}"><div class="truncate" style="max-width: 10ch;">${client.reg_no}</div></td>
                    <td title="${client.client_name}"><div class="truncate" style="max-width: 25ch;">${client.client_name}</div></td>
                    <td>${client.date}</td>
                    <td>${client.Responsible || client.phone_number || ''}</td>
                    <td>${client.TIN || ''}</td>
                    <td title="${client.service}"><div class="truncate" style="max-width: 20ch;">${client.service}</div></td>
                    <td>${formatCurrency(parseFloat(client.amount))}</td>
                    <td>${client.currency}</td>
                    <td>${formatCurrency(parseFloat(client.paid_amount))}</td>
                    <td>${formatCurrency(parseFloat(client.due_amount))}</td>
                    <td>${statusIndicator}</td>
                    <td>${actionButtons}</td>
                </tr>
            `;
        });
        tableBody.html(rowsHtml);
        updateSummaryBarForFilteredView();
    }
    
    function updateDashboardCards(stats) {
        $('#totalClients').text(stats.totalClients);
        $('#clientsChange').text(`+${stats.newClients} new this month`);

        const revenueChangeEl = $('#revenueChange');
        revenueChangeEl.text(`${Math.abs(stats.revenueChange)}% ${stats.revenueChange > 0 ? 'up' : 'down'} vs last month`)
                       .toggleClass('positive', stats.revenueChange > 0)
                       .toggleClass('negative', stats.revenueChange < 0);

        const outstandingChangeEl = $('#outstandingChange');
        outstandingChangeEl.text(`${Math.abs(stats.outstandingChange)}% ${stats.outstandingChange > 0 ? 'up' : 'down'} vs last month`)
                           .toggleClass('positive', stats.outstandingChange < 0) // Higher outstanding is bad
                           .toggleClass('negative', stats.outstandingChange > 0);

        clearInterval(dashboardStatsInterval);
        const currencies = ['RWF', 'USD', 'EUR'];
        let currencyIndex = 0;
        const cycle = () => {
            const currency = currencies[currencyIndex];
            const summary = stats.currencySummaries[currency];
            const revenueEl = $('#totalRevenue');
            const outstandingEl = $('#outstandingAmount');
            revenueEl.addClass('fading');
            outstandingEl.addClass('fading');
            setTimeout(() => {
                revenueEl.text(`${formatCurrency(summary.total_revenue)} ${currency}`).removeClass('fading');
                outstandingEl.text(`${formatCurrency(summary.outstanding_amount)} ${currency}`).removeClass('fading');
                currencyIndex = (currencyIndex + 1) % currencies.length;
            }, 400);
        };
        cycle();
        dashboardStatsInterval = setInterval(cycle, 5000);
    }

    function updateSummaryBarForFilteredView() {
        let totals = {};
        $('#clientTable tbody tr').each(function() {
            const currency = $(this).find('td').eq(7).text();
            if (!totals[currency]) {
                totals[currency] = { total: 0, paid: 0, due: 0 };
            }
            totals[currency].total += parseFloat($(this).find('td').eq(6).text().replace(/,/g, '')) || 0;
            totals[currency].paid += parseFloat($(this).find('td').eq(8).text().replace(/,/g, '')) || 0;
            totals[currency].due += parseFloat($(this).find('td').eq(9).text().replace(/,/g, '')) || 0;
        });

        const currencyKeys = Object.keys(totals);
        const summaryBar = $('#summaryBar');
        let html = '';
        if (currencyKeys.length === 1) {
            const currency = currencyKeys[0];
            html = `
                <div class="summary-item"><span class="label">Total:</span><span class="value">${formatCurrency(totals[currency].total)} ${currency}</span></div>
                <div class="summary-item"><span class="label">Paid:</span><span class="value">${formatCurrency(totals[currency].paid)} ${currency}</span></div>
                <div class="summary-item"><span class="label">Due:</span><span class="value">${formatCurrency(totals[currency].due)} ${currency}</span></div>
            `;
        } else {
            html = `<div class="summary-item"><span class="label">Totals (Mixed Currencies)</span></div>`;
        }
        summaryBar.html(html);
    }
    
    let summaryBarState = 0;
    function startSummaryBarCycler() {
        clearInterval(summaryBarInterval);
        const cycle = () => {
            const summaryBar = $('#summaryBar');
            summaryBar.removeClass('summary-rwf summary-usd summary-eur summary-current');
            let colorClass = 'summary-current';
            let html = '';

            if (summaryBarState < 3) {
                const currencies = ['RWF', 'USD', 'EUR'];
                const currency = currencies[summaryBarState];
                const totals = currencySummaries[currency] || { total_revenue: 0, outstanding_amount: 0 };
                const totalAmount = totals.total_revenue + totals.outstanding_amount;
                colorClass = `summary-${currency.toLowerCase()}`;
                html = `
                    <div class="summary-item"><span class="label">${currency} Total</span><span class="value">${formatCurrency(totalAmount)}</span></div>
                    <div class="summary-item"><span class="label">Paid</span><span class="value">${formatCurrency(totals.total_revenue)}</span></div>
                    <div class="summary-item"><span class="label">Due</span><span class="value">${formatCurrency(totals.outstanding_amount)}</span></div>
                `;
            } else {
                updateSummaryBarForFilteredView(); // This will regenerate the correct HTML
            }
            
            if (summaryBarState < 3) summaryBar.html(html);
            summaryBar.addClass(colorClass);
            summaryBarState = (summaryBarState + 1) % 4;
        };
        cycle(); // Initial call
        summaryBarInterval = setInterval(cycle, 7000);
    }

    function fetchForexRates() {
        $.ajax({
            url: 'https://open.er-api.com/v6/latest/USD',
            success: function(data) {
                if (data.result !== 'success') return;
                const forexList = $('.forex-list');
                let ratesHtml = '';
                ['RWF', 'EUR', 'GBP', 'KES', 'UGX', 'TZS'].forEach(currency => {
                    if (data.rates[currency]) {
                        ratesHtml += `<li><span class="currency-pair">USD/${currency}</span> <span class="rate">${data.rates[currency].toFixed(2)}</span></li>`;
                    }
                });
                forexList.html(ratesHtml.repeat(2));
            }
        });
    }

    function cancelEditing() {
        const editingRow = $('tr.editing-row');
        if (editingRow.length) {
            editingRow.replaceWith(editingRow.data('originalHTML'));
        }
    }

    // --- Event Handlers ---
    $('#avatar-button').on('click', (e) => { e.stopPropagation(); $('#dropdown-menu').toggleClass('show'); });
    $(document).on('click', (e) => { 
        if (!$(e.target).closest('.user-menu').length) $('#dropdown-menu').removeClass('show'); 
        if (!$(e.target).closest('.actions-menu').length) $('.actions-dropdown').removeClass('show');
    });
    $('#showAddFormBtn').on('click', () => $('#addClientCard').slideToggle(300));
    $('#addClientCard').on('input', '[name="amount"], [name="paid_amount"]', function() {
        const form = $(this).closest('form');
        const amount = parseFloat(form.find('[name="amount"]').val()) || 0;
        const paidAmount = parseFloat(form.find('[name="paid_amount"]').val()) || 0;
        form.find('[name="due_amount"]').val((amount - paidAmount).toFixed(2));
    });

    // TIN validation - only allow digits and max 9 characters
    $(document).on('input', '[name="TIN"]', function() {
        let value = $(this).val();
        // Remove non-digit characters
        value = value.replace(/\D/g, '');
        // Limit to 9 digits
        if (value.length > 9) {
            value = value.substring(0, 9);
        }
        $(this).val(value);
    });

    $('#clientForm').on('submit', function(e) {
        e.preventDefault();
        
        // Validate TIN if provided
        const tinValue = $('[name="TIN"]', this).val();
        if (tinValue && !/^\d{1,9}$/.test(tinValue)) {
            showToast('TIN must be up to 9 digits only', 'error');
            return;
        }
        
        showLoading(true);
        $.ajax({
            url: 'insert_client.php', type: 'POST', data: $(this).serialize(), dataType: 'json',
            success: function(response) {
                if (response.success) {
                    $('#addClientCard').slideUp(300);
                    this.reset();
                    showToast('Client added successfully!');
                    loadData(); // Reload data after adding a client
                } else { handleAjaxError({responseJSON: response}, 'Failed to add client.'); }
            }.bind(this),
            error: (jqXHR) => handleAjaxError(jqXHR, 'Server error during insert.'),
            complete: () => showLoading(false)
        });
    });

    $('#clientTable').on('click', '.editBtn', function() {
        cancelEditing();
        const row = $(this).closest('tr');
        row.data('originalHTML', row[0].outerHTML);
        const cells = row.children('td');
        const clientData = {
            reg_no: $(cells[1]).text(), client_name: $(cells[2]).text(), date: $(cells[3]).text(),
            Responsible: $(cells[4]).text(), TIN: $(cells[5]).text(), service: $(cells[6]).text(), 
            amount: $(cells[7]).text().replace(/,/g, ''), currency: $(cells[8]).text(), 
            paid_amount: $(cells[9]).text().replace(/,/g, '')
        };
        const editRowHtml = `
            <td class="p-2">${$(cells[0]).text()}</td>
            <td class="p-2"><input type="text" name="reg_no" class="form-control form-control-sm" value="${clientData.reg_no}"></td>
            <td class="p-2"><input type="text" name="client_name" class="form-control form-control-sm" value="${clientData.client_name}"></td>
            <td class="p-2"><input type="date" name="date" class="form-control form-control-sm" value="${clientData.date}"></td>
            <td class="p-2"><input type="text" name="Responsible" class="form-control form-control-sm" value="${clientData.Responsible}"></td>
            <td class="p-2"><input type="text" name="TIN" class="form-control form-control-sm" maxlength="9" pattern="[0-9]{1,9}" value="${clientData.TIN}"></td>
            <td class="p-2"><input type="text" name="service" class="form-control form-control-sm" value="${clientData.service}"></td>
            <td class="p-2"><input type="number" name="amount" class="form-control form-control-sm" step="0.01" value="${clientData.amount}"></td>
            <td class="p-2"><select name="currency" class="form-control form-control-sm form-select"><option value="RWF">RWF</option><option value="USD">USD</option><option value="EUR">EUR</option></select></td>
            <td class="p-2"><input type="number" name="paid_amount" class="form-control form-control-sm" step="0.01" value="${clientData.paid_amount}"></td>
            <td class="p-2" colspan="2"></td>
            <td class="p-2 action-buttons-cell"><button class="saveBtn btn btn-success btn-sm">Save</button><button class="cancelBtn btn btn-secondary btn-sm">Cancel</button></td>
        `;
        row.addClass('editing-row').html(editRowHtml);
        row.find('[name="currency"]').val(clientData.currency);
    });

    $('#clientTable').on('click', '.cancelBtn', cancelEditing);

    $('#clientTable').on('click', '.saveBtn', function() {
        const row = $(this).closest('tr');
        let formData = { id: row.data('id') };
        row.find('input, select').each(function() { formData[$(this).attr('name')] = $(this).val(); });
        showLoading(true);
        $.ajax({
            url: 'update_client.php', type: 'POST', data: formData, dataType: 'json',
            success: function(response) {
                if (response.success) {
                    showToast('Client updated successfully!');
                    loadData(); // Reload data after update
                } else { cancelEditing(); handleAjaxError({responseJSON: response}, 'Update failed.'); }
            },
            error: (jqXHR) => { cancelEditing(); handleAjaxError(jqXHR, 'Server error during update.'); },
            complete: () => showLoading(false)
        });
    });

    $('#clientTable').on('click', '.deleteBtn', function() {
        const clientId = $(this).closest('tr').data('id');
        showConfirm(() => {
            showLoading(true);
            $.ajax({
                url: 'delete_client.php', type: 'POST', data: { id: clientId }, dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        showToast('Client deleted.');
                        loadData(); // Reload data after delete
                    } else { handleAjaxError({responseJSON: response}, 'Could not delete client.'); }
                },
                error: (jqXHR) => handleAjaxError(jqXHR, 'Server error while deleting.'),
                complete: () => showLoading(false)
            });
        });
    });

    // --- Filter and Action Button Handlers ---
    let debounceTimer;
    $('#filterContainer input, #filterContainer select').on('change keyup', function() {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => loadData(), 400); // Call the main data load function on any filter change
    });
    $('#viewAllBtn').on('click', () => { 
        $('#filterContainer').find('input, select').val(''); 
        loadData(); // Reloads all data without filters
    });
    
    // --- Excel and Print Handlers ---
    $('#downloadExcelBtn').on('click', function() {
        const table = document.getElementById('clientTable');
        if (!table || table.rows.length <= 1) { showToast('No data to download.', 'error'); return; }
        const wb = XLSX.utils.book_new();
        const ws = XLSX.utils.table_to_sheet(table);
        XLSX.utils.book_append_sheet(wb, ws, 'Client_Data');
        XLSX.writeFile(wb, `Feza_Logistics_Clients_${new Date().toISOString().slice(0, 10)}.xlsx`);
    });

    $('#importExcelBtn').on('click', () => $('#importExcelFile').click());
    $('#importExcelFile').on('change', function(event) {
        const file = event.target.files[0];
        if (!file) return;
        showLoading(true);
        const reader = new FileReader();
        reader.onload = (e) => {
            try {
                const records = XLSX.utils.sheet_to_json(XLSX.read(e.target.result, { type: 'binary' }).Sheets['Sheet1']);
                $.ajax({
                    url: 'import_excel.php', type: 'POST', contentType: 'application/json', data: JSON.stringify(records),
                    success: (response) => {
                        if (response.success) { showToast(response.message, 'success'); loadData(); } 
                        else { handleAjaxError({responseJSON: response}, 'Import failed.'); }
                    },
                    error: (jqXHR) => handleAjaxError(jqXHR, 'Server error during import.'),
                    complete: () => showLoading(false)
                });
            } catch (error) { showToast('Could not process Excel file.', 'error'); showLoading(false); }
        };
        reader.readAsBinaryString(file);
    });
    
    $('#printTableBtn').on('click', function() {
        const tableToPrint = $('#clientTable').clone();
        tableToPrint.find('.table-actions').remove(); // Remove actions column for printing
        const printWindow = window.open('', '', 'height=800,width=1200');
        printWindow.document.write('<html><head><title>Print Client Data</title><link rel="stylesheet" href="assets/css/design-system.css"><style>body{background:white;padding:2rem;}th:last-child,td:last-child{display:none;}</style></head><body><h1>Client Financial Data</h1>');
        printWindow.document.write(tableToPrint.prop('outerHTML'));
        printWindow.document.write('</body></html>');
        printWindow.document.close();
        printWindow.focus();
        setTimeout(() => { printWindow.print(); printWindow.close(); }, 250);
    });

    // --- Modal and Table Action Handlers ---
    $('#clientTable').on('click', '.actions-menu-btn', function(e) {
        e.stopPropagation();
        $('.actions-dropdown').not($(this).next()).removeClass('show'); // Hide others
        $(this).next('.actions-dropdown').toggleClass('show');
    });

    $('#clientTable').on('click', '.print-link', function(e) { 
        e.preventDefault(); 
        
        // Check if the link is disabled
        if ($(this).hasClass('disabled')) {
            return false;
        }
        
        const docType = $(this).data('type'); 
        const clientId = $(this).closest('tr').data('id'); 
        $('#tin-clientId').val(clientId); 
        $('#tin-docType').val(docType); 
        $('#tinModal').addClass('show'); 
    });

    $('#clientTable').on('click', '.historyBtn', function(e) {
        e.preventDefault();
        const row = $(this).closest('tr');
        $('#historyModal-title').text(`History for: ${row.find('td').eq(2).text()}`);
        $('#historyModal-body').html('<p>Loading...</p>'); 
        $('#historyModal').addClass('show'); 
        $.ajax({ 
            url: 'fetch_history.php', data: { id: row.data('id') }, 
            success: (data) => { 
                let html = '<p>No history found.</p>'; 
                if (data.length) { 
                    html = '<table class="table"><thead><tr><th>Time</th><th>User</th><th>Action</th><th>Details</th></tr></thead><tbody>'; 
                    data.forEach(item => { html += `<tr><td>${item.changed_at}</td><td>${item.user_name}</td><td>${item.action}</td><td>${item.details}</td></tr>`; }); 
                    html += '</tbody></table>'; 
                } 
                $('#historyModal-body').html(html); 
            } 
        }); 
    });

    $('#tinForm').on('submit', function(e) { e.preventDefault(); if (/^\d{9}$/.test($('#tin-number').val())) { window.open(`print_document.php?id=${$('#tin-clientId').val()}&type=${$('#tin-docType').val()}&tin=${$('#tin-number').val()}`, '_blank'); $('#tinModal').removeClass('show'); } else { $('.tin-error-message').text('Please enter exactly 9 digits.'); } });
    $('#closeTinModalBtn, #closeHistoryModalBtn').on('click', () => $('.enhanced-modal').removeClass('show'));

    // --- Initial Load ---
    loadData(); // Initial data load
    fetchForexRates();
    setInterval(fetchForexRates, 1000 * 60 * 15);
});
</script>

</body>
</html>