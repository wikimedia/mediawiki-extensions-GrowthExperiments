<?php

namespace GrowthExperiments\NewcomerTasks;

use GrowthExperiments\HomepageModules\SuggestedEdits;
use GrowthExperiments\NewcomerTasks\ConfigurationLoader\ConfigurationLoader;
use GrowthExperiments\NewcomerTasks\TaskType\TaskType;
use GrowthExperiments\NewcomerTasks\TaskType\TaskTypeHandler;
use GrowthExperiments\NewcomerTasks\TaskType\TaskTypeHandlerRegistry;
use MediaWiki\ChangeTags\ChangeTagsStore;
use MediaWiki\Config\Config;
use MediaWiki\Context\RequestContext;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\Revision\RevisionLookup;
use MediaWiki\User\Options\UserOptionsLookup;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserIdentityUtils;
use MediaWiki\WikiMap\WikiMap;
use StatusValue;
use Wikimedia\Rdbms\IConnectionProvider;
use Wikimedia\Stats\StatsFactory;

class NewcomerTasksChangeTagsManager {
	public const SURFACED_CHANGE_TAG = 'newcomer task read view suggestion';

	/** @var ConfigurationLoader */
	private $configurationLoader;
	/** @var RevisionLookup */
	private $revisionLookup;
	/** @var TaskTypeHandlerRegistry */
	private $taskTypeHandlerRegistry;
	/** @var UserOptionsLookup */
	private $userOptionsLookup;
	/** @var IConnectionProvider */
	private $connectionProvider;
	/** @var UserIdentityUtils */
	private $userIdentityUtils;
	/** @var Config|null */
	private $config;
	/** @var UserIdentity|null */
	private $user;

	private ChangeTagsStore $changeTagsStore;
	private StatsFactory $statsFactory;

	/**
	 * @param UserOptionsLookup $userOptionsLookup
	 * @param TaskTypeHandlerRegistry $taskTypeHandlerRegistry
	 * @param ConfigurationLoader $configurationLoader
	 * @param RevisionLookup $revisionLookup
	 * @param IConnectionProvider $connectionProvider
	 * @param UserIdentityUtils $userIdentityUtils
	 * @param ChangeTagsStore $changeTagsStore
	 * @param StatsFactory $statsFactory
	 * @param Config|null $config
	 * @param UserIdentity|null $user
	 * FIXME $config and $user should be mandatory and injected by a factory
	 */
	public function __construct(
		UserOptionsLookup $userOptionsLookup,
		TaskTypeHandlerRegistry $taskTypeHandlerRegistry,
		ConfigurationLoader $configurationLoader,
		RevisionLookup $revisionLookup,
		IConnectionProvider $connectionProvider,
		UserIdentityUtils $userIdentityUtils,
		ChangeTagsStore $changeTagsStore,
		StatsFactory $statsFactory,
		?Config $config = null,
		?UserIdentity $user = null
	) {
		$this->configurationLoader = $configurationLoader;
		$this->revisionLookup = $revisionLookup;
		$this->taskTypeHandlerRegistry = $taskTypeHandlerRegistry;
		$this->userOptionsLookup = $userOptionsLookup;
		$this->connectionProvider = $connectionProvider;
		$this->userIdentityUtils = $userIdentityUtils;
		$this->changeTagsStore = $changeTagsStore;
		$this->config = $config;
		$this->user = $user;
		$this->statsFactory = $statsFactory;
	}

