<?php
/**
 * LSI Keyword Extractor
 *
 * Extracts LSI (Latent Semantic Indexing) keywords from multiple sources:
 * - SERP API related searches
 * - People Also Ask questions
 * - Competitor content analysis
 * - Claude AI generation
 *
 * @package    SEOAnalyticsPro
 * @subpackage SEOAnalyticsPro/includes/analyzers
 */

class SAP_LSI_Extractor {

    /**
     * Russian stop words to filter out
     *
     * @var array
     */
    private $stop_words_ru = array(
        'и', 'в', 'во', 'не', 'что', 'он', 'на', 'я', 'с', 'со', 'как', 'а', 'то', 'все',
        'она', 'так', 'его', 'но', 'да', 'ты', 'к', 'у', 'же', 'вы', 'за', 'бы', 'по',
        'только', 'ее', 'мне', 'было', 'вот', 'от', 'меня', 'еще', 'нет', 'о', 'из', 'ему',
        'теперь', 'когда', 'уже', 'вам', 'ни', 'быть', 'был', 'него', 'до', 'вас', 'нибудь',
        'опять', 'уж', 'вам', 'ведь', 'там', 'потом', 'себя', 'ничего', 'ей', 'может', 'они',
        'тут', 'где', 'есть', 'надо', 'ней', 'для', 'мы', 'тебя', 'их', 'чем', 'была', 'сам',
        'чтоб', 'без', 'будто', 'чего', 'раз', 'тоже', 'себе', 'под', 'будет', 'ж', 'тогда',
        'кто', 'этот', 'того', 'потому', 'этого', 'какой', 'совсем', 'ним', 'здесь', 'этом',
        'один', 'почти', 'мой', 'тем', 'чтобы', 'нее', 'сейчас', 'были', 'куда', 'зачем',
        'всех', 'никогда', 'можно', 'при', 'наконец', 'два', 'об', 'другой', 'хоть', 'после',
        'над', 'больше', 'тот', 'через', 'эти', 'нас', 'про', 'всего', 'них', 'какая', 'много',
        'разве', 'три', 'эту', 'моя', 'впрочем', 'хорошо', 'свою', 'этой', 'перед', 'иногда',
        'лучше', 'чуть', 'том', 'нельзя', 'такой', 'им', 'более', 'всегда', 'конечно', 'всю',
        'между', 'который', 'которые', 'которая', 'также', 'либо', 'или', 'это', 'эта', 'эти'
    );

    /**
     * Ukrainian stop words
     *
     * @var array
     */
    private $stop_words_uk = array(
        'і', 'в', 'у', 'не', 'що', 'він', 'на', 'я', 'з', 'із', 'як', 'а', 'то', 'все',
        'вона', 'так', 'його', 'але', 'так', 'ти', 'до', 'же', 'ви', 'за', 'б', 'по',
        'тільки', 'її', 'мені', 'було', 'ось', 'від', 'мене', 'ще', 'ні', 'бути', 'був',
        'нього', 'вам', 'вже', 'там', 'потім', 'себе', 'нічого', 'їй', 'може', 'вони',
        'тут', 'де', 'є', 'треба', 'ній', 'для', 'ми', 'тебе', 'їх', 'чим', 'була', 'сам'
    );

    /**
     * Extract LSI keywords from all available sources
     *
     * @param string $keyword Main keyword
     * @param array $serp_results SERP results with related searches
     * @param array $competitor_content Array of competitor page contents
     * @return array Combined LSI keywords with scores
     */
    public function extract_all($keyword, $serp_results = array(), $competitor_content = array()) {
        $lsi_keywords = array();

        // 1. Extract from SERP related searches
        if (!empty($serp_results['related_keywords'])) {
            $from_serp = $this->extract_from_related_searches($serp_results['related_keywords'], $keyword);
            $lsi_keywords = array_merge($lsi_keywords, $from_serp);
        }

        // 2. Extract from People Also Ask
        if (!empty($serp_results['people_also_ask'])) {
            $from_paa = $this->extract_from_people_also_ask($serp_results['people_also_ask']);
            $lsi_keywords = array_merge($lsi_keywords, $from_paa);
        }

        // 3. Extract from competitor content
        if (!empty($competitor_content)) {
            $from_content = $this->extract_from_content($competitor_content, $keyword);
            $lsi_keywords = array_merge($lsi_keywords, $from_content);
        }

        // 4. Merge duplicates and calculate final scores
        $merged = $this->merge_and_score($lsi_keywords);

        // 5. Sort by score and return top results
        uasort($merged, function($a, $b) {
            return $b['score'] <=> $a['score'];
        });

        return array_slice($merged, 0, 50, true);
    }

