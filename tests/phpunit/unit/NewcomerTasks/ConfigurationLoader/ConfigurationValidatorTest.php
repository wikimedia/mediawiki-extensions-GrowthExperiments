<?php

namespace GrowthExperiments\Tests;

use GrowthExperiments\NewcomerTasks\ConfigurationLoader\ConfigurationValidator;
use MediaWiki\Collation\CollationFactory;
use MediaWiki\Title\TitleParser;
use MediaWikiUnitTestCase;
use MessageLocalizer;
use StatusValue;

/**
 * @covers \GrowthExperiments\NewcomerTasks\ConfigurationLoader\ConfigurationValidator
 */
class ConfigurationValidatorTest extends MediaWikiUnitTestCase {

	public function testValidateIdentifier() {
		$validator = $this->getValidator();
		$this->assertGood( $validator->validateIdentifier( 'abc123-4' ) );
		$this->assertHasError( 'growthexperiments-homepage-suggestededits-config-invalidid',
			$validator->validateIdentifier( 'abc<tag>def' ) );
		$this->assertHasError( 'growthexperiments-homepage-suggestededits-config-invalidid',
			$validator->validateIdentifier( 'x y' ) );
	}

	public function testValidateTitle() {
		$validator = $this->getValidator();
		$this->assertHasError( 'growthexperiments-homepage-suggestededits-config-invalidtitle',
			$validator->validateTitle( 123 ) );
		$this->assertHasError( 'growthexperiments-homepage-suggestededits-config-invalidtitle',
			$validator->validateTitle( [ 123 ] ) );
	}

	public function testValidateRequiredField() {
		$validator = $this->getValidator();
		$this->assertHasError( 'growthexperiments-homepage-suggestededits-config-missingfield',
			$validator->validateRequiredField( 'foo', [ 'bar' => 'baz' ], 'x' ) );
		$this->assertGood( $validator->validateRequiredField( 'foo', [ 'foo' => 'bar' ], 'x' ) );
	}

	public function testValidateFieldIsArray() {
		$validator = $this->getValidator();
		$this->assertHasError( 'growthexperiments-homepage-suggestededits-config-missingfield',
			$validator->validateFieldIsArray( 'foo', [], 'x' ) );
		$this->assertHasError( 'growthexperiments-homepage-suggestededits-config-fieldarray',
			$validator->validateFieldIsArray( 'foo', [ 'foo' => 'bar' ], 'x' ) );
		$this->assertHasError( 'growthexperiments-homepage-suggestededits-config-fieldarray',
			$validator->validateFieldIsArray( 'foo', [ 'foo' => [ 'x' => 'y' ] ], 'x' ) );
		$this->assertHasError( 'growthexperiments-homepage-suggestededits-config-fieldarray',
			$validator->validateFieldIsArray( 'foo', [ 'foo' => [ 1 => 'y' ] ], 'x' ) );
		$this->assertGood( $validator->validateFieldIsArray( 'foo', [ 'foo' => [] ], 'x' ) );
		$this->assertGood( $validator->validateFieldIsArray( 'foo', [ 'foo' => [ 'x', 'y' ] ], 'x' ) );
	}

	public function testValidateArrayMaxSize() {
		$validator = $this->getValidator();
		$this->assertGood( $validator->validateArrayMaxSize( 3, [ 'x' ], 'x', 'y' ) );
		$this->assertGood( $validator->validateArrayMaxSize( 3, [ 'x', 'y', 'z' ], 'x', 'y' ) );
		$this->assertHasError( 'growthexperiments-homepage-suggestededits-config-arraymaxsize',
			$validator->validateArrayMaxSize( 3, [ 'w', 'x', 'y', 'z' ], 'x', 'y' ) );
	}

	/**
	 * @return ConfigurationValidator
	 */
	private function getValidator() {
		return new ConfigurationValidator(
			$this->createNoOpMock( MessageLocalizer::class, [ 'msg' ] ),
			$this->createNoOpMock( CollationFactory::class ),
			$this->createNoOpMock( TitleParser::class )
		);
	}

	private function assertGood( StatusValue $statusValue ) {
		$this->assertTrue( $statusValue->isGood(), "Expected good status, found " . $statusValue );
	}

	private function assertHasError( string $errorKey, StatusValue $statusValue ) {
		$this->assertTrue( $statusValue->hasMessage( $errorKey ), "Expected error $errorKey, found "
			. $statusValue );
	}

}
