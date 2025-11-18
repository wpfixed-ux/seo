<?php
if (!defined('ABSPATH')) exit;
$result = $orders->get_orders();
?>
<div class="wrap">
    <h1><?php esc_html_e('Orders', 'wc-product-manager-pro'); ?></h1>

    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th><?php esc_html_e('Order ID', 'wc-product-manager-pro'); ?></th>
                <th><?php esc_html_e('Store', 'wc-product-manager-pro'); ?></th>
                <th><?php esc_html_e('Customer', 'wc-product-manager-pro'); ?></th>
                <th><?php esc_html_e('Email', 'wc-product-manager-pro'); ?></th>
                <th><?php esc_html_e('Total', 'wc-product-manager-pro'); ?></th>
                <th><?php esc_html_e('Status', 'wc-product-manager-pro'); ?></th>
                <th><?php esc_html_e('Date', 'wc-product-manager-pro'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($result['orders'])) : ?>
                <?php foreach ($result['orders'] as $order) : ?>
                    <tr>
                        <td>#<?php echo esc_html($order->external_order_id); ?></td>
                        <td><?php echo esc_html($order->store_name); ?></td>
                        <td><?php echo esc_html($order->customer_name); ?></td>
                        <td><?php echo esc_html($order->customer_email); ?></td>
                        <td><?php echo number_format($order->total, 2); ?> <?php echo esc_html($order->currency); ?></td>
                        <td><span class="wcpmp-status wcpmp-status-<?php echo esc_attr($order->status); ?>"><?php echo esc_html($order->status); ?></span></td>
                        <td><?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($order->order_date))); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php else : ?>
                <tr>
                    <td colspan="7"><?php esc_html_e('No orders found.', 'wc-product-manager-pro'); ?></td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
