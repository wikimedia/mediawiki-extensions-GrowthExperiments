<?php

namespace GrowthExperiments\LevelingUp;

use GrowthExperiments\ExperimentUserManager;
use GrowthExperiments\HomepageHooks;
use GrowthExperiments\HomepageModules\SuggestedEdits;
use GrowthExperiments\NewcomerTasks\ConfigurationLoader\ConfigurationLoader;
use GrowthExperiments\NewcomerTasks\NewcomerTasksUserOptionsLookup;
use GrowthExperiments\NewcomerTasks\Task\TaskSet;
use GrowthExperiments\NewcomerTasks\Task\TaskSetFilters;
use GrowthExperiments\NewcomerTasks\TaskSuggester\TaskSuggesterFactory;
use GrowthExperiments\NewcomerTasks\TaskType\TaskType;
use GrowthExperiments\NewcomerTasks\TaskType\TaskTypeHandler;
use GrowthExperiments\UserImpact\UserImpactLookup;
use MediaWiki\Config\Config;
use MediaWiki\Config\ServiceOptions;
use MediaWiki\Context\RequestContext;
use MediaWiki\JobQueue\JobQueueGroup;
use MediaWiki\JobQueue\JobSpecification;
use MediaWiki\Storage\NameTableStore;
use MediaWiki\User\Options\UserOptionsLookup;
use MediaWiki\User\UserEditTracker;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserIdentityValue;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Wikimedia\Rdbms\IConnectionProvider;
use Wikimedia\Rdbms\IDBAccessObject;

/**
 * Manage the "leveling up" of a user, as the user progresses in completing suggested edit tasks.
 */
class LevelingUpManager {

	/**
	 * A JSON-encoded array containing task type IDs. If a task type ID is present in this array, it means that the
	 * user has opted out from receiving prompts to try new task types when they are working on the given task type.
	 * e.g. a user is working on a "copyedit" task type, if "copyedit" is present in this preference, then the user
	 * should not be prompted to try another task type.
	 */
	public const TASK_TYPE_PROMPT_OPT_OUTS_PREF = 'growthexperiments-levelingup-tasktype-prompt-optouts';
	public const CONSTRUCTOR_OPTIONS = [
		'GELevelingUpManagerTaskTypeCountThresholdMultiple',
		'GELevelingUpManagerInvitationThresholds',
		'GENewcomerTasksLinkRecommendationsEnabled',
		'GELevelingUpKeepGoingNotificationSendAfterSeconds',
		'GELevelingUpGetStartedNotificationSendAfterSeconds',
		'GELevelingUpNewNotificationsEnabled',
	];

	private ServiceOptions $options;
	private IConnectionProvider $connectionProvider;
	private NameTableStore $changeTagDefStore;
	private UserOptionsLookup $userOptionsLookup;
	private UserFactory $userFactory;
	private UserEditTracker $userEditTracker;
	private JobQueueGroup $jobQueueGroup;
	private ConfigurationLoader $configurationLoader;
	private UserImpactLookup $userImpactLookup;
	private TaskSuggesterFactory $taskSuggesterFactory;
	private NewcomerTasksUserOptionsLookup $newcomerTasksUserOptionsLookup;
	private ExperimentUserManager $experimentUserManager;
	private LoggerInterface $logger;
	private Config $growthConfig;
	private const KEEP_GOING_NOTIFICATION_THRESHOLD_MINIMUM = 1;

