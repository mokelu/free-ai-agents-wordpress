# Status — AI Chat & Live Chat (WordPress)

- [x] Scaffold native WP plugin (multi-file, `bglc` prefix, MIT, uninstall).
- [x] Free-pool proxy reused from `wordpress_freeai_agents` (models / chat / quota; keys stay server-side; 429 → friendly usefree.space note).
- [x] Custom tables `wp_bglc_thread` + `wp_bglc_message` (dbDelta), grouped per visitor.
- [x] REST namespace `bglc/v1`: config, models, history, chat (public nonce) + threads, messages, reply, status (admin).
- [x] Live Chat + AI Chatbot: bot auto-replies until a human takes over; messages always stored.
- [x] Admin inbox under a top-level **Live Chat** menu (filters, transcript, reply, take over / hand back / close, pending-count bubble).
- [x] Tidio-style launcher + `[bg_live_chat]` shortcode; brandable settings.
- [x] Background remover removed (2026-11-08) — chat-only product for shipping.
- [ ] `php -l` clean + activate on a live WP install; smoke-test chat and inbox takeover.
- [ ] Publish to WordPress.org (SVN) and support.

_Ecosystem: WordPress Plugin | Product: AI Chat & Live Chat — All free | Version: 1.1.0_
_Reference: Tidio `tidio-live-chat` (UX/structure), `wordpress_freeai_agents` (free-pool engine)._