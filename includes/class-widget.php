<?php
/**
 * Front-end rendering: launcher, tabs, shortcodes, and asset loading.
 *
 * @package BGLiveChat
 */

defined( 'ABSPATH' ) || exit;

/**
 * Presents the widget and wires its scripts to the REST endpoints.
 */
class BGLC_Widget {

	/** @var BGLC_Widget|null */
	protected static $instance = null;

	/** @var bool Whether assets have already been enqueued this request. */
	protected $enqueued = false;

	/**
	 * Singleton accessor.
	 *
	 * @return BGLC_Widget
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Hook shortcodes and the optional floating launcher.
	 */
	public function register_hooks() {
		add_shortcode( 'bg_live_chat', array( $this, 'shortcode' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'maybe_floating' ) );
	}

	/**
	 * Render the [bg_live_chat] shortcode (the widget inline).
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function shortcode( $atts ) {
		$atts = shortcode_atts( array( 'height' => '460px' ), $atts, 'bg_live_chat' );
		$this->enqueue();
		return $this->render_panel( array( 'inline' => true, 'height' => $atts['height'] ) );
	}

	/**
	 * If the floating option is on, enqueue assets and print the launcher.
	 */
	public function maybe_floating() {
		if ( ! (bool) get_option( 'bglc_floating', true ) ) {
			return;
		}
		if ( ! (bool) get_option( 'bglc_tab_chat', true ) ) {
			return;
		}
		$this->enqueue();
		add_action( 'wp_footer', array( $this, 'print_floating' ) );
	}

	/**
	 * Print the floating launcher markup in the footer.
	 */
	public function print_floating() {
		$pos   = 'left' === get_option( 'bglc_position', 'right' ) ? 'left' : 'right';
		$label = __( 'Chat', 'bg-live-chat-free' );
		?>
		<div class="bglc-widget bglc-pos-<?php echo esc_attr( $pos ); ?>">
			<button type="button" class="bglc-launcher" aria-expanded="false">
				<span class="bglc-launcher-icon" aria-hidden="true">&#128172;</span>
				<span class="bglc-launcher-text"><?php echo esc_html( $label ); ?></span>
			</button>
			<div class="bglc-popover" hidden>
				<?php echo $this->render_panel( array( 'inline' => false ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built internally. ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Enqueue the shared front-end CSS/JS and localize config.
	 */
	public function enqueue() {
		if ( $this->enqueued ) {
			return;
		}
		$this->enqueued = true;

		wp_enqueue_style( 'bglc-widget', BGLC_URL . 'public/widget.css', array(), BGLC_VERSION );
		wp_add_inline_style( 'bglc-widget', ':root{--bglc-color:' . ( sanitize_hex_color( get_option( 'bglc_color', '#2271b1' ) ) ?: '#2271b1' ) . ';}' );
		wp_enqueue_script( 'bglc-widget', BGLC_URL . 'public/widget.js', array(), BGLC_VERSION, true );

		wp_localize_script(
			'bglc-widget',
			'BGLC',
			array(
				'restUrl' => esc_url_raw( rest_url( BGLC_REST::NAMESPACE . '/' ) ),
				'nonce'   => wp_create_nonce( 'bglc' ),
				// Core REST cookie auth requires this too, otherwise logged-in
				// visitors are treated as guests.
				'wpRestNonce' => wp_create_nonce( 'wp_rest' ),
				'version' => BGLC_VERSION,
				'strings' => array(
					'tabChat'      => __( 'Chat', 'bg-live-chat-free' ),
					'close'        => __( 'Close', 'bg-live-chat-free' ),
					'placeholder'  => __( 'Type your message…', 'bg-live-chat-free' ),
					'send'         => __( 'Send', 'bg-live-chat-free' ),
					'thinking'     => __( 'Typing…', 'bg-live-chat-free' ),
					'pending'      => __( 'Thanks! A team member will reply here shortly.', 'bg-live-chat-free' ),
					'newChat'      => __( 'New', 'bg-live-chat-free' ),
					'powered'      => __( 'Free AI by usefree.space', 'bg-live-chat-free' ),
					'nameLabel'    => __( 'Your name', 'bg-live-chat-free' ),
					'emailLabel'   => __( 'Your email', 'bg-live-chat-free' ),
					'startChat'    => __( 'Start chat', 'bg-live-chat-free' ),
				),
			)
		);
	}

	/**
	 * Build the widget panel markup (chat).
	 *
	 * @param array $args Rendering args.
	 * @return string
	 */
	protected function render_panel( $args ) {
		$inline  = ! empty( $args['inline'] );
		$height  = $inline && ! empty( $args['height'] ) ? $args['height'] : '460px';
		$headline  = get_option( 'bglc_headline', '' );
		$style     = ' style="--bglc-log-height:' . esc_attr( $height ) . '"';

		ob_start();
		?>
		<div class="bglc-panel<?php echo $inline ? ' bglc-inline' : ''; ?>"<?php echo $style; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
			<div class="bglc-head">
				<span class="bglc-title"><?php echo esc_html( $headline ); ?></span>
				<?php if ( ! $inline ) : ?>
					<button type="button" class="bglc-close" aria-label="<?php esc_attr_e( 'Close', 'bg-live-chat-free' ); ?>">&times;</button>
				<?php endif; ?>
			</div>

			<div class="bglc-pane bglc-pane-chat is-active" data-pane="chat">
				<?php echo $this->chat_markup(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Chat pane inner markup.
	 *
	 * @return string
	 */
	protected function chat_markup() {
		$greeting = get_option( 'bglc_greeting', '' );
		ob_start();
		?>
		<?php if ( (bool) get_option( 'bglc_prechat', false ) ) : ?>
			<form class="bglc-prechat" data-region="prechat">
				<input type="text" data-region="name" autocomplete="name" placeholder="<?php esc_attr_e( 'Your name', 'bg-live-chat-free' ); ?>" />
				<input type="email" data-region="email" autocomplete="email" placeholder="<?php esc_attr_e( 'Your email', 'bg-live-chat-free' ); ?>" />
				<button type="submit" class="bglc-send"><?php esc_html_e( 'Start chat', 'bg-live-chat-free' ); ?></button>
			</form>
		<?php endif; ?>
		<div class="bglc-log" data-region="log" role="log" aria-live="polite">
			<?php if ( '' !== $greeting ) : ?>
				<div class="bglc-msg bglc-msg-bot"><?php echo esc_html( $greeting ); ?></div>
			<?php endif; ?>
		</div>
		<form class="bglc-input" data-region="form">
			<textarea class="bglc-text" data-region="text" rows="2" placeholder="<?php esc_attr_e( 'Type your message…', 'bg-live-chat-free' ); ?>"></textarea>
			<button type="submit" class="bglc-send"><?php esc_html_e( 'Send', 'bg-live-chat-free' ); ?></button>
		</form>
		<div class="bglc-foot"><?php esc_html_e( 'Free AI by usefree.space', 'bg-live-chat-free' ); ?></div>
		<?php
		return ob_get_clean();
	}
}
