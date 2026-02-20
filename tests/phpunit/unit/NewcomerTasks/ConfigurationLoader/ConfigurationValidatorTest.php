<?php

namespace GrowthExperiments\Tests\Unit;

use GrowthExperiments\NewcomerTasks\ConfigurationLoader\ConfigurationValidator;
use MediaWikiUnitTestCase;
use MessageLocalizer;

/**
 * @covers \GrowthExperiments\NewcomerTasks\ConfigurationLoader\ConfigurationValidator
 */
class ConfigurationValidatorTest extends MediaWikiUnitTestCase {

	public function testValidateArrayMaxSize() {
		$validator = $this->getValidator();
		$this->assertStatusGood( $validator->validateArrayMaxSize( 3, [ 'x' ], 'x', 'y' ) );
		$this->assertStatusGood( $validator->validateArrayMaxSize( 3, [ 'x', 'y', 'z' ], 'x',
			'y' ) );
		$this->assertStatusError( 'growthexperiments-homepage-suggestededits-config-arraymaxsize',
			$validator->validateArrayMaxSize( 3, [ 'w', 'x', 'y', 'z' ], 'x', 'y' ) );
	}

	private function getValidator(): ConfigurationValidator {
		return new ConfigurationValidator(
			$this->createNoOpMock( MessageLocalizer::class, [ 'msg' ] )
		);
	}

}
