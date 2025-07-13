<?php

namespace GrowthExperiments;

use GrowthExperiments\HomepageModules\SuggestedEdits;
use GrowthExperiments\NewcomerTasks\ConfigurationLoader\ConfigurationLoader;
use GrowthExperiments\NewcomerTasks\TaskType\StructuredTaskTypeHandler;
use GrowthExperiments\NewcomerTasks\TaskType\TaskType;
use GrowthExperiments\NewcomerTasks\TaskType\TaskTypeHandler;
use GrowthExperiments\NewcomerTasks\TaskType\TaskTypeHandlerRegistry;
use MediaWiki\Api\ApiBase;
use MediaWiki\Api\Hook\APIGetAllowedParamsHook;
use MediaWiki\Extension\VisualEditor\ApiVisualEditorEdit;
use MediaWiki\Extension\VisualEditor\VisualEditorApiVisualEditorEditPostSaveHook;
use MediaWiki\Extension\VisualEditor\VisualEditorApiVisualEditorEditPreSaveHook;
use MediaWiki\Page\ProperPageIdentity;
use MediaWiki\Status\Status;
use MediaWiki\Title\TitleFactory;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserIdentityUtils;
use MediaWiki\WikiMap\WikiMap;
use OutOfBoundsException;
use UnexpectedValueException;
use Wikimedia\Stats\StatsFactory;

/**
 * Hook handlers for hooks defined in VisualEditor.
 */
