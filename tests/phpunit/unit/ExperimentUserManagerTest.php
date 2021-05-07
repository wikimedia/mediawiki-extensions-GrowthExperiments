<?php

namespace GrowthExperiments\Tests\Unit;

use GrowthExperiments\ExperimentUserManager;
use GrowthExperiments\VariantHooks;
use MediaWiki\Config\ServiceOptions;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserIdentityValue;
use MediaWiki\User\UserOptionsLookup;
use MediaWiki\User\UserOptionsManager;
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
		$userOptionsLookupMock = $this->getMockBuilder( UserOptionsLookup::class )
			->disableOriginalConstructor()
			->getMock();
		$userOptionsLookupMock->method( 'getOption' )
			->willReturn( '' );
		$this->assertEquals( 'Foo', $this->getExperimentUserManager(
			new ServiceOptions(
				[ 'GEHomepageDefaultVariant' ],
				[ 'GEHomepageDefaultVariant' => 'Foo' ]
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
		$userOptionsLookupMock = $this->getMockBuilder( UserOptionsLookup::class )
			->disableOriginalConstructor()
			->getMock();
		$userOptionsLookupMock->method( 'getOption' )
			->with( $this->anything(), VariantHooks::USER_PREFERENCE )
			->willReturnCallback( static function ( UserIdentity $user, string $optionName ) {
				return [
					1 => VariantHooks::VARIANT_CONTROL,
					2 => VariantHooks::VARIANT_LINK_RECOMMENDATION_ENABLED,
				][$user->getId()];
			} );
		$experimentUserManager = $this->getExperimentUserManager(
			new ServiceOptions(
				[ 'GEHomepageDefaultVariant' ],
				[ 'GEHomepageDefaultVariant' => 'Foo' ]
			),
			$userOptionsLookupMock
		);

		$this->assertEquals( VariantHooks::VARIANT_CONTROL,
			$experimentUserManager->getVariant( $user1 ) );
		$this->assertEquals( VariantHooks::VARIANT_LINK_RECOMMENDATION_ENABLED,
			$experimentUserManager->getVariant( $user2 ) );
	}

	private function getExperimentUserManager(
		ServiceOptions $options, UserOptionsLookup $lookup
	) : ExperimentUserManager {
		return new ExperimentUserManager(
			$options,
			$this->getMockBuilder( UserOptionsManager::class )
				->disableOriginalConstructor()
				->getMock(),
			$lookup
		);
	}
}
