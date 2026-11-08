<?php
/**
 * Admin inbox: the top-level Live Chat menu and the conversation manager.
 *
 * @package BGLiveChat
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registers the top-level menu and renders the agent inbox screen.
 */
class BGLC_Inbox {

	/** Menu slug. */
	const MENU = 'bglc';

	/** @var BGLC_Inbox|null */
	protected static $instance = null;

	/** @var string|null Hook suffix of our top-level page. */
	protected $page_hook = null;

	/**
	 * Singleton accessor.
	 *
	 * @return BGLC_Inbox
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Hook the menu and asset loading.
	 */
	public function register_hooks() {
		add_action( 'admin_menu', array( $this, 'menu' ), 9 );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
	}

	/**
	 * Add the top-level Live Chat menu.
	 */
	public function menu() {
		$pending = BGLC_Store::pending_count();
		$bubble  = $pending > 0 ? ' <span class="bglc-count">' . (int) $pending . '</span>' : '';

		$this->page_hook = add_menu_page(
			__( 'AI Chat & Live Chat', 'bg-live-chat-free' ),
			__( 'Live Chat', 'bg-live-chat-free' ) . $bubble,
			'manage_options',
			self::MENU,
			array( $this, 'render_page' ),
			'dashicons-format-chat',
			26
		);
	}

	/**
	 * Enqueue inbox assets only on our page.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function assets( $hook ) {
		if ( $hook !== $this->page_hook ) {
			return;
		}

		wp_enqueue_style( 'bglc-inbox', BGLC_URL . 'public/inbox.css', array(), BGLC_VERSION );
		wp_enqueue_script( 'bglc-inbox', BGLC_URL . 'public/inbox.js', array(), BGLC_VERSION, true );
		wp_localize_script(
			'bglc-inbox',
			'BGLCInbox',
			array(
				'restUrl' => esc_url_raw( rest_url( BGLC_REST::NAMESPACE . '/' ) ),
				'nonce'   => wp_create_nonce( 'bglc' ),
				// WordPress core REST cookie auth also demands this nonce;
				// without it the request is treated as a guest.
				'wpRestNonce' => wp_create_nonce( 'wp_rest' ),
				'strings' => array(
					'noThreads'   => __( 'No conversations yet.', 'bg-live-chat-free' ),
					'noMessages'  => __( 'Select a conversation.', 'bg-live-chat-free' ),
					'you'         => __( 'You', 'bg-live-chat-free' ),
					'bot'         => __( 'AI', 'bg-live-chat-free' ),
					'visitor'     => __( 'Visitor', 'bg-live-chat-free' ),
					'send'        => __( 'Send', 'bg-live-chat-free' ),
					'takeOver'    => __( 'Take over from AI', 'bg-live-chat-free' ),
					'handBack'    => __( 'Hand back to AI', 'bg-live-chat-free' ),
					'close'       => __( 'Close', 'bg-live-chat-free' ),
					'saved'       => __( 'Saved', 'bg-live-chat-free' ),
					'failed'      => __( 'Could not reach the site. Reload and try again.', 'bg-live-chat-free' ),
					'loading'     => __( 'Loading…', 'bg-live-chat-free' ),
				),
			)
		);
	}

	/**
	 * Render the inbox screen skeleton; inbox.js fills it in.
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap bglc-inbox">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Live Chat Inbox', 'bg-live-chat-free' ); ?></h1>
			<hr class="wp-header-end" />
			<p class="bglc-inbox-hint"><?php esc_html_e( 'The AI answers visitors automatically. Open a conversation to read it, reply as a human, or take over.', 'bg-live-chat-free' ); ?></p>

			<div class="bglc-inbox-layout">
				<div class="bglc-threadlist">
					<div class="bglc-filters">
						<button type="button" class="bglc-filter is-active" data-status="all"><?php esc_html_e( 'All', 'bg-live-chat-free' ); ?></button>
						<button type="button" class="bglc-filter" data-status="human"><?php esc_html_e( 'Needs you', 'bg-live-chat-free' ); ?></button>
						<button type="button" class="bglc-filter" data-status="bot"><?php esc_html_e( 'AI', 'bg-live-chat-free' ); ?></button>
						<button type="button" class="bglc-filter" data-status="closed"><?php esc_html_e( 'Closed', 'bg-live-chat-free' ); ?></button>
					</div>
					<div class="bglc-threads" data-region="threads"></div>
				</div>

				<div class="bglc-thread">
					<div class="bglc-thread-head" data-region="head">
						<span class="bglc-thread-title" data-region="title"><?php esc_html_e( 'Select a conversation', 'bg-live-chat-free' ); ?></span>
						<span class="bglc-thread-actions" data-region="actions" hidden></span>
					</div>
					<div class="bglc-messages" data-region="messages"></div>
					<form class="bglc-reply" data-region="reply" hidden>
						<textarea data-region="replytext" rows="2" placeholder="<?php esc_attr_e( 'Write a reply…', 'bg-live-chat-free' ); ?>"></textarea>
						<button type="submit" class="button button-primary"><?php esc_html_e( 'Send', 'bg-live-chat-free' ); ?></button>
					</form>
				</div>
			</div>
		</div>
		<?php
	}
}
