# AI Assistant Testing Guide

This document provides testing guidelines and examples for the AI-powered financial assistant.

## Pre-Testing Checklist

Before testing, ensure:

- [ ] Ollama is installed and running (`ollama serve`)
- [ ] TinyLlama model is downloaded (`ollama pull tinyllama`)
- [ ] Database migration 004 is applied
- [ ] PHP 8+ is available
- [ ] User is logged in to the system
- [ ] Browser console is open for debugging (F12)

## Manual Testing Steps

### 1. Access the Chat Widget

1. Log in to the financial management system
2. Navigate to the dashboard (`index.php`)
3. Look for the floating chat button in the bottom-right corner
4. Click the button to open the chat panel

**Expected Result:** Chat panel should open with a welcome message and quick question buttons.

### 2. Test Quick Questions

Click each of the pre-defined quick questions:

1. "Show me total revenue for this month"
2. "How many unpaid invoices do we have?"
3. "List top 5 clients by revenue"
4. "What is our total outstanding amount in USD?"

**Expected Result:** 
- Each question should auto-populate the input field
- AI should generate appropriate SQL query
- Results should be displayed in conversational format
- SQL query should be visible below the response

### 3. Test Natural Language Queries

Try these natural language queries:

#### Basic Queries
```
- "Show all clients"
- "List clients in USD"
- "How many clients do we have?"
- "Show clients added today"
```

#### Aggregate Queries
```
- "What's the total revenue?"
- "Calculate average invoice amount"
- "Sum of all unpaid amounts"
- "Count clients by status"
```

#### Filtered Queries
```
- "Show paid invoices in EUR"
- "List partially paid clients"
- "Find clients with amount over 10000"
- "Show clients from this year"
```

#### Complex Queries
```
- "Top 10 clients by revenue"
- "Clients with highest outstanding amounts"
- "Revenue breakdown by currency"
- "Monthly revenue comparison"
```

**Expected Result:**
- AI should interpret the query correctly
- Appropriate SQL should be generated
- Results should be formatted and readable
- No error messages for valid queries

### 4. Test Error Handling

Try these queries to test error handling:

#### Blocked Queries (Should Fail)
```
- "Delete all clients"
- "Update client amounts"
- "Drop the database"
- "Insert a new record"
```

**Expected Result:**
- Request should be blocked
- Error message should explain why
- Status logged as 'blocked' in database

#### Invalid Queries
```
- "asdfghjkl"
- "Show me the moon"
- ""
```

**Expected Result:**
- Graceful error handling
- Helpful error messages
- No system crashes

### 5. Test Session Persistence

1. Send multiple queries in the same session
2. Check chat history is maintained
3. Close and reopen chat panel
4. Verify messages persist

**Expected Result:**
- Messages should remain visible during session
- Scroll should work properly with multiple messages

### 6. Test UI/UX

#### Responsive Design
- Test on desktop (1920x1080)
- Test on tablet (768x1024)
- Test on mobile (375x667)

**Expected Result:**
- Chat widget should adapt to screen size
- Text should be readable on all devices
- Buttons should be easily clickable

#### Accessibility
- Test keyboard navigation (Tab, Enter, Esc)
- Test with screen reader (if available)
- Test color contrast

**Expected Result:**
- All interactive elements accessible via keyboard
- Proper ARIA labels
- Good contrast ratios

### 7. Test Performance

1. Send 10 consecutive queries rapidly
2. Query for large result sets (100+ rows)
3. Test with slow network (throttle in DevTools)

**Expected Result:**
- System should handle concurrent requests
- Large results should be paginated/limited
- Loading indicators should work
- No browser freezing

## Database Verification

### Check Logging

Query the `ai_chat_logs` table to verify interactions are logged:

```sql
-- View recent AI interactions
SELECT 
    id,
    user_id,
    LEFT(user_query, 50) as query,
    status,
    sql_result_count,
    execution_time_ms,
    created_at
FROM ai_chat_logs
ORDER BY created_at DESC
LIMIT 10;

-- Count interactions by status
SELECT status, COUNT(*) as count
FROM ai_chat_logs
GROUP BY status;

-- Average execution time
SELECT AVG(execution_time_ms) as avg_time_ms
FROM ai_chat_logs
WHERE status = 'success';
```

**Expected Result:**
- All queries should be logged
- Timestamps should be accurate
- SQL queries should be captured
- Error messages should be present for failed queries

## API Testing

### Using cURL

Test the backend API directly:

```bash
# Test with valid query
curl -X POST http://localhost/ai_assistant.php \
  -H "Content-Type: application/json" \
  -H "Cookie: PHPSESSID=your_session_id" \
  -d '{"query": "Show me total revenue"}'

# Test with invalid query
curl -X POST http://localhost/ai_assistant.php \
  -H "Content-Type: application/json" \
  -H "Cookie: PHPSESSID=your_session_id" \
  -d '{"query": "DELETE FROM clients"}'

# Test without authentication
curl -X POST http://localhost/ai_assistant.php \
  -H "Content-Type: application/json" \
  -d '{"query": "Show clients"}'
```

