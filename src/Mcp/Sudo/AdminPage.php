<?php
/**
 * wp-admin page that exposes the sudo token lifecycle.
 *
 * @package Talaxie\Core
 */

declare(strict_types=1);

namespace Talaxie\Core\Mcp\Sudo;

defined( 'ABSPATH' ) || exit;

/**
 * Renders Tools > MCP Sudo with three actions: create, revoke one, revoke all.
 *
 * The cleartext token is shown exactly once on the redirect that follows
 * its creation. We stash it in a per-user transient (60 s TTL) so a
 * refresh won't leak it again.
 */
final class AdminPage {

	public const SLUG          = 'talaxie-mcp-sudo';
	public const PARENT        = 'tools.php';
	public const NONCE         = 'talaxie_mcp_sudo';
	public const TRANSIENT_KEY = 'talaxie_mcp_sudo_just_created_';

	/**
	 * Hook the page into wp-admin.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'admin_menu', array( self::class, 'add_menu' ) );
		add_action( 'admin_post_talaxie_mcp_sudo_create', array( self::class, 'handle_create' ) );
		add_action( 'admin_post_talaxie_mcp_sudo_revoke', array( self::class, 'handle_revoke' ) );
		add_action( 'admin_post_talaxie_mcp_sudo_revoke_all', array( self::class, 'handle_revoke_all' ) );
	}

	/**
	 * Add the Tools menu entry.
	 *
	 * @return void
	 */
	public static function add_menu(): void {
		add_submenu_page(
			self::PARENT,
			__( 'MCP Sudo', 'talaxie-core' ),
			__( 'MCP Sudo', 'talaxie-core' ),
			'manage_options',
			self::SLUG,
			array( self::class, 'render' )
		);
	}

