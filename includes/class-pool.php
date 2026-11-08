<?php
/**
 * Server-side client for the free AI pool (OpenAI-compatible), via the HTTP API.
 *
 * @package BGLiveChat
 */

defined( 'ABSPATH' ) || exit;

/**
 * Talks to the free token pool so the browser never sees the request or any key.
 */
class BGLC_Pool {

	const MODELS_TRANSIENT = 'bglc_models';

	/**
	 * A stable, salted visitor id so the pool can track the free allowance and
	 * so the inbox can group a visitor's conversations.
	 *
	 * Uses the user id for logged-in users and a salted IP hash for guests.
	 *
	 * @return string
	 */
	public static function visitor_key() {
		$salt = wp_salt( 'auth' );

		if ( is_user_logged_in() ) {
			$who = 'u' . get_current_user_id();
		} else {
			$ip  = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'anon';
			$who = 'g' . hash_hmac( 'sha256', $ip, $salt );
		}

		return 'wp-' . substr( hash_hmac( 'sha256', $who, $salt ), 0, 40 );
	}

	/**
	 * Backwards-compatible alias used by the pool headers.
	 *
	 * @return string
	 */
	public static function device_id() {
		return self::visitor_key();
	}

	/**
	 * The configured pool base URL without a trailing slash.
	 *
	 * @return string
	 */
	protected static function base() {
		$url = get_option( 'bglc_baseurl', 'https://api.usefree.space/v1' );
		return rtrim( $url, '/' );
	}

	/**
	 * Optional bring-your-own key. Blank means use the shared free allowance.
	 *
	 * @return string
	 */
	protected static function apikey() {
		return trim( (string) get_option( 'bglc_apikey', '' ) );
	}

	/**
	 * Fetch and cache the model catalogue.
	 *
	 * @return array List of [ 'id' => string, 'name' => string ].
	 */
	public static function models() {
		$cached = get_transient( self::MODELS_TRANSIENT );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$args = array(
			'timeout' => 15,
			'headers' => array( 'X-Device-Id' => self::visitor_key() ),
		);
		$key  = self::apikey();
		if ( '' !== $key ) {
			$args['headers']['Authorization'] = 'Bearer ' . $key;
		}

		$res = wp_remote_get( self::base() . '/models', $args );
		if ( is_wp_error( $res ) ) {
			return array();
		}

		$body = json_decode( wp_remote_retrieve_body( $res ), true );
		$list = array();
		foreach ( (array) ( $body['data'] ?? array() ) as $m ) {
			if ( ! empty( $m['id'] ) ) {
				$list[] = array(
					'id'   => $m['id'],
					'name' => $m['name'] ?? $m['id'],
				);
			}
		}

		if ( $list ) {
			set_transient( self::MODELS_TRANSIENT, $list, HOUR_IN_SECONDS );
		}
		return $list;
	}

	/**
	 * Send a chat completion and return the assistant text.
	 *
	 * @param string $model    Model id.
	 * @param array  $messages OpenAI-style message list.
	 * @return array { reply: string, error: string }
	 */
	public static function complete( $model, $messages ) {
		$max = (int) get_option( 'bglc_maxtokens', 512 );

		$body = array(
			'model'      => $model,
			'messages'   => $messages,
			'max_tokens' => $max > 0 ? $max : 512,
			'stream'     => false,
		);

		$headers = array(
			'Content-Type' => 'application/json',
			'X-Device-Id'  => self::visitor_key(),
		);
		$key     = self::apikey();
		if ( '' !== $key ) {
			$headers['Authorization'] = 'Bearer ' . $key;
		}

		$res = wp_remote_post(
			self::base() . '/chat/completions',
			array(
				'timeout' => 60,
				'headers' => $headers,
				'body'    => wp_json_encode( $body ),
			)
		);

		if ( is_wp_error( $res ) ) {
			return array(
				'reply' => '',
				'error' => __( 'The free AI pool could not be reached. Please try again shortly.', 'bg-live-chat-free' ),
			);
		}

		$json = json_decode( wp_remote_retrieve_body( $res ), true );
		$code = wp_remote_retrieve_response_code( $res );

		if ( 429 === $code || ( isset( $json['error']['code'] ) && 'device_exhausted' === $json['error']['code'] ) ) {
			return array(
				'reply' => '',
				'error' => __( 'The free AI tokens are used up right now, but your message has been saved and a human can reply. Get many more free tokens at usefree.space — no account, no signup.', 'bg-live-chat-free' ),
			);
		}

		if ( ! empty( $json['error'] ) ) {
			return array(
				'reply' => '',
				'error' => __( 'The free AI pool could not be reached. Please try again shortly.', 'bg-live-chat-free' ),
			);
		}

		return array(
			'reply' => $json['choices'][0]['message']['content'] ?? '',
			'error' => '',
		);
	}

	/**
	 * Query the visitor's remaining free tokens, if the pool exposes it.
	 *
	 * @return array|false { left: int, reset: string } or false when unavailable.
	 */
	public static function quota() {
		$args = array(
			'timeout' => 15,
			'headers' => array( 'X-Device-Id' => self::visitor_key() ),
		);
		$res  = wp_remote_get( self::base() . '/quota/status', $args );
		if ( is_wp_error( $res ) ) {
			return false;
		}
		$json = json_decode( wp_remote_retrieve_body( $res ), true );
		if ( ! is_array( $json ) || ! isset( $json['device_tokens_left'] ) ) {
			return false;
		}
		return array(
			'left'  => (int) $json['device_tokens_left'],
			'reset' => $json['reset_date'] ?? $json['reset'] ?? '',
		);
	}
}
