<?php
if ( ! defined( 'ABSPATH' ) ) exit;

$stats = SmartReplyr_DB::get_site_content_stats();
$crawlable = SmartReplyr_Crawler::get_crawlable_count();
$last_sync = $stats['last_sync'] ? date( 'M j, Y g:i A', strtotime( $stats['last_sync'] ) ) : 'Never';

// Get recent chunks for preview
$recent_chunks = SmartReplyr_DB::get_site_content_paginated( 25, 0 );
?>
<div class="wrap smartreplyr-wrap">
    <h1>🔍 Website Content Scanner</h1>
    <p style="font-size:14px;color:#555;">Automatically scan your website's pages and posts to build a knowledge base. The chatbot will use this content to answer visitor questions — <strong>no API required</strong>.</p>

    <!-- Stats Cards -->
    <div style="display:flex;gap:15px;margin:20px 0;flex-wrap:wrap;">
        <div style="background:#fff;border:1px solid #ddd;border-left:4px solid #6C5CE7;padding:15px 20px;border-radius:4px;min-width:180px;">
            <div style="font-size:28px;font-weight:700;color:#6C5CE7;" id="sr-stat-pages"><?php echo esc_html( $stats['total_pages'] ); ?></div>
            <div style="font-size:13px;color:#777;margin-top:4px;">Pages Indexed</div>
        </div>
        <div style="background:#fff;border:1px solid #ddd;border-left:4px solid #00b894;padding:15px 20px;border-radius:4px;min-width:180px;">
            <div style="font-size:28px;font-weight:700;color:#00b894;" id="sr-stat-chunks"><?php echo esc_html( $stats['total_chunks'] ); ?></div>
            <div style="font-size:13px;color:#777;margin-top:4px;">Content Chunks</div>
        </div>
        <div style="background:#fff;border:1px solid #ddd;border-left:4px solid #fdcb6e;padding:15px 20px;border-radius:4px;min-width:180px;">
            <div style="font-size:28px;font-weight:700;color:#636e72;" id="sr-stat-crawlable"><?php echo esc_html( $crawlable ); ?></div>
            <div style="font-size:13px;color:#777;margin-top:4px;">Available Pages/Posts</div>
        </div>
        <div style="background:#fff;border:1px solid #ddd;border-left:4px solid #74b9ff;padding:15px 20px;border-radius:4px;min-width:180px;">
            <div style="font-size:13px;font-weight:600;color:#2d3436;margin-top:5px;" id="sr-stat-lastsync"><?php echo esc_html( $last_sync ); ?></div>
            <div style="font-size:13px;color:#777;margin-top:4px;">Last Sync</div>
        </div>
    </div>

    <!-- Action Buttons -->
    <div style="background:#fff;border:1px solid #ddd;padding:20px;border-radius:4px;margin-bottom:20px;">
        <h3 style="margin-top:0;">🔄 Sync Controls</h3>
        <p style="color:#555;font-size:13px;">Click "Sync Now" to scan all published pages and posts. The crawler will extract headings, paragraphs, and auto-generate keywords for intelligent matching.</p>

        <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
            <button type="button" class="button button-primary button-hero" id="sr-sync-btn" style="font-size:14px;">
                🔄 Sync Website Content
            </button>
            <button type="button" class="button" id="sr-resync-btn" style="font-size:13px;">
                ♻️ Clear & Full Re-Sync
            </button>
        </div>

        <div id="sr-sync-status" style="margin-top:12px;display:none;">
            <div style="background:#f0f0f1;border-radius:4px;padding:12px 15px;">
                <span class="spinner is-active" style="float:none;margin:0 8px 0 0;"></span>
                <span id="sr-sync-message" style="font-size:13px;">Scanning website content...</span>
            </div>
        </div>

        <div id="sr-sync-result" style="margin-top:12px;display:none;">
            <div class="notice notice-success inline" style="margin:0;">
                <p id="sr-sync-result-text"></p>
            </div>
        </div>
    </div>

    <!-- How It Works -->
    <div style="background:#fff;border:1px solid #ddd;padding:20px;border-radius:4px;margin-bottom:20px;">
        <h3 style="margin-top:0;">📖 How It Works</h3>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:15px;">
            <div style="padding:12px;background:#f8f9fa;border-radius:6px;text-align:center;">
                <div style="font-size:28px;margin-bottom:6px;">📄</div>
                <strong>1. Scan</strong>
                <p style="font-size:12px;color:#666;margin:5px 0 0;">Crawls all published pages & posts</p>
            </div>
            <div style="padding:12px;background:#f8f9fa;border-radius:6px;text-align:center;">
                <div style="font-size:28px;margin-bottom:6px;">✂️</div>
                <strong>2. Chunk</strong>
                <p style="font-size:12px;color:#666;margin:5px 0 0;">Splits content into searchable segments by heading</p>
            </div>
            <div style="padding:12px;background:#f8f9fa;border-radius:6px;text-align:center;">
                <div style="font-size:28px;margin-bottom:6px;">🏷️</div>
                <strong>3. Keywords</strong>
                <p style="font-size:12px;color:#666;margin:5px 0 0;">Auto-extracts top keywords per chunk</p>
            </div>
            <div style="padding:12px;background:#f8f9fa;border-radius:6px;text-align:center;">
                <div style="font-size:28px;margin-bottom:6px;">🤖</div>
                <strong>4. Match</strong>
                <p style="font-size:12px;color:#666;margin:5px 0 0;">Chatbot answers using BM25 scoring</p>
            </div>
        </div>
    </div>

    <!-- Content Preview Table -->
    <?php if ( ! empty( $recent_chunks ) ) : ?>
    <div style="background:#fff;border:1px solid #ddd;padding:20px;border-radius:4px;">
        <h3 style="margin-top:0;">📋 Indexed Content Preview <span style="font-size:12px;font-weight:400;color:#999;">(showing latest 25 chunks)</span></h3>
        <table class="widefat striped" style="margin-top:10px;">
            <thead>
                <tr>
                    <th style="width:15%;">Page</th>
                    <th style="width:15%;">Heading</th>
                    <th style="width:45%;">Content</th>
                    <th style="width:15%;">Keywords</th>
                    <th style="width:10%;">Type</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $recent_chunks as $chunk ) :
                    $keywords = json_decode( $chunk['keywords'], true );
                    $kw_str = is_array( $keywords ) ? implode( ', ', array_slice( $keywords, 0, 5 ) ) : '';
                    $excerpt = mb_strlen( $chunk['chunk_text'] ) > 150 ? mb_substr( $chunk['chunk_text'], 0, 150 ) . '...' : $chunk['chunk_text'];
                ?>
                <tr>
                    <td>
                        <a href="<?php echo esc_url( $chunk['source_url'] ); ?>" target="_blank" style="text-decoration:none;font-weight:500;">
                            <?php echo esc_html( mb_strimwidth( $chunk['title'], 0, 40, '...' ) ); ?>
                        </a>
                    </td>
                    <td style="font-size:12px;color:#555;"><?php echo esc_html( mb_strimwidth( $chunk['heading'], 0, 40, '...' ) ); ?></td>
                    <td style="font-size:12px;color:#333;line-height:1.5;"><?php echo esc_html( $excerpt ); ?></td>
                    <td style="font-size:11px;color:#6C5CE7;"><?php echo esc_html( $kw_str ); ?></td>
                    <td><span style="background:#eee;padding:2px 8px;border-radius:3px;font-size:11px;"><?php echo esc_html( $chunk['post_type'] ); ?></span></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<script>
