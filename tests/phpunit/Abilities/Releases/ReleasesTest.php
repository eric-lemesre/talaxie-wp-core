<?php
/**
 * @package Talaxie\Core\Tests
 */

declare(strict_types=1);

namespace Talaxie\Core\Tests\Abilities\Releases;

use Talaxie\Core\Mcp\Abilities\Releases\CreateRelease;
use Talaxie\Core\Mcp\Abilities\Releases\DeleteRelease;
use Talaxie\Core\Mcp\Abilities\Releases\GetRelease;
use Talaxie\Core\Mcp\Abilities\Releases\ListReleases;
use Talaxie\Core\Mcp\Abilities\Releases\UpdateRelease;
use Talaxie\Core\PostTypes\Release;
use Talaxie\Core\Roles\AiBotRole;
use Talaxie\Core\Taxonomies\Component;
use WP_UnitTestCase;

final class ReleasesTest extends WP_UnitTestCase {

	private int $bot_id;

	public function set_up(): void {
		parent::set_up();
		Release::register();
		Component::register();
		$this->bot_id = self::factory()->user->create( array( 'role' => AiBotRole::ROLE ) );
	}

	public function test_release_abilities_are_registered(): void {
		foreach ( array( ListReleases::ABILITY, GetRelease::ABILITY, CreateRelease::ABILITY, UpdateRelease::ABILITY, DeleteRelease::ABILITY ) as $name ) {
			$this->assertNotNull( wp_get_ability( $name ) );
		}
	}

	public function test_all_release_abilities_are_allowed_on_production(): void {
		// Releases CRUD is the bot's job — no abilities are blocked on prod.
		$this->assertTrue( ListReleases::is_allowed_on_production() );
		$this->assertTrue( GetRelease::is_allowed_on_production() );
		$this->assertTrue( CreateRelease::is_allowed_on_production() );
		$this->assertTrue( UpdateRelease::is_allowed_on_production() );
		$this->assertTrue( DeleteRelease::is_allowed_on_production() );
	}

	public function test_bot_full_crud_on_releases_without_sudo(): void {
		wp_set_current_user( $this->bot_id );

		$created = wp_get_ability( CreateRelease::ABILITY )->execute(
			array(
				'title'      => 'Talaxie 8.0.0',
				'content'    => 'First community release.',
				'status'     => 'publish',
				'components' => array( 'studio', 'runtime' ),
			)
		);
		$this->assertIsArray( $created );
		$this->assertGreaterThan( 0, $created['id'] );
		$this->assertSame( 'publish', $created['status'] );

		$got = wp_get_ability( GetRelease::ABILITY )->execute( array( 'id' => $created['id'] ) );
		$this->assertSame( 'Talaxie 8.0.0', $got['title'] );
		$this->assertContains( 'studio', $got['components'] );

		$updated = wp_get_ability( UpdateRelease::ABILITY )->execute(
			array( 'id' => $created['id'], 'title' => 'Talaxie 8.0.1', 'components' => array( 'studio' ) )
		);
		$this->assertSame( $created['id'], $updated['id'] );

		$deleted = wp_get_ability( DeleteRelease::ABILITY )->execute( array( 'id' => $created['id'], 'force' => true ) );
		$this->assertTrue( $deleted['deleted'] );
		$this->assertNull( get_post( $created['id'] ) );
	}

	public function test_list_filters_by_component(): void {
		wp_set_current_user( $this->bot_id );

		$one = wp_get_ability( CreateRelease::ABILITY )->execute(
			array( 'title' => 'A', 'status' => 'publish', 'components' => array( 'studio' ) )
		);
		wp_get_ability( CreateRelease::ABILITY )->execute(
			array( 'title' => 'B', 'status' => 'publish', 'components' => array( 'runtime' ) )
		);

		$listed = wp_get_ability( ListReleases::ABILITY )->execute( array( 'component' => 'studio' ) );
		$ids    = array_column( $listed['items'], 'id' );
		$this->assertContains( $one['id'], $ids );
		$this->assertSame( 1, $listed['total'] );
	}
}
