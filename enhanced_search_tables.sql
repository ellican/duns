-- Enhanced search functionality requires these tables
-- These tables are referenced in the wp_ea_transactions table via foreign keys

-- Create wp_ea_contacts table if it doesn't exist
CREATE TABLE IF NOT EXISTS `wp_ea_contacts` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `name` varchar(191) NOT NULL,
  `email` varchar(191) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `name_index` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create wp_ea_categories table if it doesn't exist
CREATE TABLE IF NOT EXISTS `wp_ea_categories` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `name` varchar(191) NOT NULL,
  `description` text DEFAULT NULL,
  `type` enum('expense','payment','both') DEFAULT 'both',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `name_index` (`name`),
  KEY `type_index` (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert sample contacts
INSERT IGNORE INTO `wp_ea_contacts` (`id`, `name`, `email`, `phone`) VALUES
(1, 'John Doe', 'john.doe@example.com', '+1234567890'),
(2, 'Jane Smith', 'jane.smith@example.com', '+0987654321'),
(3, 'Acme Corporation', 'billing@acme.com', '+1122334455'),
(4, 'Tech Solutions Ltd', 'contact@techsolutions.com', '+2233445566');

-- Insert sample categories
INSERT IGNORE INTO `wp_ea_categories` (`id`, `name`, `description`, `type`) VALUES
(1, 'Office Supplies', 'General office supplies and equipment', 'expense'),
(2, 'Travel & Transportation', 'Business travel, fuel, transportation costs', 'expense'),
(3, 'Client Payments', 'Payments received from clients', 'payment'),
(4, 'Software & Technology', 'Software licenses, hardware, IT services', 'expense'),
(5, 'Marketing & Advertising', 'Marketing campaigns, advertising costs', 'expense'),
(6, 'Professional Services', 'Legal, accounting, consulting fees', 'expense');