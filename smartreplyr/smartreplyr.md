# SmartReplyr - WordPress Plugin
> Turn Visitors Into Leads Automatically

**SmartReplyr** is a production-ready WordPress plugin developed for education institutes, focused on providing an AI-powered lead generation chatbot.

## Features Implemented

1. **Lead Capture First:** Mandatory lead form (Name, Email, Phone, Course Interest) before allowing users to interact with the AI chat.
2. **AI Chat System:** Native integration with OpenAI (GPT-4o mini / GPT-4o / GPT-3.5 Turbo) including token management and system prompt generation based on page context.
3. **Knowledge Base:** An admin-managed Q&A index that the AI queries dynamically, ensuring the chatbot provides factual answers tailored to the institute. Correctly serves as a fallback to generic prompts.
4. **CRM Webhook Integration:** Send structured JSON payload to external webhooks (e.g. Zapier, Make, custom CRM) with dynamic field-mapping and full UTM source/campaign inclusion.
5. **Email Notification:** Includes a custom HTML email template triggered on new lead capture. Can be configured to route via external custom SMTP settings.
6. **Smart Context:** Automatically detects the current page URL, page title, and referral tags to personalize AI answers contextually.
7. **GDPR Ready:** Fully manageable GDPR checkbox requirement built into the frontend widget before users can chat.
8. **Admin Dashboard:** Full dashboard to view overall lead conversion rates, filtering, log viewing, widget customization (color/avatar/UI options), and detailed CSV exports of leads.
9. **Smart Rule-Based NLP:** Rule based hybrid system detecting intent and similarity index to answer questions natively before falling back to ChatGPT.
10. **Advanced Phone Validation:** `intl-tel-input` tracks international flags and phone validations beautifully.
11. **Test Chatbot Simulator:** A robust built-in sandbox located dynamically inside the Admin dashboard, simulating deep webhook integration testing.
12. **Bulletproof Architecture:** Hardened with modular, safely sandboxed individual UI blocks and a global runtime fatal-error interceptor to enforce site reliability.

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
*   `GET /wp-json/smartreplyr/v1/widget-config`
    *   Fetches public customization settings (colors, bot name, pre-loaded courses logic) for rendering the JS widget.

### Admin-Only Endpoints
*   `GET /wp-json/smartreplyr/v1/leads`
    *   Used internally for fetching filtering and dashboard results.
*   `GET | POST | DELETE /wp-json/smartreplyr/v1/knowledge`
    *   CRUD operations for the AI Knowledge Base list interface.

## Database Schema

4 custom tables are built upon activation:

### 1. `wp_smartreplyr_leads`
*   `id` (BIGINT)
*   `name` (VARCHAR)
*   `phone` (VARCHAR)
*   `email` (VARCHAR)
*   `course_interest` (VARCHAR)
*   `page_url` (TEXT)
*   `page_title` (VARCHAR)
*   `referrer` (TEXT)
*   `utm_source`, `utm_medium`, `utm_campaign`, `utm_term`, `utm_content`
*   `consent` (TINYINT)
*   `status` (VARCHAR)
*   `webhook_sent` (TINYINT)
*   `email_sent` (TINYINT)
*   `created_at` (DATETIME)

### 2. `wp_smartreplyr_conversations`
*   `id` (BIGINT)
*   `lead_id` (BIGINT - linked to leads table)
*   `messages` (LONGTEXT - JSON representation of the entire conversation thread)
*   `page_context` (TEXT)
*   `created_at` (DATETIME)
*   `updated_at` (DATETIME)

### 3. `wp_smartreplyr_settings`
*   `id` (BIGINT)
*   `option_name` (VARCHAR)
*   `option_value` (LONGTEXT)

### 4. `wp_smartreplyr_knowledge_base`
*   `id` (BIGINT)
*   `question` (TEXT)
*   `answer` (LONGTEXT)
*   `keywords` (LONGTEXT)
*   `intent` (VARCHAR)
*   `category` (VARCHAR)
*   `source` (VARCHAR)
*   `created_at` (DATETIME)

## Changelog

### v2.2.0
- **Conversational Lead Capture Pipeline:** Ripped out traditional static block forms and implemented a Drift/Intercom style sequential chat-bot state machine capturing Name, Email, Phone, and Course natively.
- **Premium Design Overhaul:** Integrated $10M SaaS-level UI elements including deep box shadows, organic gradient launchers, pulsating trigger animations, and dynamic micro-interactions on chat hover.
- **Human-Feel AI Presence:** Simulated human typing latencies randomly bounding 600-1200ms along with smart message `fadeUp` delays.
- **Auto-Open UX:** Trigger orb smartly fires conversational greeting after 5 seconds on the page to jump-start visitor interactions automatically.
- **Dynamic Quick-Reply Chips:** Inject high-conversion prompt chips directly underneath chatbot layout after standard details are received to boost deep question rate.

### v2.1.0
- Refactored Admin UI Settings to a highly scalable, dynamic section-component loader.
- Pushed custom settings validation enforcing safe Webhooks and Escaped JSON formatting.
- Added deep native Chatbot Test simulator utilizing exact front-end NLP processing inside the configuration board.
- Bulletproofed plugin lifecycle terminating PHP WSOD with a global sandbox implementation.

### v2.0.0
- Complete rebranding from EduLead AI → SmartReplyr
- Updated plugin slug, database tables, and UI
- Added backward compatibility for old shortcodes
- Improved branding consistency across admin & frontend

**v1.1.0**
- Implemented robust, dependency-free NLP native semantic matching resolving inquiries using Hybrid Engine (60% similarity / 40% keyword overlap) mapped to Intents.
- Included `intl-tel-input` on Frontend form.
- Introduced WP Media Modal for frontend avatar uploading inside widget options.
- Upgraded Admin API endpoints to track intent hits.
- Add `debug_mode` setting to natively intercept the API response directly inside the Network Console natively.

**v1.0.0 (Current Release)**
- Initial architecture release.
- Added foundational core plugins, DB seedings and schema logic.
- Built interactive frontend chat interface combining vanilla JS & glassmorphic UI.
- Implemented robust Webhook sending algorithm and SMTP native Mailer routing.
- Integrated OpenAI LLM capabilities to digest Q&A.

## Future Roadmap

*   **Embedding Search:** Upgrade basic keyword knowledge-base lookup to true vector embeddings (Weaviate / native WP options).
*   **Website Scraper Integration:** Direct URL-scraping in Admin to auto-populate the Knowledge Base.
*   **Widget Animations:** More intricate CSS3 transitions inside the main conversation layout.
*   **Advanced Dashboard Stats:** Data graphs charting captures over monthly/weekly cycles.
*   **Handoff to Human:** Option to seamlessly page a human operator mid-conversation.
