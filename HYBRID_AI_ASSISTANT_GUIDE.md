# Hybrid Conversational AI Assistant Guide

## Overview

The AI assistant operates in **two intelligent modes** with fast response times using TinyLlama:

1. **🧠 General Knowledge Mode** - Explains concepts, teaches, and has natural conversations
2. **🧾 Database Mode** - Strictly uses your company database as the only source of truth

## How It Works

### Dual-Mode Detection

The AI automatically detects what type of question you're asking and responds accordingly:

#### General Knowledge Questions
- **What is gross profit?**
- **Can you explain invoicing?**
- **How does depreciation work?**
- **What are the accounting principles?**
- **Hello! / Hi! / How are you?**

For these questions, the AI responds naturally like ChatGPT, drawing on its knowledge to teach and explain concepts conversationally.

#### Database Questions
- **Who is the latest person paid?**
- **How much revenue did we make last week?**
- **Show me top 5 clients**
- **List unpaid invoices**
- **How many clients do we have?**

For these questions, the AI:
1. Converts your question to a SQL query
2. Executes it safely against your database
3. Formats the results into natural language
4. Never makes up or imagines any data

## Key Features

### ✅ Natural Conversations
- Warm, friendly, and conversational tone
- Not robotic or formal
- Like talking to a knowledgeable colleague

### ✅ Strict Database Integrity
- Company data queries **always** use the real database
- No hallucinated or imagined data
- SQL queries are validated and secured

### ✅ Smart Mode Switching
- AI automatically detects which mode to use
- Seamless transitions between modes
- No need to specify which mode you want

### ✅ Enhanced Parameters (Optimized for TinyLlama)
- **General Knowledge**: Moderate temperature (0.5) for balanced, natural responses
- **Database Queries**: Lower temperature (0.5) for precise SQL generation
- Optimized token counts for tinyllama's context window (300-400 tokens)

## Examples

### General Knowledge Mode

**User:** "What is gross profit?"

**AI:** "Gross profit is the amount a business earns after deducting the direct costs of producing goods or services. It shows how efficiently a company produces and sells its products. For example, if you sell a product for $100 and it costs $60 to make, your gross profit is $40."

---

**User:** "Can you explain what invoicing means?"

**AI:** "Of course! Invoicing is the process of sending a bill to a customer for goods or services provided. An invoice includes details like what was sold, the quantity, price, and when payment is due. It's essential for tracking sales and getting paid on time."

---

**User:** "Hello!"

**AI:** "Hi there! I'm your financial assistant. I can help you check your company's financial data or explain any accounting concepts you're curious about. What would you like to know?"

### Database Mode

**User:** "Who is the latest person paid?"

**Backend Process:**
1. AI generates: `SQL: SELECT client_name FROM clients WHERE status = 'PAID' ORDER BY date DESC LIMIT 1`
2. System executes query safely
3. Results: `[{"client_name": "John Doe"}]`

**AI Response:** "The latest person who was paid is John Doe."

---

**User:** "How much revenue did we make last week?"

**Backend Process:**
1. AI generates: `SQL: SELECT SUM(paid_amount) as total FROM clients WHERE date >= DATE_SUB(NOW(), INTERVAL 1 WEEK)`
2. System executes query
3. Results: `[{"total": "3400000"}]`

**AI Response:** "Last week, your company made a total of 3,400,000 RWF in revenue."

---

**User:** "Show me top 5 clients"

**Backend Process:**
1. AI generates: `SQL: SELECT client_name, SUM(paid_amount) as total FROM clients GROUP BY client_name ORDER BY total DESC LIMIT 5`
2. System executes query
3. Results: List of 5 clients with totals

**AI Response:** "Here are your top 5 clients by total payments: John Enterprises ($45,000), Mary & Co ($32,500), Felix Trading ($28,900), Kane Industries ($22,300), and Alice Solutions ($19,800)."

## Technical Implementation

### Response Flow

