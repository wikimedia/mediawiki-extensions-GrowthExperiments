<?php

namespace GrowthExperiments\NewcomerTasks\TaskSuggester;

use FauxSearchResultSet;
use GrowthExperiments\NewcomerTasks\TaskType\TaskType;
use GrowthExperiments\Util;
use MediaWiki\Http\HttpRequestFactory;
use MediaWiki\Linker\LinkTarget;
use TitleFactory;

/**
 * Suggest edits based on searching a wiki via the API. Mainly meant for testing and
 * development; it can in theory be used in production but is less efficient than
 * using the search service internally.
 */
class RemoteSearchTaskSuggester extends SearchTaskSuggester {

	/** @var HttpRequestFactory */
	private $requestFactory;

	/** @var TitleFactory */
	private $titleFactory;

	/** @var string Remote API URL including api.php */
	private $apiUrl;

	/**
	 * @param HttpRequestFactory $requestFactory
	 * @param TitleFactory $titleFactory
	 * @param string $apiUrl Remote API URL including api.php
	 * @param TaskType[] $taskTypes
	 * @param LinkTarget[] $templateBlacklist
	 */
	public function __construct(
		HttpRequestFactory $requestFactory,
		TitleFactory $titleFactory,
		$apiUrl,
		array $taskTypes,
		array $templateBlacklist
	) {
		parent::__construct( $taskTypes, $templateBlacklist );
		$this->requestFactory = $requestFactory;
		$this->titleFactory = $titleFactory;
		$this->apiUrl = $apiUrl;
	}

	/** @inheritDoc */
	protected function search( $searchTerm, $limit, $offset ) {
		// We randomize the results so offsets are meaningless.
		// TODO use fixed random seed.
		$status = Util::getApiUrl( $this->requestFactory, $this->apiUrl, [
			'action' => 'query',
			'list' => 'search',
			'srsearch' => $searchTerm,
			'srnamespace' => 0,
			'srlimit' => $limit,
			'srinfo' => 'totalhits',
			'srprop' => '',
			'srsort' => 'random',
		] );
		if ( !$status->isOK() ) {
			return $status;
		}
		$data = $status->getValue();

		$results = [];
		foreach ( $data['query']['search'] ?? [] as $result ) {
			$results[] = $this->titleFactory->newFromText( $result['title'], $result['ns'] );
		}
		$resultSet = new FauxSearchResultSet( $results, (int)$data['query']['searchinfo']['totalhits'] );
		return $resultSet;
	}

}
