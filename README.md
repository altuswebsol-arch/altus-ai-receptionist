# Altus AI Receptionist

A Smith.ai-style AI receptionist for small business websites — a WordPress plugin that turns website visitors into captured leads, even outside business hours.

## What it does

- **AI chat widget** — intent-aware chat brain that answers visitor questions and guides them toward booking/contact
- **Lead capture** — collects name, email, phone and service interest; saves leads in the WordPress admin and emails them to the site owner
- **Owner escalation** — hands off to a human when the conversation needs one
- **Admin dashboard** — view conversations, leads and credit usage
- **Credit system** — 100 starter credits (1 per AI reply), pay-as-you-go top-ups that undercut Smith.ai / Tidio / Intercom pricing
- **Universal embed** — works on any site via shortcode/embed snippet
- **Business-hours aware** — configurable Mon–Fri 9AM–6PM (UK) availability with after-hours lead capture

## Tech stack

- PHP (WordPress plugin, custom DB tables, AJAX endpoints)
- Vanilla JS chat widget (no framework bloat)
- Pluggable LLM backend (Gemini / Groq / OpenAI-compatible)

## Structure

```
altus-ai-receptionist.php   Plugin bootstrap, activation, DB tables
includes/brain.php          Intent engine, reply generation, lead capture
assets/                     Widget CSS/JS, admin dashboard assets
knowledge-base.txt          Default business knowledge base
```

## Install

1. Upload the plugin folder to `wp-content/plugins/`
2. Activate in WordPress admin
3. Configure business name, hours and notification email under **AI Receptionist → Settings**
4. Place the widget via shortcode or auto-embed

Built by [Altus Web Solutions](https://altuswebsolutions.com).
