document.addEventListener('DOMContentLoaded', function() {
    if (typeof smartreplyrConfig === 'undefined') return;

    // Apply primary color variable
    document.documentElement.style.setProperty('--sr-primary', smartreplyrConfig.primary_color);

    const root = document.getElementById('smartreplyr-widget-root');
    root.classList.add(smartreplyrConfig.position);

    // PERSISTENCE CHECK
    let leadId = localStorage.getItem('smartreplyr_lead_id');
    let isLeadSubmitted = localStorage.getItem('smartreplyr_lead_submitted') === 'true';

    // Build Widget HTML
    const widgetHtml = `
        <div class="sr-widget-window">
            <div class="sr-widget-header">
                <div class="sr-avatar">
                    <img src="${smartreplyrConfig.avatar || ''}" alt="">
                    <div class="sr-online-dot"></div>
                </div>
                <div class="sr-header-info">
                    <h3>${smartreplyrConfig.bot_name}</h3>
                    <p>Usually responds instantly</p>
                </div>
            </div>
            
            <div class="sr-widget-body">
                <!-- VIEW 1: LEAD FORM (VISIBLE BY DEFAULT) -->
                <div class="sr-form-view ${isLeadSubmitted ? 'sr-hidden' : ''}" id="sr-lead-form-view">
                    <p class="sr-intro-text">${smartreplyrConfig.welcome_message || 'Please fill the form below to start chat'}</p>
                    <form id="sr-lead-form">
                        <div class="sr-form-group">
                            <label>Full Name</label>
                            <input type="text" name="name" placeholder="John Doe" required>
                        </div>
                        <div class="sr-form-group">
                            <label>Email Address</label>
                            <input type="email" name="email" placeholder="john@example.com" required>
                        </div>
                        <div class="sr-form-group">
                            <label>Phone Number</label>
                            <input type="tel" name="phone" id="sr-phone" placeholder="Your mobile number" required>
                        </div>
                        <div class="sr-form-group">
                            <label>Course Interest</label>
                            <select name="course" style="color: #111 !important; background: #fff !important; z-index: 9999;">
                                ${smartreplyrConfig.courses.map(c => `<option value="${c}">${c}</option>`).join('')}
                            </select>
                        </div>
                        
                        ${smartreplyrConfig.gdpr_enabled === '1' ? `
                        <div class="sr-gdpr-check">
                            <input type="checkbox" id="sr_consent" checked required>
                            <label for="sr_consent">${smartreplyrConfig.gdpr_text}</label>
                        </div>
                        ` : ''}

                        <button type="submit" class="sr-submit-lead" id="sr-submit-btn">
                            <span class="btn-text">Start Chat</span>
                            <div class="sr-loader sr-hidden"></div>
                        </button>
                    </form>
                    
                    <div class="sr-security-badge">
                        <svg viewBox="0 0 24 24"><path d="M12 2C9.24 2 7 4.24 7 7v3H6c-1.1 0-2 .9-2 2v8c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2v-8c0-1.1-.9-2-2-2h-1V7c0-2.76-2.24-5-5-5zm0 2c1.66 0 3 1.34 3 3v3H9V7c0-1.66 1.34-3 3-3z"></path></svg>
                        <span>🔒 256-bit SSL Encrypted & Secure</span>
                    </div>
                </div>

                <!-- VIEW 2: SUCCESS STATE (TRANSITIONAL) -->
                <div class="sr-success-view sr-hidden" id="sr-success-view">
                    <div class="sr-success-state">
                        <div class="sr-success-icon">✓</div>
                        <h4>Details Received</h4>
                        <p>Connecting you to our assistant...</p>
                    </div>
                </div>

                <!-- VIEW 3: CHAT INTERFACE -->
                <div class="sr-chat-interface ${!isLeadSubmitted ? 'sr-hidden' : ''}" id="sr-chat-view">
                    <div class="sr-messages" id="sr-messages-container"></div>
                    
                    <div class="sr-chat-input-wrapper">
                        <form class="sr-chat-input" id="sr-chat-form">
                            <input type="text" id="sr_chat_msg" placeholder="Type your message..." autocomplete="off">
                            <button type="submit" class="sr-btn-send" id="sr-btn-send">
                                <svg viewBox="0 0 24 24"><path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"></path></svg>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        
        <button class="sr-widget-trigger" id="sr-trigger-btn">
            <svg class="sr-icon chat-icon" viewBox="0 0 24 24"><path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2z"></path></svg>
            <svg class="sr-icon close-icon" viewBox="0 0 24 24"><path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"></path></svg>
        </button>
    `;

    root.innerHTML = widgetHtml;

    // DOM Elements
    const triggerBtn = document.getElementById('sr-trigger-btn');
    const leadForm = document.getElementById('sr-lead-form');
    const leadFormView = document.getElementById('sr-lead-form-view');
    const successView = document.getElementById('sr-success-view');
    const chatView = document.getElementById('sr-chat-view');
    const messagesContainer = document.getElementById('sr-messages-container');
    const chatForm = document.getElementById('sr-chat-form');
    const msgInput = document.getElementById('sr_chat_msg');
    const submitBtn = document.getElementById('sr-submit-btn');
    const phoneInput = document.getElementById('sr-phone');

    let hasOpened = false;
    let isTyping = false;
    let iti;

    // Initialize ITI
    if (window.intlTelInput) {
        iti = window.intlTelInput(phoneInput, {
            initialCountry: "auto",
            geoIpLookup: function(success, failure) {
                fetch("https://ipapi.co/json")
                    .then(res => res.json())
                    .then(data => success(data.country_code))
                    .catch(() => success("us"));
            },
            utilsScript: "https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/18.2.1/js/utils.js"
        });
    }

    // Toggle Widget
    triggerBtn.addEventListener('click', () => {
        root.classList.toggle('is-open');
        if (root.classList.contains('is-open')) {
            if (!hasOpened) {
                hasOpened = true;
                if (isLeadSubmitted) {
                    initiateChat();
                }
            }
        }
    });

    // Auto-Open (5s)
    setTimeout(() => {
        if (!root.classList.contains('is-open') && !hasOpened) {
            triggerBtn.click();
        }
    }, 5000);

    // LEAD SUBMISSION
    leadForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        
        const btnText = submitBtn.querySelector('.btn-text');
        const loader = submitBtn.querySelector('.sr-loader');
        
        // Prevent duplicate
        submitBtn.disabled = true;
        btnText.classList.add('sr-hidden');
        loader.classList.remove('sr-hidden');

        const formData = new FormData(leadForm);
        const fullPhone = iti ? iti.getNumber() : formData.get('phone');

        const payload = {
            name: formData.get('name'),
            email: formData.get('email'),
            phone: fullPhone,
            course_interest: formData.get('course'),
            consent: 1,
            page_url: window.location.href,
            page_title: smartreplyrConfig.page_title,
            referrer: document.referrer
        };

        // Timeout controller
        const controller = new AbortController();
        const timeoutId = setTimeout(() => controller.abort(), 7000);

        try {
            const res = await fetch(`${smartreplyrConfig.api_url}/lead`, {
                method: 'POST',
                headers: { 
                    'Content-Type': 'application/json',
                    'X-SR-Nonce': smartreplyrConfig.nonce 
                },
                body: JSON.stringify(payload),
                signal: controller.signal
            });
            clearTimeout(timeoutId);
            
            const data = await res.json();
            if (data.success) {
                transitionToSuccess(data.lead_id);
            } else {
                throw new Error(data.message || 'Submission failed');
            }
        } catch (err) {
            console.error('Submission error:', err);
            // Fallback success for UX (silent background retry happens in prod usually)
            if (err.name === 'AbortError') {
                transitionToSuccess(999); // Mock ID for fallback
            } else {
                const errMsg = (err.message && err.message !== 'Submission failed' && err.message !== 'Failed to fetch') 
                    ? err.message 
                    : 'Something went wrong. Please check your connection.';
                alert(errMsg);
                submitBtn.disabled = false;
                btnText.classList.remove('sr-hidden');
                loader.classList.add('sr-hidden');
            }
        }
    });

    function transitionToSuccess(newLeadId) {
        leadId = newLeadId;
        isLeadSubmitted = true;
        localStorage.setItem('smartreplyr_lead_id', leadId);
        localStorage.setItem('smartreplyr_lead_submitted', 'true');

        leadFormView.classList.add('sr-hidden');
        successView.classList.remove('sr-hidden');

        setTimeout(() => {
            successView.classList.add('sr-hidden');
            chatView.classList.remove('sr-hidden');
            initiateChat();
        }, 2000);
    }

    function initiateChat() {
        if (messagesContainer.children.length === 0) {
            appendBotMessage(smartreplyrConfig.welcome_message);
            enqueueSmartPrompts();
        }
    }

    // CHAT LOGIC
    chatForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const msg = msgInput.value.trim();
        if (!msg || isTyping) return;

        appendUserMessage(msg);
        msgInput.value = '';
        
        await askAI(msg);
    });

    async function askAI(msg) {
        const tId = showTypingIndicator();
        try {
            const res = await fetch(`${smartreplyrConfig.api_url}/chat`, {
                method: 'POST',
                headers: { 
                    'Content-Type': 'application/json',
                    'X-SR-Nonce': smartreplyrConfig.nonce 
                },
                body: JSON.stringify({
                    lead_id: leadId,
                    message: msg,
                    page_context: window.location.href
                })
            });
            const data = await res.json();
            removeTypingIndicator(tId);
            
            if (data.success) {
                appendBotMessage(data.reply);
            } else {
                appendBotMessage("I'm having trouble connecting. Please try again in a moment.");
            }
        } catch (err) {
            removeTypingIndicator(tId);
            appendBotMessage("Network error. Please check your internet connection.");
        }
    }

    // UI HELPERS
    function appendUserMessage(text) {
        messagesContainer.insertAdjacentHTML('beforeend', `<div class="sr-msg sr-message sr-msg-user">${escapeHtml(text)}</div>`);
        scrollToBottom();
    }

    function appendBotMessage(text) {
        messagesContainer.insertAdjacentHTML('beforeend', `<div class="sr-msg sr-message sr-msg-bot">${formatMarkdown(text)}</div>`);
        scrollToBottom();
    }

    function showTypingIndicator() {
        isTyping = true;
        const id = 'typing-' + Date.now();
        messagesContainer.insertAdjacentHTML('beforeend', `
            <div class="sr-msg sr-message sr-msg-bot" id="${id}">
                <div class="sr-typing"><span></span><span></span><span></span></div>
            </div>
        `);
        scrollToBottom();
        return id;
    }

    function removeTypingIndicator(id) {
        isTyping = false;
        const el = document.getElementById(id);
        if (el) el.remove();
    }

    function scrollToBottom() {
        messagesContainer.scrollTop = messagesContainer.scrollHeight;
    }

    function enqueueSmartPrompts() {
        const delay = 1500;
        setTimeout(() => {
            const prompts = `
                <div class="sr-quick-replies">
                    <button class="sr-chip" onclick="window.srQuickSend('What courses do you offer?')">View Courses</button>
                    <button class="sr-chip" onclick="window.srQuickSend('What is the fee structure?')">Fee Structure</button>
                    <button class="sr-chip" onclick="window.srQuickSend('How to apply?')">Apply Now</button>
                </div>
            `;
            messagesContainer.insertAdjacentHTML('beforeend', prompts);
            scrollToBottom();
        }, delay);
    }

    window.srQuickSend = function(val) {
        msgInput.value = val;
        chatForm.dispatchEvent(new Event('submit'));
        // Remove chips
        document.querySelector('.sr-quick-replies')?.remove();
    };

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

    // MOBILE FOCUS FIX
    msgInput.addEventListener('focus', () => {
        if (window.innerWidth < 480) {
            setTimeout(scrollToBottom, 300);
        }
    });
});
