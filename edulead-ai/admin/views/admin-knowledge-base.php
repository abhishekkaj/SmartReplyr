<div class="wrap edulead-wrap">
    <h1 class="wp-heading-inline">Knowledge Base</h1>
    <a href="#" class="page-title-action btn-add-kb">Add New Q&A</a>
    <hr class="wp-header-end">
    
    <div class="notice notice-info">
        <p>This knowledge base trains the AI. When a student asks a question, the AI will search these entries first before falling back to generic answers.</p>
    </div>
    
    <div class="el-kb-layout">
        <div class="el-kb-main">
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th width="30%">Question / Trigger</th>
                        <th width="50%">Answer Context</th>
                        <th width="10%">Category</th>
                        <th width="10%">Actions</th>
                    </tr>
                </thead>
                <tbody id="kb-list">
                    <?php
                    $kbs = EduLead_DB::get_all_kb();
                    if ( empty( $kbs ) ) : ?>
                        <tr class="no-items"><td colspan="4">No knowledge base entries found. Add your first Q&A to train the AI.</td></tr>
                    <?php else : ?>
                        <?php foreach ( $kbs as $kb ) : ?>
                            <tr data-id="<?php echo esc_attr( $kb['id'] ); ?>">
                                <td><strong><?php echo esc_html( $kb['question'] ); ?></strong></td>
                                <td><?php echo esc_html( wp_trim_words( strip_tags( $kb['answer'] ), 20 ) ); ?></td>
                                <td>
                                    <span class="el-badge"><?php echo esc_html( $kb['category'] ); ?></span>
                                    <?php if ( ! empty( $kb['intent'] ) ) : ?>
                                        <br><span class="el-badge" style="background:#000;color:#fff;font-size:10px;margin-top:4px;display:inline-block;"><?php echo esc_html( $kb['intent'] ); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <button class="button button-small btn-edit-kb" data-json="<?php echo esc_attr( wp_json_encode( $kb ) ); ?>">Edit</button>
                                    <button class="button button-small btn-delete-kb" style="color:#d63638;">Delete</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <div class="el-kb-sidebar" id="kb-editor-sidebar" style="display:none;">
            <div class="el-panel">
                <h3 id="kb-form-title">Add Q&A</h3>
                <form id="kb-form">
                    <input type="hidden" id="kb_id" name="id" value="">
                    
                    <div class="el-form-group">
                        <label for="kb_question">Question / Topic</label>
                        <textarea id="kb_question" name="question" required rows="2" placeholder="e.g., What are the MBA admission criteria?"></textarea>
                    </div>
                    
                    <div class="el-form-group">
                        <label for="kb_answer">AI Answer Context</label>
                        <textarea id="kb_answer" name="answer" required rows="6" placeholder="Provide the factual answer here. The AI will use this to generate a conversational response."></textarea>
                    </div>

                    <div class="el-form-group">
                        <label for="kb_keywords">Keywords (Comma separated)</label>
                        <input type="text" id="kb_keywords" name="keywords" placeholder="e.g. pathway, uk, course">
                    </div>
                    
                    <div class="el-form-group">
                        <label for="kb_intent">Intent</label>
                        <input type="text" id="kb_intent" name="intent" placeholder="e.g. admissions">
                    </div>
                    
                    <div class="el-form-group">
                        <label for="kb_category">Category</label>
                        <input type="text" id="kb_category" name="category" value="general" required>
                    </div>
                    
                    <div class="el-form-actions">
                        <button type="submit" class="button button-primary">Save Entry</button>
                        <button type="button" class="button btn-cancel-kb">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
