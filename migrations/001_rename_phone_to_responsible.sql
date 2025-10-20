-- Migration: Rename Phone column to Responsible in clients table
-- Date: 2025-10-20
-- Description: This migration renames the 'phone_number' column to 'Responsible' in the clients table

ALTER TABLE `clients` 
CHANGE COLUMN `phone_number` `Responsible` VARCHAR(20) DEFAULT NULL;
