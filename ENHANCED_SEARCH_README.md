# Enhanced Transaction Search Functionality

## Overview
The transaction search functionality has been significantly enhanced to provide comprehensive search capabilities across all relevant fields and related data.

## Enhancements Made

### 1. Database JOINs
- **wp_ea_contacts**: Added LEFT JOIN to search contact names associated with transactions
- **wp_ea_categories**: Added LEFT JOIN to search category names associated with transactions
- Uses COALESCE to handle NULL values gracefully
- LEFT JOINs ensure backward compatibility even if related tables don't exist

### 2. Expanded Search Fields
The search now covers:
- **Transaction Fields**: number, reference, note, status, payment_method, type, amount
- **Contact Names**: From wp_ea_contacts.name field
- **Category Names**: From wp_ea_categories.name field
- **Formatted Dates**: Multiple date formats for flexible date searching

### 3. Date Format Support
Search now supports multiple date formats:
- `YYYY-MM-DD` (2024-01-15)
- `DD/MM/YYYY` (15/01/2024)
- `MM/DD/YYYY` (01/15/2024)
- `YYYY/MM/DD` (2024/01/15)

### 4. Numeric Search
- Exact amount matching for numeric search queries
- Maintains existing numeric search functionality

## Files Modified

### api_transactions.php
- Enhanced `build_get_query()` function with JOINs and comprehensive search
- Added contact_name and category_name to SELECT clause
- Maintains existing functionality and error handling

### fetch_transactions.php
- Enhanced query with same JOINs and search capabilities
- Updated search logic to include contact and category names
- Added multiple date format searching

## Required Database Tables

For full functionality, these tables should exist:

```sql
-- wp_ea_contacts table
CREATE TABLE IF NOT EXISTS `wp_ea_contacts` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `name` varchar(191) NOT NULL,
  `email` varchar(191) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `name_index` (`name`)
);

-- wp_ea_categories table  
CREATE TABLE IF NOT EXISTS `wp_ea_categories` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `name` varchar(191) NOT NULL,
  `description` text DEFAULT NULL,
  `type` enum('expense','payment','both') DEFAULT 'both',
  PRIMARY KEY (`id`),
  KEY `name_index` (`name`)
);
```

## Backward Compatibility
- If wp_ea_contacts or wp_ea_categories tables don't exist, the search will still work
- LEFT JOINs with COALESCE ensure no errors occur with missing tables
- All existing search functionality is preserved
- No breaking changes to existing API endpoints

## Security
- All search parameters use prepared statements
- SQL injection prevention maintained
- Parameter binding with unique parameter names

## Usage Examples

### Search by Contact Name
```
GET /api_transactions.php?q=John
```
Will find transactions associated with contacts containing "John"

### Search by Category
```
GET /api_transactions.php?q=Office
```
Will find transactions in categories containing "Office"

### Search by Date
```
GET /api_transactions.php?q=2024-01-15
```
Will find transactions on January 15, 2024

### Combined Search with Filters
```
GET /api_transactions.php?type=expense&q=travel
```
Will find expense transactions containing "travel" in any searchable field

## Performance Considerations
- Added indexes on name fields in related tables
- LEFT JOINs are optimized for performance
- Query structure maintains efficiency with proper indexing