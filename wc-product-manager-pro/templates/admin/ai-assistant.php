<?php
if (!defined('ABSPATH')) exit;

$ai_commands = new WCPMP_AI_Commands();
$language = get_option('wcpmp_default_language', 'uk');
$examples = $ai_commands->get_example_commands($language);
?>
<div class="wrap">
    <h1><?php esc_html_e('AI Assistant', 'wc-product-manager-pro'); ?></h1>
    <p><?php esc_html_e('Use natural language to manage prices and stock. Type your command and AI will show you a preview before applying changes.', 'wc-product-manager-pro'); ?></p>

    <div class="wcpmp-ai-assistant">
        <div class="wcpmp-chat-container">
            <div class="wcpmp-chat-messages" id="wcpmp-chat-messages">
                <div class="wcpmp-message wcpmp-message-bot">
                    <div class="wcpmp-message-content">
                        <?php if ($language === 'uk') : ?>
                            Привіт! Я AI-асистент для управління цінами та наявністю товарів.
                            Напишіть команду природною мовою, наприклад:
                        <?php else : ?>
                            Привет! Я AI-ассистент для управления ценами и наличием товаров.
                            Напишите команду на естественном языке, например:
                        <?php endif; ?>
                        <ul>
                            <?php foreach (array_slice($examples, 0, 3) as $example) : ?>
                                <li><a href="#" class="wcpmp-example-command"><?php echo esc_html($example); ?></a></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            </div>

            <div class="wcpmp-chat-input">
                <form id="wcpmp-command-form">
                    <input type="text" id="wcpmp-command-input" placeholder="<?php echo $language === 'uk' ? 'Введіть команду...' : 'Введите команду...'; ?>" autocomplete="off">
                    <button type="submit" class="button button-primary">
                        <span class="dashicons dashicons-arrow-right-alt"></span>
                    </button>
                </form>
            </div>
        </div>

        <div class="wcpmp-examples-sidebar">
            <h3><?php esc_html_e('Example Commands', 'wc-product-manager-pro'); ?></h3>
            <ul>
                <?php foreach ($examples as $example) : ?>
                    <li><a href="#" class="wcpmp-example-command"><?php echo esc_html($example); ?></a></li>
                <?php endforeach; ?>
            </ul>

            <h3><?php esc_html_e('Tips', 'wc-product-manager-pro'); ?></h3>
            <ul class="wcpmp-tips">
                <li><?php esc_html_e('Use percentages: "on 10%", "by 15%"', 'wc-product-manager-pro'); ?></li>
                <li><?php esc_html_e('Use absolute values: "on 100 UAH", "by 50"', 'wc-product-manager-pro'); ?></li>
                <li><?php esc_html_e('Filter by category, price range, or SKU', 'wc-product-manager-pro'); ?></li>
                <li><?php esc_html_e('Always review the preview before confirming', 'wc-product-manager-pro'); ?></li>
            </ul>
        </div>
    </div>
</div>

<style>
.wcpmp-ai-assistant {
    display: grid;
    grid-template-columns: 1fr 300px;
    gap: 20px;
    margin-top: 20px;
}

.wcpmp-chat-container {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 8px;
    display: flex;
    flex-direction: column;
    height: 600px;
}

.wcpmp-chat-messages {
    flex: 1;
    overflow-y: auto;
    padding: 20px;
}

.wcpmp-message {
    margin-bottom: 15px;
    display: flex;
}

.wcpmp-message-user {
    justify-content: flex-end;
}

.wcpmp-message-content {
    max-width: 80%;
    padding: 12px 16px;
    border-radius: 12px;
    line-height: 1.5;
}

.wcpmp-message-bot .wcpmp-message-content {
    background: #f0f0f1;
    border-bottom-left-radius: 4px;
}

.wcpmp-message-user .wcpmp-message-content {
    background: #2271b1;
    color: #fff;
    border-bottom-right-radius: 4px;
}

.wcpmp-message-content ul {
    margin: 10px 0 0;
    padding-left: 20px;
}

.wcpmp-message-content li {
    margin-bottom: 5px;
}

.wcpmp-message-bot .wcpmp-message-content a {
    color: #2271b1;
}

.wcpmp-chat-input {
    border-top: 1px solid #ccd0d4;
    padding: 15px;
}

