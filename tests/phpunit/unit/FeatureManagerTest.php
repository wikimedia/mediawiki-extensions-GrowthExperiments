<?php

declare( strict_types = 1 );

namespace GrowthExperiments\Tests\Unit;

use GrowthExperiments\FeatureManager;
use MediaWiki\Config\HashConfig;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWikiUnitTestCase;

/**
 * @covers \GrowthExperiments\FeatureManager
 */
class FeatureManagerTest extends MediaWikiUnitTestCase {

	/**
	 * @dataProvider provideIsLinkRecommendationsAvailable
	 */
	public function testIsLinkRecommendationsAvailable(
		array $registeredExtensions,
		bool $suggestedEditsEnabled,
		bool $linkRecommendationsEnabled,
		bool $expected
	): void {
		$featureManager = $this->getFeatureManager( [
			'registeredExtensions' => $registeredExtensions,
			'config' => [
				'GEHomepageSuggestedEditsEnabled' => $suggestedEditsEnabled,
				'GENewcomerTasksLinkRecommendationsEnabled' => $linkRecommendationsEnabled,
			],
		] );

		$this->assertSame( $expected, $featureManager->isLinkRecommendationsAvailable() );
	}

	public static function provideIsLinkRecommendationsAvailable(): iterable {
		$allExtensionsLoaded = [ 'WikimediaMessages', 'VisualEditor', 'CirrusSearch' ];

		yield 'All dependencies satisfied, link recommendations should be available' => [
			$allExtensionsLoaded,
			true,
			true,
			true,
		];
		yield 'WikimediaMessages not loaded, link recommendations should not be available' => [
			[ 'VisualEditor', 'CirrusSearch' ],
			true,
			true,
			false,
		];
		yield 'VisualEditor not loaded, link recommendations should not be available' => [
			[ 'WikimediaMessages', 'CirrusSearch' ],
			true,
			true,
			false,
		];
		yield 'CirrusSearch not loaded, link recommendations should not be available' => [
			[ 'WikimediaMessages', 'VisualEditor' ],
			true,
			true,
			false,
		];
		yield 'GEHomepageSuggestedEditsEnabled is false, link recommendations should not be available' => [
			$allExtensionsLoaded,
			false,
			true,
			false,
		];
		yield 'GENewcomerTasksLinkRecommendationsEnabled is false, link recommendations should not be available' => [
			$allExtensionsLoaded,
			true,
			false,
			false,
		];
	}

	public static function provideAreImageRecommendationDependenciesSatisfied(): iterable {
		$allExtensionsLoaded = [ 'WikimediaMessages', 'VisualEditor', 'CirrusSearch' ];

		yield 'all dependencies satisfied' => [
			$allExtensionsLoaded,
			true,
			true,
		];

		yield 'WikimediaMessages not loaded' => [
			[ 'VisualEditor', 'CirrusSearch' ],
			true,
			false,
		];

		yield 'VisualEditor not loaded' => [
			[ 'WikimediaMessages', 'CirrusSearch' ],
			true,
			false,
		];

		yield 'CirrusSearch not loaded' => [
			[ 'WikimediaMessages', 'VisualEditor' ],
			true,
			false,
		];

		yield 'GEHomepageSuggestedEditsEnabled disabled' => [
			$allExtensionsLoaded,
			false,
			false,
		];
	}

	/**
	 * @dataProvider provideAreImageRecommendationDependenciesSatisfied
	 */
	public function testAreImageRecommendationDependenciesSatisfied(
		array $registeredExtensions,
		bool $suggestedEditsEnabled,
		bool $expected
	): void {
		$sut = $this->getFeatureManager( [
			'registeredExtensions' => $registeredExtensions,
			'config' => [ 'GEHomepageSuggestedEditsEnabled' => $suggestedEditsEnabled ],
		] );
		$this->assertSame( $expected, $sut->areImageRecommendationDependenciesSatisfied() );
	}

	public static function provideAreLinkRecommendationsEnabled(): iterable {
		yield 'enabled' => [ true, true ];
		yield 'disabled' => [ false, false ];
	}

	/**
	 * @dataProvider provideAreLinkRecommendationsEnabled
	 */
	public function testAreLinkRecommendationsEnabled( bool $configValue, bool $expected ): void {
		$sut = $this->getFeatureManager( [
			'config' => [ 'GENewcomerTasksLinkRecommendationsEnabled' => $configValue ],
		] );
		$this->assertSame( $expected, $sut->areLinkRecommendationsEnabled() );
	}

	/**
	 * Provide a configured FeatureManager with all relevant config feature flags enabled
	 *
	 * @param array $overrides
	 * @return FeatureManager
	 */
	private function getFeatureManager( array $overrides = [] ): FeatureManager {
		$extensionRegistryMock = $this->createMock( ExtensionRegistry::class );
		$registeredExtensions = $overrides['registeredExtensions'] ?? [ 'WikimediaMessages', 'VisualEditor' ];
		$extensionRegistryMock
			->method( 'isLoaded' )
			->willReturnCallback( static function ( string $extensionName ) use (
				$registeredExtensions,
			): bool {
				return in_array( $extensionName, $registeredExtensions, true );
			} );

		$config = new HashConfig( array_merge( [
			'GEHomepageSuggestedEditsEnabled' => true,
		], $overrides['config'] ?? [] ) );
		return new FeatureManager( $extensionRegistryMock, $config );
	}
}