	public function __construct(
		ServiceOptions $options,
		IConnectionProvider $connectionProvider,
		NameTableStore $changeTagDefStore,
		UserOptionsLookup $userOptionsLookup,
		UserFactory $userFactory,
		UserEditTracker $userEditTracker,
		JobQueueGroup $jobQueueGroup,
		ConfigurationLoader $configurationLoader,
		UserImpactLookup $userImpactLookup,
		TaskSuggesterFactory $taskSuggesterFactory,
		NewcomerTasksUserOptionsLookup $newcomerTasksUserOptionsLookup,
		ExperimentUserManager $experimentUserManager,
		LoggerInterface $logger,
		Config $growthConfig
	) {
		$options->assertRequiredOptions( self::CONSTRUCTOR_OPTIONS );
		$this->options = $options;
		$this->connectionProvider = $connectionProvider;
		$this->changeTagDefStore = $changeTagDefStore;
		$this->userOptionsLookup = $userOptionsLookup;
		$this->userFactory = $userFactory;
		$this->userEditTracker = $userEditTracker;
		$this->jobQueueGroup = $jobQueueGroup;
		$this->configurationLoader = $configurationLoader;
		$this->userImpactLookup = $userImpactLookup;
		$this->taskSuggesterFactory = $taskSuggesterFactory;
		$this->newcomerTasksUserOptionsLookup = $newcomerTasksUserOptionsLookup;
		$this->experimentUserManager = $experimentUserManager;
		$this->logger = $logger;
		$this->growthConfig = $growthConfig;
	}

	/**
	 * Whether leveling up features are available. This does not include any checks based on
	 * the user's contribution history, just site configuration.
	 * @param UserIdentity $user
	 * @param Config $config Site configuration.
	 * @return bool
	 */
	public static function isEnabledForUser(
		UserIdentity $user,
		Config $config
	): bool {
		// Leveling up should only be shown if
		// 1) leveling-up (suggested-edits) features are enabled on this wiki
		//    (via SuggestedEdits::isEnabledForAnyone)
		// 2) the user's homepage is enabled
		//    (via HomepageHooks::isHomepageEnabled)
		return SuggestedEdits::isEnabledForAnyone( $config )
			&& HomepageHooks::isHomepageEnabled( $user );
	}

	/**
	 * Get the enabled task types, grouped by difficulty level.
	 *
	 * We use this method to assist in getting a list of task types ordered by difficulty level. We can't
	 * rely on the order in which they are returned by getTaskTypes(), because that is affected by the
	 * structure of the JSON on MediaWiki:NewcomerTasks.json
	 *
	 * @param string[] $taskTypesToGroup
	 * @return array<string,string[]>
	 */
	public function getTaskTypesGroupedByDifficulty( array $taskTypesToGroup ): array {
		$taskTypes = $this->configurationLoader->getTaskTypes();
		$taskTypesGroupedByDifficulty = [];
		foreach ( $taskTypesToGroup as $taskTypeId ) {
			$taskType = $taskTypes[$taskTypeId];
			$taskTypesGroupedByDifficulty[$taskType->getDifficulty()][] = $taskType->getId();
		}
		$difficultyNumeric = array_flip( TaskType::DIFFICULTY_NUMERIC );
		uksort( $taskTypesGroupedByDifficulty, static function ( $a, $b ) use ( $difficultyNumeric ) {
			return $difficultyNumeric[$a] - $difficultyNumeric[$b];
		} );
		return $taskTypesGroupedByDifficulty;
	}

	/**
	 * Get the list of enabled task types, in order from least difficult to most difficult ("easy" tasks
	 * first, then "medium", then "difficult")
	 *
	 * @param string[] $taskTypesToOrder
	 * @return string[]
	 */
	public function getTaskTypesOrderedByDifficultyLevel( array $taskTypesToOrder ): array {
		// Flatten the task types grouped by difficulty. They'll be ordered by easiest to most difficult.
		$taskTypes = [];
		foreach ( $this->getTaskTypesGroupedByDifficulty( $taskTypesToOrder ) as $taskTypeIds ) {
			$taskTypes = array_merge( $taskTypes, array_values( $taskTypeIds ) );
		}
		return $taskTypes;
	}

