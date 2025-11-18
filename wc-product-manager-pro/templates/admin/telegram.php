<?php
if (!defined('ABSPATH')) exit;
$entries = $kb->get_all();
$webhook_info = $bot->get_webhook_info();
?>
<div class="wrap">
    <h1><?php esc_html_e('Telegram Bot', 'wc-product-manager-pro'); ?></h1>

    <div class="wcpmp-telegram-status">
        <h2><?php esc_html_e('Bot Status', 'wc-product-manager-pro'); ?></h2>
        <?php if (!empty($webhook_info['result']['url'])) : ?>
            <p class="wcpmp-status-active">
                <?php esc_html_e('Webhook active:', 'wc-product-manager-pro'); ?>
                <code><?php echo esc_html($webhook_info['result']['url']); ?></code>
            </p>
        <?php else : ?>
            <p class="wcpmp-status-inactive"><?php esc_html_e('Webhook not configured', 'wc-product-manager-pro'); ?></p>
            <button class="button button-primary wcpmp-set-webhook"><?php esc_html_e('Set Webhook', 'wc-product-manager-pro'); ?></button>
        <?php endif; ?>
    </div>

    <h2><?php esc_html_e('Knowledge Base', 'wc-product-manager-pro'); ?></h2>
    <p><?php esc_html_e('The bot uses this knowledge base to answer customer questions.', 'wc-product-manager-pro'); ?></p>

    <p>
        <a href="#" class="button wcpmp-add-knowledge"><?php esc_html_e('Add Entry', 'wc-product-manager-pro'); ?></a>
        <a href="#" class="button wcpmp-import-products"><?php esc_html_e('Import from Products', 'wc-product-manager-pro'); ?></a>
    </p>

    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th><?php esc_html_e('Title', 'wc-product-manager-pro'); ?></th>
                <th><?php esc_html_e('Category', 'wc-product-manager-pro'); ?></th>
                <th><?php esc_html_e('Language', 'wc-product-manager-pro'); ?></th>
                <th><?php esc_html_e('Actions', 'wc-product-manager-pro'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($entries)) : ?>
                <?php foreach ($entries as $entry) : ?>
                    <tr>
                        <td><?php echo esc_html($entry->title); ?></td>
                        <td><?php echo esc_html($entry->category ?: '-'); ?></td>
                        <td><?php echo esc_html(strtoupper($entry->language)); ?></td>
                        <td>
                            <a href="#" class="wcpmp-edit-knowledge" data-id="<?php echo esc_attr($entry->id); ?>"><?php esc_html_e('Edit', 'wc-product-manager-pro'); ?></a> |
                            <a href="#" class="wcpmp-delete-knowledge" data-id="<?php echo esc_attr($entry->id); ?>"><?php esc_html_e('Delete', 'wc-product-manager-pro'); ?></a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else : ?>
                <tr>
                    <td colspan="4"><?php esc_html_e('No knowledge base entries.', 'wc-product-manager-pro'); ?></td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
