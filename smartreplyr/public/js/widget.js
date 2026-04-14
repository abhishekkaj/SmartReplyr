document.addEventListener('DOMContentLoaded', function() {
    if (typeof smartreplyrConfig === 'undefined') return;

    // Apply primary color variable
    document.documentElement.style.setProperty('--sr-primary', smartreplyrConfig.primary_color);

    const root = document.getElementById('smartreplyr-widget-root');
    root.classList.add(smartreplyrConfig.position);

    // Build Widget HTML (No static form, fully chat UI)
    const widgetHtml = `
        <div class="sr-widget-window">
            <div class="sr-widget-header">
                <div class="sr-avatar">
                    <img src="${smartreplyrConfig.avatar}" alt="Bot">
                </div>
                <div class="sr-header-info">
                    <h3>${smartreplyrConfig.bot_name}</h3>
                    <p>Get instant answers about courses & admissions</p>
                </div>
            </div>
            
            <div class="sr-widget-body">
                <div class="sr-chat-interface" id="sr-chat-view">
                    <div class="sr-messages" id="sr-messages-container">
                        <!-- Messages dynamically appended here -->
                    </div>
                    
                    <div class="sr-chat-input-wrapper">
                        <form class="sr-chat-input" id="sr-chat-form">
                            <input type="text" id="sr_chat_msg" placeholder="Type your message..." autocomplete="off">
                            <button type="submit" class="sr-btn-send" id="sr-btn-send">
                                <svg viewBox="0 0 24 24"><path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"></path></svg>
                            </button>
                        </form>
                        <div class="sr-trust-badge">🔒 100% secure • No spam • Instant response</div>
                    </div>
                </div>
            </div>
        </div>
        
        <button class="sr-widget-trigger" id="sr-trigger-btn">
            <svg class="chat-icon" viewBox="0 0 24 24"><path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2z"></path></svg>
            <svg class="close-icon" viewBox="0 0 24 24"><path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"></path></svg>
        </button>
    `;

    root.innerHTML = widgetHtml;

    // DOM Elements
    const triggerBtn = document.getElementById('sr-trigger-btn');
    const messagesContainer = document.getElementById('sr-messages-container');
    const chatForm = document.getElementById('sr-chat-form');
    const msgInput = document.getElementById('sr_chat_msg');
    const sendBtn = document.getElementById('sr-btn-send');

    // Lead Flow State Machine
    let leadId = sessionStorage.getItem('smartreplyr_lead_id');
    const LeadFlow = {
        state: leadId ? 'complete' : 'pre_lead', 
        data: { initial_query: '', name: '', email: '', phone: '', course: '' }
    };

    let hasOpened = false;
    let isTyping = false;

    // Toggle Widget
    triggerBtn.addEventListener('click', () => {
        root.classList.toggle('is-open');
        if (root.classList.contains('is-open')) {
            msgInput.focus();
            if(!hasOpened && !leadId) {
                hasOpened = true;
                // Initiate Conversation
                enqueueBotResponse("Hi 👋 What would you like to know today?");
            }
        }
    });

    // Smart Auto-Open logic
    setTimeout(() => {
        if(!root.classList.contains('is-open') && !hasOpened) {
            triggerBtn.click();
        }
    }, 5000);

    // Provide initial view if already authenticated
    if (leadId) {
        hasOpened = true;
        // Load soft welcome if page fresh loaded
        enqueueBotResponse("Welcome back! How can I help you today?");
    }

    // Chat Message Submission Handler
    chatForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const msg = msgInput.value.trim();
        if (!msg || isTyping) return;

        appendUserMessage(msg);
        msgInput.value = '';
        
        await processStateMachine(msg);
    });

    // Global helper for chips quick-replies
    window.srSelectChip = function(value) {
        msgInput.value = value;
        chatForm.dispatchEvent(new Event('submit'));
        // remove existing chips
        document.querySelectorAll('.sr-quick-replies').forEach(e => e.remove());
    };

    // State Machine Processor
    async function processStateMachine(msg) {
        if (LeadFlow.state === 'pre_lead') {
            LeadFlow.data.initial_query = msg;
            LeadFlow.state = 'name';
            enqueueBotResponse("I can help with that! Before we continue, what is your full name?");
        } 
        else if (LeadFlow.state === 'name') {
            LeadFlow.data.name = msg;
            LeadFlow.state = 'email';
            enqueueBotResponse(`Nice to meet you, ${msg.split(' ')[0]}! What is your best email address?`);
        } 
        else if (LeadFlow.state === 'email') {
            if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(msg)) {
                enqueueBotResponse("Hmm, that email doesn't look quite right. Could you double-check it?");
                return;
            }
            LeadFlow.data.email = msg;
            LeadFlow.state = 'phone';
            enqueueBotResponse("Thanks! And what is the best phone number to reach you at?");
        } 
        else if (LeadFlow.state === 'phone') {
            LeadFlow.data.phone = msg;
            LeadFlow.state = 'course';
            
            let coursesHtml = '';
            if (smartreplyrConfig.courses && smartreplyrConfig.courses.length > 0) {
                coursesHtml = '<div class="sr-quick-replies">' + 
                    smartreplyrConfig.courses.map(c => {
                        if(c) return `<button type="button" class="sr-chip" onclick="srSelectChip('${c}')">${c}</button>`;
                        return '';
                    }).join('') + '</div>';
            }
            
            enqueueBotResponse("Almost done! Which course are you interested in?", coursesHtml);
        } 
        else if (LeadFlow.state === 'course') {
            LeadFlow.data.course = msg;
            LeadFlow.state = 'submitting';
            
            enqueueBotResponse("Saving your details... 🔒");
            await submitLeadToApi();
        } 
        else if (LeadFlow.state === 'complete') {
            // Standard AI Chat Loop
            await askAI(msg);
        }
    }

    async function submitLeadToApi() {
        const payload = {
            name: LeadFlow.data.name,
            email: LeadFlow.data.email,
            phone: LeadFlow.data.phone,
            course_interest: LeadFlow.data.course,
            consent: smartreplyrConfig.gdpr_enabled === '1' ? 1 : 0,
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
                LeadFlow.state = 'complete';
                
                // Immediately ask the initial query they had!
                if (LeadFlow.data.initial_query) {
                    await askAI(LeadFlow.data.initial_query);
                } else {
                    enqueueSmartPrompts("Thanks! A counselor will be in touch. What else can I help you with?");
                }
            } else {
                enqueueBotResponse("I'm so sorry, there was an issue saving your details. Please try again later.");
                LeadFlow.state = 'course'; // let them retry
            }
        } catch (err) {
            enqueueBotResponse("Network error capturing details. Please try again.");
            LeadFlow.state = 'course';
        }
    }

    async function askAI(msg) {
        if (!leadId) return;
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
                enqueueSmartPrompts("");
            } else {
                appendBotMessage("Sorry, I encountered an error. Please try again.");
            }
        } catch (err) {
            removeTypingIndicator(typingId);
            appendBotMessage("Network error. Please try again.");
        }
    }

    // Delay Wrapper for Bot Messages (600 - 1200ms)
    function enqueueBotResponse(textHtml, rawHtmlAppend = '') {
        const id = showTypingIndicator();
        const delay = Math.floor(Math.random() * 600) + 600; // 600 to 1200ms delay
        
        setTimeout(() => {
            removeTypingIndicator(id);
            if(textHtml) {
                appendBotMessage(textHtml);
            }
            if(rawHtmlAppend) {
                messagesContainer.insertAdjacentHTML('beforeend', rawHtmlAppend);
                scrollToBottom();
            }
        }, delay);
    }

    function enqueueSmartPrompts(preludeMsg) {
        let msg = preludeMsg || "Are you looking for anything specific?";
        let prompts = '<div class="sr-quick-replies">' + 
            `<button class="sr-chip" onclick="srSelectChip('Check Fees Structure')">Fees Structure</button>` +
            `<button class="sr-chip" onclick="srSelectChip('What is the Admission Process?')">Admission Process</button>` +
            `<button class="sr-chip" onclick="srSelectChip('I want to talk to a counselor')">Talk to Counselor</button>` +
            '</div>';
        
        enqueueBotResponse(msg, prompts);
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
        isTyping = true;
        msgInput.disabled = true;
        sendBtn.disabled = true;
        
        const id = 'typing-' + Date.now();
        messagesContainer.insertAdjacentHTML('beforeend', `
            <div class="sr-msg sr-msg-bot" id="${id}">
                <div class="sr-typing"><span></span><span></span><span></span></div>
            </div>
        `);
        scrollToBottom();
        return id;
    }

    function removeTypingIndicator(id) {
        isTyping = false;
        msgInput.disabled = false;
        sendBtn.disabled = false;
        
        const el = document.getElementById(id);
        if (el) el.remove();
        
        msgInput.focus();
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
