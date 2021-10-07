<?php

namespace GrowthExperiments\Tests;

use Collation;
use GrowthExperiments\NewcomerTasks\ConfigurationLoader\ConfigurationValidator;
use MediaWikiUnitTestCase;
use MessageLocalizer;
use StatusValue;
use TitleParser;

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

	/**
	 * @return ConfigurationValidator
	 */
	private function getValidator() {
		$messageLocalizer = $this->createNoOpAbstractMock( MessageLocalizer::class, [ 'msg' ] );
		$collation = $this->createNoOpMock( Collation::class );
		$titleParser = $this->createNoOpAbstractMock( TitleParser::class );

		return new ConfigurationValidator( $messageLocalizer, $collation, $titleParser );
	}

	private function assertGood( StatusValue $statusValue ) {
		$this->assertTrue( $statusValue->isGood(), "Expected good status, found " . $statusValue );
	}

	private function assertHasError( string $errorKey, StatusValue $statusValue ) {
		$this->assertTrue( $statusValue->hasMessage( $errorKey ), "Expected error $errorKey, found "
			. $statusValue );
	}

}
