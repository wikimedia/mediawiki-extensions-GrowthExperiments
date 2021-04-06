<?php

namespace GrowthExperiments\Tests;

use GrowthExperiments\Config\WikiPageConfigValidation;
use MediaWikiIntegrationTestCase;

/**
 * @covers \GrowthExperiments\Config\WikiPageConfigValidation
 */
class WikiPageConfigValidationTest extends MediaWikiIntegrationTestCase {

	/**
	 * @dataProvider provideValidate
	 * @param array $data
	 * @param string|null $expectedError
	 */
	public function testValidate( array $data, $expectedError ) {
		$validation = new WikiPageConfigValidation();
		$status = $validation->validate( $data );
		if ( $expectedError === null ) {
			$this->assertTrue( $status->isOK() );
		} else {
			$this->assertFalse( $status->isOK() );
			$this->assertTrue( $status->hasMessage( $expectedError ) );
		}
	}

	public function provideValidate() {
		return [
			'good' => [
				'data' => [
					'GEHelpPanelReadingModeNamespaces' => [ 1, 2, 3 ],
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
