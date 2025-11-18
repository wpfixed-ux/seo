/**
 * Craft Catalog Chatbot Widget
 *
 * @package SEOAnalyticsPro
 */

(function() {
    'use strict';

    // Configuration
    const config = window.sapChatbotConfig || {
        apiUrl: '/wp-json/sap/v1',
        ajaxUrl: '/wp-admin/admin-ajax.php',
        nonce: '',
        primaryColor: '#4a90d9',
        title: 'Каталог крафтовых товаров',
        welcomeMessage: 'Привет! Я помогу найти крафтовые товары или зарегистрировать вас как производителя.',
        placeholder: 'Введите сообщение...',
        position: 'right', // 'right' or 'left'
    };

    // Session management
    let sessionId = localStorage.getItem('sap_chatbot_session');
    if (!sessionId) {
        sessionId = 'web_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
        localStorage.setItem('sap_chatbot_session', sessionId);
    }

    // State
    let isOpen = false;
    let isLoading = false;
    let messages = [];

    // Create widget HTML
    function createWidget() {
        const widget = document.createElement('div');
        widget.id = 'sap-chatbot-widget';
        widget.innerHTML = `
            <style>
                #sap-chatbot-widget {
                    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
                    font-size: 14px;
                    line-height: 1.4;
                }

                #sap-chatbot-button {
                    position: fixed;
                    bottom: 20px;
                    ${config.position}: 20px;
                    width: 60px;
                    height: 60px;
                    border-radius: 50%;
                    background: ${config.primaryColor};
                    color: white;
                    border: none;
                    cursor: pointer;
                    box-shadow: 0 4px 12px rgba(0,0,0,0.3);
                    z-index: 9999;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    transition: transform 0.2s, box-shadow 0.2s;
                }

                #sap-chatbot-button:hover {
                    transform: scale(1.1);
                    box-shadow: 0 6px 16px rgba(0,0,0,0.4);
                }

                #sap-chatbot-button svg {
                    width: 28px;
                    height: 28px;
                }

                #sap-chatbot-container {
                    position: fixed;
                    bottom: 90px;
                    ${config.position}: 20px;
                    width: 380px;
                    height: 520px;
                    max-height: calc(100vh - 120px);
                    background: white;
                    border-radius: 12px;
                    box-shadow: 0 8px 32px rgba(0,0,0,0.2);
                    display: none;
                    flex-direction: column;
                    z-index: 9998;
                    overflow: hidden;
                }

                #sap-chatbot-container.open {
                    display: flex;
                }

                @media (max-width: 420px) {
                    #sap-chatbot-container {
                        width: calc(100vw - 20px);
                        ${config.position}: 10px;
                        bottom: 80px;
                        height: calc(100vh - 100px);
                    }
                }

                #sap-chatbot-header {
                    background: ${config.primaryColor};
                    color: white;
                    padding: 16px;
                    font-weight: 600;
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                }

                #sap-chatbot-close {
                    background: none;
                    border: none;
                    color: white;
                    cursor: pointer;
                    font-size: 24px;
                    line-height: 1;
                    padding: 0;
                    opacity: 0.8;
                }

                #sap-chatbot-close:hover {
                    opacity: 1;
                }

                #sap-chatbot-messages {
                    flex: 1;
                    overflow-y: auto;
                    padding: 16px;
                    display: flex;
                    flex-direction: column;
                    gap: 12px;
                }

                .sap-message {
                    max-width: 85%;
                    padding: 10px 14px;
                    border-radius: 12px;
                    word-wrap: break-word;
                }

                .sap-message-user {
                    align-self: flex-end;
                    background: ${config.primaryColor};
                    color: white;
                    border-bottom-right-radius: 4px;
                }

                .sap-message-assistant {
                    align-self: flex-start;
                    background: #f1f3f4;
                    color: #333;
                    border-bottom-left-radius: 4px;
                }

                .sap-message-assistant strong {
                    font-weight: 600;
                }

                .sap-message-actions {
                    display: flex;
                    flex-wrap: wrap;
                    gap: 8px;
                    margin-top: 10px;
                }

                .sap-action-button {
                    padding: 8px 12px;
                    border: 1px solid ${config.primaryColor};
                    background: white;
                    color: ${config.primaryColor};
                    border-radius: 20px;
                    cursor: pointer;
                    font-size: 12px;
                    transition: all 0.2s;
                }

                .sap-action-button:hover {
                    background: ${config.primaryColor};
                    color: white;
                }

                .sap-action-link {
                    padding: 8px 12px;
                    background: #28a745;
                    color: white;
                    border-radius: 20px;
                    text-decoration: none;
                    font-size: 12px;
                }

                #sap-chatbot-input-container {
                    padding: 12px 16px;
                    border-top: 1px solid #e0e0e0;
                    display: flex;
                    gap: 8px;
                }

                #sap-chatbot-input {
                    flex: 1;
                    padding: 10px 14px;
                    border: 1px solid #ddd;
                    border-radius: 20px;
                    outline: none;
                    font-size: 14px;
                }

                #sap-chatbot-input:focus {
                    border-color: ${config.primaryColor};
                }

                #sap-chatbot-send {
                    padding: 10px 16px;
                    background: ${config.primaryColor};
                    color: white;
                    border: none;
                    border-radius: 20px;
                    cursor: pointer;
                    font-weight: 500;
                }

                #sap-chatbot-send:disabled {
                    opacity: 0.5;
                    cursor: not-allowed;
                }

                .sap-typing {
                    display: flex;
                    gap: 4px;
                    padding: 10px 14px;
                    background: #f1f3f4;
                    border-radius: 12px;
                    align-self: flex-start;
                }

                .sap-typing span {
                    width: 8px;
                    height: 8px;
                    background: #999;
                    border-radius: 50%;
                    animation: typing 1s infinite;
                }

                .sap-typing span:nth-child(2) { animation-delay: 0.2s; }
                .sap-typing span:nth-child(3) { animation-delay: 0.4s; }

                @keyframes typing {
                    0%, 100% { opacity: 0.3; }
                    50% { opacity: 1; }
                }
            </style>

            <button id="sap-chatbot-button" title="Открыть чат">
                <svg viewBox="0 0 24 24" fill="currentColor">
                    <path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm0 14H6l-2 2V4h16v12z"/>
                </svg>
            </button>

            <div id="sap-chatbot-container">
                <div id="sap-chatbot-header">
                    <span>${config.title}</span>
                    <button id="sap-chatbot-close">&times;</button>
                </div>
                <div id="sap-chatbot-messages"></div>
                <div id="sap-chatbot-input-container">
                    <input type="text" id="sap-chatbot-input" placeholder="${config.placeholder}" />
                    <button id="sap-chatbot-send">Отправить</button>
                </div>
            </div>
        `;

        document.body.appendChild(widget);

        // Bind events
        document.getElementById('sap-chatbot-button').addEventListener('click', toggleChat);
        document.getElementById('sap-chatbot-close').addEventListener('click', toggleChat);
        document.getElementById('sap-chatbot-send').addEventListener('click', sendMessage);
        document.getElementById('sap-chatbot-input').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                sendMessage();
            }
        });

        // Load history and show welcome message
        loadHistory();
    }

    // Toggle chat window
    function toggleChat() {
        isOpen = !isOpen;
        const container = document.getElementById('sap-chatbot-container');
        if (isOpen) {
            container.classList.add('open');
            document.getElementById('sap-chatbot-input').focus();
            if (messages.length === 0) {
                addMessage(config.welcomeMessage, 'assistant');
            }
        } else {
            container.classList.remove('open');
        }
    }

    // Load chat history
    async function loadHistory() {
        try {
            const response = await fetch(`${config.apiUrl}/chat/history?session_id=${sessionId}&limit=50`);
            const data = await response.json();

            if (data.messages && data.messages.length > 0) {
                messages = data.messages;
                const messagesContainer = document.getElementById('sap-chatbot-messages');
                messagesContainer.innerHTML = '';

                data.messages.forEach(msg => {
                    addMessageToUI(msg.content, msg.role === 'user' ? 'user' : 'assistant');
                });
            }
        } catch (error) {
            console.error('Failed to load chat history:', error);
        }
    }

    // Send message
    async function sendMessage() {
        const input = document.getElementById('sap-chatbot-input');
        const message = input.value.trim();

        if (!message || isLoading) return;

        input.value = '';
        addMessage(message, 'user');
        showTyping();

        try {
            const response = await fetch(config.ajaxUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'sap_chatbot_message',
                    nonce: config.nonce,
                    message: message,
                    session_id: sessionId,
                }),
            });

            const data = await response.json();
            hideTyping();

            if (data.success && data.data) {
                addMessage(data.data.text, 'assistant', data.data.actions);
            } else {
                addMessage('Произошла ошибка. Попробуйте позже.', 'assistant');
            }
        } catch (error) {
            hideTyping();
            addMessage('Ошибка соединения. Проверьте интернет.', 'assistant');
            console.error('Chat error:', error);
        }
    }

    // Add message to state and UI
    function addMessage(text, role, actions = null) {
        messages.push({ content: text, role: role });
        addMessageToUI(text, role, actions);
    }

    // Add message to UI
    function addMessageToUI(text, role, actions = null) {
        const messagesContainer = document.getElementById('sap-chatbot-messages');

        const messageDiv = document.createElement('div');
        messageDiv.className = `sap-message sap-message-${role}`;

        // Convert markdown-like formatting
        let formattedText = text
            .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
            .replace(/\n/g, '<br>');

        messageDiv.innerHTML = formattedText;

        // Add action buttons
        if (actions && actions.length > 0) {
            const actionsDiv = document.createElement('div');
            actionsDiv.className = 'sap-message-actions';

            actions.forEach(action => {
                if (action.type === 'button') {
                    const btn = document.createElement('button');
                    btn.className = 'sap-action-button';
                    btn.textContent = action.text;
                    btn.addEventListener('click', () => {
                        document.getElementById('sap-chatbot-input').value = action.text;
                        sendMessage();
                    });
                    actionsDiv.appendChild(btn);
                } else if (action.type === 'link') {
                    const link = document.createElement('a');
                    link.className = 'sap-action-link';
                    link.href = action.url;
                    link.target = '_blank';
                    link.textContent = action.text;
                    actionsDiv.appendChild(link);
                }
            });

            messageDiv.appendChild(actionsDiv);
        }

        messagesContainer.appendChild(messageDiv);
        messagesContainer.scrollTop = messagesContainer.scrollHeight;
    }

    // Show typing indicator
    function showTyping() {
        isLoading = true;
        document.getElementById('sap-chatbot-send').disabled = true;

        const messagesContainer = document.getElementById('sap-chatbot-messages');
        const typing = document.createElement('div');
        typing.id = 'sap-typing-indicator';
        typing.className = 'sap-typing';
        typing.innerHTML = '<span></span><span></span><span></span>';
        messagesContainer.appendChild(typing);
        messagesContainer.scrollTop = messagesContainer.scrollHeight;
    }

    // Hide typing indicator
    function hideTyping() {
        isLoading = false;
        document.getElementById('sap-chatbot-send').disabled = false;

        const typing = document.getElementById('sap-typing-indicator');
        if (typing) {
            typing.remove();
        }
    }

    // Initialize on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', createWidget);
    } else {
        createWidget();
    }
})();
