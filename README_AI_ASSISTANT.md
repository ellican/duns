# AI Financial Assistant - Complete Guide

## Quick Start

### Requirements
- Ollama running on localhost:11434
- Model: qwen2.5:7b-instruct (or llama3.1:8b-instruct)
- PHP 7.4+
- MySQL/MariaDB database

### Installation

1. **Start Ollama:**
   ```bash
   ollama serve
   ```

2. **Pull the AI model:**
   ```bash
   ollama pull qwen2.5:7b-instruct
   ```

3. **Access the assistant:**
   - Log into the application
   - Click the AI chat button (bottom right)
   - Start asking questions!

## How to Use

### Example Questions

✅ **Good Questions:**
- "Who is the latest person paid?"
- "How much did we pay this week?"
- "List top 5 clients"
- "Show me unpaid invoices"
- "What's the total revenue this month?"
- "How many clients do we have?"

❌ **Avoid:**
- Requests to modify data (the assistant is read-only)
- Very complex multi-part questions
- Questions about data not in the database

### What You'll Get

The assistant responds in **natural, conversational language**, like talking to a real person:

**You:** "Who is the latest person paid?"
**AI:** "The latest person who was paid is John Doe."

**You:** "How much did we pay last week?"
**AI:** "Last week, we paid a total of 3,400,000 RWF."

## Technical Architecture

### Two-Stage Processing

```
┌─────────────────┐
│  User Question  │
└────────┬────────┘
         │
         ▼
┌─────────────────────────────┐
│ STAGE 1: SQL Generation     │
│ AI converts question to SQL │
└────────┬────────────────────┘
         │
         ▼
┌─────────────────────────┐
│ Execute SQL (validated) │
└────────┬────────────────┘
         │
         ▼
┌──────────────────────────────────┐
│ STAGE 2: Natural Response        │
│ AI converts results to natural   │
│ conversational language          │
└────────┬─────────────────────────┘
         │
         ▼
┌─────────────────────────┐
│ Return to user + log    │
└─────────────────────────┘
```

### Security Features

✅ **Read-Only:** Only SELECT queries allowed
✅ **Validated:** All SQL is checked for dangerous keywords
✅ **Limited:** Automatic LIMIT clauses prevent large queries
✅ **Logged:** All interactions saved for audit
✅ **Prepared Statements:** SQL injection protection

## Files

### Core Implementation
- **`ai_assistant.php`** - Main backend (259 lines)
- **`assets/js/ai-chat.js`** - Frontend chat widget
- **`assets/css/ai-chat.css`** - Chat styling

### Documentation
- **`AI_CONVERSATIONAL_ASSISTANT.md`** - User guide
- **`AI_OVERHAUL_SUMMARY.md`** - Implementation details
- **`AI_BEFORE_AFTER_COMPARISON.md`** - What changed
- **`README_AI_ASSISTANT.md`** - This file

### Database
- **`migrations/004_create_ai_chat_logs_table.sql`** - Audit logging table

## Configuration

Edit `ai_assistant.php` to customize:

```php
// Change AI model
define('OLLAMA_MODEL', 'qwen2.5:7b-instruct'); // or llama3.1:8b-instruct

// Adjust response length
define('MAX_TOKENS', 800);

// Change Ollama URL if needed
define('OLLAMA_API_URL', 'http://localhost:11434/api/generate');
```

## Troubleshooting

### "AI service unavailable"
**Problem:** Ollama is not running
**Solution:**
```bash
ollama serve
```

### "Invalid AI response"
**Problem:** Model not downloaded
**Solution:**
```bash
ollama pull qwen2.5:7b-instruct
```

### Slow responses
**Problem:** Model is too large for your system
**Solution:** Use a smaller model:
```php
define('OLLAMA_MODEL', 'tinyllama'); // Lighter weight
```

### "Database query failed"
**Problem:** SQL syntax error or invalid table/column reference
**Solution:** The AI is still learning. Try rephrasing your question more clearly.

## Advanced Usage

### Viewing SQL Queries

SQL queries are logged to your browser console for debugging:

1. Open DevTools (F12)
2. Go to Console tab
3. Ask a question
4. See the generated SQL in the console

### Custom Quick Questions

Edit `assets/js/ai-chat.js` in the `loadWelcomeMessage()` function:

```javascript
<button class="ai-quick-question" data-question="Your question here">
    🎯 Button Label
</button>
```

### Database Schema Reference

The AI has access to:

**clients table:**
- id, reg_no, client_name, date
- Responsible, TIN, service
- amount, currency, paid_amount, due_amount
- status (PAID/PARTIALLY PAID/NOT PAID)

**users table:**
- id, username, first_name, last_name, email

## Model Comparison

| Model | Size | Speed | Quality | Recommended For |
|-------|------|-------|---------|-----------------|
| qwen2.5:7b-instruct | 4.7GB | Medium | Excellent | Production (default) |
| llama3.1:8b-instruct | 4.7GB | Medium | Excellent | Alternative |
| tinyllama | 637MB | Fast | Good | Limited resources |

## Performance Tips

1. **Be specific:** "Show me this month's revenue" is better than "Show me revenue"
2. **Use examples:** The AI learns from the patterns in the prompts
3. **One question at a time:** Don't combine multiple questions
4. **Natural language:** Ask like you're talking to a person

## Privacy & Security

- ✅ All queries logged with timestamps
- ✅ User authentication required
- ✅ Read-only database access
- ✅ No data sent outside your server
- ✅ Ollama runs locally (no cloud API)

## Support

For issues or questions:
1. Check the troubleshooting section
2. Review the documentation files
3. Check browser console for errors
4. Verify Ollama is running and model is downloaded

## Development

### Making Changes

1. **Modify SQL generation:** Edit `buildSQLGenerationPrompt()` function
2. **Modify responses:** Edit `generateNaturalResponse()` function
3. **Add security rules:** Update `generateSQLFromQuestion()` validation
4. **Change UI:** Edit `assets/js/ai-chat.js` and `assets/css/ai-chat.css`

### Testing Changes

After modifying the code:
1. Restart your web server (if needed)
2. Clear browser cache
3. Test with various questions
4. Check browser console for errors
5. Review `ai_chat_logs` table for query results

## Credits

Built with:
- **Ollama** - Local AI inference
- **Qwen 2.5** - Language model by Alibaba Cloud
- **PHP** - Backend processing
- **JavaScript** - Frontend chat interface

---

**Version:** 2.0 (Complete Overhaul)
**Last Updated:** October 2024
**License:** As per project license
