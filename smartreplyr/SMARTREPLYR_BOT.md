# SmartReplyr Chatbot — Complete Guide
> Everything about how SmartReplyr works, in plain English and technical detail.

---

## 🧑‍🎓 What is SmartReplyr?

SmartReplyr is a chatbot that lives on your website. When a visitor arrives, it pops up (automatically, after 5 seconds, or in a corner they can click) and does two things:

1. **Captures the visitor's details** (Name, Email, Phone, Course Interest) before letting them chat.
2. **Answers their questions** using your own custom Knowledge Base AND your website's actual content — like a smart FAQ that actually understands what they're asking.

**The key difference from other chatbots:** SmartReplyr is a **strict, high-trust system**. It does NOT guess or "generate" information. It only answers if it finds a high-confidence match (45%+) in your Knowledge Base or website content. No hallucinations, no AI subscriptions, 100% control.

---

## 👤 What the Visitor (User) Experiences

### Step 1 — The Widget Appears
- A colorful circular button appears in the corner of your website (bottom-right by default).
- It glows softly to attract attention.
- After 5 seconds, it automatically opens and says hello.

### Step 2 — The Lead Form
- Before chatting, the visitor fills in a short form:
  - Full Name
  - Email Address
  - Phone Number
  - Course Interest (a dropdown you configure)
- An optional GDPR consent checkbox.
- They click **"Start Chat"**.

### Step 3 — The Chat Experience
- A success animation plays ("Details Received... Connecting you to our assistant...").
- The bot greets them with a welcome message.
- **Quick Reply Chips** appear (e.g., "View Courses", "Fee Structure", "Apply Now") — these are fully customizable from the backend.
- The visitor types any question and gets an instant, personalized reply.
- There is a **"×" close button** in the header to dismiss the chat on mobile.

---

## 🤖 How the AI Works (Plain English)

Think of the bot's brain like a very well-organized filing cabinet. Every entry in your **Knowledge Base** is one card in that cabinet. When a visitor asks something:

### Step 1: The Bot Understands the Question
Before comparing, the bot cleans the question:
- Removes filler words like "a", "the", "is", "can you"
- Converts synonyms: "fees" = "cost" = "charges" = "tuition" — they all mean the same
- Simplifies word forms: "admissions", "admitted", "admitting" → all become "admission"
- Breaks the sentence into key words

### Step 2: The Bot Scores Every KB Card
For each Q&A in your Knowledge Base, the bot calculates a **relevance score** (0–100) using:
- **TF-IDF / BM25 scoring** (the same technique used by Google and Elasticsearch)
- **Exact phrase bonuses** — if the visitor's whole question appears directly
- **Intent matching** — if keywords strongly suggest a topic (like "fees" → "cost" intent)
- **Fallback token overlap** — any words in common

### Step 3: The Bot Picks the Best Match
If the best score is **65 or above**, that answer is used. This is a strict threshold to ensure the bot never gives a "wrong" answer. If no manual KB match is found, the bot moves on to **website content matching**.

### Step 3.5: Website Content Matching (NEW)
If the manual KB doesn't have a match, the bot searches **auto-crawled website content** — your actual pages and posts:
- The bot knows which page the visitor is currently on, and **boosts answers from that page by 1.5×**
- If a match is found with a score of **40–55+** (depending on query length), it returns the answer.
- This means the bot can answer questions about ANY content on your site, but only if it's sure.

### Step 4: The Bot Delivers a Verbatim Reply
The bot returns the content exactly as stored in your Knowledge Base or on your website. It only replaces basic placeholders (like `{{lead_name}}`). It does not add source links or random greetings, ensuring 100% accuracy and trust.

### Step 5: Smart Fallback (Social & Safe Redirects)
If the answer isn't in the KB or website content, the bot **never guesses**. Instead, it handles social intents or provides a safe fallback:

| Intent Detected | Bot's Response |
|-----------------|----------------|
| Greeting (hi, hello) | Warm welcome to the institute. |
| Thanks (thank you) | Polite acknowledgement. |
| Goodbye (bye, tata) | Friendly farewell. |
| Contact (call, apply) | Shows your **Helpline, WhatsApp, and Email** from Settings. |
| **Anything Else** | "I don't have that exact info... Please contact our team." |

