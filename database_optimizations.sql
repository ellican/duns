-- Recommended indexes for the `clients` table to significantly improve
-- the performance of searching, filtering, and sorting on the dashboard.
-- Run these commands on your `duns` database.

-- Index for the main search functionality (name, reg_no, phone)
ALTER TABLE `clients` ADD INDEX `idx_search` (`client_name`, `reg_no`, `phone_number`);

-- Index for filtering by date
ALTER TABLE `clients` ADD INDEX `idx_date` (`date`);

-- Index for filtering by status and currency
ALTER TABLE `clients` ADD INDEX `idx_status_currency` (`status`, `currency`);

-- Index for currency grouping for the dashboard cards
ALTER TABLE `clients` ADD INDEX `idx_currency` (`currency`);