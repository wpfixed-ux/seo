<?php
/**
 * SERP API Integration
 *
 * Handles fetching search engine results page data from various SERP API providers.
 *
 * @package    SEOAnalyticsPro
 * @subpackage SEOAnalyticsPro/includes/api
 */

class SAP_SERP {

    /**
     * API provider (serpapi, dataforseo, custom)
     *
     * @var string
     */
    private $provider;

    /**
     * API key for authentication
     *
     * @var string
     */
    private $api_key;

    /**
     * API endpoints for different providers
     *
     * @var array
     */
    private $endpoints = array(
        'serpapi' => 'https://serpapi.com/search',
        'dataforseo' => 'https://api.dataforseo.com/v3/serp/google/organic/live/advanced',
    );

    /**
     * Default location
     *
     * @var string
     */
    private $location;

    /**
     * Default language
     *
     * @var string
     */
    private $language;

    /**
     * Google domain
     *
     * @var string
     */
    private $google_domain;

    /**
     * Constructor
     */
    public function __construct() {
        $settings = get_option('sap_settings', array());
        $this->provider = $settings['serp_api_provider'] ?? 'serpapi';
        $this->api_key = $settings['serp_api_key'] ?? '';
        $this->location = $settings['serp_location'] ?? 'Ukraine';
        $this->language = $settings['serp_language'] ?? 'ru';
        $this->google_domain = $settings['serp_google_domain'] ?? 'google.com.ua';
    }

    /**
     * Fetch SERP results for a keyword
     *
     * @param string $keyword The keyword to search
     * @param array $options Search options (location, language, num_results, etc.)
     * @return array|WP_Error SERP results or error
     */
    public function fetch_results($keyword, $options = array()) {
        if (empty($this->api_key)) {
            return new WP_Error('no_api_key', __('SERP API key is not configured.', 'seo-analytics-pro'));
        }

        $defaults = array(
            'location' => $this->location,
            'language' => $this->language,
            'google_domain' => $this->google_domain,
            'num_results' => 20,
            'device' => 'desktop'
        );

        $options = wp_parse_args($options, $defaults);

        switch ($this->provider) {
            case 'serpapi':
                return $this->fetch_serpapi($keyword, $options);

            case 'dataforseo':
                return $this->fetch_dataforseo($keyword, $options);

            default:
                return new WP_Error('invalid_provider', __('Invalid SERP API provider', 'seo-analytics-pro'));
        }
    }