> 💡 **SmartReplyr prioritizes accuracy over everything else.** It's better to say "I don't know" than to give a wrong answer.

---

## 🔧 What Admins Can Configure

Go to **WordPress Admin > SmartReplyr > Settings** to find these tabs:

### Tab 1: General & Bot UI
| Setting | What it does |
|---------|-------------|
| Bot Name | The name shown in the chatbot header |
| OpenAI API Key | Optional. If set, OpenAI is used when KB fails |
| AI Model | GPT-4o Mini / GPT-4o / GPT-3.5 Turbo |
| System Prompt | Instructions that shape how OpenAI responds |
| Welcome Message | First message shown after lead form is submitted |
| Fallback Message | Shown if all else fails |
| Quick Reply Prompts | Comma-separated chips shown to the user (e.g., View Courses,Fee Structure) |
| Debug Mode | Logs AI source, intent, and match score for each reply |
| GDPR Consent | Toggle the consent checkbox on the lead form |

### Tab 2: 📞 Contact Info (NEW)
| Setting | What it does |
|---------|-------------|
| Contact Email | The email shown when the user asks to connect/contact |
| Contact Phone | The helpline number shown to users |
| WhatsApp Number | The number used for WhatsApp redirect/display |

### Tab 3: Avatar & Branding
| Setting | What it does |
|---------|-------------|
| Primary Color | Widget button and header gradient color |
| Bot Avatar | Upload a photo for the bot via WP Media Library |
| Chat Position | Bottom-Right or Bottom-Left |
| Courses Dropdown | Comma-separated list of courses for the lead form dropdown |

### Tab 3: CRM Webhook
| Setting | What it does |
|---------|-------------|
| Enable Webhook | Toggle CRM delivery on/off |
| Webhook Endpoint URL | Your CRM's receiving URL (LeadSquared, Zapier, etc.) |
| Lead Source Name | A label attached to every lead (e.g., "Delhi Campus Chatbot") |
| Field Mapping (JSON) | Maps SmartReplyr internal keys to your CRM's expected keys |

**Example field mapping:**
```json
{
  "name": "First Name",
  "email": "Email Address",
  "phone": "Mobile",
  "course_interest": "Program",
  "lead_source": "Source"
}
```

