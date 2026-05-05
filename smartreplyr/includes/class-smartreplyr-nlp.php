<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * SmartReplyr_NLP — Strict KB-Only Answer Engine
 * 
 * A self-contained, confidence-scored matching engine that:
 *  1. Strips & normalizes text with synonym expansion
 *  2. Applies BM25-style TF-IDF scoring against the Knowledge Base
 *  3. Uses intent detection from KB entries
 *  4. Uses character-level similarity (similar_text + levenshtein) as a safety net
 *  5. Returns ONLY answers stored in KB or crawled site content
 * 
 * STRICT RULES:
 *  - NEVER generates or assumes information
 *  - NEVER returns loosely matched answers (threshold: 65%)
 *  - ALWAYS fails safely with controlled fallback
 *  - Zero hallucination guarantee
 * 
 * No external API required.
 */
class SmartReplyr_NLP {

    // ── Core: Match a user query against the KB ─────────────────────────────

    /**
     * Find the best KB match for a user query.
     * Returns the best entry if score >= threshold, else null.
     */
    public static function match_query( $user_query, $lead_context = array() ) {
        $kb_entries = SmartReplyr_DB::get_all_kb();
        if ( empty( $kb_entries ) ) return null;

        $normalized_query = self::normalize_advanced( $user_query );
        $user_tokens = self::tokenize( $normalized_query );

        if ( empty( $user_tokens ) ) return null;

        // Phase 1: Intent detection
        $detected_intent = self::detect_intent( $normalized_query, $user_tokens, $kb_entries );

        $best_match   = null;
        $highest_score = 0;

        $total_docs = count( $kb_entries );
        // Pre-compute IDF for all tokens
        $idf = self::compute_idf( $user_tokens, $kb_entries, $total_docs );

        foreach ( $kb_entries as $entry ) {
            $score = self::score_entry( $entry, $normalized_query, $user_tokens, $idf, $detected_intent, $total_docs );

            if ( $score > $highest_score ) {
                $highest_score = $score;
                $entry['_match_score']   = round( $score, 2 );
                $entry['_intent']        = $detected_intent;
                $best_match   = $entry;
            }
        }

        // Strict threshold: 65 — only return confident matches, never guess
        if ( $highest_score < 65 || ! $best_match ) return null;

        // Hard filter: reject if the KB answer is too short to be meaningful
        $answer_len = strlen( trim( $best_match['answer'] ?? '' ) );
        if ( $answer_len < 20 ) return null;

        return $best_match;
    }

    /**
     * Match user query against auto-crawled website content (site_content table).
     * Uses same BM25-style scoring as KB matching, with page-context boosting.
     *
     * @param string $user_query      The user's message
     * @param string $page_context    URL of the page the user is currently on
     * @param array  $lead_context    Lead data for personalization
     * @return array|null  Best match with answer, source_url, title, or null
     */
    public static function match_site_content( $user_query, $page_context = '', $lead_context = array() ) {
        $site_entries = SmartReplyr_DB::get_all_site_content();
        if ( empty( $site_entries ) ) return null;

        $normalized_query = self::normalize_advanced( $user_query );
        $user_tokens = self::tokenize( $normalized_query );

        if ( empty( $user_tokens ) ) return null;

        $best_match    = null;
        $highest_score = 0;

        // Normalize page context path for comparison
        $context_path = '';
        if ( ! empty( $page_context ) ) {
            $context_path = rtrim( wp_parse_url( $page_context, PHP_URL_PATH ) ?: '', '/' );
        }

        foreach ( $site_entries as $entry ) {
            $score = self::score_site_chunk( $entry, $normalized_query, $user_tokens );

            // Page-context boost: if user is ON this page, boost score 1.5x
            if ( ! empty( $context_path ) && ! empty( $entry['source_url'] ) ) {
                $entry_path = rtrim( wp_parse_url( $entry['source_url'], PHP_URL_PATH ) ?: '', '/' );
                if ( $entry_path === $context_path ) {
                    $score = min( 100, $score * 1.5 );
                }
            }

            if ( $score > $highest_score ) {
                $highest_score = $score;
                $best_match = $entry;
                $best_match['_match_score'] = round( $score, 2 );
            }
        }

        // Strict threshold: 65 — only return confident matches from site content
        if ( $highest_score < 65 || ! $best_match ) return null;

        // Hard filter: reject if chunk text is too short
        if ( strlen( trim( $best_match['chunk_text'] ?? '' ) ) < 20 ) return null;

        // Build a response from the matched chunk
        $best_match['answer'] = $best_match['chunk_text'];
        $best_match['_source'] = 'site_content';

        return $best_match;
    }

