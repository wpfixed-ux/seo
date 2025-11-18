<?php
if (!defined('ABSPATH')) exit;
$dashboard = $crm->get_dashboard();
?>
<div class="wrap">
    <h1><?php esc_html_e('CRM - Customer Management', 'wc-product-manager-pro'); ?></h1>

    <div class="wcpmp-crm-stats">
        <div class="wcpmp-stat-box">
            <span class="wcpmp-stat-number"><?php echo esc_html($dashboard['total_customers']); ?></span>
            <span class="wcpmp-stat-label"><?php esc_html_e('Total Customers', 'wc-product-manager-pro'); ?></span>
        </div>
        <div class="wcpmp-stat-box">
            <span class="wcpmp-stat-number"><?php echo esc_html($dashboard['subscribed']); ?></span>
            <span class="wcpmp-stat-label"><?php esc_html_e('Subscribed', 'wc-product-manager-pro'); ?></span>
        </div>
        <div class="wcpmp-stat-box">
            <span class="wcpmp-stat-number"><?php echo number_format($dashboard['total_revenue'], 0); ?></span>
            <span class="wcpmp-stat-label"><?php esc_html_e('Total Revenue (UAH)', 'wc-product-manager-pro'); ?></span>
        </div>
        <div class="wcpmp-stat-box">
            <span class="wcpmp-stat-number"><?php echo number_format($dashboard['avg_order_value'], 0); ?></span>
            <span class="wcpmp-stat-label"><?php esc_html_e('Avg Order (UAH)', 'wc-product-manager-pro'); ?></span>
        </div>
    </div>

    <h2><?php esc_html_e('Customer Segments', 'wc-product-manager-pro'); ?></h2>
    <a href="#" class="button button-primary wcpmp-add-segment"><?php esc_html_e('Add Segment', 'wc-product-manager-pro'); ?></a>

    <table class="wp-list-table widefat fixed striped" style="margin-top: 15px;">
        <thead>
            <tr>
                <th><?php esc_html_e('Segment', 'wc-product-manager-pro'); ?></th>
                <th><?php esc_html_e('Description', 'wc-product-manager-pro'); ?></th>
                <th><?php esc_html_e('Customers', 'wc-product-manager-pro'); ?></th>
                <th><?php esc_html_e('Type', 'wc-product-manager-pro'); ?></th>
                <th><?php esc_html_e('Actions', 'wc-product-manager-pro'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($dashboard['segments'])) : ?>
                <?php foreach ($dashboard['segments'] as $segment) : ?>
                    <tr>
                        <td><strong><?php echo esc_html($segment->name); ?></strong></td>
                        <td><?php echo esc_html($segment->description); ?></td>
                        <td><?php echo esc_html($segment->customer_count); ?></td>
                        <td><?php echo $segment->is_dynamic ? __('Dynamic', 'wc-product-manager-pro') : __('Static', 'wc-product-manager-pro'); ?></td>
                        <td>
                            <a href="?page=wcpmp-campaigns&segment=<?php echo esc_attr($segment->id); ?>"><?php esc_html_e('Campaign', 'wc-product-manager-pro'); ?></a> |
                            <a href="#" class="wcpmp-edit-segment" data-id="<?php echo esc_attr($segment->id); ?>"><?php esc_html_e('Edit', 'wc-product-manager-pro'); ?></a> |
                            <a href="#" class="wcpmp-delete-segment" data-id="<?php echo esc_attr($segment->id); ?>"><?php esc_html_e('Delete', 'wc-product-manager-pro'); ?></a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else : ?>
                <tr>
                    <td colspan="5"><?php esc_html_e('No segments created.', 'wc-product-manager-pro'); ?></td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
