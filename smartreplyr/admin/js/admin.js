(function($) {
    'use strict';

    $(document).ready(function() {

        // --- Export CSV ---
        $('#smartreplyr-export-csv').on('click', function(e) {
            e.preventDefault();
            window.location.href = smartreplyrAdmin.ajax_url + '?action=smartreplyr_export_csv&nonce=' + smartreplyrAdmin.nonce;
        });

        // --- Avatar Upload (WP Media) ---
        let mediaUploader;
        $(document).on('click', '#sr-upload-avatar', function(e) {
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
                $('#sr-avatar-preview').attr('src', attachment.url);
            });
            mediaUploader.open();
        });

        // --- Conversations Viewer ---
        $('.sr-conv-item').on('click', function() {
            $('.sr-conv-item').removeClass('active');
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
                $('#cv-messages').html('<div class="sr-viewer-placeholder">No messages in this conversation.</div>');
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
                    <div class="sr-msg ${wrapClass}">
                        <div class="sr-msg-bubble">
                            ${formatText(msg.content)}
                        </div>
                        <span class="sr-msg-time">${timeStr}</span>
                    </div>
                `;
                $('#cv-messages').append(html);
            });

            // Scroll to bottom
            const container = $('#cv-messages');
            container.scrollTop(container.prop("scrollHeight"));
        });

        // Initialize first conversation if exists
        if ($('.sr-conv-item.active').length) {
            $('.sr-conv-item.active').trigger('click');
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
            $('.sr-kb-layout').addClass('has-sidebar');
            $('#kb-editor-sidebar').show();
        });

        // Hide Form
        $('.btn-cancel-kb').on('click', function() {
            $('.sr-kb-layout').removeClass('has-sidebar');
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
                
                $('.sr-kb-layout').addClass('has-sidebar');
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
                action: 'smartreplyr_save_kb',
                nonce: smartreplyrAdmin.nonce,
                id: $('#kb_id').val(),
                question: $('#kb_question').val(),
                answer: $('#kb_answer').val(),
                keywords: $('#kb_keywords').val(),
                intent: $('#kb_intent').val(),
                category: $('#kb_category').val()
            };

            $.post(smartreplyrAdmin.ajax_url, data, function(response) {
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

            $.post(smartreplyrAdmin.ajax_url, {
                action: 'smartreplyr_delete_kb',
                nonce: smartreplyrAdmin.nonce,
                id: id
            }, function(response) {
                if (response.success) {
                    tr.fadeOut(300, function() { $(this).remove(); });
                } else {
                    alert('Error deleting entry');
                }
            });
        });

        // --- KB Import / Export ---
        
        // Download Template
        $('#smartreplyr-download-kb-template').on('click', function(e) {
            e.preventDefault();
            window.location.href = smartreplyrAdmin.ajax_url + '?action=smartreplyr_download_kb_template&nonce=' + smartreplyrAdmin.nonce;
        });

        // Show Import Form
        $('.btn-import-kb').on('click', function(e) {
            e.preventDefault();
            $('#sr-import-kb-wrapper').slideDown();
        });

        // Hide Import Form
        $('#btn-cancel-import').on('click', function() {
            $('#sr-import-kb-wrapper').slideUp();
            $('#sr-import-kb-form')[0].reset();
            $('#sr-import-results').hide();
        });

        // Handle Import Submit
        $('#sr-import-kb-form').on('submit', function(e) {
            e.preventDefault();
            
            const btn = $('#btn-submit-import');
            const originalText = btn.text();
            
            if ( ! $('#kb_file').val() ) {
                alert('Please select a file to import.');
                return;
            }
            
            const mode = $('input[name="import_mode"]:checked').val();
            if ( mode === 'replace' ) {
                if ( ! confirm('WARNING: You are about to DELETE all existing Knowledge Base entries and replace them with this file. This action cannot be undone. Are you sure?') ) {
                    return;
                }
            }

            btn.text('Uploading...').prop('disabled', true);
            $('#sr-import-results').show();
            $('#sr-import-msg').html('<strong>Uploading and processing file... Please wait.</strong>');
            $('#sr-import-errors').empty();

            const formData = new FormData(this);
            formData.append('action', 'smartreplyr_import_kb');
            formData.append('nonce', smartreplyrAdmin.nonce);

            $.ajax({
                url: smartreplyrAdmin.ajax_url,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    btn.text(originalText).prop('disabled', false);
                    
                    if (response.success) {
                        const data = response.data;
                        $('#sr-import-msg').html(
                            `<strong style="color: green;">Success!</strong> ${data.imported} entries imported. ${data.skipped} skipped.<br>
                             Total KB Size: ${data.total_kb} entries.<br>
                             <em>Page will reload in 3 seconds...</em>`
                        );
                        
                        if (data.errors && data.errors.length > 0) {
                            data.errors.forEach(err => {
                                $('#sr-import-errors').append(`<li>${err}</li>`);
                            });
                        }
                        
                        setTimeout(function() {
                            location.reload();
                        }, 3000);
                    } else {
                        $('#sr-import-msg').html(`<strong style="color: red;">Error:</strong> ${response.data}`);
                    }
                },
                error: function() {
                    btn.text(originalText).prop('disabled', false);
                    $('#sr-import-msg').html('<strong style="color: red;">A server error occurred during upload.</strong>');
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