    /**
     * Score a site content chunk against user query.
     * Simplified version of score_entry() optimized for content chunks.
     */
    private static function score_site_chunk( $entry, $norm_query, $user_tokens ) {
        // Combine title + heading + chunk for matching
        $entry_text = strtolower(
            ( $entry['title'] ?? '' ) . ' ' .
            ( $entry['heading'] ?? '' ) . ' ' .
            ( $entry['chunk_text'] ?? '' )
        );
        $entry_text = preg_replace( '/[^a-z0-9\s\-]/u', ' ', $entry_text );
        $entry_text = preg_replace( '/\s+/', ' ', trim( $entry_text ) );

        $entry_tokens = self::tokenize( $entry_text );

        // 1. Token overlap score (40%)
        $matched_tokens = 0;
        foreach ( $user_tokens as $ut ) {
            foreach ( $entry_tokens as $et ) {
                if ( self::soft_match( $ut, $et ) ) { $matched_tokens++; break; }
            }
        }
        $overlap_score = min( 100, ( $matched_tokens / max( 1, count( $user_tokens ) ) ) * 100 );

        // 2. String similarity (25%)
        // Use truncated entry text for performance
        $compare_text = substr( $entry_text, 0, 500 );
        similar_text( $norm_query, $compare_text, $sim_score );

        // 3. Keyword match score (30%)
        $kw_score = 0;
        if ( ! empty( $entry['keywords'] ) ) {
            $kws = json_decode( $entry['keywords'], true );
            if ( is_array( $kws ) && ! empty( $kws ) ) {
                $max_kw_match = 0;
                foreach ( $kws as $kw ) {
                    $kw_norm = self::normalize_advanced( $kw );
                    if ( empty( $kw_norm ) ) continue;
                    if ( $norm_query === $kw_norm ) {
                        $max_kw_match = 100;
                        break;
                    }
                    if ( strpos( $norm_query, $kw_norm ) !== false || strpos( $kw_norm, $norm_query ) !== false ) {
                        $max_kw_match = max( $max_kw_match, 80 );
                    }
                }
                $kw_score = $max_kw_match;
            }
        }

        // 4. Title/heading exact match bonus (10%)
        $title_bonus = 0;
        $title_norm = self::normalize_advanced( $entry['title'] ?? '' );
        $heading_norm = self::normalize_advanced( $entry['heading'] ?? '' );
        if ( ! empty( $title_norm ) && strpos( $title_norm, $norm_query ) !== false ) {
            $title_bonus = 100;
        } elseif ( ! empty( $heading_norm ) && strpos( $heading_norm, $norm_query ) !== false ) {
            $title_bonus = 80;
        }

        // Dynamic weighting for short queries
        if ( count( $user_tokens ) <= 2 ) {
            $final = ( $overlap_score * 0.45 ) + ( $kw_score * 0.40 ) + ( $title_bonus * 0.15 );
        } else {
            $final = ( $overlap_score * 0.40 ) + ( $sim_score * 0.20 ) + ( $kw_score * 0.30 ) + ( $title_bonus * 0.10 );
        }

        return $final;
    }

    /**
     * Return matched site content directly with source attribution.
     * No generation, no embellishment — only what's on the actual page.
     */
    public static function generate_site_content_response( $match, $lead = array() ) {
        if ( empty( $match['chunk_text'] ) ) return null;

        $response = $match['chunk_text'];

        // Add heading context if available and different from title
        $heading = $match['heading'] ?? '';
        $title   = $match['title'] ?? '';
        if ( ! empty( $heading ) && $heading !== $title ) {
            $response = "**{$heading}**\n\n" . $response;
        }

        // Source attribution link — always show where the answer came from
        if ( ! empty( $match['source_url'] ) && ! empty( $title ) ) {
            $response .= "\n\n📄 *Source: [" . $title . "](" . $match['source_url'] . ")*";
        }

        return $response;
    }