    /**
     * Extract LSI from related searches
     *
     * @param array $related_searches Related search queries
     * @param string $main_keyword Main keyword to filter out
     * @return array LSI keywords
     */
    public function extract_from_related_searches($related_searches, $main_keyword) {
        $lsi = array();
        $main_words = $this->tokenize($main_keyword);

        foreach ($related_searches as $search) {
            $words = $this->tokenize($search);

            foreach ($words as $word) {
                // Skip if it's part of main keyword or too short
                if (in_array($word, $main_words) || mb_strlen($word) < 3) {
                    continue;
                }

                // Skip stop words
                if ($this->is_stop_word($word)) {
                    continue;
                }

                if (!isset($lsi[$word])) {
                    $lsi[$word] = array(
                        'keyword' => $word,
                        'score' => 0,
                        'source' => 'related_searches'
                    );
                }
                $lsi[$word]['score'] += 10; // High weight for related searches
            }

            // Also add full phrases (2-3 words)
            $phrases = $this->extract_ngrams($search, 2, 3);
            foreach ($phrases as $phrase) {
                if (mb_stripos($phrase, $main_keyword) === false && mb_strlen($phrase) > 5) {
                    if (!isset($lsi[$phrase])) {
                        $lsi[$phrase] = array(
                            'keyword' => $phrase,
                            'score' => 0,
                            'source' => 'related_searches'
                        );
                    }
                    $lsi[$phrase]['score'] += 15;
                }
            }
        }

        return $lsi;
    }

    /**
     * Extract LSI from People Also Ask
     *
     * @param array $paa People Also Ask data
     * @return array LSI keywords
     */
    public function extract_from_people_also_ask($paa) {
        $lsi = array();

        foreach ($paa as $item) {
            $question = $item['question'] ?? '';
            if (empty($question)) continue;

            // Extract key phrases from questions
            $words = $this->tokenize($question);

            foreach ($words as $word) {
                if (mb_strlen($word) < 3 || $this->is_stop_word($word)) {
                    continue;
                }

                if (!isset($lsi[$word])) {
                    $lsi[$word] = array(
                        'keyword' => $word,
                        'score' => 0,
                        'source' => 'people_also_ask'
                    );
                }
                $lsi[$word]['score'] += 8;
            }

            // Extract question patterns
            $phrases = $this->extract_ngrams($question, 2, 4);
            foreach ($phrases as $phrase) {
                // Remove question words
                $phrase = preg_replace('/^(как|что|где|когда|почему|сколько|який|де|коли)\s+/ui', '', $phrase);
                if (mb_strlen($phrase) > 5) {
                    if (!isset($lsi[$phrase])) {
                        $lsi[$phrase] = array(
                            'keyword' => $phrase,
                            'score' => 0,
                            'source' => 'people_also_ask'
                        );
                    }
                    $lsi[$phrase]['score'] += 12;
                }
            }
        }

        return $lsi;
    }

