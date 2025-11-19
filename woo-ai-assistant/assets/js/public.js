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
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', waaConfig.nonce);
            },
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
                    beforeSend: function(xhr) {
                        xhr.setRequestHeader('X-WP-Nonce', waaConfig.nonce);
                    },
                    data: JSON.stringify({
                        message: message,
                        session_id: sessionId,
                        language: 'auto'
                    }),
                    success: function(response) {
                        $typing.remove();

                        if (response.success) {
                            addMessage(response.message, 'assistant', response.products, response.message_id);
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

            function addMessage(content, type, products, messageId) {
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
                    var initialCount = 3;

                    products.forEach(function(product, index) {
                        var $card = $('<div class="waa-product-card"></div>');

                        // Hide cards beyond initial count
                        if (index >= initialCount) {
                            $card.addClass('waa-hidden-card');
                        }

                        // Product link with image and info
                        var $link = $('<a href="' + product.url + '" class="waa-product-card-link" target="_blank" data-product-id="' + product.id + '"></a>');
                        if (product.image) {
                            $link.append('<img src="' + product.image + '" alt="" class="waa-product-card-image">');
                        }
                        $link.append(
                            '<div class="waa-product-card-info">' +
                            '<div class="waa-product-card-title">' + product.title + '</div>' +
                            '<div class="waa-product-card-price">' + formatPrice(product.price) + '</div>' +
                            '</div>'
                        );
                        $card.append($link);

                        // Add to cart button
                        if (product.stock_status === 'instock') {
                            var $btn = $('<button class="waa-add-to-cart" data-product-id="' + product.id + '">' +
                                waaConfig.i18n.addToCart + '</button>');
                            $card.append($btn);
                        } else {
                            $card.append('<span class="waa-out-of-stock">' + waaConfig.i18n.outOfStock + '</span>');
                        }

                        $cards.append($card);
                    });

                    // Add "Show more" button if there are more products
                    if (products.length > initialCount) {
                        var moreCount = products.length - initialCount;
                        var $showMore = $('<button class="waa-show-more-products">' +
                            waaConfig.i18n.showMore + ' (' + moreCount + ')' + '</button>');
                        $cards.append($showMore);
                    }

                    $message.append($cards);
                }

                // Add feedback buttons for assistant messages
                if (type === 'assistant' && messageId) {
                    var $feedback = $('<div class="waa-feedback" data-message-id="' + messageId + '"></div>');
                    $feedback.append(
                        '<button class="waa-feedback-btn waa-feedback-up" title="Полезный ответ">' +
                        '<svg viewBox="0 0 24 24" width="14" height="14"><path fill="currentColor" d="M1 21h4V9H1v12zm22-11c0-1.1-.9-2-2-2h-6.31l.95-4.57.03-.32c0-.41-.17-.79-.44-1.06L14.17 1 7.59 7.59C7.22 7.95 7 8.45 7 9v10c0 1.1.9 2 2 2h9c.83 0 1.54-.5 1.84-1.22l3.02-7.05c.09-.23.14-.47.14-.73v-2z"/></svg>' +
                        '</button>' +
                        '<button class="waa-feedback-btn waa-feedback-down" title="Неточный ответ">' +
                        '<svg viewBox="0 0 24 24" width="14" height="14"><path fill="currentColor" d="M15 3H6c-.83 0-1.54.5-1.84 1.22l-3.02 7.05c-.09.23-.14.47-.14.73v2c0 1.1.9 2 2 2h6.31l-.95 4.57-.03.32c0 .41.17.79.44 1.06L9.83 23l6.59-6.59c.36-.36.58-.86.58-1.41V5c0-1.1-.9-2-2-2zm4 0v12h4V3h-4z"/></svg>' +
                        '</button>'
                    );
                    $message.append($feedback);
                }

                $messages.append($message);
                scrollToBottom();
            }

            // Handle feedback clicks
            $messages.on('click', '.waa-feedback-btn', function() {
                var $btn = $(this);
                var $feedback = $btn.closest('.waa-feedback');
                var messageId = $feedback.data('message-id');
                var rating = $btn.hasClass('waa-feedback-up') ? 1 : -1;

                // If negative feedback, show feedback form
                if (rating === -1) {
                    showFeedbackForm(messageId, $feedback);
                } else {
                    submitFeedback(messageId, rating, '', '', $feedback);
                }
            });

            // Handle add to cart clicks
            $messages.on('click', '.waa-add-to-cart', function(e) {
                e.preventDefault();
                var $btn = $(this);
                var productId = $btn.data('product-id');

                if ($btn.hasClass('adding') || $btn.hasClass('added')) {
                    return;
                }

                $btn.addClass('adding').text(waaConfig.i18n.adding);

                $.ajax({
                    url: waaConfig.wcAjaxUrl.replace('%%endpoint%%', 'add_to_cart'),
                    method: 'POST',
                    data: {
                        product_id: productId,
                        quantity: 1
                    },
                    success: function(response) {
                        if (response.error) {
                            $btn.removeClass('adding').text(waaConfig.i18n.addToCart);
                            alert(response.error);
                        } else {
                            $btn.removeClass('adding').addClass('added');
                            $btn.html(waaConfig.i18n.added + ' <a href="' + waaConfig.cartUrl + '">' + waaConfig.i18n.viewCart + '</a>');

                            // Track add to cart event
                            trackEvent(productId, 'add_to_cart');

                            // Update cart fragments if available
                            if (response.fragments) {
                                $.each(response.fragments, function(key, value) {
                                    $(key).replaceWith(value);
                                });
                            }

                            // Trigger WooCommerce added to cart event
                            $(document.body).trigger('added_to_cart', [response.fragments, response.cart_hash, $btn]);
                        }
                    },
                    error: function() {
                        $btn.removeClass('adding').text(waaConfig.i18n.addToCart);
                    }
                });
            });

            // Handle show more products clicks
            $messages.on('click', '.waa-show-more-products', function() {
                var $btn = $(this);
                var $cards = $btn.closest('.waa-product-cards');

                $cards.find('.waa-hidden-card').removeClass('waa-hidden-card');
                $btn.remove();
                scrollToBottom();
            });

            // Track product link clicks
            $messages.on('click', '.waa-product-card-link', function() {
                var productId = $(this).data('product-id');
                if (productId) {
                    trackEvent(productId, 'click');
                }
            });

            function showFeedbackForm(messageId, $feedback) {
                var $form = $('<div class="waa-feedback-form">' +
                    '<select class="waa-feedback-category">' +
                    '<option value="">Выберите проблему...</option>' +
                    '<option value="wrong_product">Неправильный товар</option>' +
                    '<option value="wrong_price">Неверная цена</option>' +
                    '<option value="wrong_stock">Неверное наличие</option>' +
                    '<option value="not_helpful">Не помогло</option>' +
                    '<option value="other">Другое</option>' +
                    '</select>' +
                    '<textarea class="waa-feedback-text" placeholder="Опишите проблему..."></textarea>' +
                    '<div class="waa-feedback-actions">' +
                    '<button class="waa-feedback-submit">Отправить</button>' +
                    '<button class="waa-feedback-cancel">Отмена</button>' +
                    '</div>' +
                    '</div>');

                $feedback.after($form);
                $feedback.hide();

                $form.find('.waa-feedback-submit').on('click', function() {
                    var category = $form.find('.waa-feedback-category').val();
                    var text = $form.find('.waa-feedback-text').val();
                    submitFeedback(messageId, -1, text, category, $feedback);
                    $form.remove();
                });

                $form.find('.waa-feedback-cancel').on('click', function() {
                    $form.remove();
                    $feedback.show();
                });
            }

            function submitFeedback(messageId, rating, text, category, $feedback) {
                $.ajax({
                    url: waaConfig.restUrl + 'feedback',
                    method: 'POST',
                    contentType: 'application/json',
                    beforeSend: function(xhr) {
                        xhr.setRequestHeader('X-WP-Nonce', waaConfig.nonce);
                    },
                    data: JSON.stringify({
                        message_id: messageId,
                        rating: rating,
                        feedback_text: text,
                        category: category
                    }),
                    success: function() {
                        $feedback.html('<span class="waa-feedback-thanks">Спасибо за отзыв!</span>');
                    }
                });
            }

            function scrollToBottom() {
                $messages.scrollTop($messages[0].scrollHeight);
            }

            function autoResize(textarea) {
                textarea.style.height = 'auto';
                textarea.style.height = Math.min(textarea.scrollHeight, 100) + 'px';
            }

            function formatPrice(price) {
                return parseFloat(price).toLocaleString() + ' грн';
            }

            // Track event (click or add_to_cart)
            function trackEvent(productId, eventType) {
                $.ajax({
                    url: waaConfig.restUrl + 'track',
                    method: 'POST',
                    contentType: 'application/json',
                    beforeSend: function(xhr) {
                        xhr.setRequestHeader('X-WP-Nonce', waaConfig.nonce);
                    },
                    data: JSON.stringify({
                        session_id: sessionId,
                        product_id: productId,
                        event_type: eventType
                    })
                });
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