```
┌──────────────────────────┐
│   User asks question     │
└───────────┬──────────────┘
            │
            ▼
┌──────────────────────────────────────┐
│  AI analyzes question with           │
│  hybrid system prompt                │
└───────────┬──────────────────────────┘
            │
      ┌─────┴─────┐
      │           │
      ▼           ▼
┌──────────┐  ┌──────────────┐
│ General  │  │  Database    │
│ Knowledge│  │  Question    │
└─────┬────┘  └──────┬───────┘
      │              │
      │              ▼
      │         ┌─────────────────┐
      │         │ AI outputs SQL  │
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
      │         │ AI formats results   │
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

All database mode queries are:
- ✅ **Read-only** - Only SELECT queries allowed
- ✅ **Validated** - Dangerous keywords blocked (INSERT, UPDATE, DELETE, DROP, etc.)
- ✅ **Limited** - Automatic LIMIT clauses prevent large queries
- ✅ **Logged** - All interactions saved for audit
- ✅ **Prepared** - SQL injection protection via PDO

### Configuration

Key parameters in `ai_assistant.php` (optimized for TinyLlama):

```php
// Model Configuration
define('OLLAMA_MODEL', 'tinyllama');  // Fast, lightweight model
define('MAX_TOKENS', 400);             // Optimized for tinyllama

// General Knowledge Mode
'temperature' => 0.5,    // Balanced responses
'num_predict' => 300,    // Appropriate length for tinyllama

// Database Mode (SQL Generation)
'temperature' => 0.5,    // Consistent SQL generation
'num_predict' => 150,    // Shorter, focused output
```

## Quick Start Questions

Try these examples to see both modes in action:

### General Knowledge
- "What is the difference between revenue and profit?"
- "Explain cash flow to me"
- "What are assets and liabilities?"
- "How does double-entry bookkeeping work?"

### Database Queries
- "Who are our top clients?"
- "What's our total revenue this month?"
- "Show me unpaid invoices"
- "How many clients do we have?"
- "What's the average invoice amount?"

## Files Modified

### 1. `ai_assistant.php`
- Added `buildHybridSystemPrompt()` function
- Added `containsSQLRequest()` for mode detection
- Added `extractSQL()` for SQL extraction
- Added `validateAndCleanSQL()` for SQL validation
- Added `queryOllama()` with customizable parameters
- Updated main flow to support dual modes
- Enhanced logging with mode tracking

### 2. `assets/js/ai-chat.js`
- Updated welcome message to explain dual capabilities
- Added new quick question buttons for both modes:
  - 💰 Latest payment (database)
  - 🧠 Explain gross profit (general)
  - 👥 Top clients (database)
  - 📚 Teach me invoicing (general)

## Benefits

### For Users
1. **More versatile** - One assistant for learning AND data queries
2. **More natural** - Conversational responses, not robotic
3. **More trustworthy** - Real data, never made up
4. **More helpful** - Can teach concepts you don't understand

### For Developers
1. **Cleaner architecture** - Clear separation of concerns
2. **More maintainable** - Modular functions
3. **Better security** - Enhanced validation
4. **More flexible** - Easy to extend or modify

## Success Criteria

✅ AI can explain general financial concepts naturally  
✅ AI can engage in small talk and greetings  
✅ AI strictly uses database for company-specific data  
✅ No made-up or imagined financial data  
✅ Responses feel natural and conversational  
✅ Proper distinction between general and database modes  
✅ SQL queries only generated when needed  
✅ Natural language responses from database results  
✅ Increased temperature for more human-like responses  
✅ Better welcome message explaining dual capabilities  

## Troubleshooting

### AI always responds in database mode
- Check if your question mentions company-specific terms like "clients", "revenue", "payments"
- Try rephrasing to be more clearly about general concepts

### AI always responds in general mode
- Be more specific about querying your data
- Use phrases like "Show me", "List", "How much", "Who is"
- Mention specific data entities like "clients", "invoices", "payments"

### Responses are not natural enough
- Temperature settings may need adjustment
- Check the system prompt examples
- Ensure Ollama model is properly loaded

## Future Enhancements

Possible improvements:
- Multi-table joins for complex queries
- Export results to CSV/Excel
- Chart generation from data
- Historical trend analysis
- Predictive analytics
- Custom report templates

---

**Version:** 2.0 (Hybrid Mode)  
**Last Updated:** October 2024  
**Status:** Production Ready
