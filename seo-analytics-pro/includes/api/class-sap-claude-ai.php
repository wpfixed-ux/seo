<?php
/**
 * Claude AI API Integration
 *
 * Handles all communication with the Anthropic Claude AI API for SEO analysis and content strategy generation.
 *
 * @package    SEOAnalyticsPro
 * @subpackage SEOAnalyticsPro/includes/api
 */

class SAP_Claude_AI {

    /**
     * API endpoint for Claude AI
     *
     * @var string
     */
    private $api_endpoint = 'https://api.anthropic.com/v1/messages';

    /**
     * API key for authentication
     *
     * @var string
     */
    private $api_key;

    /**
     * Default model to use
     *
     * @var string
     */
    private $model = 'claude-sonnet-4-5-20250929';

    /**
     * Maximum tokens for responses
     *
     * @var int
     */
    private $max_tokens = 4096;

    /**
     * Constructor
     */
    public function __construct() {
        $settings = get_option('sap_settings', array());
        $this->api_key = $settings['claude_ai_api_key'] ?? '';
    }

    /**
     * Analyze keyword competitiveness and opportunity
     *
     * @param string $keyword The keyword to analyze
     * @param array $serp_data SERP results data
     * @param array $competitors Competitor website data
     * @return array|WP_Error Analysis results or error
     */
    public function analyze_keyword($keyword, $serp_data, $competitors = array()) {
        if (empty($this->api_key)) {
            return new WP_Error('no_api_key', __('Claude AI API key is not configured.', 'seo-analytics-pro'));
        }

        $prompt = $this->build_keyword_analysis_prompt($keyword, $serp_data, $competitors);

        $response = $this->send_request($prompt, array(
            'system' => 'You are an expert SEO analyst. Analyze keyword data and provide structured JSON responses with detailed insights about keyword competitiveness, difficulty, and opportunity.'
        ));

        if (is_wp_error($response)) {
            return $response;
        }

        return $this->parse_keyword_analysis($response);
    }

    /**
     * Generate content strategy to outrank competitors
     *
     * @param string $keyword Target keyword
     * @param string $target_site User's website
     * @param array $competitor_data Competitor analysis data
     * @param array $content_analysis Top-ranking content analysis
     * @return array|WP_Error Strategy results or error
     */
    public function generate_content_strategy($keyword, $target_site, $competitor_data, $content_analysis) {
        if (empty($this->api_key)) {
            return new WP_Error('no_api_key', __('Claude AI API key is not configured.', 'seo-analytics-pro'));
        }

        $prompt = $this->build_content_strategy_prompt($keyword, $target_site, $competitor_data, $content_analysis);

        $response = $this->send_request($prompt, array(
            'system' => 'You are an expert content strategist. Create comprehensive, actionable content strategies with specific recommendations to outrank competitors in search results.'
        ));

        if (is_wp_error($response)) {
            return $response;
        }

        return $this->parse_content_strategy($response);
    }

    /**
     * Generate detailed technical specifications for content
     *
     * @param string $content_type Type of content (article, product, category, page)
     * @param string $keyword Target keyword
     * @param array $analysis Combined analysis data
     * @param array $serp SERP results
     * @return array|WP_Error Technical specs or error
     */
    public function generate_technical_spec($content_type, $keyword, $analysis, $serp) {
        if (empty($this->api_key)) {
            return new WP_Error('no_api_key', __('Claude AI API key is not configured.', 'seo-analytics-pro'));
        }

        $prompt = $this->build_technical_spec_prompt($content_type, $keyword, $analysis, $serp);

        $response = $this->send_request($prompt, array(
            'system' => 'You are an expert SEO content architect. Generate detailed, actionable technical specifications for content creation that will outperform competitors.'
        ));

        if (is_wp_error($response)) {
            return $response;
        }

        return $this->parse_technical_spec($response);
    }

