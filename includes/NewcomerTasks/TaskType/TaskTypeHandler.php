<?php

namespace GrowthExperiments\NewcomerTasks\TaskType;

use GrowthExperiments\NewcomerTasks\ConfigurationLoader\ConfigurationValidator;
use GrowthExperiments\NewcomerTasks\SubmissionHandler;
use GrowthExperiments\NewcomerTasks\Task\Task;
use GrowthExperiments\NewcomerTasks\TaskSuggester\SearchStrategy\SearchQuery;
use MediaWiki\Linker\LinkTarget;
use MediaWiki\Title\MalformedTitleException;
use MediaWiki\Title\TitleParser;
use SearchResult;
use StatusValue;

/**
 * A TaskTypeHandler is responsible for all the type-specific behavior of some TaskType
 * (or group of TaskTypes) such as constructing the TaskType object from configuration or
 * constructing the search query that corresponds to the TaskType.
 *
 * TaskTypeHandlers are identified by their can be obtained from the TaskTypeRegis
 */
abstract class TaskTypeHandler {

	/**
	 * Change tag used to track edits made via suggested edit tasks. Subbtasks might add
	 * or replace with more specific tags.
	 */
	public const NEWCOMER_TASK_TAG = 'newcomer task';

	/** @var ConfigurationValidator */
	protected $configurationValidator;
	/** @var TitleParser */
	private $titleParser;

	public function __construct( ConfigurationValidator $configurationValidator, TitleParser $titleParser ) {
		$this->configurationValidator = $configurationValidator;
		$this->titleParser = $titleParser;
	}

	/**
	 * Get the handler ID of this handler.
	 * This is mainly for internal use by TaskTypeHandlerRegistry.
	 * @return string
	 */
	abstract public function getId(): string;

	/**
	 * @return SubmissionHandler
	 */
	abstract public function getSubmissionHandler(): SubmissionHandler;

	/**
	 * Validate task configuration used by ConfigurationLoader.
	 * @param string $taskTypeId
	 * @param array $config
	 * @return StatusValue
	 * @see ::validateTaskTypeObject
	 */
	public function validateTaskTypeConfiguration( string $taskTypeId, array $config ): StatusValue {
		$status = StatusValue::newGood();

		if ( !isset( $config['excludedTemplates'] ) ) {
			$config['excludedTemplates'] = [];
		}
		foreach ( $config['excludedTemplates'] as $template ) {
			$this->validateTemplate( $template, $taskTypeId, $status );
		}

		if ( $status->isOK() ) {
			if ( !isset( $config['excludedCategories'] ) ) {
				$config['excludedCategories'] = [];
			}
			foreach ( $config['excludedCategories'] as $category ) {
				$this->validateCategory( $category, $taskTypeId, $status );
			}
		}

		return $status;
	}

	/**
	 * Attempt to parse a template title, return a failed status value on MalformedTitleException.
	 *
	 * @param mixed $template
	 * @param string $taskTypeId
	 * @param StatusValue $status
	 * @return StatusValue
	 */
	protected function validateTemplate( $template, string $taskTypeId, StatusValue $status ): StatusValue {
		if ( !is_string( $template ) ) {
			if ( !is_scalar( $template ) ) {
				$template = '[' . gettype( $template ) . ']';
			}
			return $status->fatal( 'growthexperiments-homepage-suggestededits-config-invalidtemplatetitle',
				$template, $taskTypeId );
		}
		try {
			$this->titleParser->parseTitle( $template, NS_TEMPLATE );
		} catch ( MalformedTitleException $e ) {
			$status->fatal( 'growthexperiments-homepage-suggestededits-config-invalidtemplatetitle',
				$template, $taskTypeId );
		}
		return $status;
	}

	/**
	 * Attempt to parse a category title, return a failed status value on MalformedTitleException.
	 *
	 * @param mixed $category
	 * @param string $taskTypeId
	 * @param StatusValue $status
	 * @return StatusValue
	 */
	protected function validateCategory( $category, string $taskTypeId, StatusValue $status ): StatusValue {
		if ( !is_string( $category ) ) {
			if ( !is_scalar( $category ) ) {
				$category = '[' . gettype( $category ) . ']';
			}
			return $status->fatal( 'growthexperiments-homepage-suggestededits-config-invalidcategorytitle',
				$category, $taskTypeId );
		}
		try {
			$this->titleParser->parseTitle( $category, NS_CATEGORY );
		} catch ( MalformedTitleException $e ) {
			$status->fatal( 'growthexperiments-homepage-suggestededits-config-invalidcategorytitle',
				$category, $taskTypeId );
		}
		return $status;
	}

