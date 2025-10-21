# Feza Logistics - Financial Management System

A comprehensive financial management system for Feza Logistics with AI-powered assistance.

## Features

### Core Functionality
- 📊 **Financial Dashboard**: Real-time overview of revenue, outstanding amounts, and client data
- 💼 **Client Management**: Add, edit, and track client information
- 📄 **Document Generation**: Create professional invoices, receipts, and quotations
- 💱 **Multi-Currency Support**: Handle transactions in USD, EUR, and RWF
- 📈 **Financial Reports**: Generate and export financial summaries
- 🔍 **Advanced Search**: Filter and search through financial records
- 📱 **Responsive Design**: Works seamlessly on desktop, tablet, and mobile devices

### AI-Powered Financial Assistant
- 🤖 **Natural Language Queries**: Ask questions in plain English
- 💬 **Interactive Chat Interface**: Conversational AI assistant
- 🔒 **Secure & Audited**: All interactions logged for compliance
- 📊 **Real-time Data**: Query your live database instantly
- 🎯 **Smart SQL Generation**: Converts questions to safe database queries
- ⚡ **Fast Responses**: Optimized with TinyLlama for quick performance

## Quick Start

### Prerequisites
- PHP 8.0 or higher
- MySQL/MariaDB
- Web server (Apache/Nginx)
- Ollama (for AI assistant)

### Installation

1. **Clone the repository**
   ```bash
   git clone https://github.com/ellican/duns.git
   cd duns
   ```

2. **Set up the database**
   ```bash
   mysql -u your_username -p your_database < database.sql
   ```

3. **Apply migrations**
   ```bash
   cd migrations
   mysql -u your_username -p your_database < 001_rename_phone_to_responsible.sql
   mysql -u your_username -p your_database < 002_add_tin_column.sql
   mysql -u your_username -p your_database < 003_create_login_attempts_table.sql
   mysql -u your_username -p your_database < 004_create_ai_chat_logs_table.sql
   ```

4. **Configure database connection**
   Edit `db.php` with your database credentials:
   ```php
   $host = 'localhost';
   $dbname = 'your_database_name';
   $username = 'your_username';
   $password = 'your_password';
   ```

5. **Set up AI Assistant (Optional)**
   ```bash
   chmod +x setup_ai_assistant.sh
   ./setup_ai_assistant.sh
   ```

6. **Access the application**
   Navigate to your web server URL (e.g., `http://localhost/duns`)

## AI Assistant Setup

For detailed AI assistant setup instructions, see [AI_ASSISTANT_README.md](AI_ASSISTANT_README.md)

### Quick Setup
```bash
# Install Ollama
curl -fsSL https://ollama.ai/install.sh | sh

# Start Ollama service
ollama serve

# Pull TinyLlama model
ollama pull tinyllama

# Apply AI chat logs migration
mysql -u your_username -p your_database < migrations/004_create_ai_chat_logs_table.sql
```

## Usage

### Logging In
1. Navigate to the login page
2. Enter your credentials
3. Complete 2FA verification via email

### Using the Dashboard
- View financial summaries and metrics
- Add new clients and transactions
- Generate invoices and receipts
- Filter and search records
- Export data to Excel

### Using the AI Assistant
1. Click the floating chat button (bottom-right corner)
2. Ask questions in natural language:
   - "Show me total revenue for this month"
   - "How many unpaid invoices do we have?"
   - "List top 5 clients by revenue"
   - "What is our outstanding amount in USD?"
3. View results and generated SQL queries
4. Click quick question buttons for common queries

## Documentation

- [AI Assistant README](AI_ASSISTANT_README.md) - Detailed AI assistant documentation
- [AI Assistant Testing Guide](AI_ASSISTANT_TESTING.md) - Testing procedures and examples
- [Migrations README](migrations/README.md) - Database migration guide
- [Implementation Guide](IMPLEMENTATION_GUIDE.md) - Implementation details
- [Database Fix Documentation](DATABASE_FIX_DOCUMENTATION.md) - Database fixes

## Security

### Authentication
- Email verification required for new accounts
- Two-factor authentication (2FA) via email
- Session management with automatic timeout
- Login attempt tracking and monitoring

### AI Assistant Security
- Only SELECT queries allowed (read-only)
- SQL injection prevention
- Authentication required for all requests
- All interactions logged and audited
- Dangerous keywords blocked

### Data Protection
- PDO prepared statements for all queries
- Password hashing with bcrypt
- Session hijacking prevention
- HTTPS recommended for production

## File Structure

```
duns/
├── ai_assistant.php              # AI assistant backend API
├── index.php                     # Main dashboard
├── login.php                     # Login page
├── db.php                        # Database connection
├── assets/
│   ├── css/
│   │   ├── design-system.css    # Design system styles
│   │   ├── application.css      # Application styles
│   │   └── ai-chat.css          # AI chat widget styles
│   └── js/
│       └── ai-chat.js           # AI chat widget JavaScript
├── fpdf/                         # PDF generation library
├── migrations/                   # Database migrations
│   ├── 001_rename_phone_to_responsible.sql
│   ├── 002_add_tin_column.sql
│   ├── 003_create_login_attempts_table.sql
│   └── 004_create_ai_chat_logs_table.sql
├── setup_ai_assistant.sh        # AI assistant setup script
└── [other PHP files...]         # Various application files
```

## Technologies Used

- **Backend**: PHP 8.3
- **Database**: MySQL/MariaDB
- **Frontend**: HTML5, CSS3, JavaScript (Vanilla)
- **PDF Generation**: FPDF
- **AI**: Ollama with TinyLlama
- **Authentication**: Session-based with 2FA
- **Design**: Custom design system

## Browser Support

- Chrome/Edge (latest)
- Firefox (latest)
- Safari (latest)
- Mobile browsers (iOS Safari, Chrome Mobile)

## Performance

- Optimized database queries with indexes
- Lazy loading for large datasets
- AJAX-based updates (no full page reloads)
- Efficient CSS and JavaScript
- Response caching where appropriate

## Contributing

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

## Known Limitations

- AI assistant requires Ollama to be running
- TinyLlama may generate incorrect SQL for very complex queries
- Multi-language support is limited
- PDF generation requires FPDF library

## Troubleshooting

### AI Assistant Not Working
1. Check if Ollama is running: `curl http://localhost:11434/api/tags`
2. Verify TinyLlama is installed: `ollama list`
3. Check browser console for errors (F12)
4. Review `ai_chat_logs` table for error details

### Database Connection Issues
1. Verify credentials in `db.php`
2. Check MySQL service is running
3. Ensure user has proper permissions
4. Test connection: `mysql -u username -p database_name`

### PDF Generation Issues
1. Check FPDF library is present in `/fpdf` folder
2. Verify write permissions on server
3. Ensure client has TIN number (required for some documents)

## Support

For issues, questions, or feature requests:
- Check the documentation files
- Review the testing guide
- Contact the development team

## License

This project is proprietary software for Feza Logistics.

## Changelog

### Version 2.0 (2025-10-20)
- ✨ Added AI-powered financial assistant
- 🔒 Enhanced security with SQL validation
- 📊 Improved dashboard with real-time updates
- 🎨 Refreshed UI with modern design system
- 📱 Better mobile responsiveness

### Version 1.x
- Initial release with core financial management features
- Client and transaction management
- PDF generation for documents
- Multi-currency support