	/**
	 * Given an "active" task type ID (e.g. the task type associated with the article the user is about to edit),
	 * offer a suggestion for the next task type the user can try.
	 *
	 * The rules are:
	 *  - suggest a new task type if the currently active task type will have been completed by a multiple of
	 *    GELevelingUpManagerTaskTypeCountThresholdMultiple number of times; e.g. if the user has completed
	 *    4 copyedit tasks, and the active task type ID is copyedit, then we should prompt for a new task type. Same
	 *    rule if the user has completed 9 copyedit tasks.
	 *  - Only suggest task types that are known to have at least one candidate task available. This is expensive,
	 *    so avoid calling this method in the critical path.
	 *  - allow the user to opt out of receiving nudges for new task types, on a per task type
	 *    basis (e.g. if the user likes to do copyedit task, they can opt out of getting nudges for trying new tasks,
	 *    but if they switch to references, they would get a prompt to try another task type after the 5th reference
	 *    edit)
	 *
	 * @param UserIdentity $userIdentity
	 * @param string $activeTaskTypeId The task type ID of the task that the user is currently working on. Examples:
	 *  - the user clicked on a "copyedit" task type from Special:Homepage, then call this function with "copyedit" as
	 *    the active task type ID.
	 *  - the user just completed a newcomer task edit, and continues to edit the article in VisualEditor so there
	 *    is no page reload, call this function with "copyedit" as the active task type ID. One could do this via an
	 *    API call from ext.growthExperiments.suggestedEditSession in a post-edit hook on the client-side.
	 * @param bool $readLatest If user impact lookup should read from the primary database.
	 * @param string[]|null $availableTaskTypes
	 * @return string|null
	 */
	public function suggestNewTaskTypeForUser(
		UserIdentity $userIdentity,
		string $activeTaskTypeId,
		bool $readLatest = false,
		?array $availableTaskTypes = null,
		bool $skipThresholdMultiple = false
	): ?string {
		$flags = $readLatest ? IDBAccessObject::READ_LATEST : IDBAccessObject::READ_NORMAL;
		$userImpact = $this->userImpactLookup->getUserImpact( $userIdentity, $flags );
		if ( !$userImpact ) {
			$this->logger->error(
				'Unable to fetch next suggested task type for user {userId}; no user impact found.',
				[ 'userId' => $userIdentity->getId() ]
			);
			return null;
		}

		$editCountByTaskType = $userImpact->getEditCountByTaskType();
		$levelingUpTaskTypePromptOptOuts = $this->userOptionsLookup->getOption(
			$userIdentity,
			self::TASK_TYPE_PROMPT_OPT_OUTS_PREF,
			''
		);
		$levelingUpTaskTypePromptOptOuts = json_decode( $levelingUpTaskTypePromptOptOuts ?? '', true );
		// Safety check, in case a user mangled their preference through mis-using the user options API.
		if ( !is_array( $levelingUpTaskTypePromptOptOuts ) ) {
			$levelingUpTaskTypePromptOptOuts = [];
		}
		if ( in_array( $activeTaskTypeId, $levelingUpTaskTypePromptOptOuts ) ) {
			// User opted-out of receiving prompts to progress to another task type when on $activeTaskTypeId.
			return null;
		}
		$levelingUpThreshold = $this->options->get( 'GELevelingUpManagerTaskTypeCountThresholdMultiple' );
		if ( !$skipThresholdMultiple && ( $editCountByTaskType[$activeTaskTypeId] + 1 ) % $levelingUpThreshold !== 0 ) {
			// Only trigger this on every 5th edit of the task type.
			return null;
		}

		$taskTypes = $this->getTaskTypesOrderedByDifficultyLevel( $availableTaskTypes );
		// Remove the active task type from the candidates.
		$taskTypes = array_filter( $taskTypes, static fn ( $item ) => $item !== $activeTaskTypeId );
		// Find any task type that has fewer than GELevelingUpManagerTaskTypeCountThresholdMultiple completed
		// tasks, and offer it as the next task type.
		$taskSuggester = $this->taskSuggesterFactory->create();
		$topicFilters = $this->newcomerTasksUserOptionsLookup->getTopics( $userIdentity );
		$topicMatchMode = $this->newcomerTasksUserOptionsLookup->getTopicsMatchMode( $userIdentity );
		foreach ( $taskTypes as $candidateTaskTypeId ) {
			if ( $editCountByTaskType[$candidateTaskTypeId] < $levelingUpThreshold ) {
				// Validate that tasks exist for the task type (e.g. link-recommendation
				// may exist as a task type, but there are zero items available in the task pool)
				$suggestions = $taskSuggester->suggest(
					new UserIdentityValue( 0, 'LevelingUpManager' ),
					new TaskSetFilters( [ $candidateTaskTypeId ], $topicFilters, $topicMatchMode ),
					1,
					null,
					[ 'useCache' => false ]
				);
				if ( $suggestions instanceof TaskSet && $suggestions->count() ) {
					return $candidateTaskTypeId;
				}
			}
		}
		return null;
	}

