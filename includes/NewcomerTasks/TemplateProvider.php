<?php

namespace GrowthExperiments\NewcomerTasks;

use GrowthExperiments\NewcomerTasks\Task\Task;
use GrowthExperiments\NewcomerTasks\Task\TemplateBasedTask;
use LinkBatch;
use MediaWiki\Linker\LinkTarget;
use TitleFactory;
use TitleValue;
use Wikimedia\Rdbms\IDatabase;

/**
 * Provides template information about tasks (ie. which maintenance template the article is
 * based on).
 *
 * Articles that are suitable as newcomer tasks are identified based on the maintenance templates
 * they have, but for performance reasons it is typically done in a way that does not give any
 * information on which specific template (of all the ones belonging to the same task type)
 * has been found on the article. This class provides that extra information.
 */
class TemplateProvider {

	/** @var TitleFactory */
	public $titleFactory;

	/** @var IDatabase */
	private $db;

	/**
	 * @param TitleFactory $titleFactory
	 * @param IDatabase $dbr
	 */
	public function __construct( TitleFactory $titleFactory, IDatabase $dbr ) {
		$this->titleFactory = $titleFactory;
		$this->db = $dbr;
	}

	/**
	 * Fill the template information in the given tasks. Non-TemplateBasedTask tasks are
	 * accepted but ignored.
	 * @param Task[] $tasks
	 */
	public function fill( array $tasks ) : void {
		/** @var TemplateBasedTask[] $tasks */'@phan-var TemplateBasedTask[] $tasks';
		$tasks = array_filter( $tasks, function ( Task $task ) {
			return $task instanceof TemplateBasedTask;
		} );

		$taskTypeTemplates = [];
		$linkBatch = new LinkBatch();
		foreach ( $tasks as $task ) {
			$linkBatch->addObj( $task->getTitle() );
			$taskTypeId = $task->getTaskType()->getId();
			if ( !array_key_exists( $taskTypeId, $taskTypeTemplates ) ) {
				$taskTypeTemplates[$taskTypeId] = array_map( function ( LinkTarget $template ) {
					// We assume all maintenance templates are in the template namespace.
					return $template->getDBkey();
				}, $task->getTaskType()->getTemplates() );
			}
		}
		$linkBatch->execute();

		$tasksById = [];
		foreach ( $tasks as $task ) {
			$id = $this->titleFactory->newFromLinkTarget( $task->getTitle() )->getArticleID();
			if ( $id ) {
				$tasksById[$id] = $task;
			}
		}
		if ( !$tasksById ) {
			return;
		}

		// The query is not optimal; we could reduce the number of rows scanned by limiting
		// tl_title to those relevant to the specific task type, with something like
		// CASE WHEN page_id IN <type1 ids> THEN tl_title IN <type1 templates> WHEN ...
		// Likewise, we could avoid executing the LinkBatch by using LinkBatch::constructSet instead.
		// But that might get us in trouble with query size limits (also, the abstraction layer
		// does not support CASE) so at least for the first iteration we do the less performant
		// but simpler thing.
		// In theory this has the potential for scanning many (250 * number of all templates we use)
		// rows, but it's unlikely that many pages would have many different page issue templates.
		$res = $this->db->select(
			'templatelinks',
			[ 'tl_from', 'tl_title' ],
			[
				'tl_from' => array_keys( $tasksById ),
				// Again, we assume all maintenance templates are in the template namespace.
				'tl_namespace' => NS_TEMPLATE,
				'tl_title' => array_merge( ...array_values( $taskTypeTemplates ) ),
			],
			__METHOD__
		);
		foreach ( $res as $row ) {
			/** @var TemplateBasedTask $task */
			$task = $tasksById[$row->tl_from];
			if ( in_array( $row->tl_title, $taskTypeTemplates[$task->getTaskType()->getId()], true ) ) {
				$task->addTemplate( new TitleValue( NS_TEMPLATE, $row->tl_title ) );
			}
		}
	}

}
