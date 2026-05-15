# SmartReplyr - WordPress Plugin
> Turn Visitors Into Leads Automatically

**SmartReplyr** is a production-ready WordPress plugin for education institutes. It provides a **strict, high-trust lead generation chatbot** that captures visitor details first, then answers queries using a built-in KB-powered offline AI engine — ensuring zero hallucinations by only answering when confident matches exist in your content.

## Features

1. **Lead Capture First** — Mandatory form (Name, Email, Phone, Course Interest) before chat access.
2. **Offline AI Engine** — Self-contained NLP using BM25/TF-IDF scoring, synonym expansion, and morphological stemming. Works **without any API key**.
3. **Knowledge Base (KB)** — Admin-managed Q&A store that acts as the AI's brain. More entries = smarter bot.
4. **Website Content Scanner** — Auto-crawls all WordPress pages & posts, chunks content by headings, extracts keywords, and makes it searchable by the chatbot. One-click sync from admin.
5. **Page-Context Awareness** — Chatbot answers are boosted 1.5× for content from the page the user is currently viewing.
6. **Strict Answering Mode** — Never guesses or generates unknown info. Requires 45% match confidence.
7. **No AI Dependency** — Operates 100% locally. OpenAI fallback is available but disabled by default to maintain high trust.
8. **Email Notifications** — HTML email sent on lead capture via wp_mail or custom SMTP.
9. **UTM & Lead Source Tracking** — Full UTM parameter capture + custom lead source field.
10. **Tab-Aware Admin UI** — Settings organized by tabs; saving one tab never overwrites another.
11. **Form Builder** — Add, remove, reorder, and customize form fields (Text, Email, Tel, Number, Select, Textarea, Checkbox).
12. **Guest Visibility Hardened** — High-priority hooks ensure the widget loads on sites behind caches, security layers, or password protection.
13. **GDPR Consent** — Configurable consent checkbox on the lead form.
14. **Mobile Close Button** — X button in chatbot header for mobile users.
15. **Quick Reply Chips** — Customizable suggested prompts shown after lead capture.
16. **Custom Avatar & Branding** — WP Media Library integration for bot avatar; color picker for primary color.
17. **Debug & Log System** — All AI responses, webhook deliveries, and email attempts are logged in a dedicated DB table viewable in the admin.
18. **CSV Export** — All leads exportable to CSV from the admin dashboard.
19. **Fault-Tolerant Pipeline** — Guaranteed response on every query; granular try/catch at each stage prevents silent failures.
20. **Smart Widget UX** — Auto-retry on network errors, proper typing state management, contextual error messages.
21. **Excel/CSV KB Import** — Bulk upload Q&A entries using .xlsx or .csv files with strict validation and replace/append modes. Includes a downloadable template.
22. **Dynamic Contact Info** — Dedicated settings tab for Email, Phone, and WhatsApp. Bot automatically shows these when contact intent is detected.
23. **Source-Link Free** — Removed "Source: Knowledge Base" links for a cleaner, higher-trust look.

## How the AI Works (No API Key Required)

See **[SMARTREPLYR_BOT.md](./SMARTREPLYR_BOT.md)** for a complete plain-English + technical breakdown of the AI engine.

### Decision Flow (Strict 3-Stage Pipeline)

```
User Message
    │
    ▼
STAGE 1: Manual KB Match (class-smartreplyr-nlp.php)
    ├── Normalize + Synonym Expand (13 categories, 150+ variants)
    ├── Tokenize + Stem
    ├── BM25 / TF-IDF score vs all KB entries
    ├── Intent detect → boost matching entries
    └── Score ≥ Threshold (40–55)?
         ├── YES → generate_response() → verbatim KB answer
         └── NO ↓

STAGE 1.5: Website Content Match (auto-crawled pages/posts)
    ├── Score site_content chunks using token overlap + keywords + similarity
    ├── Page-context boost: 1.5× if user is on same page
    └── Score ≥ Threshold (40–55)?
         ├── YES → generate_site_content_response()
         └── NO ↓

STAGE 2: OpenAI Fallback (DISABLED)
    └── Bypassed in strict mode to prevent hallucinations

STAGE 3: Smart Offline Fallback (Social & Safe Fallback)
    ├── Greeting / Thanks / Goodbye detection
    ├── Contact / human request detection
    └── Safe controlled fallback ("I don't have that exact info...")
```

## API Structure

Namespace: `smartreplyr/v1`

| Method | Endpoint | Auth | Purpose |
|--------|----------|------|---------|
| POST | `/lead` | Public (HMAC nonce) | Submit lead form |
| POST | `/chat` | Public (HMAC nonce) | Send chat message |
| GET | `/widget-config` | Public | Fetch widget config |
| GET | `/leads` | Admin | List/filter leads |
| GET/POST | `/knowledge` | Admin | KB CRUD |
| DELETE | `/knowledge/{id}` | Admin | Delete KB entry |

## Settings Map (by Tab)