class VisualEditorHooks implements
	APIGetAllowedParamsHook,
	VisualEditorApiVisualEditorEditPreSaveHook,
	VisualEditorApiVisualEditorEditPostSaveHook
{

	/** Prefix used for the VisualEditor API's plugin parameter. */
	public const PLUGIN_PREFIX = 'ge-task-';

	private TitleFactory $titleFactory;
	private ConfigurationLoader $configurationLoader;
	private TaskTypeHandlerRegistry $taskTypeHandlerRegistry;

	private StatsFactory $statsFactory;
	private UserIdentityUtils $userIdentityUtils;

	public function __construct(
		TitleFactory $titleFactory,
		ConfigurationLoader $configurationLoader,
		TaskTypeHandlerRegistry $taskTypeHandlerRegistry,
		StatsFactory $statsFactory,
		UserIdentityUtils $userIdentityUtils
	) {
		$this->titleFactory = $titleFactory;
		$this->configurationLoader = $configurationLoader;
		$this->taskTypeHandlerRegistry = $taskTypeHandlerRegistry;
		$this->statsFactory = $statsFactory;
		$this->userIdentityUtils = $userIdentityUtils;
	}

	/** @inheritDoc */
	public function onVisualEditorApiVisualEditorEditPreSave(
		ProperPageIdentity $page,
		UserIdentity $user,
		string $wikitext,
		array &$params,
		array $pluginData,
		array &$apiResponse
	) {
		// This is going to run on every edit and not in a deferred update, so at least filter
		// by authenticated users to make this slightly faster for anons.
		if ( !$this->userIdentityUtils->isNamed( $user ) ) {
			return;
		}
		/** @var ?TaskTypeHandler $taskTypeHandler */
		[ $data, $taskTypeHandler, $taskType ] = $this->getDataFromApiRequest( $pluginData );
		if ( !$data || !$taskType ) {
			// Not an edit we are interested in looking at.
			return;
		}
		$title = $this->titleFactory->castFromPageIdentity( $page );
		if ( !$title ) {
			// Something weird has happened; let the save attempt go through because
			// presumably later an exception will be thrown and that can be dealt
			// with by VisualEditor.
			return;
		}
		$status = $taskTypeHandler->getSubmissionHandler()->validate(
			$taskType,
			$title->toPageIdentity(),
			$user,
			$params['oldid'],
			$data
		);
		if ( !$status->isGood() ) {
			$message = Status::wrap( $status )->getMessage();
			$apiResponse['message'] = array_merge( [ $message->getKey() ], $message->getParams() );
			Util::logStatus( $status );
			return false;
		}
	}

	/** @inheritDoc */
	public function onVisualEditorApiVisualEditorEditPostSave(
		ProperPageIdentity $page,
		UserIdentity $user,
		string $wikitext,
		array $params,
		array $pluginData,
		array $saveResult,
		array &$apiResponse
	): void {
		if ( $apiResponse['result'] !== 'success' ) {
			return;
		}
		// This is going to run on every edit and not in a deferred update, so at least filter
		// by authenticated users to make this slightly faster for anons.
		if ( !$this->userIdentityUtils->isNamed( $user ) ) {
			return;
		}
		/** @var ?TaskTypeHandler $taskTypeHandler */
		/** @var ?TaskType $taskType */
		[ $data, $taskTypeHandler, $taskType ] = $this->getDataFromApiRequest( $pluginData );
		if ( !$data || !$taskType ) {
			return;
		}
		$title = $this->titleFactory->castFromPageIdentity( $page );
		if ( !$title ) {
			throw new UnexpectedValueException( 'Unable to get Title from PageIdentity' );
		}

		$newRevId = $saveResult['edit']['newrevid'] ?? null;

		$status = $taskTypeHandler->getSubmissionHandler()->handle(
			$taskType,
			$title->toPageIdentity(),
			$user,
			$params['oldid'],
			$newRevId,
			$data
		);
		if ( $status->isGood() ) {
			$apiResponse['gelogid'] = $status->getValue()['logId'] ?? null;
			$apiResponse['gewarnings'][] = $status->getValue()['warnings'] ?? '';
			if ( $newRevId ) {
				$wiki = WikiMap::getCurrentWikiId();
				$this->statsFactory->withComponent( 'GrowthExperiments' )
					->getCounter( 'newcomer_task_save_total' )
					->setLabel( 'wiki', $wiki )
					->setLabel( 'task_type_id', $taskType->getId() )
					->increment();
			}
		} else {
			// FIXME expose error formatter to hook so this can be handled better
			$errorMessage = Status::wrap( $status )->getWikiText();
			$apiResponse['errors'][] = $errorMessage;
			Util::logStatus( $status );
		}
	}

	/** @inheritDoc */
	public function onAPIGetAllowedParams( $module, &$params, $flags ) {
		if ( $module instanceof ApiVisualEditorEdit
			&& ( $flags & ApiBase::GET_VALUES_FOR_HELP )
			&& SuggestedEdits::isEnabledForAnyone( $module->getContext()->getConfig() )
		) {
			$taskTypes = $this->configurationLoader->getTaskTypes();
			foreach ( $taskTypes as $taskTypeId => $taskType ) {
				$taskTypeHandler = $this->taskTypeHandlerRegistry->getByTaskType( $taskType );
				if ( $taskTypeHandler instanceof StructuredTaskTypeHandler ) {
					$paramValue = self::PLUGIN_PREFIX . $taskTypeId;
					$params['plugins'][ApiBase::PARAM_HELP_MSG_PER_VALUE][$paramValue] = [
							"apihelp-visualeditoredit-paramvalue-plugins-$paramValue",
					];
					$params['data-{plugin}'][ApiBase::PARAM_HELP_MSG_APPEND][$paramValue] = [
						"apihelp-visualeditoredit-append-data-plugin-$paramValue",
						$taskTypeHandler->getSubmitDataFormatMessage( $taskType, $module->getContext() ),
					];
				}
			}
		}
	}

	/**
	 * Extract the data sent by the frontend structured task logic from the API request.
	 * @param array $pluginData
	 * @return array [ JSON data from frontend, TaskTypeHandler, task type ID ] or [ null, null, null ]
	 * @phan-return array{0:?array,1:?TaskTypeHandler,2:?TaskType}
	 */
	private function getDataFromApiRequest( array $pluginData ): array {
		// Fast-track the common case of a non-Growth-related save - getTaskTypes() is not free.
		if ( !$pluginData ) {
			return [ null, null, null ];
		}

		$taskTypes = $this->configurationLoader->getTaskTypes();
		foreach ( $taskTypes as $taskTypeId => $taskType ) {
			$data = $pluginData[ self::PLUGIN_PREFIX . $taskTypeId ] ?? null;
			if ( $data ) {
				$data = json_decode( $data, true ) ?? [];
				try {
					$taskTypeHandler = $this->taskTypeHandlerRegistry->getByTaskType( $taskType );
				} catch ( OutOfBoundsException ) {
					// Probably some sort of hand-crafted fake API request. Ignore it.
					continue;
				}

				return [ $data, $taskTypeHandler, $taskType ];
			}
		}
		return [ null, null, null ];
	}

}
