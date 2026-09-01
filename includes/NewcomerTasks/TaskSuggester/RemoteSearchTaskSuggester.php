<?php
declare( strict_types = 1 );

namespace GrowthExperiments\NewcomerTasks\TaskSuggester;

use GrowthExperiments\NewcomerTasks\NewcomerTasksUserOptionsLookup;
use GrowthExperiments\NewcomerTasks\TaskSuggester\SearchStrategy\SearchQuery;
use GrowthExperiments\NewcomerTasks\TaskSuggester\SearchStrategy\SearchStrategy;
use GrowthExperiments\NewcomerTasks\TaskType\TaskType;
use GrowthExperiments\NewcomerTasks\TaskType\TaskTypeHandlerRegistry;
use GrowthExperiments\NewcomerTasks\Topic\Topic;
use GrowthExperiments\Util;
use MediaWiki\Http\HttpRequestFactory;
use MediaWiki\Page\LinkBatchFactory;
use MediaWiki\Search\FauxSearchResultSet;
use MediaWiki\Search\ISearchResultSet;
use MediaWiki\Status\StatusFormatter;
use MediaWiki\Title\TitleFactory;
use MediaWiki\Title\TitleParser;
use StatusValue;

/**
 * Suggest edits based on searching a wiki (potentially a different one) via the API.
 * Mainly meant for testing and development; it can in theory be used in production but
 * it is less efficient than using SearchEngine internally.
 */
class RemoteSearchTaskSuggester extends SearchTaskSuggester {

	/**
	 * @param TaskTypeHandlerRegistry $taskTypeHandlerRegistry
	 * @param SearchStrategy $searchStrategy
	 * @param NewcomerTasksUserOptionsLookup $newcomerTasksUserOptionsLookup
	 * @param LinkBatchFactory $linkBatchFactory
	 * @param StatusFormatter $statusFormatter
	 * @param TitleParser $titleParser
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
		StatusFormatter $statusFormatter,
		TitleParser $titleParser,
		private readonly HttpRequestFactory $requestFactory,
		private readonly TitleFactory $titleFactory,
		private readonly string $apiUrl,
		array $taskTypes,
		array $topics
	) {
		parent::__construct( $taskTypeHandlerRegistry, $searchStrategy, $newcomerTasksUserOptionsLookup,
			$linkBatchFactory, $statusFormatter, $titleParser, $taskTypes, $topics );
	}

	/** @inheritDoc */
	protected function search(
		SearchQuery $query,
		int $limit,
		int $offset,
		bool $debug
	): ISearchResultSet|StatusValue {
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
