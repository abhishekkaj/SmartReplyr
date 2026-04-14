<div class="wrap edulead-wrap">
    <h1 class="wp-heading-inline">Leads</h1>
    <a href="#" id="edulead-export-csv" class="page-title-action">Export CSV</a>
    <hr class="wp-header-end">
    
    <?php
    $paged = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
    $per_page = 20;
    
    $args = array(
        'per_page' => $per_page,
        'offset'   => ($paged - 1) * $per_page,
        'search'   => isset($_POST['s']) ? sanitize_text_field($_POST['s']) : '',
    );
    
    $leads = EduLead_DB::get_leads( $args );
    $total = EduLead_DB::count_leads( $args );
    $num_pages = ceil( $total / $per_page );
    ?>
    
    <form method="post">
        <p class="search-box">
            <label class="screen-reader-text" for="post-search-input">Search Leads:</label>
            <input type="search" id="post-search-input" name="s" value="<?php echo esc_attr($args['search']); ?>">
            <input type="submit" id="search-submit" class="button" value="Search Leads">
        </p>
    </form>
    
    <div class="tablenav top">
        <div class="alignleft actions">
            <!-- Filter dropdowns could go here -->
        </div>
        <div class="tablenav-pages">
            <span class="displaying-num"><?php echo $total; ?> items</span>
            <!-- Pagination could go here -->
        </div>
    </div>
    
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th width="5%">ID</th>
                <th width="20%">Name & Contact</th>
                <th width="15%">Course Required</th>
                <th width="25%">Page Source (UTM)</th>
                <th width="15%">Integrations</th>
                <th width="20%">Date</th>
            </tr>
        </thead>
        <tbody>
            <?php if ( empty( $leads ) ) : ?>
                <tr>
                    <td colspan="6">No leads found.</td>
                </tr>
            <?php else : ?>
                <?php foreach ( $leads as $lead ) : ?>
                    <tr>
                        <td>#<?php echo esc_html( $lead['id'] ); ?></td>
                        <td>
                            <strong><?php echo esc_html( $lead['name'] ); ?></strong><br>
                            <a href="mailto:<?php echo esc_attr( $lead['email'] ); ?>"><?php echo esc_html( $lead['email'] ); ?></a><br>
                            <?php echo esc_html( $lead['phone'] ); ?>
                        </td>
                        <td>
                            <span class="el-badge"><?php echo esc_html( $lead['course_interest'] ?: 'General' ); ?></span>
                        </td>
                        <td>
                            <a href="<?php echo esc_url( $lead['page_url'] ); ?>" target="_blank" title="<?php echo esc_attr( $lead['page_title'] ); ?>">
                                <?php echo esc_html( wp_trim_words( $lead['page_title'] ?: $lead['page_url'], 5 ) ); ?>
                            </a>
                            <?php if ( ! empty( $lead['utm_source'] ) ) : ?>
                                <br><small class="el-utm">[<?php echo esc_html( $lead['utm_source'] ); ?> / <?php echo esc_html( $lead['utm_medium'] ); ?>]</small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="el-status-icon <?php echo $lead['webhook_sent'] ? 'green' : 'gray'; ?>" title="Webhook CRM Delivery">Sync</span>
                            <span class="el-status-icon <?php echo $lead['email_sent'] ? 'green' : 'gray'; ?>" title="Email Delivery">Mail</span>
                        </td>
                        <td>
                            <?php echo esc_html( wp_date( get_option('date_format') . ' ' . get_option('time_format'), strtotime( $lead['created_at'] ) ) ); ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
        <tfoot>
            <tr>
                <th>ID</th>
                <th>Name & Contact</th>
                <th>Course Required</th>
                <th>Page Source (UTM)</th>
                <th>Integrations</th>
                <th>Date</th>
            </tr>
        </tfoot>
    </table>
</div>
