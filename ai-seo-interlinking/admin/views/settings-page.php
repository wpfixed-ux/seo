<?php
/**
 * Settings Page Template
 *
 * @package AI_SEO_Interlinking
 */

if (!defined('ABSPATH')) {
    exit;
}

$settings = get_option('ail_settings', []);
$scheduler = AIL_Scheduler::get_instance();
$schedule_status = $scheduler->get_schedule_status();
?>

<div class="wrap ail-settings-page">
    <h1><?php _e('AI SEO Interlinking Settings', 'ai-seo-interlinking'); ?></h1>

    <?php settings_errors(); ?>

    <form method="post" action="options.php" id="ail-settings-form">
        <?php settings_fields('ail_settings_group'); ?>

        <div class="ail-tabs">
            <nav class="nav-tab-wrapper">
                <a href="#tab-api" class="nav-tab nav-tab-active"><?php _e('API Settings', 'ai-seo-interlinking'); ?></a>
                <a href="#tab-strategy" class="nav-tab"><?php _e('Linking Strategy', 'ai-seo-interlinking'); ?></a>
                <a href="#tab-keywords" class="nav-tab"><?php _e('Keywords', 'ai-seo-interlinking'); ?></a>
                <a href="#tab-scheduler" class="nav-tab"><?php _e('Scheduler', 'ai-seo-interlinking'); ?></a>
                <a href="#tab-languages" class="nav-tab"><?php _e('Languages', 'ai-seo-interlinking'); ?></a>
                <a href="#tab-advanced" class="nav-tab"><?php _e('Advanced', 'ai-seo-interlinking'); ?></a>
            </nav>

            <!-- API Settings Tab -->
            <div id="tab-api" class="ail-tab-content active">
                <h2><?php _e('OpenAI API Configuration', 'ai-seo-interlinking'); ?></h2>

                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="openai_api_key"><?php _e('API Key', 'ai-seo-interlinking'); ?></label>
                        </th>
                        <td>
                            <input type="password"
                                   id="openai_api_key"
                                   name="ail_settings[openai_api_key]"
                                   value=""
                                   placeholder="sk-..."
                                   class="regular-text">
                            <p class="description">
                                <?php _e('Enter your OpenAI API key. Get one at', 'ai-seo-interlinking'); ?>
                                <a href="https://platform.openai.com/api-keys" target="_blank">OpenAI Platform</a>
                            </p>
                            <button type="button" id="test-connection" class="button">
                                <?php _e('Test Connection', 'ai-seo-interlinking'); ?>
                            </button>
                            <span id="connection-status"></span>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="openai_model"><?php _e('Model', 'ai-seo-interlinking'); ?></label>
                        </th>
                        <td>
                            <select id="openai_model" name="ail_settings[openai_model]">
                                <option value="gpt-4o-mini" <?php selected($settings['openai_model'] ?? 'gpt-4o-mini', 'gpt-4o-mini'); ?>>
                                    GPT-4o Mini (Recommended, Fast & Cheap)
                                </option>
                                <option value="gpt-4o" <?php selected($settings['openai_model'] ?? '', 'gpt-4o'); ?>>
                                    GPT-4o (Best Quality, Higher Cost)
                                </option>
                                <option value="gpt-4" <?php selected($settings['openai_model'] ?? '', 'gpt-4'); ?>>
                                    GPT-4 (Legacy, Expensive)
                                </option>
                            </select>
                            <p class="description">
                                <?php _e('GPT-4o Mini: ~$0.15/$0.60 per 1M tokens (input/output)', 'ai-seo-interlinking'); ?><br>
                                <?php _e('GPT-4o: ~$2.50/$10 per 1M tokens (input/output)', 'ai-seo-interlinking'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="max_tokens"><?php _e('Max Tokens', 'ai-seo-interlinking'); ?></label>
                        </th>
                        <td>
                            <input type="number"
                                   id="max_tokens"
                                   name="ail_settings[max_tokens]"
                                   value="<?php echo esc_attr($settings['max_tokens'] ?? 500); ?>"
                                   min="50"
                                   max="4000">
                            <p class="description"><?php _e('Maximum tokens per API request', 'ai-seo-interlinking'); ?></p>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- Linking Strategy Tab -->
            <div id="tab-strategy" class="ail-tab-content">
                <h2><?php _e('Internal Linking Strategy', 'ai-seo-interlinking'); ?></h2>

                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="linking_strategy"><?php _e('Strategy', 'ai-seo-interlinking'); ?></label>
                        </th>
                        <td>
                            <select id="linking_strategy" name="ail_settings[linking_strategy]">
                                <option value="pyramid" <?php selected($settings['linking_strategy'] ?? 'pyramid', 'pyramid'); ?>>
                                    <?php _e('Pyramid (Homepage → Categories → Products)', 'ai-seo-interlinking'); ?>
                                </option>
                                <option value="circular" <?php selected($settings['linking_strategy'] ?? '', 'circular'); ?>>
                                    <?php _e('Circular (Same Level Pages)', 'ai-seo-interlinking'); ?>
                                </option>
                                <option value="cluster" <?php selected($settings['linking_strategy'] ?? '', 'cluster'); ?>>
                                    <?php _e('Thematic Clusters (Semantic Relevance)', 'ai-seo-interlinking'); ?>
                                </option>
                                <option value="hub" <?php selected($settings['linking_strategy'] ?? '', 'hub'); ?>>
                                    <?php _e('Hub (Pillar + Supporting)', 'ai-seo-interlinking'); ?>
                                </option>
                                <option value="ai_auto" <?php selected($settings['linking_strategy'] ?? '', 'ai_auto'); ?>>
                                    <?php _e('AI Automatic', 'ai-seo-interlinking'); ?>
                                </option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="max_outbound_links"><?php _e('Max Outbound Links', 'ai-seo-interlinking'); ?></label>
                        </th>
                        <td>
                            <input type="number"
                                   id="max_outbound_links"
                                   name="ail_settings[max_outbound_links]"
                                   value="<?php echo esc_attr($settings['max_outbound_links'] ?? 5); ?>"
                                   min="1"
                                   max="20">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="max_inbound_links"><?php _e('Max Inbound Links', 'ai-seo-interlinking'); ?></label>
                        </th>
                        <td>
                            <input type="number"
                                   id="max_inbound_links"
                                   name="ail_settings[max_inbound_links]"
                                   value="<?php echo esc_attr($settings['max_inbound_links'] ?? 10); ?>"
                                   min="1"
                                   max="50">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="min_content_length"><?php _e('Min Content Length (words)', 'ai-seo-interlinking'); ?></label>
                        </th>
                        <td>
                            <input type="number"
                                   id="min_content_length"
                                   name="ail_settings[min_content_length]"
                                   value="<?php echo esc_attr($settings['min_content_length'] ?? 300); ?>"
                                   min="50"
                                   max="2000">
                        </td>
                    </tr>
                </table>
            </div>

            <!-- Keywords Tab -->
            <div id="tab-keywords" class="ail-tab-content">
                <h2><?php _e('Keyword Management', 'ai-seo-interlinking'); ?></h2>

                <div class="ail-keywords-section">
                    <h3><?php _e('AI Prompt Template', 'ai-seo-interlinking'); ?></h3>
                    <textarea name="ail_settings[ai_prompt_template]"
                              rows="10"
                              class="large-text code"><?php echo esc_textarea($settings['ai_prompt_template'] ?? ''); ?></textarea>
                    <p class="description">
                        <?php _e('Available variables: {post_title}, {post_content}, {category}, {language}', 'ai-seo-interlinking'); ?>
                    </p>
                </div>

                <div class="ail-keywords-actions">
                    <button type="button" id="generate-keywords" class="button button-primary">
                        <?php _e('Generate Keywords for All Posts', 'ai-seo-interlinking'); ?>
                    </button>
                    <div id="keywords-progress" style="display:none;">
                        <progress id="keywords-progress-bar" max="100" value="0"></progress>
                        <span id="keywords-progress-text">0%</span>
                    </div>
                </div>
            </div>

            <!-- Scheduler Tab -->
            <div id="tab-scheduler" class="ail-tab-content">
                <h2><?php _e('Batch Processing Scheduler', 'ai-seo-interlinking'); ?></h2>

                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="scheduler_enabled"><?php _e('Enable Scheduler', 'ai-seo-interlinking'); ?></label>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox"
                                       id="scheduler_enabled"
                                       name="ail_settings[scheduler_enabled]"
                                       value="1"
                                       <?php checked($settings['scheduler_enabled'] ?? false, true); ?>>
                                <?php _e('Run automated batch processing', 'ai-seo-interlinking'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="scheduler_frequency"><?php _e('Frequency', 'ai-seo-interlinking'); ?></label>
                        </th>
                        <td>
                            <select id="scheduler_frequency" name="ail_settings[scheduler_frequency]">
                                <option value="daily" <?php selected($settings['scheduler_frequency'] ?? 'daily', 'daily'); ?>>
                                    <?php _e('Daily', 'ai-seo-interlinking'); ?>
                                </option>
                                <option value="weekly" <?php selected($settings['scheduler_frequency'] ?? '', 'weekly'); ?>>
                                    <?php _e('Weekly', 'ai-seo-interlinking'); ?>
                                </option>
                                <option value="monthly" <?php selected($settings['scheduler_frequency'] ?? '', 'monthly'); ?>>
                                    <?php _e('Monthly', 'ai-seo-interlinking'); ?>
                                </option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="scheduler_time"><?php _e('Time', 'ai-seo-interlinking'); ?></label>
                        </th>
                        <td>
                            <input type="time"
                                   id="scheduler_time"
                                   name="ail_settings[scheduler_time]"
                                   value="<?php echo esc_attr($settings['scheduler_time'] ?? '02:00'); ?>">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="batch_size"><?php _e('Batch Size', 'ai-seo-interlinking'); ?></label>
                        </th>
                        <td>
                            <input type="number"
                                   id="batch_size"
                                   name="ail_settings[batch_size]"
                                   value="<?php echo esc_attr($settings['batch_size'] ?? 50); ?>"
                                   min="10"
                                   max="500">
                            <p class="description"><?php _e('Number of posts to process per batch', 'ai-seo-interlinking'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Next Scheduled Run', 'ai-seo-interlinking'); ?></th>
                        <td>
                            <?php if ($schedule_status['next_run']): ?>
                                <strong><?php echo esc_html($schedule_status['next_run']); ?></strong>
                            <?php else: ?>
                                <em><?php _e('Not scheduled', 'ai-seo-interlinking'); ?></em>
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>

                <div class="ail-manual-processing">
                    <h3><?php _e('Manual Processing', 'ai-seo-interlinking'); ?></h3>
                    <button type="button" id="process-batch" class="button button-primary">
                        <?php _e('Process Batch Now', 'ai-seo-interlinking'); ?>
                    </button>
                    <div id="batch-results" style="display:none;"></div>
                </div>
            </div>

            <!-- Languages Tab -->
            <div id="tab-languages" class="ail-tab-content">
                <h2><?php _e('Language Settings', 'ai-seo-interlinking'); ?></h2>

                <?php
                $multilang_plugin = AIL_Multilang_Support::detect_plugin();
                $available_languages = AIL_Multilang_Support::get_available_languages();
                $active_languages = isset($settings['active_languages']) ? $settings['active_languages'] : [];
                ?>

                <?php if ($multilang_plugin !== 'none'): ?>
                <div class="notice notice-info inline">
                    <p>
                        <strong><?php _e('Multilingual Plugin Detected:', 'ai-seo-interlinking'); ?></strong>
                        <?php
                        if ($multilang_plugin === 'polylang') {
                            echo 'Polylang';
                        } elseif ($multilang_plugin === 'wpml') {
                            echo 'WPML';
                        }
                        ?>
                    </p>
                    <p><?php _e('Languages are automatically detected from your multilingual plugin.', 'ai-seo-interlinking'); ?></p>
                </div>
                <?php else: ?>
                <p><?php _e('Select which languages you want to process for internal linking.', 'ai-seo-interlinking'); ?></p>
                <?php endif; ?>

                <table class="form-table">
                    <?php if ($multilang_plugin === 'none'): ?>
                    <tr>
                        <th scope="row">
                            <?php _e('Active Languages for Processing', 'ai-seo-interlinking'); ?>
                        </th>
                        <td>
                            <fieldset>
                                <legend class="screen-reader-text">
                                    <?php _e('Select which languages to process', 'ai-seo-interlinking'); ?>
                                </legend>
                                <?php foreach ($available_languages as $code => $name): ?>
                                <label style="display: block; margin-bottom: 5px;">
                                    <input type="checkbox"
                                           name="ail_settings[active_languages][]"
                                           value="<?php echo esc_attr($code); ?>"
                                           <?php checked(in_array($code, $active_languages)); ?>>
                                    <?php echo esc_html($name); ?> (<?php echo esc_html($code); ?>)
                                </label>
                                <?php endforeach; ?>
                            </fieldset>
                            <p class="description">
                                <?php _e('Select one or more languages for AI interlinking processing.', 'ai-seo-interlinking'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="default_language"><?php _e('Default Language', 'ai-seo-interlinking'); ?></label>
                        </th>
                        <td>
                            <select id="default_language" name="ail_settings[default_language]">
                                <?php
                                $default_lang = isset($settings['default_language']) ? $settings['default_language'] : 'en';
                                foreach ($available_languages as $code => $name):
                                ?>
                                <option value="<?php echo esc_attr($code); ?>" <?php selected($default_lang, $code); ?>>
                                    <?php echo esc_html($name); ?> (<?php echo esc_html($code); ?>)
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description">
                                <?php _e('Default language for posts without language metadata.', 'ai-seo-interlinking'); ?>
                            </p>
                        </td>
                    </tr>
                    <?php endif; ?>

                    <tr>
                        <th scope="row"><?php _e('Current Languages Status', 'ai-seo-interlinking'); ?></th>
                        <td>
                            <?php
                            $active_langs = AIL_Multilang_Support::get_languages();
                            $lang_stats = AIL_Multilang_Support::get_language_statistics();
                            ?>
                            <table class="widefat">
                                <thead>
                                    <tr>
                                        <th><?php _e('Language', 'ai-seo-interlinking'); ?></th>
                                        <th><?php _e('Code', 'ai-seo-interlinking'); ?></th>
                                        <th><?php _e('Links', 'ai-seo-interlinking'); ?></th>
                                        <th><?php _e('Keywords', 'ai-seo-interlinking'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($active_langs as $lang_code): ?>
                                    <tr>
                                        <td><?php echo esc_html(AIL_Multilang_Support::get_language_name($lang_code)); ?></td>
                                        <td><code><?php echo esc_html($lang_code); ?></code></td>
                                        <td><?php echo isset($lang_stats[$lang_code]) ? number_format($lang_stats[$lang_code]['links_count']) : '0'; ?></td>
                                        <td><?php echo isset($lang_stats[$lang_code]) ? number_format($lang_stats[$lang_code]['keywords_count']) : '0'; ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="language_detection"><?php _e('Language Detection', 'ai-seo-interlinking'); ?></label>
                        </th>
                        <td>
                            <select id="language_detection" name="ail_settings[language_detection]">
                                <option value="auto" <?php selected($settings['language_detection'] ?? 'auto', 'auto'); ?>>
                                    <?php _e('Automatic (use WordPress locale)', 'ai-seo-interlinking'); ?>
                                </option>
                                <option value="manual" <?php selected($settings['language_detection'] ?? '', 'manual'); ?>>
                                    <?php _e('Manual (select languages above)', 'ai-seo-interlinking'); ?>
                                </option>
                            </select>
                            <p class="description">
                                <?php _e('How to detect post languages when no multilingual plugin is active.', 'ai-seo-interlinking'); ?>
                            </p>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- Advanced Tab -->
            <div id="tab-advanced" class="ail-tab-content">
                <h2><?php _e('Advanced Settings', 'ai-seo-interlinking'); ?></h2>

                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="enable_logging"><?php _e('Enable Logging', 'ai-seo-interlinking'); ?></label>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox"
                                       id="enable_logging"
                                       name="ail_settings[enable_logging]"
                                       value="1"
                                       <?php checked($settings['enable_logging'] ?? true, true); ?>>
                                <?php _e('Log all operations and API requests', 'ai-seo-interlinking'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="log_retention_days"><?php _e('Log Retention (days)', 'ai-seo-interlinking'); ?></label>
                        </th>
                        <td>
                            <input type="number"
                                   id="log_retention_days"
                                   name="ail_settings[log_retention_days]"
                                   value="<?php echo esc_attr($settings['log_retention_days'] ?? 30); ?>"
                                   min="1"
                                   max="365">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="auto_process_on_save"><?php _e('Auto Process on Save', 'ai-seo-interlinking'); ?></label>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox"
                                       id="auto_process_on_save"
                                       name="ail_settings[auto_process_on_save]"
                                       value="1"
                                       <?php checked($settings['auto_process_on_save'] ?? false, true); ?>>
                                <?php _e('Automatically process posts when saved', 'ai-seo-interlinking'); ?>
                            </label>
                        </td>
                    </tr>
                </table>
            </div>
        </div>

        <?php submit_button(); ?>
    </form>
</div>