    /**
     * Extract keywords from competitor websites
     *
     * @param string $competitor_url Competitor website URL
     * @param string $content Parsed website content
     * @param string $target_niche Target niche/industry
     * @return array|WP_Error Extracted keywords or error
     */
    public function extract_competitor_keywords($competitor_url, $content, $target_niche = '') {
        if (empty($this->api_key)) {
            return new WP_Error('no_api_key', __('Claude AI API key is not configured.', 'seo-analytics-pro'));
        }

        $prompt = "Analyze the following website content and extract the most important SEO keywords.\n\n";
        $prompt .= "Competitor URL: {$competitor_url}\n";
        if (!empty($target_niche)) {
            $prompt .= "Target Niche: {$target_niche}\n";
        }
        $prompt .= "\nWebsite Content:\n{$content}\n\n";
        $prompt .= "Extract:\n";
        $prompt .= "1. Primary keywords (main topics/products)\n";
        $prompt .= "2. Secondary keywords (supporting topics)\n";
        $prompt .= "3. Long-tail keywords (specific phrases)\n";
        $prompt .= "4. Branded keywords\n\n";
        $prompt .= "For each keyword, estimate its importance (High/Medium/Low) and frequency.\n";
        $prompt .= "Return results as JSON in this format:\n";
        $prompt .= json_encode(array(
            'primary_keywords' => array(
                array('keyword' => 'example', 'importance' => 'high', 'frequency' => 15)
            ),
            'secondary_keywords' => array(),
            'long_tail_keywords' => array(),
            'branded_keywords' => array()
        ), JSON_PRETTY_PRINT);

        $response = $this->send_request($prompt);

        if (is_wp_error($response)) {
            return $response;
        }

        return $this->parse_json_response($response);
    }

    /**
     * Build keyword analysis prompt
     *
     * @param string $keyword The keyword
     * @param array $serp_data SERP data
     * @param array $competitors Competitor data
     * @return string The formatted prompt
     */
    private function build_keyword_analysis_prompt($keyword, $serp_data, $competitors) {
        $prompt = "You are an expert SEO analyst. Analyze the following keyword data and provide:\n\n";
        $prompt .= "KEYWORD: {$keyword}\n";

        if (!empty($serp_data['search_volume'])) {
            $prompt .= "SEARCH VOLUME: " . $serp_data['search_volume'] . "\n";
        }

        if (!empty($serp_data['results'])) {
            $prompt .= "\nCURRENT TOP 10 RESULTS:\n";
            foreach (array_slice($serp_data['results'], 0, 10) as $i => $result) {
                $position = $i + 1;
                $prompt .= "{$position}. {$result['title']} - {$result['url']}\n";
                if (!empty($result['description'])) {
                    $prompt .= "   Description: {$result['description']}\n";
                }
            }
        }

        if (!empty($competitors)) {
            $prompt .= "\nCOMPETITOR WEBSITES:\n";
            foreach ($competitors as $comp) {
                $prompt .= "- {$comp['url']}";
                if (!empty($comp['domain_authority'])) {
                    $prompt .= " (DA: {$comp['domain_authority']})";
                }
                $prompt .= "\n";
            }
        }

        $prompt .= "\nPlease provide:\n";
        $prompt .= "1. Competition assessment (Low/Medium/High) with explanation\n";
        $prompt .= "2. Difficulty score (0-100) with reasoning\n";
        $prompt .= "3. Opportunity rating (0-10) and why\n";
        $prompt .= "4. Whether this keyword is worth targeting and why\n";
        $prompt .= "5. Recommended content type (article/product/category/page)\n";
        $prompt .= "6. Estimated effort required (hours/complexity)\n";
        $prompt .= "7. Key insights about ranking factors for this keyword\n";
        $prompt .= "8. Potential traffic estimate if ranked in top 3\n\n";
        $prompt .= "Format response as JSON with these fields:\n";
        $prompt .= json_encode(array(
            'competition' => 'Low/Medium/High',
            'competition_explanation' => '',
            'difficulty_score' => 0,
            'difficulty_reasoning' => '',
            'opportunity_rating' => 0,
            'opportunity_explanation' => '',
            'worth_targeting' => true,
            'targeting_reason' => '',
            'recommended_content_type' => 'article',
            'estimated_effort' => '',
            'key_insights' => array(),
            'traffic_potential' => ''
        ), JSON_PRETTY_PRINT);

        return $prompt;
    }

