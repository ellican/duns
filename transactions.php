<?php
// This line ensures user authentication and includes your site's header/menu.
require_once 'header.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Transactions Dashboard — Feza Logistics</title>
  
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet" />
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/xlsx/dist/xlsx.full.min.js"></script>
  
  <style>
    /* ==========================================================================
       1. Global Styles & Theming
       ========================================================================== */
    :root {
      --bg-body: #f8f9fc;
      --bg-card: #ffffff;
      --bg-header: #111827;
      --text-primary: #1f2937;
      --text-secondary: #4b5563;
      --text-muted: #6b7280;
      --border-color: #e5e7eb;
      --accent-primary: #4f46e5;
      --accent-primary-hover: #4338ca;
      --accent-secondary: #eef2ff;
      --accent-secondary-hover: #e0e7ff;
      --danger: #ef4444;
      --danger-hover: #dc2626;
      --danger-light: #fee2e2;
      --success: #10b981;
      --success-hover: #059669;
      --success-light: #d1fae5;
      --orange: #f97316;
      --orange-light: #ffedd5;
      --blue: #3b82f6;
      --blue-light: #dbeafe;

      --radius-sm: 6px;
      --radius-md: 10px;
      --radius-lg: 16px;
      --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
      --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -2px rgba(0, 0, 0, 0.1);
      --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -4px rgba(0, 0, 0, 0.1);
      --transition-speed: 0.2s;
    }

    * { box-sizing: border-box; }
    body {
      margin: 0;
      background: var(--bg-body);
      color: var(--text-primary);
      font-family: "Inter", system-ui, -apple-system, sans-serif;
      -webkit-font-smoothing: antialiased;
      -moz-osx-font-smoothing: grayscale;
    }

    /* ==========================================================================
       2. Layout & Structure
       ========================================================================== */
    .page-header {
      background: linear-gradient(135deg, var(--bg-header) 0%, #374151 100%);
      padding: 32px 24px 80px 24px;
      color: white;
    }
    .header-content {
        max-width: 1400px;
        margin: 0 auto;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }
    .header-content .brand { display: flex; gap: 16px; align-items: center; }
    .header-content .logo {
        width: 48px; height: 48px; border-radius: var(--radius-md);
        background: var(--accent-primary);
        display: flex; align-items: center; justify-content: center;
        font-weight: 700; font-size: 20px;
    }
    .header-content h1 { font-size: 28px; font-weight: 700; margin: 0; }
    .header-content .controls { display: flex; gap: 12px; align-items: center; }

    .content-wrapper { max-width: 1400px; margin: 0 auto; padding: 0 24px 32px; transform: translateY(-50px); }
    .dashboard-grid { display: grid; grid-template-columns: repeat(12, 1fr); gap: 24px; }
    .card { background: var(--bg-card); padding: 24px; border-radius: var(--radius-lg); box-shadow: var(--shadow-md); border: 1px solid var(--border-color);}
    .card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
    .card-title { margin: 0; font-size: 18px; font-weight: 600; }
    
    #addTransactionSection { 
      grid-column: 1 / -1; 
      transition: all 0.4s ease-in-out; 
      transform-origin: top; 
      overflow: hidden; 
      max-height: 0;
      padding-top: 0;
      padding-bottom: 0;
      margin-top: -24px;
      opacity: 0;
    }
    #addTransactionSection.is-visible {
      max-height: 500px;
      padding-top: 24px;
      padding-bottom: 24px;
      margin-top: 0;
      opacity: 1;
    }
    
    /* Responsive Grid Layout */
    @media (min-width: 768px) {
        .grid-span-md-4 { grid-column: span 4; }
        .grid-span-md-8 { grid-column: span 8; }
    }
    @media (min-width: 1200px) {
        .grid-span-lg-3 { grid-column: span 3; }
        .grid-span-lg-9 { grid-column: span 9; }
        .grid-span-lg-2 { grid-column: span 2; }
        .grid-span-lg-10 { grid-column: span 10; }
    }
    .grid-span-12 { grid-column: 1 / -1; }


    /* ==========================================================================
       3. Components (Buttons, Forms, Table, etc.)
       ========================================================================== */

    .btn {
      display: inline-flex; align-items: center; justify-content: center; gap: 8px;
      background: var(--accent-primary); color: white;
      padding: 10px 16px; border-radius: var(--radius-md); border: none;
      cursor: pointer; font-weight: 600; white-space: nowrap;
      transition: all var(--transition-speed) ease;
      font-size: 14px;
    }
    .btn:hover { background-color: var(--accent-primary-hover); transform: translateY(-1px); box-shadow: var(--shadow-sm); }
    .btn:focus-visible { outline: 2px solid var(--accent-primary); outline-offset: 2px; }
    .btn.secondary { background: var(--accent-secondary); color: var(--accent-primary); }
    .btn.secondary:hover { background-color: var(--accent-secondary-hover); }
    .btn.danger { background-color: var(--danger); }
    .btn.danger:hover { background-color: var(--danger-hover); }
    .btn.success { background-color: var(--success); }
    .btn.success:hover { background-color: var(--success-hover); }
    .btn:disabled { background-color: #9ca3af; cursor: not-allowed; transform: none; box-shadow: none; }
    .btn-sm { padding: 6px 12px; font-size: 13px; border-radius: var(--radius-sm); }
    .btn .icon { width: 16px; height: 16px; }
    
    .filters-card { display: flex; gap: 12px; align-items: center; flex-wrap: wrap; }
    .filters-card input, .filters-card select, #txForm input, #txForm select, .modal-content input, .modal-content select {
        padding: 10px 12px; border-radius: var(--radius-md); border: 1px solid var(--border-color); font-size: 14px;
        background-color: white; transition: border-color var(--transition-speed), box-shadow var(--transition-speed);
    }
    .filters-card input:focus, .filters-card select:focus, #txForm input:focus, #txForm select:focus, .modal-content input:focus, .modal-content select:focus {
        border-color: var(--accent-primary); box-shadow: 0 0 0 2px var(--accent-secondary); outline: none;
    }
    .filters-card .grow { flex: 1; min-width: 200px; }

    #txForm { display: flex; gap: 12px; overflow-x: auto; flex-wrap: nowrap; align-items: flex-end; padding-bottom: 5px; }
    #txForm > div { display: flex; flex-direction: column; flex: 1; min-width: 120px; }
    #txForm label { font-size: 13px; color: var(--text-secondary); margin-bottom: 6px; font-weight: 500; }
    #txForm input[readonly] { background-color: #f3f4f6; cursor: not-allowed; }
    #txForm .form-actions { flex-grow: 0; display: flex; gap: 8px; }
    #refundable-wrapper { display: none; } /* Initially hidden */

    .chart-grid { display: grid; grid-template-columns: 1fr; gap: 24px; }
    @media (min-width: 1024px) { .chart-grid { grid-template-columns: 1fr 1fr; } }
    .chart-wrap { min-height: 260px; position: relative; }
    .chart-currency-note { font-size: 12px; color: var(--text-muted); text-align: center; margin-top: 8px; }

    .summary-totals { font-size: 28px; font-weight: 700; line-height: 1.3; }
    .summary-totals .details { font-size: 14px; font-weight: 500; color: var(--text-muted); }
    .summary-line:not(:last-child) { margin-bottom: 16px; }
    
    .table-container { background: var(--bg-card); border-radius: var(--radius-lg); box-shadow: var(--shadow-md); border: 1px solid var(--border-color); overflow: hidden; }
    .table-header { padding: 20px 24px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border-color); }
    .table-wrapper { overflow-x: auto; }
    table { width: 100%; border-collapse: collapse; min-width: 1200px; }
    thead th {
      font-size: 12px; text-align: left; padding: 16px 18px; color: var(--text-muted); 
      border-bottom: 1px solid var(--border-color); text-transform: uppercase; letter-spacing: 0.5px; font-weight: 600;
    }
    thead th:first-child, tbody td:first-child { padding-left: 24px; }
    tbody tr { transition: background-color var(--transition-speed); }
    tbody tr:nth-child(even) { background-color: #fcfcfd; }
    tbody tr:hover { background-color: var(--accent-secondary); }
    tbody tr.selected-row { background-color: var(--accent-secondary-hover) !important; font-weight: 600; }
    tbody tr.editing-row { background-color: #fefce8 !important; }
    tbody td { padding: 16px 18px; border-bottom: 1px solid var(--border-color); vertical-align: middle; font-size: 14px; }
    tbody tr:last-child td { border-bottom: none; }
    tbody td.inline-edit-cell { padding: 6px; }
    tbody td.inline-edit-cell input, tbody td.inline-edit-cell select {
        width: 100%; padding: 8px; font-size: 13px; border: 1px solid var(--accent-primary);
        border-radius: var(--radius-sm); background-color: var(--bg-card);
    }
    .tx-number { font-weight: 600; }
    .tx-reference { font-size: 13px; color: var(--text-secondary); }
    .tx-amount { font-weight: 600; text-align: right; }
    .tx-actions { display: flex; gap: 8px; justify-content: flex-end;}

    .status-badge { padding: 4px 12px; border-radius: 999px; font-weight: 600; font-size: 12px; text-transform: capitalize; }
    .status-badge.expense { background: var(--danger-light); color: var(--danger-hover); }
    .status-badge.payment { background: var(--success-light); color: var(--success-hover); }
    .status-badge.Initiated { background-color: var(--blue-light); color: var(--blue); }
    .status-badge.Processed { background-color: var(--orange-light); color: var(--orange); }
    .status-badge.Completed { background-color: var(--success-light); color: var(--success); }
    .refundable-badge { padding: 4px 10px; border-radius: 6px; font-weight: 500; font-size: 12px; background-color: var(--orange-light); color: var(--orange); }

    .modal-overlay {
        position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(17, 24, 39, 0.6);
        backdrop-filter: blur(4px); display: none; align-items: center; justify-content: center; 
        z-index: 1000; opacity: 0; transition: opacity var(--transition-speed) ease;
    }
    .modal-overlay.is-visible { display: flex; opacity: 1; }
    .modal-content {
        background: white; padding: 24px; border-radius: var(--radius-lg); width: 90%; max-width: 500px;
        transform: scale(0.95) translateY(10px); transition: all var(--transition-speed) ease;
    }
    .modal-overlay.is-visible .modal-content { transform: scale(1) translateY(0); }
    #bulkEditForm { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
    #bulkEditForm .full-width { grid-column: 1 / -1; }
    .modal-actions { margin-top: 24px; display: flex; justify-content: flex-end; gap: 8px; }

    .skeleton-loader td { padding: 16px 18px; }
    .skeleton-loader .skeleton { background: #e5e7eb; border-radius: var(--radius-sm); animation: pulse 1.5s cubic-bezier(0.4, 0, 0.6, 1) infinite; }
    @keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.5; } }
    
    #inactivity-warning {
        position: fixed; top: 20px; left: 50%; transform: translateX(-50%);
        padding: 15px 25px; background-color: var(--bg-header); color: white;
        border-radius: var(--radius-md); z-index: 9999; box-shadow: var(--shadow-lg);
        display: flex; align-items: center; gap: 15px; opacity: 0;
        animation: fade-in 0.3s ease forwards;
    }
    @keyframes fade-in { to { opacity: 1; } }
  </style>
