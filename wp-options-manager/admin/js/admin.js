/**
 * WP Options Manager - Admin JavaScript
 */

(function($) {
    'use strict';

    const WPOM = {
        init: function() {
            this.bindEvents();
        },

        bindEvents: function() {
            // Dashboard actions
            $('.wpom-btn-clean-transients').on('click', this.cleanTransients);
            $('.wpom-btn-clean-all-transients').on('click', this.cleanAllTransients);
            $('.wpom-btn-clean-wc-sessions').on('click', this.cleanWcSessions);
            $('.wpom-btn-disable-autoload').on('click', this.disableAutoload);

            // Cleaner page actions
            $('.wpom-btn-preview-pattern').on('click', this.previewPattern);
            $('.wpom-btn-clean-pattern').on('click', this.cleanByPattern);

            // Diagnostic page actions
            $('.wpom-btn-refresh-diagnostic').on('click', this.refreshDiagnostic);

            // Pattern input change
            $('#wpom-pattern-input').on('input', function() {
                $('.wpom-btn-clean-pattern').prop('disabled', $(this).val().trim() === '');
                $('#wpom-pattern-preview').hide();
            });
        },

        showLoader: function() {
            $('#wpom-loader').fadeIn(200);
        },

        hideLoader: function() {
            $('#wpom-loader').fadeOut(200);
        },

        showResult: function(message, type) {
            const $result = $('#wpom-operation-result');
            $result.removeClass('success error')
                   .addClass(type)
                   .html(message)
                   .fadeIn(200);

            setTimeout(function() {
                $result.fadeOut(200);
            }, 5000);
        },

        showNotice: function(message, type) {
            const noticeClass = type === 'success' ? 'notice-success' : 'notice-error';
            const $notice = $('<div class="notice ' + noticeClass + ' is-dismissible"><p>' + message + '</p></div>');
            $('.wrap h1').after($notice);

            setTimeout(function() {
                $notice.fadeOut(200, function() {
                    $(this).remove();
                });
            }, 5000);
        },

        cleanTransients: function(e) {
            e.preventDefault();

            if (!confirm(wpomAjax.strings.confirm_clean)) {
                return;
            }

            const type = $(this).data('type');
            WPOM.showLoader();

            $.ajax({
                url: wpomAjax.ajaxurl,
                type: 'POST',
                data: {
                    action: 'wpom_clean_transients',
                    nonce: wpomAjax.nonce,
                    type: type
                },
                success: function(response) {
                    WPOM.hideLoader();

                    if (response.success) {
                        const message = '<strong>Success!</strong> Deleted ' + response.data.deleted + ' transients. ' +
                                      'Freed ' + (response.data.size_freed / 1024).toFixed(2) + ' KB.';
                        WPOM.showResult(message, 'success');

                        // Reload page after 2 seconds
                        setTimeout(function() {
                            location.reload();
                        }, 2000);
                    } else {
                        WPOM.showResult('<strong>Error:</strong> ' + response.data.error, 'error');
                    }
                },
                error: function() {
                    WPOM.hideLoader();
                    WPOM.showResult('<strong>Error:</strong> Ajax request failed.', 'error');
                }
            });
        },

        cleanAllTransients: function(e) {
            e.preventDefault();

            if (!confirm(wpomAjax.strings.confirm_clean)) {
                return;
            }

            WPOM.showLoader();

            // Clean both expired and orphaned
            const requests = [
                $.ajax({
                    url: wpomAjax.ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'wpom_clean_transients',
                        nonce: wpomAjax.nonce,
                        type: 'expired'
                    }
                }),
                $.ajax({
                    url: wpomAjax.ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'wpom_clean_transients',
                        nonce: wpomAjax.nonce,
                        type: 'orphaned'
                    }
                })
            ];

            $.when.apply($, requests).done(function() {
                WPOM.hideLoader();

                let totalDeleted = 0;
                let totalFreed = 0;

                // Sum up results from both requests
                for (let i = 0; i < arguments.length; i++) {
                    const response = arguments[i][0];
                    if (response.success) {
                        totalDeleted += response.data.deleted;
                        totalFreed += response.data.size_freed;
                    }
                }

                const message = '<strong>Success!</strong> Deleted ' + totalDeleted + ' transients. ' +
                              'Freed ' + (totalFreed / 1024).toFixed(2) + ' KB.';
                WPOM.showResult(message, 'success');

                setTimeout(function() {
                    location.reload();
                }, 2000);
            }).fail(function() {
                WPOM.hideLoader();
                WPOM.showResult('<strong>Error:</strong> Operation failed.', 'error');
            });
        },

        cleanWcSessions: function(e) {
            e.preventDefault();

            const force = $(this).data('force') === 'true' || $(this).data('force') === true;

            if (force) {
                if (!confirm(wpomAjax.strings.confirm_force_clean)) {
                    return;
                }
            } else {
                if (!confirm(wpomAjax.strings.confirm_clean)) {
                    return;
                }
            }

            WPOM.showLoader();

            $.ajax({
                url: wpomAjax.ajaxurl,
                type: 'POST',
                data: {
                    action: 'wpom_clean_wc_sessions',
                    nonce: wpomAjax.nonce,
                    force: force
                },
                success: function(response) {
                    WPOM.hideLoader();

                    if (response.success) {
                        const message = '<strong>Success!</strong> Deleted ' + response.data.deleted + ' sessions. ' +
                                      'Freed ' + (response.data.size_freed / 1024).toFixed(2) + ' KB.';
                        WPOM.showResult(message, 'success');

                        setTimeout(function() {
                            location.reload();
                        }, 2000);
                    } else {
                        WPOM.showResult('<strong>Error:</strong> ' + response.data.error, 'error');
                    }
                },
                error: function() {
                    WPOM.hideLoader();
                    WPOM.showResult('<strong>Error:</strong> Ajax request failed.', 'error');
                }
            });
        },

        disableAutoload: function(e) {
            e.preventDefault();

            if (!confirm('Are you sure you want to disable autoload for large options?')) {
                return;
            }

            const threshold = $('#wpom-autoload-threshold').val() || 100;
            WPOM.showLoader();

            $.ajax({
                url: wpomAjax.ajaxurl,
                type: 'POST',
                data: {
                    action: 'wpom_disable_autoload',
                    nonce: wpomAjax.nonce,
                    threshold: threshold
                },
                success: function(response) {
                    WPOM.hideLoader();

                    if (response.success) {
                        const message = '<strong>Success!</strong> Updated ' + response.data.updated + ' options.';
                        WPOM.showResult(message, 'success');

                        setTimeout(function() {
                            location.reload();
                        }, 2000);
                    } else {
                        WPOM.showResult('<strong>Error:</strong> ' + response.data.error, 'error');
                    }
                },
                error: function() {
                    WPOM.hideLoader();
                    WPOM.showResult('<strong>Error:</strong> Ajax request failed.', 'error');
                }
            });
        },

        previewPattern: function(e) {
            e.preventDefault();

            const pattern = $('#wpom-pattern-input').val().trim();

            if (pattern === '') {
                alert('Please enter a pattern.');
                return;
            }

            WPOM.showLoader();

            $.ajax({
                url: wpomAjax.ajaxurl,
                type: 'POST',
                data: {
                    action: 'wpom_preview_pattern',
                    nonce: wpomAjax.nonce,
                    pattern: pattern
                },
                success: function(response) {
                    WPOM.hideLoader();

                    if (response.success) {
                        const data = response.data;
                        let html = '<div class="wpom-preview-stats">';
                        html += '<p><strong>Total matches:</strong> ' + data.total_count + '</p>';
                        html += '<p><strong>Total size:</strong> ' + data.total_size_kb + ' KB</p>';

                        if (data.protected_count > 0) {
                            html += '<p class="wpom-text-warning"><strong>Protected options:</strong> ' + data.protected_count + ' (will not be deleted)</p>';
                        }

                        html += '</div>';

                        if (data.options.length > 0) {
                            html += '<table class="wpom-table widefat"><thead><tr>';
                            html += '<th>Option Name</th><th>Size (KB)</th><th>Autoload</th>';
                            html += '</tr></thead><tbody>';

                            data.options.forEach(function(option) {
                                html += '<tr>';
                                html += '<td>' + option.option_name + '</td>';
                                html += '<td>' + option.size_kb + '</td>';
                                html += '<td>' + option.autoload + '</td>';
                                html += '</tr>';
                            });

                            html += '</tbody></table>';

                            if (data.total_count > 100) {
                                html += '<p class="description">Showing first 100 results. Total: ' + data.total_count + '</p>';
                            }
                        } else {
                            html += '<p>No options found matching this pattern.</p>';
                        }

                        $('#wpom-pattern-preview-content').html(html);
                        $('#wpom-pattern-preview').fadeIn(200);
                        $('.wpom-btn-clean-pattern').prop('disabled', data.total_count === 0);
                    } else {
                        WPOM.showResult('<strong>Error:</strong> ' + response.data.message, 'error');
                    }
                },
                error: function() {
                    WPOM.hideLoader();
                    WPOM.showResult('<strong>Error:</strong> Ajax request failed.', 'error');
                }
            });
        },

        cleanByPattern: function(e) {
            e.preventDefault();

            const pattern = $('#wpom-pattern-input').val().trim();

            if (pattern === '') {
                alert('Please enter a pattern.');
                return;
            }

            if (!confirm('Are you sure you want to delete all options matching this pattern? This action cannot be undone without restoring from backup.')) {
                return;
            }

            WPOM.showLoader();

            $.ajax({
                url: wpomAjax.ajaxurl,
                type: 'POST',
                data: {
                    action: 'wpom_clean_by_pattern',
                    nonce: wpomAjax.nonce,
                    pattern: pattern
                },
                success: function(response) {
                    WPOM.hideLoader();

                    if (response.success) {
                        const message = '<strong>Success!</strong> Deleted ' + response.data.deleted + ' options. ' +
                                      'Freed ' + (response.data.size_freed / 1024).toFixed(2) + ' KB.';
                        WPOM.showResult(message, 'success');

                        setTimeout(function() {
                            location.reload();
                        }, 2000);
                    } else {
                        WPOM.showResult('<strong>Error:</strong> ' + response.data.error, 'error');
                    }
                },
                error: function() {
                    WPOM.hideLoader();
                    WPOM.showResult('<strong>Error:</strong> Ajax request failed.', 'error');
                }
            });
        },

        refreshDiagnostic: function(e) {
            e.preventDefault();
            WPOM.showLoader();

            // Clear cache and reload
            location.reload();
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        WPOM.init();
    });

})(jQuery);
