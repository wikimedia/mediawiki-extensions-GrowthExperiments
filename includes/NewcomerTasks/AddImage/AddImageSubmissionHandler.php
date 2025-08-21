<?php

namespace GrowthExperiments\NewcomerTasks\AddImage;

use CirrusSearch\WeightedTagsUpdater;
use GrowthExperiments\NewcomerTasks\AbstractSubmissionHandler;
use GrowthExperiments\NewcomerTasks\AddImage\EventBus\EventGateImageSuggestionFeedbackUpdater;
use GrowthExperiments\NewcomerTasks\ImageRecommendationFilter;
use GrowthExperiments\NewcomerTasks\NewcomerTasksUserOptionsLookup;
use GrowthExperiments\NewcomerTasks\SubmissionHandler;
use GrowthExperiments\NewcomerTasks\Task\TaskSet;
use GrowthExperiments\NewcomerTasks\Task\TaskSetFilters;
use GrowthExperiments\NewcomerTasks\TaskSuggester\TaskSuggesterFactory;
use GrowthExperiments\NewcomerTasks\TaskType\ImageRecommendationBaseTaskType;
use GrowthExperiments\NewcomerTasks\TaskType\ImageRecommendationTaskType;
use GrowthExperiments\NewcomerTasks\TaskType\ImageRecommendationTaskTypeHandler;
use GrowthExperiments\NewcomerTasks\TaskType\SectionImageRecommendationTaskType;
use GrowthExperiments\NewcomerTasks\TaskType\SectionImageRecommendationTaskTypeHandler;
use GrowthExperiments\NewcomerTasks\TaskType\TaskType;
use MediaWiki\Deferred\DeferredUpdates;
use MediaWiki\Logging\ManualLogEntry;
use MediaWiki\Message\Message;
use MediaWiki\Page\ProperPageIdentity;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserIdentityUtils;
use StatusValue;
use Wikimedia\Assert\Assert;
use Wikimedia\Message\ListType;
use Wikimedia\ObjectCache\WANObjectCache;

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
		'notrelevant',
		'sectionnotappropriate',
		'noinfo',
		'offensive',
		'lowquality',
		'unfamiliar',
		'foreignlanguage',
		'other'
	];
	/**
	 * Rejection reasons which means the user is undecided (as opposed thinking the image is bad).
	 * Should be a subset of REJECTION_REASONS.
	 */
	private const REJECTION_REASONS_UNDECIDED = [ 'unfamiliar', 'foreignlanguage' ];

	private const LOG_SUBTYPES = [
		ImageRecommendationTaskTypeHandler::TASK_TYPE_ID => 'addimage',
		SectionImageRecommendationTaskTypeHandler::TASK_TYPE_ID => 'addsectionimage',
	];

	private ?WeightedTagsUpdater $weightedTagsUpdater;
	private TaskSuggesterFactory $taskSuggesterFactory;
	private NewcomerTasksUserOptionsLookup $newcomerTasksUserOptionsLookup;
	private WANObjectCache $cache;
	private UserIdentityUtils $userIdentityUtils;

	private ?EventGateImageSuggestionFeedbackUpdater $eventGateImageFeedbackUpdater;

	public function __construct(
		?WeightedTagsUpdater $weightedTagsUpdater,
		TaskSuggesterFactory $taskSuggesterFactory,
		NewcomerTasksUserOptionsLookup $newcomerTasksUserOptionsLookup,
		WANObjectCache $cache,
		UserIdentityUtils $userIdentityUtils,
		?EventGateImageSuggestionFeedbackUpdater $eventGateImageFeedbackUpdater
	) {
		$this->weightedTagsUpdater = $weightedTagsUpdater;
		$this->taskSuggesterFactory = $taskSuggesterFactory;
		$this->newcomerTasksUserOptionsLookup = $newcomerTasksUserOptionsLookup;
		$this->cache = $cache;
		$this->userIdentityUtils = $userIdentityUtils;
		$this->eventGateImageFeedbackUpdater = $eventGateImageFeedbackUpdater;
	}

	/** @inheritDoc */
	public function validate(
		TaskType $taskType, ProperPageIdentity $page, UserIdentity $user, ?int $baseRevId, array $data
	): StatusValue {
		Assert::parameterType( ImageRecommendationBaseTaskType::class, $taskType, '$taskType' );
		'@phan-var ImageRecommendationBaseTaskType $taskType';
		/** @var ImageRecommendationBaseTaskType $taskType */

		$userErrorMessage = self::getUserErrorMessage( $this->userIdentityUtils, $user );
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
		'@phan-var ImageRecommendationBaseTaskType $taskType';
		/** @var ImageRecommendationBaseTaskType $taskType */

		$status = $this->parseData( $taskType, $data );
		if ( !$status->isGood() ) {
			return $status;
		}
		[ $accepted, $reasons, $filename, $sectionTitle, $sectionNumber ] = $status->getValue();

		// Remove this image from being recommended in the future, unless it was rejected with
		// one of the "not sure" options.
		// NOTE: This has to check $accepted, because $reasons will be empty for accepted
		// suggested edits. Accepted edits need to be invalidated to account for possible
		// reverts, see T350598 for more details.
		if ( $accepted || array_diff( $reasons, self::REJECTION_REASONS_UNDECIDED ) ) {
			$this->invalidateRecommendation(
				$taskType,
				$page,
				$user->getId(),
				$accepted,
				$filename,
				$sectionTitle,
				$sectionNumber,
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
				&& isset( $qualityGateConfig[$taskType->getId()]['dailyCount'] )
				&& $qualityGateConfig[$taskType->getId()]['dailyCount']
					>= $taskType->getMaxTasksPerDay() - 1
			) {
				if ( $taskType instanceof ImageRecommendationTaskType ) {
					$warnings['geimagerecommendationdailytasksexceeded'] = true;
				} elseif ( $taskType instanceof SectionImageRecommendationTaskType ) {
					$warnings['gesectionimagerecommendationdailytasksexceeded'] = true;
				}
			}
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
		}

		$subType = self::LOG_SUBTYPES[ $taskType->getId() ];
		$logEntry = new ManualLogEntry( 'growthexperiments', $subType );
		$logEntry->setTarget( $page );
		$logEntry->setPerformer( $user );
		$logEntry->setParameters( [
			'4::section' => $sectionTitle,
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
					Message::listParam( self::REJECTION_REASONS, ListType::COMMA )
				);
			} elseif ( !in_array( $reason, self::REJECTION_REASONS, true ) ) {
				return StatusValue::newGood()->error(
					'apierror-growthexperiments-addimage-handler-reason-invaliditem',
					$reason,
					Message::listParam( self::REJECTION_REASONS, ListType::COMMA )
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

		// sectionTitle and sectionNumber are present in addimage and addsection image recommendation
		// data. Values will be null for addimage submissions.
		// See AddImageArticleTarget.prototype.invalidateRecommendation
		if ( !array_key_exists( 'sectionTitle', $data ) ) {
			return StatusValue::newGood()
				->error( 'apierror-growthexperiments-addimage-handler-section-title-missing' );
		}
		if ( !array_key_exists( 'sectionNumber', $data ) ) {
			return StatusValue::newGood()
				->error( 'apierror-growthexperiments-addimage-handler-section-number-missing' );
		}
		if ( $taskType instanceof ImageRecommendationTaskType ) {
			if ( $data['sectionTitle'] !== null ) {
				return StatusValue::newGood()->error(
					'apierror-growthexperiments-addimage-handler-section-title-wrongtype',
					gettype( $data['sectionTitle'] )
				);
			}
			if ( $data['sectionNumber'] !== null ) {
				return StatusValue::newGood()->error(
					'apierror-growthexperiments-addimage-handler-section-number-wrongtype',
					gettype( $data['sectionTitle'] )
				);
			}
		}
		if ( $taskType instanceof SectionImageRecommendationTaskType ) {
			if ( !is_string( $data['sectionTitle'] ) ) {
				return StatusValue::newGood()->error(
					'apierror-growthexperiments-addsectionimage-handler-section-title-wrongtype',
					gettype( $data['sectionTitle'] )
				);
			}
			if ( !is_int( $data['sectionNumber'] ) ) {
				return StatusValue::newGood()->error(
					'apierror-growthexperiments-addsectionimage-handler-section-number-wrongtype',
					gettype( $data['sectionTitle'] )
				);
			}
		}

		return StatusValue::newGood( [
			$data['accepted'],
			array_values( $data['reasons'] ),
			$data['filename'],
			$data['sectionTitle'],
			$data['sectionNumber'],
		] );
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
	 * @param string|null $sectionTitle Title of the section the suggestion is for
	 * @param int|null $sectionNumber Number of the section the suggestion is for
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
		?string $sectionTitle,
		?int $sectionNumber,
		array $rejectionReasons = []
	) {
		Assert::invariant(
			$this->weightedTagsUpdater !== null,
			'CirrusSearch is required if (Section-) Image Recommendations are enabled'
		);
		if ( $taskType->getId() === ImageRecommendationTaskTypeHandler::TASK_TYPE_ID ) {
			$this->weightedTagsUpdater->resetWeightedTags(
				$page, [ ImageRecommendationTaskTypeHandler::WEIGHTED_TAG_PREFIX ]
			);
		} elseif ( $taskType->getId() === SectionImageRecommendationTaskTypeHandler::TASK_TYPE_ID ) {
			$this->weightedTagsUpdater->resetWeightedTags(
				$page, [
					SectionImageRecommendationTaskTypeHandler::WEIGHTED_TAG_PREFIX,
					ImageRecommendationTaskTypeHandler::WEIGHTED_TAG_PREFIX
				]
			);
		}
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
				$sectionTitle,
				$sectionNumber,
				$rejectionReasons
			);
		}
	}

}
