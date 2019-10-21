<?php

namespace GrowthExperiments\NewcomerTasks\TaskSuggester;

use GrowthExperiments\NewcomerTasks\Task;
use GrowthExperiments\NewcomerTasks\TaskSet;
use GrowthExperiments\NewcomerTasks\TaskType\TaskType;
use GrowthExperiments\NewcomerTasks\TaskType\TemplateBasedTaskType;
use GrowthExperiments\Util;
use ISearchResultSet;
use MediaWiki\Linker\LinkTarget;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\User\UserIdentity;
use MultipleIterator;
// phpcs:ignore MediaWiki.Classes.UnusedUseStatement.UnusedUse
use SearchResult;
use StatusValue;

/**
 * Shared functionality for local and remote search.
 */
abstract class SearchTaskSuggester implements TaskSuggester {

	const DEFAULT_LIMIT = 200;

	/** @var TaskType[] id => TaskType */
	protected $taskTypes = [];

	/** @var LinkTarget[] List of templates which disqualify a page from being recommendable. */
	protected $templateBlacklist;

	/**
	 * @param TaskType[] $taskTypes
	 * @param LinkTarget[] $templateBlacklist
	 */
	public function __construct(
		array $taskTypes,
		array $templateBlacklist
	) {
		foreach ( $taskTypes as $taskType ) {
			$this->taskTypes[$taskType->getId()] = $taskType;
		}
		$this->templateBlacklist = $templateBlacklist;
	}

	/** @inheritDoc */
	public function suggest(
		UserIdentity $user,
		array $taskTypeFilter = null,
		array $topicFilter = null,
		$limit = null,
		$offset = null
	) {
		$taskTypeFilter = $taskTypeFilter ?? array_keys( $this->taskTypes );
		$limit = $limit ?? self::DEFAULT_LIMIT;
		// FIXME we are completely ignoring offset for now because 1) doing offsets when we are
		//   interleaving search results from multiple sources is hard, and 2) we are randomizing
		//   search results so offsets would not really be meaningful anyway.
		$offset = 0;

		$totalCount = 0;
		$matchIterator = new MultipleIterator( MultipleIterator::MIT_NEED_ANY |
			MultipleIterator::MIT_KEYS_ASSOC );
		foreach ( $taskTypeFilter as $taskTypeId ) {
			$taskType = $this->taskTypes[$taskTypeId] ?? null;
			if ( !$taskType ) {
				return StatusValue::newFatal( wfMessage( 'growthexperiments-newcomertasks-invalid-tasktype',
					$taskTypeId ) );
			} elseif ( !( $taskType instanceof TemplateBasedTaskType ) ) {
				LoggerFactory::getInstance( 'GrowthExperiments' )->notice(
					'Invalid task type: ' . get_class( $taskType ) );
				continue;
			}

			$searchTerm = $this->getSearchTerm( $user, $taskType, $topicFilter );
			$matches = $this->search( $searchTerm, $limit, $offset );
			if ( $matches instanceof StatusValue ) {
				return $matches;
			}

			$totalCount += $matches->getTotalHits();
			$matchIterator->attachIterator( Util::getIteratorFromTraversable( $matches ), $taskTypeId );
		}

		$taskCount = 0;
		$suggestions = [];
		foreach ( $matchIterator as $matchSlice ) {
			foreach ( array_filter( $matchSlice ) as $type => $match ) {
				// TODO: Filter out pages that are protected.
				/** @var $match SearchResult */
				$taskType = $this->taskTypes[$type];
				$suggestions[] = new Task( $taskType, $match->getTitle() );
				$taskCount++;
				if ( $taskCount >= $limit ) {
					break 2;
				}
			}
		}
		return new TaskSet( $suggestions, $totalCount, $offset );
	}

	/**
	 * @param UserIdentity $user
	 * @param TemplateBasedTaskType $taskType
	 * @param string[]|null $topicFilter
	 * @return string
	 */
	protected function getSearchTerm(
		UserIdentity $user,
		TemplateBasedTaskType $taskType,
		array $topicFilter = null
	) {
		// TODO make use of $user and $topicFilter
		$typeTerm = $this->getHasTemplateTerm( $taskType->getTemplates() );
		$deletionTerm = $this->templateBlacklist ?
			'-' . $this->getHasTemplateTerm( $this->templateBlacklist ) :
			'';

		return "$typeTerm $deletionTerm";
	}

	/**
	 * @param string $searchTerm
	 * @param int $limit
	 * @param int $offset
	 * @return ISearchResultSet|StatusValue Search results, or StatusValue on error.
	 */
	abstract protected function search( $searchTerm, $limit, $offset );

	/**
	 * @param LinkTarget[] $templates
	 * @return string
	 */
	private function getHasTemplateTerm( array $templates ) {
		return 'hastemplate:"' . implode( '|', array_map( function ( LinkTarget $template ) {
			return $template->getDBkey();
		}, $templates ) ) . '"';
	}

}
