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

        // Threshold: 18 (low enough for short 1-2 word queries to match KB entries)
        return ( $highest_score >= 18 ) ? $best_match : null;
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

        // Short query boost: 1-2 word queries like "fees", "location", "placement" 
        // get deflated BM25 scores — compensate if tokens directly match
        if ( count( $user_tokens ) <= 2 ) {
            $direct_matches = 0;
            foreach ( $user_tokens as $ut ) {
                foreach ( $entry_tokens as $et ) {
                    if ( $ut === $et || self::soft_match( $ut, $et ) ) { $direct_matches++; break; }
                }
            }
            if ( $direct_matches > 0 ) {
                $boost = ( $direct_matches / count( $user_tokens ) ) * 20;
                $final = min( 100, $final + $boost );
            }
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

        $normalized = self::normalize_advanced( $user_query );
        $name_part = $first_name ? ", {$first_name}" : '';

        // ── 1. Greeting detection ──
        $greetings  = array( 'hi', 'hello', 'hey', 'good morning', 'good evening', 'good afternoon', 'namaste', 'hii', 'hiii', 'sup', 'yo' );
        foreach ( $greetings as $g ) {
            if ( strpos( $normalized, $g ) !== false || $normalized === $g ) {
                $intro = $first_name ? "Hello {$first_name}! 👋" : "Hello! 👋";
                $course_line = $course ? " I see you're interested in **{$course}** — great choice!" : '';
                return "{$intro}{$course_line} I'm the AI assistant for **{$site}**. I can help you with:\n\n📚 **Courses & Programs**\n💰 **Fees & Scholarships**\n📝 **Admissions & Eligibility**\n🏢 **Campus & Facilities**\n💼 **Placements & Career**\n\nWhat would you like to know?";
            }
        }

        // ── 2. Thanks / positive detection ──
        $thanks = array( 'thank', 'thanks', 'thx', 'awesome', 'helpful', 'perfect', 'nice', 'cool', 'okay', 'ok', 'got it', 'understood' );
        foreach ( $thanks as $t ) {
            if ( strpos( $normalized, $t ) !== false ) {
                $responses = array(
                    $first_name ? "You're most welcome, {$first_name}! 😊 Feel free to ask anything else about our programs." : "You're most welcome! 😊 Feel free to ask anything else about our programs.",
                    "Happy to help{$name_part}! 🎓 Is there anything else you'd like to know?",
                    "Glad I could assist{$name_part}! Let me know if you have more questions. 😊",
                );
                return $responses[ array_rand( $responses ) ];
            }
        }

        // ── 3. Goodbye detection ──
        $byes = array( 'bye', 'goodbye', 'see you', 'later', 'tata', 'done', 'that all', 'nothing' );
        foreach ( $byes as $b ) {
            if ( strpos( $normalized, $b ) !== false ) {
                return "Thank you for chatting with us{$name_part}! 🙏 If you ever need help again, I'm just a message away. Have a wonderful day! 🌟";
            }
        }

        // ── 4. Contact / human request ──
        $contact_triggers = array( 'speak', 'talk', 'contact', 'human', 'person', 'phone number', 'real person', 'agent', 'counselor', 'counsellor', 'advisor', 'representative' );
        foreach ( $contact_triggers as $t ) {
            if ( strpos( $normalized, $t ) !== false ) {
                return "I'd be happy to connect you with our team{$name_part}! 📞\n\nYou can reach our admissions office through the **Contact Us** page on our website. Our counselors are available **Monday to Saturday** and would love to help you personally.\n\n📧 You can also drop us an email for a detailed response!";
            }
        }

        // ── 5. Topic-specific intelligent fallbacks ──
        $topic_responses = array(
            'fees' => array(
                "keywords" => array( 'fees', 'cost', 'price', 'charges', 'tuition', 'payment', 'how much', 'money', 'expense', 'pay', 'amount', 'budget', 'afford', 'emi', 'installment', 'scholarship', 'discount', 'concession', 'waiver', 'stipend', 'financial' ),
                "response" => "Great question about fees{$name_part}! 💰\n\nOur fee structure varies by program and includes tuition, lab fees, and other components. We also offer:\n\n✅ **Merit-based scholarships**\n✅ **Flexible payment plans / EMI options**\n✅ **Financial aid** for eligible students\n\n" . ($course ? "For **{$course}** specifically, I'd recommend speaking with our admissions team for the exact breakdown." : "I'd recommend contacting our admissions office for the exact fee details for your preferred program.") . "\n\n📞 Would you like to connect with our fee counselor?"
            ),
            'admission' => array(
                "keywords" => array( 'admission', 'apply', 'application', 'enroll', 'register', 'join', 'entry', 'eligibility', 'eligible', 'qualify', 'qualification', 'qualifications', 'criteria', 'requirement', 'requirements', 'cutoff', 'merit', 'seat', 'intake', 'deadline', 'last date', 'documents' ),
                "response" => "I'd love to help with admissions{$name_part}! 📝\n\nHere's a quick overview of our typical admission process:\n\n1️⃣ **Check Eligibility** — Review minimum requirements for your chosen program\n2️⃣ **Submit Application** — Fill out our online application form\n3️⃣ **Documentation** — Upload required documents\n4️⃣ **Selection** — Based on merit/entrance exam scores\n5️⃣ **Confirmation** — Secure your seat with fee payment\n\n" . ($course ? "For **{$course}**, eligibility criteria may be specific — our admissions team can give you exact details." : "For program-specific eligibility and deadlines, I'd recommend reaching out to our admissions office.") . "\n\n🎓 Ready to apply? Visit our admissions page or ask me more!"
            ),
            'courses' => array(
                "keywords" => array( 'courses', 'program', 'degree', 'stream', 'study', 'subjects', 'specialization', 'branch', 'department', 'curriculum', 'syllabus', 'diploma', 'certificate', 'offer', 'available', 'list' ),
                "response" => "Great to see your interest in our programs{$name_part}! 📚\n\nWe offer a wide range of **undergraduate**, **postgraduate**, and **professional** programs across various disciplines. Each program is designed with industry-relevant curriculum and expert faculty.\n\n" . ($course ? "Since you're interested in **{$course}**, I can tell you it's one of our popular programs with excellent career prospects!" : "To explore our full list of programs and find the perfect fit for you, I'd recommend visiting our **Courses** page.") . "\n\nWould you like to know about specific courses, fees, or placement records?"
            ),
            'placement' => array(
                "keywords" => array( 'placement', 'job', 'career', 'salary', 'company', 'companies', 'package', 'ctc', 'lpa', 'recruiter', 'internship', 'intern', 'training', 'industry', 'opportunities', 'placed', 'hire', 'hiring', 'employment', 'work' ),
                "response" => "Placements are one of our strong suits{$name_part}! 💼\n\nOur placement cell actively works with top companies across industries to ensure our students get excellent career opportunities:\n\n🏆 **Strong placement track record**\n🤝 **Industry partnerships** with leading companies\n📈 **Competitive salary packages**\n🎯 **Internship opportunities** starting from early semesters\n👔 **Career counseling & soft skills training**\n\n" . ($course ? "**{$course}** graduates typically see great demand in the market!" : "") . "\n\nWant specific placement statistics? I'd recommend checking our placement page or connecting with our placement cell!"
            ),
            'campus' => array(
                "keywords" => array( 'campus', 'location', 'address', 'where', 'situated', 'infrastructure', 'facility', 'facilities', 'lab', 'library', 'sports', 'canteen', 'auditorium', 'wifi', 'gym', 'building', 'classroom', 'transport', 'bus', 'city' ),
                "response" => "Our campus is designed to provide a world-class learning environment{$name_part}! 🏛️\n\nHere's what makes our campus special:\n\n🏗️ **Modern Infrastructure** — State-of-the-art buildings & classrooms\n🔬 **Advanced Labs** — Well-equipped for practical learning\n📖 **Digital Library** — Extensive collection of resources\n🏃 **Sports Facilities** — Indoor & outdoor sports\n🍽️ **Canteen** — Hygienic food options\n🚌 **Transport** — Connectivity to nearby areas\n\nWould you like to **schedule a campus visit**? We'd love to show you around!"
            ),
            'hostel' => array(
                "keywords" => array( 'hostel', 'accommodation', 'stay', 'room', 'lodging', 'residence', 'residential', 'mess', 'food', 'boarding', 'dormitory', 'pg' ),
                "response" => "Great question about accommodation{$name_part}! 🏠\n\nWe offer comfortable and secure hostel facilities for both boys and girls:\n\n🛏️ **Well-furnished rooms** (Single/Shared options)\n🍛 **Nutritious mess food** (Veg & Non-veg)\n🔒 **24/7 Security** & CCTV surveillance\n📶 **Wi-Fi connectivity**\n🧺 **Laundry facilities**\n\nFor hostel fee details and availability, please contact our admissions office. We recommend applying early as seats fill up quickly!"
            ),
            'ranking' => array(
                "keywords" => array( 'ranking', 'rank', 'ranked', 'rating', 'accreditation', 'accredited', 'naac', 'nba', 'nirf', 'aicte', 'ugc', 'approved', 'recognition', 'reputation', 'review', 'reviews', 'best', 'top', 'tier' ),
                "response" => "Thank you for asking about our credentials{$name_part}! 🏅\n\nOur institution is committed to maintaining the highest standards of academic excellence:\n\n✅ **Recognized & Approved** by relevant regulatory bodies\n📊 **Strong academic track record**\n🎓 **Experienced faculty** with industry expertise\n🏆 **Quality certifications & accreditations**\n\nFor specific ranking details and accreditation certificates, please visit the **About Us** section on our website or contact our office.\n\nWould you like to know more about our specific programs?"
            ),
            'exam' => array(
                "keywords" => array( 'exam', 'examination', 'test', 'entrance', 'jee', 'neet', 'cat', 'mat', 'gate', 'gmat', 'gre', 'aptitude', 'competitive', 'cuet', 'cet' ),
                "response" => "Thanks for asking about entrance requirements{$name_part}! 📝\n\nDifferent programs may accept different entrance exam scores. Common ones include:\n\n📋 **Engineering** — JEE Main/Advanced, State CETs\n📋 **Medical** — NEET\n📋 **Management** — CAT, MAT, XAT\n📋 **Postgraduate** — GATE, CUET-PG\n\n" . ($course ? "For **{$course}** admissions, specific entrance exam requirements may apply." : "For your chosen program, our admissions team can confirm exactly which exams are accepted.") . "\n\nWould you like help with the application process?"
            ),
            'safety' => array(
                "keywords" => array( 'safety', 'safe', 'ragging', 'anti-ragging', 'security', 'women', 'girl', 'female', 'harassment', 'discipline', 'rules' ),
                "response" => "Student safety is our top priority{$name_part}! 🛡️\n\nWe maintain a strict **zero-tolerance policy** towards ragging and harassment:\n\n✅ **Anti-ragging committee** — Active monitoring & grievance redressal\n✅ **24/7 CCTV surveillance** across campus\n✅ **Dedicated security staff**\n✅ **Women's safety cell** — Ensuring a safe environment for all\n✅ **Strict code of conduct** — Enforced campus-wide\n\nOur campus is a safe, inclusive, and welcoming space for all students. Feel free to report any concerns through our grievance portal."
            ),
            'online' => array(
                "keywords" => array( 'online', 'distance', 'remote', 'virtual', 'digital', 'e-learning', 'correspondence', 'hybrid', 'blended' ),
                "response" => "Thanks for your interest in flexible learning options{$name_part}! 💻\n\nWe understand the need for accessible education. Please check with our admissions team about:\n\n📱 **Online/Distance programs** available\n🖥️ **Learning management systems** used\n📅 **Flexible scheduling options**\n🎥 **Recorded lectures** for revision\n\nFor the most up-to-date information on our distance and online programs, I'd recommend contacting our admissions office directly."
            ),
        );

        // Check if query matches any topic
        foreach ( $topic_responses as $topic => $data ) {
            foreach ( $data['keywords'] as $kw ) {
                if ( strpos( $normalized, $kw ) !== false ) {
                    return $data['response'];
                }
            }
        }

        // ── 6. Try to suggest related KB topics ──
        $kb_entries = SmartReplyr_DB::get_all_kb();
        if ( ! empty( $kb_entries ) ) {
            $suggestions = array();
            foreach ( $kb_entries as $entry ) {
                $q = strtolower( $entry['question'] );
                if ( count( $suggestions ) >= 3 ) break;
                // Check if any user token appears in the KB question
                $user_tokens = self::tokenize( $normalized );
                foreach ( $user_tokens as $ut ) {
                    if ( strlen( $ut ) > 2 && strpos( self::normalize_advanced( $q ), $ut ) !== false ) {
                        $suggestions[] = $entry['question'];
                        break;
                    }
                }
            }
            if ( ! empty( $suggestions ) ) {
                $list = implode( "\n", array_map( function($s) { return "• {$s}"; }, array_unique( $suggestions ) ) );
                return "I appreciate your question{$name_part}! While I don't have an exact answer, here are some related topics I can help with:\n\n{$list}\n\nYou can try asking about any of these, or ask me about **courses**, **fees**, **admissions**, **placements**, or **campus life**! 🎓";
            }
        }

        // ── 7. Ultimate generic fallback (still helpful) ──
        $fallback_db = SmartReplyr_DB::get_setting( 'fallback_message', '' );
        if ( ! empty( $fallback_db ) ) {
            return $fallback_db;
        }

        $generic_fallbacks = array(
            "I appreciate your question{$name_part}! I want to make sure I give you accurate information. Our admissions counselors would be best equipped to help with this.\n\nIn the meantime, I can definitely help you with:\n📚 **Courses & Programs**\n💰 **Fees & Scholarships**\n📝 **Admissions Process**\n💼 **Placements**\n🏛️ **Campus Facilities**\n\nWhat would you like to explore? 🎓",
            "That's a great question{$name_part}! Let me point you in the right direction. While I work on expanding my knowledge, you can:\n\n✅ Ask me about **courses, fees, or admissions**\n✅ Visit our website for detailed information\n✅ Connect with our counselors for personalized guidance\n\nWhat else can I help you with? 😊",
        );
        return $generic_fallbacks[ array_rand( $generic_fallbacks ) ];
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
