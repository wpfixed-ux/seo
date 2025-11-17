<?php
/**
 * Settings View
 *
 * @package WC_AI_Translator
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1><?php _e('WC AI Translator Settings', 'wc-ai-translator'); ?></h1>

    <?php settings_errors(); ?>

    <form method="post" action="options.php">
        <?php
        settings_fields('wcat_settings_group');
        do_settings_sections('wc-ai-translator-settings');
        submit_button();
        ?>
    </form>

    <!-- Test Connection -->
    <div class="wcat-section">
        <h2><?php _e('Test API Connection', 'wc-ai-translator'); ?></h2>
        <p><?php _e('Test your OpenAI API connection to ensure everything is configured correctly.', 'wc-ai-translator'); ?></p>
        <p>
            <button type="button" class="button" id="wcat-test-connection">
                <?php _e('Test Connection', 'wc-ai-translator'); ?>
            </button>
        </p>
        <div id="wcat-test-result" style="margin-top: 15px;"></div>
    </div>
</div>
