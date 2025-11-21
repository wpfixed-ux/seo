<?php
/**
 * Import view
 *
 * @package AIMarketingAssistant
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1><?php _e('Import Customers & Orders', 'ai-marketing-assistant'); ?></h1>

    <?php settings_errors('aima_messages'); ?>

    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 20px;">

        <!-- WooCommerce Import -->
        <?php if ($wc_active): ?>
        <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
            <h2>🛒 <?php _e('Import from WooCommerce', 'ai-marketing-assistant'); ?></h2>
            <p><?php _e('Import existing orders and customers from your WooCommerce store.', 'ai-marketing-assistant'); ?></p>

            <?php if (!empty($wc_stats)): ?>
            <div style="background: #f0f6ff; padding: 15px; border-radius: 5px; margin: 15px 0;">
                <h3 style="margin-top: 0;"><?php _e('WooCommerce Statistics', 'ai-marketing-assistant'); ?></h3>
                <table style="width: 100%;">
                    <tr>
                        <td><?php _e('Total Orders:', 'ai-marketing-assistant'); ?></td>
                        <td><strong><?php echo number_format($wc_stats['total_orders']); ?></strong></td>
                    </tr>
                    <tr>
                        <td><?php _e('Already Imported:', 'ai-marketing-assistant'); ?></td>
                        <td><strong style="color: #52c41a;"><?php echo number_format($wc_stats['imported_orders']); ?></strong></td>
                    </tr>
                    <tr>
                        <td><?php _e('Pending Import:', 'ai-marketing-assistant'); ?></td>
                        <td><strong style="color: #faad14;"><?php echo number_format($wc_stats['pending_orders']); ?></strong></td>
                    </tr>
                    <tr>
                        <td colspan="2"><hr style="margin: 10px 0;"></td>
                    </tr>
                    <tr>
                        <td><?php _e('WooCommerce Customers:', 'ai-marketing-assistant'); ?></td>
                        <td><strong><?php echo number_format($wc_stats['wc_customers']); ?></strong></td>
                    </tr>
                    <tr>
                        <td><?php _e('In AI Marketing:', 'ai-marketing-assistant'); ?></td>
                        <td><strong style="color: #52c41a;"><?php echo number_format($wc_stats['imported_customers']); ?></strong></td>
                    </tr>
                </table>
            </div>
            <?php endif; ?>

            <form method="post" action="">
                <?php wp_nonce_field('aima_import_woocommerce'); ?>

                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="order_status"><?php _e('Order Status', 'ai-marketing-assistant'); ?></label>
                        </th>
                        <td>
                            <select name="order_status" id="order_status" class="regular-text">
                                <option value="completed"><?php _e('Completed', 'ai-marketing-assistant'); ?></option>
                                <option value="processing"><?php _e('Processing', 'ai-marketing-assistant'); ?></option>
                                <option value="any"><?php _e('Any Status', 'ai-marketing-assistant'); ?></option>
                            </select>
                            <p class="description"><?php _e('Select which order statuses to import', 'ai-marketing-assistant'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="days_back"><?php _e('Time Period', 'ai-marketing-assistant'); ?></label>
                        </th>
                        <td>
                            <select name="days_back" id="days_back" class="regular-text">
                                <option value="30"><?php _e('Last 30 days', 'ai-marketing-assistant'); ?></option>
                                <option value="90" selected><?php _e('Last 90 days', 'ai-marketing-assistant'); ?></option>
                                <option value="180"><?php _e('Last 6 months', 'ai-marketing-assistant'); ?></option>
                                <option value="365"><?php _e('Last year', 'ai-marketing-assistant'); ?></option>
                                <option value="0"><?php _e('All time', 'ai-marketing-assistant'); ?></option>
                            </select>
                            <p class="description"><?php _e('Import orders from this time period', 'ai-marketing-assistant'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="limit"><?php _e('Limit', 'ai-marketing-assistant'); ?></label>
                        </th>
                        <td>
                            <input type="number" name="limit" id="limit" value="500" min="1" max="10000" class="regular-text" />
                            <p class="description"><?php _e('Maximum number of orders to import (recommended: 500)', 'ai-marketing-assistant'); ?></p>
                        </td>
                    </tr>
                </table>

                <p class="submit">
                    <input type="submit" name="import_woocommerce" class="button button-primary button-large" value="<?php esc_attr_e('Import from WooCommerce', 'ai-marketing-assistant'); ?>" />
                </p>
            </form>
        </div>
        <?php else: ?>
        <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
            <h2>🛒 <?php _e('Import from WooCommerce', 'ai-marketing-assistant'); ?></h2>
            <div style="background: #fff7e6; border-left: 4px solid #faad14; padding: 15px; margin: 15px 0;">
                <p><strong><?php _e('WooCommerce is not installed or activated', 'ai-marketing-assistant'); ?></strong></p>
                <p><?php _e('Install and activate WooCommerce to import orders and customers automatically.', 'ai-marketing-assistant'); ?></p>
            </div>
        </div>
        <?php endif; ?>

        <!-- CSV Import -->
        <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
            <h2>📄 <?php _e('Import from CSV File', 'ai-marketing-assistant'); ?></h2>
            <p><?php _e('Upload a CSV file with customer and order data.', 'ai-marketing-assistant'); ?></p>

            <div style="background: #f0f6ff; padding: 15px; border-radius: 5px; margin: 15px 0;">
                <h3 style="margin-top: 0;"><?php _e('CSV Format Requirements', 'ai-marketing-assistant'); ?></h3>
                <p><?php _e('Your CSV file should include these columns:', 'ai-marketing-assistant'); ?></p>
                <ul style="margin: 10px 0;">
                    <li><strong>email</strong> <?php _e('(required)', 'ai-marketing-assistant'); ?></li>
                    <li><strong>first_name</strong> <?php _e('(optional)', 'ai-marketing-assistant'); ?></li>
                    <li><strong>last_name</strong> <?php _e('(optional)', 'ai-marketing-assistant'); ?></li>
                    <li><strong>phone</strong> <?php _e('(optional)', 'ai-marketing-assistant'); ?></li>
                    <li><strong>products</strong> <?php _e('(optional)', 'ai-marketing-assistant'); ?></li>
                    <li><strong>order_date</strong> <?php _e('(optional)', 'ai-marketing-assistant'); ?></li>
                    <li><strong>amount</strong> <?php _e('(optional)', 'ai-marketing-assistant'); ?></li>
                </ul>
            </div>

            <form method="post" action="" enctype="multipart/form-data">
                <?php wp_nonce_field('aima_import_csv'); ?>

                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="csv_file"><?php _e('CSV File', 'ai-marketing-assistant'); ?></label>
                        </th>
                        <td>
                            <input type="file" name="csv_file" id="csv_file" accept=".csv" required />
                            <p class="description"><?php _e('Upload a CSV file (max 5MB)', 'ai-marketing-assistant'); ?></p>
                        </td>
                    </tr>
                </table>

                <p class="submit">
                    <input type="submit" name="upload_csv" class="button button-primary button-large" value="<?php esc_attr_e('Upload and Import CSV', 'ai-marketing-assistant'); ?>" />
                </p>
            </form>
        </div>
    </div>

    <!-- Recent Imports -->
    <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-top: 20px;">
        <h2><?php _e('Recent Imports', 'ai-marketing-assistant'); ?></h2>

        <?php if (!empty($recent_imports)): ?>
        <table class="widefat" style="margin-top: 15px;">
            <thead>
                <tr>
                    <th><?php _e('File / Source', 'ai-marketing-assistant'); ?></th>
                    <th><?php _e('Total Rows', 'ai-marketing-assistant'); ?></th>
                    <th><?php _e('Successful', 'ai-marketing-assistant'); ?></th>
                    <th><?php _e('Failed', 'ai-marketing-assistant'); ?></th>
                    <th><?php _e('Status', 'ai-marketing-assistant'); ?></th>
                    <th><?php _e('Date', 'ai-marketing-assistant'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recent_imports as $import): ?>
                    <tr>
                        <td><strong><?php echo esc_html($import->file_name); ?></strong></td>
                        <td><?php echo number_format($import->total_rows); ?></td>
                        <td style="color: #52c41a;"><strong><?php echo number_format($import->successful_rows); ?></strong></td>
                        <td style="color: #f5222d;"><?php echo number_format($import->failed_rows); ?></td>
                        <td>
                            <?php if ($import->status === 'completed'): ?>
                                <span style="color: #52c41a;">✓ <?php _e('Completed', 'ai-marketing-assistant'); ?></span>
                            <?php elseif ($import->status === 'processing'): ?>
                                <span style="color: #1890ff;">⟳ <?php _e('Processing', 'ai-marketing-assistant'); ?></span>
                            <?php else: ?>
                                <span style="color: #f5222d;">✗ <?php _e('Failed', 'ai-marketing-assistant'); ?></span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo date_i18n('d.m.Y H:i', strtotime($import->started_at)); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
            <p><?php _e('No imports yet', 'ai-marketing-assistant'); ?></p>
        <?php endif; ?>
    </div>
</div>
