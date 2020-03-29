<?php

namespace GrowthExperiments\NewcomerTasks\TaskSuggester;

use StatusValue;

/**
 * Pseudo-factory for returning a pre-configured task suggester (not necessarily a
 * StaticTaskSuggester) or error. Intended for testing and local frontend development.
 *
 * To use it, register a MediaWikiServices hook along the lines of
 *
 *     $wgHooks['MediaWikiServices'][] = function ( MediaWikiServices $services ) {
 *         $services->redefineService( 'GrowthExperimentsTaskSuggesterFactory', function () {
 *             $taskType = new TaskType( 'copyedit', TaskType::DIFFICULTY_EASY );
 *             return new StaticTaskSuggesterFactory( [
 *                 new Task( $taskType, new TitleValue( NS_MAIN, 'Foo' ) ),
 *                 new Task( $taskType, new TitleValue( NS_MAIN, 'Bar' ) ),
 *             ] );
 *         } );
 *     };
 */
class StaticTaskSuggesterFactory extends TaskSuggesterFactory {

	/** @var TaskSuggester */
	private $taskSuggester;

	/**
	 * @param TaskSuggester|StatusValue|array $taskSuggester A TaskSuggester, an array of
	 *   suggestions to create a StaticTaskSuggester with, or an error to create an
	 *   ErrorForwardingTaskSuggester with.
	 */
	public function __construct( $taskSuggester ) {
		if ( $taskSuggester instanceof TaskSuggester ) {
			$this->taskSuggester = $taskSuggester;
		} elseif ( $taskSuggester instanceof StatusValue ) {
			$this->taskSuggester = $this->createError( $taskSuggester );
		} else {
			$this->taskSuggester = new StaticTaskSuggester( $taskSuggester );
		}
	}

	/** @inheritDoc */
	public function create() {
		return $this->taskSuggester;
	}

}
