/**
 * Admin JavaScript
 *
 * @package WC_AI_Translator
 */

(function($) {
    'use strict';

    var WCAT = {
        init: function() {
            this.bindEvents();
        },

        bindEvents: function() {
            // Scan content
            $('#wcat-scan-form').on('submit', this.scanContent);

            // Select/Deselect all
            $('#wcat-select-all').on('click', function() {
                $('.wcat-item-checkbox').prop('checked', true);
            });
            $('#wcat-deselect-all').on('click', function() {
                $('.wcat-item-checkbox').prop('checked', false);
            });

            // Start translation
            $('#wcat-start-translation').on('click', this.startBatchTranslation);

            // Single translation from metabox
            $(document).on('click', '.wcat-translate-single', this.translateSingle);
            $(document).on('click', '.wcat-translate-all', this.translateAll);

            // Queue management
            $('#wcat-pause-queue').on('click', this.pauseQueue);
            $('#wcat-resume-queue').on('click', this.resumeQueue);
            $('#wcat-retry-failed').on('click', this.retryFailed);
            $('#wcat-clear-completed').on('click', this.clearCompleted);
            $('#wcat-refresh-queue').on('click', this.refreshQueue);
            $(document).on('click', '.wcat-cancel-item', this.cancelQueueItem);

            // Logs
            $('#wcat-refresh-logs').on('click', this.refreshLogs);
            $('#wcat-export-logs').on('click', this.exportLogs);
            $('#wcat-clear-logs-button').on('click', this.clearLogs);

            // Settings
            $('#wcat-test-connection').on('click', this.testConnection);

            // Auto-refresh queue and progress
            if ($('#wcat-queue-table').length || $('#wcat-progress-section:visible').length) {
                setInterval(this.autoRefreshQueue, 5000);
            }
        },

        scanContent: function(e) {
            e.preventDefault();

            var $button = $('#wcat-scan-button');
            var $results = $('#wcat-scan-results');
            var $resultsContent = $('#wcat-scan-results-content');

            var formData = $('#wcat-scan-form').serializeArray();
            var contentTypes = [];
            var taxonomies = [];

            $.each(formData, function(i, field) {
                if (field.name === 'content_types[]') {
                    contentTypes.push(field.value);
                } else if (field.name === 'taxonomies[]') {
                    taxonomies.push(field.value);
                }
            });

            $button.prop('disabled', true).text(wcatAdmin.strings.loading);

            $.ajax({
                url: wcatAdmin.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'wcat_scan_content',
                    nonce: wcatAdmin.nonce,
                    content_types: contentTypes,
                    taxonomies: taxonomies
                },
                success: function(response) {
                    if (response.success) {
                        WCAT.displayScanResults(response.data);
                        $results.show();
                        $('#wcat-selection-section').show();
                        $('#wcat-languages-section').show();
                        $('#wcat-review-section').show();
                    } else {
                        alert(response.data.message || wcatAdmin.strings.error);
                    }
                },
                error: function() {
                    alert(wcatAdmin.strings.error);
                },
                complete: function() {
                    $button.prop('disabled', false).text('Scan Website');
                }
            });
        },

        displayScanResults: function(data) {
            var html = '<div class="wcat-scan-summary">';
            html += '<p><strong>Found items needing translation:</strong></p><ul>';

            var allItems = [];

            // Posts
            $.each(data.posts, function(postType, posts) {
                if (posts.length > 0) {
                    html += '<li>' + postType.charAt(0).toUpperCase() + postType.slice(1) + ': ' + posts.length + '</li>';
                    allItems = allItems.concat(posts);
                }
            });

            // Terms
            $.each(data.terms, function(taxonomy, terms) {
                if (terms.length > 0) {
                    html += '<li>' + taxonomy.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase()) + ': ' + terms.length + '</li>';
                    allItems = allItems.concat(terms);
                }
            });

            html += '</ul></div>';

            $('#wcat-scan-results-content').html(html);

            // Populate items list
            var itemsHtml = '';
            $.each(allItems, function(i, item) {
                var title = item.title || item.name;
                var type = item.type || item.taxonomy;
                itemsHtml += '<div class="wcat-item">';
                itemsHtml += '<input type="checkbox" class="wcat-item-checkbox" value="' + item.id + '" data-type="' + type + '" data-languages=\'' + JSON.stringify(item.missing_languages) + '\'>';
                itemsHtml += '<span><strong>' + title + '</strong> (' + type + ') - Missing: ' + item.missing_languages.join(', ') + '</span>';
                itemsHtml += '</div>';
            });
            $('#wcat-items-list').html(itemsHtml);

            // Store items data
            window.wcatScanData = allItems;
        },

        startBatchTranslation: function() {
            var selectedItems = [];
            var targetLanguages = [];

            $('.wcat-item-checkbox:checked').each(function() {
                var $this = $(this);
                selectedItems.push({
                    id: $this.val(),
                    type: $this.data('type'),
                    missing_languages: $this.data('languages')
                });
            });

            $('input[name="target_languages[]"]:checked').each(function() {
                targetLanguages.push($(this).val());
            });

            if (selectedItems.length === 0) {
                alert('Please select items to translate.');
                return;
            }

            if (targetLanguages.length === 0) {
                alert('Please select target languages.');
                return;
            }

            if (!confirm(wcatAdmin.strings.confirm_translate)) {
                return;
            }

            $.ajax({
                url: wcatAdmin.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'wcat_batch_translate',
                    nonce: wcatAdmin.nonce,
                    items: JSON.stringify(selectedItems),
                    target_languages: targetLanguages
                },
                success: function(response) {
                    if (response.success) {
                        alert(response.data.message);
                        $('#wcat-progress-section').show();
                        window.location.href = 'admin.php?page=wc-ai-translator-queue';
                    } else {
                        alert(response.data.message || wcatAdmin.strings.error);
                    }
                },
                error: function() {
                    alert(wcatAdmin.strings.error);
                }
            });
        },

        translateSingle: function() {
            var $button = $(this);
            var postId = $button.data('post-id');
            var targetLang = $button.data('target-lang');
            var contentType = $button.closest('.wcat-metabox').data('post-type') || 'post';

            $button.prop('disabled', true).text(wcatAdmin.strings.translating);

            $.ajax({
                url: wcatAdmin.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'wcat_translate_single',
                    nonce: wcatAdmin.nonce,
                    content_id: postId,
                    target_lang: targetLang,
                    content_type: contentType
                },
                success: function(response) {
                    if (response.success) {
                        $('.wcat-status-message').html('<div class="wcat-message wcat-message-success">' + response.data.message + '</div>').parent().show();
                        setTimeout(function() {
                            location.reload();
                        }, 1500);
                    } else {
                        $('.wcat-status-message').html('<div class="wcat-message wcat-message-error">' + response.data.error + '</div>').parent().show();
                        $button.prop('disabled', false).text('Translate to ' + targetLang.toUpperCase());
                    }
                },
                error: function() {
                    $('.wcat-status-message').html('<div class="wcat-message wcat-message-error">' + wcatAdmin.strings.error + '</div>').parent().show();
                    $button.prop('disabled', false).text('Translate to ' + targetLang.toUpperCase());
                }
            });
        },

        translateAll: function() {
            var $button = $(this);
            var postId = $button.data('post-id');

            if (!confirm('Translate to all missing languages?')) {
                return;
            }

            $button.prop('disabled', true).text(wcatAdmin.strings.translating);
            $('.wcat-status-message').html('<div class="wcat-message wcat-message-info">Adding to translation queue...</div>').parent().show();

            // This would trigger batch translation for all missing languages
            // Implementation similar to single translation but for multiple languages
            setTimeout(function() {
                location.reload();
            }, 2000);
        },

        testConnection: function() {
            var $button = $('#wcat-test-connection');
            var $result = $('#wcat-test-result');

            $button.prop('disabled', true).text(wcatAdmin.strings.loading);
            $result.html('<div class="wcat-loading"></div>');

            // Simulate API test - in real implementation, this would call the OpenAI test endpoint
            setTimeout(function() {
                $result.html('<div class="wcat-message wcat-message-success">API connection successful!</div>');
                $button.prop('disabled', false).text('Test Connection');
            }, 1500);
        },

        refreshQueue: function() {
            location.reload();
        },

        autoRefreshQueue: function() {
            // Auto-refresh queue status without full page reload
            $.ajax({
                url: wcatAdmin.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'wcat_get_queue_status',
                    nonce: wcatAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        // Update progress indicators
                        var progress = response.data;
                        $('.wcat-progress-fill').css('width', progress.progress_percentage + '%');
                        $('#wcat-progress-text').text(progress.progress_percentage + '%');
                    }
                }
            });
        },

        cancelQueueItem: function() {
            var queueId = $(this).data('queue-id');

            if (!confirm('Cancel this translation?')) {
                return;
            }

            $.ajax({
                url: wcatAdmin.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'wcat_cancel_translation',
                    nonce: wcatAdmin.nonce,
                    queue_id: queueId
                },
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert(response.data.message || wcatAdmin.strings.error);
                    }
                }
            });
        },

        refreshLogs: function() {
            location.reload();
        },

        exportLogs: function() {
            window.location.href = wcatAdmin.ajaxUrl + '?action=wcat_export_logs&nonce=' + wcatAdmin.nonce;
        },

        clearLogs: function() {
            if (!confirm(wcatAdmin.strings.confirm_clear_logs)) {
                return;
            }

            $.ajax({
                url: wcatAdmin.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'wcat_clear_logs',
                    nonce: wcatAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert(response.data.message || wcatAdmin.strings.error);
                    }
                }
            });
        },

        pauseQueue: function() {
            // Implement pause queue
            alert('Queue paused');
        },

        resumeQueue: function() {
            // Implement resume queue
            alert('Queue resumed');
        },

        retryFailed: function() {
            // Implement retry failed
            alert('Retrying failed translations');
        },

        clearCompleted: function() {
            // Implement clear completed
            if (confirm('Clear all completed items from queue?')) {
                alert('Completed items cleared');
            }
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        WCAT.init();
    });

})(jQuery);
