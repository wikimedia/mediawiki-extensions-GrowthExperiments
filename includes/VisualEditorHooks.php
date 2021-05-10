<?php

namespace GrowthExperiments;

use DeferredUpdates;
use GrowthExperiments\NewcomerTasks\AddLink\AddLinkSubmissionHandler;
use GrowthExperiments\NewcomerTasks\Tracker\TrackerFactory;
use MediaWiki\Extension\VisualEditor\VisualEditorApiVisualEditorEditPostSaveHook;
use MediaWiki\Page\ProperPageIdentity;
use MediaWiki\User\UserIdentity;
use TitleFactory;
use UnexpectedValueException;

/**
 * Hook handlers for hooks defined in VisualEditor. Handled in a dedicated class to avoid other
 *  classes depending on interfaces defined in VisualEditor (which is an optional dependency).
 */
class VisualEditorHooks implements VisualEditorApiVisualEditorEditPostSaveHook {

	/** @var TitleFactory */
	private $titleFactory;
	/** @var TrackerFactory */
	private $trackerFactory;
	/** @var AddLinkSubmissionHandler */
	private $addLinkSubmissionHandler;

	/**
	 * @param TitleFactory $titleFactory
	 * @param TrackerFactory $trackerFactory
	 * @param AddLinkSubmissionHandler $addLinkSubmissionHandler
	 */
	public function __construct(
		TitleFactory $titleFactory,
		TrackerFactory $trackerFactory,
		AddLinkSubmissionHandler $addLinkSubmissionHandler
	) {
		$this->titleFactory = $titleFactory;
		$this->trackerFactory = $trackerFactory;
		$this->addLinkSubmissionHandler = $addLinkSubmissionHandler;
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
		$data = $pluginData['linkrecommendation'] ?? null;
		if ( !$data || $apiResponse['result'] !== 'success' ) {
			return;
		}
		$data = json_decode( $data, true ) ?? [];
		$title = $this->titleFactory->castFromPageIdentity( $page );
		if ( !$title ) {
			throw new UnexpectedValueException( 'Unable to get Title from PageIdentity' );
		}
		$apiResponse['gelogId'] = $this->addLinkSubmissionHandler->run(
			$title,
			$user,
			$params['oldid'],
			$saveResult['edit']['newrevid'] ?? null,
			$data
		);
		DeferredUpdates::addCallableUpdate( function () use ( $user, $page ) {
			$tracker = $this->trackerFactory->getTracker( $user );
			// Untrack the page so that future edits by the user are not tagged with
			// the suggested link edit tag.
			$tracker->untrack( $page->getId() );
		} );
	}

}
