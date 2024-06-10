<?php

namespace GrowthExperiments\Tests\Unit;

use GrowthExperiments\NewcomerTasks\ConfigurationLoader\ConfigurationValidator;
use GrowthExperiments\NewcomerTasks\SubmissionHandler;
use GrowthExperiments\NewcomerTasks\TaskType\NullSubmissionHandler;
use GrowthExperiments\NewcomerTasks\TaskType\TaskTypeHandler;
use MediaWiki\Title\TitleParser;
use MediaWiki\Title\TitleValue;
use MediaWikiUnitTestCase;
use StatusValue;

/**
 * @covers \GrowthExperiments\NewcomerTasks\TaskType\TaskTypeHandler
 */
class TaskTypeHandlerTest extends MediaWikiUnitTestCase {

	public function testValidateTaskTypeConfiguration() {
		$taskTypeHandler = $this->getTaskTypeHandler();
		$this->assertStatusGood( $taskTypeHandler->validateTaskTypeConfiguration( 'test', [
			'group' => 'easy',
		] ) );
		foreach ( [
			'excludedTemplates' => 'invalidtemplatetitle',
			'excludedCategories' => 'invalidcategorytitle',
		  ] as $key => $error ) {
			$this->assertStatusGood( $taskTypeHandler->validateTaskTypeConfiguration( 'test', [
				'group' => 'easy',
				$key => [ 'Foo', 'Bar' ],
			] ) );
			$this->assertStatusError( "growthexperiments-homepage-suggestededits-config-$error",
				$taskTypeHandler->validateTaskTypeConfiguration( 'test', [
					'group' => 'easy',
					$key => [ 'Foo', 1 ],
				] ) );
		}
	}

	private function getTaskTypeHandler(): TaskTypeHandler {
		$configurationValidator = $this->createNoOpMock( ConfigurationValidator::class, [
			'validateIdentifier', 'validateRequiredField', 'validateFieldIsArray' ] );
		foreach ( [
			'validateIdentifier' => StatusValue::newGood(),
			'validateRequiredField' => StatusValue::newGood(),
			'validateFieldIsArray' => StatusValue::newGood(),
		] as $method => $returnValue ) {
			$configurationValidator->method( $method )->willReturn( $returnValue );
		}
		$titleParser = $this->createNoOpMock( TitleParser::class, [ 'parseTitle' ] );
		$titleParser->method( 'parseTitle' )->willReturnCallback(
			static function ( $title, $defaultNs = 0 ) {
				return new TitleValue( 0, $title );
			} );
		return new class( $configurationValidator, $titleParser ) extends TaskTypeHandler {
			public function getId(): string {
				return 'test';
			}

			public function getSubmissionHandler(): SubmissionHandler {
				return new NullSubmissionHandler();
			}
		};
	}

}
