<?php

namespace GrowthExperiments\NewcomerTasks\AddLink;

use GrowthExperiments\NewcomerTasks\TaskType\LinkRecommendationTaskType;
use MediaWiki\Linker\LinkTarget;

/**
 * A provider which reads the recommendation from the database. It is the caller's
 * responsibility to make sure the recommendation has been stored there (this is
 * usually done via refreshLinkRecommendations.php).
 *
 * Can fall back to a web service for convenience during debugging / local setups.
 */
class DbBackedLinkRecommendationProvider implements LinkRecommendationProvider {

	/** @var LinkRecommendationStore */
	private $linkRecommendationStore;

	/** @var LinkRecommendationProvider|null */
	private $fallbackProvider;

	/**
	 * @param LinkRecommendationStore $linkRecommendationStore
	 * @param LinkRecommendationProvider|null $fallbackProvider
	 */
	public function __construct(
		LinkRecommendationStore $linkRecommendationStore,
		LinkRecommendationProvider $fallbackProvider = null
	) {
		$this->linkRecommendationStore = $linkRecommendationStore;
		$this->fallbackProvider = $fallbackProvider;
	}

	/** @inheritDoc */
	public function get( LinkTarget $title, LinkRecommendationTaskType $taskType ) {
		// Task type parameters are assumed to be mostly static. Invalidating the recommendations
		// stored in the DB when the task type parameters change is left to some (as of yet
		// unimplemented) manual mechanism.
		$linkRecommendation = $this->linkRecommendationStore->getByLinkTarget( $title );
		if ( !$linkRecommendation && $this->fallbackProvider ) {
			$linkRecommendation = $this->fallbackProvider->get( $title, $taskType );
		}
		return $linkRecommendation;
	}

}
