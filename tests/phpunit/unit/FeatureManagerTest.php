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
use MediaWiki\Request\WebRequest;
use MediaWiki\Skin\Skin;
use MediaWiki\User\User;
use MediaWikiUnitTestCase;

/**
 * @covers \GrowthExperiments\FeatureManager
 */
class FeatureManagerTest extends MediaWikiUnitTestCase {

	public static function provideCreateAccountV2Scenarios(): iterable {
		yield 'anon, mobile, not treatment group' => [
			'anon',
			static fn () => SkinMinerva::class,
			[
				'defaultVariant' => [
					IExperimentManager::ACCOUNT_CREATION_FORM_EXPERIMENT_V2 => null,
				],
			],
			null,
			false,
		];

		yield 'anon, mobile, in treatment group' => [
			'anon',
			static fn () => SkinMinerva::class,
			[
				'defaultVariant' => [
					IExperimentManager::ACCOUNT_CREATION_FORM_EXPERIMENT_V2 => IExperimentManager::VARIANT_TREATMENT,
				],
			],
			null,
			true,
		];

		yield 'anon, mobile, in treatment group via request' => [
			'anon',
			static fn () => SkinMinerva::class,
			[
				'defaultVariant' => [
					IExperimentManager::ACCOUNT_CREATION_FORM_EXPERIMENT_V2 => null,
				],
			],
			[ IExperimentManager::ACCOUNT_CREATION_FORM_EXPERIMENT_V2 . ':' . IExperimentManager::VARIANT_TREATMENT ],
			true,
		];

		yield 'not anon, mobile, in treatment group' => [
			'logged-in',
			static fn () => SkinMinerva::class,
			[
				'defaultVariant' => [
					IExperimentManager::ACCOUNT_CREATION_FORM_EXPERIMENT_V2 => IExperimentManager::VARIANT_TREATMENT,
				],
			],
			null,
			false,
		];

		yield 'anon, not mobile, in treatment group' => [
			'anon',
			static fn () => Skin::class,
			[
				'defaultVariant' => [
					IExperimentManager::ACCOUNT_CREATION_FORM_EXPERIMENT_V2 => IExperimentManager::VARIANT_TREATMENT,
				],
			],
			null,
			false,
		];

		yield 'anon, mobile, in treatment group, not enwiki' => [
			'anon',
			static fn () => SkinMinerva::class,
			[
				'defaultVariant' => [
					IExperimentManager::ACCOUNT_CREATION_FORM_EXPERIMENT_V2 => IExperimentManager::VARIANT_TREATMENT,
				],
				'config' => [
					'DBname' => 'dewiki',
				],
			],
			null,
			false,
		];
	}

	/**
	 * @dataProvider provideCreateAccountV2Scenarios
	 */
	public function testShouldShowCreateAccountV2(
		string $userType,
		\Closure $getSkinClass,
		array $overrides,
		?array $requestOverride,
		bool $expectedResult
	): void {
		if ( !class_exists( SkinMinerva::class ) ) {
			$this->markTestSKipped( 'Minerva is not available' );
		}

		if ( $userType === 'logged-in' ) {
			$user = $this->createMock( User::class );
			$user->method( 'isAnon' )->willReturn( false );
		} else {
			$user = $this->createMock( User::class );
			$user->method( 'isAnon' )->willReturn( true );
		}

		/** @var Skin $skin */
		$skin = $this->createMock( $getSkinClass() );
		$request = $this->createMock( WebRequest::class );
		$request->method( 'getArray' )
			->with( 'experiments' )
			->willReturn( $requestOverride ?? [] );

		$sut = $this->getFeatureManager( $overrides );
		$actualResult = $sut->shouldShowCreateAccountV2( $user, $skin, $request );
		$this->assertSame( $expectedResult, $actualResult );
	}

	public static function provideNoDesktopBenefitsScenarios(): iterable {
		yield 'anon, not mobile, in treatment group' => [
			'anon',
			static fn () => Skin::class,
			[
				'defaultVariant' => [
					IExperimentManager::CREATE_ACCOUNT_NO_BENEFITS_DESKTOP => IExperimentManager::VARIANT_TREATMENT,
				],
			],
			null,
			true,
		];

		yield 'anon, mobile, not treatment group' => [
			'anon',
			static fn () => SkinMinerva::class,
			[
				'defaultVariant' => [
					IExperimentManager::CREATE_ACCOUNT_NO_BENEFITS_DESKTOP => null,
				],
			],
			null,
			false,
		];

		yield 'anon, mobile, in treatment group' => [
			'anon',
			static fn () => SkinMinerva::class,
			[
				'defaultVariant' => [
					IExperimentManager::CREATE_ACCOUNT_NO_BENEFITS_DESKTOP => IExperimentManager::VARIANT_TREATMENT,
				],
			],
			null,
			false,
		];

		yield 'anon, mobile, in treatment group via request' => [
			'anon',
			static fn () => SkinMinerva::class,
			[
				'defaultVariant' => [
					IExperimentManager::CREATE_ACCOUNT_NO_BENEFITS_DESKTOP => null,
				],
			],
			[ IExperimentManager::CREATE_ACCOUNT_NO_BENEFITS_DESKTOP . ':' . IExperimentManager::VARIANT_TREATMENT ],
			false,
		];

		yield 'not anon, not mobile, in treatment group' => [
			'logged-in',
			static fn () => Skin::class,
			[
				'defaultVariant' => [
					IExperimentManager::CREATE_ACCOUNT_NO_BENEFITS_DESKTOP => IExperimentManager::VARIANT_TREATMENT,
				],
			],
			null,
			false,
		];

		yield 'anon, not mobile, in treatment group, not enwiki' => [
			'anon',
			static fn () => Skin::class,
			[
				'defaultVariant' => [
					IExperimentManager::CREATE_ACCOUNT_NO_BENEFITS_DESKTOP => IExperimentManager::VARIANT_TREATMENT,
				],
				'config' => [
					'DBname' => 'dewiki',
				],
			],
			null,
			false,
		];
	}

	/**
	 * @dataProvider provideNoDesktopBenefitsScenarios
	 */
	public function testShouldShowNoDesktopBenefits(
		string $userType,
		\Closure $getSkinClass,
		array $overrides,
		?array $requestOverride,
		bool $expectedResult
	): void {
		if ( !class_exists( SkinMinerva::class ) ) {
			$this->markTestSKipped( 'Minerva is not available' );
		}

		if ( $userType === 'logged-in' ) {
			$user = $this->createMock( User::class );
			$user->method( 'isAnon' )->willReturn( false );
		} else {
			$user = $this->createMock( User::class );
			$user->method( 'isAnon' )->willReturn( true );
		}

		/** @var Skin $skin */
		$skin = $this->createMock( $getSkinClass() );
		$request = $this->createMock( WebRequest::class );
		$request->method( 'getArray' )
			->with( 'experiments' )
			->willReturn( $requestOverride ?? [] );

		$sut = $this->getFeatureManager( $overrides );
		$actualResult = $sut->shouldShowCreateAccountNoBenefitsTreatment( $user, $skin, $request );
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
