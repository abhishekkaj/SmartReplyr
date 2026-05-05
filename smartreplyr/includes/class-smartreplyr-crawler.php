<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * SmartReplyr_Crawler — Website Content Scanner
 *
 * Scans all published WordPress pages and posts, extracts meaningful
 * content chunks (under headings), auto-generates keywords, and stores
 * them in the site_content table for the NLP engine to search against.
 *
 * Fully offline. No external API dependency.
 */
class SmartReplyr_Crawler {

    /** Maximum posts per batch to avoid memory/timeout issues */
    const BATCH_SIZE = 50;

    /** Minimum chunk length to be considered meaningful */
    const MIN_CHUNK_LENGTH = 30;

    /** Maximum chunk length before splitting */
    const MAX_CHUNK_LENGTH = 800;

    /** Number of top keywords to extract per chunk */
    const MAX_KEYWORDS = 10;

    /**
     * Crawl all published pages and posts.
     * Returns array with stats: pages_processed, chunks_created, errors
     */
    public static function crawl_all( $force_full = false ) {
        $stats = array(
            'pages_processed' => 0,
            'chunks_created'  => 0,
            'chunks_skipped'  => 0,
            'errors'          => 0,
        );

        if ( $force_full ) {
            SmartReplyr_DB::clear_site_content();
        }

        $args = array(
            'post_type'      => array( 'page', 'post' ),
            'post_status'    => 'publish',
            'posts_per_page' => self::BATCH_SIZE,
            'paged'          => 1,
            'orderby'        => 'modified',
            'order'          => 'DESC',
        );

        $page = 1;
        do {
            $args['paged'] = $page;
            $query = new WP_Query( $args );

            if ( ! $query->have_posts() ) break;

            while ( $query->have_posts() ) {
                $query->the_post();
                $post = get_post();

                try {
                    $result = self::process_post( $post, $force_full );
                    $stats['pages_processed']++;
                    $stats['chunks_created'] += $result['created'];
                    $stats['chunks_skipped'] += $result['skipped'];
                } catch ( Throwable $e ) {
                    $stats['errors']++;
                    error_log( '[SmartReplyr Crawler] Error processing post #' . $post->ID . ': ' . $e->getMessage() );
                }
            }

            wp_reset_postdata();
            $page++;

        } while ( $page <= $query->max_num_pages );

        // Save sync timestamp
        SmartReplyr_DB::update_setting( 'last_content_sync', current_time( 'mysql' ) );

        SmartReplyr_DB::add_log( 'crawler', 'sync', 'success',
            "Content sync completed: {$stats['pages_processed']} pages, {$stats['chunks_created']} chunks created, {$stats['chunks_skipped']} skipped",
            $stats
        );

        return $stats;
    }

    /**
     * Process a single post/page — extract and store content chunks.
     */
    private static function process_post( $post, $force_full = false ) {
        $result = array( 'created' => 0, 'skipped' => 0 );

        // If not force-full, delete existing chunks for this post first (incremental re-sync)
        if ( ! $force_full ) {
            SmartReplyr_DB::delete_site_content_by_post( $post->ID );
        }

        $title      = get_the_title( $post );
        $permalink  = get_permalink( $post );
        $raw_content = $post->post_content;

        // Skip empty posts
        if ( empty( trim( $raw_content ) ) ) return $result;

        // Extract structured chunks from content
        $chunks = self::extract_chunks( $raw_content, $title );

        foreach ( $chunks as $chunk ) {
            $text = trim( $chunk['text'] );
            if ( strlen( $text ) < self::MIN_CHUNK_LENGTH ) continue;

            $hash = hash( 'sha256', $text );

            // Skip if this exact content already exists (dedup)
            if ( SmartReplyr_DB::chunk_exists_by_hash( $hash ) ) {
                $result['skipped']++;
                continue;
            }

            // Auto-extract keywords
            $keywords = self::extract_keywords( $text . ' ' . $title . ' ' . $chunk['heading'] );

            SmartReplyr_DB::insert_site_chunk( array(
                'post_id'      => $post->ID,
                'post_type'    => $post->post_type,
                'title'        => $title,
                'heading'      => $chunk['heading'],
                'chunk_text'   => $text,
                'keywords'     => $keywords,
                'source_url'   => $permalink,
                'content_hash' => $hash,
            ) );

            $result['created']++;
        }

        return $result;
    }

