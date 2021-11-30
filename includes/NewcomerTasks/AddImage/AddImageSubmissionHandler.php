<?php

namespace GrowthExperiments\NewcomerTasks\AddImage;

use CirrusSearch\CirrusSearch;
use DeferredUpdates;
use GrowthExperiments\NewcomerTasks\AbstractSubmissionHandler;
use GrowthExperiments\NewcomerTasks\ConfigurationLoader\ConfigurationLoader;
use GrowthExperiments\NewcomerTasks\ImageRecommendationFilter;
use GrowthExperiments\NewcomerTasks\NewcomerTasksUserOptionsLookup;
use GrowthExperiments\NewcomerTasks\RecommendationSubmissionHandler;
use GrowthExperiments\NewcomerTasks\Task\TaskSet;
use GrowthExperiments\NewcomerTasks\TaskSuggester\TaskSuggesterFactory;
use GrowthExperiments\NewcomerTasks\TaskType\ImageRecommendationTaskType;
use GrowthExperiments\NewcomerTasks\TaskType\ImageRecommendationTaskTypeHandler;
use GrowthExperiments\NewcomerTasks\Tracker\TrackerFactory;
use ManualLogEntry;
use MediaWiki\Page\ProperPageIdentity;
use MediaWiki\User\UserIdentity;
use Message;
use StatusValue;
use WANObjectCache;

/**
 * Record the user's decision on the recommendations for a given page.
 * Creates a Special:Log entry and handles updating the search index.
 */
class AddImageSubmissionHandler extends AbstractSubmissionHandler implements RecommendationSubmissionHandler {

	/**
	 * List of valid reasons for rejecting an image. Keep in sync with
	 * RecommendedImageRejectionDialog.rejectionReasons.
	 */
	private const REJECTION_REASONS = [
		'notrelevant', 'noinfo', 'offensive', 'lowquality', 'unfamiliar', 'foreignlanguage', 'other'
	];
	/**
	 * Rejection reasons which means the user is undecided (as opposed thinking the image is bad).
	 * Should be a subset of REJECTION_REASONS.
	 */
	private const REJECTION_REASONS_UNDECIDED = [ 'unfamiliar', 'foreignlanguage' ];

	/** @var callable */
	private $cirrusSearchFactory;

	/** @var TaskSuggesterFactory */
	private $taskSuggesterFactory;

	/** @var NewcomerTasksUserOptionsLookup */
	private $newcomerTasksUserOptionsLookup;

	/** @var ConfigurationLoader */
	private $configurationLoader;

	/** @var TrackerFactory */
	private $trackerFactory;

	/** @var WANObjectCache */
	private $cache;

	/**
	 * @param callable $cirrusSearchFactory A factory method returning a CirrusSearch instance.
	 * @param TaskSuggesterFactory $taskSuggesterFactory
	 * @param NewcomerTasksUserOptionsLookup $newcomerTasksUserOptionsLookup
	 * @param ConfigurationLoader $configurationLoader
	 * @param TrackerFactory $trackerFactory
	 * @param WANObjectCache $cache
	 */
	public function __construct(
		callable $cirrusSearchFactory,
		TaskSuggesterFactory $taskSuggesterFactory,
		NewcomerTasksUserOptionsLookup $newcomerTasksUserOptionsLookup,
		ConfigurationLoader $configurationLoader,
		TrackerFactory $trackerFactory,
		WANObjectCache $cache
	) {
		$this->cirrusSearchFactory = $cirrusSearchFactory;
		$this->taskSuggesterFactory = $taskSuggesterFactory;
		$this->newcomerTasksUserOptionsLookup = $newcomerTasksUserOptionsLookup;
		$this->configurationLoader = $configurationLoader;
		$this->trackerFactory = $trackerFactory;
		$this->cache = $cache;
	}

	/** @inheritDoc */
	public function validate(
		ProperPageIdentity $page, UserIdentity $user, int $baseRevId, array $data
	): StatusValue {
		$userErrorMessage = $this->getUserErrorMessage( $user );
		if ( $userErrorMessage ) {
			return StatusValue::newGood()->error( $userErrorMessage );
		}
		$imageRecommendation = $this->configurationLoader->getTaskTypes()['image-recommendation'];
		if ( $data['accepted'] && $imageRecommendation instanceof ImageRecommendationTaskType ) {
			$minCaptionLength = $imageRecommendation->getMinimumCaptionCharacterLength();
			if ( strlen( trim( $data['caption'] ) ) < $minCaptionLength ) {
				return StatusValue::newGood()->error(
					'growthexperiments-addimage-caption-warning-tooshort',
					$minCaptionLength
				);
			}
		}
		return $this->parseData( $data );
	}

