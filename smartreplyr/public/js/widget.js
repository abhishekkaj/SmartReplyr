document.addEventListener('DOMContentLoaded', function() {
    if (typeof smartreplyrConfig === 'undefined') return;

    // Apply primary color variable
    document.documentElement.style.setProperty('--sr-primary', smartreplyrConfig.primary_color);

    const root = document.getElementById('smartreplyr-widget-root');
    root.classList.add(smartreplyrConfig.position);

    // Build courses options
    let coursesHtml = '<option value="">Select a course...</option>';
    if (smartreplyrConfig.courses && smartreplyrConfig.courses.length > 0) {
        smartreplyrConfig.courses.forEach(course => {
            if(course) coursesHtml += `<option value="${course}">${course}</option>`;
        });
    }

    // Build Widget HTML
    const widgetHtml = `
        <div class="sr-widget-window">
            <div class="sr-widget-header">
                <div class="sr-avatar">
                    <img src="${smartreplyrConfig.avatar}" alt="Bot">
                </div>
                <div class="sr-header-info">
                    <h3>${smartreplyrConfig.bot_name}</h3>
                    <p>We typically reply in a few seconds</p>
                </div>
            </div>
            
            <div class="sr-widget-body">
                <!-- Lead Capture Form View -->
                <div class="sr-lead-form" id="sr-form-view">
                    <div class="sr-form-intro">
                        <h4>Hello there! 👋</h4>
                        <p>Please share your details below so our counselors can assist you better.</p>
                    </div>
                    
                    <form id="sr-capture-form">
                        <div class="sr-input-group">
                            <label>Full Name *</label>
                            <input type="text" id="sr_name" required placeholder="John Doe">
                        </div>
                        <div class="sr-input-group">
                            <label>Email Address *</label>
                            <input type="email" id="sr_email" required placeholder="john@example.com">
                        </div>
                        <div class="sr-input-group">
                            <label>Phone Number *</label>
                            <input type="tel" id="sr_phone" required placeholder="+1 234 567 8900">
                        </div>
                        ${coursesHtml.length > 45 ? `
                        <div class="sr-input-group">
                            <label>Course of Interest</label>
                            <select id="sr_course">${coursesHtml}</select>
                        </div>` : ''}
                        
                        ${smartreplyrConfig.gdpr_enabled === '1' ? `
                        <div class="sr-gdpr-group">
                            <input type="checkbox" id="sr_consent" required checked>
                            <label for="el_consent">${smartreplyrConfig.gdpr_text}</label>
                        </div>` : ''}
                        
                        <button type="submit" class="sr-btn-submit" id="sr-btn-submit">Start Chatting</button>
                    </form>
                </div>

                <!-- Chat Interface View -->
                <div class="sr-chat-interface" id="sr-chat-view">
                    <div class="sr-messages" id="sr-messages-container">
                        <!-- Messages appended here -->
                    </div>
                    
                    <form class="sr-chat-input" id="sr-chat-form">
                        <input type="text" id="sr_chat_msg" placeholder="Type your message..." autocomplete="off">
                        <button type="submit" class="sr-btn-send">
                            <svg viewBox="0 0 24 24"><path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"></path></svg>
                        </button>
                    </form>
                </div>
            </div>
        </div>
        
        <button class="sr-widget-trigger" id="sr-trigger-btn">
            <svg class="chat-icon" viewBox="0 0 24 24"><path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2z"></path></svg>
            <svg class="close-icon" viewBox="0 0 24 24"><path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"></path></svg>
        </button>
    `;

    root.innerHTML = widgetHtml;

    // Elements
    const triggerBtn = document.getElementById('sr-trigger-btn');
    const formView = document.getElementById('sr-form-view');
    const chatView = document.getElementById('sr-chat-view');
    const captureForm = document.getElementById('sr-capture-form');
    const chatForm = document.getElementById('sr-chat-form');
    const messagesContainer = document.getElementById('sr-messages-container');
    const msgInput = document.getElementById('sr_chat_msg');

    // Initialize intlTelInput
    const phoneInput = document.getElementById('sr_phone');
    let iti = null;
    if (window.intlTelInput) {
        iti = window.intlTelInput(phoneInput, {
            initialCountry: "auto",
            geoIpLookup: function(callback) {
                fetch("https://ipapi.co/json")
                  .then(function(res) { return res.json(); })
                  .then(function(data) { callback(data.country_code); })
                  .catch(function() { callback("us"); });
            },
            utilsScript: "https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/18.2.1/js/utils.js"
        });
    }

    // State
    let leadId = sessionStorage.getItem('smartreplyr_lead_id');
    
    // Toggle Widget
    triggerBtn.addEventListener('click', () => {
        root.classList.toggle('is-open');
        if (root.classList.contains('is-open') && leadId) {
            msgInput.focus();
        }
    });

    // Check session state
    if (leadId) {
        showChatInterface();
        // optionally load history from another endpoint, else just show empty
    }

    // Lead Submission
    captureForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        
        const submitBtn = document.getElementById('sr-btn-submit');
        submitBtn.innerText = 'Submitting...';
        submitBtn.disabled = true;

        const payload = {
            name: document.getElementById('sr_name').value,
            email: document.getElementById('sr_email').value,
            phone: iti ? iti.getNumber() : document.getElementById('sr_phone').value,
            course_interest: document.getElementById('sr_course') ? document.getElementById('sr_course').value : '',
            consent: document.getElementById('sr_consent') ? (document.getElementById('sr_consent').checked ? 1 : 0) : 1,
            page_url: window.location.href,
            page_title: smartreplyrConfig.page_title,
            referrer: document.referrer,
            utm_source: smartreplyrConfig.utm_source,
            utm_medium: smartreplyrConfig.utm_medium,
            utm_campaign: smartreplyrConfig.utm_campaign
        };

        try {
            const res = await fetch(`${smartreplyrConfig.api_url}/lead`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            
            const data = await res.json();
            
            if (data.success) {
                leadId = data.lead_id;
                sessionStorage.setItem('smartreplyr_lead_id', leadId);
                showChatInterface();
                appendBotMessage(data.message);
            } else {
                alert(data.message || 'Submission failed. Please try again.');
                submitBtn.innerText = 'Start Chatting';
                submitBtn.disabled = false;
            }
        } catch (err) {
            alert('A network error occurred.');
            submitBtn.innerText = 'Start Chatting';
            submitBtn.disabled = false;
        }
    });

    // Chat Message Submission
    chatForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        
        const msg = msgInput.value.trim();
        if (!msg || !leadId) return;

        // Add user msg to UI
        appendUserMessage(msg);
        msgInput.value = '';
        
        // Show typing indicator
        const typingId = showTypingIndicator();

        try {
            const res = await fetch(`${smartreplyrConfig.api_url}/chat`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    lead_id: leadId,
                    message: msg,
                    page_context: window.location.href
                })
            });
            
            const data = await res.json();
            
            removeTypingIndicator(typingId);
            
            if (data.success) {
                appendBotMessage(data.reply);
            } else {
                appendBotMessage("Sorry, I encountered an error. Please try again.");
            }
        } catch (err) {
            removeTypingIndicator(typingId);
            appendBotMessage("Network error. Please try again.");
        }
    });

    // View Transitions
    function showChatInterface() {
        formView.style.display = 'none';
        chatView.style.display = 'flex';
        msgInput.focus();
    }

    // UI Helpers
    function appendUserMessage(text) {
        messagesContainer.insertAdjacentHTML('beforeend', `<div class="sr-msg sr-msg-user">${escapeHtml(text)}</div>`);
        scrollToBottom();
    }

    function appendBotMessage(text) {
        messagesContainer.insertAdjacentHTML('beforeend', `<div class="sr-msg sr-msg-bot">${formatMarkdown(text)}</div>`);
        scrollToBottom();
    }

    function showTypingIndicator() {
        const id = 'typing-' + Date.now();
        messagesContainer.insertAdjacentHTML('beforeend', `
            <div class="sr-msg sr-msg-bot" id="${id}">
                <div class="sr-typing"><div class="sr-dot"></div><div class="sr-dot"></div><div class="sr-dot"></div></div>
            </div>
        `);
        scrollToBottom();
        return id;
    }

    function removeTypingIndicator(id) {
        const el = document.getElementById(id);
        if (el) el.remove();
    }

    function scrollToBottom() {
        messagesContainer.scrollTop = messagesContainer.scrollHeight;
    }

    function escapeHtml(unsafe) {
        return unsafe.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;");
    }

    function formatMarkdown(text) {
        if (!text) return '';
        let html = escapeHtml(text)
            .replace(/\n\n/g, '</p><p>')
            .replace(/\n/g, '<br>')
            .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
            .replace(/\*(.*?)\*/g, '<em>$1</em>');
        return `<p>${html}</p>`;
    }
});
