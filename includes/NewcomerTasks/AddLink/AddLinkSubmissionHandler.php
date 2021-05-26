<?php

namespace GrowthExperiments\NewcomerTasks\AddLink;

use LogicException;
use MalformedTitleException;
use MediaWiki\Cache\LinkBatchFactory;
use MediaWiki\Linker\LinkTarget;
use MediaWiki\Storage\RevisionLookup;
use MediaWiki\User\UserIdentity;
use Status;
use StatusValue;
use TitleFactory;
use UnexpectedValueException;
use Wikimedia\Rdbms\DBReadOnlyError;

/**
 * Record the user's decision on the recommendations for a given page.
 */
class AddLinkSubmissionHandler {

	/** @var LinkRecommendationHelper */
	private $linkRecommendationHelper;
	/** @var LinkSubmissionRecorder */
	private $addLinkSubmissionRecorder;
	/** @var TitleFactory */
	private $titleFactory;
	/** @var LinkRecommendationStore */
	private $linkRecommendationStore;
	/** @var LinkBatchFactory */
	private $linkBatchFactory;

	/**
	 * @param LinkRecommendationHelper $linkRecommendationHelper
	 * @param LinkRecommendationStore $linkRecommendationStore
	 * @param LinkSubmissionRecorder $addLinkSubmissionRecorder
	 * @param LinkBatchFactory $linkBatchFactory
	 * @param TitleFactory $titleFactory
	 */
	public function __construct(
		LinkRecommendationHelper $linkRecommendationHelper,
		LinkRecommendationStore $linkRecommendationStore,
		LinkSubmissionRecorder $addLinkSubmissionRecorder,
		LinkBatchFactory $linkBatchFactory,
		TitleFactory $titleFactory
	) {
		$this->linkRecommendationHelper = $linkRecommendationHelper;
		$this->addLinkSubmissionRecorder = $addLinkSubmissionRecorder;
		$this->titleFactory = $titleFactory;
		$this->linkRecommendationStore = $linkRecommendationStore;
		$this->linkBatchFactory = $linkBatchFactory;
	}

	/**
	 * @param LinkTarget $title
	 * @param UserIdentity $user
	 * @param int $baseRevId
	 * @param int|null $editRevId
	 * @param array $data
	 * @return int|null The log ID of the recorded submission; null indicates the database was in read only mode when
	 *   the submission recording was attempted or that a link recommendation for the revision could not be found.
	 * @throws MalformedTitleException
	 * @throws UnexpectedValueException
	 * @throws LogicException
	 */
	public function run( LinkTarget $title, UserIdentity $user, int $baseRevId, ?int $editRevId, array $data ): ?int {
		// The latest revision is the saved edit, so we need to find the link recommendation based on the base
		// revision ID.
		$linkRecommendation = $this->linkRecommendationStore->getByRevId(
			$baseRevId,
			RevisionLookup::READ_LATEST
		);
		if ( !$linkRecommendation ) {
			return null;
		}
		$links = $this->normalizeTargets( $linkRecommendation->getLinks() );

		$acceptedTargets = $this->normalizeTargets( $data['acceptedTargets'] ?: [] );
		$rejectedTargets = $this->normalizeTargets( $data['rejectedTargets'] ?: [] );
		$skippedTargets = $this->normalizeTargets( $data['skippedTargets'] ?: [] );

		$allTargets = array_merge( $acceptedTargets, $rejectedTargets, $skippedTargets );
		$unexpectedTargets = array_diff( $allTargets, $links );
		if ( $unexpectedTargets ) {
			throw new LogicException( 'Unexpected link targets: ' . implode( ', ', $unexpectedTargets ) );
		}

		$pageIdentity = $this->titleFactory->newFromLinkTarget( $title )->toPageIdentity();
		$status = StatusValue::newGood();
		try {
			$this->linkRecommendationHelper->deleteLinkRecommendation(
				$pageIdentity,
				// FIXME T283606: In theory if $editRevId is set (this is a real edit, not a null edit that
				//   happens when the user accepted nothing), we can leave search index updates to the
				//   SearchDataForIndex hook. In practice that does not work because we delete the DB row
				//   here so the hook logic will assume there's nothing to do. Might want to improve that
				//   in the future.
				true
			);
			$status = $this->addLinkSubmissionRecorder->record( $user, $linkRecommendation, $acceptedTargets,
				$rejectedTargets, $skippedTargets, $editRevId );
			if ( !$status->isOK() ) {
				throw new UnexpectedValueException( Status::wrap( $status )->getWikiText( null, null, 'en' ) );
			}
		} catch ( DBReadOnlyError $e ) {
			// Leaving a dangling DB row behind doesn't cause any problems so just ignore this.
		}
		$result = $status->getValue();
		return $result['logId'];
	}

	/**
	 * Normalize link targets into prefixed dbkey format
	 * @param array<int,string|LinkTarget|LinkRecommendationLink> $targets
	 * @return string[]
	 * @throws MalformedTitleException
	 */
	private function normalizeTargets( array $targets ): array {
		$linkBatch = $this->linkBatchFactory->newLinkBatch();
		$normalized = [];
		$linkTargets = [];
		foreach ( $targets as $target ) {
			if ( $target instanceof LinkRecommendationLink ) {
				$target = $target->getLinkTarget();
			}
			if ( !$target instanceof LinkTarget ) {
				$target = $this->titleFactory->newFromTextThrow( $target );
			}
			$linkTarget = $this->titleFactory->newFromLinkTarget( $target );
			$linkTargets[] = $linkTarget;
			$linkBatch->addObj( $linkTarget );
		}
		$linkBatch->execute();
		foreach ( $linkTargets as $target ) {
			$normalized[] = $target->getPrefixedDBkey();
		}
		return $normalized;
	}

}
