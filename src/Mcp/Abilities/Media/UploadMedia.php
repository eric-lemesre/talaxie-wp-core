<?php
/**
 * Upload a new media item.
 *
 * @package Talaxie\Core
 */

declare(strict_types=1);

namespace Talaxie\Core\Mcp\Abilities\Media;

use Talaxie\Core\Mcp\Abilities\AbilityInterface;
use Talaxie\Core\Mcp\CapabilityGate;

defined( 'ABSPATH' ) || exit;

/**
 * Uploads an attachment from a base64-encoded payload.
 *
 * The MCP transport is JSON-RPC, so binary uploads must be encoded.
 * Capability: upload_files (granted to ai_bot).
 */
final class UploadMedia implements AbilityInterface {

	public const ABILITY            = 'talaxie-core/media-upload';
	private const MAX_DECODED_BYTES = 16 * 1024 * 1024;

	public static function name(): string {
		return self::ABILITY;
	}

	public static function is_allowed_on_production(): bool {
		return true;
	}

	public static function register(): void {
		wp_register_ability(
			self::ABILITY,
			array(
				'label'               => __( 'Upload media', 'talaxie-core' ),
				'description'         => __( 'Create an attachment from a base64-encoded payload. Capability: upload_files.', 'talaxie-core' ),
				'category'            => 'talaxie-core',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'filename'    => array( 'type' => 'string' ),
						'data_base64' => array( 'type' => 'string' ),
						'title'       => array( 'type' => 'string' ),
						'alt_text'    => array( 'type' => 'string' ),
						'_sudo'       => array( 'type' => 'string' ),
					),
					'required'             => array( 'filename', 'data_base64' ),
					'additionalProperties' => false,
				),
				'permission_callback' => static function ( $input ) {
					return CapabilityGate::check(
						self::ABILITY,
						'upload_files',
						is_array( $input ) ? $input : array()
					);
				},
				'execute_callback'    => static function ( $input ) {
					$input    = is_array( $input ) ? $input : array();
					$filename = sanitize_file_name( (string) ( $input['filename'] ?? '' ) );
					if ( '' === $filename ) {
						return new \WP_Error( 'talaxie_media_invalid_filename', __( 'A filename is required.', 'talaxie-core' ), array( 'status' => 400 ) );
					}

					$decoded = base64_decode( (string) ( $input['data_base64'] ?? '' ), true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- MCP transports are JSON, binary uploads must be base64-encoded.
					if ( false === $decoded ) {
						return new \WP_Error( 'talaxie_media_invalid_payload', __( 'data_base64 is not valid base64.', 'talaxie-core' ), array( 'status' => 400 ) );
					}
					if ( strlen( $decoded ) > self::MAX_DECODED_BYTES ) {
						return new \WP_Error( 'talaxie_media_payload_too_large', __( 'Payload exceeds the 16 MiB limit.', 'talaxie-core' ), array( 'status' => 413 ) );
					}

					$check = wp_check_filetype( $filename );
					if ( empty( $check['type'] ) ) {
						return new \WP_Error( 'talaxie_media_unsupported_type', __( 'This file type is not allowed by WordPress.', 'talaxie-core' ), array( 'status' => 415 ) );
					}

					$uploads = wp_upload_dir();
					if ( ! empty( $uploads['error'] ) ) {
						return new \WP_Error( 'talaxie_media_upload_dir_error', (string) $uploads['error'], array( 'status' => 500 ) );
					}

					$target_path = wp_unique_filename( $uploads['path'], $filename );
					$target_full = trailingslashit( $uploads['path'] ) . $target_path;
					if ( false === file_put_contents( $target_full, $decoded ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
						return new \WP_Error( 'talaxie_media_write_failed', __( 'Could not write the upload to disk.', 'talaxie-core' ), array( 'status' => 500 ) );
					}

					$attachment_id = wp_insert_attachment(
						array(
							'guid'           => trailingslashit( $uploads['url'] ) . $target_path,
							'post_mime_type' => $check['type'],
							'post_title'     => isset( $input['title'] ) ? wp_strip_all_tags( (string) $input['title'] ) : pathinfo( $target_path, PATHINFO_FILENAME ),
							'post_content'   => '',
							'post_status'    => 'inherit',
						),
						$target_full,
						0,
						true
					);
					if ( is_wp_error( $attachment_id ) ) {
						return $attachment_id;
					}

					require_once ABSPATH . 'wp-admin/includes/image.php';
					$metadata = wp_generate_attachment_metadata( (int) $attachment_id, $target_full );
					wp_update_attachment_metadata( (int) $attachment_id, $metadata );

					if ( isset( $input['alt_text'] ) ) {
						update_post_meta( (int) $attachment_id, '_wp_attachment_image_alt', sanitize_text_field( (string) $input['alt_text'] ) );
					}

					return array(
						'id'        => (int) $attachment_id,
						'mime_type' => $check['type'],
						'url'       => (string) wp_get_attachment_url( (int) $attachment_id ),
						'filename'  => $target_path,
					);
				},
				'meta'                => array(
					'annotations'  => array(
						'readonly'    => false,
						'destructive' => false,
						'idempotent'  => false,
					),
					'show_in_rest' => true,
				),
			)
		);
	}
}
