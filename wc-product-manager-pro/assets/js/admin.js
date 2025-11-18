/**
 * WC Product Manager Pro - Admin JavaScript
 */
(function($) {
    'use strict';

    const WCPMP = {
        init: function() {
            this.bindEvents();
        },

        bindEvents: function() {
            // Sync buttons
            $(document).on('click', '.wcpmp-sync-all', this.syncAll);
            $(document).on('click', '.wcpmp-sync-store', this.syncStore);

            // Generate SEO
            $(document).on('click', '.wcpmp-generate-seo', this.generateSEO);

            // Store actions
            $(document).on('click', '.wcpmp-test-store', this.testStore);
            $(document).on('click', '.wcpmp-delete-store', this.deleteStore);

            // Campaign actions
            $(document).on('click', '.wcpmp-send-campaign', this.sendCampaign);

            // Telegram webhook
            $(document).on('click', '.wcpmp-set-webhook', this.setWebhook);

            // Import products to knowledge base
            $(document).on('click', '.wcpmp-import-products', this.importProducts);
        },

        syncAll: function(e) {
            e.preventDefault();
            const $btn = $(this);

            if (!confirm(wcpmp.strings.confirm_delete)) {
                return;
            }

            $btn.prop('disabled', true).text(wcpmp.strings.processing);

            $.ajax({
                url: wcpmp.ajax_url,
                type: 'POST',
                data: {
                    action: 'wcpmp_sync_inventory',
                    nonce: wcpmp.nonce
                },
                success: function(response) {
                    if (response.success) {
                        alert(response.data.message || wcpmp.strings.success);
                        location.reload();
                    } else {
                        alert(response.data || wcpmp.strings.error);
                    }
                },
                error: function() {
                    alert(wcpmp.strings.error);
                },
                complete: function() {
                    $btn.prop('disabled', false).text('Sync All Stores');
                }
            });
        },

        syncStore: function(e) {
            e.preventDefault();
            const storeId = $(this).data('id');

            $.ajax({
                url: wcpmp.ajax_url,
                type: 'POST',
                data: {
                    action: 'wcpmp_sync_inventory',
                    nonce: wcpmp.nonce,
                    store_id: storeId
                },
                success: function(response) {
                    if (response.success) {
                        alert(response.data.message || wcpmp.strings.success);
                        location.reload();
                    } else {
                        alert(response.data || wcpmp.strings.error);
                    }
                },
                error: function() {
                    alert(wcpmp.strings.error);
                }
            });
        },

        generateSEO: function(e) {
            e.preventDefault();
            const productId = $(this).data('id');
            const $link = $(this);

            $link.text(wcpmp.strings.processing);

            $.ajax({
                url: wcpmp.ajax_url,
                type: 'POST',
                data: {
                    action: 'wcpmp_generate_seo_description',
                    nonce: wcpmp.nonce,
                    product_id: productId,
                    language: 'uk'
                },
                success: function(response) {
                    if (response.success) {
                        alert(wcpmp.strings.success);
                        location.reload();
                    } else {
                        alert(response.data || wcpmp.strings.error);
                    }
                },
                error: function() {
                    alert(wcpmp.strings.error);
                },
                complete: function() {
                    $link.text('Generate SEO');
                }
            });
        },

        testStore: function(e) {
            e.preventDefault();
            const storeId = $(this).data('id');

            $.ajax({
                url: wcpmp.ajax_url,
                type: 'POST',
                data: {
                    action: 'wcpmp_test_store',
                    nonce: wcpmp.nonce,
                    store_id: storeId
                },
                success: function(response) {
                    if (response.success) {
                        alert('Connection successful!');
                    } else {
                        alert('Connection failed: ' + (response.data || 'Unknown error'));
                    }
                },
                error: function() {
                    alert(wcpmp.strings.error);
                }
            });
        },

        deleteStore: function(e) {
            e.preventDefault();

            if (!confirm(wcpmp.strings.confirm_delete)) {
                return;
            }

            const storeId = $(this).data('id');

            $.ajax({
                url: wcpmp.ajax_url,
                type: 'POST',
                data: {
                    action: 'wcpmp_delete_store',
                    nonce: wcpmp.nonce,
                    store_id: storeId
                },
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert(response.data || wcpmp.strings.error);
                    }
                },
                error: function() {
                    alert(wcpmp.strings.error);
                }
            });
        },

        sendCampaign: function(e) {
            e.preventDefault();

            if (!confirm('Are you sure you want to send this campaign?')) {
                return;
            }

            const campaignId = $(this).data('id');
            const $link = $(this);

            $link.text(wcpmp.strings.processing);

            $.ajax({
                url: wcpmp.ajax_url,
                type: 'POST',
                data: {
                    action: 'wcpmp_send_campaign',
                    nonce: wcpmp.nonce,
                    campaign_id: campaignId
                },
                success: function(response) {
                    if (response.success) {
                        alert('Campaign sent to ' + response.data.sent + ' recipients!');
                        location.reload();
                    } else {
                        alert(response.data || wcpmp.strings.error);
                    }
                },
                error: function() {
                    alert(wcpmp.strings.error);
                },
                complete: function() {
                    $link.text('Send');
                }
            });
        },

        setWebhook: function(e) {
            e.preventDefault();
            const $btn = $(this);

            $btn.prop('disabled', true).text(wcpmp.strings.processing);

            $.ajax({
                url: wcpmp.ajax_url,
                type: 'POST',
                data: {
                    action: 'wcpmp_set_telegram_webhook',
                    nonce: wcpmp.nonce
                },
                success: function(response) {
                    if (response.success) {
                        alert(wcpmp.strings.success);
                        location.reload();
                    } else {
                        alert(response.data || wcpmp.strings.error);
                    }
                },
                error: function() {
                    alert(wcpmp.strings.error);
                },
                complete: function() {
                    $btn.prop('disabled', false).text('Set Webhook');
                }
            });
        },

        importProducts: function(e) {
            e.preventDefault();
            const $btn = $(this);

            $btn.prop('disabled', true).text(wcpmp.strings.processing);

            $.ajax({
                url: wcpmp.ajax_url,
                type: 'POST',
                data: {
                    action: 'wcpmp_import_to_knowledge_base',
                    nonce: wcpmp.nonce
                },
                success: function(response) {
                    if (response.success) {
                        alert('Imported ' + response.data.imported + ' products!');
                        location.reload();
                    } else {
                        alert(response.data || wcpmp.strings.error);
                    }
                },
                error: function() {
                    alert(wcpmp.strings.error);
                },
                complete: function() {
                    $btn.prop('disabled', false).text('Import from Products');
                }
            });
        }
    };

    $(document).ready(function() {
        WCPMP.init();
    });

})(jQuery);
