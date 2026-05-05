<div class="wrap smartreplyr-wrap">
    <h1 class="wp-heading-inline">Knowledge Base</h1>
    <a href="#" class="page-title-action btn-add-kb">Add New Q&A</a>
    <a href="#" class="page-title-action btn-import-kb">Import Excel/CSV</a>
    <a href="#" id="smartreplyr-download-kb-template" class="page-title-action">Download Template</a>
    <hr class="wp-header-end">
    
    <div class="notice notice-info">
        <p>This knowledge base trains the AI. When a student asks a question, the AI will search these entries first before falling back to generic answers. The AI requires a strict 65% confidence match to answer.</p>
    </div>

    <!-- Import KB UI (Hidden by default) -->
    <div id="sr-import-kb-wrapper" style="display:none; background:#fff; padding:20px; border:1px solid #ccd0d4; box-shadow:0 1px 1px rgba(0,0,0,.04); margin-bottom:20px;">
        <h3>Import Knowledge Base</h3>
        <p>Upload an Excel (.xlsx) or CSV file to bulk add entries to the Knowledge Base.</p>
        <form id="sr-import-kb-form" enctype="multipart/form-data">
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="kb_file">Select File</label></th>
                    <td>
                        <input type="file" name="kb_file" id="kb_file" accept=".csv, .xlsx" required>
                        <p class="description">Max file size: 5MB.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Import Mode</th>
                    <td>
                        <label><input type="radio" name="import_mode" value="append" checked> <strong>Append</strong> — Add new entries to the existing KB.</label><br>
                        <label><input type="radio" name="import_mode" value="replace"> <strong style="color:#d63638;">Replace</strong> — Delete all existing entries and replace with the uploaded file.</label>
                    </td>
                </tr>
            </table>
            <p class="submit">
                <button type="submit" class="button button-primary" id="btn-submit-import">Start Import</button>
                <button type="button" class="button" id="btn-cancel-import">Cancel</button>
            </p>
        </form>

        <div id="sr-import-results" style="display:none; margin-top:20px; padding:15px; border-left:4px solid #2271b1; background:#f6f7f7;">
            <h4>Import Summary</h4>
            <p id="sr-import-msg"><strong>Processing...</strong></p>
            <ul id="sr-import-errors" style="color:#d63638; list-style-type:disc; padding-left:20px;"></ul>
        </div>
    </div>
    
    <div class="sr-kb-layout">
        <div class="sr-kb-main">
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
                    $kbs = SmartReplyr_DB::get_all_kb();
                    if ( empty( $kbs ) ) : ?>
                        <tr class="no-items"><td colspan="4">No knowledge base entries found. Add your first Q&A to train the AI.</td></tr>
                    <?php else : ?>
                        <?php foreach ( $kbs as $kb ) : ?>
                            <tr data-id="<?php echo esc_attr( $kb['id'] ); ?>">
                                <td><strong><?php echo esc_html( $kb['question'] ); ?></strong></td>
                                <td><?php echo esc_html( wp_trim_words( strip_tags( $kb['answer'] ), 20 ) ); ?></td>
                                <td>
                                    <span class="sr-badge"><?php echo esc_html( $kb['category'] ); ?></span>
                                    <?php if ( ! empty( $kb['intent'] ) ) : ?>
                                        <br><span class="sr-badge" style="background:#000;color:#fff;font-size:10px;margin-top:4px;display:inline-block;"><?php echo esc_html( $kb['intent'] ); ?></span>
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
        
        <div class="sr-kb-sidebar" id="kb-editor-sidebar" style="display:none;">
            <div class="sr-panel">
                <h3 id="kb-form-title">Add Q&A</h3>
                <form id="kb-form">
                    <input type="hidden" id="kb_id" name="id" value="">
                    
                    <div class="sr-form-group">
                        <label for="kb_question">Question / Topic</label>
                        <textarea id="kb_question" name="question" required rows="2" placeholder="e.g., What are the MBA admission criteria?"></textarea>
                    </div>
                    
                    <div class="sr-form-group">
                        <label for="kb_answer">AI Answer Context</label>
                        <textarea id="kb_answer" name="answer" required rows="6" placeholder="Provide the factual answer here. The AI will use this to generate a conversational response."></textarea>
                    </div>

                    <div class="sr-form-group">
                        <label for="kb_keywords">Keywords (Comma separated)</label>
                        <input type="text" id="kb_keywords" name="keywords" placeholder="e.g. pathway, uk, course">
                    </div>
                    
                    <div class="sr-form-group">
                        <label for="kb_intent">Intent</label>
                        <input type="text" id="kb_intent" name="intent" placeholder="e.g. admissions">
                    </div>
                    
                    <div class="sr-form-group">
                        <label for="kb_category">Category</label>
                        <input type="text" id="kb_category" name="category" value="general" required>
                    </div>
                    
                    <div class="sr-form-actions">
                        <button type="submit" class="button button-primary">Save Entry</button>
                        <button type="button" class="button btn-cancel-kb">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
