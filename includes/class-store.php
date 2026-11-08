<?php
/**
 * Thread and message storage for the live chat, backed by custom tables.
 *
 * @package BGLiveChat
 */

defined( 'ABSPATH' ) || exit;

/**
 * Creates and reads the chat threads and messages for the widget and inbox.
 */
class BGLC_Store {

	/** Messages kept for bot context. */
	const WINDOW = 20;

	/**
	 * Table name for threads.
	 *
	 * @return string
	 */
	public static function thread_table() {
		global $wpdb;
		return $wpdb->prefix . 'bglc_thread';
	}

	/**
	 * Table name for messages.
	 *
	 * @return string
	 */
	public static function message_table() {
		global $wpdb;
		return $wpdb->prefix . 'bglc_message';
	}

	/**
	 * Create or update the tables (dbDelta-safe).
	 */
	public static function install() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset = $wpdb->get_charset_collate();
		$thread  = self::thread_table();
		$msg     = self::message_table();

		dbDelta(
			"CREATE TABLE {$thread} (
				id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
				visitor VARCHAR(64) NOT NULL,
				name VARCHAR(120) NOT NULL DEFAULT '',
				email VARCHAR(190) NOT NULL DEFAULT '',
				status VARCHAR(12) NOT NULL DEFAULT 'bot',
				model VARCHAR(128) NOT NULL DEFAULT '',
				created DATETIME NOT NULL,
				modified DATETIME NOT NULL,
				PRIMARY KEY  (id),
				KEY visitor (visitor),
				KEY status (status)
			) {$charset};"
		);

		dbDelta(
			"CREATE TABLE {$msg} (
				id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
				thread_id BIGINT(20) UNSIGNED NOT NULL,
				role VARCHAR(16) NOT NULL,
				content LONGTEXT NOT NULL,
				created DATETIME NOT NULL,
				PRIMARY KEY  (id),
				KEY thread_id (thread_id)
			) {$charset};"
		);
	}

	/**
	 * Resolve the thread a visitor is working in, creating one when needed.
	 *
	 * A thread id of 0 means "start or reuse the visitor's open thread".
	 *
	 * @param int    $thread_id Requested thread id, or 0.
	 * @param string $model     Model id to remember.
	 * @return int The thread id.
	 */
	public static function resolve_thread( $thread_id, $model = '' ) {
		global $wpdb;
		$thread_id = (int) $thread_id;
		$visitor   = BGLC_Pool::visitor_key();
		$now       = current_time( 'mysql' );

		if ( $thread_id > 0 && self::owned( $thread_id ) ) {
			if ( '' !== $model ) {
				$wpdb->update(
					self::thread_table(),
					array( 'model' => $model, 'modified' => $now ),
					array( 'id' => $thread_id ),
					array( '%s', '%s' ),
					array( '%d' )
				);
			}
			return $thread_id;
		}

		// Reuse the visitor's most recent open thread if it is still fresh.
		$table = self::thread_table();
		$existing = $wpdb->get_var(
			$wpdb->prepare( "SELECT id FROM {$table} WHERE visitor = %s AND status <> 'closed' ORDER BY modified DESC LIMIT 1", $visitor ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);
		if ( $existing ) {
			return (int) $existing;
		}

		$wpdb->insert(
			$table,
			array(
				'visitor'  => $visitor,
				'status'   => (bool) get_option( 'bglc_bot_enabled', true ) ? 'bot' : 'human',
				'model'    => $model,
				'created'  => $now,
				'modified' => $now,
			),
			array( '%s', '%s', '%s', '%s', '%s' )
		);
		return (int) $wpdb->insert_id;
	}

	/**
	 * Store the visitor's name/email captured by the pre-chat survey.
	 *
	 * @param int    $thread_id Thread id.
	 * @param string $name      Visitor name.
	 * @param string $email     Visitor email.
	 */
	public static function set_identity( $thread_id, $name, $email ) {
		global $wpdb;
		$thread_id = (int) $thread_id;
		if ( ! self::owned( $thread_id ) ) {
			return;
		}
		$name  = sanitize_text_field( $name );
		$email = sanitize_email( $email );
		if ( '' === $name && '' === $email ) {
			return;
		}
		$data  = array();
		$format = array();
		if ( '' !== $name ) {
			$data['name'] = $name;
			$format[]      = '%s';
		}
		if ( '' !== $email ) {
			$data['email'] = $email;
			$format[]       = '%s';
		}
		$wpdb->update( self::thread_table(), $data, array( 'id' => $thread_id ), $format, array( '%d' ) );
	}

	/**
	 * Insert a message row and bump the thread timestamp.
	 *
	 * @param int    $thread_id Thread id.
	 * @param string $role      One of visitor|bot|agent.
	 * @param string $content   Message text.
	 * @return int Insert id.
	 */
	public static function add_message( $thread_id, $role, $content ) {
		global $wpdb;
		$thread_id = (int) $thread_id;
		$now       = current_time( 'mysql' );

		$wpdb->insert(
			self::message_table(),
			array(
				'thread_id' => $thread_id,
				'role'      => $role,
				'content'   => $content,
				'created'   => $now,
			),
			array( '%d', '%s', '%s', '%s' )
		);

		$wpdb->update(
			self::thread_table(),
			array( 'modified' => $now ),
			array( 'id' => $thread_id ),
			array( '%s' ),
			array( '%d' )
		);

		return (int) $wpdb->insert_id;
	}

	/**
	 * Raw messages for a thread owned by the current visitor (widget use).
	 *
	 * @param int $thread_id Thread id.
	 * @return array List of [ 'role' => string, 'content' => string, 'created' => string ].
	 */
	public static function thread_messages( $thread_id ) {
		global $wpdb;
		$thread_id = (int) $thread_id;
		if ( ! self::owned( $thread_id ) ) {
			return array();
		}
		$table = self::message_table();
		$rows  = $wpdb->get_results(
			$wpdb->prepare( "SELECT role, content, created FROM {$table} WHERE thread_id = %d ORDER BY id ASC", $thread_id ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			ARRAY_A
		);
		return $rows ? $rows : array();
	}

	/**
	 * Build the OpenAI-style message window for the bot, from thread history.
	 *
	 * @param int $thread_id Thread id.
	 * @return array List of [ 'role' => user|assistant, 'content' => string ].
	 */
	public static function bot_context( $thread_id ) {
		$out = array();
		foreach ( self::thread_messages( $thread_id ) as $m ) {
			$role = 'visitor' === $m['role'] ? 'user' : 'assistant';
			$out[] = array( 'role' => $role, 'content' => $m['content'] );
		}
		if ( count( $out ) > self::WINDOW ) {
			$out = array_slice( $out, -self::WINDOW );
		}
		return $out;
	}

	/**
	 * Threads for the admin inbox.
	 *
	 * @param string $status Filter: all|bot|human|closed.
	 * @param int    $limit  Max rows.
	 * @return array
	 */
	public static function list_threads( $status = 'all', $limit = 100 ) {
		global $wpdb;
		$table = self::thread_table();
		$limit = max( 1, (int) $limit );

		if ( in_array( $status, array( 'bot', 'human', 'closed' ), true ) ) {
			$rows = $wpdb->get_results(
				$wpdb->prepare( "SELECT * FROM {$table} WHERE status = %s ORDER BY modified DESC LIMIT %d", $status, $limit ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				ARRAY_A
			);
		} else {
			$rows = $wpdb->get_results(
				$wpdb->prepare( "SELECT * FROM {$table} ORDER BY modified DESC LIMIT %d", $limit ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				ARRAY_A
			);
		}
		return $rows ? $rows : array();
	}

	/**
	 * A single thread row by id (admin use; no ownership check).
	 *
	 * @param int $thread_id Thread id.
	 * @return array|null
	 */
	public static function get_thread( $thread_id ) {
		global $wpdb;
		$table = self::thread_table();
		$row   = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", (int) $thread_id ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			ARRAY_A
		);
		return $row ? $row : null;
	}

	/**
	 * All messages for a thread (admin use; no ownership check).
	 *
	 * @param int $thread_id Thread id.
	 * @return array
	 */
	public static function all_messages( $thread_id ) {
		global $wpdb;
		$table = self::message_table();
		$rows  = $wpdb->get_results(
			$wpdb->prepare( "SELECT role, content, created FROM {$table} WHERE thread_id = %d ORDER BY id ASC", (int) $thread_id ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			ARRAY_A
		);
		return $rows ? $rows : array();
	}

	/**
	 * Update a thread status (admin use).
	 *
	 * @param int    $thread_id Thread id.
	 * @param string $status    bot|human|closed.
	 * @return bool
	 */
	public static function set_status( $thread_id, $status ) {
		global $wpdb;
		if ( ! in_array( $status, array( 'bot', 'human', 'closed' ), true ) ) {
			return false;
		}
		$affected = $wpdb->update(
			self::thread_table(),
			array( 'status' => $status, 'modified' => current_time( 'mysql' ) ),
			array( 'id' => (int) $thread_id ),
			array( '%s', '%s' ),
			array( '%d' )
		);
		return false !== $affected;
	}

	/**
	 * Whether the current visitor owns the thread.
	 *
	 * @param int $thread_id Thread id.
	 * @return bool
	 */
	protected static function owned( $thread_id ) {
		global $wpdb;
		$thread_id = (int) $thread_id;
		if ( $thread_id <= 0 ) {
			return false;
		}
		$table   = self::thread_table();
		$visitor = BGLC_Pool::visitor_key();
		$found   = $wpdb->get_var(
			$wpdb->prepare( "SELECT id FROM {$table} WHERE id = %d AND visitor = %s", $thread_id, $visitor ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);
		return (bool) $found;
	}

	/**
	 * Threads waiting on a human reply, for the admin menu bubble.
	 *
	 * @return int
	 */
	public static function pending_count() {
		global $wpdb;
		$table = self::thread_table();
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE status = 'human'" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}
}
