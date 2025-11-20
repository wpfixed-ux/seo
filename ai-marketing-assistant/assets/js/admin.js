/**
 * AI Marketing Assistant Admin Scripts
 */

(function($) {
    'use strict';

    // Document ready
    $(document).ready(function() {
        initSegmentBuilder();
        initOfferGenerator();
        initCSVImport();
        initCampaignSender();
        initAISettings();
    });

    /**
     * Initialize segment builder
     */
    function initSegmentBuilder() {
        $('#aima-add-condition').on('click', function() {
            const conditionRow = `
                <div class="aima-condition-row">
                    <select name="segment_conditions[field][]">
                        <option value="total_spent">Total Spent</option>
                        <option value="total_orders">Total Orders</option>
                        <option value="last_order_date">Last Order Date</option>
                        <option value="created_at">Registration Date</option>
                        <option value="purchased_category">Purchased Category</option>
                        <option value="not_purchased_category">Not Purchased Category</option>
                    </select>
                    <select name="segment_conditions[operator][]">
                        <option value=">">Greater than</option>
                        <option value="<">Less than</option>
                        <option value="=">Equals</option>
                        <option value=">=">Greater or equal</option>
                        <option value="<=">Less or equal</option>
                    </select>
                    <input type="text" name="segment_conditions[value][]" placeholder="Value">
                    <button type="button" class="aima-btn-remove">Remove</button>
                </div>
            `;
            $('#aima-conditions-container').append(conditionRow);
        });

        $(document).on('click', '.aima-btn-remove', function() {
            $(this).closest('.aima-condition-row').remove();
        });

        $('#aima-update-segment').on('click', function() {
            const segmentId = $(this).data('segment-id');
            updateSegment(segmentId);
        });
    }

    /**
     * Initialize offer generator
     */
    function initOfferGenerator() {
        $('#aima-generate-offer').on('click', function() {
            const button = $(this);
            const segmentId = $('#offer_segment_id').val();
            const productIds = [];

            $('.aima-product-card.selected').each(function() {
                productIds.push($(this).data('product-id'));
            });

            if (!segmentId) {
                alert(aimaAjax.strings.error);
                return;
            }

            button.prop('disabled', true).text(aimaAjax.strings.processing);

            $.ajax({
                url: aimaAjax.ajaxurl,
                method: 'POST',
                data: {
                    action: 'aima_generate_offer',
                    nonce: aimaAjax.nonce,
                    segment_id: segmentId,
                    product_ids: productIds
                },
                success: function(response) {
                    if (response.success) {
                        populateOfferForm(response.data.offer);
                        showNotification('Offer generated successfully!', 'success');
                    } else {
                        showNotification(response.data.message || aimaAjax.strings.error, 'error');
                    }
                },
                error: function() {
                    showNotification(aimaAjax.strings.error, 'error');
                },
                complete: function() {
                    button.prop('disabled', false).text('Generate with AI');
                }
            });
        });

        $(document).on('click', '.aima-product-card', function() {
            $(this).toggleClass('selected');
        });
    }

    /**
     * Initialize CSV import
     */
    function initCSVImport() {
        $('#aima-csv-upload-form').on('submit', function(e) {
            e.preventDefault();

            const formData = new FormData(this);
            formData.append('action', 'aima_import_csv');
            formData.append('nonce', aimaAjax.nonce);

            const progressBar = $('.aima-progress-bar');
            const progressFill = $('.aima-progress-fill');

            progressBar.show();
            progressFill.css('width', '0%').text('0%');

            $.ajax({
                url: aimaAjax.ajaxurl,
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                xhr: function() {
                    const xhr = new window.XMLHttpRequest();
                    xhr.upload.addEventListener('progress', function(e) {
                        if (e.lengthComputable) {
                            const percentComplete = Math.round((e.loaded / e.total) * 100);
                            progressFill.css('width', percentComplete + '%').text(percentComplete + '%');
                        }
                    }, false);
                    return xhr;
                },
                success: function(response) {
                    if (response.success) {
                        showNotification(response.data.message, 'success');
                        progressFill.css('width', '100%').text('100%');
                        setTimeout(function() {
                            location.reload();
                        }, 2000);
                    } else {
                        showNotification(response.data.message || aimaAjax.strings.error, 'error');
                    }
                },
                error: function() {
                    showNotification(aimaAjax.strings.error, 'error');
                }
            });
        });
    }

    /**
     * Initialize campaign sender
     */
    function initCampaignSender() {
        $('.aima-send-campaign').on('click', function() {
            if (!confirm('Are you sure you want to send this campaign?')) {
                return;
            }

            const button = $(this);
            const offerId = button.data('offer-id');
            const sendMethod = button.data('method') || 'email';

            button.prop('disabled', true).html('<span class="aima-loading"></span> Sending...');

            $.ajax({
                url: aimaAjax.ajaxurl,
                method: 'POST',
                data: {
                    action: 'aima_send_campaign',
                    nonce: aimaAjax.nonce,
                    offer_id: offerId,
                    send_method: sendMethod
                },
                success: function(response) {
                    if (response.success) {
                        showNotification('Campaign sent successfully! Sent to ' + response.data.sent_count + ' recipients.', 'success');
                        setTimeout(function() {
                            location.reload();
                        }, 2000);
                    } else {
                        showNotification(response.data.message || aimaAjax.strings.error, 'error');
                    }
                },
                error: function() {
                    showNotification(aimaAjax.strings.error, 'error');
                },
                complete: function() {
                    button.prop('disabled', false).text('Send Campaign');
                }
            });
        });
    }

    /**
     * Update segment
     */
    function updateSegment(segmentId) {
        $.ajax({
            url: aimaAjax.ajaxurl,
            method: 'POST',
            data: {
                action: 'aima_update_segment',
                nonce: aimaAjax.nonce,
                segment_id: segmentId
            },
            success: function(response) {
                if (response.success) {
                    showNotification('Segment updated! ' + response.data.customer_count + ' customers found.', 'success');
                    setTimeout(function() {
                        location.reload();
                    }, 1500);
                } else {
                    showNotification(response.data.message || aimaAjax.strings.error, 'error');
                }
            },
            error: function() {
                showNotification(aimaAjax.strings.error, 'error');
            }
        });
    }

    /**
     * Populate offer form with AI generated data
     */
    function populateOfferForm(offer) {
        $('#offer_name').val(offer.headline);
        $('#offer_subject').val(offer.subheadline);
        $('#offer_content').val(offer.body);
        $('#offer_coupon_code').val(offer.coupon_code);
        $('#offer_discount_value').val(offer.discount_value);
        $('#offer_cta_text').val(offer.cta_text);
        $('#offer_banner_concept').val(offer.banner_concept);
    }

    /**
     * Show notification
     */
    function showNotification(message, type) {
        const notification = $('<div class="aima-notification ' + type + '">' + message + '</div>');
        $('.wrap').prepend(notification);

        setTimeout(function() {
            notification.fadeOut(function() {
                $(this).remove();
            });
        }, 5000);
    }

    /**
     * Initialize AI settings handlers
     */
    function initAISettings() {
        // Change AI provider - load models
        $('#aima_ai_provider').on('change', function() {
            const provider = $(this).val();
            loadProviderModels(provider);
        });

        // Test API connection
        $('#aima_test_api').on('click', function() {
            const button = $(this);
            const provider = $('#aima_ai_provider').val();
            const model = $('#aima_ai_model').val();
            const apiKey = $('#aima_ai_api_key').val();

            if (!apiKey) {
                showNotification('Please enter API key first', 'error');
                return;
            }

            button.prop('disabled', true).html('<span class="aima-loading"></span> Testing...');
            $('#aima_test_result').html('');

            $.ajax({
                url: aimaAjax.ajaxurl,
                method: 'POST',
                data: {
                    action: 'aima_test_api',
                    nonce: aimaAjax.nonce,
                    provider: provider,
                    model: model,
                    api_key: apiKey
                },
                success: function(response) {
                    if (response.success) {
                        const result = response.data;
                        $('#aima_test_result').html(
                            '<div class="aima-notification success">' +
                            '✅ ' + result.message + '<br>' +
                            '<strong>Provider:</strong> ' + result.provider + '<br>' +
                            '<strong>Model:</strong> ' + result.model + '<br>' +
                            '<strong>Response:</strong> ' + result.response +
                            '</div>'
                        );
                    } else {
                        $('#aima_test_result').html(
                            '<div class="aima-notification error">' +
                            '❌ ' + (response.data.message || 'Connection failed') +
                            '</div>'
                        );
                    }
                },
                error: function() {
                    $('#aima_test_result').html(
                        '<div class="aima-notification error">❌ Network error</div>'
                    );
                },
                complete: function() {
                    button.prop('disabled', false).text('Test Connection');
                }
            });
        });
    }

    /**
     * Load models for selected provider
     */
    function loadProviderModels(provider) {
        const modelsData = $('#aima_provider_models').data('providers');

        if (!modelsData || !modelsData[provider]) {
            return;
        }

        const models = modelsData[provider].models;
        const $modelSelect = $('#aima_ai_model');

        // Clear and repopulate model select
        $modelSelect.empty();

        $.each(models, function(modelKey, modelName) {
            $modelSelect.append(
                $('<option></option>')
                    .attr('value', modelKey)
                    .text(modelName)
            );
        });

        // Select first model by default
        $modelSelect.val(Object.keys(models)[0]);
    }

})(jQuery);
