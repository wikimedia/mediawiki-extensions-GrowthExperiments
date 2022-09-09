<?php

namespace GrowthExperiments\Tests;

use GrowthExperiments\Config\Validation\StructuredMentorListValidator;
use GrowthExperiments\Mentorship\Provider\MentorProvider;
use InvalidArgumentException;
use MediaWiki\User\UserIdentityLookup;
use MediaWiki\User\UserIdentityValue;
use MediaWikiUnitTestCase;

/**
 * @coversDefaultClass \GrowthExperiments\Config\Validation\StructuredMentorListValidator
 */
class StructuredMentorListValidatorTest extends MediaWikiUnitTestCase {

	private function getValidator(): StructuredMentorListValidator {
		$userIdentityLookup = $this->createMock( UserIdentityLookup::class );
		$userIdentityLookup->method( 'getUserIdentityByUserId' )
			->willReturn( new UserIdentityValue( 123, 'Mentor' ) );
		return new StructuredMentorListValidator(
			$userIdentityLookup
		);
	}

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

		$validator = $this->getValidator();
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
		$validator = $this->getValidator();
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
	 * @covers ::validateMentorMessage
	 * @dataProvider validateMentorDataProvider
	 */
	public function testValidateMentor( array $mentorData, ?string $expectedError ) {
		$validator = $this->getValidator();
		$status = $validator->validate( [
			'Mentors' => [
				123 => $mentorData
			]
		] );
		if ( $expectedError === null ) {
			$this->assertTrue( $status->isGood() );
		} else {
			$this->assertFalse( $status->isGood() );
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
			'messageTooLong' => [
				'mentorData' => [
					'message' => str_repeat( 'a', MentorProvider::INTRO_TEXT_LENGTH + 1 ),
					'weight' => 2,
					'automaticallyAssigned' => true,
				],
				'expectedError' => 'growthexperiments-mentor-writer-error-message-too-long',
			],
		];
	}
}
