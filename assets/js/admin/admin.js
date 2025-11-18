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
    }

})(jQuery);
