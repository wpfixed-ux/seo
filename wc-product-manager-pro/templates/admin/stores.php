<?php
if (!defined('ABSPATH')) exit;
?>
<div class="wrap">
    <h1 class="wp-heading-inline"><?php esc_html_e('Stores', 'wc-product-manager-pro'); ?></h1>
    <a href="#" class="page-title-action wcpmp-add-store"><?php esc_html_e('Add Store', 'wc-product-manager-pro'); ?></a>
    <hr class="wp-header-end">

    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th><?php esc_html_e('Name', 'wc-product-manager-pro'); ?></th>
                <th><?php esc_html_e('Type', 'wc-product-manager-pro'); ?></th>
                <th><?php esc_html_e('API URL', 'wc-product-manager-pro'); ?></th>
                <th><?php esc_html_e('Status', 'wc-product-manager-pro'); ?></th>
                <th><?php esc_html_e('Last Sync', 'wc-product-manager-pro'); ?></th>
                <th><?php esc_html_e('Actions', 'wc-product-manager-pro'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($stores)) : ?>
                <?php foreach ($stores as $store) : ?>
                    <tr>
                        <td><strong><?php echo esc_html($store->name); ?></strong></td>
                        <td>
                            <?php
                            $type_labels = array(
                                'prom_ua' => 'Prom.ua',
                                'woocommerce' => 'WooCommerce'
                            );
                            echo esc_html($type_labels[$store->type] ?? $store->type);
                            ?>
                        </td>
                        <td><?php echo esc_html($store->api_url); ?></td>
                        <td>
                            <?php if ($store->is_active) : ?>
                                <span class="wcpmp-status-active"><?php esc_html_e('Active', 'wc-product-manager-pro'); ?></span>
                            <?php else : ?>
                                <span class="wcpmp-status-inactive"><?php esc_html_e('Inactive', 'wc-product-manager-pro'); ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php
                            if ($store->last_sync) {
                                echo esc_html(human_time_diff(strtotime($store->last_sync), current_time('timestamp'))) . ' ' . __('ago', 'wc-product-manager-pro');
                            } else {
                                esc_html_e('Never', 'wc-product-manager-pro');
                            }
                            ?>
                        </td>
                        <td>
                            <a href="#" class="wcpmp-edit-store" data-id="<?php echo esc_attr($store->id); ?>"><?php esc_html_e('Edit', 'wc-product-manager-pro'); ?></a> |
                            <a href="#" class="wcpmp-test-store" data-id="<?php echo esc_attr($store->id); ?>"><?php esc_html_e('Test', 'wc-product-manager-pro'); ?></a> |
                            <a href="#" class="wcpmp-sync-store" data-id="<?php echo esc_attr($store->id); ?>"><?php esc_html_e('Sync', 'wc-product-manager-pro'); ?></a> |
                            <a href="#" class="wcpmp-delete-store" data-id="<?php echo esc_attr($store->id); ?>"><?php esc_html_e('Delete', 'wc-product-manager-pro'); ?></a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else : ?>
                <tr>
                    <td colspan="6"><?php esc_html_e('No stores configured.', 'wc-product-manager-pro'); ?></td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
