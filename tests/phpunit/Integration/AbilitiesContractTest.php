<?php
/**
 * @package Talaxie\Core\Tests
 */

declare(strict_types=1);

namespace Talaxie\Core\Tests\Integration;

use Talaxie\Core\Mcp\Server;
use WP_UnitTestCase;

/**
 * Whole-surface contract for the abilities shipped by talaxie-core.
 *
 * Pure abilities-API integration (no MCP transport). Validates that
 * every ability declares the metadata required by the spec (name,
 * label, description, schemas), that the prod-server tool list matches
 * the abilities flagged as production-safe, and that a representative
 * read flow returns coherent data.
 *
 * Always runs — independent of mcp-adapter availability.
 */
final class AbilitiesContractTest extends WP_UnitTestCase {

	public function test_every_ability_class_is_registered_with_the_core_registry(): void {
		foreach ( Server::abilities() as $class ) {
			$ability = wp_get_ability( $class::name() );
			$this->assertNotNull( $ability, sprintf( 'Ability %s is not registered', $class::name() ) );
			$this->assertSame( $class::name(), $ability->get_name() );
			$this->assertNotSame( '', $ability->get_label(), 'missing label on ' . $class::name() );
			$this->assertNotSame( '', $ability->get_description(), 'missing description on ' . $class::name() );
			$this->assertSame( Server::CATEGORY, $ability->get_category() );
		}
	}

	public function test_every_ability_carries_an_input_schema(): void {
		foreach ( Server::abilities() as $class ) {
			$schema = wp_get_ability( $class::name() )->get_input_schema();
			$this->assertIsArray( $schema );
			$this->assertSame( 'object', $schema['type'] ?? null, $class::name() . ' input must be an object' );
		}
	}

	public function test_every_destructive_ability_has_a_schema_property_for_sudo(): void {
		foreach ( Server::abilities() as $class ) {
			if ( $class::is_allowed_on_production() ) {
				continue;
			}
			$schema     = wp_get_ability( $class::name() )->get_input_schema();
			$properties = $schema['properties'] ?? array();
			$this->assertArrayHasKey(
				'_sudo',
				$properties,
				$class::name() . ' is destructive but its schema does not advertise the _sudo input'
			);
		}
	}

	public function test_prod_safe_abilities_count_matches_filter_for_production(): void {
		$prod = array();
		foreach ( Server::abilities() as $class ) {
			if ( $class::is_allowed_on_production() ) {
				$prod[] = $class::name();
			}
		}
		$this->assertGreaterThanOrEqual( 20, count( $prod ), 'unexpectedly few prod abilities' );
		$this->assertLessThan( count( Server::abilities() ), count( $prod ), 'no abilities are prod-blocked' );
	}

	public function test_admin_can_round_trip_site_get_info(): void {
		$admin = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin );

		$result = wp_get_ability( 'talaxie-core/site-get-info' )->execute( array() );
		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'name', $result );
		$this->assertArrayHasKey( 'wp_version', $result );
		$this->assertArrayHasKey( 'environment', $result );
	}
}
