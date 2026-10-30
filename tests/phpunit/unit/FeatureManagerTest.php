<?php

declare( strict_types = 1 );

namespace GrowthExperiments\Tests\Unit;

use GrowthExperiments\FeatureManager;
use GrowthExperiments\HomeAvailability;
use GrowthExperiments\HomepageHooks;
use GrowthExperiments\IExperimentManager;
use MediaWiki\Config\HashConfig;
use MediaWiki\Extension\TestKitchen\Sdk\ExperimentCoordinatorInterface;
use MediaWiki\Extension\TestKitchen\Sdk\ExperimentInterface;
use MediaWiki\Extension\TestKitchen\Sdk\ExperimentManagerInterface;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\User\Options\UserOptionsLookup;
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

	public static function provideGetHomeAvailability(): iterable {
		$allExtensions = [ 'PersonalDashboard', 'BetaFeatures' ];
		yield 'both flags off' => [ $allExtensions, false, false, HomeAvailability::Unavailable ];
		yield 'Home flag on' => [ $allExtensions, true, false, HomeAvailability::Wikiwide ];
		yield 'both flags on, Home flag wins' => [ $allExtensions, true, true, HomeAvailability::Wikiwide ];
		yield 'beta flag on' => [ $allExtensions, false, true, HomeAvailability::BetaFeature ];
		yield 'beta flag on, BetaFeatures not loaded' => [
			[ 'PersonalDashboard' ], false, true, HomeAvailability::Unavailable,
		];
		yield 'Home flag on, PersonalDashboard not loaded' => [
			[ 'BetaFeatures' ], true, false, HomeAvailability::Unavailable,
		];
		yield 'beta flag on, PersonalDashboard not loaded' => [
			[ 'BetaFeatures' ], false, true, HomeAvailability::Unavailable,
		];
	}

	/**
	 * @dataProvider provideGetHomeAvailability
	 */
	public function testGetHomeAvailability(
		array $registeredExtensions, bool $homeEnabled, bool $betaFeatureEnabled, HomeAvailability $expected
	): void {
		$featureManager = $this->getFeatureManager( [
			'registeredExtensions' => $registeredExtensions,
			'config' => [
				'GEHomeEnabled' => $homeEnabled,
				'GEHomeBetaFeatureEnabled' => $betaFeatureEnabled,
			],
		] );
		$this->assertSame( $expected, $featureManager->getHomeAvailability() );
	}

	public static function provideIsHomeEnabledForUser(): iterable {
		yield 'available, preference on' => [ true, true, true, true ];
		yield 'available, preference off' => [ true, true, false, false ];
		yield 'flag off, preference on' => [ false, true, true, false ];
		yield 'PersonalDashboard not loaded, preference on' => [ true, false, true, false ];
	}

	/**
	 * @dataProvider provideIsHomeEnabledForUser
	 */
	public function testIsHomeEnabledForUser(
		bool $homeEnabled, bool $dashboardLoaded, bool $preferenceEnabled, bool $expected
	): void {
		$featureManager = $this->getFeatureManager( [
			'registeredExtensions' => $dashboardLoaded ? [ 'PersonalDashboard' ] : [],
			'config' => [ 'GEHomeEnabled' => $homeEnabled ],
			'userOptions' => [ HomepageHooks::HOMEPAGE_PREF_ENABLE => $preferenceEnabled ],
		] );
		$this->assertSame(
			$expected,
			$featureManager->isHomeEnabledForUser( new UserIdentityValue( 1, 'Alice' ) )
		);
	}

	public static function provideIsHomeBetaFeatureEnabledByUserWhenHomeIsNotABetaFeature(): iterable {
		yield 'Home unavailable' => [ false ];
		yield 'Home wiki-wide' => [ true ];
	}

	/**
	 * The BetaFeature availability case calls BetaFeatures, which needs the global
	 * service container. SpecialHomeTest tests that case.
	 *
	 * @dataProvider provideIsHomeBetaFeatureEnabledByUserWhenHomeIsNotABetaFeature
	 */
	public function testIsHomeBetaFeatureEnabledByUserWhenHomeIsNotABetaFeature( bool $homeEnabled ): void {
		$featureManager = $this->getFeatureManager( [
			'registeredExtensions' => [ 'PersonalDashboard', 'BetaFeatures' ],
			'config' => [ 'GEHomeEnabled' => $homeEnabled ],
			'userOptions' => [ 'growthexperiments-home-betafeature' => true ],
		] );
		$this->assertFalse(
			$featureManager->isHomeBetaFeatureEnabledByUser( new UserIdentityValue( 1, 'Alice' ) )
		);
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

	public static function provideEarlyOnboardingExperimentGroup(): iterable {
		yield 'no experiment manager configured' => [
			'experimentManager' => null,
			'startDate' => '20260101000000',
			'assignedGroup' => IExperimentManager::VARIANT_TREATMENT,
			'registrationDate' => '20260201000000',
			'expectedTreatment' => false,
			'expectedControl' => false,
		];
		yield 'no experiment start date configured' => [
			'experimentManager' => 'mock',
			'startDate' => null,
			'assignedGroup' => IExperimentManager::VARIANT_TREATMENT,
			'registrationDate' => '20260201000000',
			'expectedTreatment' => false,
			'expectedControl' => false,
		];
		yield 'user registered after the experiment start date and is in the control group' => [
			'experimentManager' => 'mock',
			'startDate' => '20260101000000',
			'assignedGroup' => IExperimentManager::VARIANT_CONTROL,
			'registrationDate' => '20260201000000',
			'expectedTreatment' => false,
			'expectedControl' => true,
		];
		yield 'user registered after the experiment start date and is not enrolled' => [
			'experimentManager' => 'mock',
			'startDate' => '20260101000000',
			'assignedGroup' => null,
			'registrationDate' => '20260201000000',
			'expectedTreatment' => false,
			'expectedControl' => false,
		];
		yield 'user has no registration date' => [
			'experimentManager' => 'mock',
			'startDate' => '20260101000000',
			'assignedGroup' => IExperimentManager::VARIANT_TREATMENT,
			'registrationDate' => null,
			'expectedTreatment' => false,
			'expectedControl' => false,
		];
		yield 'user registered before the experiment start date' => [
			'experimentManager' => 'mock',
			'startDate' => '20260101000000',
			'assignedGroup' => IExperimentManager::VARIANT_TREATMENT,
			'registrationDate' => '20251231235959',
			'expectedTreatment' => false,
			'expectedControl' => false,
		];
		yield 'control user registered before the experiment start date' => [
			'experimentManager' => 'mock',
			'startDate' => '20260101000000',
			'assignedGroup' => IExperimentManager::VARIANT_CONTROL,
			'registrationDate' => '20251231235959',
			'expectedTreatment' => false,
			'expectedControl' => false,
		];
		yield 'user registered exactly on the experiment start date' => [
			'experimentManager' => 'mock',
			'startDate' => '20260101000000',
			'assignedGroup' => IExperimentManager::VARIANT_TREATMENT,
			'registrationDate' => '20260101000000',
			'expectedTreatment' => false,
			'expectedControl' => false,
		];
		yield 'user registered after the experiment start date and is in the treatment group' => [
			'experimentManager' => 'mock',
			'startDate' => '20260101000000',
			'assignedGroup' => IExperimentManager::VARIANT_TREATMENT,
			'registrationDate' => '20260101000001',
			'expectedTreatment' => true,
			'expectedControl' => false,
		];
		yield 'experiment start date configured as ISO 8601, user registered after it' => [
			'experimentManager' => 'mock',
			'startDate' => '2026-01-01T00:00:00Z',
			'assignedGroup' => IExperimentManager::VARIANT_TREATMENT,
			'registrationDate' => '20260101000001',
			'expectedTreatment' => true,
			'expectedControl' => false,
		];
		yield 'experiment start date configured as ISO 8601, user registered before it' => [
			'experimentManager' => 'mock',
			'startDate' => '2026-01-01T00:00:00Z',
			'assignedGroup' => IExperimentManager::VARIANT_TREATMENT,
			'registrationDate' => '20251231235959',
			'expectedTreatment' => false,
			'expectedControl' => false,
		];
		yield 'scaled, control user after start' => [
			'experimentManager' => 'mock',
			'startDate' => '2026-01-01T00:00:00Z',
			'assignedGroup' => IExperimentManager::VARIANT_CONTROL,
			'registrationDate' => '20261231235959',
			'expectedTreatment' => true,
			'expectedControl' => false,
			'scaled' => true,
		];
		yield 'scaled, treatment user after start' => [
			'experimentManager' => 'mock',
			'startDate' => '20260101000000',
			'assignedGroup' => IExperimentManager::VARIANT_TREATMENT,
			'registrationDate' => '20260201000000',
			'expectedTreatment' => true,
			'expectedControl' => false,
			'scaled' => true,
		];
		// After the experiment ends, every user is unenrolled.
		yield 'scaled, unenrolled user after start' => [
			'experimentManager' => 'mock',
			'startDate' => '20260101000000',
			'assignedGroup' => null,
			'registrationDate' => '20260201000000',
			'expectedTreatment' => true,
			'expectedControl' => false,
			'scaled' => true,
		];
		yield 'scaled, user registered before start' => [
			'experimentManager' => 'mock',
			'startDate' => '20260101000000',
			'assignedGroup' => IExperimentManager::VARIANT_CONTROL,
			'registrationDate' => '20251231235959',
			'expectedTreatment' => false,
			'expectedControl' => false,
			'scaled' => true,
		];
		yield 'scaled, no experiment start date configured' => [
			'experimentManager' => 'mock',
			'startDate' => null,
			'assignedGroup' => IExperimentManager::VARIANT_TREATMENT,
			'registrationDate' => '20260201000000',
			'expectedTreatment' => false,
			'expectedControl' => false,
			'scaled' => true,
		];
		yield 'scaled, no experiment manager configured' => [
			'experimentManager' => null,
			'startDate' => '20260101000000',
			'assignedGroup' => IExperimentManager::VARIANT_TREATMENT,
			'registrationDate' => '20260201000000',
			'expectedTreatment' => true,
			'expectedControl' => false,
			'scaled' => true,
		];
	}

	/**
	 * @dataProvider provideEarlyOnboardingExperimentGroup
	 */
	public function testEarlyOnboardingExperimentGroup(
		?string $experimentManager,
		?string $startDate,
		?string $assignedGroup,
		?string $registrationDate,
		bool $expectedTreatment,
		bool $expectedControl,
		bool $scaled = false,
	): void {
		$this->skipIfTestKitchenIsMissing();

		$user = new UserIdentityValue( 1, 'TestUser' );

		$experimentManagerMock = null;
		if ( $experimentManager !== null ) {
			$experimentMock = $this->createMock( ExperimentInterface::class );
			$experimentMock->method( 'isAssignedGroup' )
				->willReturnCallback( static fn ( string $group ) => $group === $assignedGroup );

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
			'config' => [
				'GEAccountSetupExperimentStartRegistrationDate' => $startDate,
				'GEEarlyOnboardingScaled' => $scaled,
			],
			'userRegistrationLookup' => $userRegistrationLookupMock,
			'experimentManager' => $experimentManagerMock,
		] );

		$this->assertSame(
			$expectedTreatment,
			$featureManager->isEarlyOnboardingExperimentTreatment( $user ),
			'isEarlyOnboardingExperimentTreatment'
		);
		$this->assertSame(
			$expectedControl,
			$featureManager->isEarlyOnboardingExperimentControl( $user ),
			'isEarlyOnboardingExperimentControl'
		);
	}

	/**
	 * The SDK starts before the account exists. For a user created in this request,
	 * the coordinator must update its user identifier before the group is read.
	 */
	public function testIsEarlyOnboardingExperimentTreatmentUpdatesTheExperimentUserForANewAccount(): void {
		$this->skipIfTestKitchenIsMissing();
		$user = new UserIdentityValue( 1, 'TestUser' );
		$experimentCoordinator = $this->createMock( ExperimentCoordinatorInterface::class );
		$experimentCoordinator->expects( $this->once() )
			->method( 'updateUser' )
			->with( $user );

		$featureManager = $this->getFeatureManagerForTreatment( $user, $experimentCoordinator );

		$this->assertTrue( $featureManager->isEarlyOnboardingExperimentTreatment( $user, true ) );
	}

	public function testIsEarlyOnboardingExperimentControlUpdatesTheExperimentUserForANewAccount(): void {
		$this->skipIfTestKitchenIsMissing();
		$user = new UserIdentityValue( 1, 'TestUser' );
		$experimentCoordinator = $this->createMock( ExperimentCoordinatorInterface::class );
		$experimentCoordinator->expects( $this->once() )
			->method( 'updateUser' )
			->with( $user );

		$featureManager = $this->getFeatureManagerForTreatment( $user, $experimentCoordinator );

		$this->assertTrue( $featureManager->isEarlyOnboardingExperimentControl( $user, true ) );
	}

	public function testIsEarlyOnboardingExperimentTreatmentKeepsTheExperimentUserForAnOlderAccount(): void {
		$this->skipIfTestKitchenIsMissing();
		$user = new UserIdentityValue( 1, 'TestUser' );
		$experimentCoordinator = $this->createNoOpMock( ExperimentCoordinatorInterface::class );

		$featureManager = $this->getFeatureManagerForTreatment( $user, $experimentCoordinator );

		$this->assertTrue( $featureManager->isEarlyOnboardingExperimentTreatment( $user ) );
	}

	/**
	 * ServiceWiring gives the coordinator together with the manager. If it is absent,
	 * the group is still read, but from the identifier of the anonymous user.
	 */
	public function testIsEarlyOnboardingExperimentTreatmentLogsAMissingExperimentCoordinator(): void {
		$this->skipIfTestKitchenIsMissing();
		$user = new UserIdentityValue( 1, 'TestUser' );
		$logger = $this->createMock( LoggerInterface::class );
		$logger->expects( $this->once() )->method( 'error' );

		$featureManager = $this->getFeatureManagerForTreatment( $user, null, $logger );

		$this->assertTrue( $featureManager->isEarlyOnboardingExperimentTreatment( $user, true ) );
	}

	/**
	 * After the experiment ends, a new account is not enrolled. The scaled flag
	 * still gives it the treatment, and does not use TestKitchen.
	 */
	public function testScaledEarlyOnboardingGivesTreatmentToANewAccountWithoutTestKitchen(): void {
		$this->skipIfTestKitchenIsMissing();
		$user = new UserIdentityValue( 1, 'TestUser' );

		$featureManager = $this->getFeatureManager( [
			'config' => [
				'GEAccountSetupExperimentStartRegistrationDate' => '20260101000000',
				'GEEarlyOnboardingScaled' => true,
			],
			'experimentManager' => $this->createNoOpMock( ExperimentManagerInterface::class ),
			'experimentCoordinator' => $this->createNoOpMock( ExperimentCoordinatorInterface::class ),
		] );

		$this->assertTrue( $featureManager->isEarlyOnboardingExperimentTreatment( $user, true ) );
		$this->assertFalse( $featureManager->isEarlyOnboardingExperimentControl( $user, true ) );
	}

	private function skipIfTestKitchenIsMissing(): void {
		if ( !interface_exists( ExperimentInterface::class ) ) {
			$this->markTestSkipped( 'TestKitchen extension is not installed.' );
		}
	}

	/**
	 * Provide a FeatureManager which reports $user as assigned to any early onboarding group.
	 */
	private function getFeatureManagerForTreatment(
		UserIdentityValue $user,
		?ExperimentCoordinatorInterface $experimentCoordinator,
		?LoggerInterface $logger = null
	): FeatureManager {
		$experimentMock = $this->createMock( ExperimentInterface::class );
		$experimentMock->method( 'isAssignedGroup' )->willReturn( true );
		$experimentManagerMock = $this->createMock( ExperimentManagerInterface::class );
		$experimentManagerMock->method( 'getExperiment' )->willReturn( $experimentMock );

		$userRegistrationLookupMock = $this->createMock( UserRegistrationLookup::class );
		$userRegistrationLookupMock->method( 'getFirstRegistration' )
			->with( $user )
			->willReturn( '20260101000001' );

		return $this->getFeatureManager( [
			'config' => [ 'GEAccountSetupExperimentStartRegistrationDate' => '20260101000000' ],
			'userRegistrationLookup' => $userRegistrationLookupMock,
			'experimentManager' => $experimentManagerMock,
			'experimentCoordinator' => $experimentCoordinator,
			'logger' => $logger,
		] );
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
			'GEEarlyOnboardingScaled' => false,
			'GEHomeBetaFeatureEnabled' => false,
		], $overrides['config'] ?? [] ) );
		$userRegistrationLookupMock = $overrides['userRegistrationLookup']
			?? $this->createMock( UserRegistrationLookup::class );
		$userOptions = $overrides['userOptions'] ?? [];
		$userOptionsLookupMock = $this->createMock( UserOptionsLookup::class );
		$userOptionsLookupMock
			->method( 'getBoolOption' )
			->willReturnCallback( static function ( $user, string $option ) use ( $userOptions ): bool {
				return (bool)( $userOptions[$option] ?? false );
			} );
		return new FeatureManager(
			$extensionRegistryMock,
			$config,
			$userRegistrationLookupMock,
			$userOptionsLookupMock,
			$overrides['logger'] ?? $this->createNoOpMock( LoggerInterface::class ),
			$overrides['experimentManager'] ?? null,
			$overrides['experimentCoordinator'] ?? null,
		);
	}
}
