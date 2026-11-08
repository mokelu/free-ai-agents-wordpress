<?php
/**
 * Admin settings screen for the plugin.
 *
 * @package BGLiveChat
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registers the options and renders the Settings submenu.
 */
class BGLC_Settings {

	/** Parent menu slug shared with the inbox. */
	const PARENT = 'bglc';

	/** @var BGLC_Settings|null */
	protected static $instance = null;

	/**
	 * Singleton accessor.
	 *
	 * @return BGLC_Settings
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Hook admin menu + settings registration.
	 */
	public function register_hooks() {
		add_action( 'admin_menu', array( $this, 'menu' ), 10 );
		add_action( 'admin_init', array( $this, 'register' ) );
	}

	/**
	 * Add the Settings submenu under the Live Chat menu.
	 */
	public function menu() {
		add_submenu_page(
			self::PARENT,
			__( 'Settings', 'bg-live-chat-free' ),
			__( 'Settings', 'bg-live-chat-free' ),
			'manage_options',
			'bglc-settings',
			array( $this, 'render_page' )
		);
	}

	/**
	 * The stored option names.
	 *
	 * @return string[]
	 */
	protected function option_names() {
		return array(
			'bglc_baseurl', 'bglc_defaultmodel', 'bglc_maxtokens', 'bglc_apikey',
			'bglc_bot_enabled', 'bglc_bot_prompt', 'bglc_headline', 'bglc_greeting',
			'bglc_prechat', 'bglc_tab_chat', 'bglc_floating',
			'bglc_color', 'bglc_position',
		);
	}

	/**
	 * Register each setting with the Settings API.
	 */
	public function register() {
		register_setting( 'bglc', 'bglc_baseurl', array( 'sanitize_callback' => 'esc_url_raw' ) );
		register_setting( 'bglc', 'bglc_defaultmodel', array( 'sanitize_callback' => 'sanitize_text_field' ) );
		register_setting( 'bglc', 'bglc_maxtokens', array( 'sanitize_callback' => 'absint' ) );
		register_setting( 'bglc', 'bglc_apikey', array( 'sanitize_callback' => 'sanitize_text_field' ) );
		register_setting( 'bglc', 'bglc_bot_prompt', array( 'sanitize_callback' => 'sanitize_textarea_field' ) );
		register_setting( 'bglc', 'bglc_headline', array( 'sanitize_callback' => 'sanitize_text_field' ) );
		register_setting( 'bglc', 'bglc_greeting', array( 'sanitize_callback' => 'sanitize_textarea_field' ) );
		register_setting( 'bglc', 'bglc_color', array( 'sanitize_callback' => array( $this, 'sanitize_color' ) ) );
		register_setting( 'bglc', 'bglc_position', array( 'sanitize_callback' => array( $this, 'sanitize_position' ) ) );

		foreach ( array( 'bglc_bot_enabled', 'bglc_prechat', 'bglc_tab_chat', 'bglc_floating' ) as $flag ) {
			register_setting( 'bglc', $flag, array( 'sanitize_callback' => 'rest_sanitize_boolean' ) );
		}
	}