	/** @inheritDoc */
	public function handle(
		ProperPageIdentity $page, UserIdentity $user, int $baseRevId, ?int $editRevId, array $data
	): StatusValue {
		$status = $this->parseData( $data );
		if ( !$status->isGood() ) {
			return $status;
		}
		[ $accepted, $reasons ] = $status->getValue();
		$imageRecommendation = $this->configurationLoader->getTaskTypes()['image-recommendation'];
		$this->trackerFactory->setTaskTypeOverride( $imageRecommendation );

		// Remove this image from being recommended in the future, unless it was rejected with
		// one of the "not sure" options.
		if ( $accepted || array_diff( $reasons, self::REJECTION_REASONS_UNDECIDED ) ) {
			$this->invalidateRecommendation( $page );
		}

		$warnings = [];
		$taskSuggester = $this->taskSuggesterFactory->create();
		$taskSet = $taskSuggester->suggest(
			$user,
			$this->newcomerTasksUserOptionsLookup->getTaskTypeFilter( $user ),
			$this->newcomerTasksUserOptionsLookup->getTopicFilter( $user )
		);
		if ( $taskSet instanceof TaskSet ) {
			$imageRecommendation = $this->configurationLoader->getTaskTypes()['image-recommendation'] ?? null;
			$qualityGateConfig = $taskSet->getQualityGateConfig();
			if ( $imageRecommendation instanceof ImageRecommendationTaskType &&
				isset( $qualityGateConfig[ImageRecommendationTaskTypeHandler::TASK_TYPE_ID]['dailyCount'] ) &&
				$qualityGateConfig[ImageRecommendationTaskTypeHandler::TASK_TYPE_ID]['dailyCount'] >=
				$imageRecommendation->getMaxTasksPerDay() - 1 ) {
				$warnings['geimagerecommendationdailytasksexceeded'] = true;
			}
		}

		$logEntry = new ManualLogEntry( 'growthexperiments', 'addimage' );
		$logEntry->setTarget( $page );
		$logEntry->setPerformer( $user );
		$logEntry->setParameters( [
			'accepted' => $accepted,
		] );
		if ( $editRevId ) {
			// This has the side effect of the log entry getting tagged with all the change tags
			// the revision is getting tagged with. Overall, still preferable - the log entry is
			// not published to recent changes so its tags don't matter much.
			$logEntry->setAssociatedRevId( $editRevId );
		}
		$logId = $logEntry->insert();
		// Do not publish to recent changes, it would be pointless as this action cannot
		// be inspected or patrolled.
		$logEntry->publish( $logId, 'udp' );

		// Reduce the likelihood that the user encounters the task they were undecided about again.
		if ( in_array( $accepted, self::REJECTION_REASONS_UNDECIDED ) ) {
			// Refresh the user's TaskSet cache in a deferred update, since this can be kind of slow.
			DeferredUpdates::addCallableUpdate( static function () use ( $taskSuggester, $user, $taskSet ) {
				$taskSuggester->suggest(
					$user,
					$taskSet->getFilters()->getTaskTypeFilters(),
					$taskSet->getFilters()->getTopicFilters(),
					null,
					null,
					[ 'resetCache' => true ]
				);
			} );
		}
		return StatusValue::newGood( [ 'logId' => $logId, 'warnings' => $warnings ] );
	}

	/**
	 * Validate and parse Add Image data submitted through the VE save API.
	 * @param array $data
	 * @return StatusValue A status with [ $accepted, $reasons ] on success:
	 *   - $accepted (bool): true if the image was accepted, false if it was rejected
	 *   - $reasons (string[]): list of rejection reasons.
	 */
	private function parseData( array $data ): StatusValue {
		if ( !array_key_exists( 'accepted', $data ) ) {
			return StatusValue::newGood()
				->error( 'apierror-growthexperiments-addimage-handler-accepted-missing' );
		} elseif ( !is_bool( $data['accepted'] ) ) {
			return StatusValue::newGood()->error(
				'apierror-growthexperiments-addimage-handler-accepted-wrongtype',
				gettype( $data['accepted'] )
			);
		}

		if ( !array_key_exists( 'reasons', $data ) ) {
			return StatusValue::newGood()
				->error( 'apierror-growthexperiments-addimage-handler-reason-missing' );
		} elseif ( !is_array( $data['reasons'] ) ) {
			return StatusValue::newGood()->error(
				'apierror-growthexperiments-addimage-handler-reason-wrongtype',
				gettype( $data['reasons'] )
			);
		}
		foreach ( $data['reasons'] as $reason ) {
			if ( !is_string( $reason ) ) {
				return StatusValue::newGood()->error(
					'apierror-growthexperiments-addimage-handler-reason-invaliditem',
					'[' . gettype( $reason ) . ']',
					Message::listParam( self::REJECTION_REASONS, 'comma' )
				);
			} elseif ( !in_array( $reason, self::REJECTION_REASONS, true ) ) {
				return StatusValue::newGood()->error(
					'apierror-growthexperiments-addimage-handler-reason-invaliditem',
					$reason,
					Message::listParam( self::REJECTION_REASONS, 'comma' )
				);
			}
		}

		return StatusValue::newGood( [ $data['accepted'], array_values( $data['reasons'] ) ] );
	}

	/**
	 * Invalidate the recommendation for the specified page.
	 *
	 * This is used when the recommendation is accepted or rejected for decided reasons and when
	 * the recommendation is invalid (for example: no images remain after filtering,
	 * the article already has an image).
	 *
	 * @param ProperPageIdentity $page
	 *
	 * @see ApiInvalidateImageRecommendation::execute
	 */
	public function invalidateRecommendation( ProperPageIdentity $page ) {
		$imageRecommendation = $this->configurationLoader->getTaskTypes()['image-recommendation'];
		/** @var CirrusSearch $cirrusSearch */
		$cirrusSearch = ( $this->cirrusSearchFactory )();
		$cirrusSearch->resetWeightedTags( $page,
			ImageRecommendationTaskTypeHandler::WEIGHTED_TAG_PREFIX );
		// Mark the task as "invalid" in a temporary cache, until the weighted tags in the search
		// index are updated.
		$this->cache->set(
			ImageRecommendationFilter::makeKey(
				$this->cache,
				$imageRecommendation->getId(),
				$page->getDBkey()
			),
			true,
			$this->cache::TTL_MINUTE * 10
		);
	}

}
