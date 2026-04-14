<div class="wrap smartreplyr-wrap">
    <h1>SmartReplyr Dashboard</h1>
    
    <?php
    $total_leads = SmartReplyr_DB::count_leads();
    $today_leads = SmartReplyr_DB::count_leads( array( 'date_from' => current_time('Y-m-d'), 'date_to' => current_time('Y-m-d') ) );
    
    global $wpdb;
    $t_conv = SmartReplyr_DB::get_conversations_table();
    $total_convs = $wpdb->get_var( "SELECT COUNT(*) FROM $t_conv" );
    
    $recent_leads = SmartReplyr_DB::get_leads( array( 'per_page' => 5 ) );
    ?>
    
    <div class="sr-stats-grid">
        <div class="sr-stat-card">
            <h3>Total Leads</h3>
            <div class="sr-stat-num"><?php echo esc_html( $total_leads ); ?></div>
        </div>
        <div class="sr-stat-card">
            <h3>Leads Today</h3>
            <div class="sr-stat-num"><?php echo esc_html( $today_leads ); ?></div>
        </div>
        <div class="sr-stat-card">
            <h3>Total Conversations</h3>
            <div class="sr-stat-num"><?php echo esc_html( $total_convs ); ?></div>
        </div>
        <div class="sr-stat-card">
            <h3>Conversion Rate</h3>
            <div class="sr-stat-num">
                <?php echo $total_convs > 0 ? round( ($total_leads / $total_convs) * 100, 1 ) . '%' : '0%'; ?>
            </div>
        </div>
    </div>
    
    <div class="sr-dashboard-columns">
        <div class="sr-col">
            <div class="sr-panel">
                <h2>Recent Leads</h2>
                <?php if ( empty( $recent_leads ) ) : ?>
                    <p>No leads yet. Set up your widget and start capturing!</p>
                <?php else : ?>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Course</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ( $recent_leads as $lead ) : ?>
                                <tr>
                                    <td>
                                        <strong><?php echo esc_html( $lead['name'] ); ?></strong><br>
                                        <small><?php echo esc_html( $lead['email'] ); ?></small>
                                    </td>
                                    <td><?php echo esc_html( $lead['course_interest'] ?: '-' ); ?></td>
                                    <td><?php echo esc_html( date_i18n( get_option('date_format'), strtotime( $lead['created_at'] ) ) ); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <p><a href="?page=smartreplyr-leads" class="button button-secondary">View all leads</a></p>
                <?php endif; ?>
            </div>

            <div class="sr-panel" style="margin-top: 20px;">
                <h2>System Health</h2>
                <ul style="list-style-type: disc; margin-left: 20px;">
                    <li><strong>DB Status:</strong> Version <?php echo esc_html( get_option( 'smartreplyr_db_version', 'N/A' ) ); ?> (Stable)</li>
                    <li><strong>Knowledge Base:</strong> <?php echo count( SmartReplyr_DB::get_all_kb() ); ?> Entries</li>
                    <li><strong>Webhook Status:</strong> <?php echo SmartReplyr_DB::get_setting( 'webhook_enabled', '0' ) === '1' ? '<span style="color: green;">Active</span>' : '<span style="color: gray;">Inactive</span>'; ?></li>
                </ul>
            </div>
        </div>
        
        <div class="sr-col">
            <div class="sr-panel">
                <h2>Quick Actions</h2>
                <ul class="sr-quick-actions">
                    <li><a href="?page=smartreplyr-settings" class="button button-primary">Configure AI & API</a></li>
                    <li><a href="?page=smartreplyr-kb" class="button button-secondary">Train Knowledge Base</a></li>
                    <li><a href="?page=smartreplyr-settings&tab=webhook" class="button button-secondary">Set up CRM Webhook</a></li>
                </ul>
            </div>
        </div>
    </div>
</div>