.wcpmp-chat-input form {
    display: flex;
    gap: 10px;
}

.wcpmp-chat-input input {
    flex: 1;
    padding: 10px 15px;
    border: 1px solid #8c8f94;
    border-radius: 20px;
    font-size: 14px;
}

.wcpmp-chat-input button {
    border-radius: 50%;
    width: 40px;
    height: 40px;
    padding: 0;
    display: flex;
    align-items: center;
    justify-content: center;
}

.wcpmp-examples-sidebar {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 8px;
    padding: 20px;
    height: fit-content;
}

.wcpmp-examples-sidebar h3 {
    margin: 0 0 10px;
    font-size: 14px;
}

.wcpmp-examples-sidebar ul {
    margin: 0 0 20px;
    padding: 0;
    list-style: none;
}

.wcpmp-examples-sidebar li {
    margin-bottom: 8px;
}

.wcpmp-examples-sidebar a {
    text-decoration: none;
    color: #2271b1;
    font-size: 13px;
}

.wcpmp-examples-sidebar a:hover {
    text-decoration: underline;
}

.wcpmp-tips {
    font-size: 12px;
    color: #646970;
}

.wcpmp-tips li {
    margin-bottom: 5px;
}

/* Preview table */
.wcpmp-preview-table {
    width: 100%;
    border-collapse: collapse;
    margin: 10px 0;
    font-size: 12px;
}

.wcpmp-preview-table th,
.wcpmp-preview-table td {
    padding: 8px;
    border: 1px solid #dcdcde;
    text-align: left;
}

.wcpmp-preview-table th {
    background: #f6f7f7;
}

.wcpmp-preview-table .change-positive {
    color: #00a32a;
}

.wcpmp-preview-table .change-negative {
    color: #d63638;
}

/* Confirmation buttons */
.wcpmp-confirmation {
    display: flex;
    gap: 10px;
    margin-top: 15px;
}

.wcpmp-confirmation button {
    padding: 8px 16px;
}

/* Loading */
.wcpmp-loading {
    display: flex;
    align-items: center;
    gap: 10px;
    color: #646970;
}

.wcpmp-loading .spinner {
    float: none;
    margin: 0;
}

@media (max-width: 1200px) {
    .wcpmp-ai-assistant {
        grid-template-columns: 1fr;
    }

    .wcpmp-examples-sidebar {
        display: none;
    }
}
</style>

