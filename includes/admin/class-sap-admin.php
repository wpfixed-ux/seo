<?php
/**
 * Admin-specific functionality
 *
 * @package    SEOAnalyticsPro
 * @subpackage SEOAnalyticsPro/includes/admin
 */

class SAP_Admin {
    private $plugin_name;
    private $version;

    public function __construct($plugin_name, $version) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
    }

    public function enqueue_styles() {
        wp_enqueue_style($this->plugin_name, SAP_ASSETS_URL . 'css/admin.css', array(), $this->version);
    }

    public function enqueue_scripts() {
        wp_enqueue_script($this->plugin_name, SAP_ASSETS_URL . 'js/admin/admin.js', array('jquery'), $this->version, true);
        wp_localize_script($this->plugin_name, 'sapData', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('sap_nonce'),
            'strings' => array(
                'analyzing' => __('Analyzing...', 'seo-analytics-pro'),
                'success' => __('Success!', 'seo-analytics-pro'),
                'error' => __('Error occurred', 'seo-analytics-pro'),
            )
        ));
    }

    public function add_plugin_admin_menu() {
        add_menu_page(
            'SEO Analytics Pro',
            'SEO Analytics',
            'manage_options',
            'seo-analytics-pro',
            array($this, 'display_plugin_admin_page'),
            'dashicons-chart-line',
            30
        );

        add_submenu_page(
            'seo-analytics-pro',
            __('Dashboard', 'seo-analytics-pro'),
            __('Dashboard', 'seo-analytics-pro'),
            'manage_options',
            'seo-analytics-pro',
            array($this, 'display_plugin_admin_page')
        );

        add_submenu_page(
            'seo-analytics-pro',
            __('New Project', 'seo-analytics-pro'),
            __('New Project', 'seo-analytics-pro'),
            'manage_options',
            'seo-analytics-pro-new',
            array($this, 'display_new_project_page')
        );

        add_submenu_page(
            'seo-analytics-pro',
            __('Keywords', 'seo-analytics-pro'),
            __('Keywords', 'seo-analytics-pro'),
            'manage_options',
            'seo-analytics-pro-keywords',
            array($this, 'display_keywords_page')
        );

        add_submenu_page(
            'seo-analytics-pro',
            __('Content Specs', 'seo-analytics-pro'),
            __('Content Specs', 'seo-analytics-pro'),
            'manage_options',
            'seo-analytics-pro-specs',
            array($this, 'display_specs_page')
        );

        add_submenu_page(
            'seo-analytics-pro',
            __('Content Generator', 'seo-analytics-pro'),
            __('Content Generator', 'seo-analytics-pro'),
            'manage_options',
            'seo-analytics-pro-generator',
            array($this, 'display_generator_page')
        );

        add_submenu_page(
            'seo-analytics-pro',
            __('Generation Queue', 'seo-analytics-pro'),
            __('Generation Queue', 'seo-analytics-pro'),
            'manage_options',
            'seo-analytics-pro-queue',
            array($this, 'display_queue_page')
        );

        add_submenu_page(
            'seo-analytics-pro',
            __('Settings', 'seo-analytics-pro'),
            __('Settings', 'seo-analytics-pro'),
            'manage_options',
            'seo-analytics-pro-settings',
            array($this, 'display_settings_page')
        );
    }

    public function register_settings() {
        register_setting('sap_settings', 'sap_settings', array($this, 'sanitize_settings'));
    }

    public function sanitize_settings($input) {
        $sanitized = array();

        if (isset($input['claude_ai_api_key'])) {
            $sanitized['claude_ai_api_key'] = sanitize_text_field($input['claude_ai_api_key']);
        }

        if (isset($input['serp_api_key'])) {
            $sanitized['serp_api_key'] = sanitize_text_field($input['serp_api_key']);
        }

        if (isset($input['serp_api_provider'])) {
            $sanitized['serp_api_provider'] = sanitize_text_field($input['serp_api_provider']);
        }

        if (isset($input['serp_location'])) {
            $sanitized['serp_location'] = sanitize_text_field($input['serp_location']);
        }

        if (isset($input['serp_language'])) {
            $sanitized['serp_language'] = sanitize_text_field($input['serp_language']);
        }

        if (isset($input['serp_google_domain'])) {
            $sanitized['serp_google_domain'] = sanitize_text_field($input['serp_google_domain']);
        }

        if (isset($input['max_serp_results'])) {
            $sanitized['max_serp_results'] = absint($input['max_serp_results']);
        }

        if (isset($input['max_competitors'])) {
            $sanitized['max_competitors'] = absint($input['max_competitors']);
        }

        return $sanitized;
    }

    /**
     * Main Dashboard Page
     */
    public function display_plugin_admin_page() {
        $settings = get_option('sap_settings', array());
        $has_claude_key = !empty($settings['claude_ai_api_key']);
        $has_serp_key = !empty($settings['serp_api_key']);
        ?>
        <div class="wrap sap-wrap">
            <h1><?php _e('SEO Analytics Pro', 'seo-analytics-pro'); ?></h1>

            <?php if (!$has_claude_key || !$has_serp_key): ?>
            <div class="notice notice-warning">
                <p>
                    <strong><?php _e('Setup Required:', 'seo-analytics-pro'); ?></strong>
                    <?php _e('Please configure your API keys in', 'seo-analytics-pro'); ?>
                    <a href="<?php echo admin_url('admin.php?page=seo-analytics-pro-settings'); ?>"><?php _e('Settings', 'seo-analytics-pro'); ?></a>
                </p>
            </div>
            <?php endif; ?>

            <div class="sap-dashboard">
                <!-- Quick Stats -->
                <div class="sap-cards">
                    <div class="sap-card">
                        <h3><?php _e('Projects', 'seo-analytics-pro'); ?></h3>
                        <div class="sap-stat">0</div>
                        <a href="<?php echo admin_url('admin.php?page=seo-analytics-pro-new'); ?>" class="button"><?php _e('Create New', 'seo-analytics-pro'); ?></a>
                    </div>

                    <div class="sap-card">
                        <h3><?php _e('Keywords', 'seo-analytics-pro'); ?></h3>
                        <div class="sap-stat">0</div>
                        <a href="<?php echo admin_url('admin.php?page=seo-analytics-pro-keywords'); ?>" class="button"><?php _e('View All', 'seo-analytics-pro'); ?></a>
                    </div>

                    <div class="sap-card">
                        <h3><?php _e('Content Specs', 'seo-analytics-pro'); ?></h3>
                        <div class="sap-stat">0</div>
                        <a href="<?php echo admin_url('admin.php?page=seo-analytics-pro-specs'); ?>" class="button"><?php _e('View All', 'seo-analytics-pro'); ?></a>
                    </div>

                    <div class="sap-card">
                        <h3><?php _e('API Status', 'seo-analytics-pro'); ?></h3>
                        <div class="sap-status">
                            <span class="<?php echo $has_claude_key ? 'status-ok' : 'status-error'; ?>">
                                Claude AI: <?php echo $has_claude_key ? '✓' : '✗'; ?>
                            </span>
                            <span class="<?php echo $has_serp_key ? 'status-ok' : 'status-error'; ?>">
                                SERP API: <?php echo $has_serp_key ? '✓' : '✗'; ?>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Quick Analysis -->
                <div class="sap-section">
                    <h2><?php _e('Quick Keyword Analysis', 'seo-analytics-pro'); ?></h2>
                    <form id="sap-quick-analysis" class="sap-form">
                        <div class="sap-form-row">
                            <label for="quick-keyword"><?php _e('Keyword', 'seo-analytics-pro'); ?></label>
                            <input type="text" id="quick-keyword" name="keyword" placeholder="<?php _e('Enter keyword to analyze...', 'seo-analytics-pro'); ?>" required>
                        </div>
                        <div class="sap-form-row">
                            <label for="quick-website"><?php _e('Your Website', 'seo-analytics-pro'); ?></label>
                            <input type="url" id="quick-website" name="website" placeholder="https://yourwebsite.com">
                        </div>
                        <button type="submit" class="button button-primary" <?php echo (!$has_claude_key || !$has_serp_key) ? 'disabled' : ''; ?>>
                            <?php _e('Analyze Keyword', 'seo-analytics-pro'); ?>
                        </button>
                    </form>
                    <div id="sap-quick-results" class="sap-results" style="display:none;"></div>
                </div>

                <!-- Getting Started -->
                <div class="sap-section">
                    <h2><?php _e('Getting Started', 'seo-analytics-pro'); ?></h2>
                    <ol class="sap-steps">
                        <li>
                            <strong><?php _e('Configure API Keys', 'seo-analytics-pro'); ?></strong>
                            <p><?php _e('Add your Claude AI and SERP API keys in Settings', 'seo-analytics-pro'); ?></p>
                        </li>
                        <li>
                            <strong><?php _e('Create a Project', 'seo-analytics-pro'); ?></strong>
                            <p><?php _e('Set up your website and add competitors to analyze', 'seo-analytics-pro'); ?></p>
                        </li>
                        <li>
                            <strong><?php _e('Analyze Keywords', 'seo-analytics-pro'); ?></strong>
                            <p><?php _e('Import keywords or let AI discover them from competitors', 'seo-analytics-pro'); ?></p>
                        </li>
                        <li>
                            <strong><?php _e('Generate Content Specs', 'seo-analytics-pro'); ?></strong>
                            <p><?php _e('Get AI-powered technical specifications for your content', 'seo-analytics-pro'); ?></p>
                        </li>
                    </ol>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * New Project Page
     */
    public function display_new_project_page() {
        ?>
        <div class="wrap sap-wrap">
            <h1><?php _e('Create New Project', 'seo-analytics-pro'); ?></h1>

            <form id="sap-new-project" class="sap-form sap-form-large">
                <div class="sap-form-section">
                    <h2><?php _e('Project Details', 'seo-analytics-pro'); ?></h2>

                    <div class="sap-form-row">
                        <label for="project-name"><?php _e('Project Name', 'seo-analytics-pro'); ?> *</label>
                        <input type="text" id="project-name" name="name" required>
                    </div>

                    <div class="sap-form-row">
                        <label for="target-website"><?php _e('Your Website URL', 'seo-analytics-pro'); ?> *</label>
                        <input type="url" id="target-website" name="target_website" placeholder="https://yourwebsite.com" required>
                    </div>
                </div>

                <div class="sap-form-section">
                    <h2><?php _e('Analysis Method', 'seo-analytics-pro'); ?></h2>

                    <div class="sap-radio-cards">
                        <label class="sap-radio-card">
                            <input type="radio" name="analysis_method" value="competitor" checked>
                            <div class="sap-radio-content">
                                <strong><?php _e('Competitor-Based Discovery', 'seo-analytics-pro'); ?></strong>
                                <p><?php _e('Extract keywords from competitor websites and analyze their strategies', 'seo-analytics-pro'); ?></p>
                            </div>
                        </label>

                        <label class="sap-radio-card">
                            <input type="radio" name="analysis_method" value="keyword">
                            <div class="sap-radio-content">
                                <strong><?php _e('Keyword-First Analysis', 'seo-analytics-pro'); ?></strong>
                                <p><?php _e('Start with your own keyword list and analyze SERP results', 'seo-analytics-pro'); ?></p>
                            </div>
                        </label>
                    </div>
                </div>

                <div class="sap-form-section" id="competitor-section">
                    <h2><?php _e('Competitor Websites', 'seo-analytics-pro'); ?></h2>
                    <p class="description"><?php _e('Add 3-10 competitor URLs to analyze (one per line)', 'seo-analytics-pro'); ?></p>

                    <div class="sap-form-row">
                        <textarea id="competitors" name="competitors" rows="6" placeholder="https://competitor1.com
https://competitor2.com
https://competitor3.com"></textarea>
                    </div>
                </div>

                <div class="sap-form-section" id="keywords-section" style="display:none;">
                    <h2><?php _e('Keywords to Analyze', 'seo-analytics-pro'); ?></h2>
                    <p class="description"><?php _e('Enter keywords to analyze (one per line)', 'seo-analytics-pro'); ?></p>

                    <div class="sap-form-row">
                        <textarea id="keywords" name="keywords" rows="6" placeholder="keyword 1
keyword 2
keyword 3"></textarea>
                    </div>

                    <div class="sap-form-row">
                        <label><?php _e('Or upload CSV file:', 'seo-analytics-pro'); ?></label>
                        <input type="file" id="keywords-file" name="keywords_file" accept=".csv,.txt">
                    </div>
                </div>

                <div class="sap-form-actions">
                    <button type="submit" class="button button-primary button-large">
                        <?php _e('Create Project & Start Analysis', 'seo-analytics-pro'); ?>
                    </button>
                </div>
            </form>
        </div>

        <script>
        jQuery(document).ready(function($) {
            $('input[name="analysis_method"]').on('change', function() {
                if ($(this).val() === 'competitor') {
                    $('#competitor-section').show();
                    $('#keywords-section').hide();
                } else {
                    $('#competitor-section').hide();
                    $('#keywords-section').show();
                }
            });
        });
        </script>
        <?php
    }

    /**
     * Keywords Page
     */
    public function display_keywords_page() {
        ?>
        <div class="wrap sap-wrap">
            <h1>
                <?php _e('Keywords', 'seo-analytics-pro'); ?>
                <a href="<?php echo admin_url('admin.php?page=seo-analytics-pro-new'); ?>" class="page-title-action"><?php _e('Add New', 'seo-analytics-pro'); ?></a>
            </h1>

            <div class="sap-toolbar">
                <div class="sap-filters">
                    <select id="filter-project">
                        <option value=""><?php _e('All Projects', 'seo-analytics-pro'); ?></option>
                    </select>
                    <select id="filter-status">
                        <option value=""><?php _e('All Statuses', 'seo-analytics-pro'); ?></option>
                        <option value="pending"><?php _e('Pending', 'seo-analytics-pro'); ?></option>
                        <option value="analyzed"><?php _e('Analyzed', 'seo-analytics-pro'); ?></option>
                        <option value="selected"><?php _e('Selected', 'seo-analytics-pro'); ?></option>
                    </select>
                    <button class="button"><?php _e('Filter', 'seo-analytics-pro'); ?></button>
                </div>
                <div class="sap-actions">
                    <button class="button" id="export-keywords"><?php _e('Export CSV', 'seo-analytics-pro'); ?></button>
                </div>
            </div>

            <table class="wp-list-table widefat fixed striped sap-table">
                <thead>
                    <tr>
                        <td class="check-column"><input type="checkbox"></td>
                        <th><?php _e('Keyword', 'seo-analytics-pro'); ?></th>
                        <th><?php _e('Search Volume', 'seo-analytics-pro'); ?></th>
                        <th><?php _e('Difficulty', 'seo-analytics-pro'); ?></th>
                        <th><?php _e('Competition', 'seo-analytics-pro'); ?></th>
                        <th><?php _e('Priority', 'seo-analytics-pro'); ?></th>
                        <th><?php _e('Status', 'seo-analytics-pro'); ?></th>
                        <th><?php _e('Actions', 'seo-analytics-pro'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td colspan="8" class="sap-empty-state">
                            <p><?php _e('No keywords yet.', 'seo-analytics-pro'); ?></p>
                            <a href="<?php echo admin_url('admin.php?page=seo-analytics-pro-new'); ?>" class="button button-primary">
                                <?php _e('Create your first project', 'seo-analytics-pro'); ?>
                            </a>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <?php
    }

    /**
     * Content Specs Page
     */
    public function display_specs_page() {
        ?>
        <div class="wrap sap-wrap">
            <h1><?php _e('Content Specifications', 'seo-analytics-pro'); ?></h1>

            <div class="sap-toolbar">
                <div class="sap-filters">
                    <select id="filter-content-type">
                        <option value=""><?php _e('All Types', 'seo-analytics-pro'); ?></option>
                        <option value="article"><?php _e('Articles', 'seo-analytics-pro'); ?></option>
                        <option value="product"><?php _e('Products', 'seo-analytics-pro'); ?></option>
                        <option value="category"><?php _e('Categories', 'seo-analytics-pro'); ?></option>
                        <option value="page"><?php _e('Pages', 'seo-analytics-pro'); ?></option>
                    </select>
                    <button class="button"><?php _e('Filter', 'seo-analytics-pro'); ?></button>
                </div>
                <div class="sap-actions">
                    <button class="button" id="export-specs"><?php _e('Export All', 'seo-analytics-pro'); ?></button>
                </div>
            </div>

            <div class="sap-specs-grid">
                <div class="sap-empty-state">
                    <h3><?php _e('No content specifications yet', 'seo-analytics-pro'); ?></h3>
                    <p><?php _e('Analyze keywords to generate AI-powered content specifications.', 'seo-analytics-pro'); ?></p>
                    <a href="<?php echo admin_url('admin.php?page=seo-analytics-pro-new'); ?>" class="button button-primary">
                        <?php _e('Start Analysis', 'seo-analytics-pro'); ?>
                    </a>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Settings Page
     */
    public function display_settings_page() {
        $settings = get_option('sap_settings', array());
        ?>
        <div class="wrap sap-wrap">
            <h1><?php _e('SEO Analytics Pro Settings', 'seo-analytics-pro'); ?></h1>

            <form method="post" action="options.php" class="sap-form sap-form-large">
                <?php settings_fields('sap_settings'); ?>

                <div class="sap-form-section">
                    <h2><?php _e('API Configuration', 'seo-analytics-pro'); ?></h2>
                    <p class="description"><?php _e('Enter your API keys to enable SEO analysis features.', 'seo-analytics-pro'); ?></p>

                    <div class="sap-form-row">
                        <label for="claude-api-key">
                            <?php _e('Claude AI API Key', 'seo-analytics-pro'); ?> *
                            <span class="sap-help">
                                <a href="https://console.anthropic.com/" target="_blank"><?php _e('Get API Key', 'seo-analytics-pro'); ?></a>
                            </span>
                        </label>
                        <input type="password"
                               id="claude-api-key"
                               name="sap_settings[claude_ai_api_key]"
                               value="<?php echo esc_attr($settings['claude_ai_api_key'] ?? ''); ?>"
                               class="regular-text">
                        <p class="description"><?php _e('Required for AI-powered analysis and content strategy generation.', 'seo-analytics-pro'); ?></p>
                    </div>

                    <div class="sap-form-row">
                        <label for="serp-api-provider"><?php _e('SERP API Provider', 'seo-analytics-pro'); ?></label>
                        <select id="serp-api-provider" name="sap_settings[serp_api_provider]">
                            <option value="serpapi" <?php selected($settings['serp_api_provider'] ?? '', 'serpapi'); ?>>SERPApi</option>
                            <option value="dataforseo" <?php selected($settings['serp_api_provider'] ?? '', 'dataforseo'); ?>>DataForSEO</option>
                            <option value="hasdata" <?php selected($settings['serp_api_provider'] ?? '', 'hasdata'); ?>>HasData (Free Plan Available)</option>
                        </select>
                        <p class="description">
                            <?php _e('Choose your SERP API provider.', 'seo-analytics-pro'); ?>
                            <a href="https://hasdata.com/prices" target="_blank"><?php _e('HasData has a free plan for testing', 'seo-analytics-pro'); ?></a>
                        </p>
                    </div>

                    <div class="sap-form-row">
                        <label for="serp-api-key">
                            <?php _e('SERP API Key', 'seo-analytics-pro'); ?> *
                        </label>
                        <input type="password"
                               id="serp-api-key"
                               name="sap_settings[serp_api_key]"
                               value="<?php echo esc_attr($settings['serp_api_key'] ?? ''); ?>"
                               class="regular-text">
                        <p class="description">
                            <?php _e('Required for fetching search engine results.', 'seo-analytics-pro'); ?>
                            <?php _e('Get API key:', 'seo-analytics-pro'); ?>
                            <a href="https://serpapi.com/" target="_blank">SERPApi</a> |
                            <a href="https://dataforseo.com/" target="_blank">DataForSEO</a> |
                            <a href="https://hasdata.com/" target="_blank">HasData</a>
                        </p>
                    </div>
                </div>

                <div class="sap-form-section">
                    <h2><?php _e('Region Settings', 'seo-analytics-pro'); ?></h2>
                    <p class="description"><?php _e('Configure the target region for search results analysis.', 'seo-analytics-pro'); ?></p>

                    <div class="sap-form-row">
                        <label for="serp-location"><?php _e('Target Country', 'seo-analytics-pro'); ?></label>
                        <select id="serp-location" name="sap_settings[serp_location]">
                            <option value="Ukraine" <?php selected($settings['serp_location'] ?? 'Ukraine', 'Ukraine'); ?>>Ukraine</option>
                            <option value="United States" <?php selected($settings['serp_location'] ?? '', 'United States'); ?>>United States</option>
                            <option value="United Kingdom" <?php selected($settings['serp_location'] ?? '', 'United Kingdom'); ?>>United Kingdom</option>
                            <option value="Germany" <?php selected($settings['serp_location'] ?? '', 'Germany'); ?>>Germany</option>
                            <option value="France" <?php selected($settings['serp_location'] ?? '', 'France'); ?>>France</option>
                            <option value="Poland" <?php selected($settings['serp_location'] ?? '', 'Poland'); ?>>Poland</option>
                            <option value="Canada" <?php selected($settings['serp_location'] ?? '', 'Canada'); ?>>Canada</option>
                            <option value="Australia" <?php selected($settings['serp_location'] ?? '', 'Australia'); ?>>Australia</option>
                            <option value="Spain" <?php selected($settings['serp_location'] ?? '', 'Spain'); ?>>Spain</option>
                            <option value="Italy" <?php selected($settings['serp_location'] ?? '', 'Italy'); ?>>Italy</option>
                            <option value="Netherlands" <?php selected($settings['serp_location'] ?? '', 'Netherlands'); ?>>Netherlands</option>
                            <option value="Brazil" <?php selected($settings['serp_location'] ?? '', 'Brazil'); ?>>Brazil</option>
                        </select>
                    </div>

                    <div class="sap-form-row">
                        <label for="serp-language"><?php _e('Search Language', 'seo-analytics-pro'); ?></label>
                        <select id="serp-language" name="sap_settings[serp_language]">
                            <option value="ru" <?php selected($settings['serp_language'] ?? 'ru', 'ru'); ?>>Русский (Russian)</option>
                            <option value="uk" <?php selected($settings['serp_language'] ?? '', 'uk'); ?>>Українська (Ukrainian)</option>
                            <option value="en" <?php selected($settings['serp_language'] ?? '', 'en'); ?>>English</option>
                            <option value="de" <?php selected($settings['serp_language'] ?? '', 'de'); ?>>Deutsch (German)</option>
                            <option value="fr" <?php selected($settings['serp_language'] ?? '', 'fr'); ?>>Français (French)</option>
                            <option value="pl" <?php selected($settings['serp_language'] ?? '', 'pl'); ?>>Polski (Polish)</option>
                            <option value="es" <?php selected($settings['serp_language'] ?? '', 'es'); ?>>Español (Spanish)</option>
                            <option value="it" <?php selected($settings['serp_language'] ?? '', 'it'); ?>>Italiano (Italian)</option>
                            <option value="nl" <?php selected($settings['serp_language'] ?? '', 'nl'); ?>>Nederlands (Dutch)</option>
                            <option value="pt" <?php selected($settings['serp_language'] ?? '', 'pt'); ?>>Português (Portuguese)</option>
                        </select>
                    </div>

                    <div class="sap-form-row">
                        <label for="serp-google-domain"><?php _e('Google Domain', 'seo-analytics-pro'); ?></label>
                        <select id="serp-google-domain" name="sap_settings[serp_google_domain]">
                            <option value="google.com.ua" <?php selected($settings['serp_google_domain'] ?? 'google.com.ua', 'google.com.ua'); ?>>google.com.ua (Ukraine)</option>
                            <option value="google.com" <?php selected($settings['serp_google_domain'] ?? '', 'google.com'); ?>>google.com (USA)</option>
                            <option value="google.co.uk" <?php selected($settings['serp_google_domain'] ?? '', 'google.co.uk'); ?>>google.co.uk (UK)</option>
                            <option value="google.de" <?php selected($settings['serp_google_domain'] ?? '', 'google.de'); ?>>google.de (Germany)</option>
                            <option value="google.fr" <?php selected($settings['serp_google_domain'] ?? '', 'google.fr'); ?>>google.fr (France)</option>
                            <option value="google.pl" <?php selected($settings['serp_google_domain'] ?? '', 'google.pl'); ?>>google.pl (Poland)</option>
                            <option value="google.ca" <?php selected($settings['serp_google_domain'] ?? '', 'google.ca'); ?>>google.ca (Canada)</option>
                            <option value="google.com.au" <?php selected($settings['serp_google_domain'] ?? '', 'google.com.au'); ?>>google.com.au (Australia)</option>
                            <option value="google.es" <?php selected($settings['serp_google_domain'] ?? '', 'google.es'); ?>>google.es (Spain)</option>
                            <option value="google.it" <?php selected($settings['serp_google_domain'] ?? '', 'google.it'); ?>>google.it (Italy)</option>
                            <option value="google.nl" <?php selected($settings['serp_google_domain'] ?? '', 'google.nl'); ?>>google.nl (Netherlands)</option>
                            <option value="google.com.br" <?php selected($settings['serp_google_domain'] ?? '', 'google.com.br'); ?>>google.com.br (Brazil)</option>
                        </select>
                        <p class="description"><?php _e('Select the Google domain for your target region.', 'seo-analytics-pro'); ?></p>
                    </div>
                </div>

                <div class="sap-form-section">
                    <h2><?php _e('Analysis Settings', 'seo-analytics-pro'); ?></h2>

                    <div class="sap-form-row">
                        <label for="max-serp-results"><?php _e('Max SERP Results', 'seo-analytics-pro'); ?></label>
                        <input type="number"
                               id="max-serp-results"
                               name="sap_settings[max_serp_results]"
                               value="<?php echo esc_attr($settings['max_serp_results'] ?? 20); ?>"
                               min="10" max="100" step="10">
                        <p class="description"><?php _e('Number of search results to analyze per keyword (10-100).', 'seo-analytics-pro'); ?></p>
                    </div>

                    <div class="sap-form-row">
                        <label for="max-competitors"><?php _e('Max Competitors', 'seo-analytics-pro'); ?></label>
                        <input type="number"
                               id="max-competitors"
                               name="sap_settings[max_competitors]"
                               value="<?php echo esc_attr($settings['max_competitors'] ?? 10); ?>"
                               min="3" max="20">
                        <p class="description"><?php _e('Maximum number of competitor websites per project (3-20).', 'seo-analytics-pro'); ?></p>
                    </div>
                </div>

                <div class="sap-form-actions">
                    <?php submit_button(__('Save Settings', 'seo-analytics-pro'), 'primary', 'submit', false); ?>
                    <button type="button" class="button" id="test-api-keys"><?php _e('Test API Keys', 'seo-analytics-pro'); ?></button>
                </div>
            </form>

            <div id="api-test-results" class="sap-results" style="display:none;"></div>
        </div>
        <?php
    }

    /**
     * Content Generator Page
     */
    public function display_generator_page() {
        $tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'articles';
        ?>
        <div class="wrap sap-wrap">
            <h1><?php _e('Content Generator', 'seo-analytics-pro'); ?></h1>

            <nav class="nav-tab-wrapper">
                <a href="?page=seo-analytics-pro-generator&tab=articles" class="nav-tab <?php echo $tab === 'articles' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Articles', 'seo-analytics-pro'); ?>
                </a>
                <a href="?page=seo-analytics-pro-generator&tab=products" class="nav-tab <?php echo $tab === 'products' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Products', 'seo-analytics-pro'); ?>
                </a>
                <a href="?page=seo-analytics-pro-generator&tab=categories" class="nav-tab <?php echo $tab === 'categories' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Categories', 'seo-analytics-pro'); ?>
                </a>
                <a href="?page=seo-analytics-pro-generator&tab=pages" class="nav-tab <?php echo $tab === 'pages' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Pages', 'seo-analytics-pro'); ?>
                </a>
            </nav>

            <div class="sap-generator-content">
                <?php
                switch ($tab) {
                    case 'products':
                        $this->display_generator_products();
                        break;
                    case 'categories':
                        $this->display_generator_categories();
                        break;
                    case 'pages':
                        $this->display_generator_pages();
                        break;
                    default:
                        $this->display_generator_articles();
                }
                ?>
            </div>
        </div>
        <?php
    }

    /**
     * Articles Generator Tab
     */
    private function display_generator_articles() {
        ?>
        <div class="sap-section">
            <h2><?php _e('Generate Articles', 'seo-analytics-pro'); ?></h2>

            <div class="sap-generator-options">
                <div class="sap-form-section">
                    <h3><?php _e('Select Content Specifications', 'seo-analytics-pro'); ?></h3>
                    <p class="description"><?php _e('Choose technical specifications to generate articles from.', 'seo-analytics-pro'); ?></p>

                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <td class="check-column"><input type="checkbox" id="select-all-specs"></td>
                                <th><?php _e('Title', 'seo-analytics-pro'); ?></th>
                                <th><?php _e('Keywords', 'seo-analytics-pro'); ?></th>
                                <th><?php _e('Length', 'seo-analytics-pro'); ?></th>
                                <th><?php _e('Status', 'seo-analytics-pro'); ?></th>
                            </tr>
                        </thead>
                        <tbody id="specs-list">
                            <tr>
                                <td colspan="5" class="sap-empty-state">
                                    <?php _e('No content specifications available. Create specs from keyword analysis first.', 'seo-analytics-pro'); ?>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="sap-form-section">
                    <h3><?php _e('Generation Settings', 'seo-analytics-pro'); ?></h3>

                    <div class="sap-form-row">
                        <label for="gen-post-status"><?php _e('Post Status', 'seo-analytics-pro'); ?></label>
                        <select id="gen-post-status" name="post_status">
                            <option value="draft"><?php _e('Draft', 'seo-analytics-pro'); ?></option>
                            <option value="pending"><?php _e('Pending Review', 'seo-analytics-pro'); ?></option>
                            <option value="publish"><?php _e('Publish', 'seo-analytics-pro'); ?></option>
                        </select>
                    </div>

                    <div class="sap-form-row">
                        <label for="gen-category"><?php _e('Category', 'seo-analytics-pro'); ?></label>
                        <?php
                        wp_dropdown_categories(array(
                            'id' => 'gen-category',
                            'name' => 'category',
                            'show_option_none' => __('Select category', 'seo-analytics-pro'),
                            'option_none_value' => '',
                            'hide_empty' => false
                        ));
                        ?>
                    </div>

                    <div class="sap-form-row">
                        <label>
                            <input type="checkbox" name="schedule_generation" value="1">
                            <?php _e('Schedule generation (add to queue)', 'seo-analytics-pro'); ?>
                        </label>
                    </div>
                </div>

                <div class="sap-form-actions">
                    <button type="button" class="button button-primary" id="generate-articles">
                        <?php _e('Generate Selected Articles', 'seo-analytics-pro'); ?>
                    </button>
                    <button type="button" class="button" id="add-to-queue">
                        <?php _e('Add to Queue', 'seo-analytics-pro'); ?>
                    </button>
                </div>
            </div>
        </div>

        <div class="sap-section">
            <h2><?php _e('Quick Article Generation', 'seo-analytics-pro'); ?></h2>
            <p class="description"><?php _e('Generate a single article without a technical specification.', 'seo-analytics-pro'); ?></p>

            <form id="quick-article-form" class="sap-form">
                <div class="sap-form-row">
                    <label for="quick-title"><?php _e('Article Title', 'seo-analytics-pro'); ?> *</label>
                    <input type="text" id="quick-title" name="title" required>
                </div>

                <div class="sap-form-row">
                    <label for="quick-keywords"><?php _e('Keywords (comma separated)', 'seo-analytics-pro'); ?> *</label>
                    <input type="text" id="quick-keywords" name="keywords" placeholder="keyword 1, keyword 2, keyword 3" required>
                </div>

                <div class="sap-form-row">
                    <label for="quick-length"><?php _e('Target Length (words)', 'seo-analytics-pro'); ?></label>
                    <input type="number" id="quick-length" name="length" value="2000" min="500" max="10000">
                </div>

                <div class="sap-form-row">
                    <label for="quick-instructions"><?php _e('Additional Instructions', 'seo-analytics-pro'); ?></label>
                    <textarea id="quick-instructions" name="instructions" rows="4" placeholder="<?php _e('Any specific requirements for the article...', 'seo-analytics-pro'); ?>"></textarea>
                </div>

                <button type="submit" class="button button-primary">
                    <?php _e('Generate Article', 'seo-analytics-pro'); ?>
                </button>
            </form>
        </div>
        <?php
    }

    /**
     * Products Generator Tab
     */
    private function display_generator_products() {
        ?>
        <div class="sap-section">
            <h2><?php _e('Generate Product Descriptions', 'seo-analytics-pro'); ?></h2>

            <?php if (!class_exists('WooCommerce')): ?>
            <div class="notice notice-warning">
                <p><?php _e('WooCommerce is not installed. Product generation requires WooCommerce.', 'seo-analytics-pro'); ?></p>
            </div>
            <?php else: ?>

            <div class="sap-form-section">
                <h3><?php _e('Bulk Product Generation', 'seo-analytics-pro'); ?></h3>
                <p class="description"><?php _e('Generate descriptions for multiple products at once.', 'seo-analytics-pro'); ?></p>

                <div class="sap-form-row">
                    <label for="product-category"><?php _e('Product Category', 'seo-analytics-pro'); ?></label>
                    <?php
                    wp_dropdown_categories(array(
                        'id' => 'product-category',
                        'name' => 'product_category',
                        'taxonomy' => 'product_cat',
                        'show_option_none' => __('All categories', 'seo-analytics-pro'),
                        'option_none_value' => '',
                        'hide_empty' => false
                    ));
                    ?>
                </div>

                <div class="sap-form-row">
                    <label>
                        <input type="checkbox" name="only_empty" value="1" checked>
                        <?php _e('Only products without description', 'seo-analytics-pro'); ?>
                    </label>
                </div>

                <button type="button" class="button button-primary" id="generate-product-descriptions">
                    <?php _e('Generate Product Descriptions', 'seo-analytics-pro'); ?>
                </button>
            </div>

            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Categories Generator Tab
     */
    private function display_generator_categories() {
        ?>
        <div class="sap-section">
            <h2><?php _e('Generate Category Descriptions', 'seo-analytics-pro'); ?></h2>

            <div class="sap-form-section">
                <h3><?php _e('Select Categories', 'seo-analytics-pro'); ?></h3>

                <div class="sap-form-row">
                    <label for="category-taxonomy"><?php _e('Taxonomy', 'seo-analytics-pro'); ?></label>
                    <select id="category-taxonomy" name="taxonomy">
                        <option value="category"><?php _e('Post Categories', 'seo-analytics-pro'); ?></option>
                        <?php if (class_exists('WooCommerce')): ?>
                        <option value="product_cat"><?php _e('Product Categories', 'seo-analytics-pro'); ?></option>
                        <?php endif; ?>
                    </select>
                </div>

                <div class="sap-form-row">
                    <label>
                        <input type="checkbox" name="only_empty_cat" value="1" checked>
                        <?php _e('Only categories without description', 'seo-analytics-pro'); ?>
                    </label>
                </div>

                <button type="button" class="button button-primary" id="generate-category-descriptions">
                    <?php _e('Generate Category Descriptions', 'seo-analytics-pro'); ?>
                </button>
            </div>
        </div>
        <?php
    }

    /**
     * Pages Generator Tab
     */
    private function display_generator_pages() {
        ?>
        <div class="sap-section">
            <h2><?php _e('Generate Page Content', 'seo-analytics-pro'); ?></h2>

            <form id="generate-page-form" class="sap-form sap-form-large">
                <div class="sap-form-row">
                    <label for="page-title"><?php _e('Page Title', 'seo-analytics-pro'); ?> *</label>
                    <input type="text" id="page-title" name="title" required>
                </div>

                <div class="sap-form-row">
                    <label for="page-type"><?php _e('Page Type', 'seo-analytics-pro'); ?></label>
                    <select id="page-type" name="page_type">
                        <option value="about"><?php _e('About Us', 'seo-analytics-pro'); ?></option>
                        <option value="services"><?php _e('Services', 'seo-analytics-pro'); ?></option>
                        <option value="contact"><?php _e('Contact', 'seo-analytics-pro'); ?></option>
                        <option value="faq"><?php _e('FAQ', 'seo-analytics-pro'); ?></option>
                        <option value="landing"><?php _e('Landing Page', 'seo-analytics-pro'); ?></option>
                        <option value="custom"><?php _e('Custom', 'seo-analytics-pro'); ?></option>
                    </select>
                </div>

                <div class="sap-form-row">
                    <label for="page-keywords"><?php _e('Keywords', 'seo-analytics-pro'); ?></label>
                    <input type="text" id="page-keywords" name="keywords" placeholder="keyword 1, keyword 2">
                </div>

                <div class="sap-form-row">
                    <label for="page-instructions"><?php _e('Content Requirements', 'seo-analytics-pro'); ?></label>
                    <textarea id="page-instructions" name="instructions" rows="4"></textarea>
                </div>

                <button type="submit" class="button button-primary">
                    <?php _e('Generate Page', 'seo-analytics-pro'); ?>
                </button>
            </form>
        </div>
        <?php
    }

    /**
     * Generation Queue Page
     */
    public function display_queue_page() {
        ?>
        <div class="wrap sap-wrap">
            <h1><?php _e('Generation Queue', 'seo-analytics-pro'); ?></h1>

            <div class="sap-toolbar">
                <div class="sap-filters">
                    <select id="queue-status-filter">
                        <option value=""><?php _e('All Statuses', 'seo-analytics-pro'); ?></option>
                        <option value="pending"><?php _e('Pending', 'seo-analytics-pro'); ?></option>
                        <option value="processing"><?php _e('Processing', 'seo-analytics-pro'); ?></option>
                        <option value="completed"><?php _e('Completed', 'seo-analytics-pro'); ?></option>
                        <option value="failed"><?php _e('Failed', 'seo-analytics-pro'); ?></option>
                    </select>
                    <button class="button"><?php _e('Filter', 'seo-analytics-pro'); ?></button>
                </div>
                <div class="sap-actions">
                    <button class="button" id="process-queue"><?php _e('Process Queue Now', 'seo-analytics-pro'); ?></button>
                    <button class="button" id="clear-completed"><?php _e('Clear Completed', 'seo-analytics-pro'); ?></button>
                </div>
            </div>

            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <td class="check-column"><input type="checkbox"></td>
                        <th><?php _e('Title', 'seo-analytics-pro'); ?></th>
                        <th><?php _e('Type', 'seo-analytics-pro'); ?></th>
                        <th><?php _e('Status', 'seo-analytics-pro'); ?></th>
                        <th><?php _e('Scheduled', 'seo-analytics-pro'); ?></th>
                        <th><?php _e('Actions', 'seo-analytics-pro'); ?></th>
                    </tr>
                </thead>
                <tbody id="queue-list">
                    <tr>
                        <td colspan="6" class="sap-empty-state">
                            <?php _e('No items in the generation queue.', 'seo-analytics-pro'); ?>
                        </td>
                    </tr>
                </tbody>
            </table>

            <div class="sap-section" style="margin-top: 20px;">
                <h2><?php _e('Schedule Settings', 'seo-analytics-pro'); ?></h2>

                <div class="sap-form-row">
                    <label>
                        <input type="checkbox" name="enable_scheduled" value="1">
                        <?php _e('Enable scheduled generation', 'seo-analytics-pro'); ?>
                    </label>
                    <p class="description"><?php _e('Automatically process queue items at scheduled times.', 'seo-analytics-pro'); ?></p>
                </div>

                <div class="sap-form-row">
                    <label for="generation-interval"><?php _e('Generation Interval', 'seo-analytics-pro'); ?></label>
                    <select id="generation-interval" name="interval">
                        <option value="hourly"><?php _e('Every Hour', 'seo-analytics-pro'); ?></option>
                        <option value="twicedaily"><?php _e('Twice Daily', 'seo-analytics-pro'); ?></option>
                        <option value="daily"><?php _e('Daily', 'seo-analytics-pro'); ?></option>
                    </select>
                </div>

                <div class="sap-form-row">
                    <label for="items-per-run"><?php _e('Items per Run', 'seo-analytics-pro'); ?></label>
                    <input type="number" id="items-per-run" name="items_per_run" value="5" min="1" max="20">
                    <p class="description"><?php _e('Number of items to generate in each scheduled run.', 'seo-analytics-pro'); ?></p>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * AJAX Handlers
     */
    public function ajax_analyze_competitor() {
        check_ajax_referer('sap_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'seo-analytics-pro')));
        }

        $competitor_url = sanitize_url($_POST['url'] ?? '');

        if (empty($competitor_url)) {
            wp_send_json_error(array('message' => __('URL is required', 'seo-analytics-pro')));
        }

        // TODO: Implement actual competitor analysis
        wp_send_json_success(array(
            'message' => __('Competitor analysis started', 'seo-analytics-pro'),
            'url' => $competitor_url
        ));
    }

    public function ajax_analyze_keyword() {
        check_ajax_referer('sap_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'seo-analytics-pro')));
        }

        $keyword = sanitize_text_field($_POST['keyword'] ?? '');

        if (empty($keyword)) {
            wp_send_json_error(array('message' => __('Keyword is required', 'seo-analytics-pro')));
        }

        // Get API instances
        $claude_ai = new SAP_Claude_AI();
        $serp_api = new SAP_SERP();

        // Check if APIs are configured
        if (!$claude_ai->is_configured()) {
            wp_send_json_error(array('message' => __('Claude AI API key not configured', 'seo-analytics-pro')));
        }

        if (!$serp_api->is_configured()) {
            wp_send_json_error(array('message' => __('SERP API key not configured', 'seo-analytics-pro')));
        }

        // Fetch SERP results
        $serp_results = $serp_api->fetch_results($keyword);

        if (is_wp_error($serp_results)) {
            wp_send_json_error(array('message' => $serp_results->get_error_message()));
        }

        // Analyze with Claude AI
        $analysis = $claude_ai->analyze_keyword($keyword, $serp_results);

        if (is_wp_error($analysis)) {
            wp_send_json_error(array('message' => $analysis->get_error_message()));
        }

        wp_send_json_success(array(
            'keyword' => $keyword,
            'serp' => $serp_results,
            'analysis' => $analysis
        ));
    }

    public function ajax_generate_content_spec() {
        check_ajax_referer('sap_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'seo-analytics-pro')));
        }

        // TODO: Implement content spec generation
        wp_send_json_success(array('message' => __('Content specification generated', 'seo-analytics-pro')));
    }

    public function ajax_import_keywords() {
        check_ajax_referer('sap_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'seo-analytics-pro')));
        }

        // TODO: Implement keyword import
        wp_send_json_success(array('message' => __('Keywords imported', 'seo-analytics-pro')));
    }

    /**
     * Register metaboxes for content generation
     */
    public function register_metaboxes() {
        // Post metabox
        add_meta_box(
            'sap_content_generator',
            __('SEO Content Generator', 'seo-analytics-pro'),
            array($this, 'render_post_metabox'),
            'post',
            'side',
            'high'
        );

        // Page metabox
        add_meta_box(
            'sap_content_generator',
            __('SEO Content Generator', 'seo-analytics-pro'),
            array($this, 'render_page_metabox'),
            'page',
            'side',
            'high'
        );

        // WooCommerce product metabox
        if (class_exists('WooCommerce')) {
            add_meta_box(
                'sap_content_generator',
                __('SEO Content Generator', 'seo-analytics-pro'),
                array($this, 'render_product_metabox'),
                'product',
                'side',
                'high'
            );
        }
    }

    /**
     * Register term meta boxes (for categories)
     */
    public function register_term_metaboxes() {
        // Post categories
        add_action('category_edit_form', array($this, 'render_category_metabox'), 10, 2);
        add_action('category_add_form_fields', array($this, 'render_category_add_metabox'));

        // WooCommerce product categories
        if (class_exists('WooCommerce')) {
            add_action('product_cat_edit_form', array($this, 'render_product_category_metabox'), 10, 2);
            add_action('product_cat_add_form_fields', array($this, 'render_product_category_add_metabox'));
        }
    }

    /**
     * Render metabox for posts
     */
    public function render_post_metabox($post) {
        wp_nonce_field('sap_metabox_nonce', 'sap_metabox_nonce');
        ?>
        <div class="sap-metabox">
            <div class="sap-metabox-section">
                <label for="sap-post-keywords">
                    <strong><?php _e('Keywords', 'seo-analytics-pro'); ?></strong>
                </label>
                <input type="text"
                       id="sap-post-keywords"
                       name="sap_keywords"
                       class="widefat"
                       placeholder="<?php _e('keyword 1, keyword 2', 'seo-analytics-pro'); ?>"
                       value="<?php echo esc_attr(get_post_meta($post->ID, '_sap_keywords', true)); ?>">
            </div>

            <div class="sap-metabox-section">
                <label for="sap-post-length">
                    <strong><?php _e('Target Length', 'seo-analytics-pro'); ?></strong>
                </label>
                <input type="number"
                       id="sap-post-length"
                       name="sap_length"
                       class="widefat"
                       min="500"
                       max="10000"
                       value="<?php echo esc_attr(get_post_meta($post->ID, '_sap_length', true) ?: '2000'); ?>">
            </div>

            <div class="sap-metabox-section">
                <label for="sap-post-instructions">
                    <strong><?php _e('Instructions', 'seo-analytics-pro'); ?></strong>
                </label>
                <textarea id="sap-post-instructions"
                          name="sap_instructions"
                          class="widefat"
                          rows="3"
                          placeholder="<?php _e('Additional requirements...', 'seo-analytics-pro'); ?>"><?php echo esc_textarea(get_post_meta($post->ID, '_sap_instructions', true)); ?></textarea>
            </div>

            <div class="sap-metabox-section">
                <label>
                    <input type="checkbox" name="sap_replace_content" value="1">
                    <?php _e('Replace existing content', 'seo-analytics-pro'); ?>
                </label>
            </div>

            <div class="sap-metabox-actions">
                <button type="button"
                        class="button button-primary sap-generate-content"
                        data-post-id="<?php echo $post->ID; ?>"
                        data-content-type="article">
                    <?php _e('Generate Content', 'seo-analytics-pro'); ?>
                </button>
                <span class="spinner"></span>
            </div>

            <div class="sap-metabox-result" style="display:none;"></div>
        </div>

        <style>
            .sap-metabox-section { margin-bottom: 10px; }
            .sap-metabox-section label { display: block; margin-bottom: 5px; }
            .sap-metabox-actions { margin-top: 15px; }
            .sap-metabox-actions .spinner { float: none; margin: 0 5px; }
            .sap-metabox-result { margin-top: 10px; padding: 10px; background: #f0f0f0; border-radius: 3px; }
            .sap-metabox-result.success { background: #d4edda; color: #155724; }
            .sap-metabox-result.error { background: #f8d7da; color: #721c24; }
        </style>
        <?php
    }

    /**
     * Render metabox for pages
     */
    public function render_page_metabox($post) {
        wp_nonce_field('sap_metabox_nonce', 'sap_metabox_nonce');
        ?>
        <div class="sap-metabox">
            <div class="sap-metabox-section">
                <label for="sap-page-type">
                    <strong><?php _e('Page Type', 'seo-analytics-pro'); ?></strong>
                </label>
                <select id="sap-page-type" name="sap_page_type" class="widefat">
                    <option value="about"><?php _e('About Us', 'seo-analytics-pro'); ?></option>
                    <option value="services"><?php _e('Services', 'seo-analytics-pro'); ?></option>
                    <option value="contact"><?php _e('Contact', 'seo-analytics-pro'); ?></option>
                    <option value="faq"><?php _e('FAQ', 'seo-analytics-pro'); ?></option>
                    <option value="landing"><?php _e('Landing Page', 'seo-analytics-pro'); ?></option>
                    <option value="custom"><?php _e('Custom', 'seo-analytics-pro'); ?></option>
                </select>
            </div>

            <div class="sap-metabox-section">
                <label for="sap-page-keywords">
                    <strong><?php _e('Keywords', 'seo-analytics-pro'); ?></strong>
                </label>
                <input type="text"
                       id="sap-page-keywords"
                       name="sap_keywords"
                       class="widefat"
                       placeholder="<?php _e('keyword 1, keyword 2', 'seo-analytics-pro'); ?>"
                       value="<?php echo esc_attr(get_post_meta($post->ID, '_sap_keywords', true)); ?>">
            </div>

            <div class="sap-metabox-section">
                <label for="sap-page-instructions">
                    <strong><?php _e('Content Requirements', 'seo-analytics-pro'); ?></strong>
                </label>
                <textarea id="sap-page-instructions"
                          name="sap_instructions"
                          class="widefat"
                          rows="3"
                          placeholder="<?php _e('Specific requirements for this page...', 'seo-analytics-pro'); ?>"><?php echo esc_textarea(get_post_meta($post->ID, '_sap_instructions', true)); ?></textarea>
            </div>

            <div class="sap-metabox-section">
                <label>
                    <input type="checkbox" name="sap_replace_content" value="1">
                    <?php _e('Replace existing content', 'seo-analytics-pro'); ?>
                </label>
            </div>

            <div class="sap-metabox-actions">
                <button type="button"
                        class="button button-primary sap-generate-content"
                        data-post-id="<?php echo $post->ID; ?>"
                        data-content-type="page">
                    <?php _e('Generate Content', 'seo-analytics-pro'); ?>
                </button>
                <span class="spinner"></span>
            </div>

            <div class="sap-metabox-result" style="display:none;"></div>
        </div>
        <?php
    }

    /**
     * Render metabox for WooCommerce products
     */
    public function render_product_metabox($post) {
        wp_nonce_field('sap_metabox_nonce', 'sap_metabox_nonce');
        ?>
        <div class="sap-metabox">
            <div class="sap-metabox-section">
                <label for="sap-product-keywords">
                    <strong><?php _e('Product Keywords', 'seo-analytics-pro'); ?></strong>
                </label>
                <input type="text"
                       id="sap-product-keywords"
                       name="sap_keywords"
                       class="widefat"
                       placeholder="<?php _e('keyword 1, keyword 2', 'seo-analytics-pro'); ?>"
                       value="<?php echo esc_attr(get_post_meta($post->ID, '_sap_keywords', true)); ?>">
            </div>

            <div class="sap-metabox-section">
                <label for="sap-product-features">
                    <strong><?php _e('Key Features', 'seo-analytics-pro'); ?></strong>
                </label>
                <textarea id="sap-product-features"
                          name="sap_features"
                          class="widefat"
                          rows="3"
                          placeholder="<?php _e('Main product features to highlight...', 'seo-analytics-pro'); ?>"><?php echo esc_textarea(get_post_meta($post->ID, '_sap_features', true)); ?></textarea>
            </div>

            <div class="sap-metabox-section">
                <label>
                    <input type="checkbox" name="sap_generate_short" value="1" checked>
                    <?php _e('Generate short description', 'seo-analytics-pro'); ?>
                </label>
            </div>

            <div class="sap-metabox-section">
                <label>
                    <input type="checkbox" name="sap_replace_content" value="1">
                    <?php _e('Replace existing descriptions', 'seo-analytics-pro'); ?>
                </label>
            </div>

            <div class="sap-metabox-actions">
                <button type="button"
                        class="button button-primary sap-generate-content"
                        data-post-id="<?php echo $post->ID; ?>"
                        data-content-type="product">
                    <?php _e('Generate Description', 'seo-analytics-pro'); ?>
                </button>
                <span class="spinner"></span>
            </div>

            <div class="sap-metabox-result" style="display:none;"></div>
        </div>
        <?php
    }

    /**
     * Render metabox for category edit form
     */
    public function render_category_metabox($term, $taxonomy) {
        wp_nonce_field('sap_term_metabox_nonce', 'sap_term_metabox_nonce');
        ?>
        <h2><?php _e('SEO Content Generator', 'seo-analytics-pro'); ?></h2>
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="sap-cat-keywords"><?php _e('Keywords', 'seo-analytics-pro'); ?></label>
                </th>
                <td>
                    <input type="text"
                           id="sap-cat-keywords"
                           name="sap_keywords"
                           class="regular-text"
                           placeholder="<?php _e('keyword 1, keyword 2', 'seo-analytics-pro'); ?>"
                           value="<?php echo esc_attr(get_term_meta($term->term_id, '_sap_keywords', true)); ?>">
                    <p class="description"><?php _e('Keywords to target in the category description.', 'seo-analytics-pro'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="sap-cat-instructions"><?php _e('Instructions', 'seo-analytics-pro'); ?></label>
                </th>
                <td>
                    <textarea id="sap-cat-instructions"
                              name="sap_instructions"
                              rows="3"
                              class="large-text"
                              placeholder="<?php _e('Additional requirements...', 'seo-analytics-pro'); ?>"><?php echo esc_textarea(get_term_meta($term->term_id, '_sap_instructions', true)); ?></textarea>
                </td>
            </tr>
            <tr>
                <th scope="row"></th>
                <td>
                    <label>
                        <input type="checkbox" name="sap_replace_description" value="1">
                        <?php _e('Replace existing description', 'seo-analytics-pro'); ?>
                    </label>
                </td>
            </tr>
            <tr>
                <th scope="row"></th>
                <td>
                    <button type="button"
                            class="button button-primary sap-generate-term-content"
                            data-term-id="<?php echo $term->term_id; ?>"
                            data-taxonomy="<?php echo esc_attr($taxonomy); ?>">
                        <?php _e('Generate Description', 'seo-analytics-pro'); ?>
                    </button>
                    <span class="spinner" style="float: none;"></span>
                    <div class="sap-term-result" style="display:none; margin-top: 10px;"></div>
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * Render metabox for category add form
     */
    public function render_category_add_metabox() {
        ?>
        <div class="form-field">
            <label for="sap-new-cat-keywords"><?php _e('SEO Keywords', 'seo-analytics-pro'); ?></label>
            <input type="text" id="sap-new-cat-keywords" name="sap_keywords" placeholder="<?php _e('keyword 1, keyword 2', 'seo-analytics-pro'); ?>">
            <p><?php _e('Keywords for AI content generation.', 'seo-analytics-pro'); ?></p>
        </div>
        <?php
    }

    /**
     * Render metabox for WooCommerce product category edit form
     */
    public function render_product_category_metabox($term, $taxonomy) {
        $this->render_category_metabox($term, $taxonomy);
    }

    /**
     * Render metabox for WooCommerce product category add form
     */
    public function render_product_category_add_metabox() {
        $this->render_category_add_metabox();
    }

    /**
     * Save metabox data for posts/pages/products
     */
    public function save_metabox_data($post_id) {
        // Verify nonce
        if (!isset($_POST['sap_metabox_nonce']) || !wp_verify_nonce($_POST['sap_metabox_nonce'], 'sap_metabox_nonce')) {
            return;
        }

        // Check autosave
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        // Check permissions
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        // Save keywords
        if (isset($_POST['sap_keywords'])) {
            update_post_meta($post_id, '_sap_keywords', sanitize_text_field($_POST['sap_keywords']));
        }

        // Save length
        if (isset($_POST['sap_length'])) {
            update_post_meta($post_id, '_sap_length', absint($_POST['sap_length']));
        }

        // Save instructions
        if (isset($_POST['sap_instructions'])) {
            update_post_meta($post_id, '_sap_instructions', sanitize_textarea_field($_POST['sap_instructions']));
        }

        // Save features (for products)
        if (isset($_POST['sap_features'])) {
            update_post_meta($post_id, '_sap_features', sanitize_textarea_field($_POST['sap_features']));
        }

        // Save page type
        if (isset($_POST['sap_page_type'])) {
            update_post_meta($post_id, '_sap_page_type', sanitize_text_field($_POST['sap_page_type']));
        }
    }

    /**
     * Save term meta data
     */
    public function save_term_meta($term_id, $tt_id, $taxonomy) {
        // Verify nonce for edit form
        if (isset($_POST['sap_term_metabox_nonce']) && !wp_verify_nonce($_POST['sap_term_metabox_nonce'], 'sap_term_metabox_nonce')) {
            return;
        }

        // Save keywords
        if (isset($_POST['sap_keywords'])) {
            update_term_meta($term_id, '_sap_keywords', sanitize_text_field($_POST['sap_keywords']));
        }

        // Save instructions
        if (isset($_POST['sap_instructions'])) {
            update_term_meta($term_id, '_sap_instructions', sanitize_textarea_field($_POST['sap_instructions']));
        }
    }

    /**
     * AJAX handler for generating content from metabox
     */
    public function ajax_generate_metabox_content() {
        check_ajax_referer('sap_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => __('Permission denied', 'seo-analytics-pro')));
        }

        $post_id = absint($_POST['post_id'] ?? 0);
        $content_type = sanitize_text_field($_POST['content_type'] ?? 'article');
        $keywords = sanitize_text_field($_POST['keywords'] ?? '');
        $length = absint($_POST['length'] ?? 2000);
        $instructions = sanitize_textarea_field($_POST['instructions'] ?? '');
        $replace = !empty($_POST['replace']);

        if (empty($post_id)) {
            wp_send_json_error(array('message' => __('Post ID is required', 'seo-analytics-pro')));
        }

        $post = get_post($post_id);
        if (!$post) {
            wp_send_json_error(array('message' => __('Post not found', 'seo-analytics-pro')));
        }

        // Get content generator
        $generator = new SAP_Content_Generator();

        // Prepare spec data
        $spec = array(
            'title' => $post->post_title,
            'primary_keyword' => !empty($keywords) ? explode(',', $keywords)[0] : $post->post_title,
            'secondary_keywords' => array_map('trim', explode(',', $keywords)),
            'word_count' => $length,
            'additional_instructions' => $instructions,
            'content_type' => $content_type
        );

        // Generate content based on type
        $result = null;
        switch ($content_type) {
            case 'product':
                $features = sanitize_textarea_field($_POST['features'] ?? '');
                $spec['features'] = $features;
                $spec['generate_short'] = !empty($_POST['generate_short']);
                $result = $generator->generate_product($spec, $replace ? $post_id : 0);
                break;

            case 'page':
                $page_type = sanitize_text_field($_POST['page_type'] ?? 'custom');
                $spec['page_type'] = $page_type;
                $result = $generator->generate_page($spec, $replace ? $post_id : 0);
                break;

            default: // article
                $result = $generator->generate_article($spec, $replace ? $post_id : 0);
        }

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        // Update post content if requested
        if ($replace && !empty($result['content'])) {
            wp_update_post(array(
                'ID' => $post_id,
                'post_content' => $result['content']
            ));

            // Update short description for products
            if ($content_type === 'product' && !empty($result['short_description'])) {
                update_post_meta($post_id, '_product_short_description', $result['short_description']);
            }
        }

        wp_send_json_success(array(
            'message' => __('Content generated successfully!', 'seo-analytics-pro'),
            'content' => $result['content'] ?? '',
            'post_id' => $result['post_id'] ?? $post_id
        ));
    }

    /**
     * AJAX handler for generating term content from metabox
     */
    public function ajax_generate_term_content() {
        check_ajax_referer('sap_nonce', 'nonce');

        if (!current_user_can('manage_categories')) {
            wp_send_json_error(array('message' => __('Permission denied', 'seo-analytics-pro')));
        }

        $term_id = absint($_POST['term_id'] ?? 0);
        $taxonomy = sanitize_text_field($_POST['taxonomy'] ?? 'category');
        $keywords = sanitize_text_field($_POST['keywords'] ?? '');
        $instructions = sanitize_textarea_field($_POST['instructions'] ?? '');
        $replace = !empty($_POST['replace']);

        if (empty($term_id)) {
            wp_send_json_error(array('message' => __('Term ID is required', 'seo-analytics-pro')));
        }

        $term = get_term($term_id, $taxonomy);
        if (!$term || is_wp_error($term)) {
            wp_send_json_error(array('message' => __('Term not found', 'seo-analytics-pro')));
        }

        // Get content generator
        $generator = new SAP_Content_Generator();

        // Prepare spec data
        $spec = array(
            'title' => $term->name,
            'primary_keyword' => !empty($keywords) ? explode(',', $keywords)[0] : $term->name,
            'secondary_keywords' => array_map('trim', explode(',', $keywords)),
            'additional_instructions' => $instructions,
            'taxonomy' => $taxonomy
        );

        // Generate category content
        $result = $generator->generate_category($spec, $replace ? $term_id : 0);

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        // Update term description if requested
        if ($replace && !empty($result['content'])) {
            wp_update_term($term_id, $taxonomy, array(
                'description' => $result['content']
            ));
        }

        wp_send_json_success(array(
            'message' => __('Description generated successfully!', 'seo-analytics-pro'),
            'content' => $result['content'] ?? '',
            'term_id' => $term_id
        ));
    }
}
