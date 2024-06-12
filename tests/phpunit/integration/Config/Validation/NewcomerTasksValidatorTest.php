<?php

namespace GrowthExperiments\Tests\Integration;

use GrowthExperiments\Config\Validation\NewcomerTasksValidator;
use GrowthExperiments\GrowthExperimentsServices;
use MediaWikiIntegrationTestCase;
use StatusValue;

/**
 * @covers \GrowthExperiments\Config\Validation\NewcomerTasksValidator
 */
class NewcomerTasksValidatorTest extends MediaWikiIntegrationTestCase {

	/**
	 * @dataProvider provideValidate
	 */
	public function testValidate( array $config, StatusValue $expectedStatus ) {
		$growthServices = GrowthExperimentsServices::wrap( $this->getServiceContainer() );
		$registry = $growthServices->getTaskTypeHandlerRegistry();
		$validator = new NewcomerTasksValidator( $registry );
		$status = $validator->validate( $config );
		$this->assertEquals( $expectedStatus, $status );
	}

	public static function provideValidate() {
		return [
			'empty' => [
				'config' => [],
				'expectedStatus' => StatusValue::newGood(),
			],
			'no handler' => [
				'config' => [
					'has-no-handler' => [
						'type' => 'no-such-handler',
					],
				],
				'expectedStatus' => StatusValue::newFatal(
					'growthexperiments-config-validator-newcomertasks-invalid-task-type-handler-id',
					'no-such-handler'
				),
			],
			'null' => [
				'config' => [
					'has-null-handler' => [
						'type' => 'null',
					],
				],
				'expectedStatus' => StatusValue::newFatal(
					'growthexperiments-homepage-suggestededits-config-nulltasktype'
				),
			],
			'no messages' => [
				'config' => [
					'unittest-custom' => [
						'group' => 'easy',
						'templates' => [ 'Template:Foo' ],
					],
				],
				'expectedStatus' => ( static function () {
					$status = StatusValue::newGood();
					foreach ( [ 'name', 'description', 'shortdescription', 'time' ] as $message ) {
						$status->fatal(
							'growthexperiments-homepage-suggestededits-config-missingmessage',
							"growthexperiments-homepage-suggestededits-tasktype-$message-unittest-custom",
							'unittest-custom'
						);
					}
					return $status;
				} )(),
			],
			'copyedit' => [
				'config' => [
					'copyedit' => [
						'group' => 'easy',
						'templates' => [ 'Template:Foo' ],
					],
				],
				'expectedStatus' => StatusValue::newGood(),
			],
		];
	}

}