    /**
     * Extract LSI from competitor content using TF-IDF approach
     *
     * @param array $contents Array of page contents
     * @param string $main_keyword Main keyword
     * @return array LSI keywords
     */
    public function extract_from_content($contents, $main_keyword) {
        $lsi = array();
        $all_words = array();
        $doc_count = count($contents);
        $word_doc_frequency = array();

        // First pass: collect all words and document frequencies
        foreach ($contents as $content) {
            $text = is_array($content) ? ($content['content'] ?? '') : $content;
            $words = $this->tokenize($text);
            $doc_words = array_unique($words);

            foreach ($doc_words as $word) {
                if (mb_strlen($word) < 3 || $this->is_stop_word($word)) {
                    continue;
                }

                if (!isset($word_doc_frequency[$word])) {
                    $word_doc_frequency[$word] = 0;
                }
                $word_doc_frequency[$word]++;
            }

            // Count word frequencies in this document
            $word_counts = array_count_values($words);
            foreach ($word_counts as $word => $count) {
                if (!isset($all_words[$word])) {
                    $all_words[$word] = 0;
                }
                $all_words[$word] += $count;
            }
        }

        // Calculate TF-IDF scores
        $main_words = $this->tokenize($main_keyword);

        foreach ($all_words as $word => $total_freq) {
            // Skip main keyword words
            if (in_array($word, $main_words)) {
                continue;
            }

            // Skip if too short or stop word
            if (mb_strlen($word) < 3 || $this->is_stop_word($word)) {
                continue;
            }

            // Calculate IDF
            $doc_freq = $word_doc_frequency[$word] ?? 1;
            $idf = log($doc_count / $doc_freq);

            // TF-IDF score
            $tf_idf = $total_freq * $idf;

            // Only include words that appear in multiple documents (common terms)
            if ($doc_freq >= 2 || $total_freq >= 5) {
                $lsi[$word] = array(
                    'keyword' => $word,
                    'score' => round($tf_idf, 2),
                    'source' => 'content_analysis',
                    'frequency' => $total_freq,
                    'doc_frequency' => $doc_freq
                );
            }
        }

        // Extract common n-grams from all content
        $all_text = implode(' ', array_map(function($c) {
            return is_array($c) ? ($c['content'] ?? '') : $c;
        }, $contents));

        $ngrams = $this->extract_ngrams($all_text, 2, 3);
        $ngram_counts = array_count_values($ngrams);

        foreach ($ngram_counts as $ngram => $count) {
            if ($count >= 3 && mb_strlen($ngram) > 5) {
                // Skip if contains main keyword
                if (mb_stripos($ngram, $main_keyword) !== false) {
                    continue;
                }

                $score = $count * 5;
                if (!isset($lsi[$ngram]) || $lsi[$ngram]['score'] < $score) {
                    $lsi[$ngram] = array(
                        'keyword' => $ngram,
                        'score' => $score,
                        'source' => 'content_ngrams',
                        'frequency' => $count
                    );
                }
            }
        }

        return $lsi;
    }

    /**
     * Generate LSI keywords using Claude AI
     *
     * @param string $keyword Main keyword
     * @param array $context Additional context (SERP results, etc.)
     * @return array|WP_Error LSI keywords or error
     */
    public function generate_with_ai($keyword, $context = array()) {
        $claude = new SAP_Claude_AI();

        if (!$claude->is_configured()) {
            return new WP_Error('no_api', __('Claude AI is not configured', 'seo-analytics-pro'));
        }

        $prompt = "Generate 30 LSI (Latent Semantic Indexing) keywords for the main keyword: \"{$keyword}\"\n\n";

        if (!empty($context['niche'])) {
            $prompt .= "Niche/Industry: {$context['niche']}\n";
        }

        if (!empty($context['language'])) {
            $prompt .= "Language: {$context['language']}\n";
        }

        $prompt .= "\nRequirements:\n";
        $prompt .= "1. Include semantically related terms (synonyms, related concepts)\n";
        $prompt .= "2. Include common modifiers (best, cheap, professional, etc.)\n";
        $prompt .= "3. Include action words (buy, order, find, compare)\n";
        $prompt .= "4. Include location modifiers if applicable\n";
        $prompt .= "5. Include technical/specific terms related to the topic\n\n";
        $prompt .= "Return as JSON array with objects containing:\n";
        $prompt .= "- keyword: the LSI term\n";
        $prompt .= "- relevance: high/medium/low\n";
        $prompt .= "- type: synonym/related/modifier/action/technical\n\n";
        $prompt .= "Example format:\n";
        $prompt .= json_encode(array(
            array('keyword' => 'example', 'relevance' => 'high', 'type' => 'synonym')
        ));

        // This would call Claude AI - implementation in SAP_Claude_AI class
        // For now, return placeholder
        return array(
            'ai_generated' => true,
            'keywords' => array()
        );
    }

