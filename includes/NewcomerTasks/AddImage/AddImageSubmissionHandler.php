<?php

namespace GrowthExperiments\NewcomerTasks\AddImage;

use CirrusSearch\CirrusSearch;
use DeferredUpdates;
use GrowthExperiments\NewcomerTasks\AbstractSubmissionHandler;
use GrowthExperiments\NewcomerTasks\AddImage\EventBus\EventGateImageSuggestionFeedbackUpdater;
use GrowthExperiments\NewcomerTasks\ImageRecommendationFilter;
use GrowthExperiments\NewcomerTasks\NewcomerTasksUserOptionsLookup;
use GrowthExperiments\NewcomerTasks\SubmissionHandler;
use GrowthExperiments\NewcomerTasks\Task\TaskSet;
use GrowthExperiments\NewcomerTasks\Task\TaskSetFilters;
use GrowthExperiments\NewcomerTasks\TaskSuggester\TaskSuggesterFactory;
use GrowthExperiments\NewcomerTasks\TaskType\ImageRecommendationBaseTaskType;
use GrowthExperiments\NewcomerTasks\TaskType\ImageRecommendationTaskTypeHandler;
use GrowthExperiments\NewcomerTasks\TaskType\TaskType;
use ManualLogEntry;
use MediaWiki\Page\ProperPageIdentity;
use MediaWiki\User\UserIdentity;
use Message;
use StatusValue;
use WANObjectCache;
use Wikimedia\Assert\Assert;

/**
 * Record the user's decision on the recommendations for a given page.
 * Creates a Special:Log entry and handles updating the search index.
 */
class AddImageSubmissionHandler extends AbstractSubmissionHandler implements SubmissionHandler {

	/**
	 * List of valid reasons for rejecting an image. Keep in sync with
	 * RecommendedImageRejectionDialog.rejectionReasons.
	 */
	public const REJECTION_REASONS = [
		'notrelevant', 'noinfo', 'offensive', 'lowquality', 'unfamiliar', 'foreignlanguage', 'other'
	];
	/**
	 * Rejection reasons which means the user is undecided (as opposed thinking the image is bad).
	 * Should be a subset of REJECTION_REASONS.
	 */
	private const REJECTION_REASONS_UNDECIDED = [ 'unfamiliar', 'foreignlanguage' ];

	/** @var callable */
	private $cirrusSearchFactory;

	private TaskSuggesterFactory $taskSuggesterFactory;
	private NewcomerTasksUserOptionsLookup $newcomerTasksUserOptionsLookup;
	private WANObjectCache $cache;

	private ?EventGateImageSuggestionFeedbackUpdater $eventGateImageFeedbackUpdater;

	/**
	 * @param callable $cirrusSearchFactory A factory method returning a CirrusSearch instance.
	 * @param TaskSuggesterFactory $taskSuggesterFactory
	 * @param NewcomerTasksUserOptionsLookup $newcomerTasksUserOptionsLookup
	 * @param WANObjectCache $cache
	 * @param EventGateImageSuggestionFeedbackUpdater|null $eventGateImageFeedbackUpdater
	 */
	public function __construct(
		callable $cirrusSearchFactory,
		TaskSuggesterFactory $taskSuggesterFactory,
		NewcomerTasksUserOptionsLookup $newcomerTasksUserOptionsLookup,
		WANObjectCache $cache,
		?EventGateImageSuggestionFeedbackUpdater $eventGateImageFeedbackUpdater
	) {
		$this->cirrusSearchFactory = $cirrusSearchFactory;
		$this->taskSuggesterFactory = $taskSuggesterFactory;
		$this->newcomerTasksUserOptionsLookup = $newcomerTasksUserOptionsLookup;
		$this->cache = $cache;
		$this->eventGateImageFeedbackUpdater = $eventGateImageFeedbackUpdater;
	}

	/** @inheritDoc */
	public function validate(
		TaskType $taskType, ProperPageIdentity $page, UserIdentity $user, ?int $baseRevId, array $data
	): StatusValue {
		Assert::parameterType( ImageRecommendationBaseTaskType::class, $taskType, '$taskType' );
		'@phan-var ImageRecommendationBaseTaskType $taskType';/** @var ImageRecommendationBaseTaskType $taskType */

		$userErrorMessage = $this->getUserErrorMessage( $user );
		if ( $userErrorMessage ) {
			return StatusValue::newGood()->error( $userErrorMessage );
		}

		return $this->parseData( $taskType, $data );
	}