<script>
jQuery(document).ready(function($) {
    var $messages = $('#wcpmp-chat-messages');
    var $input = $('#wcpmp-command-input');
    var pendingCommand = null;

    // Send command
    $('#wcpmp-command-form').on('submit', function(e) {
        e.preventDefault();

        var command = $input.val().trim();
        if (!command) return;

        // Add user message
        addMessage(command, 'user');
        $input.val('');

        // Show loading
        var $loading = $('<div class="wcpmp-message wcpmp-message-bot"><div class="wcpmp-message-content wcpmp-loading"><span class="spinner is-active"></span> <?php echo esc_js(__("Processing...", "wc-product-manager-pro")); ?></div></div>');
        $messages.append($loading);
        scrollToBottom();

        // Send to server
        $.ajax({
            url: wcpmp.ajax_url,
            type: 'POST',
            data: {
                action: 'wcpmp_ai_process_command',
                nonce: wcpmp.nonce,
                command: command,
                language: '<?php echo esc_js($language); ?>'
            },
            success: function(response) {
                $loading.remove();

                if (response.success) {
                    showPreview(response.data);
                } else {
                    addMessage('❌ ' + response.data, 'bot');
                }
            },
            error: function() {
                $loading.remove();
                addMessage('❌ <?php echo esc_js(__("Error occurred", "wc-product-manager-pro")); ?>', 'bot');
            }
        });
    });

    // Example command click
    $(document).on('click', '.wcpmp-example-command', function(e) {
        e.preventDefault();
        $input.val($(this).text()).focus();
    });

    // Show preview
    function showPreview(data) {
        pendingCommand = data.command;

        var preview = data.preview;
        var html = '<strong>' + preview.summary + '</strong><br><br>';
        html += '<?php echo esc_js(__("Affected products:", "wc-product-manager-pro")); ?> ' + preview.total_affected;

        if (preview.total_affected > preview.showing) {
            html += ' (<?php echo esc_js(__("showing first", "wc-product-manager-pro")); ?> ' + preview.showing + ')';
        }

        html += '<table class="wcpmp-preview-table"><thead><tr>';
        html += '<th>SKU</th><th><?php echo esc_js(__("Product", "wc-product-manager-pro")); ?></th>';
        html += '<th><?php echo esc_js(__("Current", "wc-product-manager-pro")); ?></th>';
        html += '<th><?php echo esc_js(__("New", "wc-product-manager-pro")); ?></th>';
        html += '<th><?php echo esc_js(__("Change", "wc-product-manager-pro")); ?></th>';
        html += '</tr></thead><tbody>';

        preview.items.forEach(function(item) {
            var changeClass = item.change > 0 ? 'change-positive' : (item.change < 0 ? 'change-negative' : '');
            var changePrefix = item.change > 0 ? '+' : '';

            html += '<tr>';
            html += '<td>' + item.sku + '</td>';
            html += '<td>' + item.name.substring(0, 30) + '</td>';
            html += '<td>' + formatValue(item.old_value, item.field) + '</td>';
            html += '<td>' + formatValue(item.new_value, item.field) + '</td>';
            html += '<td class="' + changeClass + '">' + changePrefix + formatValue(item.change, item.field) + '</td>';
            html += '</tr>';
        });

        html += '</tbody></table>';

        html += '<div class="wcpmp-confirmation">';
        html += '<label><input type="checkbox" id="wcpmp-sync-stores"> <?php echo esc_js(__("Sync to connected stores", "wc-product-manager-pro")); ?></label>';
        html += '</div>';

        html += '<div class="wcpmp-confirmation">';
        html += '<button class="button button-primary wcpmp-confirm-changes"><?php echo esc_js(__("Apply Changes", "wc-product-manager-pro")); ?></button>';
        html += '<button class="button wcpmp-cancel-changes"><?php echo esc_js(__("Cancel", "wc-product-manager-pro")); ?></button>';
        html += '</div>';

        addMessage(html, 'bot');
    }

    // Format value
    function formatValue(value, field) {
        if (field === 'price') {
            return parseFloat(value).toFixed(2) + ' <?php echo esc_js(__("UAH", "wc-product-manager-pro")); ?>';
        }
        return parseInt(value) + ' <?php echo esc_js(__("pcs", "wc-product-manager-pro")); ?>';
    }

    // Confirm changes
    $(document).on('click', '.wcpmp-confirm-changes', function() {
        if (!pendingCommand) return;

        var $btn = $(this);
        var syncToStores = $('#wcpmp-sync-stores').is(':checked');

        $btn.prop('disabled', true).text('<?php echo esc_js(__("Applying...", "wc-product-manager-pro")); ?>');

        $.ajax({
            url: wcpmp.ajax_url,
            type: 'POST',
            data: {
                action: 'wcpmp_ai_apply_changes',
                nonce: wcpmp.nonce,
                command_data: JSON.stringify(pendingCommand),
                sync_to_stores: syncToStores ? 1 : 0
            },
            success: function(response) {
                if (response.success) {
                    addMessage('✅ ' + response.data.message, 'bot');
                } else {
                    addMessage('❌ ' + response.data, 'bot');
                }
            },
            error: function() {
                addMessage('❌ <?php echo esc_js(__("Error occurred", "wc-product-manager-pro")); ?>', 'bot');
            },
            complete: function() {
                pendingCommand = null;
                $('.wcpmp-confirmation').remove();
            }
        });
    });

    // Cancel changes
    $(document).on('click', '.wcpmp-cancel-changes', function() {
        pendingCommand = null;
        addMessage('<?php echo esc_js(__("Changes cancelled", "wc-product-manager-pro")); ?>', 'bot');
        $('.wcpmp-confirmation').remove();
    });

    // Add message to chat
    function addMessage(content, type) {
        var $msg = $('<div class="wcpmp-message wcpmp-message-' + type + '"><div class="wcpmp-message-content">' + content + '</div></div>');
        $messages.append($msg);
        scrollToBottom();
    }

    // Scroll to bottom
    function scrollToBottom() {
        $messages.scrollTop($messages[0].scrollHeight);
    }
});
</script>
