document.addEventListener('DOMContentLoaded', function() {
    if (typeof smartreplyrConfig === 'undefined') return;

    // Apply primary color variable
    document.documentElement.style.setProperty('--sr-primary', smartreplyrConfig.primary_color);

    // PERSISTENCE CHECK
    let leadId = localStorage.getItem('smartreplyr_lead_id');
    let isLeadSubmitted = localStorage.getItem('smartreplyr_lead_submitted') === 'true';

    // Build Widget HTML Template
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
                <button class="sr-close-window" id="sr-close-btn" title="Close Chat">
                    <svg viewBox="0 0 24 24"><path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"></path></svg>
                </button>
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

    const initWidget = () => {
        const root = document.getElementById('smartreplyr-widget-root');
        if (!root) {
            if (!window._sr_retried) {
                window._sr_retried = true;
                setTimeout(initWidget, 500);
            } else {
                console.warn('SmartReplyr: Root element not found (#smartreplyr-widget-root)');
            }
            return;
        }

        root.classList.add(smartreplyrConfig.position);
        root.innerHTML = widgetHtml;
        setupListeners(root);
    };

    const setupListeners = (root) => {
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

        // Initialize ITI (safe check)
        if (window.intlTelInput && phoneInput) {
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
                        initiateChat(messagesContainer);
                    }
                }
            }
        });

        // Close Button (Header)
        document.getElementById('sr-close-btn').addEventListener('click', () => {
            root.classList.remove('is-open');
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

            try {
                const res = await fetch(`${smartreplyrConfig.api_url}/lead`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-SR-Nonce': smartreplyrConfig.nonce },
                    body: JSON.stringify(payload)
                });
                
                const data = await res.json();
                if (data.success) {
                    leadId = data.lead_id;
                    isLeadSubmitted = true;
                    localStorage.setItem('smartreplyr_lead_id', leadId);
                    localStorage.setItem('smartreplyr_lead_submitted', 'true');
                    
                    leadFormView.classList.add('sr-hidden');
                    successView.classList.remove('sr-hidden');

                    setTimeout(() => {
                        successView.classList.add('sr-hidden');
                        chatView.classList.remove('sr-hidden');
                        initiateChat(messagesContainer);
                    }, 2000);
                } else {
                    throw new Error(data.message || 'Submission failed');
                }
            } catch (err) {
                alert(err.message || 'Something went wrong. Please check your connection.');
                submitBtn.disabled = false;
                btnText.classList.remove('sr-hidden');
                loader.classList.add('sr-hidden');
            }
        });

        // CHAT LOGIC
        chatForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const msg = msgInput.value.trim();
            if (!msg || isTyping) return;

            appendUserMessage(messagesContainer, msg);
            msgInput.value = '';
            
            await askAI(messagesContainer, msg);
        });

        // Focus fix
        msgInput.addEventListener('focus', () => {
            if (window.innerWidth < 480) {
                setTimeout(() => messagesContainer.scrollTop = messagesContainer.scrollHeight, 300);
            }
        });
    };

    function initiateChat(container) {
        if (container.children.length === 0) {
            appendBotMessage(container, smartreplyrConfig.welcome_message);
            enqueueSmartPrompts(container);
        }
    }

    async function askAI(container, msg) {
        const tId = showTypingIndicator(container);
        try {
            const res = await fetch(`${smartreplyrConfig.api_url}/chat`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-SR-Nonce': smartreplyrConfig.nonce },
                body: JSON.stringify({ lead_id: leadId, message: msg, page_context: window.location.href })
            });
            const data = await res.json();
            document.getElementById(tId)?.remove();
            
            if (data.success) {
                appendBotMessage(container, data.reply);
            } else {
                appendBotMessage(container, "I'm having trouble connecting. Please try again.");
            }
        } catch (err) {
            document.getElementById(tId)?.remove();
            appendBotMessage(container, "Network error. Please check your connection.");
        }
    }

    function appendUserMessage(container, text) {
        container.insertAdjacentHTML('beforeend', `<div class="sr-msg sr-message sr-msg-user">${escapeHtml(text)}</div>`);
        container.scrollTop = container.scrollHeight;
    }

    function appendBotMessage(container, text) {
        container.insertAdjacentHTML('beforeend', `<div class="sr-msg sr-message sr-msg-bot">${formatMarkdown(text)}</div>`);
        container.scrollTop = container.scrollHeight;
    }

    function showTypingIndicator(container) {
        const id = 'typing-' + Date.now();
        container.insertAdjacentHTML('beforeend', `<div class="sr-msg sr-message sr-msg-bot" id="${id}"><div class="sr-typing"><span></span><span></span><span></span></div></div>`);
        container.scrollTop = container.scrollHeight;
        return id;
    }

    function enqueueSmartPrompts(container) {
        setTimeout(() => {
            const promptsList = (smartreplyrConfig.quick_prompts && smartreplyrConfig.quick_prompts.length > 0) 
                ? smartreplyrConfig.quick_prompts 
                : ['View Courses', 'Fee Structure', 'How to Apply?'];

            const prompts = `
                <div class="sr-quick-replies">
                    ${promptsList.map(p => `<button class="sr-chip" onclick="window.srQuickSend('${escapeJs(p)}')">${escapeHtml(p)}</button>`).join('')}
                </div>
            `;
            container.insertAdjacentHTML('beforeend', prompts);
            container.scrollTop = container.scrollHeight;
        }, 1500);
    }

    function escapeJs(str) {
        return str.replace(/'/g, "\\'");
    }

    window.srQuickSend = function(val) {
        const input = document.getElementById('sr_chat_msg');
        if (input) {
            input.value = val;
            document.getElementById('sr-chat-form').dispatchEvent(new Event('submit'));
            document.querySelector('.sr-quick-replies')?.remove();
        }
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

    initWidget();
});
