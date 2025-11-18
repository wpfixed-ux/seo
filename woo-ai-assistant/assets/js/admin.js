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
                data: {
                    action: 'waa_index_batch',
                    nonce: waaAdmin.nonce
                },
                success: function(response) {
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
                            log('Indexing complete!');
                            resetIndexing();
                        } else {
                            // Process next batch
                            setTimeout(processBatch, 500);
                        }
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

    // Initialize
    $(document).ready(function() {
        initTabs();
        initIndexing();
    });

})(jQuery);
