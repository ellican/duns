-- Add currency columns to tables if they don't already exist
-- This script is safe to run multiple times

-- Add currency column to invoices table
ALTER TABLE `invoices` ADD COLUMN `currency` VARCHAR(3) DEFAULT 'RWF' AFTER `total`;

-- Add currency column to quotations table  
ALTER TABLE `quotations` ADD COLUMN `currency` VARCHAR(3) DEFAULT 'RWF' AFTER `total`;

-- Receipts get currency from related invoice, so no need to add currency column there

-- Update any existing records to have default currency
UPDATE `invoices` SET `currency` = 'RWF' WHERE `currency` IS NULL OR `currency` = '';
UPDATE `quotations` SET `currency` = 'RWF' WHERE `currency` IS NULL OR `currency` = '';