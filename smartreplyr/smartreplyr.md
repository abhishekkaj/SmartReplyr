# SmartReplyr - WordPress Plugin
> Turn Visitors Into Leads Automatically

**SmartReplyr** is a production-ready WordPress plugin for education institutes. It provides an AI-powered lead generation chatbot that captures visitor details first, then answers queries using a built-in KB-powered offline AI engine.

## Features

1. **Lead Capture First** — Mandatory form (Name, Email, Phone, Course Interest) before chat access.
2. **Offline AI Engine** — Self-contained NLP using BM25/TF-IDF scoring, synonym expansion, and morphological stemming. Works **without any API key**.
3. **Knowledge Base (KB)** — Admin-managed Q&A store that acts as the AI's brain. More entries = smarter bot.
4. **OpenAI Fallback** — Optional. If an API key is configured, OpenAI GPT is used when KB matching fails.
5. **Flexible CRM Webhook** — Sends lead data to any CRM (LeadSquared, Zapier, Make) with fuzzy field mapping and custom lead source.
6. **Email Notifications** — HTML email sent on lead capture via wp_mail or custom SMTP.
7. **UTM & Lead Source Tracking** — Full UTM parameter capture + custom lead source field.
8. **Tab-Aware Admin UI** — Settings organized by tabs; saving one tab never overwrites another.
9. **Guest Visibility Hardened** — High-priority hooks ensure the widget loads on sites behind caches, security layers, or password protection.
10. **GDPR Consent** — Configurable consent checkbox on the lead form.
11. **Mobile Close Button** — X button in chatbot header for mobile users.
12. **Quick Reply Chips** — Customizable suggested prompts shown after lead capture.
13. **Custom Avatar & Branding** — WP Media Library integration for bot avatar; color picker for primary color.
14. **Debug & Log System** — All AI responses, webhook deliveries, and email attempts are logged in a dedicated DB table viewable in the admin.
15. **CSV Export** — All leads exportable to CSV from the admin dashboard.

## How the AI Works (No API Key Required)

See **[SMARTREPLYR_BOT.md](./SMARTREPLYR_BOT.md)** for a complete plain-English + technical breakdown of the AI engine.

### Decision Flow

```
User Message
    │
    ▼
NLP Engine (class-smartreplyr-nlp.php)
    ├── Normalize + Synonym Expand
    ├── Tokenize + Stem
    ├── BM25 / TF-IDF score vs all KB entries
    ├── Intent detect → boost matching entries
    └── Score ≥ 30?
         ├── YES → generate_response() → fluent, personalized reply
         └── NO  → OpenAI configured?
                      ├── YES → OpenAI API → reply
                      └── NO  → smart_fallback() → graceful intelligent reply
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

### Avatar & Branding
`avatar_url`, `primary_color`, `chat_position`, `courses_list`

### CRM Webhook
`webhook_enabled`, `webhook_url`, `lead_source`, `field_mapping`

### Email & SMTP
`email_enabled`, `notification_email`, `smtp_host`, `smtp_port`, `smtp_username`, `smtp_password`, `smtp_encryption`

## Database Tables (5 Total)

| Table | Purpose |
|-------|---------|
| `wp_smartreplyr_leads` | Lead data + UTM + consent + sending status |
| `wp_smartreplyr_conversations` | Full JSON chat history per lead |
| `wp_smartreplyr_settings` | All plugin configuration (key-value) |
| `wp_smartreplyr_knowledge_base` | Q&A entries that power the offline AI |
| `wp_smartreplyr_logs` | Webhook, email, AI, and security event logs |

## Changelog

### v2.2.3 (Current)
- **Offline AI Engine:** Complete NLP overhaul using BM25/TF-IDF scoring, synonym expansion, n-gram phrase matching, intent detection, and morphological stemming — works 100% without OpenAI API.
- **Fluent Response Generator:** KB answers are now transformed into personalized, conversational replies with lead's name, course context, and CTAs.
- **Smart Fallback Layer:** Handles greetings, thank-you messages, and contact requests intelligently without any KB entry.
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
- **Website Scraper:** Auto-populate KB from institute website URLs.
- **Human Handoff:** Live agent transfer mid-conversation.
- **Multi-language Support:** Auto-translate responses for Hindi/regional language users.
