<?php

namespace GrowthExperiments\Tests\Unit;

use GrowthExperiments\ExperimentUserManager;
use GrowthExperiments\VariantHooks;
use MediaWiki\Config\ServiceOptions;
use MediaWiki\User\Options\UserOptionsLookup;
use MediaWiki\User\Options\UserOptionsManager;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserIdentityValue;
use MediaWikiUnitTestCase;

/**
 * @coversDefaultClass \GrowthExperiments\ExperimentUserManager
 */
class ExperimentUserManagerTest extends MediaWikiUnitTestCase {

	/**
	 * @covers ::getVariant
	 */
	public function testGetVariantFallbackToDefault() {
		$user = new UserIdentityValue( 0, __CLASS__ );
		$userOptionsLookupMock = $this->createMock( UserOptionsLookup::class );
		$userOptionsLookupMock->method( 'getOption' )
			->willReturn( '' );
		$this->assertEquals( 'Foo', $this->getExperimentUserManager(
			new ServiceOptions(
				ExperimentUserManager::CONSTRUCTOR_OPTIONS,
				[
					'GEHomepageDefaultVariant' => 'Foo',
					'GEHomepageNewAccountVariantsByPlatform' => []
				]
			),
			$userOptionsLookupMock
		)->getVariant( $user ) );
	}

	/**
	 * @covers ::getVariant
	 */
	public function testGetVariantWithUserAssigned() {
		$user1 = new UserIdentityValue( 1, __CLASS__ );
		$user2 = new UserIdentityValue( 2, __CLASS__ );
		$userOptionsLookupMock = $this->createMock( UserOptionsLookup::class );
		$userOptionsLookupMock->method( 'getOption' )
			->with( $this->anything(), VariantHooks::USER_PREFERENCE )
			->willReturnCallback( static function ( UserIdentity $user, string $optionName ) {
				return [
					1 => VariantHooks::VARIANT_CONTROL,
					2 => VariantHooks::VARIANT_CONTROL
				][$user->getId()];
			} );
		$experimentUserManager = $this->getExperimentUserManager(
			new ServiceOptions(
				ExperimentUserManager::CONSTRUCTOR_OPTIONS,
				[
					'GEHomepageDefaultVariant' => 'Foo',
					'GEHomepageNewAccountVariantsByPlatform' => []
				]
			),
			$userOptionsLookupMock
		);

		$this->assertEquals( VariantHooks::VARIANT_CONTROL,
			$experimentUserManager->getVariant( $user1 ) );
		$this->assertEquals( VariantHooks::VARIANT_CONTROL,
			$experimentUserManager->getVariant( $user2 ) );
	}

	/**
	 * @covers ::getRandomVariant
	 * @dataProvider variantAssignmentByPlatformProvider
	 */
	public function testVariantAssignmentByPlatform(
		string $message, string $expectedVariant, array $serviceOptions, ?string $platform = null
	) {
		$userOptionsLookupMock = $this->createMock( UserOptionsLookup::class );
		$userOptionsLookupMock->method( 'getOption' )
			->willReturn( '' );
		$this->assertEquals(
			$expectedVariant,
			$this->getExperimentUserManager(
				new ServiceOptions(
					ExperimentUserManager::CONSTRUCTOR_OPTIONS,
					$serviceOptions
				),
				$userOptionsLookupMock,
				$platform
			)->getRandomVariant(),
			$message
		);
	}

	/**
	 * @see ::testVariantAssignmentByPlatform
	 * @return array[]
	 */
	public static function variantAssignmentByPlatformProvider(): array {
		return [
			[
				'mobile: GEHomepageNewAccountVariantsByPlatform assigns control variant for mobile users.',
				'control',
				[
					'GEHomepageDefaultVariant' => 'control',
					'GEHomepageNewAccountVariantsByPlatform' => [
						'control' => [
							'desktop' => 100,
							'mobile' => 100
						]
					]
				],
				'mobile'
			],
			[
				'desktop: GEHomepageNewAccountVariantsByPlatform assigns control variant for desktop users',
				'control',
				[
					'GEHomepageDefaultVariant' => 'control',
					'GEHomepageNewAccountVariantsByPlatform' => [
						'control' => [
							'desktop' => 100,
							'mobile' => 100
						]
					]
				],
				'desktop'
			],
		];
	}

	private function getExperimentUserManager(
		ServiceOptions $options, UserOptionsLookup $lookup, ?string $platform = null
	): ExperimentUserManager {
		return new ExperimentUserManager(
			$options,
			$this->createMock( UserOptionsManager::class ),
			$lookup,
			$platform
		);
	}
}
