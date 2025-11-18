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
                        </select>
                    </div>

                    <div class="sap-form-row">
                        <label for="serp-api-key">
                            <?php _e('SERP API Key', 'seo-analytics-pro'); ?> *
                            <span class="sap-help">
                                <a href="https://serpapi.com/" target="_blank"><?php _e('Get API Key', 'seo-analytics-pro'); ?></a>
                            </span>
                        </label>
                        <input type="password"
                               id="serp-api-key"
                               name="sap_settings[serp_api_key]"
                               value="<?php echo esc_attr($settings['serp_api_key'] ?? ''); ?>"
                               class="regular-text">
                        <p class="description"><?php _e('Required for fetching search engine results.', 'seo-analytics-pro'); ?></p>
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
                            <option value="uk" <?php selected($settings['serp_language'] ?? 'uk', 'uk'); ?>>Українська (Ukrainian)</option>
                            <option value="ru" <?php selected($settings['serp_language'] ?? '', 'ru'); ?>>Русский (Russian)</option>
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
}
