<?php

declare( strict_types = 1 );

namespace GrowthExperiments\Tests\Unit;

use GrowthExperiments\FeatureManager;
use GrowthExperiments\IExperimentManager;
use MediaWiki\Config\HashConfig;
use MediaWiki\Extension\TestKitchen\Sdk\ExperimentInterface;
use MediaWiki\Extension\TestKitchen\Sdk\ExperimentManagerInterface;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\User\Registration\UserRegistrationLookup;
use MediaWiki\User\UserIdentityValue;
use MediaWikiUnitTestCase;
use Psr\Log\LoggerInterface;

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

	public static function provideIsEarlyOnboardingExperimentTreatment(): iterable {
		yield 'no experiment manager configured' => [
			'experimentManager' => null,
			'startDate' => '20260101000000',
			'assignedGroup' => IExperimentManager::VARIANT_TREATMENT,
			'registrationDate' => '20260201000000',
			'expected' => false,
		];
		yield 'no experiment start date configured' => [
			'experimentManager' => 'mock',
			'startDate' => null,
			'assignedGroup' => IExperimentManager::VARIANT_TREATMENT,
			'registrationDate' => '20260201000000',
			'expected' => false,
		];
		yield 'user not assigned to the treatment group' => [
			'experimentManager' => 'mock',
			'startDate' => '20260101000000',
			'assignedGroup' => IExperimentManager::VARIANT_CONTROL,
			'registrationDate' => '20260201000000',
			'expected' => false,
		];
		yield 'user has no registration date' => [
			'experimentManager' => 'mock',
			'startDate' => '20260101000000',
			'assignedGroup' => IExperimentManager::VARIANT_TREATMENT,
			'registrationDate' => null,
			'expected' => false,
		];
		yield 'user registered before the experiment start date' => [
			'experimentManager' => 'mock',
			'startDate' => '20260101000000',
			'assignedGroup' => IExperimentManager::VARIANT_TREATMENT,
			'registrationDate' => '20251231235959',
			'expected' => false,
		];
		yield 'user registered exactly on the experiment start date' => [
			'experimentManager' => 'mock',
			'startDate' => '20260101000000',
			'assignedGroup' => IExperimentManager::VARIANT_TREATMENT,
			'registrationDate' => '20260101000000',
			'expected' => false,
		];
		yield 'user registered after the experiment start date and is in the treatment group' => [
			'experimentManager' => 'mock',
			'startDate' => '20260101000000',
			'assignedGroup' => IExperimentManager::VARIANT_TREATMENT,
			'registrationDate' => '20260101000001',
			'expected' => true,
		];
		yield 'experiment start date configured as ISO 8601, user registered after it' => [
			'experimentManager' => 'mock',
			'startDate' => '2026-01-01T00:00:00Z',
			'assignedGroup' => IExperimentManager::VARIANT_TREATMENT,
			'registrationDate' => '20260101000001',
			'expected' => true,
		];
		yield 'experiment start date configured as ISO 8601, user registered before it' => [
			'experimentManager' => 'mock',
			'startDate' => '2026-01-01T00:00:00Z',
			'assignedGroup' => IExperimentManager::VARIANT_TREATMENT,
			'registrationDate' => '20251231235959',
			'expected' => false,
		];
	}

	/**
	 * @dataProvider provideIsEarlyOnboardingExperimentTreatment
	 */
	public function testIsEarlyOnboardingExperimentTreatment(
		?string $experimentManager,
		?string $startDate,
		string $assignedGroup,
		?string $registrationDate,
		bool $expected
	): void {
		if ( !interface_exists( ExperimentInterface::class ) ) {
			$this->markTestSkipped( 'TestKitchen extension is not installed.' );
		}

		$user = new UserIdentityValue( 1, 'TestUser' );

		$experimentManagerMock = null;
		if ( $experimentManager !== null ) {
			$experimentMock = $this->createMock( ExperimentInterface::class );
			$experimentMock->method( 'isAssignedGroup' )
				->with( IExperimentManager::VARIANT_TREATMENT )
				->willReturn( $assignedGroup === IExperimentManager::VARIANT_TREATMENT );

			$experimentManagerMock = $this->createMock( ExperimentManagerInterface::class );
			$experimentManagerMock->method( 'getExperiment' )
				->with( IExperimentManager::DE_1_3_1_SPECIALHOMEPAGE_ONBOARDING_AB_TEST )
				->willReturn( $experimentMock );
		}

		$userRegistrationLookupMock = $this->createMock( UserRegistrationLookup::class );
		$userRegistrationLookupMock->method( 'getFirstRegistration' )
			->with( $user )
			->willReturn( $registrationDate );

		$featureManager = $this->getFeatureManager( [
			'config' => [ 'GEAccountSetupExperimentStartRegistrationDate' => $startDate ],
			'userRegistrationLookup' => $userRegistrationLookupMock,
			'experimentManager' => $experimentManagerMock,
		] );

		$this->assertSame( $expected, $featureManager->isEarlyOnboardingExperimentTreatment( $user ) );
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
		$userRegistrationLookupMock = $overrides['userRegistrationLookup']
			?? $this->createMock( UserRegistrationLookup::class );
		return new FeatureManager(
			$extensionRegistryMock,
			$config,
			$userRegistrationLookupMock,
			$this->createNoOpMock( LoggerInterface::class ),
			$overrides['experimentManager'] ?? null
		);
	}
}
