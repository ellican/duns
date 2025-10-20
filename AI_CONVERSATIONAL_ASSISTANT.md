# Conversational Financial AI Assistant

## How It Works

This AI assistant behaves like a human financial assistant. It:

1. **Understands natural language** - Ask questions like you would to a real person
2. **Queries the database** - Converts your question to SQL automatically
3. **Responds naturally** - Gives you answers in conversational language

## Example Conversations

**Q:** "Who is the latest person paid?"
**A:** "The latest person who was paid is John Doe."

**Q:** "How much did we spend last month?"
**A:** "We spent a total of 4,200,000 RWF last month."

**Q:** "List top 3 clients"
**A:** "Here are the top 3 clients by payments: John Doe, Mary K., and Felix R."

## Technical Details

- **Model**: qwen2.5:7b-instruct (or llama3.1:8b-instruct)
- **Architecture**: Two-stage AI processing
  - Stage 1: Convert question → SQL
  - Stage 2: Convert SQL results → Natural language
- **Security**: Only SELECT queries allowed, all modifications blocked

## Setup

1. Ensure Ollama is running:
   ```bash
   ollama serve
   ```

2. Pull the model:
   ```bash
   ollama pull qwen2.5:7b-instruct
   # OR
   ollama pull llama3.1:8b-instruct
   ```

3. Access the chat from your dashboard (logged-in users only)

## Model Configuration

Default: `qwen2.5:7b-instruct`
Fallback: `llama3.1:8b-instruct`
Light fallback: `tinyllama`

Change in `ai_assistant.php`:
```php
define('OLLAMA_MODEL', 'qwen2.5:7b-instruct');
```

## Database Schema

The AI assistant has access to:

### clients table
- id, reg_no, client_name, date, Responsible, TIN, service
- amount, currency (USD/EUR/RWF), paid_amount, due_amount
- status (PAID/PARTIALLY PAID/NOT PAID)

### users table
- id, username, first_name, last_name, email

## Features

✅ Responds to every question with natural language
✅ No generic "I can only retrieve data" messages
✅ Actual database data in every response
✅ Sounds like talking to a human
✅ Works with qwen2.5:7b-instruct or llama3.1:8b-instruct
✅ Two-stage processing: SQL generation → Natural response
✅ Safe, read-only database access
✅ All queries logged for audit

## Usage Tips

- Ask questions naturally, as you would to a person
- Be specific about what you want to know
- The assistant understands various phrasings
- All interactions are logged for security and audit purposes

## Example Queries

- "Who is the latest person paid?"
- "How much did we pay last week?"
- "List top 5 clients"
- "Show me unpaid invoices"
- "What's the total revenue this month?"
- "How many clients do we have?"