</head>
<body>

  <div style="display: none;">
    <svg xmlns="http://www.w3.org/2000/svg">
      <symbol id="icon-plus" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 5a1 1 0 011 1v3h3a1 1 0 110 2h-3v3a1 1 0 11-2 0v-3H6a1 1 0 110-2h3V6a1 1 0 011-1z" clip-rule="evenodd" /></symbol>
      <symbol id="icon-print" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5 4v3H4a2 2 0 00-2 2v6a2 2 0 002 2h12a2 2 0 002-2V9a2 2 0 00-2-2h-1V4a2 2 0 00-2-2H7a2 2 0 00-2 2zm8 0H7v3h6V4zm0 8H7V9h6v3z" clip-rule="evenodd" /></symbol>
      <symbol id="icon-export" viewBox="0 0 20 20" fill="currentColor"><path d="M10.75 2.75a.75.75 0 00-1.5 0v8.614L6.295 8.235a.75.75 0 10-1.09 1.03l4.25 4.5a.75.75 0 001.09 0l4.25-4.5a.75.75 0 00-1.09-1.03l-2.955 3.129V2.75z" /><path d="M3.5 12.75a.75.75 0 00-1.5 0v2.5A2.75 2.75 0 004.75 18h10.5A2.75 2.75 0 0018 15.25v-2.5a.75.75 0 00-1.5 0v2.5c0 .69-.56 1.25-1.25 1.25H4.75c-.69 0-1.25-.56-1.25-1.25v-2.5z" /></svg>
      <symbol id="icon-edit" viewBox="0 0 20 20" fill="currentColor"><path d="M17.414 2.586a2 2 0 00-2.828 0L7 10.172V13h2.828l7.586-7.586a2 2 0 000-2.828z" /><path fill-rule="evenodd" d="M2 6a2 2 0 012-2h4a1 1 0 010 2H4v10h10v-4a1 1 0 112 0v4a2 2 0 01-2 2H4a2 2 0 01-2-2V6z" clip-rule="evenodd" /></svg>
      <symbol id="icon-trash" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd" /></svg>
      <symbol id="icon-check" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" /></svg>
      <symbol id="icon-x" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" /></svg>
      <symbol id="icon-filter" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M3 3a1 1 0 011-1h12a1 1 0 011 1v3a1 1 0 01-.293.707L12 11.414V15a1 1 0 01-.293.707l-2 2A1 1 0 018 17v-5.586L3.293 6.707A1 1 0 013 6V3z" clip-rule="evenodd" /></svg>
      <symbol id="icon-empty" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79a9 9 0 1 1-11.21-1.95 7 7 0 0 0 7.21 7.21Z"></path></symbol>
    </svg>
  </div>
  
  <header class="page-header">
    <div class="header-content">
      <div class="brand"><div class="logo">FT</div><h1>Transactions</h1></div>
      <div class="controls">
        <button class="btn secondary" id="printBtn"><svg class="icon"><use href="#icon-print"/></svg>Print PDF</button>
        <button class="btn secondary" id="exportCsvBtn"><svg class="icon"><use href="#icon-export"/></svg>Export CSV</button>
        <button class="btn" id="addTxBtn"><svg class="icon"><use href="#icon-plus"/></svg>Add Transaction</button>
      </div>
    </div>
  </header>

  <main class="content-wrapper">
    <div class="dashboard-grid">
      <div id="addTransactionSection" class="card grid-span-12">
        <form id="txForm" autocomplete="off">
          <input type="hidden" id="f_id" name="id" />
          <div><label for="f_payment_date">Date</label><input type="date" id="f_payment_date" name="payment_date" required /></div>
          <div><label for="f_type">Type</label><select id="f_type" name="type"><option value="expense">Expense</option><option value="payment">Payment</option></select></div>
          <div id="refundable-wrapper"><label for="f_refundable">Refundable</label><select id="f_refundable" name="refundable"><option value="0">No</option><option value="1">Yes</option></select></div>
          <div><label for="f_number">Number</label><input type="text" id="f_number" name="number" readonly placeholder="Auto-generated" /></div>
          <div><label for="f_reference">Reference</label><input type="text" id="f_reference" name="reference" /></div>
          <div><label for="f_payment_method">Payment Method</label><select id="f_payment_method" name="payment_method"><option>MTN</option><option>BANK</option><option>CASH</option><option>OTHER</option></select></div>
          <div><label for="f_amount">Amount</label><input type="number" step="0.01" id="f_amount" name="amount" required /></div>
          <div><label for="f_currency">Currency</label><select id="f_currency" name="currency"><option>RWF</option><option>USD</option><option>EUR</option></select></div>
          <div><label for="f_status">Status</label><select id="f_status" name="status"><option>Initiated</option><option>Processed</option><option>Completed</option></select></div>
          <div style="flex-grow: 1.5;"><label for="f_note">Note</label><input type="text" id="f_note" name="note" /></div>
          <div class="form-actions">
            <button class="btn" type="submit"><svg class="icon"><use href="#icon-check"/></svg>Save</button>
            <button class="btn secondary" type="button" id="cancelAddBtn"><svg class="icon"><use href="#icon-x"/></svg>Cancel</button>
          </div>
        </form>
      </div>

      <div class="summary-card card grid-span-12 grid-span-lg-2">
        <h3 class="card-title">Net Summary</h3>
        <div id="summaryTotals" class="summary-totals">—</div>
      </div>
      <div class="summary-card card grid-span-12 grid-span-lg-2">
        <h3 class="card-title">To Be Refunded</h3>
        <div id="refundableTotals" class="summary-totals">—</div>
      </div>
      <div class="chart-card card grid-span-12 grid-span-lg-8">
        <div class="chart-grid">
            <div><h3 class="card-title">Payment vs Expense</h3><div class="chart-wrap"><canvas id="pieChart"></canvas></div><p id="pieChartNote" class="chart-currency-note"></p></div>
            <div><h3 class="card-title">By Payment Method</h3><div class="chart-wrap"><canvas id="barChart"></canvas></div><p id="barChartNote" class="chart-currency-note"></p></div>
        </div>
      </div>
      
      <div class="filters-card card grid-span-12">
        <input type="date" id="fromDateFilter" title="Start Date"/>
        <input type="date" id="toDateFilter" title="End Date"/>
        <select id="typeFilter" title="Transaction Type"><option value="all">All Types</option><option value="payment">Payment</option><option value="expense">Expense</option></select>
        <select id="currencyFilter" title="Currency"><option value="all">All Currencies</option></select>
        <input type="text" id="searchFilter" placeholder="Search anything..." class="grow" autocomplete="off" />
        <button class="btn secondary" id="applyFilterBtn"><svg class="icon"><use href="#icon-filter"/></svg>Apply</button>
      </div>

      <div class="table-container grid-span-12">
        <div class="table-header">
          <h3 class="card-title">All Transactions (<span id="txCount">0</span>)</h3>
          <button class="btn danger" id="bulkEditBtn" style="display: none;"><svg class="icon"><use href="#icon-edit"/></svg>Bulk Edit Selected</button>
        </div>
        <div class="table-wrapper">
          <table>
              <thead><tr><th><input type="checkbox" id="selectAllCheckbox"></th><th>Date</th><th>Type</th><th>Number / Reference</th><th>Payment Method</th><th>Note</th><th style="text-align: right;">Amount</th><th>Status</th><th>Refundable</th><th style="text-align: right;">Actions</th></tr></thead>
              <tbody id="txTableBody"></tbody>
          </table>
        </div>
      </div>
    </div>
  </main>

  <div id="bulkEditModal" class="modal-overlay">
    <div class="modal-content">
      <h3 class="card-title">Bulk Edit Transactions</h3>
      <p style="margin-top: -10px; margin-bottom: 20px; color: var(--text-secondary);">Selected: <strong id="bulkEditCount">0</strong> transactions. Only filled fields will be updated.</p>
      <form id="bulkEditForm">
        <div><label for="b_payment_date">Date</label><input type="date" id="b_payment_date" name="payment_date" /></div>
        <div><label for="b_type">Type</label><select id="b_type" name="type"><option value="">--</option><option value="expense">Expense</option><option value="payment">Payment</option></select></div>
        <div><label for="b_status">Status</label><select id="b_status" name="status"><option value="">--</option><option value="Initiated">Initiated</option><option>Processed</option><option>Completed</option></select></div>
        <div><label for="b_refundable">Refundable</label><select id="b_refundable" name="refundable"><option value="">--</option><option value="1">Yes</option><option value="0">No</option></select></div>
        <div class="full-width"><label for="b_payment_method">Payment Method</label><select id="b_payment_method" name="payment_method"><option value="">--</option><option>MTN</option><option>BANK</option><option>CASH</option><option>OTHER</option></select></div>
        <div class="full-width"><label for="b_note">Note</label><input type="text" id="b_note" name="note" /></div>
      </form>
      <div class="modal-actions">
        <button class="btn secondary" id="cancelBulkEditBtn">Cancel</button>
        <button class="btn" id="saveBulkEditBtn">Save Changes</button>
      </div>
    </div>
  </div>

  <script>
    // --- Inactivity Timer ---
    (function() {
        const LOGOUT_TIME = 5 * 60 * 1000; const WARNING_TIME = 4 * 60 * 1000;
        let logoutTimer, warningTimer;
        const logoutUser = () => { window.location.href = 'logout.php'; };
        const showWarning = () => { if (document.getElementById('inactivity-warning')) return; const warningDiv = document.createElement('div'); warningDiv.id = 'inactivity-warning'; warningDiv.innerHTML = 'You will be logged out in 1 minute due to inactivity. <button id="stayLoggedIn">Stay Logged In</button>'; document.body.appendChild(warningDiv); document.getElementById('stayLoggedIn').onclick = () => resetTimers();};
        const resetTimers = () => { clearTimeout(warningTimer); clearTimeout(logoutTimer); const warningDiv = document.getElementById('inactivity-warning'); if (warningDiv) warningDiv.remove(); warningTimer = setTimeout(showWarning, WARNING_TIME); logoutTimer = setTimeout(logoutUser, LOGOUT_TIME); };
        ['click', 'mousemove', 'keydown', 'scroll'].forEach(event => document.addEventListener(event, resetTimers, true));
        resetTimers();
    })();

    // --- Main Application Logic ---
    document.addEventListener('DOMContentLoaded', () => {
        let allTransactions = [];
        let charts = {};
        const API_URL = 'api_transactions.php';

        const elements = {
            txTableBody: document.getElementById('txTableBody'),
            addTxBtn: document.getElementById("addTxBtn"),
            addTransactionSection: document.getElementById("addTransactionSection"),
            txForm: document.getElementById("txForm"),
            cancelAddBtn: document.getElementById("cancelAddBtn"),
            txCount: document.getElementById('txCount'),
            fromDateFilter: document.getElementById('fromDateFilter'),
            toDateFilter: document.getElementById('toDateFilter'),
            typeFilter: document.getElementById('typeFilter'),
            currencyFilter: document.getElementById('currencyFilter'),
            searchFilter: document.getElementById('searchFilter'),
            applyFilterBtn: document.getElementById('applyFilterBtn'),
            printBtn: document.getElementById('printBtn'),
            exportCsvBtn: document.getElementById('exportCsvBtn'),
            selectAllCheckbox: document.getElementById('selectAllCheckbox'),
            bulkEditBtn: document.getElementById('bulkEditBtn'),
            bulkEditModal: document.getElementById('bulkEditModal'),
            bulkEditForm: document.getElementById('bulkEditForm'),
            bulkEditCount: document.getElementById('bulkEditCount'),
            cancelBulkEditBtn: document.getElementById('cancelBulkEditBtn'),
            saveBulkEditBtn: document.getElementById('saveBulkEditBtn'),
            pieChartNote: document.getElementById('pieChartNote'),
            barChartNote: document.getElementById('barChartNote'),
            refundableTotals: document.getElementById('refundableTotals'),
            refundableWrapper: document.getElementById('refundable-wrapper'),
            formType: document.getElementById('f_type'),
        };
        
        const showSkeletonLoader = (rows = 8) => {
            let skeletonHTML = '';
            for (let i = 0; i < rows; i++) {
                skeletonHTML += `<tr class="skeleton-loader">
                    <td><div class="skeleton" style="width: 20px; height: 20px;"></div></td>
                    <td><div class="skeleton" style="width: 80px; height: 16px;"></div></td>
                    <td><div class="skeleton" style="width: 90px; height: 24px; border-radius: 999px;"></div></td>
                    <td><div class="skeleton" style="width: 120px; height: 16px;"></div><div class="skeleton" style="width: 150px; height: 12px; margin-top: 6px;"></div></td>
                    <td><div class="skeleton" style="width: 60px; height: 16px;"></div></td>
                    <td><div class="skeleton" style="width: 180px; height: 16px;"></div></td>
                    <td style="text-align: right;"><div class="skeleton" style="width: 100px; height: 16px; margin-left: auto;"></div></td>
                    <td><div class="skeleton" style="width: 90px; height: 24px; border-radius: 999px;"></div></td>
                    <td><div class="skeleton" style="width: 60px; height: 16px;"></div></td>
                    <td style="text-align: right;"><div class="skeleton" style="width: 120px; height: 32px; border-radius: 6px; margin-left: auto;"></div></td>
                </tr>`;
            }
            elements.txTableBody.innerHTML = skeletonHTML;
        };

        const fetchTransactions = async (filters = {}) => {
            showSkeletonLoader();
            try {
                // Legacy support for notes-based refundable status
                const response = await apiCall('GET', filters);
                if (response && response.success) {
                    allTransactions = response.data.map(tx => {
                        // Check for new 'refundable' field, fallback to note check
                        if (tx.refundable === null || tx.refundable === undefined) {
                            tx.refundable = /(to be refunded|refundable)/i.test(tx.note) ? '1' : '0';
                        }
                        return tx;
                    });
                    renderUI(allTransactions);
                } else {
                    showErrorState('Failed to load transactions. The server returned an error.');
                }
            } catch (error) {
                console.error("Fetch Error:", error);
                showErrorState(`Could not connect to the server. (${error.message})`);
            }
        };
        
        const showErrorState = (message) => {
             elements.txTableBody.innerHTML = `<tr><td colspan="10" style="text-align:center; padding: 60px 24px; color: var(--text-muted);">
              <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1" style="margin-bottom: 16px;"><use href="#icon-empty"/></svg>
              <h4 style="margin:0 0 4px;">An Error Occurred</h4>
              <p style="margin:0;">${escapeHtml(message)}</p>
            </td></tr>`;
        }

        const renderUI = (transactions) => {
            cancelEditing();
            renderTable(transactions);
            updateSummary(transactions);
            updateRefundableSummary(transactions);
            updateCharts(transactions);
            updateCurrencyFilterOptions(transactions);
            elements.txCount.textContent = transactions.length;
            updateBulkEditVisibility();
        };

        const renderTable = (transactions) => {
            elements.txTableBody.innerHTML = '';
            if (transactions.length === 0) {
                elements.txTableBody.innerHTML = `<tr><td colspan="10" style="text-align:center; padding: 60px 24px; color: var(--text-muted);">
                  <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1" style="margin-bottom: 16px;"><use href="#icon-empty"/></svg>
                  <h4 style="margin:0 0 4px;">No Transactions Found</h4>
                  <p style="margin:0;">No transactions match your current filter criteria.</p>
                </td></tr>`;
                return;
            }
            transactions.forEach(tx => {
                const tr = document.createElement('tr');
                tr.dataset.id = tx.id;
                tr.innerHTML = `
                    <td><input type="checkbox" class="row-checkbox" data-id="${tx.id}"></td>
                    <td data-field="payment_date">${escapeHtml(tx.payment_date)}</td>
                    <td data-field="type"><span class="status-badge ${escapeHtml(tx.type)}">${capitalize(tx.type)}</span></td>
                    <td data-field="reference"><div class="tx-number">${escapeHtml(tx.number)}</div><div class="tx-reference">${escapeHtml(tx.reference)}</div></td>
                    <td data-field="payment_method">${escapeHtml(tx.payment_method)}</td>
                    <td data-field="note">${escapeHtml(tx.note)}</td>
                    <td data-field="amount" class="tx-amount">${parseFloat(tx.amount).toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2})} ${escapeHtml(tx.currency)}</td>
                    <td data-field="status"><span class="status-badge ${escapeHtml(tx.status)}">${escapeHtml(tx.status)}</span></td>
                    <td data-field="refundable">${tx.type === 'expense' && tx.refundable == '1' ? '<span class="refundable-badge">Yes</span>' : '—'}</td>
                    <td class="tx-actions">
                        <button class="btn secondary btn-sm edit-btn"><svg class="icon"><use href="#icon-edit"/></svg>Edit</button>
                        <button class="btn danger btn-sm delete-btn" data-id="${tx.id}"><svg class="icon"><use href="#icon-trash"/></svg>Delete</button>
                    </td>
                `;
                elements.txTableBody.appendChild(tr);
            });
        };
        
        const cancelEditing = () => {
            const editingRow = document.querySelector('tr.editing-row');
            if (editingRow && editingRow.dataset.originalHtml) {
                editingRow.innerHTML = editingRow.dataset.originalHtml;
                editingRow.classList.remove('editing-row');
                delete editingRow.dataset.originalHtml;
            }
        };

        const updateSummary = (transactions) => {
            const totals = {};
            transactions.forEach(tx => {
                const currency = tx.currency || 'N/A';
                if (!totals[currency]) totals[currency] = { payment: 0, expense: 0 };
                const amount = parseFloat(tx.amount);
                if (tx.type === 'payment') totals[currency].payment += amount;
                else if (tx.type === 'expense') totals[currency].expense += amount;
            });

            const summaryEl = document.getElementById("summaryTotals");
            summaryEl.innerHTML = Object.keys(totals).length === 0 ? '—' : '';
            for (const currency in totals) {
                const { payment, expense } = totals[currency];
                const net = payment - expense;
                const netColor = net >= 0 ? 'var(--success)' : 'var(--danger)';
                summaryEl.innerHTML += `<div class="summary-line">
                    <span class="net-amount" style="color: ${netColor}">${net.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})} ${escapeHtml(currency)}</span>
                    <span class="details">
                        <span style="color: var(--success)">↑${payment.toLocaleString(undefined, {minimumFractionDigits: 2})}</span> / 
                        <span style="color: var(--danger)">↓${expense.toLocaleString(undefined, {minimumFractionDigits: 2})}</span>
                    </span></div>`;
            }
        };
        
        const updateRefundableSummary = (transactions) => {
            const totals = {};
            const refundableExpenses = transactions.filter(tx => tx.type === 'expense' && tx.refundable == '1');
            
            refundableExpenses.forEach(tx => {
                const currency = tx.currency || 'N/A';
                if (!totals[currency]) totals[currency] = 0;
                totals[currency] += parseFloat(tx.amount);
            });

            elements.refundableTotals.innerHTML = Object.keys(totals).length === 0 ? '—' : '';
            for (const currency in totals) {
                elements.refundableTotals.innerHTML += `<div class="summary-line">
                    <span class="net-amount" style="color: var(--orange);">${totals[currency].toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})} ${escapeHtml(currency)}</span>
                </div>`;
            }
        };

        const getDominantCurrency = (transactions) => {
            if (!transactions.length) return null;
            const counts = transactions.reduce((acc, tx) => { acc[tx.currency] = (acc[tx.currency] || 0) + 1; return acc; }, {});
            return Object.keys(counts).reduce((a, b) => counts[a] > counts[b] ? a : b);
        };

        const updateCharts = (transactions) => {
            destroyCharts();
            elements.pieChartNote.textContent = '';
            elements.barChartNote.textContent = '';
            if (!transactions.length) return;

            const dominantCurrency = getDominantCurrency(transactions);
            const filteredTx = transactions.filter(tx => tx.currency === dominantCurrency);
            
            const note = `Chart data shown for dominant currency: ${dominantCurrency}`;
            elements.pieChartNote.textContent = note;
            elements.barChartNote.textContent = note;

            const paymentTotal = filteredTx.filter(t => t.type === 'payment').reduce((sum, t) => sum + parseFloat(t.amount), 0);
            const expenseTotal = filteredTx.filter(t => t.type === 'expense').reduce((sum, t) => sum + parseFloat(t.amount), 0);
            const byPayment = filteredTx.reduce((acc, tx) => {
                const method = tx.payment_method || 'Unknown';
                acc[method] = (acc[method] || 0) + parseFloat(tx.amount);
                return acc;
            }, {});

            const chartOptions = { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom', labels: { font: { size: 12, family: "Inter" } } } } };
            charts.pie = new Chart('pieChart', { type: 'doughnut', data: { labels: ['Payment', 'Expense'], datasets: [{ data: [paymentTotal, expenseTotal], backgroundColor: [ 'rgba(16, 185, 129, 0.9)', 'rgba(239, 68, 68, 0.9)'], borderColor: 'var(--bg-card)', borderWidth: 4 }] }, options: chartOptions });
            charts.bar = new Chart('barChart', { type: 'bar', data: { labels: Object.keys(byPayment), datasets: [{ label: `Total Amount (${dominantCurrency})`, data: Object.values(byPayment), backgroundColor: 'rgba(59, 130, 246, 0.8)', borderRadius: 4 }] }, options: { ...chartOptions, scales: { y: { beginAtZero: true } } } });
        };
        
        async function apiCall(method = 'GET', data = null) {
            const options = { method, headers: {} };
            let url = API_URL;
            if (method === 'POST') {
                options.headers['Content-Type'] = 'application/json';
                options.body = JSON.stringify(data);
            } else if (data) {
                url += '?' + new URLSearchParams(Object.fromEntries(Object.entries(data).filter(([_, v]) => v != null && v !== '')));
            }
            const response = await fetch(url, options);
            if (response.headers.get('Content-Type')?.includes('application/pdf')) {
                return response;
            }
            if (!response.ok) {
                const errJson = await response.json().catch(() => ({error: `HTTP error! Status: ${response.status}`}));
                throw new Error(errJson.error || 'An unknown error occurred.');
            }
            return response.json();
        }
        
        const getFilterState = () => ({ 
            from: elements.fromDateFilter.value, to: elements.toDateFilter.value, 
            type: elements.typeFilter.value, currency: elements.currencyFilter.value, 
            q: elements.searchFilter.value.trim() 
        });
        
        // --- Event Listeners ---

        elements.applyFilterBtn.onclick = () => fetchTransactions(getFilterState());
        
        let debounceTimeout;
        elements.searchFilter.addEventListener('input', () => {
            clearTimeout(debounceTimeout);
            debounceTimeout = setTimeout(() => fetchTransactions(getFilterState()), 400);
        });

        elements.formType.addEventListener('change', (e) => {
            elements.refundableWrapper.style.display = e.target.value === 'expense' ? 'block' : 'none';
        });

        elements.txTableBody.addEventListener('click', async (e) => {
            const button = e.target.closest('button');
            if (!button) return;

            if (button.classList.contains('delete-btn')) {
                const id = button.dataset.id;
                if (confirm('Are you sure you want to delete this transaction? This action cannot be undone.')) {
                    const res = await apiCall('POST', { action: 'delete', id });
                    if (res && res.success) fetchTransactions(getFilterState());
                }
            }

            if (button.classList.contains('edit-btn')) {
                const tr = button.closest('tr');
                const tx = allTransactions.find(t => t.id == tr.dataset.id);
                if (!tx) return;
                
                cancelEditing();
                tr.dataset.originalHtml = tr.innerHTML;
                tr.classList.add('editing-row');

                const refundableSelectHtml = tx.type === 'expense' ? `<select name="refundable"><option value="0">No</option><option value="1">Yes</option></select>` : '—';
                tr.cells[1].innerHTML = `<input type="date" name="payment_date" value="${tx.payment_date}">`;
                tr.cells[2].innerHTML = `<select name="type"><option value="expense">Expense</option><option value="payment">Payment</option></select>`;
                tr.cells[3].innerHTML = `<input type="text" name="reference" value="${tx.reference}">`;
                tr.cells[4].innerHTML = `<select name="payment_method"><option>MTN</option><option>BANK</option><option>CASH</option><option>OTHER</option></select>`;
                tr.cells[5].innerHTML = `<input type="text" name="note" value="${tx.note}">`;
                tr.cells[6].innerHTML = `<input type="number" step="0.01" name="amount" value="${tx.amount}" style="width: 70px; margin-right: 5px;"><select name="currency" style="width: 60px;"><option>RWF</option><option>USD</option><option>EUR</option></select>`;
                tr.cells[7].innerHTML = `<select name="status"><option>Initiated</option><option>Processed</option><option>Completed</option></select>`;
                tr.cells[8].innerHTML = refundableSelectHtml;
                tr.cells[9].innerHTML = `<div class="tx-actions"><button class="btn success btn-sm save-btn"><svg class="icon"><use href="#icon-check"/></svg>Save</button><button class="btn secondary btn-sm cancel-edit-btn"><svg class="icon"><use href="#icon-x"/></svg>Cancel</button></div>`;

                tr.querySelector('[name="type"]').value = tx.type;
                tr.querySelector('[name="payment_method"]').value = tx.payment_method;
                tr.querySelector('[name="currency"]').value = tx.currency;
                tr.querySelector('[name="status"]').value = tx.status;
                if(tx.type === 'expense') {
                    tr.querySelector('[name="refundable"]').value = tx.refundable;
                }
                Array.from(tr.cells).slice(1, 9).forEach(cell => cell.classList.add('inline-edit-cell'));
            }

            if (button.classList.contains('cancel-edit-btn')) { cancelEditing(); }

            if (button.classList.contains('save-btn')) {
                const tr = button.closest('tr');
                const id = tr.dataset.id;
                const data = { id, action: 'update' };
                tr.querySelectorAll('input, select').forEach(input => { data[input.name] = input.value; });
                
                button.disabled = true; button.innerHTML = 'Saving...';
                const response = await apiCall('POST', data);
                if (response && response.success) {
                    fetchTransactions(getFilterState());
                } else {
                    alert('Failed to update transaction.');
                    cancelEditing();
                }
            }
        });

        elements.txForm.addEventListener("submit", async (e) => {
            e.preventDefault();
            const formData = new FormData(elements.txForm);
            const data = Object.fromEntries(formData.entries());
            data.action = data.id ? 'update' : 'create';
            
            if (data.type !== 'expense') {
                data.refundable = '0';
            }

            const submitBtn = elements.txForm.querySelector('button[type="submit"]');
            submitBtn.disabled = true; submitBtn.innerHTML = 'Saving...';
            
            const response = await apiCall('POST', data);
            if (response && response.success) {
                fetchTransactions(getFilterState());
                resetAndHideForm();
            } else {
                alert(response.error || 'Failed to save transaction');
            }
            submitBtn.disabled = false; submitBtn.innerHTML = `<svg class="icon"><use href="#icon-check"/></svg>Save`;
        });
        
        elements.saveBulkEditBtn.onclick = async () => {
            const ids = getSelectedIds();
            const formData = new FormData(elements.bulkEditForm);
            const updates = Object.fromEntries(Object.entries(formData.entries()).filter(([_, v]) => v !== ''));
            if (Object.keys(updates).length === 0) { alert('Please select at least one field to update.'); return; }
            
            const response = await apiCall('POST', { action: 'bulk_update', ids, updates });
            if (response && response.success) {
                toggleBulkEditModal(false);
                fetchTransactions(getFilterState());
                elements.selectAllCheckbox.checked = false;
            }
        };

        elements.printBtn.onclick = async () => {
            elements.printBtn.disabled = true; elements.printBtn.innerHTML = 'Generating...';
            const payload = { action: 'print', filters: getFilterState(), pieChartImage: charts.pie ? charts.pie.toBase64Image() : null, barChartImage: charts.bar ? charts.bar.toBase64Image() : null };
            try {
                const response = await fetch(API_URL, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) });
                if (!response.ok) { throw new Error( (await response.json()).error || 'PDF generation failed.' ); }
                const blob = await response.blob();
                const url = window.URL.createObjectURL(blob);
                window.open(url, '_blank');
            } catch (error) { alert(`Could not generate PDF: ${error.message}`);
            } finally { elements.printBtn.disabled = false; elements.printBtn.innerHTML = `<svg class="icon"><use href="#icon-print"/></svg>Print PDF`; }
        };

        elements.exportCsvBtn.onclick = () => {
            if (allTransactions.length === 0) { alert("There is no data to export."); return; }
            const headers = ["ID", "Date", "Type", "Number", "Reference", "Payment Method", "Amount", "Currency", "Status", "Note", "Refundable"];
            const data = allTransactions.map(tx => [tx.id, tx.payment_date, tx.type, tx.number, tx.reference, tx.payment_method, tx.amount, tx.currency, tx.status, tx.note, tx.refundable == '1' ? 'Yes' : 'No']);
            const worksheet = XLSX.utils.aoa_to_sheet([headers, ...data]);
            const workbook = XLSX.utils.book_new();
            XLSX.utils.book_append_sheet(workbook, worksheet, "Transactions");
            XLSX.writeFile(workbook, "Feza_Logistics_Transactions.xlsx");
        };
        
        const resetAndHideForm = () => { elements.txForm.reset(); elements.txForm.id.value = ''; toggleAddForm(false); };
        const toggleAddForm = (show) => {
            if (show) {
                elements.addTransactionSection.classList.add('is-visible');
                elements.addTxBtn.style.display = "none";
                elements.txForm.payment_date.valueAsDate = new Date();
                elements.refundableWrapper.style.display = elements.formType.value === 'expense' ? 'block' : 'none';
                elements.txForm.payment_date.focus();
            } else {
                elements.addTransactionSection.classList.remove('is-visible');
                elements.addTxBtn.style.display = "inline-flex";
            }
        };
        elements.addTxBtn.onclick = () => toggleAddForm(true);
        elements.cancelAddBtn.onclick = resetAndHideForm;
        
        const toggleBulkEditModal = (show) => {
          if(show) {
            elements.bulkEditCount.textContent = getSelectedIds().length;
            elements.bulkEditModal.classList.add('is-visible');
          } else {
            elements.bulkEditModal.classList.remove('is-visible');
            elements.bulkEditForm.reset();
          }
        }
        elements.bulkEditBtn.onclick = () => toggleBulkEditModal(true);
        elements.cancelBulkEditBtn.onclick = () => toggleBulkEditModal(false);

        const getSelectedIds = () => Array.from(document.querySelectorAll('.row-checkbox:checked')).map(cb => cb.dataset.id);
        const updateBulkEditVisibility = () => { elements.bulkEditBtn.style.display = getSelectedIds().length > 0 ? 'inline-flex' : 'none'; }
        elements.selectAllCheckbox.addEventListener('change', (e) => {
            document.querySelectorAll('.row-checkbox').forEach(cb => { cb.checked = e.target.checked; cb.closest('tr').classList.toggle('selected-row', cb.checked); });
            updateBulkEditVisibility();
        });
        elements.txTableBody.addEventListener('change', (e) => {
            if (e.target.classList.contains('row-checkbox')) {
                e.target.closest('tr').classList.toggle('selected-row', e.target.checked);
                updateBulkEditVisibility();
            }
        });

        const updateCurrencyFilterOptions = (transactions) => {
            const currentVal = elements.currencyFilter.value;
            const existingOptions = new Set(Array.from(elements.currencyFilter.options).map(o => o.value));
            const newCurrencies = new Set(transactions.map(tx => tx.currency));
            newCurrencies.forEach(cur => {
                if (cur && !existingOptions.has(cur)) {
                    elements.currencyFilter.add(new Option(cur, cur));
                }
            });
            elements.currencyFilter.value = currentVal;
        };
        
        // --- Helper Functions ---
        const capitalize = (str) => str ? str.charAt(0).toUpperCase() + str.slice(1) : '';
        const escapeHtml = (str) => String(str || '').replace(/[&<>"']/g, m => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'})[m]);
        const destroyCharts = () => { Object.values(charts).forEach(chart => chart && chart.destroy()); };
        
        // Initial data load
        fetchTransactions();
    });
  </script>
</body>
</html>