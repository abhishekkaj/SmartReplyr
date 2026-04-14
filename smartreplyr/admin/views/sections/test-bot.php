<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<h2>Test Chatbot Simulator</h2>
<p class="description">Test your knowledge base indexing and natural language matching locally right from the dashboard without navigating to the frontend.</p>

<div class="sr-test-bot-container" style="background:#f9f9f9; padding:20px; border:1px solid #ddd; max-width:600px; border-radius:5px;">
    <p>
        <label for="sr_test_question" style="font-weight:bold; display:block; margin-bottom:5px;">Ask a test question:</label>
        <input type="text" id="sr_test_question" class="large-text" placeholder="e.g. Do you offer an MBA program?">
    </p>
    <p>
        <button type="button" class="button button-primary" id="sr_test_bot_btn">Test Engine</button>
        <span class="spinner" id="sr_test_bot_spinner" style="float:none; margin:0 10px;"></span>
    </p>
    
    <div id="sr_test_bot_results" style="display:none; margin-top:20px; background:#fff; padding:15px; border-left:4px solid <?php echo esc_attr($settings['primary_color']); ?>;">
        <h4 style="margin-top:0;">Evaluation Results</h4>
        <p><strong>Status:</strong> <span id="sr_test_status"></span></p>
        <p><strong>Simulated Response:</strong> <br><em id="sr_test_response" style="display:inline-block; margin-top:5px; padding:10px; background:#f0f0f1; border-radius:3px;"></em></p>
        <hr>
        <p><strong>Intent Hit:</strong> <code id="sr_test_intent"></code></p>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    $('#sr_test_bot_btn').on('click', function(e) {
        e.preventDefault();
        var question = $('#sr_test_question').val();
        if (!question.trim()) return alert("Please enter a question to test.");
        
        $('#sr_test_bot_spinner').addClass('is-active');
        $('#sr_test_bot_btn').prop('disabled', true);
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'smartreplyr_test_bot',
                nonce: smartreplyrAdmin.nonce,
                question: question
            },
            success: function(response) {
                $('#sr_test_bot_spinner').removeClass('is-active');
                $('#sr_test_bot_btn').prop('disabled', false);
                
                $('#sr_test_bot_results').show();
                if (response.success) {
                    $('#sr_test_status').html('<span style="color:green;">Success</span>');
                    $('#sr_test_response').text(response.data.message);
                    $('#sr_test_intent').text(response.data.intent || 'None');
                } else {
                    $('#sr_test_status').html('<span style="color:red;">Failed</span>');
                    $('#sr_test_response').text(response.data);
                }
            },
            error: function() {
                $('#sr_test_bot_spinner').removeClass('is-active');
                $('#sr_test_bot_btn').prop('disabled', false);
                alert("An error occurred trying to hook into the Chatbot AJAX endpoints.");
            }
        });
    });
});
</script>