    /**
     * Score a single KB entry against the user query using a BM25-inspired hybrid approach.
     */
    private static function score_entry( $entry, $norm_query, $user_tokens, $idf, $detected_intent, $total_docs ) {
        $entry_question = self::normalize_advanced( $entry['question'] );
        $entry_tokens   = self::tokenize( $entry_question );

        // --- 1. Token Overlap Score (40%) ---
        $matched_tokens = 0;
        foreach ( $user_tokens as $ut ) {
            foreach ( $entry_tokens as $et ) {
                if ( self::soft_match( $ut, $et ) ) { $matched_tokens++; break; }
            }
        }
        $overlap_score = min( 100, ( $matched_tokens / max( 1, count( $user_tokens ) ) ) * 100 );

        // --- 2. String Similarity Score (20%) ---
        similar_text( $norm_query, $entry_question, $sim_score );

        // --- 3. Keyword Overlap Score (30%) ---
        $kw_score = self::keyword_score( $norm_query, $user_tokens, $entry, $entry_tokens );

        // --- 4. Exact phrase bonus (10%) ---
        $exact_bonus = 0;
        if ( strpos( $entry_question, $norm_query ) !== false || strpos( $norm_query, $entry_question ) !== false ) {
            $exact_bonus = 100;
        } else {
            // Sliding window phrase match
            $chunks = self::get_ngrams( $user_tokens, 3 );
            foreach ( $chunks as $chunk ) {
                if ( strpos( $entry_question, $chunk ) !== false ) {
                    $exact_bonus = max( $exact_bonus, 70 );
                }
            }
        }

        // Dynamic weighting for short queries
        if ( count( $user_tokens ) <= 2 ) {
            // Drop sim_score penalty for short queries
            $final = ( $overlap_score * 0.45 ) + ( $kw_score * 0.40 ) + ( $exact_bonus * 0.15 );
        } else {
            $final = ( $overlap_score * 0.40 ) + ( $sim_score * 0.20 ) + ( $kw_score * 0.30 ) + ( $exact_bonus * 0.10 );
        }

        // Intent match bonus
        if ( $detected_intent && ! empty( $entry['intent'] ) && strtolower( trim( $entry['intent'] ) ) === $detected_intent ) {
            $final = min( 100, $final * 1.25 );
        }
        // Intent mismatch penalty (only if both exist)
        if ( $detected_intent && ! empty( $entry['intent'] ) && strtolower( trim( $entry['intent'] ) ) !== $detected_intent ) {
            $final *= 0.60;
        }

        return $final;
    }

    // ── Intelligent Response Generation ─────────────────────────────────────

    /**
     * Return the matched KB answer directly. No generation, no embellishment.
     * Only replaces placeholders and adds source attribution.
     */
    public static function generate_response( $match, $lead = array(), $history = array() ) {
        if ( empty( $match['answer'] ) ) return null;

        $site_name = get_bloginfo( 'name' );
        $lead_name = $lead['name'] ?? '';
        $first_name = $lead_name ? explode( ' ', $lead_name )[0] : '';

        $answer = wp_kses_post( $match['answer'] );

        // Replace placeholders only — no generated text
        $answer = str_replace( '{{institute_name}}', esc_html( $site_name ), $answer );
        if ( $first_name ) {
            $answer = str_replace( '{{lead_name}}', esc_html( $first_name ), $answer );
        }

        // Source attribution (if KB entry has category/intent metadata)
        $source_label = '';
        if ( ! empty( $match['category'] ) && $match['category'] !== 'general' ) {
            $source_label = "\n\n📋 *Source: " . ucfirst( $match['category'] ) . " Knowledge Base*";
        }

        return $answer . $source_label;
    }

