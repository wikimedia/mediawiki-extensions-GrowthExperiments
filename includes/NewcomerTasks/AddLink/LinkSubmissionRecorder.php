<?php

namespace GrowthExperiments\NewcomerTasks\AddLink;

use MediaWiki\Cache\LinkBatchFactory;
use MediaWiki\Logging\ManualLogEntry;
use MediaWiki\Title\TitleParser;
use MediaWiki\User\UserIdentity;
use StatusValue;
use Wikimedia\Rdbms\IDBAccessObject;

/**
 * Record information about a user accepting/rejecting parts of a link recommendation.
 * This involves creating a log entry and updating an article-specific exclusion list.
 */
class LinkSubmissionRecorder {

	/** @var TitleParser */
	private $titleParser;

	/** @var LinkBatchFactory */
	private $linkBatchFactory;

	/** @var LinkRecommendationStore */
	private $linkRecommendationStore;

	/**
	 * @param TitleParser $titleParser
	 * @param LinkBatchFactory $linkBatchFactory
	 * @param LinkRecommendationStore $linkRecommendationStore
	 */
	public function __construct(
		TitleParser $titleParser,
		LinkBatchFactory $linkBatchFactory,
		LinkRecommendationStore $linkRecommendationStore
	) {
		$this->titleParser = $titleParser;
		$this->linkBatchFactory = $linkBatchFactory;
		$this->linkRecommendationStore = $linkRecommendationStore;
	}

	/**
	 * Record the results of a user reviewing a link recommendation.
	 * @param UserIdentity $user
	 * @param LinkRecommendation $linkRecommendation
	 * @param string[] $acceptedTargets
	 * @param string[] $rejectedTargets
	 * @param string[] $skippedTargets
	 * @param int|null $editRevId Revision ID of the edit adding the links (might be null since
	 *   it's not necessary that any links have been added).
	 * @return StatusValue
	 */
	public function record(
		UserIdentity $user,
		LinkRecommendation $linkRecommendation,
		array $acceptedTargets,
		array $rejectedTargets,
		array $skippedTargets,
		?int $editRevId
	): StatusValue {
		if ( $this->linkRecommendationStore->hasSubmission( $linkRecommendation,
			IDBAccessObject::READ_LOCKING )
		) {
			// There's already a review for this revision. Possibly a race condition where two
			// users reviewed the same task at the same time.
			return StatusValue::newGood()->error( 'growthexperiments-addlink-duplicatesubmission',
				$linkRecommendation->getRevisionId() );
		}
		$this->linkRecommendationStore->recordSubmission(
			$user,
			$linkRecommendation,
			$this->titlesToPageIds( $acceptedTargets ),
			$this->titlesToPageIds( $rejectedTargets ),
			$this->titlesToPageIds( $skippedTargets ),
			$editRevId
		);
		$logId = $this->log(
			$user,
			$linkRecommendation,
			count( $acceptedTargets ),
			count( $rejectedTargets ),
			count( $skippedTargets ),
			$editRevId
		);
		return StatusValue::newGood( [ 'logId' => $logId ] );
	}

	/**
	 * Make a Special:Log entry.
	 * @param UserIdentity $user
	 * @param LinkRecommendation $linkRecommendation
	 * @param int $acceptedLinkCount
	 * @param int $rejectedLinkCount
	 * @param int $skippedLinkCount
	 * @return int Log ID.
	 * @param int|null $editRevId Revision ID of the edit adding the links (might be null since
	 *   it's not necessary that any links have been added).
	 */
	private function log(
		UserIdentity $user,
		LinkRecommendation $linkRecommendation,
		int $acceptedLinkCount,
		int $rejectedLinkCount,
		int $skippedLinkCount,
		?int $editRevId
	): int {
		$totalLinkCount = $acceptedLinkCount + $rejectedLinkCount + $skippedLinkCount;

		$logEntry = new ManualLogEntry( 'growthexperiments', 'addlink' );
		$logEntry->setTarget( $linkRecommendation->getTitle() );
		$logEntry->setPerformer( $user );
		$logEntry->setParameters( [
			'4:number:count' => $totalLinkCount,
			'5:number:count' => $acceptedLinkCount,
			'6:number:count' => $rejectedLinkCount,
			'7:number:count' => $skippedLinkCount,
		] );
		if ( $editRevId ) {
			// This has the side effect of the log entry getting tagged with all the change tags
			// the revision is getting tagged with. Overall, still preferable - the log entry is
			// not published to recent changes so its tags don't matter much.
			$logEntry->setAssociatedRevId( $editRevId );
		}
		$logId = $logEntry->insert();
		// Do not publish to recent changes, it would be pointless as this action cannot
		// be inspected or patrolled.
		$logEntry->publish( $logId, 'udp' );
		return $logId;
	}

	/**
	 * Converts title strings to page IDs. Non-existent pages are omitted.
	 * @param string[] $titles
	 * @return int[]
	 */
	private function titlesToPageIds( array $titles ): array {
		$linkBatch = $this->linkBatchFactory->newLinkBatch();
		foreach ( $titles as $title ) {
			// ensuring that the title is valid is left to the caller
			$linkBatch->addObj( $this->titleParser->parseTitle( $title ) );
		}
		$ids = $linkBatch->execute();
		// LinkBatch::execute() returns a title => ID map. Discard titles, discard
		// 0 ID used for non-existent pages (we assume those won't be recommended anyway),
		// squash duplicates (just in case; they shouldn't exist).
		return array_unique( array_filter( array_values( $ids ) ) );
	}

}
