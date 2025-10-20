# AI Assistant Quick Reference Guide

This guide provides quick commands and references for developers working with the AI assistant feature.

## Quick Start Commands

```bash
# Install Ollama
curl -fsSL https://ollama.ai/install.sh | sh

# Start Ollama service
ollama serve &

# Pull TinyLlama model
ollama pull tinyllama

# Test Ollama is working
curl http://localhost:11434/api/tags

# Run setup script
./setup_ai_assistant.sh

# Apply database migration
mysql -u duns -p duns < migrations/004_create_ai_chat_logs_table.sql
```

## File Locations

| File | Purpose | Size |
|------|---------|------|
| `ai_assistant.php` | Backend API | ~12KB |
| `assets/css/ai-chat.css` | Chat widget styles | ~8KB |
| `assets/js/ai-chat.js` | Chat widget JavaScript | ~13KB |
| `migrations/004_create_ai_chat_logs_table.sql` | Database schema | ~1KB |
| `setup_ai_assistant.sh` | Setup script | ~5KB |
| `AI_ASSISTANT_README.md` | Full documentation | ~7KB |
| `AI_ASSISTANT_TESTING.md` | Testing guide | ~10KB |

## API Endpoints

### POST /ai_assistant.php

**Request:**
```json
{
    "query": "Show me total revenue for this month",
    "session_id": "optional_session_id"
}
```

**Success Response:**
```json
{
    "success": true,
    "response": "Here's what I found:\n\n**Total:** $125,450.75",
    "sql": "SELECT SUM(paid_amount) as total FROM clients WHERE MONTH(date) = MONTH(CURRENT_DATE())",
    "result_count": 1,
    "execution_time_ms": 234
}
```

**Error Response:**
```json
{
    "success": false,
    "error": "Blocked dangerous keyword: DELETE",
    "user_message": "Only SELECT queries are allowed"
}
```

## Database Schema

### ai_chat_logs Table

```sql
CREATE TABLE `ai_chat_logs` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) NOT NULL,
  `session_id` VARCHAR(255) NOT NULL,
  `user_query` TEXT NOT NULL,
  `ai_response` TEXT,
  `sql_executed` TEXT,
  `sql_result_count` INT DEFAULT 0,
  `execution_time_ms` INT DEFAULT 0,
  `status` ENUM('success', 'error', 'blocked') DEFAULT 'success',
  `error_message` TEXT,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_session_id` (`session_id`),
  KEY `idx_created_at` (`created_at`),
  CONSTRAINT `fk_ai_chat_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
);
```

## Common Queries

### View Recent Interactions
```sql
SELECT 
    user_id,
    LEFT(user_query, 50) as query,
    status,
    execution_time_ms,
    created_at
FROM ai_chat_logs
ORDER BY created_at DESC
LIMIT 20;
```

### Count by Status
```sql
SELECT 
    status,
    COUNT(*) as count,
    AVG(execution_time_ms) as avg_time
FROM ai_chat_logs
GROUP BY status;
```

### Find Slow Queries
```sql
SELECT 
    user_query,
    execution_time_ms,
    created_at
FROM ai_chat_logs
WHERE execution_time_ms > 1000
ORDER BY execution_time_ms DESC;
```

### Find Blocked Queries
```sql
SELECT 
    user_query,
    error_message,
    created_at
FROM ai_chat_logs
WHERE status = 'blocked'
ORDER BY created_at DESC;
```

## Configuration Variables

In `ai_assistant.php`:

```php
define('OLLAMA_API_URL', 'http://localhost:11434/api/generate');
define('OLLAMA_MODEL', 'tinyllama');
define('MAX_RESPONSE_TOKENS', 500);
define('SQL_TIMEOUT', 5); // seconds
```

## JavaScript API

### Initialize Chat Widget
```javascript
const chatWidget = new AIChatWidget();
```

### Send Message Programmatically
```javascript
// Assuming widget is initialized
document.getElementById('aiChatInput').value = 'Show total revenue';
document.getElementById('aiChatSend').click();
```

