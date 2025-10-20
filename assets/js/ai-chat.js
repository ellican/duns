/**
 * AI Chat Assistant Widget
 * Handles the interactive chat interface for the AI financial assistant
 */

class AIChatWidget {
    constructor() {
        this.isOpen = false;
        this.isTyping = false;
        this.messages = [];
        this.sessionId = this.generateSessionId();
        
        this.init();
    }
    
    /**
     * Initialize the chat widget
     */
    init() {
        this.createWidget();
        this.attachEventListeners();
        this.loadWelcomeMessage();
    }
    
    /**
     * Generate a unique session ID for chat history
     */
    generateSessionId() {
        return 'chat_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
    }
    
    /**
     * Create the chat widget HTML
     */
    createWidget() {
        const widgetHTML = `
            <div class="ai-chat-widget" id="aiChatWidget">
                <!-- Floating toggle button -->
                <button class="ai-chat-toggle" id="aiChatToggle" aria-label="Toggle AI Assistant">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                        <path d="M12 2C6.48 2 2 6.48 2 12c0 1.54.36 3 .97 4.29L2 22l5.71-.97C9 21.64 10.46 22 12 22c5.52 0 10-4.48 10-10S17.52 2 12 2zm0 18c-1.38 0-2.68-.31-3.85-.86l-.27-.14-2.85.48.48-2.85-.14-.27A7.934 7.934 0 014 12c0-4.41 3.59-8 8-8s8 3.59 8 8-3.59 8-8 8zm4-10h-2V8c0-.55-.45-1-1-1s-1 .45-1 1v2H8c-.55 0-1 .45-1 1s.45 1 1 1h4v2c0 .55.45 1 1 1s1-.45 1-1v-2h2c.55 0 1-.45 1-1s-.45-1-1-1z"/>
                    </svg>
                    <span class="ai-chat-badge" id="aiChatBadge">1</span>
                </button>
                
                <!-- Chat panel -->
                <div class="ai-chat-panel" id="aiChatPanel">
                    <!-- Header -->
                    <div class="ai-chat-header">
                        <div class="ai-chat-header-info">
                            <div class="ai-chat-avatar">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                                    <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 3c1.66 0 3 1.34 3 3s-1.34 3-3 3-3-1.34-3-3 1.34-3 3-3zm0 14.2c-2.5 0-4.71-1.28-6-3.22.03-1.99 4-3.08 6-3.08 1.99 0 5.97 1.09 6 3.08-1.29 1.94-3.5 3.22-6 3.22z"/>
                                </svg>
                            </div>
                            <div class="ai-chat-title">
                                <h3>Financial Assistant</h3>
                                <span class="status">Online • Ready to help</span>
                            </div>
                        </div>
                        <button class="ai-chat-close" id="aiChatClose" aria-label="Close chat">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                    
                    <!-- Messages area -->
                    <div class="ai-chat-messages" id="aiChatMessages">
                        <!-- Messages will be inserted here -->
                    </div>
                    
                    <!-- Input area -->
                    <div class="ai-chat-input-container">
                        <textarea 
                            class="ai-chat-input" 
                            id="aiChatInput" 
                            placeholder="Ask me anything about your finances..."
                            rows="1"
                        ></textarea>
                        <button class="ai-chat-send-btn" id="aiChatSend" aria-label="Send message">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                                <path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/>
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
        `;
        
        document.body.insertAdjacentHTML('beforeend', widgetHTML);
    }
    