    /**
     * Build content strategy prompt
     *
     * @param string $keyword Target keyword
     * @param string $target_site User's website
     * @param array $competitor_data Competitor analysis
     * @param array $content_analysis Content analysis
     * @return string The formatted prompt
     */
    private function build_content_strategy_prompt($keyword, $target_site, $competitor_data, $content_analysis) {
        $prompt = "You are an expert content strategist. Create a comprehensive content strategy to outrank competitors for this keyword.\n\n";
        $prompt .= "TARGET KEYWORD: {$keyword}\n";
        $prompt .= "MY WEBSITE: {$target_site}\n\n";

        if (!empty($competitor_data)) {
            $prompt .= "COMPETITORS RANKING:\n";
            $prompt .= json_encode($competitor_data, JSON_PRETTY_PRINT) . "\n\n";
        }

        if (!empty($content_analysis)) {
            $prompt .= "TOP RANKING CONTENT ANALYSIS:\n";
            $prompt .= json_encode($content_analysis, JSON_PRETTY_PRINT) . "\n\n";
        }

        $prompt .= "Generate:\n";
        $prompt .= "1. Content strategy overview (2-3 paragraphs)\n";
        $prompt .= "2. Recommended content length (words)\n";
        $prompt .= "3. Content structure (headings, sections with word counts)\n";
        $prompt .= "4. Primary and secondary keywords to target\n";
        $prompt .= "5. LSI keywords to include naturally\n";
        $prompt .= "6. Internal linking strategy\n";
        $prompt .= "7. External authority sources to reference\n";
        $prompt .= "8. Unique angles to differentiate from competitors\n";
        $prompt .= "9. Content format recommendations (text, images, video, infographics)\n";
        $prompt .= "10. Specific weaknesses in competitor content to exploit\n";
        $prompt .= "11. Content freshness strategy (update frequency)\n";
        $prompt .= "12. User intent analysis and how to satisfy it\n\n";
        $prompt .= "Return as detailed JSON.";

        return $prompt;
    }

    /**
     * Build technical specification prompt
     *
     * @param string $content_type Content type
     * @param string $keyword Target keyword
     * @param array $analysis Analysis data
     * @param array $serp SERP data
     * @return string The formatted prompt
     */
    private function build_technical_spec_prompt($content_type, $keyword, $analysis, $serp) {
        $prompt = "Create detailed technical specifications for content creation.\n\n";
        $prompt .= "CONTENT TYPE: {$content_type}\n";
        $prompt .= "TARGET KEYWORD: {$keyword}\n\n";

        if (!empty($analysis)) {
            $prompt .= "COMPETITOR ANALYSIS:\n";
            $prompt .= json_encode($analysis, JSON_PRETTY_PRINT) . "\n\n";
        }

        if (!empty($serp)) {
            $prompt .= "SERP DATA:\n";
            $prompt .= json_encode($serp, JSON_PRETTY_PRINT) . "\n\n";
        }

        $prompt .= "Generate complete technical specifications including:\n";
        $prompt .= "- Exact title (optimized for CTR and SEO, max 60 chars)\n";
        $prompt .= "- Meta description (compelling, max 160 chars)\n";
        $prompt .= "- URL slug (SEO-friendly)\n";
        $prompt .= "- Content outline with word counts per section\n";
        $prompt .= "- Keywords to include with target density\n";
        $prompt .= "- LSI keywords (20-30 terms)\n";
        $prompt .= "- Questions to answer in the content\n";
        $prompt .= "- Image/media requirements (types, quantity, alt text suggestions)\n";
        $prompt .= "- Internal linking opportunities (5-10 suggestions)\n";
        $prompt .= "- External sources to cite (authoritative)\n";
        $prompt .= "- Readability target (Flesch score)\n";
        $prompt .= "- Tone and style guidelines\n";
        $prompt .= "- Schema markup recommendations\n\n";
        $prompt .= "Also generate:\n";
        $prompt .= "- AI content generation prompt (detailed instructions for AI writer)\n";
        $prompt .= "- SEO checklist for this content\n";
        $prompt .= "- Success metrics to track\n\n";
        $prompt .= "Return as structured JSON.";

        return $prompt;
    }

