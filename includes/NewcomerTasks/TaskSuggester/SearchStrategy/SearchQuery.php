<?php

namespace GrowthExperiments\NewcomerTasks\TaskSuggester\SearchStrategy;

use GrowthExperiments\NewcomerTasks\TaskSuggester\UnderlinkedFunctionScoreBuilder;
use GrowthExperiments\NewcomerTasks\TaskType\TaskType;
use GrowthExperiments\NewcomerTasks\Topic\Topic;

/**
 * A search query string with some associated metadata about what it represents.
 * For now, a query is associated with a single task type and a single topic; this might
 * change in the future (see T238171#5870744).
 */
class SearchQuery {

	/** Sort option with custom handling for prioritizing underlinked articles. */
	public const RESCORE_UNDERLINKED = 'growth_underlinked';

	/** @var string */
	private $id;

	/** @var string */
	private $queryString;

	/** @var TaskType */
	private $taskType;

	/** @var Topic[] */
	private array $topics;

	/** @var string|null */
	private $sort;

	/** @var string|null */
	private $rescoreProfile;

	/** @var string|null */
	private $debugUrl;

	/**
	 * @param string $id Search ID. Used for internal purposes such as debugging or deduplication.
	 * @param string $queryString Search query string.
	 * @param TaskType $taskType Task type returned by the query.
	 * @param array{0:?Topic} $topics Topics associated to the query.
	 */
	public function __construct( string $id, string $queryString, TaskType $taskType, array $topics ) {
		$this->id = $id;
		$this->queryString = $queryString;
		$this->taskType = $taskType;
		$this->topics = array_filter( $topics );
	}

	/**
	 * Get a human-readable unique ID for this search query. This is used internally by the
	 * task suggester for deduplication and for debug logging.
	 */
	public function getId(): string {
		return $this->id;
	}

	/**
	 * Get the search query string represented by this object. This is a string suitable for
	 * passing to SearchEngine::searchText() or the srsearch parameter of the search API.
	 */
	public function getQueryString(): string {
		return $this->queryString;
	}

	/**
	 * Results from the search query will belong to this task type.
	 */
	public function getTaskType(): TaskType {
		return $this->taskType;
	}

	/**
	 * Results from the search query can belong to any of the topics requested. To get per-topic score
	 * or extra data from the search result T243478 needs to be resolved first.
	 * @return array Array of Topic(s) associated with the query
	 */
	public function getTopics(): array {
		return $this->topics;
	}

	/**
	 * Get the sort option to use for this query (for SearchEngine::setSort() / the srsort
	 * API parameter).
	 * @return string|null
	 */
	public function getSort(): ?string {
		return $this->sort;
	}

	/**
	 * @param string|null $sort
	 * @see ::getSort
	 */
	public function setSort( ?string $sort ): void {
		$this->sort = $sort;
	}

	/**
	 * Get the custom rescore profile to use.
	 * @return string|null
	 * @see UnderlinkedFunctionScoreBuilder
	 */
	public function getRescoreProfile(): ?string {
		return $this->rescoreProfile;
	}

	/**
	 * @param string $rescoreProfile
	 * @see ::getRescoreProfile
	 */
	public function setRescoreProfile( string $rescoreProfile ) {
		$this->rescoreProfile = $rescoreProfile;
	}

	/**
	 * Get debug URL for this query. This is an URL that will give detailed information about
	 * the search results and how they were scored.
	 * Note: this is only set after the search was performed, and it depends on the suggester
	 * and its debug settings whether it is set at all.
	 * @return string|null
	 */
	public function getDebugUrl(): ?string {
		return $this->debugUrl;
	}

	/**
	 * @param string|null $debugUrl
	 * @see ::getDebugUrl
	 */
	public function setDebugUrl( ?string $debugUrl ): void {
		$this->debugUrl = $debugUrl;
	}

}
