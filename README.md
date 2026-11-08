# AI Chat & Live Chat — WordPress Plugin

A free Tidio-style floating widget for WordPress with **Live Chat / AI Chatbot** plus an admin inbox. No account, no signup, no API key needed to start.

Built as a native, self-contained WordPress plugin. Unlike Tidio (which is a thin wrapper around a hosted SaaS widget), this runs on our **shared free token pool** ([usefree.space](https://usefree.space)) proxied server-side. The sibling reference for the free-pool engine is [`wordpress_freeai_agents`](../wordpress_freeai_agents).

## Features

- **Live Chat + AI Chatbot** — visitors chat in a bottom-corner launcher; the AI answers automatically on the free pool until a human takes over.
- **Admin inbox** (`Live Chat` menu) — read threads, reply as a human, take over from the AI, hand back, or close. Threads/messages are stored in two custom tables.
- **Bring your own key** — a saved key routes AI requests directly and skips the free allowance.
- **Brandable** — accent colour, headline, greeting, launcher side, pre-chat survey, default model, system prompt.
- **Placement** — floating launcher on every page, plus the `[bg_live_chat]` shortcode.

## Structure

```
bg-live-chat-free.php          # entry point: header, constants, boot, activation
includes/
  class-pool.php               # free OpenAI-compatible pool proxy (models/chat/quota)
  class-store.php              # custom tables: bglc_thread + bglc_message
  class-rest.php               # REST namespace bglc/v1 (widget + inbox)
  class-settings.php           # Settings screen + option registration
  class-inbox.php              # top-level Live Chat menu + inbox screen
  class-widget.php             # launcher, shortcode, asset loading
public/
  widget.js / widget.css       # front-end: launcher + chat
  inbox.js  / inbox.css        # admin inbox UI
uninstall.php                  # drops tables + options
readme.txt                     # WP.org plugin readme
```

## Install (for testing)

Copy this folder to `wp-content/plugins/wordpress_bg_remover_livechat/`, activate, then open **Live Chat → Settings** and **Live Chat** (inbox). The floating widget appears on the front end.

## License

GPLv2 or later. Website: [usefree.space](https://usefree.space)