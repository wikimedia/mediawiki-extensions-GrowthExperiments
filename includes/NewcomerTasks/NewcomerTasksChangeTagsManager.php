<?php

namespace GrowthExperiments\NewcomerTasks;

use ChangeTags;
use GrowthExperiments\HomepageModules\SuggestedEdits;
use GrowthExperiments\NewcomerTasks\ConfigurationLoader\ConfigurationLoader;
use GrowthExperiments\NewcomerTasks\TaskType\TaskTypeHandler;
use GrowthExperiments\NewcomerTasks\TaskType\TaskTypeHandlerRegistry;
use IContextSource;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\Revision\RevisionLookup;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserOptionsLookup;
use PrefixingStatsdDataFactoryProxy;
use RequestContext;
use StatusValue;
use Wikimedia\Rdbms\IDatabase;
use Wikimedia\Rdbms\ILoadBalancer;

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
	/** @var IDatabase */
	private $dbr;
	/** @var IContextSource|null */
	private $requestContext;

	/**
	 * @param UserOptionsLookup $userOptionsLookup
	 * @param TaskTypeHandlerRegistry $taskTypeHandlerRegistry
	 * @param ConfigurationLoader $configurationLoader
	 * @param PrefixingStatsdDataFactoryProxy $perDbNameStatsdDataFactory
	 * @param RevisionLookup $revisionLookup
	 * @param ILoadBalancer $loadBalancer
	 * @param IContextSource|null $requestContext
	 */
	public function __construct(
		UserOptionsLookup $userOptionsLookup,
		TaskTypeHandlerRegistry $taskTypeHandlerRegistry,
		ConfigurationLoader $configurationLoader,
		PrefixingStatsdDataFactoryProxy $perDbNameStatsdDataFactory,
		RevisionLookup $revisionLookup,
		ILoadBalancer $loadBalancer,
		?IContextSource $requestContext = null
	) {
		$this->configurationLoader = $configurationLoader;
		$this->revisionLookup = $revisionLookup;
		$this->taskTypeHandlerRegistry = $taskTypeHandlerRegistry;
		$this->userOptionsLookup = $userOptionsLookup;
		$this->perDbNameStatsdDataFactory = $perDbNameStatsdDataFactory;
		$this->dbr = $loadBalancer->getConnectionRef( DB_REPLICA );
		$this->requestContext = $requestContext;
	}

	/**
	 * @param string $taskTypeId
	 * @param int $revisionId
	 * @param UserIdentity $userIdentity
	 * @return StatusValue
	 */
	public function apply( string $taskTypeId, int $revisionId, UserIdentity $userIdentity ): StatusValue {
		$result = $this->checkUserAccess( $userIdentity );
		if ( !$result->isGood() ) {
			return $result;
		}

		$taskType = $this->configurationLoader->getTaskTypes()[$taskTypeId] ?? null;
		if ( !$taskType ) {
			return StatusValue::newFatal( 'Invalid task type ID: ' . $taskTypeId );
		}

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

		$taskTypeHandler = $this->taskTypeHandlerRegistry->getByTaskType( $taskType );
		$rc_id = null;
		$log_id = null;
		$result = ChangeTags::updateTags(
			$taskTypeHandler->getChangeTags( $taskType->getId() ),
			null,
			$rc_id,
			$revisionId,
			$log_id,
			null,
			null,
			$userIdentity
		);
		LoggerFactory::getInstance( 'GrowthExperiments' )->debug(
			'ChangeTags::updateTags result in NewcomerTaskCompleteHandler: ' . json_encode( $result )
		);
		$this->perDbNameStatsdDataFactory->increment(
			'GrowthExperiments.NewcomerTask.' . $taskType->getId() . '.Save'
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
		$existingTags = ChangeTags::getTags(
			$this->dbr,
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
		if ( !$userIdentity->isRegistered() ) {
			return StatusValue::newFatal( 'You must be logged-in' );
		}
		$context = $this->requestContext ?? RequestContext::getMain();
		if ( !SuggestedEdits::isEnabled( $context ) ||
			!SuggestedEdits::isActivated( $context, $this->userOptionsLookup )
		) {
			return StatusValue::newFatal( 'Suggested edits are not enabled or activated for your user.' );
		}
		return StatusValue::newGood();
	}

}