    /**
     * Send request to Claude AI API
     *
     * @param string $prompt The prompt to send
     * @param array $options Additional options
     * @return string|WP_Error Response content or error
     */
    private function send_request($prompt, $options = array()) {
        $start_time = microtime(true);

        $system_message = $options['system'] ?? 'You are a helpful AI assistant specialized in SEO analysis and content strategy.';
        $model = $options['model'] ?? $this->model;
        $max_tokens = $options['max_tokens'] ?? $this->max_tokens;

        $body = array(
            'model' => $model,
            'max_tokens' => $max_tokens,
            'system' => $system_message,
            'messages' => array(
                array(
                    'role' => 'user',
                    'content' => $prompt
                )
            )
        );

        $args = array(
            'headers' => array(
                'Content-Type' => 'application/json',
                'x-api-key' => $this->api_key,
                'anthropic-version' => '2023-06-01'
            ),
            'body' => wp_json_encode($body),
            'timeout' => 60
        );

        $response = wp_remote_post($this->api_endpoint, $args);

        $execution_time = microtime(true) - $start_time;

        if (is_wp_error($response)) {
            $this->log_api_call('claude-ai', $this->api_endpoint, $body, null, null, $execution_time);
            return $response;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        $data = json_decode($response_body, true);

        // Log the API call
        $tokens_used = $data['usage']['input_tokens'] ?? 0 + $data['usage']['output_tokens'] ?? 0;
        $this->log_api_call('claude-ai', $this->api_endpoint, $body, $data, $status_code, $execution_time, $tokens_used);

        if ($status_code !== 200) {
            $error_message = $data['error']['message'] ?? 'Unknown error';
            return new WP_Error('api_error', sprintf(__('Claude AI API error: %s', 'seo-analytics-pro'), $error_message));
        }

        if (empty($data['content'][0]['text'])) {
            return new WP_Error('empty_response', __('Empty response from Claude AI', 'seo-analytics-pro'));
        }

        return $data['content'][0]['text'];
    }

    /**
     * Parse keyword analysis response
     *
     * @param string $response Raw response
     * @return array Parsed analysis
     */
    private function parse_keyword_analysis($response) {
        return $this->parse_json_response($response);
    }

    /**
     * Parse content strategy response
     *
     * @param string $response Raw response
     * @return array Parsed strategy
     */
    private function parse_content_strategy($response) {
        return $this->parse_json_response($response);
    }

    /**
     * Parse technical spec response
     *
     * @param string $response Raw response
     * @return array Parsed specs
     */
    private function parse_technical_spec($response) {
        return $this->parse_json_response($response);
    }

    /**
     * Parse JSON response from Claude AI
     *
     * @param string $response Raw response text
     * @return array|WP_Error Parsed data or error
     */
    private function parse_json_response($response) {
        // Try to extract JSON from response (Claude sometimes wraps it in markdown)
        if (preg_match('/```json\s*(.*?)\s*```/s', $response, $matches)) {
            $json_string = $matches[1];
        } else if (preg_match('/```\s*(.*?)\s*```/s', $response, $matches)) {
            $json_string = $matches[1];
        } else {
            $json_string = $response;
        }

        $data = json_decode($json_string, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            // If JSON parsing fails, return the raw response
            return array(
                'raw_response' => $response,
                'parse_error' => json_last_error_msg()
            );
        }

        return $data;
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
     * @param int $tokens_used Tokens consumed
     */
    private function log_api_call($service_name, $endpoint, $request_data, $response_data, $status_code, $execution_time, $tokens_used = 0) {
        global $wpdb;

        // Calculate estimated cost (rough estimate for Claude Sonnet)
        $cost = 0;
        if ($tokens_used > 0) {
            // Approximate: $3 per million input tokens, $15 per million output tokens
            // Simplified: average $9 per million tokens
            $cost = ($tokens_used / 1000000) * 9;
        }

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
                'tokens_used' => $tokens_used,
                'cost' => $cost,
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
        $response = $this->send_request('Say "API key is valid" if you can read this message.', array(
            'max_tokens' => 50
        ));

        if (is_wp_error($response)) {
            return $response;
        }

        return true;
    }
}