    /**
     * Fetch results from SERPApi
     *
     * @param string $keyword The keyword
     * @param array $options Options
     * @return array|WP_Error Results or error
     */
    private function fetch_serpapi($keyword, $options) {
        $start_time = microtime(true);

        $params = array(
            'api_key' => $this->api_key,
            'q' => $keyword,
            'location' => $options['location'],
            'hl' => $options['language'],
            'gl' => $this->get_country_code($options['location']),
            'google_domain' => $options['google_domain'],
            'num' => $options['num_results'],
            'device' => $options['device']
        );

        $url = add_query_arg($params, $this->endpoints['serpapi']);

        $response = wp_remote_get($url, array(
            'timeout' => 30
        ));

        $execution_time = microtime(true) - $start_time;

        if (is_wp_error($response)) {
            $this->log_api_call('serpapi', $url, $params, null, null, $execution_time);
            return $response;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        $this->log_api_call('serpapi', $url, $params, $data, $status_code, $execution_time);

        if ($status_code !== 200) {
            $error_message = $data['error'] ?? 'Unknown error';
            return new WP_Error('api_error', sprintf(__('SERPApi error: %s', 'seo-analytics-pro'), $error_message));
        }

        return $this->normalize_serpapi_results($data, $keyword);
    }

    /**
     * Fetch results from DataForSEO
     *
     * @param string $keyword The keyword
     * @param array $options Options
     * @return array|WP_Error Results or error
     */
    private function fetch_dataforseo($keyword, $options) {
        $start_time = microtime(true);

        $post_data = array(
            array(
                'keyword' => $keyword,
                'location_name' => $options['location'],
                'language_code' => $options['language'],
                'depth' => $options['num_results'],
                'device' => $options['device']
            )
        );

        $args = array(
            'headers' => array(
                'Content-Type' => 'application/json',
                'Authorization' => 'Basic ' . base64_encode($this->api_key)
            ),
            'body' => wp_json_encode($post_data),
            'timeout' => 30
        );

        $response = wp_remote_post($this->endpoints['dataforseo'], $args);

        $execution_time = microtime(true) - $start_time;

        if (is_wp_error($response)) {
            $this->log_api_call('dataforseo', $this->endpoints['dataforseo'], $post_data, null, null, $execution_time);
            return $response;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        $this->log_api_call('dataforseo', $this->endpoints['dataforseo'], $post_data, $data, $status_code, $execution_time);

        if ($status_code !== 200 || !empty($data['status_code']) && $data['status_code'] !== 20000) {
            $error_message = $data['status_message'] ?? 'Unknown error';
            return new WP_Error('api_error', sprintf(__('DataForSEO error: %s', 'seo-analytics-pro'), $error_message));
        }

        return $this->normalize_dataforseo_results($data, $keyword);
    }

    /**
     * Normalize SERPApi results to standard format
     *
     * @param array $data Raw API response
     * @param string $keyword The keyword
     * @return array Normalized results
     */
    private function normalize_serpapi_results($data, $keyword) {
        $normalized = array(
            'keyword' => $keyword,
            'total_results' => $data['search_information']['total_results'] ?? 0,
            'results' => array(),
            'related_keywords' => array(),
            'people_also_ask' => array(),
            'raw_data' => $data
        );

        // Normalize organic results
        if (!empty($data['organic_results'])) {
            foreach ($data['organic_results'] as $i => $result) {
                $normalized['results'][] = array(
                    'position' => $result['position'] ?? ($i + 1),
                    'title' => $result['title'] ?? '',
                    'url' => $result['link'] ?? '',
                    'domain' => $this->extract_domain($result['link'] ?? ''),
                    'description' => $result['snippet'] ?? '',
                    'displayed_url' => $result['displayed_link'] ?? ''
                );
            }
        }

        // Extract related searches
        if (!empty($data['related_searches'])) {
            foreach ($data['related_searches'] as $related) {
                $normalized['related_keywords'][] = $related['query'] ?? '';
            }
        }

        // Extract People Also Ask
        if (!empty($data['related_questions'])) {
            foreach ($data['related_questions'] as $question) {
                $normalized['people_also_ask'][] = array(
                    'question' => $question['question'] ?? '',
                    'answer' => $question['snippet'] ?? '',
                    'source' => $question['link'] ?? ''
                );
            }
        }

        return $normalized;
    }

    /**
     * Normalize DataForSEO results to standard format
     *
     * @param array $data Raw API response
     * @param string $keyword The keyword
     * @return array Normalized results
     */
    private function normalize_dataforseo_results($data, $keyword) {
        $normalized = array(
            'keyword' => $keyword,
            'total_results' => 0,
            'results' => array(),
            'related_keywords' => array(),
            'people_also_ask' => array(),
            'raw_data' => $data
        );

        if (empty($data['tasks'][0]['result'][0])) {
            return $normalized;
        }

        $result_data = $data['tasks'][0]['result'][0];
        $normalized['total_results'] = $result_data['total_count'] ?? 0;

        // Normalize organic results
        if (!empty($result_data['items'])) {
            foreach ($result_data['items'] as $item) {
                if ($item['type'] === 'organic') {
                    $normalized['results'][] = array(
                        'position' => $item['rank_absolute'] ?? 0,
                        'title' => $item['title'] ?? '',
                        'url' => $item['url'] ?? '',
                        'domain' => $item['domain'] ?? '',
                        'description' => $item['description'] ?? '',
                        'displayed_url' => $item['breadcrumb'] ?? ''
                    );
                } else if ($item['type'] === 'people_also_ask') {
                    if (!empty($item['items'])) {
                        foreach ($item['items'] as $paa) {
                            $normalized['people_also_ask'][] = array(
                                'question' => $paa['title'] ?? '',
                                'answer' => $paa['expanded_element']['description'] ?? '',
                                'source' => $paa['expanded_element']['url'] ?? ''
                            );
                        }
                    }
                } else if ($item['type'] === 'related_searches') {
                    if (!empty($item['items'])) {
                        foreach ($item['items'] as $related) {
                            $normalized['related_keywords'][] = $related['query'] ?? '';
                        }
                    }
                }
            }
        }

        return $normalized;
    }

    /**
     * Analyze SERP results for patterns and insights
     *
     * @param array $serp_results Normalized SERP results
     * @return array Analysis results
     */
    public function analyze_serp($serp_results) {
        $analysis = array(
            'average_title_length' => 0,
            'average_description_length' => 0,
            'common_domains' => array(),
            'content_types' => array(),
            'has_featured_snippet' => false,
            'has_people_also_ask' => !empty($serp_results['people_also_ask']),
            'top_10_domains' => array(),
            'domain_diversity' => 0
        );

        if (empty($serp_results['results'])) {
            return $analysis;
        }

        $results = $serp_results['results'];
        $total_results = count($results);
        $title_lengths = array();
        $desc_lengths = array();
        $domains = array();

        foreach ($results as $result) {
            // Calculate lengths
            $title_lengths[] = strlen($result['title']);
            $desc_lengths[] = strlen($result['description']);

            // Track domains
            $domain = $result['domain'];
            if (!isset($domains[$domain])) {
                $domains[$domain] = 0;
            }
            $domains[$domain]++;

            // Track top 10 domains
            if ($result['position'] <= 10) {
                $analysis['top_10_domains'][] = $domain;
            }
        }

        // Calculate averages
        $analysis['average_title_length'] = !empty($title_lengths) ? round(array_sum($title_lengths) / count($title_lengths)) : 0;
        $analysis['average_description_length'] = !empty($desc_lengths) ? round(array_sum($desc_lengths) / count($desc_lengths)) : 0;

        // Sort domains by frequency
        arsort($domains);
        $analysis['common_domains'] = array_slice($domains, 0, 5, true);

        // Calculate domain diversity (higher = more diverse)
        $analysis['domain_diversity'] = count($domains) / $total_results;

        return $analysis;
    }

    /**
     * Get keyword suggestions from autocomplete
     *
     * @param string $keyword Seed keyword
     * @return array|WP_Error Keyword suggestions or error
     */
    public function get_keyword_suggestions($keyword) {
        // This would integrate with Google Autocomplete API or similar
        // For now, return a placeholder
        return array(
            'suggestions' => array(),
            'message' => 'Keyword suggestions feature requires additional API integration'
        );
    }

    /**
     * Get country code from location name
     *
     * @param string $location Location name
     * @return string Country code
     */
    private function get_country_code($location) {
        $codes = array(
            'Ukraine' => 'ua',
            'United States' => 'us',
            'United Kingdom' => 'uk',
            'Germany' => 'de',
            'France' => 'fr',
            'Poland' => 'pl',
            'Russia' => 'ru',
            'Canada' => 'ca',
            'Australia' => 'au',
            'Spain' => 'es',
            'Italy' => 'it',
            'Netherlands' => 'nl',
            'Brazil' => 'br',
        );

        return $codes[$location] ?? 'ua';
    }

    /**
     * Extract domain from URL
     *
     * @param string $url Full URL
     * @return string Domain
     */
    private function extract_domain($url) {
        $parsed = parse_url($url);
        return $parsed['host'] ?? '';
    }

    /**
     * Log API call to database
     *
     * @param string $service_name Service name
     * @param string $endpoint API endpoint
     * @param array $request_data Request data
     * @param array $response_data Response data
     * @param int $status_code HTTP status code
     * @param float $execution_time Execution time in seconds
     */
    private function log_api_call($service_name, $endpoint, $request_data, $response_data, $status_code, $execution_time) {
        global $wpdb;

        $table = $wpdb->prefix . 'sap_api_logs';

        $wpdb->insert(
            $table,
            array(
                'service_name' => $service_name,
                'endpoint' => $endpoint,
                'request_data' => wp_json_encode($request_data),
                'response_data' => wp_json_encode($response_data),
                'status_code' => $status_code,
                'execution_time' => $execution_time,
                'tokens_used' => 0,
                'cost' => 0,
                'created_at' => current_time('mysql')
            ),
            array('%s', '%s', '%s', '%s', '%d', '%f', '%d', '%f', '%s')
        );
    }

    /**
     * Check if API key is configured
     *
     * @return bool
     */
    public function is_configured() {
        return !empty($this->api_key);
    }

    /**
     * Validate API key
     *
     * @return bool|WP_Error True if valid, WP_Error if invalid
     */
    public function validate_api_key() {
        if (empty($this->api_key)) {
            return new WP_Error('no_api_key', __('API key is required', 'seo-analytics-pro'));
        }

        // Send a simple test request
        $result = $this->fetch_results('test', array('num_results' => 1));

        if (is_wp_error($result)) {
            return $result;
        }

        return true;
    }
}
