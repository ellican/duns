# AI Assistant Logging and Error Handling - Implementation Guide

## Overview

This document describes the comprehensive logging and error handling improvements made to the AI assistant (`ai_assistant.php`) to diagnose and fix the generic error message issue.

## Problem Statement

The AI assistant was returning a generic error message: "I encountered an error processing your request. Please try rephrasing your question or ask something simpler."

This error provided no information about:
- What went wrong
- Where in the process the error occurred
- How to fix the issue

## Solution Implemented

### 1. Comprehensive Logging System

Added a `logDebug()` function that writes detailed logs to `logs/ai_assistant.log` with:
- Timestamp for each entry
- Structured data output using `print_r()`
- Clear section separators for readability

### 2. Enhanced Error Tracking

The system now logs:

#### A. cURL Requests to Ollama API
```php
=== OLLAMA API REQUEST ===
URL: http://localhost:11434/api/generate
Payload:
    - model: tinyllama
    - prompt: [full system prompt + user query]
    - stream: false
    - options: temperature, top_p, num_predict
```

#### B. Raw JSON Responses from Ollama
```php
=== OLLAMA API RESPONSE ===
HTTP Code: 200
Raw Response: [complete JSON response]
AI Generated Response: [SQL query from model]
```

#### C. SQL Query Extraction and Validation
```php
=== EXTRACTING SQL ===
AI Response to Parse: [raw AI output]
Cleaned SQL: [after markdown removal]
Final Validated SQL: [with LIMIT clause added]
```

#### D. Database Query Execution
```php
=== EXECUTING SQL ===
Query: SELECT * FROM clients WHERE status = 'PAID' LIMIT 100
Query executed successfully
Result count: 15
First row sample: [data from first result]
```

#### E. Error Details
```php
=== REQUEST FAILED ===
Error Type: error
Error Message: Failed to connect to Ollama API: Connection refused
Execution time: 125ms
```

### 3. Improved Error Messages

The system now provides context-specific error messages:

| Error Type | User Message |
|------------|-------------|
| Ollama connection failure | "The AI service is currently unavailable. Please contact your system administrator." |
| SQL execution error | "I had trouble running the query. Please try rephrasing your question or simplifying your request." |
| Invalid query type | "I can only answer questions that retrieve data. I cannot modify or delete information." |
| Generic errors | "I encountered an error processing your request. Please try rephrasing your question or ask something simpler." |

### 4. Enhanced cURL Error Handling

Added detailed cURL error tracking:
- Capture `curl_error()` for connection issues
- Log HTTP status codes
- Validate response before JSON parsing
- Check for JSON decode errors with `json_last_error_msg()`

## Log File Structure

### Location
```
/home/runner/work/duns/duns/logs/ai_assistant.log
```

### Format
```
[2025-10-20 12:35:38] === NEW AI ASSISTANT REQUEST ===
User ID: 5
Session ID: abc123xyz
User Query: Show me total revenue this month
--------------------------------------------------------------------------------
[2025-10-20 12:35:38] === OLLAMA API REQUEST ===
URL: http://localhost:11434/api/generate
Payload:
Array
(
    [model] => tinyllama
    [prompt] => [system prompt + query]
    [stream] => false
    ...
)
--------------------------------------------------------------------------------
[2025-10-20 12:35:39] === OLLAMA API RESPONSE ===
HTTP Code: 200
Raw Response: {"model":"tinyllama","response":"SELECT SUM..."}
AI Generated Response: SELECT SUM(paid_amount) FROM clients WHERE MONTH(date) = MONTH(CURRENT_DATE())
--------------------------------------------------------------------------------
[2025-10-20 12:35:39] === EXTRACTING SQL ===
...
--------------------------------------------------------------------------------
[2025-10-20 12:35:39] === EXECUTING SQL ===
...
--------------------------------------------------------------------------------
[2025-10-20 12:35:39] === REQUEST SUCCESSFUL ===
Execution time: 1250ms
Result count: 1
--------------------------------------------------------------------------------
```

## Debugging Common Issues

### Issue 1: "The AI service is currently unavailable"

**Cause**: Ollama is not running or not accessible

