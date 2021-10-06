<?php

namespace GrowthExperiments;

use DeferredUpdates;
use GrowthExperiments\NewcomerTasks\ConfigurationLoader\ConfigurationLoader;
use GrowthExperiments\NewcomerTasks\TaskType\LinkRecommendationTaskType;
use GrowthExperiments\NewcomerTasks\TaskType\StructuredTaskTypeHandler;
use GrowthExperiments\NewcomerTasks\TaskType\TaskTypeHandlerRegistry;
use GrowthExperiments\NewcomerTasks\Tracker\TrackerFactory;
use MediaWiki\Extension\VisualEditor\VisualEditorApiVisualEditorEditPostSaveHook;
use MediaWiki\Extension\VisualEditor\VisualEditorApiVisualEditorEditPreSaveHook;
use MediaWiki\Page\ProperPageIdentity;
use MediaWiki\User\UserIdentity;
use OutOfBoundsException;
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
	/** @var TrackerFactory */
	private $trackerFactory;

	/**
	 * @param TitleFactory $titleFactory
	 * @param ConfigurationLoader $configurationLoader
	 * @param TaskTypeHandlerRegistry $taskTypeHandlerRegistry
	 * @param TrackerFactory $trackerFactory
	 */
	public function __construct(
		TitleFactory $titleFactory,
		ConfigurationLoader $configurationLoader,
		TaskTypeHandlerRegistry $taskTypeHandlerRegistry,
		TrackerFactory $trackerFactory
	) {
		$this->titleFactory = $titleFactory;
		$this->configurationLoader = $configurationLoader;
		$this->taskTypeHandlerRegistry = $taskTypeHandlerRegistry;
		$this->trackerFactory = $trackerFactory;
	}

	/** @inheritDoc */
	public function onVisualEditorApiVisualEditorEditPreSave(
		ProperPageIdentity $page,
		UserIdentity $user,
		string $wikitext,
		array $params,
		array $pluginData,
		array &$apiResponse
	) {
		/** @var ?StructuredTaskTypeHandler $taskTypeHandler */
		list( $data, $taskTypeHandler ) = $this->getDataFromApiRequest( $pluginData );
		if ( !$data ) {
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
		/** @var ?StructuredTaskTypeHandler $taskTypeHandler */
		list( $data, $taskTypeHandler ) = $this->getDataFromApiRequest( $pluginData );
		if ( !$data ) {
			// This is going to run on every edit and not in a deferred update, so at least filter
			// by authenticated users to make this slightly faster for anons.
			if ( $user->isRegistered() ) {
				// The user is registered, obtain an edit tracker for the user, then check to see if
				// the page that was edited is in their tracker and is also an instance of a link recommendation.
				// Because we're already in this code path ($pluginData['linkrecommendation'] is not set), that means
				// the edit came external to the add link interface. Untracking the page avoids adding the "add link"
				// tag in the onRecentChanges_save hook.
				$tracker = $this->trackerFactory->getTracker( $user );
				if ( $tracker->getTaskTypeForPage( $page->getId() ) instanceof LinkRecommendationTaskType ) {
					$tracker->untrack( $page->getId() );
				}
			}
			return;
		}
		$title = $this->titleFactory->castFromPageIdentity( $page );
		if ( !$title ) {
			throw new UnexpectedValueException( 'Unable to get Title from PageIdentity' );
		}

		$status = $taskTypeHandler->getSubmissionHandler()->handle(
			$title->toPageIdentity(),
			$user,
			$params['oldid'],
			$saveResult['edit']['newrevid'] ?? null,
			$data
		);
		if ( $status->isGood() ) {
			$apiResponse['gelogid'] = $status->getValue()['logId'] ?? null;
		} else {
			// FIXME expose error formatter to hook so this can be handled better
			$errorMessage = Status::wrap( $status )->getWikiText();
			$apiResponse['errors'][] = $errorMessage;
			Util::logStatus( $status );
		}

		DeferredUpdates::addCallableUpdate( function () use ( $user, $page ) {
			$tracker = $this->trackerFactory->getTracker( $user );
			// Untrack the page so that future edits by the user are not tagged with
			// the suggested link edit tag.
			$tracker->untrack( $page->getId() );
		} );
	}

	/**
	 * Extract the data sent by the frontend structured task logic from the API request.
	 * @param array $pluginData
	 * @return array [ JSON data from frontend, TaskTypeHandler ] or [ null, null ]
	 * @phan-return array{0:?array,1:?StructuredTaskTypeHandler}
	 */
	private function getDataFromApiRequest( array $pluginData ): array {
		// Fast-track the common case of a non-Growth-related save - getTaskTypes() is not free.
		if ( !$pluginData ) {
			return [ null, null ];
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
				if ( !( $taskTypeHandler instanceof StructuredTaskTypeHandler ) ) {
					// This mechanism is for structured tasks only.
					continue;
				}

				return [ $data, $taskTypeHandler ];
			}
		}
		return [ null, null ];
	}

}
