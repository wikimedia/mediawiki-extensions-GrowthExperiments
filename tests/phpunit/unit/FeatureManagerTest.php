<?php

declare( strict_types = 1 );

namespace GrowthExperiments\Tests\Unit;

use GrowthExperiments\FeatureManager;
use GrowthExperiments\IExperimentManager;
use GrowthExperiments\StaticExperimentManager;
use MediaWiki\Config\HashConfig;
use MediaWiki\Config\ServiceOptions;
use MediaWiki\Minerva\Skins\SkinMinerva;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\Skin\Skin;
use MediaWiki\User\User;
use MediaWiki\User\UserIdentityValue;
use MediaWikiUnitTestCase;

/**
 * @covers \GrowthExperiments\FeatureManager
 */
class FeatureManagerTest extends MediaWikiUnitTestCase {
	public function testShouldShowReviseToneTasksForUser() {
		$sut = $this->getFeatureManager( [ 'defaultVariant' => [
			'growthexperiments-revise-tone' => 'treatment',
		] ] );
		$user = new UserIdentityValue( 0, __CLASS__ );
		$this->assertTrue( $sut->shouldShowReviseToneTasksForUser( $user ) );
	}

	public function testShouldNotShowReviseToneTasksForUser() {
		$sut = $this->getFeatureManager( [ 'defaultVariant' => [
			'growthexperiments-revise-tone' => 'control',
		] ] );
		$user = new UserIdentityValue( 0, __CLASS__ );
		$this->assertFalse( $sut->shouldShowReviseToneTasksForUser( $user ) );
	}

	public static function provideCreateAccountV1Scenarios(): iterable {
		yield 'anon, mobile, not treatment group' => [
			'anon',
			SkinMinerva::class,
			[
				'defaultVariant' => [
					IExperimentManager::ACCOUNT_CREATION_FORM_EXPERIMENT_V1 => null,
				],
			],
			false,
		];

		yield 'anon, mobile, in treatment group' => [
			'anon',
			SkinMinerva::class,
			[
				'defaultVariant' => [
					IExperimentManager::ACCOUNT_CREATION_FORM_EXPERIMENT_V1 => IExperimentManager::VARIANT_TREATMENT,
				],
			],
			true,
		];

		yield 'not anon, mobile, in treatment group' => [
			'logged-in',
			SkinMinerva::class,
			[
				'defaultVariant' => [
					IExperimentManager::ACCOUNT_CREATION_FORM_EXPERIMENT_V1 => IExperimentManager::VARIANT_TREATMENT,
				],
			],
			false,
		];

		yield 'anon, not mobile, in treatment group' => [
			'anon',
			Skin::class,
			[
				'defaultVariant' => [
					IExperimentManager::ACCOUNT_CREATION_FORM_EXPERIMENT_V1 => IExperimentManager::VARIANT_TREATMENT,
				],
			],
			false,
		];

		yield 'anon, mobile, in treatment group, not enwiki' => [
			'anon',
			SkinMinerva::class,
			[
				'defaultVariant' => [
					IExperimentManager::ACCOUNT_CREATION_FORM_EXPERIMENT_V1 => IExperimentManager::VARIANT_TREATMENT,
				],
				'config' => [
					'DBname' => 'dewiki',
				],
			],
			false,
		];
	}

	/**
	 * @dataProvider provideCreateAccountV1Scenarios
	 */
	public function testShouldShowCreateAccountV1(
		string $userType,
		string $skinClass,
		array $overrides,
		bool $expectedResult
	): void {
		if ( $userType === 'logged-in' ) {
			$user = $this->createMock( User::class );
			$user->method( 'isAnon' )->willReturn( false );
		} else {
			$user = $this->createMock( User::class );
			$user->method( 'isAnon' )->willReturn( true );
		}

		/** @var Skin $skin */
		$skin = $this->createMock( $skinClass );

		$sut = $this->getFeatureManager( $overrides );
		$actualResult = $sut->shouldShowCreateAccountV1( $user, $skin );
		$this->assertSame( $expectedResult, $actualResult );
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
			'GEReviseToneSuggestedEditEnabled' => true,
			'GEHomepageSuggestedEditsEnabled' => true,
			'DBname' => 'enwiki',
		], $overrides['config'] ?? [] ) );
		$sut = new FeatureManager( $extensionRegistryMock, $config );
		$sut->setExperimentManager( new StaticExperimentManager( new ServiceOptions( [ 'GEHomepageDefaultVariant' ], [
			'GEHomepageDefaultVariant' => $overrides['defaultVariant'] ?? 'control',
		] ) ) );
		return $sut;
	}
}
