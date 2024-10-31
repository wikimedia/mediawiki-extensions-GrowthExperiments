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
use StatusValue;
use Wikimedia\Rdbms\IConnectionProvider;
use Wikimedia\Stats\PrefixingStatsdDataFactoryProxy;

class NewcomerTasksChangeTagsManager {

	/** @var ConfigurationLoader */
	private $configurationLoader;
	/** @var RevisionLookup */
	private $revisionLookup;
	/** @var TaskTypeHandlerRegistry */
	private $taskTypeHandlerRegistry;
	/** @var UserOptionsLookup */
	private $userOptionsLookup;
	/** @var PrefixingStatsdDataFactoryProxy */
	private $perDbNameStatsdDataFactory;
	/** @var IConnectionProvider */
	private $connectionProvider;
	/** @var UserIdentityUtils */
	private $userIdentityUtils;
	/** @var Config|null */
	private $config;
	/** @var UserIdentity|null */
	private $user;

	private ChangeTagsStore $changeTagsStore;

	/**
	 * @param UserOptionsLookup $userOptionsLookup
	 * @param TaskTypeHandlerRegistry $taskTypeHandlerRegistry
	 * @param ConfigurationLoader $configurationLoader
	 * @param PrefixingStatsdDataFactoryProxy $perDbNameStatsdDataFactory
	 * @param RevisionLookup $revisionLookup
	 * @param IConnectionProvider $connectionProvider
	 * @param UserIdentityUtils $userIdentityUtils
	 * @param ChangeTagsStore $changeTagsStore
	 * @param Config|null $config
	 * @param UserIdentity|null $user
	 * FIXME $config and $user should be mandatory and injected by a factory
	 */
	public function __construct(
		UserOptionsLookup $userOptionsLookup,
		TaskTypeHandlerRegistry $taskTypeHandlerRegistry,
		ConfigurationLoader $configurationLoader,
		PrefixingStatsdDataFactoryProxy $perDbNameStatsdDataFactory,
		RevisionLookup $revisionLookup,
		IConnectionProvider $connectionProvider,
		UserIdentityUtils $userIdentityUtils,
		ChangeTagsStore $changeTagsStore,
		?Config $config = null,
		?UserIdentity $user = null
	) {
		$this->configurationLoader = $configurationLoader;
		$this->revisionLookup = $revisionLookup;
		$this->taskTypeHandlerRegistry = $taskTypeHandlerRegistry;
		$this->userOptionsLookup = $userOptionsLookup;
		$this->perDbNameStatsdDataFactory = $perDbNameStatsdDataFactory;
		$this->connectionProvider = $connectionProvider;
		$this->userIdentityUtils = $userIdentityUtils;
		$this->changeTagsStore = $changeTagsStore;
		$this->config = $config;
		$this->user = $user;
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
		$this->perDbNameStatsdDataFactory->increment(
			'GrowthExperiments.NewcomerTask.' . $taskTypeId . '.Save'
		);
		return StatusValue::newGood( $result );
	}

	/**
	 * @param int $revId
	 * @return StatusValue
	 */
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

	/**
	 * @param UserIdentity $userIdentity
	 * @return StatusValue
	 */
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
	 * @return StatusValue
	 */
	public function getTags( string $taskTypeId, UserIdentity $userIdentity ): StatusValue {
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
		return StatusValue::newGood( $tags );
	}

}