	/**
	 * Validate a task object. This is a companion to validateTaskTypeConfiguration() - some
	 * validation requires a TaskType object (typically checking whether messages exist) but
	 * first we need to make sure the configuration is valid enough to create the object,
	 * and the two cannot be done in the same method due to inheritance.
	 * @param TaskType $taskType
	 * @return StatusValue
	 */
	public function validateTaskTypeObject( TaskType $taskType ): StatusValue {
		return $this->configurationValidator->validateTaskMessages( $taskType );
	}

	/**
	 * @param string $taskTypeId
	 * @param array $config Task type configuration. Caller is assumed to have checked it
	 *   with validateTaskTypeConfiguration().
	 * @return TaskType
	 * @note Subclasses overriding this method must set the handled ID of the task type.
	 */
	public function createTaskType( string $taskTypeId, array $config ): TaskType {
		$extraData = [ 'learnMoreLink' => $config['learnmore'] ?? null ];
		$taskType = new TaskType( $taskTypeId, $config['group'], $extraData );
		$taskType->setHandlerId( $this->getId() );
		return $taskType;
	}

	/**
	 * Get a CirrusSearch search term corresponding to this task.
	 *
	 * Task types extending this one must call this parent method to get exclusion search strings.
	 *
	 * @param TaskType $taskType
	 * @return string
	 */
	public function getSearchTerm( TaskType $taskType ): string {
		$searchTerm = '';
		$excludedTemplates = $taskType->getExcludedTemplates();
		if ( $excludedTemplates ) {
			// extra space added to facilitate concatenation
			$searchTerm .= '-hastemplate:' . Util::escapeSearchTitleList( $excludedTemplates ) . ' ';
		}
		$excludedCategories = $taskType->getExcludedCategories();
		if ( $excludedCategories ) {
			// extra space added to facilitate concatenation
			$searchTerm .= '-incategory:' . Util::escapeSearchTitleList( $excludedCategories ) . ' ';
		}
		return $searchTerm;
	}

	/**
	 * @param SearchQuery $query
	 * @param SearchResult $match
	 * @return Task
	 */
	public function createTaskFromSearchResult( SearchQuery $query, SearchResult $match ): Task {
		$taskType = $query->getTaskType();
		$topics = $query->getTopics();
		$task = new Task( $taskType, $match->getTitle() );
		if ( $topics ) {
			$task->setTopics( $topics );
		}

		return $task;
	}

	/**
	 * Get the list of change tags to apply to edits originating from this task type.
	 * @param string|null $taskType
	 * @return string[]
	 */
	public function getChangeTags( ?string $taskType = null ): array {
		return [ self::NEWCOMER_TASK_TAG ];
	}

	/**
	 * Get the task type ID based on the change tag associated with it.
	 *
	 * @param string $changeTagName
	 * @return string|null
	 */
	public function getTaskTypeIdByChangeTagName( string $changeTagName ): ?string {
		return null;
	}

	/**
	 * @param array $config
	 * @return LinkTarget[]
	 * @throws MalformedTitleException
	 */
	protected function parseExcludedTemplates( array $config ): array {
		$excludedTemplates = [];
		foreach ( $config['excludedTemplates'] ?? [] as $excludedTemplate ) {
			$excludedTemplates[] = $this->titleParser->parseTitle( $excludedTemplate, NS_TEMPLATE );
		}
		return $excludedTemplates;
	}

	/**
	 * @param array $config
	 * @return LinkTarget[]
	 * @throws MalformedTitleException
	 */
	protected function parseExcludedCategories( array $config ): array {
		$excludedCategories = [];
		foreach ( $config['excludedCategories'] ?? [] as $excludedCategory ) {
			$excludedCategories[] = $this->titleParser->parseTitle( $excludedCategory, NS_CATEGORY );
		}
		return $excludedCategories;
	}

}
