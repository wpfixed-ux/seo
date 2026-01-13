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

            // Plugin cleaner actions
            $('.wpom-btn-analyze-plugin').on('click', this.analyzePlugin);
            $(document).on('click', '.wpom-btn-clean-plugin', this.cleanPlugin);

            // Diagnostic page actions - prefix viewer and deleter
            $('.wpom-btn-view-prefix-options').on('click', this.viewPrefixOptions);
            $('.wpom-btn-delete-prefix').on('click', this.deletePrefix);
            $('.wpom-btn-delete-single-option').on('click', this.deleteSingleOption);
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

        analyzePlugin: function(e) {
            e.preventDefault();

            const $button = $(this);
            const $pluginItem = $button.closest('.wpom-plugin-item');
            const pluginSlug = $pluginItem.data('plugin-slug');
            const $infoContainer = $pluginItem.find('.wpom-plugin-info');

            // Show loading state
            $button.prop('disabled', true).text('Analyzing...');

            $.ajax({
                url: wpomAjax.ajaxurl,
                type: 'POST',
                data: {
                    action: 'wpom_analyze_plugin',
                    nonce: wpomAjax.nonce,
                    plugin_slug: pluginSlug
                },
                success: function(response) {
                    $button.prop('disabled', false).text('Analyze');

                    if (response.success) {
                        const data = response.data;

                        // Build info HTML
                        let html = '<div class="wpom-plugin-status ' + (data.is_active ? 'active' : 'inactive') + '">';
                        html += '<strong>Status:</strong> ' + (data.is_active ? 'Active (Cannot clean)' : 'Inactive (Safe to clean)');
                        html += '</div>';

                        html += '<div class="wpom-plugin-stats">';
                        html += '<div class="wpom-plugin-stat">';
                        html += '<div class="wpom-plugin-stat-label">Options Found</div>';
                        html += '<div class="wpom-plugin-stat-value">' + data.count + '</div>';
                        html += '</div>';

                        html += '<div class="wpom-plugin-stat">';
                        html += '<div class="wpom-plugin-stat-label">Total Size</div>';
                        html += '<div class="wpom-plugin-stat-value' + (data.size_kb > 100 ? ' large' : '') + '">' + data.size_kb + ' KB</div>';
                        html += '</div>';

                        html += '<div class="wpom-plugin-stat">';
                        html += '<div class="wpom-plugin-stat-label">Autoload Size</div>';
                        html += '<div class="wpom-plugin-stat-value">' + data.autoload_size_kb + ' KB</div>';
                        html += '</div>';
                        html += '</div>';

                        // Show options list if exists
                        if (data.options && data.options.length > 0) {
                            html += '<div class="wpom-plugin-options-list">';
                            html += '<h4>Options List:</h4>';
                            html += '<table><thead><tr>';
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

                            if (data.count > 20) {
                                html += '<p class="description">Showing first 20 options. Total: ' + data.count + '</p>';
                            }

                            html += '</div>';
                        }

                        // Add clean button if plugin is inactive and has options
                        if (!data.is_active && data.count > 0) {
                            html += '<div class="wpom-plugin-clean-action">';
                            html += '<button type="button" class="button button-primary wpom-btn-clean-plugin" data-plugin-slug="' + pluginSlug + '" data-plugin-name="' + data.plugin_name + '">';
                            html += '<span class="dashicons dashicons-trash"></span> Clean All ' + data.plugin_name + ' Options';
                            html += '</button>';
                            html += '<p class="description">This will delete all ' + data.count + ' options and free ' + data.size_kb + ' KB. A backup will be created automatically.</p>';
                            html += '</div>';
                        }

                        $infoContainer.html(html).slideDown(300);
                    } else {
                        WPOM.showResult('<strong>Error:</strong> ' + response.data.message, 'error');
                    }
                },
                error: function() {
                    $button.prop('disabled', false).text('Analyze');
                    WPOM.showResult('<strong>Error:</strong> Ajax request failed.', 'error');
                }
            });
        },

        cleanPlugin: function(e) {
            e.preventDefault();

            const $button = $(this);
            const pluginSlug = $button.data('plugin-slug');
            const pluginName = $button.data('plugin-name');

            if (!confirm('Are you sure you want to delete all options for ' + pluginName + '?\n\nThis action will:\n- Delete all plugin options from wp_options table\n- Create an automatic backup\n- Free up database space\n\nYou can restore from backup if needed.')) {
                return;
            }

            // Show loading state
            $button.prop('disabled', true).text('Cleaning...');
            WPOM.showLoader();

            $.ajax({
                url: wpomAjax.ajaxurl,
                type: 'POST',
                data: {
                    action: 'wpom_clean_plugin',
                    nonce: wpomAjax.nonce,
                    plugin_slug: pluginSlug
                },
                success: function(response) {
                    WPOM.hideLoader();
                    $button.prop('disabled', false).text('Clean Options');

                    if (response.success) {
                        const message = '<strong>Success!</strong> Deleted ' + response.data.deleted + ' options for ' + pluginName + '. ' +
                                      'Freed ' + response.data.size_freed_kb + ' KB. ' +
                                      'Backup ID: #' + response.data.backup_id;
                        WPOM.showResult(message, 'success');

                        // Reload page after 3 seconds
                        setTimeout(function() {
                            location.reload();
                        }, 3000);
                    } else {
                        WPOM.showResult('<strong>Error:</strong> ' + response.data.message, 'error');
                    }
                },
                error: function() {
                    WPOM.hideLoader();
                    $button.prop('disabled', false).text('Clean Options');
                    WPOM.showResult('<strong>Error:</strong> Ajax request failed.', 'error');
                }
            });
        },

        viewPrefixOptions: function(e) {
            e.preventDefault();

            const $button = $(this);
            const prefix = $button.data('prefix');
            const $detailsRow = $('#wpom-prefix-details-' + prefix);
            const $contentDiv = $detailsRow.find('.wpom-prefix-options-list');
            const $loader = $detailsRow.find('.wpom-loader-small');

            // Toggle visibility
            if ($detailsRow.is(':visible')) {
                $detailsRow.slideUp(200);
                $button.html('<span class="dashicons dashicons-visibility"></span> View');
                return;
            }

            // Show row and loader
            $detailsRow.show();
            $loader.show();
            $button.html('<span class="dashicons dashicons-hidden"></span> Hide');

            // If already loaded, just show
            if ($contentDiv.html() !== '') {
                $loader.hide();
                return;
            }

            // Load data via AJAX
            $.ajax({
                url: wpomAjax.ajaxurl,
                type: 'POST',
                data: {
                    action: 'wpom_view_prefix_options',
                    nonce: wpomAjax.nonce,
                    prefix: prefix
                },
                success: function(response) {
                    $loader.hide();

                    if (response.success && response.data.options.length > 0) {
                        let html = '<table class="widefat"><thead><tr>';
                        html += '<th>Option Name</th><th>Size (KB)</th><th>Autoload</th>';
                        html += '</tr></thead><tbody>';

                        response.data.options.forEach(function(option) {
                            html += '<tr>';
                            html += '<td><code>' + option.option_name + '</code></td>';
                            html += '<td>' + option.size_kb + '</td>';
                            html += '<td>' + option.autoload + '</td>';
                            html += '</tr>';
                        });

                        html += '</tbody></table>';

                        if (response.data.count >= 100) {
                            html += '<p class="description">Showing first 100 options.</p>';
                        }

                        $contentDiv.html(html);
                    } else {
                        $contentDiv.html('<p>No options found or error loading data.</p>');
                    }
                },
                error: function() {
                    $loader.hide();
                    $contentDiv.html('<p class="wpom-text-error">Error loading options.</p>');
                }
            });
        },

        deletePrefix: function(e) {
            e.preventDefault();

            const $button = $(this);
            const prefix = $button.data('prefix');
            const label = $button.data('label');
            const count = $button.data('count');

            if (!confirm('Are you sure you want to delete ALL ' + count + ' options for "' + label + '" (' + prefix + '*)?\n\nThis action will:\n- Delete all matching options from wp_options table\n- Create an automatic backup\n- Free up database space\n\nProtected WordPress core options will NOT be deleted.\n\nYou can restore from backup if needed.')) {
                return;
            }

            // Show loading state
            $button.prop('disabled', true).text('Deleting...');
            WPOM.showLoader();

            $.ajax({
                url: wpomAjax.ajaxurl,
                type: 'POST',
                data: {
                    action: 'wpom_delete_prefix',
                    nonce: wpomAjax.nonce,
                    prefix: prefix
                },
                success: function(response) {
                    WPOM.hideLoader();
                    $button.prop('disabled', false).html('<span class="dashicons dashicons-trash"></span> Delete All');

                    if (response.success) {
                        const message = '<strong>Success!</strong> Deleted ' + response.data.deleted + ' options (' + label + '). ' +
                                      'Freed ' + response.data.size_freed_kb + ' KB. ' +
                                      (response.data.protected > 0 ? response.data.protected + ' protected options were skipped. ' : '') +
                                      'Backup ID: #' + response.data.backup_id;
                        WPOM.showResult(message, 'success');

                        // Reload page after 3 seconds
                        setTimeout(function() {
                            location.reload();
                        }, 3000);
                    } else {
                        WPOM.showResult('<strong>Error:</strong> ' + response.data.error, 'error');
                    }
                },
                error: function() {
                    WPOM.hideLoader();
                    $button.prop('disabled', false).html('<span class="dashicons dashicons-trash"></span> Delete All');
                    WPOM.showResult('<strong>Error:</strong> Ajax request failed.', 'error');
                }
            });
        },

        deleteSingleOption: function(e) {
            e.preventDefault();

            const $button = $(this);
            const optionName = $button.data('option-name');
            const size = $button.data('size');

            if (!confirm('Are you sure you want to delete option "' + optionName + '"?\n\nSize: ' + size + ' KB\n\nThis action will:\n- Delete this option from wp_options table\n- Create an automatic backup\n\nYou can restore from backup if needed.')) {
                return;
            }

            // Show loading state
            $button.prop('disabled', true).text('Deleting...');
            WPOM.showLoader();

            $.ajax({
                url: wpomAjax.ajaxurl,
                type: 'POST',
                data: {
                    action: 'wpom_delete_single_option',
                    nonce: wpomAjax.nonce,
                    option_name: optionName
                },
                success: function(response) {
                    WPOM.hideLoader();

                    if (response.success) {
                        const message = '<strong>Success!</strong> Deleted option "' + optionName + '". ' +
                                      'Freed ' + response.data.size_freed_kb + ' KB. ' +
                                      'Backup ID: #' + response.data.backup_id;
                        WPOM.showResult(message, 'success');

                        // Remove the row from table
                        $button.closest('tr').fadeOut(300, function() {
                            $(this).remove();
                        });
                    } else {
                        $button.prop('disabled', false).html('<span class="dashicons dashicons-trash"></span> Delete');
                        WPOM.showResult('<strong>Error:</strong> ' + response.data.error, 'error');
                    }
                },
                error: function() {
                    WPOM.hideLoader();
                    $button.prop('disabled', false).html('<span class="dashicons dashicons-trash"></span> Delete');
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
