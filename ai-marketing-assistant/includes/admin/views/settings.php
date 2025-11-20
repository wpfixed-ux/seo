<?php
/**
 * Settings page view
 *
 * @package AIMarketingAssistant
 */

if (!defined('ABSPATH')) {
    exit;
}

// Display saved messages
settings_errors('aima_messages');
?>

<div class="wrap">
    <h1><?php _e('AI Marketing Assistant - Settings', 'ai-marketing-assistant'); ?></h1>

    <form method="post" action="">
        <?php wp_nonce_field('aima_save_settings'); ?>

        <!-- AI Provider Settings -->
        <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-top: 20px;">
            <h2><?php _e('AI Provider Settings', 'ai-marketing-assistant'); ?></h2>
            <p><?php _e('Configure your AI provider for generating marketing content', 'ai-marketing-assistant'); ?></p>

            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="aima_ai_provider"><?php _e('AI Provider', 'ai-marketing-assistant'); ?></label>
                    </th>
                    <td>
                        <select name="aima_ai_provider" id="aima_ai_provider" class="regular-text">
                            <?php foreach ($providers as $provider_key => $provider_data): ?>
                                <option value="<?php echo esc_attr($provider_key); ?>"
                                    <?php selected($current_provider, $provider_key); ?>>
                                    <?php echo esc_html($provider_data['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description">
                            <?php _e('Choose your preferred AI provider. Each has different capabilities and pricing.', 'ai-marketing-assistant'); ?>
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="aima_ai_model"><?php _e('AI Model', 'ai-marketing-assistant'); ?></label>
                    </th>
                    <td>
                        <select name="aima_ai_model" id="aima_ai_model" class="regular-text">
                            <?php foreach ($current_models as $model_key => $model_name): ?>
                                <option value="<?php echo esc_attr($model_key); ?>"
                                    <?php selected(get_option('aima_ai_model'), $model_key); ?>>
                                    <?php echo esc_html($model_name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description">
                            <?php _e('Select the specific model to use. Latest models usually provide better results.', 'ai-marketing-assistant'); ?>
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="aima_ai_api_key"><?php _e('API Key', 'ai-marketing-assistant'); ?></label>
                    </th>
                    <td>
                        <input type="password" name="aima_ai_api_key" id="aima_ai_api_key"
                               value="<?php echo esc_attr(get_option('aima_ai_api_key')); ?>"
                               class="regular-text" autocomplete="off">
                        <button type="button" id="aima_test_api" class="button" style="margin-left: 10px;">
                            <?php _e('Test Connection', 'ai-marketing-assistant'); ?>
                        </button>
                        <div id="aima_test_result" style="margin-top: 10px;"></div>
                        <p class="description">
                            <strong><?php _e('API Key Setup:', 'ai-marketing-assistant'); ?></strong><br>
                            • <strong>Claude (Anthropic):</strong> Get API key at <a href="https://console.anthropic.com/" target="_blank">console.anthropic.com</a><br>
                            • <strong>OpenAI:</strong> Get API key at <a href="https://platform.openai.com/api-keys" target="_blank">platform.openai.com/api-keys</a><br>
                            • <strong>Kimi (Moonshot):</strong> Get API key at <a href="https://platform.moonshot.cn/" target="_blank">platform.moonshot.cn</a>
                        </p>
                    </td>
                </tr>
            </table>

            <div id="aima_provider_models" style="display: none;" data-providers='<?php echo json_encode($providers); ?>'></div>
        </div>

        <!-- Email Settings -->
        <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-top: 20px;">
            <h2><?php _e('Email Campaign Settings', 'ai-marketing-assistant'); ?></h2>

            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="aima_email_from_name"><?php _e('From Name', 'ai-marketing-assistant'); ?></label>
                    </th>
                    <td>
                        <input type="text" name="aima_email_from_name" id="aima_email_from_name"
                               value="<?php echo esc_attr(get_option('aima_email_from_name')); ?>"
                               class="regular-text">
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="aima_email_from_email"><?php _e('From Email', 'ai-marketing-assistant'); ?></label>
                    </th>
                    <td>
                        <input type="email" name="aima_email_from_email" id="aima_email_from_email"
                               value="<?php echo esc_attr(get_option('aima_email_from_email')); ?>"
                               class="regular-text">
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="aima_campaign_frequency"><?php _e('Campaign Frequency', 'ai-marketing-assistant'); ?></label>
                    </th>
                    <td>
                        <select name="aima_campaign_frequency" id="aima_campaign_frequency">
                            <option value="daily" <?php selected(get_option('aima_campaign_frequency'), 'daily'); ?>>
                                <?php _e('Daily', 'ai-marketing-assistant'); ?>
                            </option>
                            <option value="weekly" <?php selected(get_option('aima_campaign_frequency'), 'weekly'); ?>>
                                <?php _e('Weekly', 'ai-marketing-assistant'); ?>
                            </option>
                            <option value="monthly" <?php selected(get_option('aima_campaign_frequency'), 'monthly'); ?>>
                                <?php _e('Monthly', 'ai-marketing-assistant'); ?>
                            </option>
                        </select>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Telegram Settings -->
        <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-top: 20px;">
            <h2><?php _e('Telegram Bot Settings', 'ai-marketing-assistant'); ?></h2>
            <p><?php _e('Configure Telegram bot for sending notifications to subscribers', 'ai-marketing-assistant'); ?></p>

            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="aima_telegram_bot_token"><?php _e('Bot Token', 'ai-marketing-assistant'); ?></label>
                    </th>
                    <td>
                        <input type="text" name="aima_telegram_bot_token" id="aima_telegram_bot_token"
                               value="<?php echo esc_attr(get_option('aima_telegram_bot_token')); ?>"
                               class="regular-text">
                        <p class="description">
                            <?php _e('Get bot token from', 'ai-marketing-assistant'); ?>
                            <a href="https://t.me/BotFather" target="_blank">@BotFather</a>
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="aima_telegram_channel_id"><?php _e('Channel ID (optional)', 'ai-marketing-assistant'); ?></label>
                    </th>
                    <td>
                        <input type="text" name="aima_telegram_channel_id" id="aima_telegram_channel_id"
                               value="<?php echo esc_attr(get_option('aima_telegram_channel_id')); ?>"
                               class="regular-text">
                    </td>
                </tr>
            </table>
        </div>

        <!-- Analytics Settings -->
        <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-top: 20px;">
            <h2><?php _e('Analytics Settings', 'ai-marketing-assistant'); ?></h2>

            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="aima_analytics_days"><?php _e('Analytics Period (days)', 'ai-marketing-assistant'); ?></label>
                    </th>
                    <td>
                        <input type="number" name="aima_analytics_days" id="aima_analytics_days"
                               value="<?php echo esc_attr(get_option('aima_analytics_days')); ?>"
                               min="1" max="365" class="small-text">
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="aima_min_purchase_count"><?php _e('Min Purchase Count for Segmentation', 'ai-marketing-assistant'); ?></label>
                    </th>
                    <td>
                        <input type="number" name="aima_min_purchase_count" id="aima_min_purchase_count"
                               value="<?php echo esc_attr(get_option('aima_min_purchase_count')); ?>"
                               min="1" max="100" class="small-text">
                    </td>
                </tr>
            </table>
        </div>

        <p class="submit">
            <button type="submit" name="save_settings" class="button button-primary button-large">
                <?php _e('Save Settings', 'ai-marketing-assistant'); ?>
            </button>
        </p>
    </form>

    <!-- Provider Comparison -->
    <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-top: 20px;">
        <h2><?php _e('AI Provider Comparison', 'ai-marketing-assistant'); ?></h2>
        <table class="widefat" style="margin-top: 15px;">
            <thead>
                <tr>
                    <th><?php _e('Provider', 'ai-marketing-assistant'); ?></th>
                    <th><?php _e('Best For', 'ai-marketing-assistant'); ?></th>
                    <th><?php _e('Languages', 'ai-marketing-assistant'); ?></th>
                    <th><?php _e('Pricing', 'ai-marketing-assistant'); ?></th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><strong>Claude (Anthropic)</strong></td>
                    <td>Creative marketing content, long-form text, detailed analysis</td>
                    <td>Excellent multilingual support including Russian</td>
                    <td>$3-15 per million tokens</td>
                </tr>
                <tr>
                    <td><strong>OpenAI GPT-4</strong></td>
                    <td>Versatile tasks, structured outputs, fast responses</td>
                    <td>Good multilingual, strong English</td>
                    <td>$2.50-30 per million tokens</td>
                </tr>
                <tr>
                    <td><strong>Kimi (Moonshot)</strong></td>
                    <td>Long context, Chinese/Asian markets, cost-effective</td>
                    <td>Excellent Chinese, good English/Russian</td>
                    <td>$0.50-2 per million tokens</td>
                </tr>
            </tbody>
        </table>
        <p style="margin-top: 15px; font-style: italic;">
            <?php _e('💡 Tip: Test all three providers with your content to find which works best for your needs!', 'ai-marketing-assistant'); ?>
        </p>
    </div>
</div>