    /**
     * Extract structured content chunks from raw post HTML.
     * Splits content by headings (H1–H4), then by paragraphs within each section.
     */
    private static function extract_chunks( $html, $page_title ) {
        $chunks = array();

        // Remove shortcodes, scripts, styles
        $html = strip_shortcodes( $html );
        $html = preg_replace( '/<script[^>]*>.*?<\/script>/is', '', $html );
        $html = preg_replace( '/<style[^>]*>.*?<\/style>/is', '', $html );

        // Split content by headings (H1–H4)
        // This captures: [heading_tag, heading_text, content_after_heading]
        $sections = preg_split(
            '/(<h[1-4][^>]*>.*?<\/h[1-4]>)/is',
            $html,
            -1,
            PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY
        );

        $current_heading = $page_title;
        $buffer = '';

        foreach ( $sections as $section ) {
            // Check if this section is a heading
            if ( preg_match( '/<h[1-4][^>]*>(.*?)<\/h[1-4]>/is', $section, $hm ) ) {
                // Flush previous buffer
                if ( ! empty( trim( $buffer ) ) ) {
                    $clean = self::clean_text( $buffer );
                    if ( strlen( $clean ) >= self::MIN_CHUNK_LENGTH ) {
                        $sub_chunks = self::split_long_text( $clean );
                        foreach ( $sub_chunks as $sc ) {
                            $chunks[] = array( 'heading' => $current_heading, 'text' => $sc );
                        }
                    }
                }
                $current_heading = self::clean_text( $hm[1] );
                $buffer = '';
            } else {
                $buffer .= ' ' . $section;
            }
        }

        // Flush final buffer
        if ( ! empty( trim( $buffer ) ) ) {
            $clean = self::clean_text( $buffer );
            if ( strlen( $clean ) >= self::MIN_CHUNK_LENGTH ) {
                $sub_chunks = self::split_long_text( $clean );
                foreach ( $sub_chunks as $sc ) {
                    $chunks[] = array( 'heading' => $current_heading, 'text' => $sc );
                }
            }
        }

        // If no chunks from heading-based split, treat entire content as one chunk
        if ( empty( $chunks ) ) {
            $full_text = self::clean_text( $html );
            if ( strlen( $full_text ) >= self::MIN_CHUNK_LENGTH ) {
                $sub_chunks = self::split_long_text( $full_text );
                foreach ( $sub_chunks as $sc ) {
                    $chunks[] = array( 'heading' => $page_title, 'text' => $sc );
                }
            }
        }

        return $chunks;
    }

    /**
     * Split long text into smaller chunks at sentence boundaries.
     */
    private static function split_long_text( $text ) {
        if ( strlen( $text ) <= self::MAX_CHUNK_LENGTH ) {
            return array( $text );
        }

        $chunks = array();
        // Split on sentence-ending punctuation followed by space
        $sentences = preg_split( '/(?<=[.!?])\s+/', $text, -1, PREG_SPLIT_NO_EMPTY );
        $current = '';

        foreach ( $sentences as $sentence ) {
            if ( strlen( $current . ' ' . $sentence ) > self::MAX_CHUNK_LENGTH && ! empty( $current ) ) {
                $chunks[] = trim( $current );
                $current = $sentence;
            } else {
                $current .= ( empty( $current ) ? '' : ' ' ) . $sentence;
            }
        }

        if ( ! empty( trim( $current ) ) ) {
            $chunks[] = trim( $current );
        }

        return $chunks;
    }

    /**
     * Clean raw HTML to plain readable text.
     */
    private static function clean_text( $html ) {
        // Convert common HTML entities and tags to readable text
        $text = wp_strip_all_tags( $html, true );
        $text = html_entity_decode( $text, ENT_QUOTES, 'UTF-8' );
        // Remove excessive whitespace
        $text = preg_replace( '/\s+/', ' ', $text );
        // Remove URLs
        $text = preg_replace( '/https?:\/\/\S+/', '', $text );
        return trim( $text );
    }

    /**
     * Extract top keywords from text using term-frequency scoring.
     * Returns array of top keywords (lowercased, deduplicated).
     */
    private static function extract_keywords( $text ) {
        $text = strtolower( $text );
        $text = preg_replace( '/[^a-z0-9\s\-]/u', ' ', $text );
        $text = preg_replace( '/\s+/', ' ', $text );

        $stopwords = array(
            'a','an','the','is','are','was','were','be','been','being',
            'have','has','had','do','does','did','will','would','could',
            'should','shall','may','might','can','must','need',
            'i','me','my','we','our','you','your','he','she','it','its',
            'they','them','their','this','that','these','those',
            'am','at','in','on','for','of','to','from','by','with',
            'and','or','but','so','if','then','else','when','where',
            'how','what','which','who','whom','why','not','no','nor',
            'up','out','about','into','over','after','before','between',
            'under','above','below','each','every','all','both','few',
            'more','most','other','some','such','than','too','very',
            'just','also','now','here','there','only','own','same',
            'please','tell','give','show','get','got','make','made',
            'know','think','want','like','well','still','back','even',
        );

        $words = explode( ' ', trim( $text ) );
        $freq = array();

        foreach ( $words as $word ) {
            $word = trim( $word, '-' );
            if ( strlen( $word ) < 3 ) continue;
            if ( in_array( $word, $stopwords ) ) continue;
            if ( is_numeric( $word ) ) continue;

            if ( ! isset( $freq[ $word ] ) ) $freq[ $word ] = 0;
            $freq[ $word ]++;
        }

        // Sort by frequency descending
        arsort( $freq );

        // Return top N keywords
        return array_slice( array_keys( $freq ), 0, self::MAX_KEYWORDS );
    }

    /**
     * Get count of crawlable posts (pages + posts).
     */
    public static function get_crawlable_count() {
        $args = array(
            'post_type'      => array( 'page', 'post' ),
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
        );
        $query = new WP_Query( $args );
        return $query->found_posts;
    }
}
