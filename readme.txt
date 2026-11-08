=== AI Chat & Live Chat - All free ===
Contributors: riemannresearch
Tags: live chat, chatbot, ai, free
Requires at least: 5.8
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A free live chat widget with an AI chatbot. No account, no signup, no API key needed to start.

== Description ==

AI Chat & Live Chat gives your WordPress site a friendly floating widget, and it is free to run.

* **Live Chat + AI Chatbot** — visitors chat in real time. An AI assistant answers automatically on a shared free token pool (default endpoint api.usefree.space) until a human takes over.
* **Admin inbox** — every conversation is stored on your own site. Read threads, reply as a human, take over from the AI, hand back, or close.
* **Bring your own key** — a saved key routes AI requests directly and skips the free allowance.
* **Server-side proxy** — raw pool requests and any API key never touch the browser.
* **Flexible placement** — a floating launcher on every page, plus the `[bg_live_chat]` shortcode.
* **Brandable** — set the accent colour, headline, greeting, launcher side, and pre-chat survey.

When the free allowance is used up, the widget saves the message and shows a friendly note pointing to usefree.space for more free tokens.

== Installation ==

1. Upload the `bg-live-chat-free` folder to `/wp-content/plugins/`.
2. Activate the plugin through the *Plugins* menu.
3. Open *Live Chat* in the admin menu for the inbox, and *Live Chat → Settings* to configure.
4. The floating widget appears on your site. Embed it inline anywhere with `[bg_live_chat]`.

== Frequently Asked Questions ==

= Do I need an API key or account? =

No. AI replies run on the shared free pool by default.

= Where are conversations stored? =

In two custom tables on your own site (`wp_bglc_thread` and `wp_bglc_message`), grouped by visitor.

== Changelog ==

= 1.1.0 =
* Removed the background remover; plugin now ships as AI Chat & Live Chat only.

= 1.0.0 =
* First release: Tidio-style floating widget with Chat and BG Remover tabs, free-pool AI chatbot, admin inbox with take-over/hand-back/close, client-side background removal, optional save-to-library, shortcodes, and brandable settings.