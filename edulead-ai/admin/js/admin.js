(function($) {
    'use strict';

    $(document).ready(function() {

        // --- Export CSV ---
        $('#edulead-export-csv').on('click', function(e) {
            e.preventDefault();
            window.location.href = eduleadAdmin.ajax_url + '?action=edulead_export_csv&nonce=' + eduleadAdmin.nonce;
        });

        // --- Avatar Upload (WP Media) ---
        let mediaUploader;
        $('#el-upload-avatar').on('click', function(e) {
            e.preventDefault();
            if (mediaUploader) {
                mediaUploader.open();
                return;
            }
            mediaUploader = wp.media({
                title: 'Choose Bot Avatar',
                button: { text: 'Use this image' },
                multiple: false
            });
            mediaUploader.on('select', function() {
                const attachment = mediaUploader.state().get('selection').first().toJSON();
                $('#avatar_url').val(attachment.url);
                $('#el-avatar-preview').attr('src', attachment.url);
            });
            mediaUploader.open();
        });

        // --- Conversations Viewer ---
        $('.el-conv-item').on('click', function() {
            $('.el-conv-item').removeClass('active');
            $(this).addClass('active');

            const id = $(this).data('id');
            const messagesStr = $(this).attr('data-messages');
            
            let messages = [];
            try {
                messages = JSON.parse(messagesStr) || [];
            } catch (e) {
                messages = [];
            }

            const name = $(this).find('h4').text();
            
            $('#cv-name').text(name);
            $('#cv-messages').html('');

            if (messages.length === 0) {
                $('#cv-messages').html('<div class="el-viewer-placeholder">No messages in this conversation.</div>');
                return;
            }

            messages.forEach(function(msg) {
                const isUser = msg.role === 'user';
                const wrapClass = isUser ? 'user' : 'assistant';
                
                // Format timestamp
                let timeStr = '';
                if (msg.timestamp) {
                    const d = new Date(msg.timestamp.replace(' ', 'T'));
                    timeStr = d.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
                }

                const html = `
                    <div class="el-msg ${wrapClass}">
                        <div class="el-msg-bubble">
                            ${formatText(msg.content)}
                        </div>
                        <span class="el-msg-time">${timeStr}</span>
                    </div>
                `;
                $('#cv-messages').append(html);
            });

            // Scroll to bottom
            const container = $('#cv-messages');
            container.scrollTop(container.prop("scrollHeight"));
        });

        // Initialize first conversation if exists
        if ($('.el-conv-item.active').length) {
            $('.el-conv-item.active').trigger('click');
        }

        // --- Knowledge Base ---
        
        // Show Add Form
        $('.btn-add-kb').on('click', function(e) {
            e.preventDefault();
            $('#kb_id').val('');
            $('#kb_question').val('');
            $('#kb_answer').val('');
            $('#kb_keywords').val('');
            $('#kb_intent').val('');
            $('#kb_category').val('general');
            $('#kb-form-title').text('Add Q&A');
            $('.el-kb-layout').addClass('has-sidebar');
            $('#kb-editor-sidebar').show();
        });

        // Hide Form
        $('.btn-cancel-kb').on('click', function() {
            $('.el-kb-layout').removeClass('has-sidebar');
            setTimeout(() => $('#kb-editor-sidebar').hide(), 300);
        });

        // Edit Entry
        $(document).on('click', '.btn-edit-kb', function() {
            const dataStr = $(this).attr('data-json');
            let data = {};
            try { data = JSON.parse(dataStr); } catch(e){}

            if (data.id) {
                $('#kb_id').val(data.id);
                $('#kb_question').val(data.question);
                $('#kb_answer').val(data.answer);
                $('#kb_intent').val(data.intent || '');
                $('#kb_category').val(data.category || 'general');
                
                // Parse keywords array back to string
                let kw = '';
                try {
                    const parsed = JSON.parse(data.keywords);
                    if (Array.isArray(parsed)) kw = parsed.join(', ');
                } catch(e) {}
                $('#kb_keywords').val(kw);

                $('#kb-form-title').text('Edit Q&A');
                
                $('.el-kb-layout').addClass('has-sidebar');
                $('#kb-editor-sidebar').show();
                $('html, body').animate({ scrollTop: 0 }, 'slow');
            }
        });

        // Save Entry (AJAX)
        $('#kb-form').on('submit', function(e) {
            e.preventDefault();
            
            const btn = $(this).find('button[type="submit"]');
            const originalText = btn.text();
            btn.text('Saving...').prop('disabled', true);

            const data = {
                action: 'edulead_save_kb',
                nonce: eduleadAdmin.nonce,
                id: $('#kb_id').val(),
                question: $('#kb_question').val(),
                answer: $('#kb_answer').val(),
                keywords: $('#kb_keywords').val(),
                intent: $('#kb_intent').val(),
                category: $('#kb_category').val()
            };

            $.post(eduleadAdmin.ajax_url, data, function(response) {
                btn.text(originalText).prop('disabled', false);
                if (response.success) {
                    location.reload(); // Simple reload for now to refresh table
                } else {
                    alert('Error saving entry: ' + (response.data || 'Unknown error'));
                }
            });
        });

        // Delete Entry (AJAX)
        $(document).on('click', '.btn-delete-kb', function() {
            if (!confirm('Are you sure you want to delete this entry? The AI will no longer use it.')) {
                return;
            }

            const tr = $(this).closest('tr');
            const id = tr.data('id');

            $.post(eduleadAdmin.ajax_url, {
                action: 'edulead_delete_kb',
                nonce: eduleadAdmin.nonce,
                id: id
            }, function(response) {
                if (response.success) {
                    tr.fadeOut(300, function() { $(this).remove(); });
                } else {
                    alert('Error deleting entry');
                }
            });
        });

        // --- Format Markdown to simple HTML for chat preview ---
        function formatText(text) {
            if (!text) return '';
            let html = text
                .replace(/\n\n/g, '</p><p>')
                .replace(/\n/g, '<br>')
                .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
                .replace(/\*(.*?)\*/g, '<em>$1</em>')
                .replace(/`(.*?)`/g, '<code>$1</code>');
            return `<p>${html}</p>`;
        }
    });

})(jQuery);