	/**
	 * Render the page.
	 *
	 * @return void
	 */
	public static function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to manage MCP sudo tokens.', 'talaxie-core' ) );
		}

		$tokens   = TokenManager::list_active();
		$display  = self::pop_just_created();
		$post_url = admin_url( 'admin-post.php' );

		echo '<div class="wrap">';
		printf( '<h1>%s</h1>', esc_html__( 'MCP Sudo', 'talaxie-core' ) );

		if ( null !== $display ) {
			echo '<div class="notice notice-success"><p>';
			echo '<strong>' . esc_html__( 'Token created.', 'talaxie-core' ) . '</strong> ';
			esc_html_e( 'Copy it now — it will not be shown again.', 'talaxie-core' );
			echo '</p><pre style="background:#f6f7f7;padding:1em;border:1px solid #c3c4c7;font-family:monospace;">' . esc_html( $display['token'] ) . '</pre>';
			printf( '<p><em>%s %s</em></p>', esc_html__( 'Expires (UTC):', 'talaxie-core' ), esc_html( $display['expires_at'] ) );
			echo '</div>';
		}

		echo '<h2>' . esc_html__( 'Create a sudo token', 'talaxie-core' ) . '</h2>';
		echo '<form method="post" action="' . esc_url( $post_url ) . '" style="background:#fff;padding:1em;border:1px solid #c3c4c7;max-width:640px;">';
		wp_nonce_field( self::NONCE );
		echo '<input type="hidden" name="action" value="talaxie_mcp_sudo_create" />';

		echo '<table class="form-table" role="presentation"><tbody>';

		echo '<tr><th><label for="tlx_scope">' . esc_html__( 'Scope', 'talaxie-core' ) . '</label></th><td>';
		echo '<input type="text" id="tlx_scope" name="scope" class="regular-text" placeholder="manage_options,delete_users" required />';
		echo '<p class="description">' . esc_html__( 'Comma-separated capability list. The token will only validate for these capabilities.', 'talaxie-core' ) . '</p>';
		echo '</td></tr>';

		echo '<tr><th><label for="tlx_ttl">' . esc_html__( 'TTL (minutes)', 'talaxie-core' ) . '</label></th><td>';
		printf(
			'<input type="number" id="tlx_ttl" name="ttl_minutes" value="%d" min="1" max="%d" class="small-text" />',
			15,
			(int) ceil( TokenManager::HARD_MAX_TTL / MINUTE_IN_SECONDS )
		);
		echo '</td></tr>';

		echo '<tr><th>' . esc_html__( 'Single-use', 'talaxie-core' ) . '</th><td>';
		echo '<label><input type="checkbox" name="single_use" value="1" /> ';
		esc_html_e( 'Invalidate after the first successful validation.', 'talaxie-core' );
		echo '</label></td></tr>';

		echo '</tbody></table>';
		submit_button( __( 'Generate token', 'talaxie-core' ) );
		echo '</form>';

		echo '<h2 style="margin-top:2em;">' . esc_html__( 'Active tokens', 'talaxie-core' ) . '</h2>';

		if ( empty( $tokens ) ) {
			echo '<p>' . esc_html__( 'No active sudo tokens.', 'talaxie-core' ) . '</p>';
		} else {
			echo '<table class="widefat striped"><thead><tr>';
			printf( '<th>%s</th>', esc_html__( 'ID', 'talaxie-core' ) );
			printf( '<th>%s</th>', esc_html__( 'Scope', 'talaxie-core' ) );
			printf( '<th>%s</th>', esc_html__( 'Expires (UTC)', 'talaxie-core' ) );
			printf( '<th>%s</th>', esc_html__( 'Single-use', 'talaxie-core' ) );
			printf( '<th>%s</th>', esc_html__( 'Uses', 'talaxie-core' ) );
			printf( '<th>%s</th>', esc_html__( 'Created by', 'talaxie-core' ) );
			printf( '<th>%s</th>', esc_html__( 'Actions', 'talaxie-core' ) );
			echo '</tr></thead><tbody>';

			foreach ( $tokens as $row ) {
				$user = get_userdata( $row['created_by_user_id'] );
				echo '<tr>';
				printf( '<td>%d</td>', (int) $row['id'] );
				printf( '<td><code>%s</code></td>', esc_html( implode( ',', $row['scope'] ) ) );
				printf( '<td>%s</td>', esc_html( $row['expires_at'] ) );
				printf( '<td>%s</td>', $row['single_use'] ? esc_html__( 'yes', 'talaxie-core' ) : esc_html__( 'no', 'talaxie-core' ) );
				printf( '<td>%d</td>', (int) $row['usage_count'] );
				printf( '<td>%s</td>', esc_html( $user instanceof \WP_User ? $user->user_login : '?' ) );
				echo '<td>';
				echo '<form method="post" action="' . esc_url( $post_url ) . '" style="display:inline;">';
				wp_nonce_field( self::NONCE );
				echo '<input type="hidden" name="action" value="talaxie_mcp_sudo_revoke" />';
				printf( '<input type="hidden" name="token_id" value="%d" />', (int) $row['id'] );
				submit_button( __( 'Revoke', 'talaxie-core' ), 'small', 'submit', false );
				echo '</form>';
				echo '</td></tr>';
			}
			echo '</tbody></table>';

			echo '<form method="post" action="' . esc_url( $post_url ) . '" style="margin-top:1em;">';
			wp_nonce_field( self::NONCE );
			echo '<input type="hidden" name="action" value="talaxie_mcp_sudo_revoke_all" />';
			submit_button( __( 'Revoke all active tokens', 'talaxie-core' ), 'delete' );
			echo '</form>';
		}

		echo '</div>';
	}

	/**
	 * POST handler — create.
	 *
	 * @return void
	 */
	public static function handle_create(): void {
		self::guard();

		$scope_raw = isset( $_POST['scope'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['scope'] ) ) : '';
		$scope     = array_values( array_filter( array_map( 'trim', explode( ',', $scope_raw ) ), 'strlen' ) );
		$ttl_min   = isset( $_POST['ttl_minutes'] ) ? max( 1, (int) $_POST['ttl_minutes'] ) : 15;
		$single    = ! empty( $_POST['single_use'] );

		$result = TokenManager::create( $scope, $ttl_min * MINUTE_IN_SECONDS, $single );
		if ( $result instanceof \WP_Error ) {
			wp_die( esc_html( $result->get_error_message() ) );
		}

		set_transient(
			self::TRANSIENT_KEY . get_current_user_id(),
			array(
				'token'      => $result['token'],
				'expires_at' => $result['expires_at'],
			),
			MINUTE_IN_SECONDS
		);

		wp_safe_redirect( self::page_url() );
		exit;
	}

	/**
	 * POST handler — revoke single.
	 *
	 * @return void
	 */
	public static function handle_revoke(): void {
		self::guard();
		$id = isset( $_POST['token_id'] ) ? (int) $_POST['token_id'] : 0;
		TokenManager::revoke( $id );
		wp_safe_redirect( self::page_url() );
		exit;
	}

	/**
	 * POST handler — revoke all.
	 *
	 * @return void
	 */
	public static function handle_revoke_all(): void {
		self::guard();
		TokenManager::revoke_all();
		wp_safe_redirect( self::page_url() );
		exit;
	}

	/**
	 * Reject unauthenticated/missing-nonce requests.
	 *
	 * @return void
	 */
	private static function guard(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to manage MCP sudo tokens.', 'talaxie-core' ) );
		}
		check_admin_referer( self::NONCE );
	}

	/**
	 * Pop the just-created cleartext token (one read only).
	 *
	 * @return array{token:string, expires_at:string}|null
	 */
	private static function pop_just_created(): ?array {
		$key   = self::TRANSIENT_KEY . get_current_user_id();
		$value = get_transient( $key );
		if ( ! is_array( $value ) || empty( $value['token'] ) ) {
			return null;
		}
		delete_transient( $key );
		return array(
			'token'      => (string) $value['token'],
			'expires_at' => (string) ( $value['expires_at'] ?? '' ),
		);
	}

	/**
	 * URL of the admin page (used by redirects).
	 *
	 * @return string
	 */
	private static function page_url(): string {
		return add_query_arg( 'page', self::SLUG, admin_url( self::PARENT ) );
	}
}