	/**
	 * Whether to show the user an invitation to try out suggested edits, right after the user did
	 * a normal edit.
	 * We show an invitation if the user's mainspace edit count (after the edit) is in
	 * $wgGELevelingUpManagerInvitationThresholds, and they did not make any suggested edit yet.
	 * @param UserIdentity $userIdentity
	 * @return bool
	 */
	public function shouldInviteUserAfterNormalEdit( UserIdentity $userIdentity ): bool {
		$thresholds = $this->options->get( 'GELevelingUpManagerInvitationThresholds' );
		if ( !$thresholds ) {
			return false;
		}

		// Check total edit counts first, which is fast; don't bother checking users with many edits,
		// for some arbitrary definition of "many".
		// @phan-suppress-next-line PhanParamTooFewInternalUnpack
		$quickThreshold = 3 * max( ...$thresholds );
		if ( $this->userEditTracker->getUserEditCount( $userIdentity ) > $quickThreshold ) {
			return false;
		}

		$wasPosted = RequestContext::getMain()->getRequest()->wasPosted();
		$db = $wasPosted
			? $this->connectionProvider->getPrimaryDatabase()
			: $this->connectionProvider->getReplicaDatabase();

		$user = $this->userFactory->newFromUserIdentity( $userIdentity );
		$tagId = $this->changeTagDefStore->acquireId( TaskTypeHandler::NEWCOMER_TASK_TAG );
		$editCounts = $db->newSelectQueryBuilder()
			->table( 'revision' )
			->join( 'page', null, 'rev_page = page_id' )
			->leftJoin( 'change_tag', null, [ 'rev_id = ct_rev_id', 'ct_tag_id' => $tagId ] )
			->fields( [
				'article_edits' => 'COUNT(*)',
				'suggested_edits' => 'COUNT(ct_rev_id)',
				'last_edit_timestamp' => 'MAX(rev_timestamp)',
			] )
			->conds( [
				'rev_actor' => $user->getActorId(),
				'page_namespace' => NS_MAIN,
				// count deleted revisions for now
			] )
			// limit() not needed because of the edit count check above, and it would be somewhat
			// complicated to combine it with COUNT()
			->caller( __METHOD__ )
			->fetchRow();

		if ( $editCounts->suggested_edits > 0 ) {
			return false;
		}
		$articleEdits = (int)$editCounts->article_edits;
		if ( !$wasPosted && $editCounts->last_edit_timestamp < $db->timestamp( time() - 3 ) ) {
			// If the last edit was more than 3 seconds ago, we are probably not seeing the actual
			// last edit due to replication lag. 3 is chosen arbitrarily to be large enough to
			// account for slow saves and the VE reload, but small enough to account for the user
			// making edits in quick succession.
			$articleEdits++;
		}
		return in_array( $articleEdits, $thresholds, true );
	}

	/**
	 * Get the suggested edits count from the user's impact data.
	 *
	 * @param UserIdentity $userIdentity
	 * @return int
	 */
	public function getSuggestedEditsCount( UserIdentity $userIdentity ): int {
		$impact = $this->userImpactLookup->getUserImpact( $userIdentity );
		if ( !$impact ) {
			$this->logger->error(
				'Unable to fetch suggested edits count for user {userId}; no user impact found.',
				[
					'userId' => $userIdentity->getId(),
					'exception' => new RuntimeException,
				]
			);
			return 0;
		}
		return $impact->getNewcomerTaskEditCount();
	}

	/**
	 * Whether to send the keep going notification to a user.
	 *
	 * Note that this only checks the edit thresholds; the event should be enqueued
	 * at account creation time with a job release timestamp of 48 hours.
	 *
	 * @param UserIdentity $userIdentity
	 * @return bool
	 */
	public function shouldSendKeepGoingNotification( UserIdentity $userIdentity ): bool {
		$suggestedEditCount = $this->getSuggestedEditsCount( $userIdentity );
		return $suggestedEditCount >= self::KEEP_GOING_NOTIFICATION_THRESHOLD_MINIMUM
			&& $suggestedEditCount <= $this->growthConfig->get( 'GELevelingUpKeepGoingNotificationThresholdsMaximum' );
	}

