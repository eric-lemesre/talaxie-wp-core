<?php
/**
 * @package Talaxie\Core\Tests
 */

declare(strict_types=1);

namespace Talaxie\Core\Tests\Roles;

use Talaxie\Core\Roles\AiBotRole;
use WP_UnitTestCase;

/**
 * @covers \Talaxie\Core\Roles\AiBotRole
 */
final class AiBotRoleTest extends WP_UnitTestCase {

	public function test_role_is_registered(): void {
		$role = get_role( AiBotRole::ROLE );
		$this->assertInstanceOf( \WP_Role::class, $role );
	}

	public function test_granted_capabilities_include_publishing(): void {
		$role = get_role( AiBotRole::ROLE );
		$this->assertTrue( $role->has_cap( 'read' ) );
		$this->assertTrue( $role->has_cap( 'edit_posts' ) );
		$this->assertTrue( $role->has_cap( 'edit_published_posts' ) );
		$this->assertTrue( $role->has_cap( 'upload_files' ) );
	}

	public function test_dangerous_capabilities_are_not_granted(): void {
		$role = get_role( AiBotRole::ROLE );
		$this->assertFalse( $role->has_cap( 'manage_options' ) );
		$this->assertFalse( $role->has_cap( 'delete_users' ) );
		$this->assertFalse( $role->has_cap( 'activate_plugins' ) );
		$this->assertFalse( $role->has_cap( 'unfiltered_html' ) );
	}

	public function test_register_is_idempotent(): void {
		AiBotRole::register();
		AiBotRole::register();
		$this->assertInstanceOf( \WP_Role::class, get_role( AiBotRole::ROLE ) );
	}

	public function test_unregister_removes_the_role(): void {
		AiBotRole::unregister();
		$this->assertNull( get_role( AiBotRole::ROLE ) );
		// Restore for downstream tests.
		AiBotRole::register();
	}
}
