<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class EduLead_NLP {

    /**
     * Attempts to find a matching entry in the knowledge base using rule-based scoring without AI.
     */
    public static function match_query($user_query) {
        $kb_entries = EduLead_DB::get_all_kb();
        if (empty($kb_entries)) {
            return null;
        }

        // Step 1: Normalize Input
        $normalized_query = self::normalize($user_query);
        $user_tokens = explode(' ', $normalized_query);
        if (empty($normalized_query)) {
            return null;
        }

        // Step 5: Intent Detection
        $detected_intent = self::detect_intent($normalized_query, $kb_entries);
        
        $best_match = null;
        $highest_score = 0;

        foreach ($kb_entries as $entry) {
            // Filter by intent if one was clearly detected
            if ($detected_intent && !empty($entry['intent'])) {
                if (trim(strtolower($entry['intent'])) !== $detected_intent) {
                    continue; 
                }
            }

            $entry_question = self::normalize($entry['question']);
            
            // 1. Similarity Matching
            similar_text($normalized_query, $entry_question, $sim_score);
            
            // 2. Keyword Matching
            $kw_score = self::calculate_keyword_score($normalized_query, $user_tokens, $entry, $entry_question);

            // 3. Distance Check (Optional penalty if we want to factor in levenshtein)
            $distance = levenshtein($normalized_query, $entry_question);
            // We use levenshtein as a tie-breaker or subtle reducer, but mainly rely on hybrid score
            $distance_penalty = $distance > 15 ? 5 : 0; 

            // Step 3: Final Scoring Logic (60% similarity, 40% keyword)
            $final_score = ($sim_score * 0.6) + ($kw_score * 0.4) - $distance_penalty;

            if ($final_score > $highest_score) {
                $highest_score = $final_score;
                
                $entry['match_score'] = round($final_score, 2);
                $entry['intent_detected'] = $detected_intent ?: 'none';
                $entry['levenshtein_dist'] = $distance;
                $entry['sim_score'] = round($sim_score, 2);
                $entry['kw_score'] = round($kw_score, 2);
                
                $best_match = $entry;
            }
        }

        // Threshold of 40 based on prompt instructions
        if ($highest_score >= 40) {
            return $best_match;
        }

        return null;
    }

    private static function calculate_keyword_score($normalized_query, $user_tokens, $entry, $entry_question) {
        $entry_keywords = array();
        if (!empty($entry['keywords'])) {
            $kw_data = json_decode($entry['keywords'], true);
            if (is_array($kw_data)) {
                $entry_keywords = array_filter(array_map(array('self', 'normalize'), $kw_data));
            }
        }

        // If explicit keywords exist, test overlap in the string
        if (!empty($entry_keywords)) {
            $matched_kw = 0;
            foreach ($entry_keywords as $kw) {
                if (strpos($normalized_query, $kw) !== false) {
                    $matched_kw++;
                }
            }
            return ($matched_kw / count($entry_keywords)) * 100;
        } 
        
        // If no explicit keywords, fallback to token-by-token overlap against the question
        $q_tokens = array_filter(explode(' ', $entry_question));
        if (empty($q_tokens)) return 0;
        
        $intersect = array_intersect($user_tokens, $q_tokens);
        return (count($intersect) / count($q_tokens)) * 100;
    }

    private static function detect_intent($normalized_query, $kb_entries) {
        $intent_scores = array();

        foreach ($kb_entries as $entry) {
            if (empty($entry['intent']) || empty($entry['keywords'])) {
                continue;
            }
            
            $intent = trim(strtolower($entry['intent']));
            $kws = json_decode($entry['keywords'], true);
            
            if (!is_array($kws)) continue;

            if (!isset($intent_scores[$intent])) {
                $intent_scores[$intent] = 0;
            }

            foreach ($kws as $kw) {
                $kw_norm = self::normalize($kw);
                if (empty($kw_norm)) continue;
                
                // If a keyword phrase exists exactly in user query, score intent
                if (strpos($normalized_query, $kw_norm) !== false) {
                    // Weight intent scores based on keyword length (longer phrases = higher confidence)
                    $intent_scores[$intent] += count(explode(' ', $kw_norm));
                }
            }
        }

        if (empty($intent_scores)) return null;

        arsort($intent_scores);
        $top_intent = array_key_first($intent_scores);
        
        if ($intent_scores[$top_intent] > 0) {
            return $top_intent;
        }
        
        return null;
    }

    public static function normalize($text) {
        $text = strtolower($text);
        // Remove special characters
        $text = preg_replace('/[^a-z0-9 ]/i', '', $text);
        // Trim spaces to single spaces
        $text = preg_replace('/\s+/', ' ', $text);
        return trim($text);
    }
}