    /**
     * Merge LSI keywords from different sources and calculate final scores
     *
     * @param array $lsi_keywords All collected LSI keywords
     * @return array Merged and scored keywords
     */
    private function merge_and_score($lsi_keywords) {
        $merged = array();

        foreach ($lsi_keywords as $key => $data) {
            $keyword = mb_strtolower($data['keyword']);

            if (!isset($merged[$keyword])) {
                $merged[$keyword] = array(
                    'keyword' => $data['keyword'],
                    'score' => 0,
                    'sources' => array()
                );
            }

            $merged[$keyword]['score'] += $data['score'];
            $merged[$keyword]['sources'][] = $data['source'];
        }

        // Boost score for keywords from multiple sources
        foreach ($merged as &$item) {
            $unique_sources = array_unique($item['sources']);
            if (count($unique_sources) > 1) {
                $item['score'] *= (1 + (count($unique_sources) * 0.2));
            }
            $item['sources'] = $unique_sources;
        }

        return $merged;
    }

    /**
     * Tokenize text into words
     *
     * @param string $text Text to tokenize
     * @return array Words
     */
    private function tokenize($text) {
        // Convert to lowercase
        $text = mb_strtolower($text);

        // Remove special characters but keep Cyrillic
        $text = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $text);

        // Split by whitespace
        $words = preg_split('/\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);

        return $words;
    }

    /**
     * Extract n-grams from text
     *
     * @param string $text Text to process
     * @param int $min_n Minimum n-gram size
     * @param int $max_n Maximum n-gram size
     * @return array N-grams
     */
    private function extract_ngrams($text, $min_n = 2, $max_n = 3) {
        $words = $this->tokenize($text);
        $ngrams = array();

        for ($n = $min_n; $n <= $max_n; $n++) {
            for ($i = 0; $i <= count($words) - $n; $i++) {
                $ngram = implode(' ', array_slice($words, $i, $n));

                // Skip if contains only stop words
                $ngram_words = explode(' ', $ngram);
                $non_stop = array_filter($ngram_words, function($w) {
                    return !$this->is_stop_word($w) && mb_strlen($w) >= 3;
                });

                if (count($non_stop) >= 1) {
                    $ngrams[] = $ngram;
                }
            }
        }

        return $ngrams;
    }

    /**
     * Check if word is a stop word
     *
     * @param string $word Word to check
     * @return bool
     */
    private function is_stop_word($word) {
        $word = mb_strtolower($word);
        return in_array($word, $this->stop_words_ru) || in_array($word, $this->stop_words_uk);
    }

    /**
     * Filter LSI keywords by minimum score
     *
     * @param array $lsi_keywords LSI keywords with scores
     * @param float $min_score Minimum score threshold
     * @return array Filtered keywords
     */
    public function filter_by_score($lsi_keywords, $min_score = 5) {
        return array_filter($lsi_keywords, function($item) use ($min_score) {
            return $item['score'] >= $min_score;
        });
    }

    /**
     * Group LSI keywords by type/category
     *
     * @param array $lsi_keywords LSI keywords
     * @return array Grouped keywords
     */
    public function group_by_type($lsi_keywords) {
        $grouped = array(
            'high_relevance' => array(),
            'medium_relevance' => array(),
            'low_relevance' => array()
        );

        $max_score = 0;
        foreach ($lsi_keywords as $item) {
            if ($item['score'] > $max_score) {
                $max_score = $item['score'];
            }
        }

        foreach ($lsi_keywords as $keyword => $item) {
            $ratio = $max_score > 0 ? $item['score'] / $max_score : 0;

            if ($ratio >= 0.6) {
                $grouped['high_relevance'][$keyword] = $item;
            } elseif ($ratio >= 0.3) {
                $grouped['medium_relevance'][$keyword] = $item;
            } else {
                $grouped['low_relevance'][$keyword] = $item;
            }
        }

        return $grouped;
    }
}