jQuery(function($) {
    var nonce = '<?php echo esc_js( wp_create_nonce( 'smartreplyr_admin_nonce' ) ); ?>';

    function doSync(forceFull) {
        $('#sr-sync-status').show();
        $('#sr-sync-result').hide();
        $('#sr-sync-btn, #sr-resync-btn').prop('disabled', true);
        var msg = forceFull ? 'Clearing & re-scanning all content...' : 'Scanning website content...';
        $('#sr-sync-message').text(msg);

        $.ajax({
            url: ajaxurl,
            method: 'POST',
            data: {
                action: 'smartreplyr_sync_content',
                nonce: nonce,
                force_full: forceFull ? '1' : '0'
            },
            success: function(res) {
                $('#sr-sync-status').hide();
                $('#sr-sync-btn, #sr-resync-btn').prop('disabled', false);
                if (res.success) {
                    $('#sr-sync-result').show();
                    $('#sr-sync-result-text').text(res.data.message);
                    $('#sr-stat-pages').text(res.data.total_pages);
                    $('#sr-stat-chunks').text(res.data.total_chunks);
                    $('#sr-stat-lastsync').text(res.data.last_sync || 'Just now');
                } else {
                    alert('Sync failed: ' + (res.data || 'Unknown error'));
                }
            },
            error: function() {
                $('#sr-sync-status').hide();
                $('#sr-sync-btn, #sr-resync-btn').prop('disabled', false);
                alert('Request failed. Please try again.');
            }
        });
    }

    $('#sr-sync-btn').on('click', function() { doSync(false); });
    $('#sr-resync-btn').on('click', function() {
        if (confirm('This will clear all crawled content and re-scan everything. Continue?')) {
            doSync(true);
        }
    });
});
</script>
