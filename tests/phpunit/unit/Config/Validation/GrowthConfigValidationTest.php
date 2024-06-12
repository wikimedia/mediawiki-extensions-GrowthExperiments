<?php

namespace GrowthExperiments\Tests\Unit;

use GrowthExperiments\Config\Validation\GrowthConfigValidation;
use MediaWikiUnitTestCase;

/**
 * @covers \GrowthExperiments\Config\Validation\GrowthConfigValidation
 */
class GrowthConfigValidationTest extends MediaWikiUnitTestCase {

	/**
	 * @dataProvider provideValidate
	 * @param array $data
	 * @param string|null $expectedError
	 */
	public function testValidate( array $data, $expectedError ) {
		$validation = new GrowthConfigValidation();
		$status = $validation->validate( $data );
		if ( $expectedError === null ) {
			$this->assertTrue( $status->isOK() );
		} else {
			$this->assertStatusError( $expectedError, $status );
		}
	}

	public static function provideValidate() {
		return [
			'good' => [
				'data' => [
					'GEHelpPanelReadingModeNamespaces' => [ 1, 2, 3 ],
					'GEHelpPanelHelpDeskTitle' => null,
				],
				'expectedError' => null,
			],
			'good2' => [
				'data' => [
					'GEHelpPanelHelpDeskTitle' => '',
				],
				'expectedError' => null,
			],
			'bad type for bool' => [
				'data' => [
					'GEHelpPanelHelpDeskPostOnTop' => 'foo',
				],
				'expectedError' => 'growthexperiments-config-validator-datatype-mismatch',
			],
			'bad type for string' => [
				'data' => [
					'GEHelpPanelHelpDeskTitle' => false,
				],
				'expectedError' => 'growthexperiments-config-validator-datatype-mismatch',
			],
			'bad type for int[]' => [
				'data' => [
					'GEHelpPanelReadingModeNamespaces' => 'foo',
				],
				'expectedError' => 'growthexperiments-config-validator-datatype-mismatch',
			],
			'bad type for int[] #2' => [
				'data' => [
					'GEHelpPanelReadingModeNamespaces' => [ 'foo', 'bar', 'baz' ],
				],
				'expectedError' => 'growthexperiments-config-validator-datatype-mismatch',
			],
		];
	}

}
