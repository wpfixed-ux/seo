/**
 * AI SEO Interlinking Admin JavaScript
 *
 * @package AI_SEO_Interlinking
 */

(function($) {
    'use strict';

    const AIL_Admin = {
        /**
         * Initialize
         */
        init: function() {
            this.bindEvents();
            this.initTabs();
        },

        /**
         * Bind events
         */
        bindEvents: function() {
            $('#test-connection').on('click', this.testConnection);
            $('#generate-keywords').on('click', this.generateKeywords);
            $('#process-batch').on('click', this.processBatch);
            $('#clear-logs').on('click', this.clearLogs);
            $('.ail-refresh-stats').on('click', this.refreshStats);
        },

        /**
         * Initialize tabs
         */
        initTabs: function() {
            $('.nav-tab').on('click', function(e) {
                e.preventDefault();

                const targetTab = $(this).attr('href');

                // Update tab navigation
                $('.nav-tab').removeClass('nav-tab-active');
                $(this).addClass('nav-tab-active');

                // Update tab content
                $('.ail-tab-content').removeClass('active');
                $(targetTab).addClass('active');

                // Store active tab in sessionStorage
                sessionStorage.setItem('ail_active_tab', targetTab);
            });

            // Restore active tab from sessionStorage
            const activeTab = sessionStorage.getItem('ail_active_tab');
            if (activeTab) {
                $('.nav-tab[href="' + activeTab + '"]').trigger('click');
            }
        },

        /**
         * Test OpenAI connection
         */
        testConnection: function(e) {
            e.preventDefault();

            const $button = $(this);
            const $status = $('#connection-status');
            const apiKey = $('#openai_api_key').val();

            if (!apiKey) {
                AIL_Admin.showNotice($status, 'error', ailAdmin.strings.error + ': API key is required');
                return;
            }

            $button.prop('disabled', true).text(ailAdmin.strings.testing);
            $status.html('<span class="spinner is-active"></span>');

            $.ajax({
                url: ailAdmin.ajaxurl,
                type: 'POST',
                data: {
                    action: 'ail_test_connection',
                    nonce: ailAdmin.nonce,
                    api_key: apiKey
                },
                success: function(response) {
                    if (response.success) {
                        AIL_Admin.showNotice(
                            $status,
                            'success',
                            ailAdmin.strings.success + ' - Model: ' + response.data.model
                        );
                    } else {
                        AIL_Admin.showNotice(
                            $status,
                            'error',
                            ailAdmin.strings.error + ': ' + response.data.message
                        );
                    }
                },
                error: function(xhr, status, error) {
                    AIL_Admin.showNotice(
                        $status,
                        'error',
                        ailAdmin.strings.error + ': ' + error
                    );
                },
                complete: function() {
                    $button.prop('disabled', false).text('Test Connection');
                }
            });
        },

        /**
         * Generate keywords for all posts
         */
        generateKeywords: function(e) {
            e.preventDefault();

            if (!confirm('This will generate keywords for all posts using AI. This may take a while and consume API credits. Continue?')) {
                return;
            }

            const $button = $(this);
            const $progress = $('#keywords-progress');
            const $progressBar = $('#keywords-progress-bar');
            const $progressText = $('#keywords-progress-text');

            $button.prop('disabled', true);
            $progress.show();

            // Start batch processing
            AIL_Admin.processKeywordsBatch(0, $progressBar, $progressText, function() {
                $button.prop('disabled', false);
                $progress.hide();
                alert('Keywords generation completed!');
            });
        },

        /**
         * Process keywords batch recursively
         */
        processKeywordsBatch: function(offset, $progressBar, $progressText, callback) {
            $.ajax({
                url: ailAdmin.ajaxurl,
                type: 'POST',
                data: {
                    action: 'ail_generate_keywords_batch',
                    nonce: ailAdmin.nonce,
                    offset: offset
                },
                success: function(response) {
                    if (response.success && response.data.has_more) {
                        const progress = response.data.progress || 0;
                        $progressBar.val(progress);
                        $progressText.text(progress + '%');

                        // Continue with next batch
                        AIL_Admin.processKeywordsBatch(
                            response.data.next_offset,
                            $progressBar,
                            $progressText,
                            callback
                        );
                    } else {
                        // Completed
                        $progressBar.val(100);
                        $progressText.text('100%');
                        callback();
                    }
                },
                error: function() {
                    alert('Error processing keywords batch');
                    callback();
                }
            });
        },

        /**
         * Process batch manually
         */
        processBatch: function(e) {
            e.preventDefault();

            if (!confirm('Process a batch of posts now?')) {
                return;
            }

            const $button = $(this);
            const $results = $('#batch-results');

            $button.prop('disabled', true).text(ailAdmin.strings.processing);
            $results.html('<span class="spinner is-active"></span> Processing...').show();

            $.ajax({
                url: ailAdmin.ajaxurl,
                type: 'POST',
                data: {
                    action: 'ail_process_batch',
                    nonce: ailAdmin.nonce,
                    batch_size: $('#batch_size').val() || 50
                },
                success: function(response) {
                    if (response.success) {
                        const data = response.data;
                        $results.html(
                            '<div class="notice notice-success inline">' +
                            '<p><strong>Batch Processing Complete</strong></p>' +
                            '<ul>' +
                            '<li>Processed: ' + data.processed + '</li>' +
                            '<li>Success: ' + data.success + '</li>' +
                            '<li>Errors: ' + data.errors + '</li>' +
                            '<li>Skipped: ' + data.skipped + '</li>' +
                            '</ul>' +
                            '</div>'
                        );
                    } else {
                        $results.html(
                            '<div class="notice notice-error inline">' +
                            '<p>' + response.data.message + '</p>' +
                            '</div>'
                        );
                    }
                },
                error: function(xhr, status, error) {
                    $results.html(
                        '<div class="notice notice-error inline">' +
                        '<p>Error: ' + error + '</p>' +
                        '</div>'
                    );
                },
                complete: function() {
                    $button.prop('disabled', false).text('Process Batch Now');
                }
            });
        },

        /**
         * Clear all logs
         */
        clearLogs: function(e) {
            e.preventDefault();

            if (!confirm('Are you sure you want to clear all logs? This cannot be undone!')) {
                return;
            }

            const $button = $(this);

            $button.prop('disabled', true);

            $.ajax({
                url: ailAdmin.ajaxurl,
                type: 'POST',
                data: {
                    action: 'ail_clear_logs',
                    nonce: ailAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert('Error: ' + response.data.message);
                        $button.prop('disabled', false);
                    }
                },
                error: function() {
                    alert('Error clearing logs');
                    $button.prop('disabled', false);
                }
            });
        },

        /**
         * Refresh statistics
         */
        refreshStats: function(e) {
            e.preventDefault();
            location.reload();
        },

        /**
         * Show notice
         */
        showNotice: function($element, type, message) {
            const iconClass = type === 'success' ? 'dashicons-yes' : 'dashicons-no';
            const html = '<span class="ail-notice ail-notice-' + type + '">' +
                        '<span class="dashicons ' + iconClass + '"></span> ' +
                        message +
                        '</span>';
            $element.html(html);

            // Auto-hide after 5 seconds
            setTimeout(function() {
                $element.fadeOut();
            }, 5000);
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        AIL_Admin.init();
    });

})(jQuery);