	/**
	 * Apply change tags to a newcomer task.
	 *
	 * Note that this should only be used with non-VisualEditor based edits. VE edits are handled via
	 * the onVisualEditorApiVisualEditorEditPreSave hook, which also allows for displaying the change
	 * tags in the RecentChanges feed.
	 *
	 * Also note that using this method will set the tags for display in article history but it will
	 * not appear in RecentChanges (T24509).
	 *
	 * @param string $taskTypeId
	 * @param int $revisionId
	 * @param UserIdentity $userIdentity
	 * @return StatusValue
	 */
	public function apply( string $taskTypeId, int $revisionId, UserIdentity $userIdentity ): StatusValue {
		$result = $this->getTags( $taskTypeId, $userIdentity );

		if ( !$result->isGood() ) {
			return $result;
		}
		$tags = $result->getValue();

		$revision = $this->revisionLookup->getRevisionById( $revisionId );
		if ( !$revision ) {
			return StatusValue::newFatal( $revisionId . ' is not a valid revision ID.' );
		}
		$revisionUserId = $revision->getUser()->getId();
		$authorityUserId = $userIdentity->getId();
		if ( $revisionUserId !== $authorityUserId ) {
			return StatusValue::newFatal(
				sprintf(
					'User ID %d on revision does not match logged-in user ID %d.', $revisionUserId, $authorityUserId
				)
			);
		}

		$result = $this->checkExistingTags( $revisionId );
		if ( !$result->isGood() ) {
			return $result;
		}

		$rc_id = null;
		$log_id = null;
		$result = $this->changeTagsStore->updateTags(
			$tags,
			null,
			$rc_id,
			$revisionId,
			$log_id,
			null,
			null,
			$userIdentity
		);
		LoggerFactory::getInstance( 'GrowthExperiments' )->debug(
			'ChangeTagsStore::updateTags() result in NewcomerTaskCompleteHandler: ' . json_encode( $result )
		);
		// This is needed for non-VE edits.
		// VE edits are incremented in the post-save VisualEditor hook.
		$statsAction = 'Save';
		$wiki = WikiMap::getCurrentWikiId();
		$this->statsFactory
			->withComponent( 'GrowthExperiments' )
			->getCounter( 'newcomertask_total' )
			->setLabel( 'taskType', $taskTypeId )
			->setLabel( 'wiki', $wiki )
			->setLabel( 'action', $statsAction )
			->copyToStatsdAt( "$wiki.GrowthExperiments.NewcomerTask." . $taskTypeId . '.' . $statsAction )
			->increment();

		return StatusValue::newGood( $result );
	}

	private function checkExistingTags( int $revId ): StatusValue {
		$rc_id = null;
		$log_id = null;
		$existingTags = $this->changeTagsStore->getTags(
			$this->connectionProvider->getReplicaDatabase(),
			$rc_id,
			$revId,
			$log_id
		);

		// Guard against duplicate submissions, or re-tagging older revisions.
		if ( in_array( TaskTypeHandler::NEWCOMER_TASK_TAG, $existingTags ) ) {
			return StatusValue::newFatal( 'Revision already has newcomer task tag.' );
		}
		return StatusValue::newGood();
	}

	private function checkUserAccess( UserIdentity $userIdentity ): StatusValue {
		if ( !$this->userIdentityUtils->isNamed( $userIdentity ) ) {
			return StatusValue::newFatal( 'You must be logged-in' );
		}
		if ( !$this->config || !$this->user ) {
			$ctx = RequestContext::getMain();
			$this->config = $ctx->getConfig();
			$this->user = $ctx->getUser();
		}
		if ( !SuggestedEdits::isEnabled( $this->config ) ||
			!SuggestedEdits::isActivated( $this->user, $this->userOptionsLookup )
		) {
			return StatusValue::newFatal( 'Suggested edits are not enabled or activated for your user.' );
		}
		return StatusValue::newGood();
	}

	/**
	 * @param string $taskTypeId
	 * @param UserIdentity $userIdentity
	 * @param bool $wasTaskSurfaced was the task surfaced in read-mode?
	 * @return StatusValue
	 */
	public function getTags(
		string $taskTypeId,
		UserIdentity $userIdentity,
		bool $wasTaskSurfaced = false
	): StatusValue {
		$result = $this->checkUserAccess( $userIdentity );
		if ( !$result->isGood() ) {
			return $result;
		}
		$taskType = $this->configurationLoader->getTaskTypes()[$taskTypeId] ?? null;
		if ( !$taskType instanceof TaskType ) {
			return StatusValue::newFatal( 'Invalid task type ID: ' . $taskTypeId );
		}
		$taskTypeHandler = $this->taskTypeHandlerRegistry->getByTaskType( $taskType );
		$tags = $taskTypeHandler->getChangeTags( $taskType->getId() );
		if ( $wasTaskSurfaced ) {
			$tags[] = self::SURFACED_CHANGE_TAG;
		}
		return StatusValue::newGood( $tags );
	}

}
