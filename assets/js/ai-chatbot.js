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
    let isWaitingForResponse = false; // Track if waiting for response
    
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
                },
                error: function(xhr, status, error) {
                    console.error('Error clearing chat history', status, error);
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
        if (message === '') return;

        // Disable input and show loading
        isWaitingForResponse = true;
        inputField.prop('disabled', true);
        sendButton.prop('disabled', true);
        
        // Add user message to UI
        addUserMessage(message);
        
        // Clear input
        inputField.val('');
        
        // Create loading indicator
        const typingIndicator = $('<div class="mptbm-chatbot-message bot typing"><div class="mptbm-chatbot-bubble">' + 
                                  (mptbmAIChatbot.i18n.typing || 'Thinking...') + 
                                  '<span class="mptbm-chatbot-loading"><span></span><span></span><span></span></span></div></div>');
        messagesContainer.append(typingIndicator);
        scrollToBottom();
        
        // Save message to conversation history
        conversationHistory.push({
            role: 'user', 
            content: message
        });
        
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
            timeout: 60000, // 60 second timeout
            success: function(response) {
                // Remove typing indicator
                typingIndicator.remove();
                
                if (response.success && response.data) {
                    // Add AI response to UI
                    addBotMessage(response.data);
                    
                    // Save response to conversation history
                    conversationHistory.push({
                        role: 'assistant', 
                        content: response.data
                    });
                } else {
                    // Handle error
                    let errorMessage = 'Something went wrong. Please try again.';
                    if (response.data) {
                        errorMessage = response.data;
                    }
                    addBotMessage(errorMessage, true, true); // true for scrolling, true for error
                }
                
                // Enable input
                isWaitingForResponse = false;
                inputField.prop('disabled', false);
                sendButton.prop('disabled', false);
                inputField.focus();
            },
            error: function(jqXHR, textStatus, errorThrown) {
                // Remove typing indicator
                typingIndicator.remove();
                
                // Display specific error message based on status
                let errorMessage = 'An error occurred while connecting to the chatbot.';
                
                if (jqXHR.status === 0) {
                    errorMessage = 'Network error. Please check your internet connection.';
                } else if (jqXHR.status === 404) {
                    errorMessage = 'The chatbot endpoint was not found.';
                } else if (jqXHR.status === 500) {
                    errorMessage = 'Server error. The chatbot service is currently unavailable.';
                } else if (textStatus === 'timeout') {
                    errorMessage = 'Request timed out. The server took too long to respond.';
                } else if (textStatus === 'abort') {
                    errorMessage = 'Request was aborted.';
                } else if (errorThrown) {
                    errorMessage = 'Error: ' + errorThrown;
                }
                
                console.error('AJAX Error:', textStatus, errorThrown, jqXHR);
                
                // Add error message to chat
                addBotMessage(errorMessage, true, true); // true for scrolling, true for error
                
                // Enable input
                isWaitingForResponse = false;
                inputField.prop('disabled', false);
                sendButton.prop('disabled', false);
                inputField.focus();
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
    function addBotMessage(message, scroll = true, isError = false) {
        const messageClass = isError ? 'mptbm-chatbot-message bot error' : 'mptbm-chatbot-message bot';
        const messageElement = $('<div class="' + messageClass + '"><div class="mptbm-chatbot-bubble">' + formatMessage(message) + '</div></div>');
        messagesContainer.append(messageElement);
        if (scroll) scrollToBottom();
        
        // If error, don't add to conversation history
        if (!isError) {
            // Add to conversation history if not already in history
            if (conversationHistory.length === 0 || 
                conversationHistory[conversationHistory.length - 1].role !== 'assistant' ||
                conversationHistory[conversationHistory.length - 1].content !== message) {
                conversationHistory.push({
                    role: 'assistant',
                    content: message
                });
            }
        }
    }
    
    // Format message with markdown-like formatting
    function formatMessage(message) {
        if (!message) return '';
        
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