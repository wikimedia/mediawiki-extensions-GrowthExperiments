<?php

namespace GrowthExperiments\NewcomerTasks\AddLink;

use GrowthExperiments\NewcomerTasks\TaskType\LinkRecommendationTaskType;
use MediaWiki\Linker\LinkTarget;

class DbBackedLinkRecommendationProvider implements LinkRecommendationProvider {

	/** @var LinkRecommendationProvider */
	private $linkRecommendationProvider;

	/** @var LinkRecommendationStore */
	private $linkRecommendationStore;

	/**
	 * @param LinkRecommendationProvider $linkRecommendationProvider
	 * @param LinkRecommendationStore $linkRecommendationStore
	 */
	public function __construct(
		LinkRecommendationProvider $linkRecommendationProvider,
		LinkRecommendationStore $linkRecommendationStore
	) {
		$this->linkRecommendationProvider = $linkRecommendationProvider;
		$this->linkRecommendationStore = $linkRecommendationStore;
	}

	/** @inheritDoc */
	public function get( LinkTarget $title, LinkRecommendationTaskType $taskType ) {
		// Task type parameters are assumed to be mostly static. Invalidating the recommendations
		// stored in the DB when the task type parameters change is left to some (as of yet
		// unimplemented) manual mechanism.
		$linkRecommendation = $this->linkRecommendationStore->getByLinkTarget( $title );
		if ( !$linkRecommendation ) {
			$linkRecommendation = $this->linkRecommendationProvider->get( $title, $taskType );
			if ( $linkRecommendation instanceof LinkRecommendation ) {
				$this->linkRecommendationStore->insert( $linkRecommendation );
			}
		}
		return $linkRecommendation;
	}

}
