<div class="wrap edulead-wrap">
    <h1>EduLead AI Dashboard</h1>
    
    <?php
    $total_leads = EduLead_DB::count_leads();
    $today_leads = EduLead_DB::count_leads( array( 'date_from' => current_time('Y-m-d'), 'date_to' => current_time('Y-m-d') ) );
    
    global $wpdb;
    $t_conv = $wpdb->prefix . 'edulead_conversations';
    $total_convs = $wpdb->get_var( "SELECT COUNT(*) FROM $t_conv" );
    
    $recent_leads = EduLead_DB::get_leads( array( 'per_page' => 5 ) );
    ?>
    
    <div class="el-stats-grid">
        <div class="el-stat-card">
            <h3>Total Leads</h3>
            <div class="el-stat-num"><?php echo esc_html( $total_leads ); ?></div>
        </div>
        <div class="el-stat-card">
            <h3>Leads Today</h3>
            <div class="el-stat-num"><?php echo esc_html( $today_leads ); ?></div>
        </div>
        <div class="el-stat-card">
            <h3>Total Conversations</h3>
            <div class="el-stat-num"><?php echo esc_html( $total_convs ); ?></div>
        </div>
        <div class="el-stat-card">
            <h3>Conversion Rate</h3>
            <div class="el-stat-num">
                <?php echo $total_convs > 0 ? round( ($total_leads / $total_convs) * 100, 1 ) . '%' : '0%'; ?>
            </div>
        </div>
    </div>
    
    <div class="el-dashboard-columns">
        <div class="el-col">
            <div class="el-panel">
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
                    <p><a href="?page=edulead-leads" class="button button-secondary">View all leads</a></p>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="el-col">
            <div class="el-panel">
                <h2>Quick Actions</h2>
                <ul class="el-quick-actions">
                    <li><a href="?page=edulead-settings" class="button button-primary">Configure AI & API</a></li>
                    <li><a href="?page=edulead-kb" class="button button-secondary">Train Knowledge Base</a></li>
                    <li><a href="?page=edulead-settings&tab=webhook" class="button button-secondary">Set up CRM Webhook</a></li>
                </ul>
            </div>
        </div>
    </div>
</div>
