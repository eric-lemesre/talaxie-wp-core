<?php
/**
 * Persists MCP audit events as posts of the talaxie_mcp_audit CPT.
 *
 * @package Talaxie\Core
 */

declare(strict_types=1);

namespace Talaxie\Core\Mcp\Audit;

defined( 'ABSPATH' ) || exit;

/**
 * Subscribes to the talaxie_mcp_audit action emitted by CapabilityGate
 * and writes one row per event.
 *
 * Inputs are stored as post-meta so the admin UI can sort/filter on
 * indexed fields (capability, ability, allowed) without parsing JSON.
 * The full event payload is also kept for forensic purposes.
 */
final class AuditLogger {

	public const META_ABILITY    = '_talaxie_mcp_ability';
	public const META_CAPABILITY = '_talaxie_mcp_capability';
	public const META_USER       = '_talaxie_mcp_user_id';
	public const META_ALLOWED    = '_talaxie_mcp_allowed';
	public const META_SUDO_USED  = '_talaxie_mcp_sudo_used';
	public const META_IP         = '_talaxie_mcp_ip';
	public const META_RAW        = '_talaxie_mcp_event';

	/**
	 * Hook the logger into WordPress.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'talaxie_mcp_audit', array( self::class, 'log' ) );
	}

	/**
	 * Persist a single audit event.
	 *
	 * @param array<string, mixed> $event Event payload as emitted by CapabilityGate.
	 *
	 * @return int|null Post ID on success, null on failure.
	 */
	public static function log( array $event ): ?int {
		$ability    = isset( $event['ability'] ) ? (string) $event['ability'] : '';
		$capability = isset( $event['capability'] ) ? (string) $event['capability'] : '';
		$user_id    = isset( $event['user_id'] ) ? (int) $event['user_id'] : 0;
		$allowed    = ! empty( $event['allowed'] );
		$sudo_used  = ! empty( $event['sudo_used'] );

		$title = sprintf(
			'%s — %s — %s',
			$allowed ? 'allow' : 'deny',
			'' !== $ability ? $ability : 'unknown',
			'' !== $capability ? $capability : 'unknown'
		);

		$post_id = wp_insert_post(
			array(
				'post_type'   => AuditPostType::POST_TYPE,
				'post_status' => 'publish',
				'post_title'  => $title,
				'post_author' => $user_id,
			),
			true
		);

		if ( is_wp_error( $post_id ) || ! is_int( $post_id ) || $post_id <= 0 ) {
			return null;
		}

		update_post_meta( $post_id, self::META_ABILITY, $ability );
		update_post_meta( $post_id, self::META_CAPABILITY, $capability );
		update_post_meta( $post_id, self::META_USER, $user_id );
		update_post_meta( $post_id, self::META_ALLOWED, $allowed ? '1' : '0' );
		update_post_meta( $post_id, self::META_SUDO_USED, $sudo_used ? '1' : '0' );
		update_post_meta( $post_id, self::META_IP, self::client_ip() );
		update_post_meta( $post_id, self::META_RAW, self::redact_event( $event ) );

		return $post_id;
	}

	/**
	 * Best-effort client IP, sanitized.
	 *
	 * @return string
	 */
	private static function client_ip(): string {
		$raw = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		if ( '' === $raw ) {
			return '';
		}
		$validated = filter_var( $raw, FILTER_VALIDATE_IP );
		return is_string( $validated ) ? $validated : '';
	}

	/**
	 * Redact secrets from the raw event before persisting it.
	 *
	 * @param array<string, mixed> $event Event payload.
	 *
	 * @return array<string, mixed>
	 */
	private static function redact_event( array $event ): array {
		if ( isset( $event['_sudo'] ) ) {
			$event['_sudo'] = '***REDACTED***';
		}
		if ( isset( $event['input'] ) && is_array( $event['input'] ) && isset( $event['input']['_sudo'] ) ) {
			$event['input']['_sudo'] = '***REDACTED***';
		}
		return $event;
	}
}