### Custom Event Listeners
```javascript
// Listen for chat open
document.getElementById('aiChatToggle').addEventListener('click', () => {
    console.log('Chat toggled');
});
```

## Security Checklist

- [ ] Only SELECT queries allowed
- [ ] All dangerous keywords blocked
- [ ] Authentication required
- [ ] All queries logged
- [ ] PDO prepared statements used
- [ ] Error messages sanitized
- [ ] Session validation active
- [ ] CSRF protection (if applicable)

## Troubleshooting Commands

```bash
# Check if Ollama is running
curl http://localhost:11434/api/tags

# Check Ollama logs
journalctl -u ollama -f

# Test TinyLlama
ollama run tinyllama "SELECT * FROM clients LIMIT 5"

# Check PHP errors
tail -f /var/log/apache2/error.log
# or
tail -f /var/log/nginx/error.log

# Check database connection
mysql -u duns -p duns -e "SHOW TABLES LIKE 'ai_chat_logs'"

# Verify migration
mysql -u duns -p duns -e "DESCRIBE ai_chat_logs"
```

## Performance Tuning

### Ollama Optimization
```bash
# Use GPU if available
OLLAMA_GPU=1 ollama serve

# Adjust concurrent requests
OLLAMA_MAX_LOADED_MODELS=2 ollama serve

# Adjust memory
OLLAMA_MAX_VRAM=4GB ollama serve
```

### Database Optimization
```sql
-- Add composite index for common queries
CREATE INDEX idx_user_session_time 
ON ai_chat_logs (user_id, session_id, created_at);

-- Analyze table
ANALYZE TABLE ai_chat_logs;

-- Optimize table
OPTIMIZE TABLE ai_chat_logs;
```

### PHP Optimization
```php
// Enable OPcache in php.ini
opcache.enable=1
opcache.memory_consumption=128
opcache.max_accelerated_files=10000
opcache.revalidate_freq=60
```

## Common Issues & Solutions

### Issue: "Ollama API returned status code: 404"
**Solution:**
```bash
# Restart Ollama
pkill ollama
ollama serve &
```

### Issue: "Only SELECT queries are allowed"
**Solution:** This is intentional security. Modify your query to use SELECT only.

### Issue: Chat widget not appearing
**Solution:**
```bash
# Check if files are loaded
curl http://localhost/assets/css/ai-chat.css
curl http://localhost/assets/js/ai-chat.js

# Check browser console (F12) for errors
```

### Issue: Slow responses
**Solution:**
- Reduce `MAX_RESPONSE_TOKENS`
- Keep Ollama running to avoid cold starts
- Add database indexes
- Use more specific queries

## Development Workflow

1. **Make changes** to AI assistant files
2. **Test syntax**: `php -l ai_assistant.php`
3. **Test in browser**: Open chat and try queries
4. **Check logs**: Review `ai_chat_logs` table
5. **Monitor performance**: Check execution times
6. **Commit changes**: Git commit with clear message

## Testing Checklist

- [ ] UI loads correctly
- [ ] Quick questions work
- [ ] Custom queries work
- [ ] Error handling works
- [ ] SQL validation works
- [ ] Authentication works
- [ ] Logging works
- [ ] Performance acceptable
- [ ] Mobile responsive
- [ ] Browser compatible

## Useful Links

- [Ollama Documentation](https://github.com/ollama/ollama)
- [TinyLlama Model](https://huggingface.co/TinyLlama)
- [PHP PDO Manual](https://www.php.net/manual/en/book.pdo.php)
- [MDN Web Docs](https://developer.mozilla.org/)

## Support

For questions or issues:
1. Check this quick reference
2. Review full documentation in `AI_ASSISTANT_README.md`
3. Check testing guide in `AI_ASSISTANT_TESTING.md`
4. Contact development team

---

**Last Updated:** 2025-10-20  
**Version:** 1.0
