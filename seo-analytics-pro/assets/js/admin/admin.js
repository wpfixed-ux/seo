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
