#!/bin/bash

# AI Assistant Setup Script for Feza Logistics
# This script helps set up Ollama and the AI assistant feature

set -e

echo "==================================================="
echo "AI Assistant Setup for Feza Logistics"
echo "==================================================="
echo ""

# Color codes for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Function to print colored messages
print_success() {
    echo -e "${GREEN}✓ $1${NC}"
}

print_error() {
    echo -e "${RED}✗ $1${NC}"
}

print_info() {
    echo -e "${YELLOW}ℹ $1${NC}"
}

# Check if running as root (not recommended for Ollama)
if [ "$EUID" -eq 0 ]; then 
    print_error "Please do not run this script as root"
    exit 1
fi

# Step 1: Check if Ollama is installed
echo "Step 1: Checking Ollama installation..."
if command -v ollama &> /dev/null; then
    print_success "Ollama is already installed"
    ollama --version
else
    print_info "Ollama is not installed. Installing now..."
    
    # Install Ollama
    curl -fsSL https://ollama.ai/install.sh | sh
    
    if [ $? -eq 0 ]; then
        print_success "Ollama installed successfully"
    else
        print_error "Failed to install Ollama"
        exit 1
    fi
fi

echo ""

# Step 2: Check if Ollama service is running
echo "Step 2: Checking Ollama service..."
if curl -s http://localhost:11434/api/tags > /dev/null 2>&1; then
    print_success "Ollama service is running"
else
    print_info "Ollama service is not running. Starting it..."
    
    # Try to start Ollama in the background
    nohup ollama serve > /tmp/ollama.log 2>&1 &
    
    # Wait a few seconds for the service to start
    sleep 3
    
    if curl -s http://localhost:11434/api/tags > /dev/null 2>&1; then
        print_success "Ollama service started successfully"
    else
        print_error "Failed to start Ollama service"
        print_info "Please start Ollama manually: ollama serve"
        exit 1
    fi
fi

echo ""

# Step 3: Check if TinyLlama model is installed
echo "Step 3: Checking TinyLlama model..."
if ollama list | grep -q "tinyllama"; then
    print_success "TinyLlama model is already installed"
else
    print_info "TinyLlama model is not installed. Downloading now (this may take a few minutes)..."
    
    ollama pull tinyllama
    
    if [ $? -eq 0 ]; then
        print_success "TinyLlama model downloaded successfully"
    else
        print_error "Failed to download TinyLlama model"
        exit 1
    fi
fi

echo ""

# Step 4: Check database connection
echo "Step 4: Checking database setup..."
DB_USER="duns"
DB_NAME="duns"
DB_PASS="QRJ5M0VuI1nkMQW"

if mysql -u "$DB_USER" -p"$DB_PASS" -e "USE $DB_NAME" 2>/dev/null; then
    print_success "Database connection successful"
    
    # Check if migration is needed
    if mysql -u "$DB_USER" -p"$DB_PASS" -D "$DB_NAME" -e "SHOW TABLES LIKE 'ai_chat_logs'" 2>/dev/null | grep -q "ai_chat_logs"; then
        print_success "AI chat logs table already exists"
    else
        print_info "AI chat logs table not found. Would you like to run the migration? (y/n)"
        read -r response
        if [[ "$response" =~ ^[Yy]$ ]]; then
            mysql -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" < migrations/004_create_ai_chat_logs_table.sql
            print_success "Migration completed successfully"
        else
            print_info "Skipping migration. Remember to run it manually later."
        fi
    fi
else
    print_error "Database connection failed"
    print_info "Please check your database credentials in db.php"
fi

echo ""

# Step 5: Test the AI assistant
echo "Step 5: Testing AI assistant..."
print_info "Sending a test query to the AI..."

TEST_RESPONSE=$(curl -s -X POST http://localhost:11434/api/generate \
    -H "Content-Type: application/json" \
    -d '{
        "model": "tinyllama",
        "prompt": "Say hello",
        "stream": false
    }')

if echo "$TEST_RESPONSE" | grep -q "response"; then
    print_success "AI assistant is responding correctly"
else
    print_error "AI assistant test failed"
    print_info "Response: $TEST_RESPONSE"
fi

echo ""
echo "==================================================="
echo "Setup Complete!"
echo "==================================================="
echo ""
print_success "AI Assistant is ready to use!"
echo ""
echo "Next steps:"
echo "1. Make sure Ollama service stays running (use systemd or supervisor)"
echo "2. Access the dashboard at your web server URL"
echo "3. Click the floating chat button to start using the AI assistant"
echo ""
echo "For systemd setup (optional):"
echo "  sudo systemctl enable ollama"
echo "  sudo systemctl start ollama"
echo ""
print_info "Check AI_ASSISTANT_README.md for detailed documentation"
echo ""
