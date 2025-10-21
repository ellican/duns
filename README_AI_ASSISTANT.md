# Hybrid AI Financial Assistant - Complete Guide

## 🎯 What's New: Dual-Mode Intelligence

The AI assistant now operates in **two intelligent modes**:

1. **🧠 General Knowledge Mode** - Explains concepts, teaches, has natural conversations (like ChatGPT)
2. **🧾 Database Mode** - Strictly uses your company database as the only source of truth

The AI **automatically detects** which mode to use based on your question!

## Quick Start

### Requirements
- Ollama running on localhost:11434
- Model: tinyllama (default for fast response times)
- PHP 7.4+
- MySQL/MariaDB database

### Installation

1. **Start Ollama:**
   ```bash
   ollama serve
   ```

2. **Pull the AI model:**
   ```bash
   ollama pull tinyllama
   ```

3. **Access the assistant:**
   - Log into the application
   - Click the AI chat button (bottom right)
   - Start asking questions!

## How to Use

### Example Questions

✅ **General Knowledge Questions:**
- "What is gross profit?"
- "Can you explain invoicing?"
- "How does depreciation work?"
- "Hello! / Hi there!"
- "Explain the difference between revenue and profit"

✅ **Database Questions:**
- "Who is the latest person paid?"
- "How much did we pay this week?"
- "List top 5 clients"
- "Show me unpaid invoices"
- "What's the total revenue this month?"
- "How many clients do we have?"

❌ **Avoid:**
- Requests to modify data (the assistant is read-only)
- Very complex multi-part questions
- Questions about data not in your database

### What You'll Get

The assistant responds in **natural, conversational language**, like talking to a real person:

#### General Knowledge Example:
**You:** "What is gross profit?"
**AI:** "Gross profit is the amount a business earns after deducting the direct costs of producing goods or services. It shows how efficiently a company produces and sells its products. For example, if you sell a product for $100 and it costs $60 to make, your gross profit is $40."

#### Database Query Example:
**You:** "Who is the latest person paid?"
**AI:** "The latest person who was paid is John Doe."

**You:** "How much did we pay last week?"
**AI:** "Last week, we paid a total of 3,400,000 RWF."

## Technical Architecture

### Hybrid Dual-Mode Processing

```
┌──────────────────────────┐
│   User asks question     │
└───────────┬──────────────┘
            │
            ▼
┌──────────────────────────────────────┐
│  AI analyzes with hybrid prompt      │
└───────────┬──────────────────────────┘
            │
      ┌─────┴─────┐
      │           │
      ▼           ▼
┌──────────┐  ┌──────────────┐
│ General  │  │  Database    │
│ Answer   │  │  Query       │
│ Directly │  │              │
└─────┬────┘  └──────┬───────┘
      │              │
      │              ▼
      │         ┌─────────────────┐
      │         │ Generate SQL    │
      │         └────────┬────────┘
      │                  │
      │                  ▼
      │         ┌──────────────────┐
      │         │ Execute SQL      │
      │         │ (validated)      │
      │         └────────┬─────────┘
      │                  │
      │                  ▼
      │         ┌──────────────────────┐
      │         │ Format results       │
      │         │ naturally            │
      │         └────────┬─────────────┘
      │                  │
      └────────┬─────────┘
               │
               ▼
      ┌─────────────────┐
      │ Return to user  │
      └─────────────────┘
```

### Security Features

✅ **Read-Only:** Only SELECT queries allowed
✅ **Validated:** All SQL is checked for dangerous keywords
✅ **Limited:** Automatic LIMIT clauses prevent large queries
✅ **Logged:** All interactions saved for audit
✅ **Prepared Statements:** SQL injection protection

## Files

### Core Implementation
- **`ai_assistant.php`** - Main backend with hybrid dual-mode system
- **`assets/js/ai-chat.js`** - Frontend chat widget with updated welcome
- **`assets/css/ai-chat.css`** - Chat styling

### Documentation
- **`HYBRID_AI_ASSISTANT_GUIDE.md`** - Comprehensive hybrid mode guide (NEW!)
- **`AI_CONVERSATIONAL_ASSISTANT.md`** - User guide
- **`AI_OVERHAUL_SUMMARY.md`** - Implementation details
- **`AI_BEFORE_AFTER_COMPARISON.md`** - What changed
- **`README_AI_ASSISTANT.md`** - This file

### Database
- **`migrations/004_create_ai_chat_logs_table.sql`** - Audit logging table

## Configuration

Edit `ai_assistant.php` to customize:

```php
// Change AI model (default is tinyllama for speed)
define('OLLAMA_MODEL', 'tinyllama'); // Alternative: qwen2.5:7b-instruct

// Adjust response length
define('MAX_TOKENS', 400);

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
ollama pull tinyllama
```

### Slow responses
**Problem:** Model may be too large for your system
**Solution:** Tinyllama is already the fastest option (637MB). For even better performance, ensure:
- Ollama has sufficient RAM allocated
- No other resource-intensive processes are running
- Consider upgrading hardware if issues persist

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
| tinyllama | 637MB | Fast | Good | Production (default) - Best speed |
| qwen2.5:7b-instruct | 4.7GB | Medium | Excellent | High-end systems |
| llama3.1:8b-instruct | 4.7GB | Medium | Excellent | High-end systems |

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
- **TinyLlama** - Lightweight, fast language model
- **PHP** - Backend processing
- **JavaScript** - Frontend chat interface

---

**Version:** 2.0 (Complete Overhaul)
**Last Updated:** October 2024
**License:** As per project license
