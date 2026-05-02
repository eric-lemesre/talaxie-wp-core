<?php
/**
 * @package Talaxie\Core\Tests
 */

declare(strict_types=1);

namespace Talaxie\Core\Tests\Abilities\Media;

use Talaxie\Core\Mcp\Abilities\Media\DeleteMedia;
use Talaxie\Core\Mcp\Abilities\Media\ListMedia;
use Talaxie\Core\Mcp\Abilities\Media\UploadMedia;
use Talaxie\Core\Mcp\Sudo\TokenManager;
use Talaxie\Core\Mcp\Sudo\TokenSchema;
use Talaxie\Core\Roles\AiBotRole;
use WP_UnitTestCase;

final class MediaTest extends WP_UnitTestCase {

	private int $bot_id;
	private int $admin_id;

	public function set_up(): void {
		parent::set_up();
		global $wpdb;
		$wpdb->query( 'TRUNCATE TABLE ' . TokenSchema::table_name() ); // phpcs:ignore

		$this->admin_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		$this->bot_id   = self::factory()->user->create( array( 'role' => AiBotRole::ROLE ) );
	}

	public function test_media_abilities_are_registered(): void {
		foreach ( array( ListMedia::ABILITY, UploadMedia::ABILITY, DeleteMedia::ABILITY ) as $name ) {
			$this->assertNotNull( wp_get_ability( $name ) );
		}
	}

	public function test_only_delete_is_blocked_on_production(): void {
		$this->assertTrue( ListMedia::is_allowed_on_production() );
		$this->assertTrue( UploadMedia::is_allowed_on_production() );
		$this->assertFalse( DeleteMedia::is_allowed_on_production() );
	}

	public function test_bot_can_upload_and_list_attachments(): void {
		wp_set_current_user( $this->bot_id );

		// 1x1 transparent PNG.
		$payload = base64_encode(
			base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=' )
		);

		$result = wp_get_ability( UploadMedia::ABILITY )->execute(
			array(
				'filename'    => 'pixel.png',
				'data_base64' => $payload,
				'title'       => 'Tiny pixel',
				'alt_text'    => 'A tiny pixel',
			)
		);
		$this->assertIsArray( $result );
		$this->assertGreaterThan( 0, $result['id'] );
		$this->assertSame( 'image/png', $result['mime_type'] );

		$listed = wp_get_ability( ListMedia::ABILITY )->execute( array( 'mime_type' => 'image' ) );
		$this->assertGreaterThanOrEqual( 1, $listed['total'] );
	}

	public function test_bot_cannot_delete_without_sudo(): void {
		$attachment_id = self::factory()->attachment->create_object(
			'pixel.png',
			0,
			array( 'post_mime_type' => 'image/png' )
		);

		wp_set_current_user( $this->bot_id );
		$result = wp_get_ability( DeleteMedia::ABILITY )->check_permissions( array( 'id' => $attachment_id ) );
		$this->assertInstanceOf( \WP_Error::class, $result );

		wp_set_current_user( $this->admin_id );
		$created = TokenManager::create( array( 'delete_posts' ), 900, false, $this->admin_id );

		wp_set_current_user( $this->bot_id );
		$result = wp_get_ability( DeleteMedia::ABILITY )->check_permissions( array( 'id' => $attachment_id, '_sudo' => $created['token'] ) );
		$this->assertTrue( $result );
	}

	public function test_upload_rejects_invalid_base64(): void {
		wp_set_current_user( $this->bot_id );

		$result = wp_get_ability( UploadMedia::ABILITY )->execute(
			array( 'filename' => 'bad.txt', 'data_base64' => 'not-base64!!!' )
		);
		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'talaxie_media_invalid_payload', $result->get_error_code() );
	}
}