    /**
     * Attach event listeners
     */
    attachEventListeners() {
        // Toggle chat
        document.getElementById('aiChatToggle').addEventListener('click', () => {
            this.toggleChat();
        });
        
        // Close chat
        document.getElementById('aiChatClose').addEventListener('click', () => {
            this.closeChat();
        });
        
        // Send message
        document.getElementById('aiChatSend').addEventListener('click', () => {
            this.sendMessage();
        });
        
        // Input handling
        const input = document.getElementById('aiChatInput');
        
        // Auto-resize textarea
        input.addEventListener('input', (e) => {
            e.target.style.height = 'auto';
            e.target.style.height = Math.min(e.target.scrollHeight, 120) + 'px';
        });
        
        // Send on Enter (Shift+Enter for new line)
        input.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                this.sendMessage();
            }
        });
    }
    
    /**
     * Toggle chat open/close
     */
    toggleChat() {
        if (this.isOpen) {
            this.closeChat();
        } else {
            this.openChat();
        }
    }
    
    /**
     * Open chat panel
     */
    openChat() {
        this.isOpen = true;
        document.getElementById('aiChatPanel').classList.add('show');
        document.getElementById('aiChatInput').focus();
        document.getElementById('aiChatBadge').classList.remove('show');
    }
    
    /**
     * Close chat panel
     */
    closeChat() {
        this.isOpen = false;
        document.getElementById('aiChatPanel').classList.remove('show');
    }
    
    /**
     * Load welcome message
     */
    loadWelcomeMessage() {
        const welcomeHTML = `
            <div class="ai-message assistant">
                <div class="ai-message-avatar">AI</div>
                <div class="ai-message-content">
                    <div class="ai-welcome-message">
                        <h4>👋 Hi! I'm your smart financial assistant</h4>
                        <p>I can help you in two ways:</p>
                        <ul style="text-align: left; font-size: 0.9em; margin: 10px 0;">
                            <li>📊 Answer questions about <strong>your company data</strong> (payments, clients, revenue)</li>
                            <li>🧠 Explain <strong>financial concepts</strong> and teach you about accounting</li>
                        </ul>
                        <p>Try asking me anything!</p>
                        <div class="ai-quick-questions">
                            <button class="ai-quick-question" data-question="Who is the latest person paid?">💰 Latest payment</button>
                            <button class="ai-quick-question" data-question="What is gross profit?">🧠 Explain gross profit</button>
                            <button class="ai-quick-question" data-question="Show top 5 clients">👥 Top clients</button>
                            <button class="ai-quick-question" data-question="How does invoicing work?">📚 Teach me invoicing</button>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        const messagesContainer = document.getElementById('aiChatMessages');
        messagesContainer.innerHTML = welcomeHTML;
        
        // Attach click handlers to quick questions
        document.querySelectorAll('.ai-quick-question').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const question = e.target.getAttribute('data-question');
                document.getElementById('aiChatInput').value = question;
                this.sendMessage();
            });
        });
    }
    
    /**
     * Send user message
     */
    async sendMessage() {
        const input = document.getElementById('aiChatInput');
        const message = input.value.trim();
        
        if (!message || this.isTyping) {
            return;
        }
        
        // Clear input
        input.value = '';
        input.style.height = 'auto';
        
        // Add user message to chat
        this.addMessage('user', message);
        
        // Show typing indicator
        this.showTypingIndicator();
        
        // Disable send button
        const sendBtn = document.getElementById('aiChatSend');
        sendBtn.disabled = true;
        
        try {
            // Send to backend
            const response = await fetch('ai_assistant.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    query: message,
                    session_id: this.sessionId
                })
            });
            
            const data = await response.json();
            
            // Hide typing indicator
            this.hideTypingIndicator();
            
            if (data.success) {
                // Add AI response
                this.addMessage('assistant', data.response, data.sql);
            } else {
                // Show error
                const errorMsg = data.user_message || data.error || 'Something went wrong. Please try again.';
                this.addMessage('assistant', errorMsg, null, true);
            }
            
        } catch (error) {
            console.error('Error sending message:', error);
            this.hideTypingIndicator();
            this.addMessage('assistant', 'Sorry, I\'m having trouble connecting. Please try again later.', null, true);
        } finally {
            // Re-enable send button
            sendBtn.disabled = false;
            input.focus();
        }
    }
    
    /**
     * Add message to chat
     */
    addMessage(sender, content, sql = null, isError = false) {
        const messagesContainer = document.getElementById('aiChatMessages');
        const timestamp = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        
        // Format content (convert markdown-style bold to HTML)
        const formattedContent = content.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>').replace(/\n/g, '<br>');
        
        // Don't show SQL in UI anymore, only in console
        if (sql) {
            console.log('SQL executed:', sql);
        }
        
        let errorClass = isError ? ' ai-error-message' : '';
        
        const messageHTML = `
            <div class="ai-message ${sender}">
                <div class="ai-message-avatar">${sender === 'user' ? 'You' : 'AI'}</div>
                <div class="ai-message-content${errorClass}">
                    ${formattedContent}
                    <div class="ai-message-meta">${timestamp}</div>
                </div>
            </div>
        `;
        
        messagesContainer.insertAdjacentHTML('beforeend', messageHTML);
        
        // Scroll to bottom
        messagesContainer.scrollTop = messagesContainer.scrollHeight;
        
        // Store message
        this.messages.push({ sender, content, timestamp, sql });
    }
    
    /**
     * Show typing indicator
     */
    showTypingIndicator() {
        this.isTyping = true;
        const messagesContainer = document.getElementById('aiChatMessages');
        
        const typingHTML = `
            <div class="ai-message assistant" id="aiTypingIndicator">
                <div class="ai-message-avatar">AI</div>
                <div class="ai-typing-indicator">
                    <div class="ai-typing-dots">
                        <span></span>
                        <span></span>
                        <span></span>
                    </div>
                </div>
            </div>
        `;
        
        messagesContainer.insertAdjacentHTML('beforeend', typingHTML);
        messagesContainer.scrollTop = messagesContainer.scrollHeight;
    }
    
    /**
     * Hide typing indicator
     */
    hideTypingIndicator() {
        this.isTyping = false;
        const indicator = document.getElementById('aiTypingIndicator');
        if (indicator) {
            indicator.remove();
        }
    }
    
    /**
     * Escape HTML for safe display
     */
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
}

// Initialize chat widget when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    new AIChatWidget();
});