    /**
     * Safe fallback when no KB or site content match is found.
     * 
     * STRICT RULES:
     * - NEVER generate or assume information
     * - NEVER return topic-specific content not stored in KB
     * - ONLY handle social intents (greeting, thanks, bye, contact)
     * - ALWAYS direct to human team for substantive questions
     */
    public static function smart_fallback( $user_query, $lead = array(), $history = array() ) {
        $first_name = '';
        if ( ! empty( $lead['name'] ) ) {
            $first_name = explode( ' ', $lead['name'] )[0];
        }
        $site = get_bloginfo( 'name' );
        $normalized = self::normalize_advanced( $user_query );
        $name_part = $first_name ? ", {$first_name}" : '';

        // ── 1. Greeting detection ──
        $greetings = array( 'hi', 'hello', 'hey', 'good morning', 'good evening', 'good afternoon', 'namaste', 'hii', 'hiii', 'sup', 'yo' );
        foreach ( $greetings as $g ) {
            if ( strpos( $normalized, $g ) !== false || $normalized === $g ) {
                $intro = $first_name ? "Hello {$first_name}! 👋" : "Hello! 👋";
                return "{$intro} Welcome to **{$site}**. Feel free to ask me any question and I'll share what I know!";
            }
        }

        // ── 2. Thanks / positive detection ──
        $thanks = array( 'thank', 'thanks', 'thx', 'awesome', 'helpful', 'perfect', 'nice', 'cool', 'okay', 'ok', 'got it', 'understood' );
        foreach ( $thanks as $t ) {
            if ( strpos( $normalized, $t ) !== false ) {
                return $first_name
                    ? "You're welcome, {$first_name}! Feel free to ask if you have more questions."
                    : "You're welcome! Feel free to ask if you have more questions.";
            }
        }

        // ── 3. Goodbye detection ──
        $byes = array( 'bye', 'goodbye', 'see you', 'later', 'tata', 'done', 'that all', 'nothing' );
        foreach ( $byes as $b ) {
            if ( strpos( $normalized, $b ) !== false ) {
                return "Thank you for chatting with us{$name_part}! If you need help again, we're here. Have a great day!";
            }
        }

        // ── 4. Contact / human request ──
        $contact_triggers = array( 'speak', 'talk', 'contact', 'human', 'person', 'phone number', 'real person', 'agent', 'counselor', 'counsellor', 'advisor', 'representative', 'call' );
        foreach ( $contact_triggers as $t ) {
            if ( strpos( $normalized, $t ) !== false ) {
                return "I'd be happy to connect you with our team{$name_part}! Please visit the **Contact Us** page on our website, or reach out to our admissions office directly for personalized assistance.";
            }
        }

        // ── 5. Safe controlled fallback — NEVER generate information ──
        $fallback_db = SmartReplyr_DB::get_setting( 'fallback_message', '' );
        if ( ! empty( $fallback_db ) ) {
            return $fallback_db;
        }

        $safe_fallbacks = array(
            "I don't have that exact information right now{$name_part}. Please contact our team for accurate details — they'll be happy to help!",
            "I want to make sure you get accurate information{$name_part}. For this question, I'd recommend reaching out to our admissions team directly.",
            "I don't have a confident answer for that{$name_part}. Would you like to speak with a counselor who can help you with the specifics?",
        );
        return $safe_fallbacks[ array_rand( $safe_fallbacks ) ];
    }

    // ── NLP Utilities ────────────────────────────────────────────────────────

    /**
     * Advanced normalization: lowercase, remove noise, apply synonym expansion.
     */
    public static function normalize_advanced( $text ) {
        $text = strtolower( $text );
        $text = html_entity_decode( $text, ENT_QUOTES, 'UTF-8' );
        $text = preg_replace( '/[^a-z0-9\s\-]/u', ' ', $text );
        $text = preg_replace( '/\b(a|an|the|is|are|was|were|do|does|did|i|me|my|we|our|you|your|it|its|at|in|on|for|of|to|from|by|with|and|or|but|so|what|which|where|when|how|who|can|could|would|should|will|have|has|had|please|tell|give|show)\b/', '', $text );
        $text = preg_replace( '/\s+/', ' ', $text );
        $text = trim( $text );

        // Synonym expansion: replace common variations with canonical form
        $text = self::expand_synonyms( $text );
        return $text;
    }

    /**
     * Basic tokenize & stem.
     */
    public static function tokenize( $text ) {
        $tokens = preg_split( '/\s+/', trim( $text ), -1, PREG_SPLIT_NO_EMPTY );
        return array_map( array( 'self', 'stem' ), $tokens );
    }

    /**
     * Simple suffix stemmer (English).
     */
    public static function stem( $word ) {
        $suffixes = array( 'ings', 'tion', 'sion', 'ness', 'ment', 'ing', 'ies', 'ied', 'ess', 'ful', 'ed', 'er', 'ly', 's' );
        foreach ( $suffixes as $suffix ) {
            if ( strlen( $word ) > strlen( $suffix ) + 3 && substr( $word, -strlen( $suffix ) ) === $suffix ) {
                return substr( $word, 0, -strlen( $suffix ) );
            }
        }
        return $word;
    }