**Expected Results:**
- Valid queries should return success=true
- Invalid queries should be blocked
- Unauthenticated requests should return 401

### Using Browser Console

```javascript
// Test AI assistant from browser console
fetch('ai_assistant.php', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json'
    },
    body: JSON.stringify({
        query: 'Show total revenue this month'
    })
})
.then(response => response.json())
.then(data => console.log(data))
.catch(error => console.error('Error:', error));
```

## Security Testing

### SQL Injection Attempts

Try these malicious queries:

```
- "' OR '1'='1"
- "'; DROP TABLE clients; --"
- "SELECT * FROM clients; DELETE FROM clients WHERE 1=1; --"
- "1' UNION SELECT * FROM users --"
```

**Expected Result:**
- All attempts should be blocked
- System should remain secure
- Errors logged appropriately

### Authentication Bypass

1. Open chat in incognito/private window
2. Try to access `ai_assistant.php` without session
3. Attempt to manipulate session cookies

**Expected Result:**
- Unauthenticated access denied
- 401 status code returned
- Redirect to login page

## Performance Benchmarks

### Expected Performance Metrics

| Metric | Target | Acceptable |
|--------|--------|------------|
| Initial load time | < 1s | < 2s |
| Query response time | < 3s | < 5s |
| UI render time | < 100ms | < 200ms |
| Memory usage | < 50MB | < 100MB |
| Database query time | < 100ms | < 500ms |

### Load Testing

If you have Apache Bench (ab) installed:

```bash
# Test 100 requests with 10 concurrent
ab -n 100 -c 10 -p query.json -T "application/json" \
   -C "PHPSESSID=your_session_id" \
   http://localhost/ai_assistant.php
```

**Expected Result:**
- No failed requests
- Consistent response times
- No server errors

## Browser Compatibility

Test on:

- [ ] Chrome/Edge (latest)
- [ ] Firefox (latest)
- [ ] Safari (latest)
- [ ] Mobile Safari (iOS)
- [ ] Chrome Mobile (Android)

**Expected Result:**
- Consistent behavior across browsers
- No JavaScript errors
- Proper CSS rendering

## Known Issues & Limitations

Document any issues found during testing:

1. **Issue**: TinyLlama occasionally generates incorrect SQL for complex queries
   - **Workaround**: Rephrase the query more simply
   - **Severity**: Low

2. **Issue**: Very large result sets may take longer to format
   - **Workaround**: Use more specific queries with filters
   - **Severity**: Low

## Test Report Template

Use this template to document test results:

```
## Test Session: [Date/Time]
Tester: [Name]
Environment: [Production/Staging/Development]

### Summary
- Total tests run: X
- Passed: X
- Failed: X
- Blocked: X

### Test Results

#### Functional Tests
- [ ] Chat widget loads correctly
- [ ] Quick questions work
- [ ] Natural language queries work
- [ ] Error handling works
- [ ] Session persistence works

#### Security Tests
- [ ] SQL injection prevented
- [ ] Authentication enforced
- [ ] Dangerous queries blocked
- [ ] All interactions logged

#### Performance Tests
- [ ] Response time acceptable
- [ ] No memory leaks
- [ ] Handles concurrent requests
- [ ] Database queries optimized

### Issues Found
1. [Description]
   - Severity: [High/Medium/Low]
   - Status: [Open/Fixed/Deferred]

### Recommendations
1. [Recommendation]
```

## Automated Testing (Future)

For automated testing, consider implementing:

1. **PHPUnit tests** for backend logic
2. **Jest/Mocha tests** for JavaScript
3. **Selenium/Playwright** for E2E tests
4. **Load testing** with k6 or JMeter

Example PHPUnit test:

```php
<?php
use PHPUnit\Framework\TestCase;

class AIAssistantTest extends TestCase
{
    public function testSQLValidation()
    {
        // Test that only SELECT queries are allowed
        $this->assertTrue(isSelectQuery("SELECT * FROM clients"));
        $this->assertFalse(isSelectQuery("DELETE FROM clients"));
    }
    
    public function testBlockDangerousKeywords()
    {
        $this->assertFalse(containsDangerousKeyword("DROP TABLE"));
        $this->assertFalse(containsDangerousKeyword("UPDATE clients"));
    }
}
```

## Conclusion

Thorough testing ensures the AI assistant is:
- Functional and user-friendly
- Secure and safe
- Performant and reliable
- Well-documented and maintainable

Report all issues to the development team with detailed reproduction steps.
