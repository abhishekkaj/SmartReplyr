<div class="wrap edulead-wrap">
    <h1 class="wp-heading-inline">Conversation Logs</h1>
    <hr class="wp-header-end">
    
    <?php
    $paged = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
    $per_page = 15;
    
    $args = array(
        'per_page' => $per_page,
        'offset'   => ($paged - 1) * $per_page,
    );
    
    $conversations = EduLead_DB::get_conversations( $args );
    ?>
    
    <div class="el-conversations-layout">
        <div class="el-conv-list">
            <?php if ( empty( $conversations ) ) : ?>
                <div class="el-empty">No conversations yet.</div>
            <?php else : ?>
                <?php foreach ( $conversations as $index => $conv ) : ?>
                    <div class="el-conv-item <?php echo $index === 0 ? 'active' : ''; ?>" data-id="<?php echo esc_attr( $conv['id'] ); ?>" data-messages="<?php echo esc_attr( $conv['messages'] ); ?>">
                        <h4><?php echo esc_html( $conv['name'] ); ?></h4>
                        <div class="el-conv-meta">
                            <span><?php echo esc_html( wp_trim_words( $conv['page_context'], 3 ) ); ?></span>
                            <span><?php echo esc_html( wp_date( get_option('date_format'), strtotime( $conv['updated_at'] ) ) ); ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <div class="el-conv-viewer">
            <?php if ( empty( $conversations ) ) : ?>
                <div class="el-viewer-placeholder">Select a conversation to view log</div>
            <?php else : ?>
                <div class="el-viewer-header">
                    <h3 id="cv-name"><?php echo esc_html( $conversations[0]['name'] ); ?></h3>
                    <div class="cv-meta">
                        <a href="mailto:<?php echo esc_attr( $conversations[0]['email'] ); ?>" id="cv-email"><?php echo esc_html( $conversations[0]['email'] ); ?></a>
                        | <a href="tel:<?php echo esc_attr( $conversations[0]['phone'] ); ?>" id="cv-phone"><?php echo esc_html( $conversations[0]['phone'] ); ?></a>
                    </div>
                </div>
                <div class="el-viewer-body" id="cv-messages">
                    <!-- populated by JS -->
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
