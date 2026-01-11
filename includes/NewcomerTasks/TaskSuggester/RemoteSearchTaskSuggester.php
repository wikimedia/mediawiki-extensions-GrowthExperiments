<?php

namespace GrowthExperiments\NewcomerTasks\TaskSuggester;

use FauxSearchResultSet;
use GrowthExperiments\NewcomerTasks\NewcomerTasksUserOptionsLookup;
use GrowthExperiments\NewcomerTasks\TaskSuggester\SearchStrategy\SearchQuery;
use GrowthExperiments\NewcomerTasks\TaskSuggester\SearchStrategy\SearchStrategy;
use GrowthExperiments\NewcomerTasks\TaskType\TaskType;
use GrowthExperiments\NewcomerTasks\TaskType\TaskTypeHandlerRegistry;
use GrowthExperiments\NewcomerTasks\Topic\Topic;
use GrowthExperiments\Util;
use MediaWiki\Http\HttpRequestFactory;
use MediaWiki\Page\LinkBatchFactory;
use MediaWiki\Title\TitleFactory;

/**
 * Suggest edits based on searching a wiki (potentially a different one) via the API.
 * Mainly meant for testing and development; it can in theory be used in production but
 * it is less efficient than using SearchEngine internally.
 */
class RemoteSearchTaskSuggester extends SearchTaskSuggester {

	/** @var HttpRequestFactory */
	private $requestFactory;

	/** @var TitleFactory */
	private $titleFactory;

	/** @var string Remote API URL including api.php */
	private $apiUrl;

	/**
	 * @param TaskTypeHandlerRegistry $taskTypeHandlerRegistry
	 * @param SearchStrategy $searchStrategy
	 * @param NewcomerTasksUserOptionsLookup $newcomerTasksUserOptionsLookup
	 * @param LinkBatchFactory $linkBatchFactory
	 * @param HttpRequestFactory $requestFactory
	 * @param TitleFactory $titleFactory
	 * @param string $apiUrl Remote API URL including api.php
	 * @param TaskType[] $taskTypes
	 * @param Topic[] $topics
	 */
	public function __construct(
		TaskTypeHandlerRegistry $taskTypeHandlerRegistry,
		SearchStrategy $searchStrategy,
		NewcomerTasksUserOptionsLookup $newcomerTasksUserOptionsLookup,
		LinkBatchFactory $linkBatchFactory,
		HttpRequestFactory $requestFactory,
		TitleFactory $titleFactory,
		$apiUrl,
		array $taskTypes,
		array $topics
	) {
		parent::__construct( $taskTypeHandlerRegistry, $searchStrategy, $newcomerTasksUserOptionsLookup,
			$linkBatchFactory, $taskTypes, $topics );
		$this->requestFactory = $requestFactory;
		$this->titleFactory = $titleFactory;
		$this->apiUrl = $apiUrl;
	}

	/** @inheritDoc */
	protected function search(
		SearchQuery $query,
		int $limit,
		int $offset,
		bool $debug
	) {
		// We randomize the results so offsets are meaningless.
		// TODO use fixed random seed.
		$params = [
			'action' => 'query',
			'list' => 'search',
			'srsearch' => $query->getQueryString(),
			'srnamespace' => 0,
			'srlimit' => $limit,
			'srinfo' => 'totalhits',
			'srprop' => '',
			'srqiprofile' => $query->getRescoreProfile() ?? 'classic_noboostlinks',
			// Convenient for debugging. Production setups should use LocalSearchTaskSuggester anyway.
			'errorlang' => 'en',
		];
		// FIXME quick fix: don't randomize if we use morelike, seems to conflict
		if ( $query->getSort() ) {
			$params['srsort'] = $query->getSort();
		}
		$status = Util::getApiUrl( $this->requestFactory, $this->apiUrl, $params );
		if ( !$status->isOK() ) {
			return $status;
		}
		$data = $status->getValue();

		$results = [];
		foreach ( $data['query']['search'] ?? [] as $result ) {
			$title = $this->titleFactory->newFromText( $result['title'], $result['ns'] );
			if ( !$title ) {
				continue;
			}
			$results[] = $title;
		}
		$resultSet = new FauxSearchResultSet( $results, (int)$data['query']['searchinfo']['totalhits'] );

		if ( $debug ) {
			// Add Cirrus debug dump URLs which show the details of how the scores were calculated.
			$query->setDebugUrl( $this->apiUrl . '?' . wfArrayToCgi( $params, [
				'cirrusDumpResult' => 1,
				'cirrusExplain' => 'pretty',
			] ) );
		}

		return $resultSet;
	}

}