    /**
     * Soft match: accepts stemmed similarity or 1-char levenshtein.
     */
    public static function soft_match( $a, $b ) {
        if ( $a === $b ) return true;
        if ( self::stem( $a ) === self::stem( $b ) ) return true;
        if ( strlen( $a ) > 4 && strlen( $b ) > 4 && levenshtein( $a, $b ) <= 1 ) return true;
        return false;
    }

    /**
     * Compute Inverse Document Frequency for a set of tokens.
     */
    private static function compute_idf( $tokens, $kb_entries, $total_docs ) {
        $idf = array();
        foreach ( $tokens as $token ) {
            $df = 0;
            foreach ( $kb_entries as $entry ) {
                $q = self::normalize_advanced( $entry['question'] );
                $q_tokens = self::tokenize( $q );
                foreach ( $q_tokens as $qt ) {
                    if ( self::soft_match( $token, $qt ) ) { $df++; break; }
                }
            }
            $idf[ $token ] = log( ( $total_docs + 1 ) / ( $df + 1 ) ) + 1;
        }
        return $idf;
    }

    /**
     * Get n-grams up to size $n from a token array.
     */
    private static function get_ngrams( $tokens, $n = 3 ) {
        $ngrams = array();
        $len    = count( $tokens );
        for ( $i = 0; $i < $len; $i++ ) {
            for ( $j = 2; $j <= $n && $i + $j <= $len; $j++ ) {
                $ngrams[] = implode( ' ', array_slice( $tokens, $i, $j ) );
            }
        }
        return $ngrams;
    }

    /**
     * Keyword overlap score using explicit KB keywords or token intersection.
     */
    private static function keyword_score( $norm_query, $user_tokens, $entry, $entry_tokens ) {
        // Explicit keywords in entry
        if ( ! empty( $entry['keywords'] ) ) {
            $kws = json_decode( $entry['keywords'], true );
            if ( is_array( $kws ) && ! empty( $kws ) ) {
                $max_match = 0;
                foreach ( $kws as $kw ) {
                    $kw_norm = self::normalize_advanced( $kw );
                    if ( empty( $kw_norm ) ) continue;
                    
                    if ( $norm_query === $kw_norm ) {
                        return 100;
                    }
                    if ( strpos( $norm_query, $kw_norm ) !== false || strpos( $kw_norm, $norm_query ) !== false ) {
                        $max_match = max( $max_match, 80 );
                    }
                    
                    $kw_tokens = self::tokenize( $kw_norm );
                    $overlap = 0;
                    foreach ( $user_tokens as $ut ) {
                        foreach ( $kw_tokens as $kt ) {
                            if ( self::soft_match( $ut, $kt ) ) { $overlap++; break; }
                        }
                    }
                    if ( ! empty( $kw_tokens ) ) {
                        $score = ( $overlap / count( $kw_tokens ) ) * 100;
                        $max_match = max( $max_match, $score );
                    }
                }
                return $max_match;
            }
        }

        // Fallback: token overlap vs entry tokens
        if ( empty( $entry_tokens ) ) return 0;
        $matched = 0;
        foreach ( $user_tokens as $ut ) {
            foreach ( $entry_tokens as $et ) {
                if ( self::soft_match( $ut, $et ) ) { $matched++; break; }
            }
        }
        return min( 100, ( $matched / max( 1, count( $user_tokens ) ) ) * 100 );
    }

    /**
     * Intent detection using keyword scoring per intent group.
     */
    private static function detect_intent( $norm_query, $user_tokens, $kb_entries ) {
        $intent_scores = array();

        foreach ( $kb_entries as $entry ) {
            if ( empty( $entry['intent'] ) ) continue;
            $intent = trim( strtolower( $entry['intent'] ) );
            if ( ! isset( $intent_scores[ $intent ] ) ) $intent_scores[ $intent ] = 0;

            if ( ! empty( $entry['keywords'] ) ) {
                $kws = json_decode( $entry['keywords'], true );
                if ( is_array( $kws ) ) {
                    foreach ( $kws as $kw ) {
                        $kw_norm = self::normalize_advanced( $kw );
                        if ( empty( $kw_norm ) ) continue;
                        if ( strpos( $norm_query, $kw_norm ) !== false ) {
                            $intent_scores[ $intent ] += count( explode( ' ', $kw_norm ) );
                        }
                    }
                }
            }
        }

        if ( empty( $intent_scores ) ) return null;
        arsort( $intent_scores );
        $top = array_key_first( $intent_scores );
        return ( $intent_scores[ $top ] > 0 ) ? $top : null;
    }

