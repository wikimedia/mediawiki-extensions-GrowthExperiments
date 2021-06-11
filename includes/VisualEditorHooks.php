<?php

namespace GrowthExperiments;

use DeferredUpdates;
use GrowthExperiments\NewcomerTasks\AddLink\AddLinkSubmissionHandler;
use GrowthExperiments\NewcomerTasks\AddLink\LinkRecommendationStore;
use GrowthExperiments\NewcomerTasks\TaskType\LinkRecommendationTaskType;
use GrowthExperiments\NewcomerTasks\Tracker\TrackerFactory;
use IDBAccessObject;
use MediaWiki\Extension\VisualEditor\VisualEditorApiVisualEditorEditPostSaveHook;
use MediaWiki\Extension\VisualEditor\VisualEditorApiVisualEditorEditPreSaveHook;
use MediaWiki\Page\ProperPageIdentity;
use MediaWiki\User\UserIdentity;
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

	/** @var TitleFactory */
	private $titleFactory;
	/** @var TrackerFactory */
	private $trackerFactory;
	/** @var AddLinkSubmissionHandler */
	private $addLinkSubmissionHandler;
	/** @var LinkRecommendationStore */
	private $linkRecommendationStore;

	/**
	 * @param TitleFactory $titleFactory
	 * @param TrackerFactory $trackerFactory
	 * @param AddLinkSubmissionHandler $addLinkSubmissionHandler
	 * @param LinkRecommendationStore $linkRecommendationStore
	 */
	public function __construct(
		TitleFactory $titleFactory,
		TrackerFactory $trackerFactory,
		AddLinkSubmissionHandler $addLinkSubmissionHandler,
		LinkRecommendationStore $linkRecommendationStore
	) {
		$this->titleFactory = $titleFactory;
		$this->trackerFactory = $trackerFactory;
		$this->addLinkSubmissionHandler = $addLinkSubmissionHandler;
		$this->linkRecommendationStore = $linkRecommendationStore;
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
		$data = $pluginData['linkrecommendation'] ?? null;
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
		if ( !$this->linkRecommendationStore->getByLinkTarget(
			$title,
			IDBAccessObject::READ_LATEST )
		) {
			// There's no link recommendation data stored for this page, so it must have been
			// removed from the database during the time the user had the UI open. Don't allow
			// the save to continue.
			$apiResponse['message'] = [ 'growthexperiments-addlink-notinstore', $title->getPrefixedText() ];
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
		$data = $pluginData['linkrecommendation'] ?? null;
		if ( $apiResponse['result'] !== 'success' ) {
			return;
		}
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
