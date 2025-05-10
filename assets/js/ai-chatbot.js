jQuery(document).ready(function($) {
    // Chat elements
    const chatbot = $('#mptbm-ai-chatbot');
    const chatButton = $('.mptbm-chatbot-button', chatbot);
    const chatContainer = $('.mptbm-chatbot-container', chatbot);
    const chatClose = $('.mptbm-chatbot-close', chatbot);
    const clearChat = $('.mptbm-chatbot-clear', chatbot);
    const messagesContainer = $('.mptbm-chatbot-messages', chatbot);
    const inputField = $('textarea', chatbot);
    const sendButton = $('.mptbm-chatbot-send', chatbot);
    
    // Chat state
    let conversationHistory = [];
    let isOpen = false;
    
    // Load saved history if available
    if (chatbot.data('history')) {
        try {
            conversationHistory = chatbot.data('history');
            // Display previous messages
            conversationHistory.forEach(function(message) {
                if (message.role === 'user') {
                    addUserMessage(message.content, false);
                } else if (message.role === 'assistant') {
                    addBotMessage(message.content, false);
                }
            });
            
            // Add a separator to show where the previous conversation ended
            const separator = $('<div class="mptbm-chatbot-separator"><span>' + 
                               (mptbmAIChatbot.i18n.previousConversation || 'Previous conversation') + 
                               '</span></div>');
            messagesContainer.append(separator);
            scrollToBottom();
        } catch (e) {
            console.error('Error loading chat history', e);
            conversationHistory = [];
        }
    } else {
        // Add welcome message if no history
        addBotMessage(mptbmAIChatbot.welcomeMessage);
    }
    
    // Toggle chat open/closed
    chatButton.on('click', function() {
        if (isOpen) {
            closeChat();
        } else {
            openChat();
        }
    });
    
    // Close chat
    chatClose.on('click', function() {
        closeChat();
    });
    
    // Clear chat history
    clearChat.on('click', function() {
        // Confirm before clearing
        if (confirm(mptbmAIChatbot.i18n.confirmClear || 'Are you sure you want to clear the chat history?')) {
            // Clear local conversation history
            conversationHistory = [];
            
            // Clear chat display
            messagesContainer.empty();
            
            // Add welcome message again
            addBotMessage(mptbmAIChatbot.welcomeMessage);
            
            // Clear server-side history
            $.ajax({
                url: mptbmAIChatbot.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'mptbm_clear_chat_history',
                    nonce: mptbmAIChatbot.nonce
                },
                success: function(response) {
                    if (!response.success) {
                        console.error('Error clearing chat history', response);
                    }
                }
            });
        }
    });
    
    // Send message on button click
    sendButton.on('click', function() {
        sendMessage();
    });
    
    // Send message on Enter key (but allow Shift+Enter for new lines)
    inputField.on('keydown', function(e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            sendMessage();
        }
    });
    
    // Open chat function
    function openChat() {
        chatContainer.addClass('active');
        chatButton.addClass('active');
        isOpen = true;
        inputField.focus();
        scrollToBottom();
    }
    
    // Close chat function
    function closeChat() {
        chatContainer.removeClass('active');
        chatButton.removeClass('active');
        isOpen = false;
    }
    
    // Send message function
    function sendMessage() {
        const message = inputField.val().trim();
        
        if (!message) return;
        
        // Add user message to chat
        addUserMessage(message);
        
        // Clear input field
        inputField.val('');
        
        // Save message to history
        conversationHistory.push({
            role: 'user',
            content: message
        });
        
        // Add typing indicator
        const typingIndicator = $('<div class="mptbm-chatbot-message bot typing"><div class="mptbm-chatbot-bubble">' + mptbmAIChatbot.i18n.typing + '</div></div>');
        messagesContainer.append(typingIndicator);
        scrollToBottom();
        
        // Send message to server
        $.ajax({
            url: mptbmAIChatbot.ajaxUrl,
            type: 'POST',
            data: {
                action: 'mptbm_ai_chat_message',
                nonce: mptbmAIChatbot.nonce,
                message: message,
                history: JSON.stringify(conversationHistory)
            },
            success: function(response) {
                // Remove typing indicator
                typingIndicator.remove();
                
                if (response.success) {
                    // Add bot response
                    addBotMessage(response.data.response);
                    
                    // Update conversation history
                    conversationHistory = response.data.history;
                } else {
                    // Show detailed error message
                    let errorMessage = mptbmAIChatbot.i18n.error;
                    if (response.data && typeof response.data === 'string') {
                        errorMessage = 'Error: ' + response.data;
                    }
                    addBotMessage(errorMessage);
                    console.error('AI Chatbot Error:', response.data);
                }
            },
            error: function(xhr, status, error) {
                // Remove typing indicator
                typingIndicator.remove();
                
                // Show detailed error message if available
                let errorMessage = mptbmAIChatbot.i18n.error;
                if (xhr.responseJSON && xhr.responseJSON.data) {
                    errorMessage = 'Error: ' + xhr.responseJSON.data;
                } else if (error) {
                    errorMessage = 'Error: ' + error;
                }
                addBotMessage(errorMessage);
                console.error('AI Chatbot AJAX Error:', status, error, xhr.responseText);
            }
        });
    }
    
    // Add user message to chat
    function addUserMessage(message, scroll = true) {
        const messageElement = $('<div class="mptbm-chatbot-message user"><div class="mptbm-chatbot-bubble">' + formatMessage(message) + '</div></div>');
        messagesContainer.append(messageElement);
        if (scroll) scrollToBottom();
    }
    
    // Add bot message to chat
    function addBotMessage(message, scroll = true) {
        const messageElement = $('<div class="mptbm-chatbot-message bot"><div class="mptbm-chatbot-bubble">' + formatMessage(message) + '</div></div>');
        messagesContainer.append(messageElement);
        if (scroll) scrollToBottom();
    }
    
    // Format message with markdown-like formatting
    function formatMessage(message) {
        // Escape HTML
        let formatted = message.replace(/</g, '&lt;').replace(/>/g, '&gt;');
        
        // Convert URLs to links
        formatted = formatted.replace(
            /(https?:\/\/[^\s]+)/g,
            '<a href="$1" target="_blank" rel="noopener noreferrer">$1</a>'
        );
        
        // Convert newlines to <br>
        formatted = formatted.replace(/\n/g, '<br>');
        
        // Bold text
        formatted = formatted.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
        
        // Italic text
        formatted = formatted.replace(/\*(.*?)\*/g, '<em>$1</em>');
        
        return formatted;
    }
    
    // Scroll to the bottom of the chat
    function scrollToBottom() {
        messagesContainer.scrollTop(messagesContainer[0].scrollHeight);
    }
}); 