    /**
     * Synonym expansion map — converts user terms to canonical terms.
     */
    private static function expand_synonyms( $text ) {
        $synonyms = array(
            'fees'          => array( 'fee', 'cost', 'price', 'charges', 'tuition', 'payment', 'how much', 'scholarship', 'discount', 'expense', 'pay', 'money', 'amount', 'budget', 'afford', 'stipend', 'concession', 'waiver', 'emi', 'installment' ),
            'admission'     => array( 'admissions', 'apply', 'application', 'enroll', 'enrollment', 'register', 'registration', 'join', 'entry', 'eligibility', 'eligible', 'qualify', 'qualification', 'qualifications', 'criteria', 'requirement', 'requirements', 'cutoff', 'cut-off', 'merit', 'seat', 'seats', 'intake', 'last date', 'deadline' ),
            'courses'       => array( 'course', 'program', 'programs', 'degree', 'degrees', 'stream', 'streams', 'study', 'subjects', 'specialization', 'branch', 'branches', 'department', 'departments', 'curriculum', 'syllabus', 'major', 'minor', 'diploma', 'certificate', 'pg', 'ug', 'undergraduate', 'postgraduate', 'phd', 'doctorate', 'masters' ),
            'campus'        => array( 'college', 'institute', 'university', 'location', 'address', 'hostel', 'infrastructure', 'facility', 'facilities', 'lab', 'library', 'sports', 'canteen', 'cafeteria', 'auditorium', 'wifi', 'gym', 'ground', 'building', 'classroom', 'situated', 'where', 'city', 'transport', 'bus', 'shuttle' ),
            'placement'     => array( 'job', 'career', 'salary', 'hire', 'hiring', 'company', 'companies', 'corporate', 'employment', 'work', 'package', 'ctc', 'lpa', 'recruiter', 'recruiters', 'internship', 'intern', 'training', 'industry', 'opportunities', 'placed', 'average', 'highest', 'lowest', 'median' ),
            'duration'      => array( 'year', 'years', 'semester', 'months', 'long', 'length', 'time', 'period', 'span' ),
            'contact'       => array( 'phone', 'number', 'call', 'mail', 'email', 'helpline', 'whatsapp', 'reach', 'connect', 'enquiry', 'inquiry', 'office' ),
            'ranking'       => array( 'rank', 'ranked', 'rating', 'accreditation', 'accredited', 'naac', 'nba', 'nirf', 'aicte', 'ugc', 'approved', 'recognition', 'tier', 'best', 'top', 'reputation', 'review', 'reviews' ),
            'scholarship'   => array( 'scholarships', 'financial', 'aid', 'merit', 'freeships', 'freeship', 'grant', 'bursary', 'fund', 'funding', 'sponsorship' ),
            'hostel'        => array( 'accommodation', 'stay', 'room', 'lodging', 'residence', 'residential', 'mess', 'food', 'pg', 'boarding', 'dormitory' ),
            'exam'          => array( 'examination', 'test', 'entrance', 'jee', 'neet', 'cat', 'mat', 'gate', 'gmat', 'gre', 'sat', 'clat', 'cuet', 'cet', 'aptitude', 'competitive' ),
            'online'        => array( 'distance', 'remote', 'virtual', 'digital', 'e-learning', 'elearning', 'correspondence', 'odel', 'hybrid', 'blended' ),
            'safety'        => array( 'safe', 'ragging', 'anti-ragging', 'security', 'women', 'girl', 'female', 'harassment', 'discipline', 'rules', 'code' ),
        );

        foreach ( $synonyms as $canonical => $variants ) {
            foreach ( $variants as $variant ) {
                $text = preg_replace( '/\b' . preg_quote( $variant, '/' ) . '\b/', $canonical, $text );
            }
        }
        return $text;
    }

    /**
     * Backward-compatible alias for old code using normalize().
     */
    public static function normalize( $text ) {
        return self::normalize_advanced( $text );
    }
}
