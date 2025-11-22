/**
 * AI Image Generator
 * JavaScript for image generation metaboxes
 */

(function($) {
    'use strict';

    /**
     * Image Generator Handler
     */
    var SAP_ImageGenerator = {

        init: function() {
            this.bindEvents();
        },

        bindEvents: function() {
            var self = this;

            // Background preset buttons
            $(document).on('click', '.sap-bg-preset', function(e) {
                e.preventDefault();
                var $btn = $(this);
                var preset = $btn.data('preset');

                // Toggle active state
                $('.sap-bg-preset').removeClass('active');
                $btn.addClass('active');

                // Show/hide custom prompt field
                if (preset === 'custom' || preset === 'ai') {
                    $('.sap-custom-prompt').slideDown();
                } else {
                    $('.sap-custom-prompt').slideUp();
                }
            });

            // Apply background button
            $(document).on('click', '#sap-apply-background', function(e) {
                e.preventDefault();
                self.applyBackground($(this));
            });

            // Generate product image button
            $(document).on('click', '#sap-generate-product-image', function(e) {
                e.preventDefault();
                self.generateProductImage($(this));
            });

            // Generate article image button
            $(document).on('click', '#sap-generate-article-image', function(e) {
                e.preventDefault();
                self.generateArticleImage($(this));
            });

            // Auto-prompt checkbox toggle
            $(document).on('change', '#sap-use-auto-prompt', function() {
                if ($(this).is(':checked')) {
                    $('#sap-manual-prompt-field').slideUp();
                } else {
                    $('#sap-manual-prompt-field').slideDown();
                }
            });

            // Test Hugging Face connection (on settings page)
            $(document).on('click', '#test-huggingface-connection', function(e) {
                e.preventDefault();
                self.testConnection($(this));
            });
        },

        /**
         * Apply background to product image
         */
        applyBackground: function($btn) {
            var postId = $btn.data('post-id');
            var preset = $('.sap-bg-preset.active').data('preset');
            var customPrompt = $('#sap-bg-custom-prompt').val();

            if (!preset) {
                this.showError(__('Please select a background preset'));
                return;
            }

            var $spinner = $btn.siblings('.spinner');
            var $result = $('#sap-image-result');
            var $log = $('#sap-generation-log');

            // Show spinner
            $spinner.addClass('is-active');
            $btn.prop('disabled', true);
            $result.hide();
            $log.show();
            this.addLog(__('Preparing to change background...'));

            $.ajax({
                url: sapData.ajaxurl,
                type: 'POST',
                data: {
                    action: 'sap_change_image_background',
                    nonce: sapData.nonce,
                    post_id: postId,
                    preset: preset,
                    custom_prompt: customPrompt
                },
                success: function(response) {
                    $spinner.removeClass('is-active');
                    $btn.prop('disabled', false);

                    if (response.success) {
                        var html = '<p>' + response.data.message + '</p>';
                        if (response.data.image_url) {
                            html += '<img src="' + response.data.image_url + '" style="max-width:100%; height:auto;">';
                        }
                        $result.html(html).removeClass('error').addClass('success').show();

                        if (response.data.log) {
                            $('.sap-log-content').html(response.data.log.replace(/\n/g, '<br>'));
                        }

                        // Reload the page to update thumbnail
                        setTimeout(function() {
                            location.reload();
                        }, 2000);
                    } else {
                        $result.html('<p>' + response.data.message + '</p>').removeClass('success').addClass('error').show();
                        if (response.data.log) {
                            $('.sap-log-content').html(response.data.log.replace(/\n/g, '<br>'));
                        }
                    }
                },
                error: function() {
                    $spinner.removeClass('is-active');
                    $btn.prop('disabled', false);
                    $result.html('<p>' + __('Connection error') + '</p>').removeClass('success').addClass('error').show();
                }
            });
        },

        /**
         * Generate product image
         */
        generateProductImage: function($btn) {
            var postId = $btn.data('post-id');
            var prompt = $('#sap-product-image-prompt').val();

            var $spinner = $btn.siblings('.spinner');
            var $result = $('#sap-image-result');
            var $log = $('#sap-generation-log');

            // Show spinner
            $spinner.addClass('is-active');
            $btn.prop('disabled', true);
            $result.hide();
            $log.show();
            this.addLog(__('Starting image generation...'));

            $.ajax({
                url: sapData.ajaxurl,
                type: 'POST',
                data: {
                    action: 'sap_generate_product_image',
                    nonce: sapData.nonce,
                    post_id: postId,
                    prompt: prompt
                },
                success: function(response) {
                    $spinner.removeClass('is-active');
                    $btn.prop('disabled', false);

                    if (response.success) {
                        var html = '<p>' + response.data.message + '</p>';
                        if (response.data.image_url) {
                            html += '<img src="' + response.data.image_url + '" style="max-width:100%; height:auto; margin-top:10px;">';
                        }
                        $result.html(html).removeClass('error').addClass('success').show();

                        if (response.data.log) {
                            $('.sap-log-content').html(response.data.log.replace(/\n/g, '<br>'));
                        }

                        // Reload the page to update thumbnail
                        setTimeout(function() {
                            location.reload();
                        }, 2000);
                    } else {
                        $result.html('<p>' + response.data.message + '</p>').removeClass('success').addClass('error').show();
                        if (response.data.log) {
                            $('.sap-log-content').html(response.data.log.replace(/\n/g, '<br>'));
                        }
                    }
                },
                error: function() {
                    $spinner.removeClass('is-active');
                    $btn.prop('disabled', false);
                    $result.html('<p>' + __('Connection error') + '</p>').removeClass('success').addClass('error').show();
                }
            });
        },

        /**
         * Generate article image
         */
        generateArticleImage: function($btn) {
            var postId = $btn.data('post-id');
            var autoPrompt = $('#sap-use-auto-prompt').is(':checked');
            var prompt = $('#sap-article-image-prompt').val();
            var aspectRatio = $('#sap-article-aspect-ratio').val();

            var $spinner = $btn.siblings('.spinner');
            var $result = $('#sap-image-result');
            var $log = $('#sap-generation-log');

            // Show spinner
            $spinner.addClass('is-active');
            $btn.prop('disabled', true);
            $result.hide();
            $log.show();
            this.addLog(__('Starting image generation...'));

            $.ajax({
                url: sapData.ajaxurl,
                type: 'POST',
                data: {
                    action: 'sap_generate_article_image',
                    nonce: sapData.nonce,
                    post_id: postId,
                    prompt: prompt,
                    auto_prompt: autoPrompt ? 1 : 0,
                    aspect_ratio: aspectRatio
                },
                success: function(response) {
                    $spinner.removeClass('is-active');
                    $btn.prop('disabled', false);

                    if (response.success) {
                        var html = '<p>' + response.data.message + '</p>';
                        if (response.data.image_url) {
                            html += '<img src="' + response.data.image_url + '" style="max-width:100%; height:auto; margin-top:10px;">';
                        }
                        $result.html(html).removeClass('error').addClass('success').show();

                        if (response.data.log) {
                            $('.sap-log-content').html(response.data.log.replace(/\n/g, '<br>'));
                        }

                        // Reload the page to update thumbnail
                        setTimeout(function() {
                            location.reload();
                        }, 2000);
                    } else {
                        $result.html('<p>' + response.data.message + '</p>').removeClass('success').addClass('error').show();
                        if (response.data.log) {
                            $('.sap-log-content').html(response.data.log.replace(/\n/g, '<br>'));
                        }
                    }
                },
                error: function() {
                    $spinner.removeClass('is-active');
                    $btn.prop('disabled', false);
                    $result.html('<p>' + __('Connection error') + '</p>').removeClass('success').addClass('error').show();
                }
            });
        },

        /**
         * Test Hugging Face API connection
         */
        testConnection: function($btn) {
            var $spinner = $('<span class="spinner is-active" style="float:none;margin-left:10px;"></span>');
            $btn.after($spinner);
            $btn.prop('disabled', true);

            $.ajax({
                url: sapData.ajaxurl,
                type: 'POST',
                data: {
                    action: 'sap_test_huggingface_connection',
                    nonce: sapData.nonce
                },
                success: function(response) {
                    $spinner.remove();
                    $btn.prop('disabled', false);

                    if (response.success) {
                        var message = __('Hugging Face API connection successful!');
                        if (response.data.model_used) {
                            message += '\n' + __('Model: ') + response.data.model_used;
                        }
                        alert(message);
                    } else {
                        alert(__('Connection failed: ') + response.data.message);
                    }
                },
                error: function() {
                    $spinner.remove();
                    $btn.prop('disabled', false);
                    alert(__('Connection error'));
                }
            });
        },

        /**
         * Add log entry
         */
        addLog: function(message) {
            var timestamp = new Date().toLocaleTimeString('en-US', { hour12: false });
            var entry = '[' + timestamp + '] ' + message;
            var $log = $('.sap-log-content');

            if ($log.length) {
                var currentLog = $log.html();
                if (currentLog) {
                    $log.html(currentLog + '<br>' + entry);
                } else {
                    $log.html(entry);
                }
                // Auto-scroll to bottom
                $log.scrollTop($log[0].scrollHeight);
            }
        },

        /**
         * Show error message
         */
        showError: function(message) {
            var $result = $('#sap-image-result');
            $result.html('<p>' + message + '</p>').removeClass('success').addClass('error').show();
        }
    };

    /**
     * Simple translation function
     */
    function __(text) {
        // In a real implementation, this would use WordPress i18n
        // For now, return the text as-is
        return text;
    }

    /**
     * Initialize on document ready
     */
    $(document).ready(function() {
        SAP_ImageGenerator.init();
    });

})(jQuery);
