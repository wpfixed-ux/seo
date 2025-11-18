<?php
if (!defined('ABSPATH')) exit;
$all_campaigns = $campaigns->get_all();
?>
<div class="wrap">
    <h1 class="wp-heading-inline"><?php esc_html_e('Email Campaigns', 'wc-product-manager-pro'); ?></h1>
    <a href="#" class="page-title-action wcpmp-add-campaign"><?php esc_html_e('Add Campaign', 'wc-product-manager-pro'); ?></a>
    <hr class="wp-header-end">

    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th><?php esc_html_e('Campaign', 'wc-product-manager-pro'); ?></th>
                <th><?php esc_html_e('Segment', 'wc-product-manager-pro'); ?></th>
                <th><?php esc_html_e('Status', 'wc-product-manager-pro'); ?></th>
                <th><?php esc_html_e('Sent', 'wc-product-manager-pro'); ?></th>
                <th><?php esc_html_e('Opens', 'wc-product-manager-pro'); ?></th>
                <th><?php esc_html_e('Clicks', 'wc-product-manager-pro'); ?></th>
                <th><?php esc_html_e('Created', 'wc-product-manager-pro'); ?></th>
                <th><?php esc_html_e('Actions', 'wc-product-manager-pro'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($all_campaigns)) : ?>
                <?php foreach ($all_campaigns as $campaign) : ?>
                    <tr>
                        <td>
                            <strong><?php echo esc_html($campaign->name); ?></strong>
                            <?php if ($campaign->ai_generated) : ?>
                                <span class="wcpmp-ai-badge">AI</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo esc_html($campaign->segment_name ?? __('All Customers', 'wc-product-manager-pro')); ?></td>
                        <td><span class="wcpmp-status wcpmp-status-<?php echo esc_attr($campaign->status); ?>"><?php echo esc_html($campaign->status); ?></span></td>
                        <td><?php echo esc_html($campaign->sent_count); ?></td>
                        <td><?php echo esc_html($campaign->open_count); ?></td>
                        <td><?php echo esc_html($campaign->click_count); ?></td>
                        <td><?php echo esc_html(date_i18n(get_option('date_format'), strtotime($campaign->created_at))); ?></td>
                        <td>
                            <?php if ($campaign->status === 'draft') : ?>
                                <a href="#" class="wcpmp-send-campaign" data-id="<?php echo esc_attr($campaign->id); ?>"><?php esc_html_e('Send', 'wc-product-manager-pro'); ?></a> |
                            <?php endif; ?>
                            <a href="#" class="wcpmp-edit-campaign" data-id="<?php echo esc_attr($campaign->id); ?>"><?php esc_html_e('Edit', 'wc-product-manager-pro'); ?></a> |
                            <a href="#" class="wcpmp-delete-campaign" data-id="<?php echo esc_attr($campaign->id); ?>"><?php esc_html_e('Delete', 'wc-product-manager-pro'); ?></a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else : ?>
                <tr>
                    <td colspan="8"><?php esc_html_e('No campaigns created.', 'wc-product-manager-pro'); ?></td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
