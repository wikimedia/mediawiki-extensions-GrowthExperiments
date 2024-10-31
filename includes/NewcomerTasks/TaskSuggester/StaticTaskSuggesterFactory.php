<?php

namespace GrowthExperiments\NewcomerTasks\TaskSuggester;

use GrowthExperiments\NewcomerTasks\ConfigurationLoader\ConfigurationLoader;
use MediaWiki\Title\TitleFactory;
use StatusValue;

/**
 * Pseudo-factory for returning a pre-configured task suggester (not necessarily a
 * StaticTaskSuggester) or error. Intended for testing and local frontend development.
 *
 * To use it, register a MediaWikiServices hook along the lines of
 *
 *     $wgHooks['MediaWikiServices'][] = function ( MediaWikiServices $services ) {
 *         $services->redefineService( 'GrowthExperimentsTaskSuggesterFactory', function () use ( $services ) {
 *             $taskType = new TaskType( 'copyedit', TaskType::DIFFICULTY_EASY );
 *             return new StaticTaskSuggesterFactory( [
 *                 new Task( $taskType, new TitleValue( NS_MAIN, 'Foo' ) ),
 *                 new Task( $taskType, new TitleValue( NS_MAIN, 'Bar' ) ),
 *             ], $services->getTitleFactory() );
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
	 * @param TitleFactory|null $titleFactory
	 */
	public function __construct( $taskSuggester, ?TitleFactory $titleFactory = null ) {
		if ( $taskSuggester instanceof TaskSuggester ) {
			$this->taskSuggester = $taskSuggester;
		} elseif ( $taskSuggester instanceof StatusValue ) {
			$this->taskSuggester = $this->createError( $taskSuggester );
		} else {
			$this->taskSuggester = new StaticTaskSuggester( $taskSuggester, $titleFactory );
		}
	}

	/** @inheritDoc */
	public function create( ?ConfigurationLoader $customConfigurationLoader = null ) {
		return $this->taskSuggester;
	}

}
