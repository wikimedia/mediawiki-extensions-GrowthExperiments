<?php

namespace GrowthExperiments\Tests\Unit;

use GrowthExperiments\NewcomerTasks\ConfigurationLoader\ConfigurationValidator;
use MediaWiki\Collation\CollationFactory;
use MediaWikiUnitTestCase;
use MessageLocalizer;

/**
 * @covers \GrowthExperiments\NewcomerTasks\ConfigurationLoader\ConfigurationValidator
 */
class ConfigurationValidatorTest extends MediaWikiUnitTestCase {

	public function testValidateIdentifier() {
		$validator = $this->getValidator();
		$this->assertStatusGood( $validator->validateIdentifier( 'abc123-4' ) );
		$this->assertStatusError( 'growthexperiments-homepage-suggestededits-config-invalidid',
			$validator->validateIdentifier( 'abc<tag>def' ) );
		$this->assertStatusError( 'growthexperiments-homepage-suggestededits-config-invalidid',
			$validator->validateIdentifier( 'x y' ) );
	}

	public function testValidateRequiredField() {
		$validator = $this->getValidator();
		$this->assertStatusError( 'growthexperiments-homepage-suggestededits-config-missingfield',
			$validator->validateRequiredField( 'foo', [ 'bar' => 'baz' ], 'x' ) );
		$this->assertStatusGood( $validator->validateRequiredField( 'foo', [ 'foo' => 'bar' ],
			'x' ) );
	}

	public function testValidateFieldIsArray() {
		$validator = $this->getValidator();
		$this->assertStatusError( 'growthexperiments-homepage-suggestededits-config-missingfield',
			$validator->validateFieldIsArray( 'foo', [], 'x' ) );
		$this->assertStatusError( 'growthexperiments-homepage-suggestededits-config-fieldarray',
			$validator->validateFieldIsArray( 'foo', [ 'foo' => 'bar' ], 'x' ) );
		$this->assertStatusError( 'growthexperiments-homepage-suggestededits-config-fieldarray',
			$validator->validateFieldIsArray( 'foo', [ 'foo' => [ 'x' => 'y' ] ], 'x' ) );
		$this->assertStatusError( 'growthexperiments-homepage-suggestededits-config-fieldarray',
			$validator->validateFieldIsArray( 'foo', [ 'foo' => [ 1 => 'y' ] ], 'x' ) );
		$this->assertStatusGood( $validator->validateFieldIsArray( 'foo', [ 'foo' => [] ], 'x' ) );
		$this->assertStatusGood( $validator->validateFieldIsArray( 'foo', [ 'foo' => [ 'x', 'y' ] ],
			'x' ) );
	}

	public function testValidateArrayMaxSize() {
		$validator = $this->getValidator();
		$this->assertStatusGood( $validator->validateArrayMaxSize( 3, [ 'x' ], 'x', 'y' ) );
		$this->assertStatusGood( $validator->validateArrayMaxSize( 3, [ 'x', 'y', 'z' ], 'x',
			'y' ) );
		$this->assertStatusError( 'growthexperiments-homepage-suggestededits-config-arraymaxsize',
			$validator->validateArrayMaxSize( 3, [ 'w', 'x', 'y', 'z' ], 'x', 'y' ) );
	}

	/**
	 * @return ConfigurationValidator
	 */
	private function getValidator() {
		return new ConfigurationValidator(
			$this->createNoOpMock( MessageLocalizer::class, [ 'msg' ] ),
			$this->createNoOpMock( CollationFactory::class )
		);
	}

}
