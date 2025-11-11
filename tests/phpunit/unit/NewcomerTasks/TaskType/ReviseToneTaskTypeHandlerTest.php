<?php

declare( strict_types = 1 );

namespace GrowthExperiments\Tests\Unit;

use GrowthExperiments\NewcomerTasks\ConfigurationLoader\ConfigurationValidator;
use GrowthExperiments\NewcomerTasks\ReviseTone\SubpageReviseToneRecommendationProvider;
use GrowthExperiments\NewcomerTasks\TaskType\ReviseToneTaskType;
use GrowthExperiments\NewcomerTasks\TaskType\ReviseToneTaskTypeHandler;
use GrowthExperiments\NewcomerTasks\TaskType\TaskType;
use MediaWiki\Config\HashConfig;
use MediaWiki\Title\TitleParser;
use MediaWiki\Title\TitleValue;
use MediaWikiUnitTestCase;

/**
 * @covers \GrowthExperiments\NewcomerTasks\TaskType\ReviseToneTaskTypeHandler
 */
class ReviseToneTaskTypeHandlerTest extends MediaWikiUnitTestCase {
	public function testTaskType(): void {
		$sut = new ReviseToneTaskTypeHandler(
			$this->createNoOpMock( ConfigurationValidator::class ),
			$this->createNoOpMock( TitleParser::class ),
			$this->createNoOpMock( SubpageReviseToneRecommendationProvider::class ),
			new HashConfig( [
				'GEReviseToneParagraphScoreThreshold' => 0.8,
				'GEReviseToneOverrideSearchTerm' => '',
			] ),
		);

		$taskType = $sut->createTaskType(
			ReviseToneTaskTypeHandler::TASK_TYPE_ID,
			[ 'group' => TaskType::DIFFICULTY_EASY ]
		);

		$this->assertInstanceOf( ReviseToneTaskType::class, $taskType );
		$this->assertSame( 'revise-tone', $taskType->getHandlerId() );
	}

	public function testGetSearchTermProduction(): void {
		$config = new HashConfig( [
			'GEReviseToneParagraphScoreThreshold' => 0.75,
			'GEReviseToneOverrideSearchTerm' => '',
		] );
		$titleParserMock = $this->getTitleParserMock();
		$sut = new ReviseToneTaskTypeHandler(
			$this->createNoOpMock( ConfigurationValidator::class ),
			$titleParserMock,
			$this->createNoOpMock( SubpageReviseToneRecommendationProvider::class ),
			$config,
		);

		$ReviseToneTaskType = $sut->createTaskType(
			ReviseToneTaskTypeHandler::TASK_TYPE_ID,
			[
				'group' => TaskType::DIFFICULTY_EASY,
				'excludedTemplates' => [ 'template1', 'template2' ],
				'excludedCategories' => [ 'category1', 'category2' ],
			]
		);

		$actualSearchTerm = $sut->getSearchTerm( $ReviseToneTaskType );

		$expectedSearchTerms = [
			'-hastemplate:"template1|template2"',
			'-incategory:"category1|category2"',
			'lasteditdate:<now-24h',
			'creationdate:<today-90d',
			'hasrecommendation:tone>0.75 ',
		];
		$this->assertSame( implode( ' ', $expectedSearchTerms ), $actualSearchTerm );
	}

	public function testGetSearchTermBeta(): void {
		$config = new HashConfig( [
			'GEReviseToneOverrideSearchTerm' => 'hastemplate:peacock_inline',
		] );
		$titleParserMock = $this->getTitleParserMock();
		$sut = new ReviseToneTaskTypeHandler(
			$this->createNoOpMock( ConfigurationValidator::class ),
			$titleParserMock,
			$this->createNoOpMock( SubpageReviseToneRecommendationProvider::class ),
			$config,
		);

		$ReviseToneTaskType = $sut->createTaskType(
			ReviseToneTaskTypeHandler::TASK_TYPE_ID,
			[
				'group' => TaskType::DIFFICULTY_EASY,
				'excludedTemplates' => [ 'template1', 'template2' ],
				'excludedCategories' => [ 'category1', 'category2' ],
			]
		);

		$actualSearchTerm = $sut->getSearchTerm( $ReviseToneTaskType );

		$expectedSearchTerms = [
			'-hastemplate:"template1|template2"',
			'-incategory:"category1|category2"',
			'hastemplate:peacock_inline ',
		];
		$this->assertSame( implode( ' ', $expectedSearchTerms ), $actualSearchTerm );
	}

	public function getTitleParserMock(): TitleParser {
		$titleParserMock = $this->createNoOpMock( TitleParser::class, [ 'parseTitle' ] );
		$titleParserMock->method( 'parseTitle' )->willReturnCallback(
			static fn ( string $title, int $namespace ) => TitleValue::tryNew( $namespace, $title ),
		);
		return $titleParserMock;
	}
}
