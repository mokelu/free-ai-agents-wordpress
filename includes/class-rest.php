<?php
/**
 * REST endpoints powering the front-end widget and the admin inbox.
 *
 * @package BGLiveChat
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registers /wp-json/bglc/v1 routes used by public/widget.js and the inbox.
 */
class BGLC_REST {

	const NAMESPACE = 'bglc/v1';

	/** @var BGLC_REST|null */
	protected static $instance = null;

	/**
	 * Singleton accessor.
	 *
	 * @return BGLC_REST
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Hook the routes.
	 */
	public function register_hooks() {
		add_action( 'rest_api_init', array( $this, 'routes' ) );
	}

	/**
	 * All routes.
	 */
	public function routes() {
		// Public widget routes.
		register_rest_route(
			self::NAMESPACE,
			'/config',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'route_config' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/models',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'route_models' ),
				'permission_callback' => array( $this, 'permission_public' ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/history',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'route_history' ),
				'permission_callback' => array( $this, 'permission_public' ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/chat',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'route_chat' ),
				'permission_callback' => array( $this, 'permission_public' ),
			)
		);

		// Admin inbox routes.
		register_rest_route(
			self::NAMESPACE,
			'/threads',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'route_threads' ),
				'permission_callback' => array( $this, 'permission_admin' ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/messages',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'route_admin_messages' ),
				'permission_callback' => array( $this, 'permission_admin' ),
				'args'                => array(
					'thread' => array(
						'type'              => 'integer',
						'required'          => true,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/reply',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'route_reply' ),
				'permission_callback' => array( $this, 'permission_admin' ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/status',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'route_status' ),
				'permission_callback' => array( $this, 'permission_admin' ),
			)
		);
	}

	/**
	 * Guests and logged-in users alike may use the free widget, guarded by our nonce.
	 *
	 * @param WP_REST_Request $request Incoming request.
	 * @return bool|WP_Error
	 */
	public function permission_public( $request ) {
		$nonce = $request->get_header( 'X-BGLC-Nonce' );
		if ( $nonce && wp_verify_nonce( $nonce, 'bglc' ) ) {
			return true;
		}
		if ( is_user_logged_in() ) {
			return current_user_can( 'read' );
		}
		return new WP_Error(
			'bglc_forbidden',
			__( 'Your session expired. Please reload the page.', 'bg-live-chat-free' ),
			array( 'status' => 403 )
		);
	}

	/**
	 * Inbox routes require a manager plus a valid nonce.
	 *
	 * @param WP_REST_Request $request Incoming request.
	 * @return bool|WP_Error
	 */
	public function permission_admin( $request ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error( 'bglc_forbidden', __( 'Not allowed.', 'bg-live-chat-free' ), array( 'status' => 403 ) );
		}
		$nonce = $request->get_header( 'X-BGLC-Nonce' );
		if ( $nonce && wp_verify_nonce( $nonce, 'bglc' ) ) {
			return true;
		}
		return new WP_Error( 'bglc_forbidden', __( 'Session expired. Reload the page.', 'bg-live-chat-free' ), array( 'status' => 403 ) );
	}

	/**
	 * GET /config — widget presentation without any secret.
	 *
	 * @return WP_REST_Response
	 */
	public function route_config() {
		return rest_ensure_response(
			array(
				'bot'       => (bool) get_option( 'bglc_bot_enabled', true ),
				'prechat'   => (bool) get_option( 'bglc_prechat', false ),
				'headline'  => get_option( 'bglc_headline', '' ),
				'greeting'  => get_option( 'bglc_greeting', '' ),
				'color'     => get_option( 'bglc_color', '#2271b1' ),
				'position'  => get_option( 'bglc_position', 'right' ),
			)
		);
	}

	/**
	 * GET /models
	 *
	 * @return WP_REST_Response
	 */
	public function route_models() {
		$models  = BGLC_Pool::models();
		$default = get_option( 'bglc_defaultmodel', '' );
		if ( '' === $default && $models ) {
			$default = $models[0]['id'];
		}
		return rest_ensure_response(
			array(
				'models'  => $models,
				'default' => $default,
			)
		);
	}

	/**
	 * GET /history?thread=ID — resume a visitor's conversation.
	 *
	 * @param WP_REST_Request $request Incoming request.
	 * @return WP_REST_Response
	 */
	public function route_history( $request ) {
		$thread = absint( $request->get_param( 'thread' ) );
		if ( $thread <= 0 ) {
			return rest_ensure_response( array( 'thread' => 0, 'messages' => array() ) );
		}
		return rest_ensure_response(
			array(
				'thread'   => $thread,
				'messages' => BGLC_Store::thread_messages( $thread ),
			)
		);
	}

	/**
	 * POST /chat — the main visitor turn.
	 *
	 * @param WP_REST_Request $request Incoming request.
	 * @return WP_REST_Response
	 */
	public function route_chat( $request ) {
		$model   = sanitize_text_field( (string) $request->get_param( 'model' ) );
		$message = trim( (string) $request->get_param( 'message' ) );
		$thread  = absint( $request->get_param( 'thread' ) );
		$name    = sanitize_text_field( (string) $request->get_param( 'name' ) );
		$email   = sanitize_email( (string) $request->get_param( 'email' ) );

		if ( '' === $message ) {
			return rest_ensure_response( array( 'reply' => '', 'error' => '', 'thread' => $thread, 'status' => 'bot' ) );
		}

		if ( '' === $model ) {
			$models = BGLC_Pool::models();
			$model  = $models ? $models[0]['id'] : '';
		}

		$thread = BGLC_Store::resolve_thread( $thread, $model );
		if ( '' !== $name || '' !== $email ) {
			BGLC_Store::set_identity( $thread, $name, $email );
		}
		BGLC_Store::add_message( $thread, 'visitor', $message );

		$row         = BGLC_Store::get_thread( $thread );
		$status      = $row ? $row['status'] : 'bot';
		$bot_enabled = (bool) get_option( 'bglc_bot_enabled', true ) && 'bot' === $status;

		if ( ! $bot_enabled ) {
			// A human will pick it up; nothing is lost.
			return rest_ensure_response(
				array(
					'reply'   => '',
					'error'   => '',
					'thread'  => $thread,
					'status'  => $status,
					'pending' => true,
				)
			);
		}

		$system = trim( (string) get_option( 'bglc_bot_prompt', '' ) );
		if ( '' === $system ) {
			$system = __( 'You are a friendly website support assistant. Answer clearly and concisely.', 'bg-live-chat-free' );
		}

		$messages = array_merge(
			array( array( 'role' => 'system', 'content' => $system ) ),
			BGLC_Store::bot_context( $thread )
		);

		$result = BGLC_Pool::complete( $model, $messages );

		if ( '' !== $result['error'] ) {
			return rest_ensure_response(
				array(
					'reply'   => '',
					'error'   => $result['error'],
					'thread'  => $thread,
					'status'  => $status,
					'pending' => true,
				)
			);
		}

		BGLC_Store::add_message( $thread, 'bot', $result['reply'] );

		return rest_ensure_response(
			array(
				'reply'   => $result['reply'],
				'error'   => '',
				'thread'  => $thread,
				'status'  => $status,
				'pending' => false,
			)
		);
	}

	/**
	 * GET /threads — admin inbox list.
	 *
	 * @param WP_REST_Request $request Incoming request.
	 * @return WP_REST_Response
	 */
	public function route_threads( $request ) {
		$status = sanitize_key( (string) $request->get_param( 'status' ) );
		return rest_ensure_response( array( 'threads' => BGLC_Store::list_threads( $status ? $status : 'all' ) ) );
	}

	/**
	 * GET /messages?thread=ID — admin thread transcript.
	 *
	 * @param WP_REST_Request $request Incoming request.
	 * @return WP_REST_Response
	 */
	public function route_admin_messages( $request ) {
		$thread = absint( $request->get_param( 'thread' ) );
		return rest_ensure_response(
			array(
				'thread'   => BGLC_Store::get_thread( $thread ),
				'messages' => BGLC_Store::all_messages( $thread ),
			)
		);
	}

	/**
	 * POST /reply — a human agent answers a thread.
	 *
	 * @param WP_REST_Request $request Incoming request.
	 * @return WP_REST_Response
	 */
	public function route_reply( $request ) {
		$thread  = absint( $request->get_param( 'thread' ) );
		$message = trim( (string) $request->get_param( 'message' ) );
		if ( $thread <= 0 || '' === $message ) {
			return rest_ensure_response( array( 'sent' => false ) );
		}
		BGLC_Store::add_message( $thread, 'agent', $message );
		return rest_ensure_response( array( 'sent' => true ) );
	}

	/**
	 * POST /status — take over, hand back to bot, or close a thread.
	 *
	 * @param WP_REST_Request $request Incoming request.
	 * @return WP_REST_Response
	 */
	public function route_status( $request ) {
		$thread = absint( $request->get_param( 'thread' ) );
		$status = sanitize_key( (string) $request->get_param( 'status' ) );
		$ok     = BGLC_Store::set_status( $thread, $status );
		return rest_ensure_response( array( 'updated' => $ok, 'status' => $status ) );
	}
}