	/**
	 * Keep a #rrggbb colour or fall back to the default.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	public function sanitize_color( $value ) {
		$clean = sanitize_hex_color( is_string( $value ) ? $value : '' );
		return $clean ? $clean : '#2271b1';
	}

	/**
	 * Only allow left or right placement.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	public function sanitize_position( $value ) {
		return 'left' === $value ? 'left' : 'right';
	}

	/**
	 * Render the settings screen.
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$quota  = BGLC_Pool::quota();
		$models = BGLC_Pool::models();
		?>
		<div class="wrap bglc-settings">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<p><?php esc_html_e( 'A free live chat with an AI chatbot. Leave the key blank to run on the shared free token pool, or add your own to route directly.', 'bg-live-chat-free' ); ?></p>
			<?php if ( $quota ) : ?>
				<p><strong><?php esc_html_e( 'Free allowance:', 'bg-live-chat-free' ); ?></strong> <?php echo esc_html( number_format_i18n( $quota['left'] ) ); ?> <?php esc_html_e( 'tokens left on this admin device this month.', 'bg-live-chat-free' ); ?></p>
			<?php endif; ?>
			<form method="post" action="options.php">
				<?php settings_fields( 'bglc' ); ?>

				<h2><?php esc_html_e( 'Widget', 'bg-live-chat-free' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="bglc_headline"><?php esc_html_e( 'Widget headline', 'bg-live-chat-free' ); ?></label></th>
						<td><input name="bglc_headline" id="bglc_headline" type="text" class="regular-text" value="<?php echo esc_attr( get_option( 'bglc_headline', '' ) ); ?>" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="bglc_greeting"><?php esc_html_e( 'Greeting message', 'bg-live-chat-free' ); ?></label></th>
						<td><textarea name="bglc_greeting" id="bglc_greeting" rows="2" class="large-text"><?php echo esc_textarea( get_option( 'bglc_greeting', '' ) ); ?></textarea></td>
					</tr>
					<tr>
						<th scope="row"><label for="bglc_color"><?php esc_html_e( 'Accent colour', 'bg-live-chat-free' ); ?></label></th>
						<td><input name="bglc_color" id="bglc_color" type="text" class="small-text" value="<?php echo esc_attr( get_option( 'bglc_color', '#2271b1' ) ); ?>" /> <span class="bglc-swatch" style="background:<?php echo esc_attr( get_option( 'bglc_color', '#2271b1' ) ); ?>"></span></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Launcher side', 'bg-live-chat-free' ); ?></th>
						<td>
							<label><input type="radio" name="bglc_position" value="right" <?php checked( 'right', get_option( 'bglc_position', 'right' ) ); ?> /> <?php esc_html_e( 'Bottom right', 'bg-live-chat-free' ); ?></label>
							<label><input type="radio" name="bglc_position" value="left" <?php checked( 'left', get_option( 'bglc_position', 'right' ) ); ?> /> <?php esc_html_e( 'Bottom left', 'bg-live-chat-free' ); ?></label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Floating launcher', 'bg-live-chat-free' ); ?></th>
						<td>
							<label><input type="checkbox" name="bglc_floating" value="1" <?php checked( (bool) get_option( 'bglc_floating', true ) ); ?> /> <?php esc_html_e( 'Show the launcher on every page', 'bg-live-chat-free' ); ?></label>
							<p class="description"><?php esc_html_e( 'You can also embed the widget with the shortcode [bg_live_chat].', 'bg-live-chat-free' ); ?></p>
						</td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'AI Chatbot', 'bg-live-chat-free' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Enable AI replies', 'bg-live-chat-free' ); ?></th>
						<td>
							<label><input type="checkbox" name="bglc_bot_enabled" value="1" <?php checked( (bool) get_option( 'bglc_bot_enabled', true ) ); ?> /> <?php esc_html_e( 'Let the AI answer visitors automatically until a human takes over', 'bg-live-chat-free' ); ?></label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Pre-chat survey', 'bg-live-chat-free' ); ?></th>
						<td>
							<label><input type="checkbox" name="bglc_prechat" value="1" <?php checked( (bool) get_option( 'bglc_prechat', false ) ); ?> /> <?php esc_html_e( 'Ask for name and email before chatting', 'bg-live-chat-free' ); ?></label>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="bglc_bot_prompt"><?php esc_html_e( 'Assistant system prompt', 'bg-live-chat-free' ); ?></label></th>
						<td><textarea name="bglc_bot_prompt" id="bglc_bot_prompt" rows="4" class="large-text"><?php echo esc_textarea( get_option( 'bglc_bot_prompt', '' ) ); ?></textarea></td>
					</tr>
					<tr>
						<th scope="row"><label for="bglc_defaultmodel"><?php esc_html_e( 'Default model', 'bg-live-chat-free' ); ?></label></th>
						<td>
							<select name="bglc_defaultmodel" id="bglc_defaultmodel">
								<option value=""><?php esc_html_e( 'First available', 'bg-live-chat-free' ); ?></option>
								<?php $dm = get_option( 'bglc_defaultmodel', '' ); ?>
								<?php foreach ( $models as $m ) : ?>
									<option value="<?php echo esc_attr( $m['id'] ); ?>" <?php selected( $dm, $m['id'] ); ?>><?php echo esc_html( $m['name'] ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="bglc_maxtokens"><?php esc_html_e( 'Max tokens per reply', 'bg-live-chat-free' ); ?></label></th>
						<td><input name="bglc_maxtokens" id="bglc_maxtokens" type="number" min="1" step="1" value="<?php echo esc_attr( (int) get_option( 'bglc_maxtokens', 512 ) ); ?>" /></td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Free pool & keys', 'bg-live-chat-free' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="bglc_baseurl"><?php esc_html_e( 'Free pool base URL', 'bg-live-chat-free' ); ?></label></th>
						<td><input name="bglc_baseurl" id="bglc_baseurl" type="url" class="regular-text" value="<?php echo esc_attr( get_option( 'bglc_baseurl', 'https://api.usefree.space/v1' ) ); ?>" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="bglc_apikey"><?php esc_html_e( 'Bring-your-own API key (optional)', 'bg-live-chat-free' ); ?></label></th>
						<td>
							<input name="bglc_apikey" id="bglc_apikey" type="password" class="regular-text" value="<?php echo esc_attr( get_option( 'bglc_apikey', '' ) ); ?>" autocomplete="off" />
							<p class="description"><?php esc_html_e( 'Blank uses the shared free allowance. A key routes requests directly and skips the free quota.', 'bg-live-chat-free' ); ?></p>
						</td>
					</tr>
				</table>

				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}
}
