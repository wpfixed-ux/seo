/* SEO Analytics Pro - Admin JavaScript */
(function($) {
    'use strict';

    $(document).ready(function() {
        console.log('SEO Analytics Pro loaded');

        // Initialize tooltips
        if (typeof $.fn.tooltip !== 'undefined') {
            $('[data-toggle="tooltip"]').tooltip();
        }

        // Keyword analysis
        $('.sap-analyze-keyword').on('click', function(e) {
            e.preventDefault();
            var keywordId = $(this).data('keyword-id');
            analyzeKeyword(keywordId);
        });

        // Competitor analysis
        $('.sap-analyze-competitor').on('click', function(e) {
            e.preventDefault();
            var competitorId = $(this).data('competitor-id');
            analyzeCompetitor(competitorId);
        });

        // Import keywords
        $('#sap-import-keywords-form').on('submit', function(e) {
            e.preventDefault();
            importKeywords(new FormData(this));
        });

        // Metabox content generation (posts/pages/products)
        $(document).on('click', '.sap-generate-content', function(e) {
            e.preventDefault();
            var $button = $(this);
            var $metabox = $button.closest('.sap-metabox');
            var $spinner = $metabox.find('.spinner');
            var $result = $metabox.find('.sap-metabox-result');

            var postId = $button.data('post-id');
            var contentType = $button.data('content-type');

            // Gather form data
            var data = {
                action: 'sap_generate_metabox_content',
                nonce: sapData.nonce,
                post_id: postId,
                content_type: contentType,
                keywords: $metabox.find('[name="sap_keywords"]').val(),
                length: $metabox.find('[name="sap_length"]').val() || 2000,
                instructions: $metabox.find('[name="sap_instructions"]').val(),
                replace: $metabox.find('[name="sap_replace_content"]').is(':checked') ? 1 : 0,
                features: $metabox.find('[name="sap_features"]').val() || '',
                page_type: $metabox.find('[name="sap_page_type"]').val() || 'custom',
                generate_short: $metabox.find('[name="sap_generate_short"]').is(':checked') ? 1 : 0
            };

            // Show loading state
            $button.prop('disabled', true);
            $spinner.addClass('is-active');
            $result.hide().removeClass('success error');

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: data,
                success: function(response) {
                    if (response.success) {
                        $result.addClass('success').html(response.data.message).show();
                        // Reload page if content was replaced
                        if (data.replace) {
                            setTimeout(function() {
                                location.reload();
                            }, 1500);
                        }
                    } else {
                        $result.addClass('error').html(response.data.message).show();
                    }
                },
                error: function(xhr, status, error) {
                    $result.addClass('error').html('AJAX error: ' + error).show();
                },
                complete: function() {
                    $button.prop('disabled', false);
                    $spinner.removeClass('is-active');
                }
            });
        });

        // Term metabox content generation (categories)
        $(document).on('click', '.sap-generate-term-content', function(e) {
            e.preventDefault();
            var $button = $(this);
            var $row = $button.closest('td');
            var $spinner = $row.find('.spinner');
            var $result = $row.find('.sap-term-result');
            var $form = $button.closest('table.form-table');

            var termId = $button.data('term-id');
            var taxonomy = $button.data('taxonomy');

            var data = {
                action: 'sap_generate_term_content',
                nonce: sapData.nonce,
                term_id: termId,
                taxonomy: taxonomy,
                keywords: $form.find('[name="sap_keywords"]').val(),
                instructions: $form.find('[name="sap_instructions"]').val(),
                replace: $form.find('[name="sap_replace_description"]').is(':checked') ? 1 : 0
            };

            // Show loading state
            $button.prop('disabled', true);
            $spinner.addClass('is-active');
            $result.hide().removeClass('success error');

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: data,
                success: function(response) {
                    if (response.success) {
                        $result.css({
                            'background': '#d4edda',
                            'color': '#155724',
                            'padding': '10px',
                            'border-radius': '3px'
                        }).html(response.data.message).show();
                        // Reload page if description was replaced
                        if (data.replace) {
                            setTimeout(function() {
                                location.reload();
                            }, 1500);
                        }
                    } else {
                        $result.css({
                            'background': '#f8d7da',
                            'color': '#721c24',
                            'padding': '10px',
                            'border-radius': '3px'
                        }).html(response.data.message).show();
                    }
                },
                error: function(xhr, status, error) {
                    $result.css({
                        'background': '#f8d7da',
                        'color': '#721c24',
                        'padding': '10px',
                        'border-radius': '3px'
                    }).html('AJAX error: ' + error).show();
                },
                complete: function() {
                    $button.prop('disabled', false);
                    $spinner.removeClass('is-active');
                }
            });
        });

        // Quick article generation form
        $('#quick-article-form').on('submit', function(e) {
            e.preventDefault();
            var $form = $(this);
            var $button = $form.find('button[type="submit"]');

            var data = {
                action: 'sap_generate_metabox_content',
                nonce: sapData.nonce,
                post_id: 0, // Will create new post
                content_type: 'article',
                keywords: $form.find('[name="keywords"]').val(),
                length: $form.find('[name="length"]').val(),
                instructions: $form.find('[name="instructions"]').val(),
                title: $form.find('[name="title"]').val()
            };

            $button.prop('disabled', true).text(sapData.strings.analyzing);

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: data,
                success: function(response) {
                    if (response.success) {
                        alert(response.data.message);
                        if (response.data.post_id) {
                            window.location.href = 'post.php?post=' + response.data.post_id + '&action=edit';
                        }
                    } else {
                        alert('Error: ' + response.data.message);
                    }
                },
                error: function(xhr, status, error) {
                    alert('AJAX error: ' + error);
                },
                complete: function() {
                    $button.prop('disabled', false).text('Generate Article');
                }
            });
        });

        // Generate page form
        $('#generate-page-form').on('submit', function(e) {
            e.preventDefault();
            var $form = $(this);
            var $button = $form.find('button[type="submit"]');

            var data = {
                action: 'sap_generate_metabox_content',
                nonce: sapData.nonce,
                post_id: 0, // Will create new page
                content_type: 'page',
                keywords: $form.find('[name="keywords"]').val(),
                instructions: $form.find('[name="instructions"]').val(),
                title: $form.find('[name="title"]').val(),
                page_type: $form.find('[name="page_type"]').val()
            };

            $button.prop('disabled', true).text(sapData.strings.analyzing);

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: data,
                success: function(response) {
                    if (response.success) {
                        alert(response.data.message);
                        if (response.data.post_id) {
                            window.location.href = 'post.php?post=' + response.data.post_id + '&action=edit';
                        }
                    } else {
                        alert('Error: ' + response.data.message);
                    }
                },
                error: function(xhr, status, error) {
                    alert('AJAX error: ' + error);
                },
                complete: function() {
                    $button.prop('disabled', false).text('Generate Page');
                }
            });
        });
    });

    function analyzeKeyword(keywordId) {
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'sap_analyze_keyword',
                keyword_id: keywordId,
                nonce: sapData.nonce
            },
            beforeSend: function() {
                // Show loading state
            },
            success: function(response) {
                if (response.success) {
                    // Handle success
                    console.log('Keyword analyzed:', response.data);
                } else {
                    // Handle error
                    alert('Error: ' + response.data.message);
                }
            },
            error: function(xhr, status, error) {
                alert('AJAX error: ' + error);
            }
        });
    }

    function analyzeCompetitor(competitorId) {
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'sap_analyze_competitor',
                competitor_id: competitorId,
                nonce: sapData.nonce
            },
            success: function(response) {
                if (response.success) {
                    console.log('Competitor analyzed:', response.data);
                } else {
                    alert('Error: ' + response.data.message);
                }
            }
        });
    }

    function importKeywords(formData) {
        formData.append('action', 'sap_import_keywords');
        formData.append('nonce', sapData.nonce);

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    alert('Keywords imported successfully!');
                    location.reload();
                } else {
                    alert('Error: ' + response.data.message);
                }
            }
        });

        // Test API connection button
        $('#test-api-keys').on('click', function() {
            var $button = $(this);
            var $results = $('#api-test-results');

            $button.prop('disabled', true).text(sapData.strings.analyzing);
            $results.hide().html('');

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'sap_test_api_connection',
                    nonce: sapData.nonce
                },
                success: function(response) {
                    if (response.success) {
                        var html = '<div class="sap-test-results">';

                        // Claude AI result
                        var claude = response.data.claude;
                        html += '<div class="sap-test-result ' + (claude.status === 'success' ? 'success' : 'error') + '">';
                        html += '<strong>Claude AI:</strong> ';
                        if (claude.status === 'success') {
                            html += '<span class="dashicons dashicons-yes"></span> ' + claude.message;
                            if (claude.model) html += ' (Model: ' + claude.model + ')';
                        } else {
                            html += '<span class="dashicons dashicons-no"></span> ' + claude.message;
                            if (claude.code) html += ' (Code: ' + claude.code + ')';
                        }
                        if (claude.time) html += ' - ' + claude.time + 's';
                        html += '</div>';

                        // SERP API result
                        var serp = response.data.serp;
                        html += '<div class="sap-test-result ' + (serp.status === 'success' ? 'success' : 'error') + '">';
                        html += '<strong>SERP API:</strong> ';
                        if (serp.status === 'success') {
                            html += '<span class="dashicons dashicons-yes"></span> ' + serp.message;
                            if (serp.info) html += '<br><small>' + serp.info + '</small>';
                        } else {
                            html += '<span class="dashicons dashicons-no"></span> ' + serp.message;
                            if (serp.code) html += ' (Code: ' + serp.code + ')';
                        }
                        if (serp.time) html += ' - ' + serp.time + 's';
                        html += '</div>';

                        html += '</div>';
                        $results.html(html).show();
                    } else {
                        $results.html('<div class="sap-test-result error">' + response.data.message + '</div>').show();
                    }
                },
                error: function(xhr, status, error) {
                    $results.html('<div class="sap-test-result error">AJAX Error: ' + error + '</div>').show();
                },
                complete: function() {
                    $button.prop('disabled', false).text('Test API Keys');
                }
            });
        });

        // API Logs page functionality
        if ($('#logs-body').length) {
            var currentPage = 1;
            var logsData = [];

            function loadLogs(page) {
                page = page || 1;
                var service = $('#log-service-filter').val();
                var status = $('#log-status-filter').val();

                $('#logs-body').html('<tr><td colspan="7" class="sap-loading">Loading...</td></tr>');

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'sap_get_api_logs',
                        nonce: sapData.nonce,
                        page: page,
                        service: service,
                        status: status
                    },
                    success: function(response) {
                        if (response.success) {
                            logsData = response.data.logs;
                            renderLogs(response.data);
                            renderPagination(response.data);
                            updateStats(response.data.logs);
                            currentPage = page;
                        }
                    }
                });
            }

            function renderLogs(data) {
                var html = '';
                if (data.logs.length === 0) {
                    html = '<tr><td colspan="7" class="sap-empty-state">No logs found</td></tr>';
                } else {
                    $.each(data.logs, function(i, log) {
                        var statusClass = log.status_code == 200 ? 'status-success' : 'status-error';
                        html += '<tr>';
                        html += '<td>' + log.created_at + '</td>';
                        html += '<td>' + log.service_name + '</td>';
                        html += '<td>' + (log.endpoint.length > 50 ? log.endpoint.substr(0, 50) + '...' : log.endpoint) + '</td>';
                        html += '<td class="' + statusClass + '">' + log.status_code + '</td>';
                        html += '<td>' + parseFloat(log.execution_time).toFixed(2) + 's</td>';
                        html += '<td>' + (log.tokens_used || 0) + '</td>';
                        html += '<td><span class="sap-view-log dashicons dashicons-visibility" data-id="' + log.id + '"></span></td>';
                        html += '</tr>';
                    });
                }
                $('#logs-body').html(html);
            }

            function renderPagination(data) {
                var html = '';
                if (data.pages > 1) {
                    for (var i = 1; i <= data.pages; i++) {
                        var active = i === data.current_page ? 'button-primary' : '';
                        html += '<button class="button ' + active + ' page-btn" data-page="' + i + '">' + i + '</button>';
                    }
                }
                $('#logs-pagination').html(html);
            }

            function updateStats(logs) {
                var total = logs.length;
                var success = logs.filter(function(l) { return l.status_code == 200; }).length;
                var tokens = logs.reduce(function(sum, l) { return sum + parseInt(l.tokens_used || 0); }, 0);
                var cost = logs.reduce(function(sum, l) { return sum + parseFloat(l.cost || 0); }, 0);

                $('#stat-total').text(total);
                $('#stat-success-rate').text(total > 0 ? Math.round(success / total * 100) + '%' : '-');
                $('#stat-tokens').text(tokens.toLocaleString());
                $('#stat-cost').text('$' + cost.toFixed(4));
            }

            // Load initial logs
            loadLogs(1);

            // Filter button
            $('#filter-logs, #refresh-logs').on('click', function() {
                loadLogs(1);
            });

            // Pagination
            $(document).on('click', '.page-btn', function() {
                loadLogs($(this).data('page'));
            });

            // View log details
            $(document).on('click', '.sap-view-log', function() {
                var logId = $(this).data('id');
                var log = logsData.find(function(l) { return l.id == logId; });
                if (log) {
                    var request = JSON.stringify(JSON.parse(log.request_data || '{}'), null, 2);
                    var response = JSON.stringify(JSON.parse(log.response_data || '{}'), null, 2);
                    $('#log-request').text(request);
                    $('#log-response').text(response);
                    $('#log-details-modal').show();
                }
            });

            // Close modal
            $('.sap-modal-close, .sap-modal').on('click', function(e) {
                if (e.target === this) {
                    $('#log-details-modal').hide();
                }
            });

            // Clear logs
            $('#clear-old-logs').on('click', function() {
                if (confirm('Clear logs older than 30 days?')) {
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'sap_clear_api_logs',
                            nonce: sapData.nonce,
                            days: 30
                        },
                        success: function(response) {
                            if (response.success) {
                                alert(response.data.message);
                                loadLogs(1);
                            }
                        }
                    });
                }
            });

            $('#clear-all-logs').on('click', function() {
                if (confirm('Clear ALL logs? This cannot be undone.')) {
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'sap_clear_api_logs',
                            nonce: sapData.nonce,
                            days: 0
                        },
                        success: function(response) {
                            if (response.success) {
                                alert(response.data.message);
                                loadLogs(1);
                            }
                        }
                    });
                }
            });
        }
    });

    function analyzeKeyword(keywordId) {
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'sap_analyze_keyword',
                keyword_id: keywordId,
                nonce: sapData.nonce
            },
            beforeSend: function() {
                // Show loading state
            },
            success: function(response) {
                if (response.success) {
                    console.log('Keyword analyzed:', response.data);
                } else {
                    alert('Error: ' + response.data.message);
                }
            },
            error: function(xhr, status, error) {
                alert('AJAX error: ' + error);
            }
        });
    }

    function analyzeCompetitor(competitorId) {
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'sap_analyze_competitor',
                competitor_id: competitorId,
                nonce: sapData.nonce
            },
            success: function(response) {
                if (response.success) {
                    console.log('Competitor analyzed:', response.data);
                } else {
                    alert('Error: ' + response.data.message);
                }
            }
        });
    }

    function importKeywords(formData) {
        formData.append('action', 'sap_import_keywords');
        formData.append('nonce', sapData.nonce);

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    alert('Keywords imported successfully!');
                    location.reload();
                } else {
                    alert('Error: ' + response.data.message);
                }
            }
        });
    }

})(jQuery);