	/** @inheritDoc */
	public function handle(
		TaskType $taskType, ProperPageIdentity $page, UserIdentity $user, ?int $baseRevId, ?int $editRevId, array $data
	): StatusValue {
		Assert::parameterType( ImageRecommendationBaseTaskType::class, $taskType, '$taskType' );
		'@phan-var ImageRecommendationBaseTaskType $taskType';/** @var ImageRecommendationBaseTaskType $taskType */

		$status = $this->parseData( $taskType, $data );
		if ( !$status->isGood() ) {
			return $status;
		}
		[ $accepted, $reasons, $filename ] = $status->getValue();

		// Remove this image from being recommended in the future, unless it was rejected with
		// one of the "not sure" options.
		if ( array_diff( $reasons, self::REJECTION_REASONS_UNDECIDED ) ) {
			$this->invalidateRecommendation(
				$taskType,
				$page,
				$user->getId(),
				$accepted,
				$filename,
				$reasons
			);
		}

		$warnings = [];
		$taskSuggester = $this->taskSuggesterFactory->create();
		$taskSet = $taskSuggester->suggest(
			$user,
			new TaskSetFilters(
				$this->newcomerTasksUserOptionsLookup->getTaskTypeFilter( $user ),
				$this->newcomerTasksUserOptionsLookup->getTopics( $user ),
				$this->newcomerTasksUserOptionsLookup->getTopicsMatchMode( $user )
			)
		);
		if ( $taskSet instanceof TaskSet ) {
			$qualityGateConfig = $taskSet->getQualityGateConfig();
			if ( $taskType instanceof ImageRecommendationBaseTaskType
				&& isset( $qualityGateConfig[ImageRecommendationTaskTypeHandler::TASK_TYPE_ID]['dailyCount'] )
				&& $qualityGateConfig[ImageRecommendationTaskTypeHandler::TASK_TYPE_ID]['dailyCount']
					>= $taskType->getMaxTasksPerDay() - 1
			) {
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
					$taskSet->getFilters(),
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
	 * @param ImageRecommendationBaseTaskType $taskType
	 * @param array $data
	 * @return StatusValue A status with [ $accepted, $reasons ] on success:
	 *   - $accepted (bool): true if the image was accepted, false if it was rejected
	 *   - $reasons (string[]): list of rejection reasons.
	 *   - $filename (string) The filename of the image suggestion
	 */
	private function parseData( ImageRecommendationBaseTaskType $taskType, array $data ): StatusValue {
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
		$recommendationAccepted = $data[ 'accepted' ] ?? false;
		if ( $recommendationAccepted ) {
			$minCaptionLength = $taskType->getMinimumCaptionCharacterLength();
			if ( strlen( trim( $data['caption'] ) ) < $minCaptionLength ) {
				return StatusValue::newGood()->error(
					'growthexperiments-addimage-caption-warning-tooshort',
					$minCaptionLength
				);
			}
		}

		return StatusValue::newGood( [ $data['accepted'], array_values( $data['reasons'] ), $data['filename'] ] );
	}

	/**
	 * Invalidate the recommendation for the specified page.
	 *
	 * This method will:
	 * - Reset the "hasrecommendation:image" weighted tag for the article, so the article is no longer returned in
	 *   search results for image suggestions.
	 * - Add the article to a short-lived cache, which ImageRecommendationFilter consults to decide if the article
	 *   should appear in the user's suggested edits queue on Special:Homepage or via the growthtasks API.
	 * - Generate and send an event to EventGate to the image-suggestion-feedback stream.
	 *
	 * @param ImageRecommendationBaseTaskType $taskType
	 * @param ProperPageIdentity $page
	 * @param int $userId
	 * @param null|bool $accepted True if accepted, false if rejected, null if invalidating for
	 * other reasons (e.g. image exists on page when user visits it)
	 * @param string $filename Unprefixed filename.
	 * @param string[] $rejectionReasons Reasons for rejecting the image.
	 * @throws \Exception
	 * @see ApiInvalidateImageRecommendation::execute
	 */
	public function invalidateRecommendation(
		ImageRecommendationBaseTaskType $taskType,
		ProperPageIdentity $page,
		int $userId,
		?bool $accepted,
		string $filename,
		array $rejectionReasons = []
	) {
		/** @var CirrusSearch $cirrusSearch */
		$cirrusSearch = ( $this->cirrusSearchFactory )();
		$cirrusSearch->resetWeightedTags( $page,
			ImageRecommendationTaskTypeHandler::WEIGHTED_TAG_PREFIX );

		// Mark the task as "invalid" in a temporary cache, until the weighted tags in the search
		// index are updated.
		$this->cache->set(
			ImageRecommendationFilter::makeKey(
				$this->cache,
				$taskType->getId(),
				$page->getDBkey()
			),
			true,
			$this->cache::TTL_MINUTE * 10
		);

		if ( $this->eventGateImageFeedbackUpdater ) {
			$this->eventGateImageFeedbackUpdater->update(
				$page->getId(),
				$userId,
				$accepted,
				$filename,
				$rejectionReasons
			);
		}
	}

}
