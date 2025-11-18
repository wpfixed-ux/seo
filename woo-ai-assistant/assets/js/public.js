/**
 * WooCommerce AI Assistant - Public JavaScript
 */

(function($) {
    'use strict';

    // Generate session ID
    function generateSessionId() {
        return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(c) {
            var r = Math.random() * 16 | 0,
                v = c === 'x' ? r : (r & 0x3 | 0x8);
            return v.toString(16);
        });
    }

    // Search Widget
    function initSearch() {
        $('.waa-search-wrapper').each(function() {
            var $wrapper = $(this);
            var $input = $wrapper.find('.waa-search-input');
            var $results = $wrapper.find('.waa-search-results');
            var language = $wrapper.data('language') || 'ru';
            var searchTimeout;

            $input.on('input', function() {
                clearTimeout(searchTimeout);
                var query = $(this).val().trim();

                if (query.length < 2) {
                    $results.removeClass('active').empty();
                    return;
                }

                searchTimeout = setTimeout(function() {
                    performSearch(query, language, $results);
                }, 300);
            });

            // Close results on click outside
            $(document).on('click', function(e) {
                if (!$wrapper.is(e.target) && $wrapper.has(e.target).length === 0) {
                    $results.removeClass('active');
                }
            });
        });
    }

    function performSearch(query, language, $results) {
        $.ajax({
            url: waaConfig.restUrl + 'search',
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({
                query: query,
                language: language,
                limit: 5
            }),
            success: function(response) {
                if (response.success && response.products.length > 0) {
                    var html = '';
                    response.products.forEach(function(product) {
                        html += '<a href="' + product.url + '" class="waa-search-result-item">';
                        if (product.image) {
                            html += '<img src="' + product.image + '" alt="" class="waa-result-image">';
                        }
                        html += '<div class="waa-result-info">';
                        html += '<div class="waa-result-title">' + product.title + '</div>';
                        html += '<div class="waa-result-price">' + product.price_html + '</div>';
                        html += '<div class="waa-result-stock">' + product.stock_text + '</div>';
                        html += '</div></a>';
                    });
                    $results.html(html).addClass('active');
                } else {
                    $results.removeClass('active').empty();
                }
            }
        });
    }

    // Chat Widget
    function initChat() {
        $('.waa-chat-widget, .waa-floating-chat').each(function() {
            var $chat = $(this);
            var $messages = $chat.find('.waa-chat-messages');
            var $input = $chat.find('.waa-chat-input');
            var $send = $chat.find('.waa-chat-send');
            var $wrapper = $chat.closest('.waa-floating-wrapper');
            var sessionId = $wrapper.data('session') || $chat.data('session') || generateSessionId();
            var language = $wrapper.data('language') || $chat.data('language') || 'ru';
            var isProcessing = false;

            // Enable/disable send button
            $input.on('input', function() {
                $send.prop('disabled', $(this).val().trim() === '' || isProcessing);
                autoResize(this);
            });

            // Send on Enter (Shift+Enter for new line)
            $input.on('keydown', function(e) {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    if (!$send.prop('disabled')) {
                        sendMessage();
                    }
                }
            });

            // Send button click
            $send.on('click', function() {
                sendMessage();
            });

            function sendMessage() {
                var message = $input.val().trim();
                if (!message || isProcessing) return;

                isProcessing = true;
                $send.prop('disabled', true);

                // Add user message
                addMessage(message, 'user');
                $input.val('').trigger('input');

                // Show typing indicator
                var $typing = $('<div class="waa-message waa-message-assistant"><div class="waa-typing-indicator"><span></span><span></span><span></span></div></div>');
                $messages.append($typing);
                scrollToBottom();

                // Send to API
                $.ajax({
                    url: waaConfig.restUrl + 'chat',
                    method: 'POST',
                    contentType: 'application/json',
                    data: JSON.stringify({
                        message: message,
                        session_id: sessionId,
                        language: 'auto'
                    }),
                    success: function(response) {
                        $typing.remove();

                        if (response.success) {
                            addMessage(response.message, 'assistant', response.products);
                            sessionId = response.session_id;
                            language = response.language;
                        } else {
                            addMessage(waaConfig.i18n.error, 'assistant');
                        }
                    },
                    error: function() {
                        $typing.remove();
                        addMessage(waaConfig.i18n.error, 'assistant');
                    },
                    complete: function() {
                        isProcessing = false;
                        $send.prop('disabled', $input.val().trim() === '');
                    }
                });
            }

            function addMessage(content, type, products) {
                var $message = $('<div class="waa-message waa-message-' + type + '"></div>');
                var $content = $('<div class="waa-message-content"></div>');

                // Convert URLs to links
                var linkedContent = content.replace(
                    /(https?:\/\/[^\s]+)/g,
                    '<a href="$1" target="_blank">$1</a>'
                );

                $content.html(linkedContent);
                $message.append($content);

                // Add product cards
                if (products && products.length > 0) {
                    var $cards = $('<div class="waa-product-cards"></div>');
                    products.slice(0, 3).forEach(function(product) {
                        var $card = $('<a href="' + product.url + '" class="waa-product-card" target="_blank"></a>');
                        if (product.image) {
                            $card.append('<img src="' + product.image + '" alt="" class="waa-product-card-image">');
                        }
                        $card.append(
                            '<div class="waa-product-card-info">' +
                            '<div class="waa-product-card-title">' + product.title + '</div>' +
                            '<div class="waa-product-card-price">' + formatPrice(product.price) + '</div>' +
                            '</div>'
                        );
                        $cards.append($card);
                    });
                    $message.append($cards);
                }

                $messages.append($message);
                scrollToBottom();
            }

            function scrollToBottom() {
                $messages.scrollTop($messages[0].scrollHeight);
            }

            function autoResize(textarea) {
                textarea.style.height = 'auto';
                textarea.style.height = Math.min(textarea.scrollHeight, 100) + 'px';
            }

            function formatPrice(price) {
                return parseFloat(price).toLocaleString() + ' ₴';
            }
        });
    }

    // Floating Button
    function initFloating() {
        $('.waa-floating-wrapper').each(function() {
            var $wrapper = $(this);
            var $button = $wrapper.find('.waa-floating-button');
            var $chat = $wrapper.find('.waa-floating-chat');
            var $close = $wrapper.find('.waa-chat-close');
            var $iconChat = $button.find('.waa-icon-chat');
            var $iconClose = $button.find('.waa-icon-close');

            $button.on('click', function() {
                $chat.toggle();
                $iconChat.toggle();
                $iconClose.toggle();

                if ($chat.is(':visible')) {
                    $wrapper.find('.waa-chat-input').focus();
                }
            });

            $close.on('click', function() {
                $chat.hide();
                $iconChat.show();
                $iconClose.hide();
            });
        });
    }

    // Initialize on document ready
    $(document).ready(function() {
        initSearch();
        initChat();
        initFloating();
    });

})(jQuery);
