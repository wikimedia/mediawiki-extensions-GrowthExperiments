<?php

namespace GrowthExperiments\Tests;

use GrowthExperiments\Config\Validation\StructuredMentorListValidator;
use InvalidArgumentException;
use MediaWikiUnitTestCase;

/**
 * @coversDefaultClass \GrowthExperiments\Config\Validation\StructuredMentorListValidator
 */
class StructuredMentorListValidatorTest extends MediaWikiUnitTestCase {
	/**
	 * @param string $variable
	 * @param mixed $value
	 * @param string|null $expectException
	 * @dataProvider validateVariableDataProvider
	 * @covers ::validateVariable
	 */
	public function testValidateVariable(
		string $variable,
		$value,
		?string $expectException
	) {
		if ( $expectException ) {
			$this->expectException( InvalidArgumentException::class );
			$this->expectExceptionMessage( $expectException );
		} else {
			$this->expectNotToPerformAssertions();
		}

		$validator = new StructuredMentorListValidator();
		$validator->validateVariable( $variable, $value );
	}

	public function validateVariableDataProvider() {
		return [
			[
				'variable' => 'foo',
				'value' => 'bar',
				'expectException' => "Invalid variable foo configured in the mentor list"
			],
			[
				'variable' => 'Mentors',
				'value' => 'bar',
				'expectException' => null
			],
		];
	}

	/**
	 * @param array $data
	 * @param string|null $expectedError
	 * @covers ::validate
	 * @dataProvider validateDataProvider
	 */
	public function testValidate( array $data, ?string $expectedError ) {
		$validator = new StructuredMentorListValidator();
		$status = $validator->validate( $data );
		if ( $expectedError === null ) {
			$this->assertTrue( $status->isOK() );
		} else {
			$this->assertFalse( $status->isOK() );
			$this->assertTrue( $status->hasMessage( $expectedError ) );
		}
	}

	public function validateDataProvider() {
		return [
			'emptyOk' => [
				'data' => [
					'Mentors' => [],
				],
				'expectedError' => null,
			],
			'good' => [
				'data' => [
					'Mentors' => [
						123 => [
							'message' => null,
							'weight' => 2,
							'automaticallyAssigned' => true,
						]
					],
					'ManuallyAssignedMentors' => [],
				],
				'expectedError' => null,
			],
			'mentorListNotAnArray' => [
				'data' => [
					'Mentors' => 'bar'
				],
				'expectedError' => 'growthexperiments-mentor-list-datatype-mismatch'
			],
			'mentorListIncorrectArray' => [
				'data' => [
					'Mentors' => [
						'foo' => 'bar',
					]
				],
				'expectedError' => 'growthexperiments-mentor-list-datatype-mismatch'
			],
			'mentorListIncorrectArray2' => [
				'data' => [
					'Mentors' => [
						123 => 'bar',
					]
				],
				'expectedError' => 'growthexperiments-mentor-list-datatype-mismatch'
			],
		];
	}

	/**
	 * @param array $mentorData
	 * @param string|null $expectedError
	 * @covers ::validateMentor
	 * @dataProvider validateMentorDataProvider
	 */
	public function testValidateMentor( array $mentorData, ?string $expectedError ) {
		$validator = new StructuredMentorListValidator();
		$status = $validator->validate( [
			'Mentors' => [
				123 => $mentorData
			]
		] );
		if ( $expectedError === null ) {
			$this->assertTrue( $status->isOK() );
		} else {
			$this->assertFalse( $status->isOK() );
			$this->assertTrue( $status->hasMessage( $expectedError ) );
		}
	}

	public function validateMentorDataProvider() {
		return [
			'unexpectedKey' => [
				'mentorData' => [
					'foo' => 'bar',
					'message' => null,
					'weight' => 2,
					'automaticallyAssigned' => true,
				],
				'expectedError' => 'growthexperiments-mentor-list-unexpected-key-mentor',
			],
			'missingMessage' => [
				'mentorData' => [
					'weight' => 2
				],
				'expectedError' => 'growthexperiments-mentor-list-missing-key',
			],
			'missingWeight' => [
				'mentorData' => [
					'message' => 2
				],
				'expectedError' => 'growthexperiments-mentor-list-missing-key',
			],
			'malformedMessage' => [
				'mentorData' => [
					'message' => 123,
					'weight' => 2,
					'automaticallyAssigned' => true,
				],
				'expectedError' => 'growthexperiments-mentor-list-datatype-mismatch',
			],
			'messageNullOk' => [
				'mentorData' => [
					'message' => null,
					'weight' => 2,
					'automaticallyAssigned' => true,
				],
				'expectedError' => null,
			],
			'messageStringOk' => [
				'mentorData' => [
					'message' => 'foobar',
					'weight' => 2,
					'automaticallyAssigned' => false,
				],
				'expectedError' => null,
			],
			'weightNotAnInt' => [
				'mentorData' => [
					'message' => null,
					'weight' => 'foo',
					'automaticallyAssigned' => true,
				],
				'expectedError' => 'growthexperiments-mentor-list-datatype-mismatch',
			],
			'weightIntOk' => [
				'mentorData' => [
					'message' => null,
					'weight' => 2,
					'automaticallyAssigned' => true,
				],
				'expectedError' => null,
			],
		];
	}
}
