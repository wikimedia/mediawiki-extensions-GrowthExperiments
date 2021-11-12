<?php

namespace GrowthExperiments\NewcomerTasks\AddImage;

use CirrusSearch\CirrusSearch;
use GrowthExperiments\NewcomerTasks\AbstractSubmissionHandler;
use GrowthExperiments\NewcomerTasks\ConfigurationLoader\ConfigurationLoader;
use GrowthExperiments\NewcomerTasks\NewcomerTasksUserOptionsLookup;
use GrowthExperiments\NewcomerTasks\RecommendationSubmissionHandler;
use GrowthExperiments\NewcomerTasks\Task\TaskSet;
use GrowthExperiments\NewcomerTasks\TaskSuggester\TaskSuggesterFactory;
use GrowthExperiments\NewcomerTasks\TaskType\ImageRecommendationTaskType;
use GrowthExperiments\NewcomerTasks\TaskType\ImageRecommendationTaskTypeHandler;
use ManualLogEntry;
use MediaWiki\Page\ProperPageIdentity;
use MediaWiki\User\UserIdentity;
use Message;
use StatusValue;

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
	/**
	 * @var NewcomerTasksUserOptionsLookup
	 */
	private $newcomerTasksUserOptionsLookup;
	/**
	 * @var ConfigurationLoader
	 */
	private $configurationLoader;

	/**
	 * @param callable $cirrusSearchFactory A factory method returning a CirrusSearch instance.
	 * @param TaskSuggesterFactory $taskSuggesterFactory
	 * @param NewcomerTasksUserOptionsLookup $newcomerTasksUserOptionsLookup
	 * @param ConfigurationLoader $configurationLoader
	 */
	public function __construct(
		callable $cirrusSearchFactory,
		TaskSuggesterFactory $taskSuggesterFactory,
		NewcomerTasksUserOptionsLookup $newcomerTasksUserOptionsLookup,
		ConfigurationLoader $configurationLoader
	) {
		$this->cirrusSearchFactory = $cirrusSearchFactory;
		$this->taskSuggesterFactory = $taskSuggesterFactory;
		$this->newcomerTasksUserOptionsLookup = $newcomerTasksUserOptionsLookup;
		$this->configurationLoader = $configurationLoader;
	}

	/** @inheritDoc */
	public function validate(
		ProperPageIdentity $page, UserIdentity $user, int $baseRevId, array $data
	): StatusValue {
		$userErrorMessage = $this->getUserErrorMessage( $user );
		if ( $userErrorMessage ) {
			return StatusValue::newGood()->error( $userErrorMessage );
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

		// Remove this image from being recommended in the future, unless it was rejected with
		// one of the "not sure" options.
		if ( $accepted || array_diff( $reasons, self::REJECTION_REASONS_UNDECIDED ) ) {
			/** @var CirrusSearch $cirrusSearch */
			$cirrusSearch = ( $this->cirrusSearchFactory )();
			$cirrusSearch->resetWeightedTags( $page,
				ImageRecommendationTaskTypeHandler::WEIGHTED_TAG_PREFIX );
		}

		$warnings = [];
		$taskSet = $this->taskSuggesterFactory->create()->suggest(
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

}
