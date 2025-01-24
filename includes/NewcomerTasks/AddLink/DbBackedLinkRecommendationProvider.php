<?php

declare( strict_types = 1 );

namespace GrowthExperiments\NewcomerTasks\AddLink;

use GrowthExperiments\NewcomerTasks\TaskType\LinkRecommendationTaskType;
use GrowthExperiments\NewcomerTasks\TaskType\TaskType;
use MediaWiki\Linker\LinkTarget;
use MediaWiki\Title\TitleFormatter;
use StatusValue;
use Wikimedia\Assert\Assert;

/**
 * A provider which reads the recommendation from the database. It is the caller's
 * responsibility to make sure the recommendation has been stored there (this is
 * usually done via refreshLinkRecommendations.php).
 *
 * Can fall back to a web service for convenience during debugging / local setups.
 */
class DbBackedLinkRecommendationProvider implements LinkRecommendationProvider {

	private LinkRecommendationStore $linkRecommendationStore;

	private ?LinkRecommendationProvider $fallbackProvider;

	private TitleFormatter $titleFormatter;

	/**
	 * @param LinkRecommendationStore $linkRecommendationStore
	 * @param LinkRecommendationProvider|null $fallbackProvider
	 * @param TitleFormatter $titleFormatter
	 */
	public function __construct(
		LinkRecommendationStore $linkRecommendationStore,
		?LinkRecommendationProvider $fallbackProvider,
		TitleFormatter $titleFormatter
	) {
		$this->linkRecommendationStore = $linkRecommendationStore;
		$this->fallbackProvider = $fallbackProvider;
		$this->titleFormatter = $titleFormatter;
	}

	/** @inheritDoc */
	public function get( LinkTarget $title, TaskType $taskType ) {
		Assert::parameterType( LinkRecommendationTaskType::class, $taskType, '$taskType' );
		// Task type parameters are assumed to be mostly static. Invalidating the recommendations
		// stored in the DB when the task type parameters change should be done manually
		// via revalidateLinkRecommendations.php.
		$linkRecommendation = $this->linkRecommendationStore->getByLinkTarget( $title );
		if ( !$linkRecommendation ) {
			if ( $this->fallbackProvider ) {
				$linkRecommendation = $this->fallbackProvider->get( $title, $taskType );
			} else {
				// This can happen due to race conditions - the search index update is late so the
				// user is sent to a task which has just been deleted from the DB. It could also be
				// caused by errors in updating the index, which are important to monitor. So make
				// this error non-fatal but track it via Util::STATSD_INCREMENTABLE_ERROR_MESSAGES.
				$linkRecommendation = StatusValue::newGood()->error( 'growthexperiments-addlink-notinstore',
					$this->titleFormatter->getPrefixedText( $title ) );
			}
		}
		return $linkRecommendation;
	}

}