### General & Bot UI
`bot_name`, `openai_api_key`, `openai_model`, `system_prompt`, `welcome_message`, `fallback_message`, `quick_prompts`, `debug_mode`, `gdpr_enabled`, `gdpr_text`

### Contact Info (NEW)
`contact_email`, `contact_phone`, `contact_whatsapp`

### Avatar & Branding
`avatar_url`, `primary_color`, `chat_position`, `courses_list`

### CRM Webhook
`webhook_enabled`, `webhook_url`, `lead_source`, `field_mapping`

### Email & SMTP
`email_enabled`, `notification_email`, `smtp_host`, `smtp_port`, `smtp_username`, `smtp_password`, `smtp_encryption`

### Form Builder
`form_fields` (JSON)

## Database Tables (6 Total)

| Table | Purpose |
|-------|---------|
| `wp_smartreplyr_leads` | Lead data + UTM + consent + sending status + **meta_data** (custom fields) |
| `wp_smartreplyr_conversations` | Full JSON chat history per lead |
| `wp_smartreplyr_settings` | All plugin configuration (key-value) |
| `wp_smartreplyr_knowledge_base` | Q&A entries that power the offline AI |
| `wp_smartreplyr_site_content` | Auto-crawled website content chunks (title, heading, text, keywords, source_url) |
| `wp_smartreplyr_logs` | Webhook, email, AI, and security event logs |

## Changelog

- **Dynamic Contact Info:** Added a new settings tab for Helpline, WhatsApp, and Email. The bot now automatically pulls this data when users ask to "contact", "apply", "call", etc.
- **Source Mentions Removed:** Removed "Source: Knowledge Base" and source links from bot replies for a cleaner and more professional user experience.
- **Improved NLP Accuracy:** Implemented dynamic thresholds (40/45/55) based on query length and an "Intent Mismatch Guard" (+25 to threshold) to prevent the bot from giving wrong-category answers (e.g. course description for fee query).
- **Excel/CSV KB Import:** Added a native, dependency-free import system for .xlsx and .csv files. Supports Append/Replace modes and provides a downloadable template.
- **Strict KB-Only Mode:** Raised thresholds (18/22 → 45%), removed score padding, and added hard filter (min 20 chars) to ensure zero hallucinations.
- **Verbatim Responses:** Removed random greetings, CTAs, and course injections from AI replies. What you put in the KB is exactly what the user sees.
- **Safe Fallbacks:** Gutted topic-specific fallback generation. The bot now only handles social intents and safe redirects to human teams.
- **OpenAI Disabled:** Bypassed Stage 2 to ensure 100% data control and trust.
- **Website Content Scanner:** Auto-crawls all WordPress pages & posts, extracts headings and paragraphs, chunks into searchable segments.
- **Page-Context Awareness:** Content from the page the user is currently viewing gets a 1.5× score boost.
- **REST API Hardened:** Rate limit increased 3→30 req/min, guaranteed response on every query.

### v2.2.3
- **Offline AI Engine:** Complete NLP overhaul using BM25/TF-IDF scoring, synonym expansion, n-gram phrase matching, intent detection, and morphological stemming — works 100% without OpenAI API.
- **Fluent Response Generator:** KB answers are now transformed into personalized, conversational replies with lead's name, course context, and CTAs.
- **Smart Fallback Layer:** Handles greetings, thank-you messages, and contact requests intelligently without any KB entry.
- **Form Builder:** Complete drag-and-drop interface to add/remove/reorder form fields with support for 7+ field types.
- **Customizable Quick Prompts:** Admin can now define suggested chips from the backend.

### v2.2.2
- Fuzzy CRM field mapping (handles "First Name", "Mobile", etc.)
- Custom Lead Source field in CRM settings tab.
- Tab-aware settings save — saving CRM tab no longer unchecks Email notifications.
- Close button (×) added to chatbot header for mobile users.
- SMTP email hardening and detailed failure logging.
- Fixed email class file corruption and path constant (`SMARTREPLYR_AI_PLUGIN_DIR` → `SMARTREPLYR_PLUGIN_DIR`).

### v2.2.1
- High-priority boot hooks (`plugins_loaded`, priority 1) for guest visibility on secured sites.
- Removed hard CDN dependency for `intl-tel-input`.
- HMAC-SHA256 static nonce for robust API security without session dependency.

### v2.2.0
- Conversational lead capture pipeline (state machine: Name → Email → Phone → Course).
- Premium SaaS-level UI with animations and micro-interactions.
- Auto-open widget (5s delay).

### v2.1.0
- Dynamic tab-based admin settings UI.
- Built-in chatbot test simulator.
- Global fatal error sandbox.

### v2.0.0
- Complete rebranding from EduLead AI → SmartReplyr.

## Future Roadmap

- **Vector Embeddings:** Upgrade KB search to Weaviate/pgvector for semantic similarity.
- **Human Handoff:** Live agent transfer mid-conversation.
- **Multi-language Support:** Auto-translate responses for Hindi/regional language users.
- **Auto-Sync on Publish:** Automatically re-crawl a page when it's updated or published.