	/**
	 * Whether to send the get started notification to a user.
	 *
	 * @param UserIdentity $userIdentity
	 * @return bool
	 */
	public function shouldSendGetStartedNotification( UserIdentity $userIdentity ): bool {
		if ( $this->options->get( 'GELevelingUpNewNotificationsEnabled' ) ) {
			return $this->userEditTracker->getUserEditCount( $userIdentity ) === 0;
		}
		$maxEdits = (int)$this->growthConfig->get( 'GELevelingUpGetStartedMaxTotalEdits' );
		return $this->getSuggestedEditsCount( $userIdentity ) === 0 &&
			$this->userEditTracker->getUserEditCount( $userIdentity ) < $maxEdits;
	}

	/**
	 * @param string $jobName Job name; use NotificationKeepGoingJob::JOB_NAME or
	 * NotificationGetStartedJob::JOB_NAME respectively
	 * @param UserIdentity $recepientUser
	 * @param ?int $delay After how many seconds should the notification be sent? Null to disable
	 * the notification altogether.
	 * @return bool Was the notification scheduled?
	 */
	private function scheduleNotificationJob(
		string $jobName,
		UserIdentity $recepientUser,
		?int $delay
	) {
		if ( $delay === null ) {
			$this->logger->debug(
				__METHOD__ . ' avoided sending {jobName} notification, feature is disabled',
				[ 'exception' => new \RuntimeException, 'jobName' => $jobName ],
			);
			return false;
		}

		$jobQueue = $this->jobQueueGroup->get( $jobName );
		if ( !$jobQueue->delayedJobsEnabled() ) {
			$this->logger->error(
				'Failed to schedule {jobName} with a delay, delayed jobs are not supported',
				[ 'exception' => new \RuntimeException, 'jobName' => $jobName ],
			);
			return false;
		}

		$this->jobQueueGroup->lazyPush(
			new JobSpecification( $jobName, [
				'userId' => $recepientUser->getId(),
				// Process the job X seconds after account creation
				'jobReleaseTimestamp' => (int)wfTimestamp() + $delay,
			] )
		);
		return true;
	}

	/**
	 * @param array<string, ?int>|int|null $delaySpecification Array of variant => delay (null
	 * for functionality disabled); default key is used in case no variant matches
	 * @param UserIdentity $user
	 * @return int|null The delay for the user
	 */
	private function getNotificationDelayForUser( $delaySpecification, UserIdentity $user ): ?int {
		if ( !is_array( $delaySpecification ) ) {
			// same delay for everyone
			return $delaySpecification;
		}

		$variantKey = $this->experimentUserManager->getVariant( $user );
		return array_key_exists( $variantKey, $delaySpecification )
			? $delaySpecification[$variantKey]
			: $delaySpecification['default'];
	}

	/**
	 * Schedule the keep going notification
	 *
	 * The caller is expected to check LevellingUpManager::isEnabledForUser, as appropriate.
	 *
	 * @param UserIdentity $user
	 * @return bool
	 */
	public function scheduleKeepGoingNotification( UserIdentity $user ): bool {
		return $this->scheduleNotificationJob(
			NotificationKeepGoingJob::JOB_NAME,
			$user,
			$this->getNotificationDelayForUser(
				$this->options->get( 'GELevelingUpKeepGoingNotificationSendAfterSeconds' ),
				$user
			)
		);
	}

	/**
	 * Schedule the getting started notification
	 *
	 * The caller is expected to check LevellingUpManager::isEnabledForUser, as appropriate.
	 *
	 * @param UserIdentity $user
	 * @return bool
	 */
	public function scheduleGettingStartedNotification( UserIdentity $user ): bool {
		return $this->scheduleNotificationJob(
			NotificationGetStartedJob::JOB_NAME,
			$user,
			$this->getNotificationDelayForUser(
				$this->options->get( 'GELevelingUpGetStartedNotificationSendAfterSeconds' ),
				$user
			)
		);
	}

}