**Check the logs for**:
```
cURL Error: Failed to connect to localhost port 11434: Connection refused
```

**Solution**:
```bash
# Check if Ollama is running
curl http://localhost:11434/api/tags

# Start Ollama if needed
ollama serve

# Check if TinyLlama is installed
ollama list
ollama pull tinyllama
```

### Issue 2: "Invalid response from Ollama API"

**Cause**: Ollama returned malformed JSON

**Check the logs for**:
```
JSON Decode Error: Syntax error
Invalid Response Structure: [logged response]
```

**Solution**:
- Check Ollama version compatibility
- Verify the model is properly loaded
- Restart Ollama service

### Issue 3: "SQL execution error"

**Cause**: Generated SQL has syntax errors or references invalid tables/columns

**Check the logs for**:
```
=== SQL EXECUTION ERROR ===
Error Message: Table 'duns.invalid_table' doesn't exist
```

**Solution**:
- Review the generated SQL in the logs
- Check if database schema matches what AI expects
- Verify database permissions
- Update system prompt with correct schema info

### Issue 4: "Only SELECT queries are allowed"

**Cause**: AI generated a non-SELECT query (INSERT, UPDATE, DELETE, etc.)

**Check the logs for**:
```
ERROR: Not a SELECT query
```

**Solution**:
- This is working as designed (security feature)
- The system prompt should be clearer about SELECT-only
- User needs to rephrase their question

## File Changes

### Modified Files
1. **ai_assistant.php**
   - Added `logDebug()` function
   - Enhanced `queryOllama()` with logging
   - Enhanced `extractAndValidateSQL()` with logging
   - Enhanced `executeSQLQuery()` with logging
   - Improved error messages in main execution flow
   - Added LOG_FILE constant

2. **.gitignore**
   - Added `logs/` directory to exclude log files from git

### New Directories
- `logs/` - Created automatically when first log is written

## Security Considerations

### Log File Permissions
- Log files contain SQL queries and user data
- Ensure `logs/` directory is not web-accessible
- Recommended: Set proper file permissions (0755 for directory, 0644 for files)

### Log Retention
- Logs can grow large over time
- Consider implementing log rotation
- Example logrotate config:
```
/home/runner/work/duns/duns/logs/*.log {
    daily
    rotate 7
    compress
    missingok
    notifempty
}
```

## Testing

### Manual Testing
1. Ensure Ollama is running: `curl http://localhost:11434/api/tags`
2. Log into the application
3. Open the AI chat widget
4. Ask a question: "Show me total revenue"
5. Check `logs/ai_assistant.log` for detailed execution trace

### Test Different Scenarios

#### Success Case
```
Question: "Show unpaid invoices"
Expected: Log shows successful SQL generation and execution
```

#### Ollama Unavailable
```
Stop Ollama: pkill ollama
Question: "Show revenue"
Expected: Log shows cURL connection error
User sees: "The AI service is currently unavailable"
```

#### Invalid SQL
```
Question: "Delete all clients"
Expected: Log shows "ERROR: Not a SELECT query"
User sees: "I can only answer questions that retrieve data"
```

## Performance Impact

### Logging Overhead
- File I/O for each request adds ~5-10ms
- Negligible compared to Ollama API calls (500-2000ms)
- Can be disabled in production by commenting out `logDebug()` calls

### Log File Size
- Typical log entry: 1-2 KB
- 100 requests/day = ~100-200 KB/day
- Weekly rotation recommended

## Future Enhancements

1. **Structured Logging**: Use JSON format for easier parsing
2. **Log Levels**: Add DEBUG, INFO, WARNING, ERROR levels
3. **Log Aggregation**: Send logs to centralized logging system
4. **Metrics**: Track response times, error rates, popular queries
5. **Admin Dashboard**: View logs and metrics in web UI

## Conclusion

The comprehensive logging system provides complete visibility into the AI assistant's operation, making it easy to:
- Diagnose connection issues with Ollama
- Debug SQL generation problems
- Track query execution errors
- Identify patterns in failures
- Provide better support to users

All logging is automatic and requires no code changes to enable/disable for specific requests.
