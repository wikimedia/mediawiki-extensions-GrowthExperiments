<?php

namespace GrowthExperiments;

use GrowthExperiments\NewcomerTasks\ConfigurationLoader\ConfigurationLoader;
use GrowthExperiments\NewcomerTasks\TaskType\TaskTypeHandler;
use GrowthExperiments\NewcomerTasks\TaskType\TaskTypeHandlerRegistry;
use MediaWiki\Extension\VisualEditor\VisualEditorApiVisualEditorEditPostSaveHook;
use MediaWiki\Extension\VisualEditor\VisualEditorApiVisualEditorEditPreSaveHook;
use MediaWiki\Page\ProperPageIdentity;
use MediaWiki\User\UserIdentity;
use OutOfBoundsException;
use PrefixingStatsdDataFactoryProxy;
use Status;
use TitleFactory;
use UnexpectedValueException;

/**
 * Hook handlers for hooks defined in VisualEditor. Handled in a dedicated class to avoid other
 *  classes depending on interfaces defined in VisualEditor (which is an optional dependency).
 */
class VisualEditorHooks implements
	VisualEditorApiVisualEditorEditPreSaveHook,
	VisualEditorApiVisualEditorEditPostSaveHook
{

	/** Prefix used for the VisualEditor API's plugin parameter. */
	private const PLUGIN_PREFIX = 'ge-task-';

	/** @var TitleFactory */
	private $titleFactory;
	/** @var ConfigurationLoader */
	private $configurationLoader;
	/** @var TaskTypeHandlerRegistry */
	private $taskTypeHandlerRegistry;
	/** @var PrefixingStatsdDataFactoryProxy */
	private $perDbNameStatsdDataFactory;

	/**
	 * @param TitleFactory $titleFactory
	 * @param ConfigurationLoader $configurationLoader
	 * @param TaskTypeHandlerRegistry $taskTypeHandlerRegistry
	 * @param PrefixingStatsdDataFactoryProxy $perDbNameStatsdDataFactory
	 */
	public function __construct(
		TitleFactory $titleFactory,
		ConfigurationLoader $configurationLoader,
		TaskTypeHandlerRegistry $taskTypeHandlerRegistry,
		PrefixingStatsdDataFactoryProxy $perDbNameStatsdDataFactory
	) {
		$this->titleFactory = $titleFactory;
		$this->configurationLoader = $configurationLoader;
		$this->taskTypeHandlerRegistry = $taskTypeHandlerRegistry;
		$this->perDbNameStatsdDataFactory = $perDbNameStatsdDataFactory;
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
		if ( !$user->isRegistered() ) {
			return;
		}
		/** @var ?TaskTypeHandler $taskTypeHandler */
		list( $data, $taskTypeHandler, $taskTypeId ) = $this->getDataFromApiRequest( $pluginData );
		if ( !$data || !$taskTypeId ) {
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
		if ( !$user->isRegistered() ) {
			return;
		}
		/** @var ?TaskTypeHandler $taskTypeHandler */
		list( $data, $taskTypeHandler, $taskTypeId ) = $this->getDataFromApiRequest( $pluginData );
		if ( !$data || !$taskTypeId ) {

			return;
		}
		$title = $this->titleFactory->castFromPageIdentity( $page );
		if ( !$title ) {
			throw new UnexpectedValueException( 'Unable to get Title from PageIdentity' );
		}

		$newRevId = $saveResult['edit']['newrevid'] ?? null;

		$status = $taskTypeHandler->getSubmissionHandler()->handle(
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
				$this->perDbNameStatsdDataFactory->increment(
					'GrowthExperiments.NewcomerTask.' . $taskTypeId . '.Save'
				);
			}
		} else {
			// FIXME expose error formatter to hook so this can be handled better
			$errorMessage = Status::wrap( $status )->getWikiText();
			$apiResponse['errors'][] = $errorMessage;
			Util::logStatus( $status );
		}
	}

	/**
	 * Extract the data sent by the frontend structured task logic from the API request.
	 * @param array $pluginData
	 * @return array [ JSON data from frontend, TaskTypeHandler, task type ID ] or [ null, null, null ]
	 * @phan-return array{0:?array,1:?TaskTypeHandler,2:?string}
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
				} catch ( OutOfBoundsException $e ) {
					// Probably some sort of hand-crafted fake API request. Ignore it.
					continue;
				}

				return [ $data, $taskTypeHandler, $taskTypeId ];
			}
		}
		return [ null, null, null ];
	}

}
