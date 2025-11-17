<?php
/**
 * Web Scraper
 *
 * Handles web scraping for competitor analysis.
 *
 * @package    SEOAnalyticsPro
 * @subpackage SEOAnalyticsPro/includes/api
 */

class SAP_Scraper {

    /**
     * Scrape a website and extract content
     *
     * @param string $url URL to scrape
     * @param array $options Scraping options
     * @return array|WP_Error Scraped content or error
     */
    public function scrape_url($url, $options = array()) {
        $response = wp_remote_get($url, array(
            'timeout' => 15,
            'user-agent' => 'Mozilla/5.0 (compatible; SEOAnalyticsPro/1.0; +https://example.com/bot)'
        ));

        if (is_wp_error($response)) {
            return $response;
        }

        $html = wp_remote_retrieve_body($response);

        return $this->parse_html($html, $url);
    }

    /**
     * Parse HTML and extract relevant SEO content
     *
     * @param string $html HTML content
     * @param string $url Source URL
     * @return array Parsed content
     */
    private function parse_html($html, $url) {
        $data = array(
            'url' => $url,
            'title' => '',
            'meta_description' => '',
            'meta_keywords' => '',
            'headings' => array(),
            'content' => '',
            'links' => array()
        );

        // Extract title
        if (preg_match('/<title[^>]*>(.*?)<\/title>/si', $html, $matches)) {
            $data['title'] = trim($matches[1]);
        }

        // Extract meta description
        if (preg_match('/<meta\s+name=["\']description["\']\s+content=["\'](.*?)["\']/si', $html, $matches)) {
            $data['meta_description'] = trim($matches[1]);
        }

        // Extract meta keywords
        if (preg_match('/<meta\s+name=["\']keywords["\']\s+content=["\'](.*?)["\']/si', $html, $matches)) {
            $data['meta_keywords'] = trim($matches[1]);
        }

        // Extract headings
        if (preg_match_all('/<h([1-6])[^>]*>(.*?)<\/h\1>/si', $html, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $level = $match[1];
                $text = strip_tags($match[2]);
                $data['headings'][] = array(
                    'level' => 'h' . $level,
                    'text' => trim($text)
                );
            }
        }

        // Extract body content (simplified)
        $content = strip_tags($html);
        $content = preg_replace('/\s+/', ' ', $content);
        $data['content'] = trim($content);

        return $data;
    }
}
