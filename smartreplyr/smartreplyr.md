# SmartReplyr - WordPress Plugin
> Turn Visitors Into Leads Automatically

**SmartReplyr** is a production-ready WordPress plugin developed for education institutes, focused on providing an AI-powered lead generation chatbot.

## Features Implemented

1. **Lead Capture First:** Mandatory lead form (Name, Email, Phone, Course Interest) before allowing users to interact with the AI chat.
2. **AI Chat System:** Native integration with OpenAI (GPT-4o mini / GPT-4o / GPT-3.5 Turbo) including token management and system prompt generation based on page context.
3. **Knowledge Base:** An admin-managed Q&A index that the AI queries dynamically, ensuring the chatbot provides factual answers tailored to the institute. Correctly serves as a fallback to generic prompts.
4. **Flexible CRM Webhook Integration:** Send structured JSON payload to external webhooks (e.g. LeadSquared, Zapier, Make) with **dynamic fuzzy field-mapping**. Supports mapping any internal key (name, phone, lead_source, etc.) to any custom CRM key.
5. **Email Notification:** Includes a custom HTML email template triggered on new lead capture. Can be configured to route via external custom SMTP settings.
6. **Smart Context:** Automatically detects the current page URL, page title, and referral tags to personalize AI answers contextually.
7. **GDPR Ready:** Fully manageable GDPR checkbox requirement built into the frontend widget before users can chat.
8. **Admin Dashboard:** Full dashboard to view overall lead conversion rates, filtering, log viewing, widget customization (color/avatar/UI options), and detailed CSV exports of leads.
9. **Hardened Guest Visibility:** Specialized loading sequence using high-priority hooks (`plugins_loaded`, `wp_body_open`) and fallback rendering to ensure the bot shows up on sites with security layers, caches, or password protection.
10. **Lead Tracking:** Integrated UTM tracking (`utm_source`, `utm_medium`, etc.) and a custom **Lead Source** setting for granular attribution.

## API Structure

The plugin operates primarily over the WP REST API under the namespace: `smartreplyr/v1`.

### Public Endpoints
*   `POST /wp-json/smartreplyr/v1/lead`
    *   Creates a new lead from the widget.
    *   Requires: `name`, `email`, `phone`, `consent` (if GDPR active).
    *   Returns: `lead_id`, `conversation_id`, and `message`.
    *   Triggers background Webhook delivery and Email routines.
*   `POST /wp-json/smartreplyr/v1/chat`
    *   Processes user queries and fetches AI reply.
    *   Requires `lead_id`, `message`, `page_context`.

## Database Schema

5 custom tables are built upon activation:

### 1. `wp_smartreplyr_leads`
Store lead details, page context, and UTM parameters.

### 2. `wp_smartreplyr_conversations`
Store full JSON-serialized chat history linked to leads.

### 3. `wp_smartreplyr_settings`
Global plugin configuration (OpenAI keys, colors, webhook URLs, etc.).

### 4. `wp_smartreplyr_knowledge_base`
Q&A pairs for the RAG (Retrieval Augmented Generation) engine.

### 5. `wp_smartreplyr_logs`
Detailed execution logs for Webhooks, AI responses, and SMTP failures.

## Changelog

### v2.2.2 (Current)
- **Fuzzy CRM Mapping:** Webhook engine now automatically detects intent if users use labels like "First Name" or "Mobile" in their mapping JSON instead of the technical internal keys.
- **Custom Lead Source:** Added ability to define and map a custom lead source (e.g. "Dubai Campus Chatbot") per site instance.
- **Tab-Aware Admin UI:** Refactored the settings save logic to prevent cross-tab settings overwrites; saving CRM settings no longer unchecks Email notifications.
- **Production Persistence:** Integrated `localStorage` lead persistence to prevent data loss if a user refreshes the page mid-conversation.

### v2.2.1
- **Bulletproof Global Initialization:** Strategic move of plugin boot to `plugins_loaded` and priority `1` hooks to ensure visibility on sites hidden behind Bluehost/Mandatory password screens.
- **Asset Fallbacks:** Removed hard dependency on `intl-tel-input` CDN. The chatbot now degrades gracefully to a standard input if the external CDN is blocked by firewalls.
- **Static API Security:** Replaced session-based nonces with specialized static HMAC-SHA256 hashes to ensure API reliability for guests arriving via aggressive caching systems.

### v2.2.0
- **Conversational Lead Capture Pipeline:** Sequential chat-bot state machine capturing Name, Email, Phone, and Course natively.
- **Premium Design Overhaul:** Deep box shadows, organic gradient launchers, pulsating trigger animations, and dynamic micro-interactions.
- **Smart Message Delay:** Simulated human typing latencies and `fadeUp` delays for a more natural feel.

### v2.1.0
- Refactored Admin UI Settings to a highly scalable, dynamic section-component loader.
- Added deep native Chatbot Test simulator inside the configuration board.
- Bulletproofed plugin lifecycle with global sandbox implementation.

### v2.0.0
- Complete rebranding from EduLead AI → SmartReplyr.
- Updated database tables and UI naming conventions.

## Future Roadmap

*   **Embedding Search:** Upgrade basic keyword knowledge-base lookup to true vector embeddings.
*   **Website Scraper Integration:** Direct URL-scraping in Admin to auto-populate the Knowledge Base.
*   **Handoff to Human:** Option to seamlessly page a human operator mid-conversation.