**Supported fuzzy aliases** (you don't have to be exact):
- `name` → also understands "Full Name", "Contact Name", "First Name"
- `phone` → also understands "Mobile", "Mobile Number", "Contact"
- `email` → also understands "Email Address", "Email ID"
- `lead_source` → also understands "Source", "Origin"

### Tab 4: Email & SMTP
| Setting | What it does |
|---------|-------------|
| Enable Notifications | Toggle email on new lead capture |
| Send To | Where notification emails go |
| SMTP Host | e.g., smtp.gmail.com |
| Port | 587 (TLS) or 465 (SSL) |
| Username | Your email login (must match "From" address for Gmail/Outlook) |
| Password | App password (not your regular password for Gmail) |
| Encryption | TLS or SSL |

### Tab 5: Form Builder
This tab allows you to completely customize the lead capture form:
- **Drag and Drop:** Reorder fields by dragging them up or down.
- **Visibility:** Hide/show any field using the toggle switch.
- **Requirement:** Mark any field as required or optional.
- **Supported Fields:** Add custom fields like Text, Email, Phone, Number, Dropdown, Textarea, and Checkbox.
- **Data Storage:** All custom field values are stored in the database (`meta_data` column) and can be seen in the Leads list.

> ⚠️ **Core Fields:** Name, Email, Phone, and Course Interest are "Core" fields. They are optimized for the system and cannot be deleted, but you can hide or reorder them.

> ⚠️ **Gmail users:** You must use an **App Password**, not your regular Gmail password. Enable 2-factor authentication on your Google account first.

---

## 🔍 Website Content Scanner

Go to **WordPress Admin > SmartReplyr > Website Scanner** to auto-index your site.

### How It Works
1. **Scan** — Crawls all published pages and posts via `WP_Query`
2. **Chunk** — Splits content by H1–H4 headings into searchable segments (30–800 chars each)
3. **Clean** — Strips shortcodes, scripts, styles, excessive whitespace
4. **Keywords** — Auto-extracts top 10 keywords per chunk using term-frequency scoring
5. **Dedup** — SHA256 hashing prevents duplicate content
6. **Store** — Each chunk is saved with: page title, heading, text, keywords, and source URL

### Controls
| Button | What it does |
|--------|--------------|
| 🔄 Sync Website Content | Scans all published pages/posts and indexes new content |
| ♻️ Clear & Full Re-Sync | Clears all indexed content and re-scans everything from scratch |

### Stats Dashboard
- **Pages Indexed** — How many unique pages/posts have been crawled
- **Content Chunks** — Total number of searchable content segments
- **Available Pages** — How many published pages/posts exist on the site
- **Last Sync** — When the scanner last ran

> 💡 **Pro tip:** Re-sync after updating your website content. The chatbot will immediately start answering questions from the updated pages.

> ⚠️ **Gmail users:** You must use an **App Password**, not your regular Gmail password. Enable 2-factor authentication on your Google account first.

---

## 📊 Where Lead Data Goes

Every submitted lead is saved in the WordPress database and visible under **SmartReplyr > Leads**. Each lead record contains:

- Name, Email, Phone, Course Interest
- Page URL and Page Title where the chat happened
- Referrer URL
- UTM parameters (source, medium, campaign, term, content)
- Custom Lead Source
- Whether a webhook was sent ✓/✗
- Whether a notification email was sent ✓/✗
- Timestamp

You can **export all leads to CSV** from the admin leads page.

---

## 🧠 Building the Knowledge Base (The Bot's Brain)

Go to **SmartReplyr > Knowledge Base** to add Q&A entries.

### What Makes a Good KB Entry?
| Field | Advice |
|-------|--------|
| Question | Write it the way a student would ask. Natural language. |
| Answer | Be detailed and clear. The bot will use this verbatim (with slight personalization). |
| Keywords | JSON array of key terms: `["fees", "cost", "tuition", "scholarship"]` |
| Intent | A label grouping related questions: `fees`, `admission`, `campus`, `placement` |
| Category | Broad grouping: `general`, `financial`, `academic` |

### Example Entry
```
Question: What is the fee structure for MBA?
Answer: Our MBA program fee is ₹1,20,000 per year. We offer merit-based scholarships covering up to 50% of the fee. EMI options are also available.
Keywords: ["fees", "fee", "cost", "mba fees", "scholarship", "emi"]
Intent: fees
Category: financial
```

### Tips for Maximum Smartness
- Add 3–5 variations of common questions (fees, admission, courses, placement, contact)
- Always fill in Keywords — this dramatically improves accuracy
- Use the same intent labels consistently across entries (e.g., always use `fees` not mixing with `cost`)
- Set Debug Mode = ON temporarily to see match scores, then optimize low-confidence entries

---

## 📥 Excel/CSV Knowledge Base Import

For bulk management, you can import your Knowledge Base from an Excel (.xlsx) or CSV file.

### How to Import
1. Go to **SmartReplyr > Knowledge Base**.
2. Click **Download Template** to get a sample file with the correct headers.
3. Add your Q&As to the file (Question and Answer are required).
4. Click **Import Excel/CSV**, select your file, and choose your mode:
   - **Append**: Adds new entries to your existing KB.
   - **Replace**: Deletes your current KB and replaces it with the new file.
5. Click **Start Import**. A summary will show you how many rows were imported or skipped.

### Required Format (Columns)
| Column | Description |
|--------|-------------|
| **Question** | Required. The visitor's potential question. |
| **Answer** | Required. The factual response the AI will provide. |
| **Keywords** | Optional. Comma-separated terms to boost matching. |
| **Intent** | Optional. A label like `fees` or `admission`. |
| **Category** | Optional. A grouping like `financial` or `academic`. |
| **Source** | Optional. Reference for where the info came from. |

### Strict Validation Rules
To maintain the **Strict KB-Only** standard, the importer enforces these rules:
- **No Empty Fields**: Rows missing a Question or Answer are automatically skipped.
- **Min Length**: Questions must be at least 3 characters; Answers must be at least 10 characters.
- **Clean Data**: The system automatically strips invalid hidden characters and trims whitespace.
- **Normalization**: Keywords are automatically cleaned and lowercased for better matching.

---

## 🏗 Technical Architecture (For Developers)

### File Structure
```
smartreplyr/
├── smartreplyr.php                  # Plugin bootstrap, constants, hooks
├── includes/
│   ├── class-smartreplyr-activator.php   # DB creation, migration, seed defaults
│   ├── class-smartreplyr-db.php          # All DB queries (leads, KB, site_content, settings, logs)
│   ├── class-smartreplyr-nlp.php         # ★ Offline AI Engine (BM25, synonyms, stemming, site content matching)
│   ├── class-smartreplyr-crawler.php     # ★ Website Content Scanner (crawl, chunk, keyword extraction)
│   ├── class-smartreplyr-ai.php          # OpenAI API integration (optional fallback)
│   ├── class-smartreplyr-rest-api.php    # All REST endpoints (/lead, /chat, /knowledge)
│   ├── class-smartreplyr-webhook.php     # CRM webhook delivery with fuzzy mapping
│   ├── class-smartreplyr-email.php       # Email notifications with SMTP support
│   └── class-smartreplyr-loader.php      # Hook registration manager
├── public/
│   ├── class-smartreplyr-public.php      # Script enqueuing + widget root render
│   ├── js/widget.js                      # Frontend chatbot widget (Vanilla JS, auto-retry)
│   └── css/widget.css                    # Widget styles (glassmorphism, mobile-first)
├── admin/
│   ├── class-smartreplyr-admin.php       # Admin menu, settings save (tab-aware), AJAX
│   ├── js/admin.js                       # Admin JS (KB editor, media uploader)
│   ├── css/admin.css                     # Admin panel styles
│   └── views/
│       ├── settings-page.php             # Main settings wrapper + tab router
│       ├── admin-dashboard.php           # Lead stats dashboard
│       ├── admin-leads.php               # Leads list + filter + CSV export
│       ├── admin-conversations.php       # Chat history viewer
│       ├── admin-knowledge-base.php      # KB entry manager
│       ├── admin-website-scanner.php     # ★ Website content scanner UI
│       └── sections/
│           ├── general.php               # General + AI tab
│           ├── avatar.php                # Branding tab
│           ├── crm.php                   # CRM Webhook tab
│           ├── email.php                 # Email + SMTP tab
│           ├── form-builder.php          # Form Builder tab
│           └── test-bot.php              # Live bot simulator tab
└── assets/
    └── img/default-avatar.svg           # Default bot avatar
```

### NLP Engine Deep-Dive (`class-smartreplyr-nlp.php`)

#### `match_query($user_query, $lead_context)` — Main Entry Point
1. Loads all KB entries from DB
2. Calls `normalize_advanced()` + `tokenize()` on the user query
3. Computes IDF (Inverse Document Frequency) for user tokens across the KB
4. Iterates KB entries, calls `score_entry()` for each
5. Hard filter: Rejects matches if the answer is too short (< 20 chars)
6. Returns the best match if score ≥ threshold (40–55), else null

#### `match_site_content($user_query, $page_context, $lead_context)` — Website Content Matching
1. Loads all chunks from `site_content` table
2. Scores each chunk using token overlap (40%), string similarity (25%), keyword match (25%), title/heading bonus (10%)
3. Applies page-context boost: 1.5× if user is currently on the same page
4. Returns best match if score ≥ threshold (40–55)

#### `score_entry()` — Hybrid Scorer
| Component | Weight | Method |
|-----------|--------|--------|
| BM25/TF-IDF | 40% | Term frequency normalized by document length |
| String Similarity | 25% | PHP `similar_text()` |
| Keyword Overlap | 25% | Exact KB keyword match or token intersection |
| Exact Phrase Bonus | 10% | Full query substring match or n-gram match |
| Intent Match Boost | ×1.25 | Applied when detected intent matches entry intent |
| Intent Mismatch Penalty | ×0.80 | Softer penalty applied when intents conflict |
| Intent Mismatch Guard | +25 | Adds 25 points to threshold if intents don't match (prevents wrong-category answers) |

#### `normalize_advanced($text)`
1. Lowercase + HTML decode
2. Strip punctuation (keep a-z, 0-9, spaces, hyphens)
3. Remove stopwords (a, the, is, for, how, what, etc.)
4. Call `expand_synonyms()` — maps 150+ common variants across 13 categories to canonical terms (fees, admission, courses, campus, placement, duration, contact, ranking, scholarship, hostel, exam, online, safety)
5. Return clean string

#### `generate_response($match, $lead, $history)`
- Takes the matched KB `answer` field verbatim.
- Replaces `{{institute_name}}` and `{{lead_name}}` placeholders.
- **Strict Mode:** No source links, greetings, CTA injections, or course mentions.

#### `smart_fallback($user_query, $lead, $history)`
| Trigger | Response |
|---------|----------|
| Social (hi, thanks, bye) | Polite, social response. |
| Human (call, counselor) | Redirects to human team/contact page. |
| Substantive questions | Safe fallback: "I don't have that exact info..." |

### REST API Security
- **Nonce:** HMAC-SHA256 derived from WordPress salt — stateless, cache-safe, no session required
- **Rate Limiting:** Max 30 requests/minute per IP, enforced via WP transients
- **Fault Tolerance:** Chat endpoint always returns `success: true` with a reply — even if the entire AI pipeline crashes
- **Admin endpoints:** Standard `current_user_can('manage_options')` check

### Settings Save Architecture (Tab-Aware)
Each settings tab maps to a specific field group. When the form is submitted, a hidden `active_tab` field identifies the current tab. Only fields belonging to that tab are saved — preventing cross-tab checkbox overwrites.

```php
$tab_fields_map = [
    'general' => ['bot_name', 'openai_api_key', 'quick_prompts', ...],
    'avatar'  => ['avatar_url', 'primary_color', ...],
    'crm'     => ['webhook_enabled', 'webhook_url', 'lead_source', 'field_mapping'],
    'email'   => ['email_enabled', 'notification_email', 'smtp_host', ...],
];
```

### CRM Webhook Fuzzy Mapping
The webhook engine supports fuzzy key resolution. If you map `"First Name"` → `"name"`, it normalizes case and compares against an alias table:

```php
$aliases = [
    'name'        => ['name', 'full name', 'first name', 'contact name'],
    'email'       => ['email', 'email address', 'email id'],
    'phone'       => ['phone', 'mobile', 'mobile number', 'contact'],
    'lead_source' => ['lead source', 'source', 'origin'],
    ...
];
```

---

## ❓ Frequently Asked Questions

**Q: Does the bot need an OpenAI API key to work?**  
A: No. The bot works entirely from your Knowledge Base using its built-in offline AI engine. The OpenAI API key is optional — it provides extra intelligence when no KB match is found.

**Q: How do I make the bot smarter?**  
A: Add more entries to the Knowledge Base. The more specific your Q&As are, the better. Fill in Keywords and Intent fields for every entry.

**Q: Why is the chat not showing for visitors?**  
A: Check that the site's cache has been cleared (WP Rocket / Cloudflare). Also verify the page isn't behind a "Password Protected" mode that blocks all JS. Go to SmartReplyr > Settings > General and enable Debug Mode to identify issues.

**Q: Leads aren't going to the CRM — what do I check?**  
A: Go to SmartReplyr > Settings > Logs. You'll see the exact error message from the webhook attempt. Most common issues: wrong URL, mismatched field names (the fuzzy mapper handles common cases — just check the JSON format is valid).

**Q: Emails aren't sending via Gmail SMTP — why?**  
A: Gmail requires you to use an **App Password** (not your normal password). Also ensure the "From" email in your settings matches your Gmail username exactly.

**Q: How do I update the chatbot's suggested chips?**  
A: Go to General & Bot UI tab → Quick Reply Prompts → enter comma-separated values → Save.

**Q: What is the Website Scanner?**  
A: The Website Scanner (SmartReplyr > Website Scanner) auto-crawls all your published pages and posts, extracts the text content, and makes it searchable by the chatbot. Click "Sync Website Content" after updating your site. The bot will then answer questions based on your actual website content — no manual KB entries needed.

**Q: Does the chatbot know which page the visitor is on?**  
A: Yes! The chatbot receives the visitor's current page URL. When searching website content, answers from the same page are boosted 1.5× — making responses contextually relevant to what the visitor is reading.
