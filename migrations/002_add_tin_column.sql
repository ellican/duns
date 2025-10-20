-- Migration: Add TIN column to clients table
-- Date: 2025-10-20
-- Description: This migration adds a TIN (Tax Identification Number) column to the clients table
--              The column stores numeric values up to 9 digits

ALTER TABLE `clients` 
ADD COLUMN `TIN` VARCHAR(9) DEFAULT NULL AFTER `Responsible`,
ADD INDEX `idx_tin` (`TIN`);
