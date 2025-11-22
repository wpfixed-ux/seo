/**
 * AI Image Generator
 * JavaScript for image generation metaboxes
 */

(function($) {
    'use strict';

    /**
     * Image Generator Handler
     */
    var AIMG_ImageGenerator = {

        init: function() {
            this.bindEvents();
        },

        bindEvents: function() {
            var self = this;

            // Background preset buttons
            $(document).on('click', '.aimg-bg-preset', function(e) {
                e.preventDefault();
                var $btn = $(this);
                var preset = $btn.data('preset');

                // Toggle active state
                $('.aimg-bg-preset').removeClass('active');
                $btn.addClass('active');

                // Show/hide custom prompt field
                if (preset === 'custom' || preset === 'ai') {
                    $('.aimg-custom-prompt').slideDown();
                } else {
                    $('.aimg-custom-prompt').slideUp();
                }
            });

            // Apply background button
            $(document).on('click', '#aimg-apply-background', function(e) {
                e.preventDefault();
                self.applyBackground($(this));
            });

            // Generate product image button
            $(document).on('click', '#aimg-generate-product-image', function(e) {
                e.preventDefault();
                self.generateProductImage($(this));
            });

            // Generate article image button
            $(document).on('click', '#aimg-generate-article-image', function(e) {
                e.preventDefault();
                self.generateArticleImage($(this));
            });

            // Auto-prompt checkbox toggle
            $(document).on('change', '#aimg-use-auto-prompt', function() {
                if ($(this).is(':checked')) {
                    $('#aimg-manual-prompt-field').slideUp();
                } else {
                    $('#aimg-manual-prompt-field').slideDown();
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
            var preset = $('.aimg-bg-preset.active').data('preset');
            var customPrompt = $('#aimg-bg-custom-prompt').val();

            if (!preset) {
                this.showError(__('Please select a background preset'));
                return;
            }

            var $spinner = $btn.siblings('.spinner');
            var $result = $('#aimg-image-result');
            var $log = $('#aimg-generation-log');

            // Show spinner
            $spinner.addClass('is-active');
            $btn.prop('disabled', true);
            $result.hide();
            $log.show();
            this.addLog(__('Preparing to change background...'));

            $.ajax({
                url: aimgData.ajaxurl,
                type: 'POST',
                data: {
                    action: 'aimg_change_image_background',
                    nonce: aimgData.nonce,
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
                            $('.aimg-log-content').html(response.data.log.replace(/\n/g, '<br>'));
                        }

                        // Reload the page to update thumbnail
                        setTimeout(function() {
                            location.reload();
                        }, 2000);
                    } else {
                        $result.html('<p>' + response.data.message + '</p>').removeClass('success').addClass('error').show();
                        if (response.data.log) {
                            $('.aimg-log-content').html(response.data.log.replace(/\n/g, '<br>'));
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
            var prompt = $('#aimg-product-image-prompt').val();

            var $spinner = $btn.siblings('.spinner');
            var $result = $('#aimg-image-result');
            var $log = $('#aimg-generation-log');

            // Show spinner
            $spinner.addClass('is-active');
            $btn.prop('disabled', true);
            $result.hide();
            $log.show();
            this.addLog(__('Starting image generation...'));

            $.ajax({
                url: aimgData.ajaxurl,
                type: 'POST',
                data: {
                    action: 'aimg_generate_product_image',
                    nonce: aimgData.nonce,
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
                            $('.aimg-log-content').html(response.data.log.replace(/\n/g, '<br>'));
                        }

                        // Reload the page to update thumbnail
                        setTimeout(function() {
                            location.reload();
                        }, 2000);
                    } else {
                        $result.html('<p>' + response.data.message + '</p>').removeClass('success').addClass('error').show();
                        if (response.data.log) {
                            $('.aimg-log-content').html(response.data.log.replace(/\n/g, '<br>'));
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
            var autoPrompt = $('#aimg-use-auto-prompt').is(':checked');
            var prompt = $('#aimg-article-image-prompt').val();
            var aspectRatio = $('#aimg-article-aspect-ratio').val();

            var $spinner = $btn.siblings('.spinner');
            var $result = $('#aimg-image-result');
            var $log = $('#aimg-generation-log');

            // Show spinner
            $spinner.addClass('is-active');
            $btn.prop('disabled', true);
            $result.hide();
            $log.show();
            this.addLog(__('Starting image generation...'));

            $.ajax({
                url: aimgData.ajaxurl,
                type: 'POST',
                data: {
                    action: 'aimg_generate_article_image',
                    nonce: aimgData.nonce,
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
                            $('.aimg-log-content').html(response.data.log.replace(/\n/g, '<br>'));
                        }

                        // Reload the page to update thumbnail
                        setTimeout(function() {
                            location.reload();
                        }, 2000);
                    } else {
                        $result.html('<p>' + response.data.message + '</p>').removeClass('success').addClass('error').show();
                        if (response.data.log) {
                            $('.aimg-log-content').html(response.data.log.replace(/\n/g, '<br>'));
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
                url: aimgData.ajaxurl,
                type: 'POST',
                data: {
                    action: 'aimg_test_connection',
                    nonce: aimgData.nonce
                },
                success: function(response) {
                    $spinner.remove();
                    $btn.prop('disabled', false);

                    var $results = $('#api-test-results');
                    if (response.success) {
                        var message = __('Hugging Face API connection successful!');
                        if (response.data.model_used) {
                            message += '<br>' + __('Model: ') + response.data.model_used;
                        }
                        $results.html('<p>' + message + '</p>').removeClass('error').addClass('success').show();
                    } else {
                        $results.html('<p>' + __('Connection failed: ') + response.data.message + '</p>').removeClass('success').addClass('error').show();
                    }
                },
                error: function() {
                    $spinner.remove();
                    $btn.prop('disabled', false);
                    var $results = $('#api-test-results');
                    $results.html('<p>' + __('Connection error') + '</p>').removeClass('success').addClass('error').show();
                }
            });
        },

        /**
         * Add log entry
         */
        addLog: function(message) {
            var timestamp = new Date().toLocaleTimeString('en-US', { hour12: false });
            var entry = '[' + timestamp + '] ' + message;
            var $log = $('.aimg-log-content');

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
            var $result = $('#aimg-image-result');
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
        AIMG_ImageGenerator.init();
    });

})(jQuery);
