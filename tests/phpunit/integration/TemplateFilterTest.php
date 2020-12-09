<?php

namespace GrowthExperiments\Tests;

use GrowthExperiments\NewcomerTasks\Task\Task;
use GrowthExperiments\NewcomerTasks\Task\TaskSet;
use GrowthExperiments\NewcomerTasks\Task\TaskSetFilters;
use GrowthExperiments\NewcomerTasks\TaskType\TaskType;
use GrowthExperiments\NewcomerTasks\TaskType\TemplateBasedTaskType;
use GrowthExperiments\NewcomerTasks\TemplateFilter;
use MediaWiki\MediaWikiServices;

/**
 * @group Database
 * @coversDefaultClass \GrowthExperiments\NewcomerTasks\TemplateFilter
 */
class TemplateFilterTest extends \MediaWikiIntegrationTestCase {

	public function __construct( $name = null, array $data = [], $dataName = '' ) {
		parent::__construct( $name, $data, $dataName );
		$this->tablesUsed = array_merge(
			$this->tablesUsed,
			[ 'templatelinks' ]
		);
	}

	/**
	 * @covers ::filter
	 * FIXME: Convert to use a dataProvider.
	 */
	public function testFilter() {
		$kdoTemplate = $this->insertPage( 'Kdo?', 'Kdo?', NS_TEMPLATE );
		$kdyTemplate = $this->insertPage( 'Kdy?', 'Kdy?', NS_TEMPLATE );
		$wikifikovatTemplate = $this->insertPage( 'Wikifikovat', '', NS_TEMPLATE );
		$templates = [ $kdoTemplate['title'], $kdyTemplate['title'] ];
		$copyeditTaskType = new TemplateBasedTaskType( 'copyedit', TaskType::DIFFICULTY_EASY, [],
			$templates );
		$linksTaskType = new TemplateBasedTaskType( 'links', TaskType::DIFFICULTY_EASY, [], [
			$wikifikovatTemplate['title'] ] );
		$newTaskType = new TaskType( 'foo', TaskType::DIFFICULTY_EASY );
		$copyedit1 = $this->insertPage( 'Copyedit1', '{Kdo?} {Kdy?}' );
		$copyedit2 = $this->insertPage( 'Copyedit2', '{Kdy?}' );
		$links1 = $this->insertPage( 'Links1', '{Wikifikovat}' );
		$linksNontemplate = $this->insertPage( 'LinksNonTemplate', 'LinksNonTemplate' );
		$task1 = new Task( $copyeditTaskType, $copyedit1['title'] );
		$task2 = new Task( $copyeditTaskType, $copyedit2['title'] );
		$task3 = new Task( $linksTaskType, $links1['title'] );

		$taskSet = new TaskSet( [
			$task1,
			$task2,
			$task3,
			new Task( $newTaskType, $linksNontemplate['title'] )
		], 10, 5, new TaskSetFilters() );

		$templateFilter = new TemplateFilter(
			wfGetDB( DB_REPLICA ),
			MediaWikiServices::getInstance()->getTitleFactory(),
			MediaWikiServices::getInstance()->getLinkBatchFactory()
		);
		$filteredTaskSet = $templateFilter->filter( $taskSet );
		// Nothing is in templatelinks, so the only valid task should be the non-template based
		// task.
		$this->assertArrayEquals( [ 'LinksNonTemplate' ], array_map( function ( Task $task ) {
			return $task->getTitle()->getDBkey();
		}, iterator_to_array( $filteredTaskSet ) ) );

		// Insert data into template links with values we'd expect: the articles should be
		// associated with the templates that are in the source of the page.
		$rows = [
			[
				'tl_from' => $copyedit2['id'],
				'tl_namespace' => NS_TEMPLATE,
				'tl_title' => 'Kdy?'
			],
			[
				'tl_from' => $copyedit1['id'],
				'tl_namespace' => NS_TEMPLATE,
				'tl_title' => 'Kdo?'
			],
			[
				'tl_from' => $copyedit1['id'],
				'tl_namespace' => NS_TEMPLATE,
				'tl_title' => 'Kdy?',
			],
			[
				'tl_from' => $links1['id'],
				'tl_namespace' => NS_TEMPLATE,
				'tl_title' => 'Wikifikovat'
			]
		];
		$this->db->insert( 'templatelinks', $rows );
		$filteredTaskSet = $templateFilter->filter( $taskSet );
		$this->assertArrayEquals( [ 'Copyedit1', 'Copyedit2', 'Links1', 'LinksNonTemplate' ],
		array_map( function ( Task $task ) {
			return $task->getTitle()->getDBkey();
		}, iterator_to_array( $filteredTaskSet ) ) );

		// Remove an item from the templatelinks table, simulating a situation where the
		// cached taskset contains out of date info.
		$this->db->delete(
			'templatelinks',
			[
				'tl_from' => $links1['id'],
				'tl_namespace' => NS_TEMPLATE,
				'tl_title' => 'Wikifikovat'
			]
		);
		$filteredTaskSet = $templateFilter->filter( $taskSet );
		$this->assertArrayEquals( [ 'Copyedit1', 'Copyedit2', 'LinksNonTemplate' ],
			array_map( function ( Task $task ) {
				return $task->getTitle()->getDBkey();
			}, iterator_to_array( $filteredTaskSet ) ) );

		// Remove one template associated with a task but keep the other (copyedit1 has Kdo? and
		// Kdy? templates.
		$this->db->delete(
			'templatelinks',
			[
				'tl_from' => $copyedit1['id'],
				'tl_namespace' => NS_TEMPLATE,
				'tl_title' => 'Kdy?'
			]
		);
		$filteredTaskSet = $templateFilter->filter( $taskSet );
		$this->assertArrayEquals( [ 'Copyedit1', 'Copyedit2', 'LinksNonTemplate' ],
			array_map( function ( Task $task ) {
				return $task->getTitle()->getDBkey();
			}, iterator_to_array( $filteredTaskSet ) ) );
	}

}
