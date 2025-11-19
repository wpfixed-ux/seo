/**
 * WooCommerce AI Assistant - Admin JavaScript
 */

(function($) {
    'use strict';

    // Settings tabs
    function initTabs() {
        $('.nav-tab-wrapper .nav-tab').on('click', function(e) {
            e.preventDefault();
            var target = $(this).attr('href');

            $('.nav-tab').removeClass('nav-tab-active');
            $(this).addClass('nav-tab-active');

            $('.waa-tab-content').removeClass('active');
            $(target).addClass('active');
        });
    }

    // Indexing
    function initIndexing() {
        var $startBtn = $('#waa-start-index');
        var $clearBtn = $('#waa-clear-index');
        var $progress = $('.waa-progress-wrapper');
        var $progressFill = $('.waa-progress-fill');
        var $progressText = $('.waa-progress-text');
        var $log = $('.waa-index-log');
        var isIndexing = false;
        var retryCount = 0;
        var maxRetries = 3;
        var batchDelay = 100; // Reduced delay for faster indexing

        $startBtn.on('click', function() {
            if (isIndexing) return;

            if (!confirm(waaAdmin.i18n.confirm_reindex)) return;

            isIndexing = true;
            $startBtn.prop('disabled', true).text(waaAdmin.i18n.indexing);
            $progress.show();
            $log.show().empty();

            // Start indexing
            $.ajax({
                url: waaAdmin.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'waa_start_indexing',
                    nonce: waaAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        log('Started indexing: ' + response.data.total_products + ' products, ' + response.data.total_posts + ' posts');
                        processBatch();
                    } else {
                        log('Error: ' + response.data, 'error');
                        resetIndexing();
                    }
                },
                error: function() {
                    log('Network error', 'error');
                    resetIndexing();
                }
            });
        });

        function processBatch() {
            $.ajax({
                url: waaAdmin.ajaxUrl,
                method: 'POST',
                timeout: 60000, // 60 second timeout for large batches
                data: {
                    action: 'waa_index_batch',
                    nonce: waaAdmin.nonce
                },
                success: function(response) {
                    retryCount = 0; // Reset retry counter on success

                    if (response.success) {
                        var data = response.data;
                        var total = data.total_products + data.total_posts;
                        var indexed = data.indexed_products + data.indexed_posts;
                        var percent = total > 0 ? Math.round((indexed / total) * 100) : 0;

                        $progressFill.css('width', percent + '%');
                        $progressText.text(indexed + ' / ' + total + ' (' + percent + '%)');

                        // Log errors
                        if (data.errors && data.errors.length > 0) {
                            data.errors.forEach(function(error) {
                                log(error, 'error');
                            });
                        }

                        if (data.is_complete) {
                            log('Индексация завершена! Проиндексировано: ' + indexed + ' элементов');
                            $progressFill.css('width', '100%');
                            resetIndexing();
                        } else {
                            // Process next batch with reduced delay
                            setTimeout(processBatch, batchDelay);
                        }
                    } else {
                        log('Error: ' + response.data, 'error');
                        resetIndexing();
                    }
                },
                error: function(xhr, status, error) {
                    retryCount++;

                    if (retryCount <= maxRetries) {
                        var delay = Math.pow(2, retryCount) * 1000; // Exponential backoff: 2s, 4s, 8s
                        log('Ошибка сети, повтор через ' + (delay/1000) + ' сек... (попытка ' + retryCount + '/' + maxRetries + ')', 'error');
                        setTimeout(processBatch, delay);
                    } else {
                        log('Не удалось завершить индексацию после ' + maxRetries + ' попыток: ' + error, 'error');
                        resetIndexing();
                    }
                }
            });
        }

        function resetIndexing() {
            isIndexing = false;
            $startBtn.prop('disabled', false).text('Начать индексацию');
        }

        function log(message, type) {
            var className = type === 'error' ? 'error' : '';
            $log.append('<p class="' + className + '">' + message + '</p>');
            $log.scrollTop($log[0].scrollHeight);
        }

        // Clear index
        $clearBtn.on('click', function() {
            if (!confirm('Очистить весь индекс?')) return;

            $.ajax({
                url: waaAdmin.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'waa_clear_index',
                    nonce: waaAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        alert('Индекс очищен');
                        location.reload();
                    } else {
                        alert('Ошибка: ' + response.data);
                    }
                }
            });
        });
    }

    // Test Connection
    function initTestConnection() {
        var $testBtn = $('#waa-test-connection');
        var $result = $('#waa-test-result');

        $testBtn.on('click', function() {
            $testBtn.prop('disabled', true).text('Testing...');
            $result.html('<span style="color: #666;">Connecting...</span>');

            $.ajax({
                url: waaAdmin.restUrl + 'test-connection',
                method: 'POST',
                headers: {
                    'X-WP-Nonce': waaAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $result.html('<span style="color: green;">✓ ' + response.message + '</span>');
                    } else {
                        $result.html('<span style="color: red;">✗ ' + response.message + '</span>');
                    }
                },
                error: function(xhr) {
                    var message = 'Connection failed';
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        message = xhr.responseJSON.message;
                    }
                    $result.html('<span style="color: red;">✗ ' + message + '</span>');
                },
                complete: function() {
                    $testBtn.prop('disabled', false).text('Test Connection');
                }
            });
        });
    }

    // API Logs
    function initApiLogs() {
        var $loadBtn = $('#waa-load-logs');
        var $clearBtn = $('#waa-clear-logs');
        var $logsContainer = $('#waa-api-logs');

        $loadBtn.on('click', function() {
            $loadBtn.prop('disabled', true).text('Loading...');

            $.ajax({
                url: waaAdmin.restUrl + 'api-logs',
                method: 'GET',
                headers: {
                    'X-WP-Nonce': waaAdmin.nonce
                },
                success: function(response) {
                    if (response.success && response.logs) {
                        if (response.logs.length === 0) {
                            $logsContainer.html('<em>No logs available</em>').show();
                        } else {
                            var html = '';
                            response.logs.forEach(function(log) {
                                html += '<div style="margin-bottom: 10px; padding-bottom: 10px; border-bottom: 1px solid #ddd;">';
                                html += '<strong>' + log.timestamp + '</strong> - ' + log.action + '\n';
                                html += JSON.stringify(log.data, null, 2);
                                html += '</div>';
                            });
                            $logsContainer.html(html).show();
                        }
                    } else {
                        $logsContainer.html('<em>Failed to load logs</em>').show();
                    }
                },
                error: function() {
                    $logsContainer.html('<em>Error loading logs</em>').show();
                },
                complete: function() {
                    $loadBtn.prop('disabled', false).text('Показать логи');
                }
            });
        });

        $clearBtn.on('click', function() {
            if (!confirm('Clear all API logs?')) return;

            $clearBtn.prop('disabled', true);

            $.ajax({
                url: waaAdmin.restUrl + 'api-logs',
                method: 'DELETE',
                headers: {
                    'X-WP-Nonce': waaAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $logsContainer.html('<em>Logs cleared</em>').show();
                        setTimeout(function() {
                            $logsContainer.hide();
                        }, 2000);
                    }
                },
                complete: function() {
                    $clearBtn.prop('disabled', false);
                }
            });
        });
    }

    // Initialize
    $(document).ready(function() {
        initTabs();
        initIndexing();
        initTestConnection();
        initApiLogs();
    });

})(jQuery);
