<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * SmartReplyr_NLP — Offline AI Engine
 * 
 * A self-contained, multi-strategy NLP engine that:
 *  1. Strips & normalizes text with synonym expansion
 *  2. Applies BM25-style TF-IDF scoring against the Knowledge Base
 *  3. Uses intent detection from KB entries
 *  4. Uses character-level similarity (similar_text + levenshtein) as a safety net
 *  5. Constructs fluent, personalized responses from KB answers
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

        // Threshold: 30 (lower than old 40 because BM25 scoring better reflects real relevance)
        return ( $highest_score >= 30 ) ? $best_match : null;
    }

    /**
     * Score a single KB entry against the user query using a BM25-inspired hybrid approach.
     */
    private static function score_entry( $entry, $norm_query, $user_tokens, $idf, $detected_intent, $total_docs ) {
        $entry_question = self::normalize_advanced( $entry['question'] );
        $entry_tokens   = self::tokenize( $entry_question );

        // --- 1. TF-IDF / BM25 Score (40%) ---
        $bm25_score = 0;
        $k1 = 1.5; $b = 0.75;
        $avg_len = 8; // average KB question length in tokens
        $doc_len = max( 1, count( $entry_tokens ) );

        foreach ( $user_tokens as $token ) {
            $tf = 0;
            foreach ( $entry_tokens as $et ) {
                if ( $et === $token || self::soft_match( $token, $et ) ) $tf++;
            }
            if ( $tf === 0 ) continue;
            $tf_norm = ( $tf * ( $k1 + 1 ) ) / ( $tf + $k1 * ( 1 - $b + $b * $doc_len / $avg_len ) );
            $idf_val = $idf[ $token ] ?? 1;
            $bm25_score += $tf_norm * $idf_val;
        }
        $bm25_score = min( 100, $bm25_score * 12 ); // normalize to 0-100 range

        // --- 2. String Similarity Score (25%) ---
        similar_text( $norm_query, $entry_question, $sim_score );

        // --- 3. Keyword Overlap Score (25%) ---
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

        $final = ( $bm25_score * 0.40 ) + ( $sim_score * 0.25 ) + ( $kw_score * 0.25 ) + ( $exact_bonus * 0.10 );

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
     * Given a matched KB entry and conversation context, generate a natural, fluent response.
     */
    public static function generate_response( $match, $lead = array(), $history = array() ) {
        if ( empty( $match['answer'] ) ) return null;

        $site_name = get_bloginfo( 'name' );
        $lead_name = $lead['name'] ?? '';
        $first_name = $lead_name ? explode( ' ', $lead_name )[0] : '';
        $course     = $lead['course_interest'] ?? '';

        $answer = wp_kses_post( $match['answer'] );

        // Replace placeholders
        $answer = str_replace( '{{institute_name}}', esc_html( $site_name ), $answer );
        if ( $first_name ) {
            $answer = str_replace( '{{lead_name}}', esc_html( $first_name ), $answer );
        }

        // Personalized intro (only on first message if lead context exists)
        $personalized = '';
        if ( $first_name && count( $history ) <= 2 ) {
            $greetings = array( "Great question, {$first_name}! ", "Here's what I found, {$first_name}: ", "{$first_name}, " );
            $personalized = $greetings[ array_rand( $greetings ) ];
        }

        // Course-specific context injection
        if ( $course && strpos( strtolower( $answer ), strtolower( $course ) ) === false ) {
            $suffixes = array(
                " If you're interested in **{$course}**, our admissions team can guide you specifically.",
                " This applies to our {$course} program as well.",
            );
            $answer .= $suffixes[ array_rand( $suffixes ) ];
        }

        // CTA injection (probability-based)
        $cta_pool = array(
            "\n\n📞 Want to talk to an advisor? Just ask!",
            "\n\n🎓 Ready to apply? I can share the admission steps.",
            "\n\n📅 Would you like to schedule a campus visit?",
        );
        if ( rand(0, 2) === 0 ) {
            $answer .= $cta_pool[ array_rand( $cta_pool ) ];
        }

        return $personalized . $answer;
    }

    /**
     * Generate an intelligent fallback when no KB entry matches.
     */
    public static function smart_fallback( $user_query, $lead = array(), $history = array() ) {
        $first_name = '';
        if ( ! empty( $lead['name'] ) ) {
            $first_name = explode( ' ', $lead['name'] )[0];
        }
        $course = $lead['course_interest'] ?? '';
        $site   = get_bloginfo( 'name' );

        // Greeting detection
        $normalized = self::normalize_advanced( $user_query );
        $greetings  = array( 'hi', 'hello', 'hey', 'good morning', 'good evening', 'good afternoon', 'namaste' );
        foreach ( $greetings as $g ) {
            if ( strpos( $normalized, $g ) !== false ) {
                $intro = $first_name ? "Hello {$first_name}! 👋" : "Hello! 👋";
                $course_line = $course ? " I see you're interested in **{$course}**." : '';
                return "{$intro}{$course_line} I'm the AI assistant for **{$site}**. Feel free to ask me anything about courses, admissions, fees, or campus life! How can I help you today?";
            }
        }

        // Thanks detection
        $thanks = array( 'thank', 'thanks', 'great', 'awesome', 'helpful', 'perfect', 'good' );
        foreach ( $thanks as $t ) {
            if ( strpos( $normalized, $t ) !== false ) {
                return $first_name 
                    ? "You're most welcome, {$first_name}! 😊 Is there anything else I can help you with?"
                    : "You're most welcome! 😊 Is there anything else I can help you with?";
            }
        }

        // Contact / human request
        $contact_triggers = array( 'speak', 'talk', 'contact', 'call', 'human', 'person', 'phone number', 'address', 'email' );
        foreach ( $contact_triggers as $t ) {
            if ( strpos( $normalized, $t ) !== false ) {
                return "To connect with our team directly, please visit our **Contact Us** page or reach out through the details listed there. Our counselors are available Monday to Saturday. 📞";
            }
        }

        // Generic intelligent fallback
        $fallback_db = SmartReplyr_DB::get_setting( 'fallback_message', '' );
        if ( ! empty( $fallback_db ) ) {
            return $fallback_db;
        }

        $name_part = $first_name ? ", {$first_name}" : '';
        return "I appreciate your question{$name_part}! This seems like something our admissions counselors would be able to answer best. You can ask me about **courses**, **fees**, **admissions process**, or **campus facilities** and I'll do my best to help. 🎓";
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
                $matched = 0;
                foreach ( $kws as $kw ) {
                    $kw_norm = self::normalize_advanced( $kw );
                    if ( ! empty( $kw_norm ) && strpos( $norm_query, $kw_norm ) !== false ) {
                        $matched++;
                    }
                }
                return min( 100, ( $matched / count( $kws ) ) * 100 );
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
        return min( 100, ( $matched / max( 1, count( $entry_tokens ) ) ) * 100 );
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
            'fees'          => array( 'fee', 'cost', 'price', 'charges', 'tuition', 'payment', 'how much', 'scholarship', 'discount' ),
            'admission'     => array( 'admissions', 'apply', 'application', 'enroll', 'enrollment', 'register', 'registration', 'join', 'entry', 'eligibility', 'eligible', 'qualify', 'qualification' ),
            'courses'       => array( 'course', 'program', 'programs', 'degree', 'degrees', 'stream', 'streams', 'study', 'subjects', 'specialization' ),
            'campus'        => array( 'college', 'institute', 'university', 'location', 'address', 'hostel', 'infrastructure', 'facility', 'facilities', 'lab', 'library' ),
            'placement'     => array( 'job', 'career', 'salary', 'hire', 'hiring', 'company', 'companies', 'corporate', 'jobss', 'employment', 'work' ),
            'duration'      => array( 'year', 'years', 'semester', 'months', 'long', 'length' ),
            'contact'       => array( 'phone', 'number', 'call', 'mail', 'email', 'helpline', 'whatsapp' ),
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
