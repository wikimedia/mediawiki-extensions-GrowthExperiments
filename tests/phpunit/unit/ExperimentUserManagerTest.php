<?php

namespace GrowthExperiments\Tests\Unit;

use GrowthExperiments\ExperimentUserManager;
use MediaWiki\Config\ServiceOptions;
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
		$user = new UserIdentityValue( 0, __CLASS__ );
		$userOptionsLookupMock = $this->getMockBuilder( UserOptionsLookup::class )
			->disableOriginalConstructor()
			->getMock();
		$userOptionsLookupMock->method( 'getOption' )
			->willReturn( 'D' );
		$this->assertEquals( 'D', $this->getExperimentUserManager(
			new ServiceOptions(
				[ 'GEHomepageDefaultVariant' ],
				[ 'GEHomepageDefaultVariant' => 'Foo' ]
			),
			$userOptionsLookupMock
		)->getVariant( $user ) );
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
