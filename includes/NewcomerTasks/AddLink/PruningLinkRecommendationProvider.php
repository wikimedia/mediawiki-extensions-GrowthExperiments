<?php

declare( strict_types = 1 );

namespace GrowthExperiments\NewcomerTasks\AddLink;

use GrowthExperiments\NewcomerTasks\TaskType\LinkRecommendationTaskType;
use GrowthExperiments\NewcomerTasks\TaskType\TaskType;
use MediaWiki\Linker\LinkTarget;
use MediaWiki\Page\LinkBatchFactory;
use MediaWiki\Title\MalformedTitleException;
use MediaWiki\Title\TitleFactory;
use StatusValue;

/**
 * A link recommendation provider that removes red links and links which have been rejected too
 * often. The class works as a decorator wrapping another provider.
 */
class PruningLinkRecommendationProvider implements LinkRecommendationProvider {

	private TitleFactory $titleFactory;

	private LinkBatchFactory $linkBatchFactory;

	private LinkRecommendationStore $linkRecommendationStore;

	private LinkRecommendationProvider $innerProvider;

	private bool $pruneRedLinks;

	/**
	 * @param TitleFactory $titleFactory
	 * @param LinkBatchFactory $linkBatchFactory
	 * @param LinkRecommendationStore $linkRecommendationStore
	 * @param LinkRecommendationProvider $innerProvider
	 * @param bool $pruneRedLinks Prune red links? Should be true in production settings as we
	 *   don't want to recommend red links, but in a developer setup it might be convenient to
	 *   pretend that all recommended articles exist.
	 */
	public function __construct(
		TitleFactory $titleFactory,
		LinkBatchFactory $linkBatchFactory,
		LinkRecommendationStore $linkRecommendationStore,
		LinkRecommendationProvider $innerProvider,
		bool $pruneRedLinks
	) {
		$this->titleFactory = $titleFactory;
		$this->linkBatchFactory = $linkBatchFactory;
		$this->linkRecommendationStore = $linkRecommendationStore;
		$this->innerProvider = $innerProvider;
		$this->pruneRedLinks = $pruneRedLinks;
	}

	/**
	 * @inheritDoc
	 * @throws MalformedTitleException
	 */
	public function get( LinkTarget $title, TaskType $taskType ) {
		$status = $this->getDetailed( $title, $taskType );
		if ( $status->isGood() ) {
			return $status->getValue();
		}
		return $status;
	}

	/**
	 * @throws MalformedTitleException
	 */
	public function getDetailed( LinkTarget $title, TaskType $taskType ): LinkRecommendationEvalStatus {
		$recommendation = $this->innerProvider->get( $title, $taskType );
		if ( $recommendation instanceof StatusValue ) {
			return LinkRecommendationEvalStatus::newGood()->merge( $recommendation );
		}

		return $this->pruneLinkRecommendation( $recommendation );
	}

	/**
	 * Remove exclusion-listed links and optionally red links from a LinkRecommendation.
	 * Returns a warning status when all links have been removed.
	 *
	 * @throws MalformedTitleException
	 */
	private function pruneLinkRecommendation( LinkRecommendation $linkRecommendation ): LinkRecommendationEvalStatus {
		$excludedLinkIds = $this->linkRecommendationStore->getExcludedLinkIds(
			$linkRecommendation->getPageId(),
			LinkRecommendationTaskType::REJECTION_EXCLUSION_LIMIT
		);
		$this->linkBatchFactory->newLinkBatch(
			array_map(
				function ( LinkRecommendationLink $link ) {
					return $this->titleFactory->newFromText( $link->getLinkTarget() );
				},
				$linkRecommendation->getLinks()
			)
		)->execute();
		$prunedRedLinksCounter = 0;
		$prunedExcludedLinksCounter = 0;
		$goodLinks = array_filter(
			$linkRecommendation->getLinks(),
			function ( LinkRecommendationLink $link ) use (
				$excludedLinkIds,
				&$prunedRedLinksCounter,
				&$prunedExcludedLinksCounter
			) {
				$pageId = $this->titleFactory->newFromTextThrow( $link->getLinkTarget() )->getArticleID();
				if ( $this->pruneRedLinks && !$pageId ) {
					$prunedRedLinksCounter++;
					return false;
				}
				if ( in_array( $pageId, $excludedLinkIds ) ) {
					$prunedExcludedLinksCounter++;
					return false;
				}
				return true;
			}
		);
		$returnStatus = LinkRecommendationEvalStatus::newGood();
		$returnStatus->setNumberOfPrunedRedLinks( $prunedRedLinksCounter );
		$returnStatus->setNumberOfPrunedExcludedLinks( $prunedExcludedLinksCounter );

		if ( !$goodLinks ) {
			$returnStatus->setNotGoodCause( LinkRecommendationEvalStatus::NOT_GOOD_CAUSE_ALL_RECOMMENDATIONS_PRUNED );
			// Message used for debugging, keep it in English to reduce translator burden.
			return $returnStatus->warning( 'rawmessage',
				'All of the links in the recommendation have been pruned' );
		}
		// In most cases we could just return the original object; opt for consistency instead.
		return $returnStatus->setResult(
			true,
			new LinkRecommendation(
				$linkRecommendation->getTitle(),
				$linkRecommendation->getPageId(),
				$linkRecommendation->getRevisionId(),
				$goodLinks,
				$linkRecommendation->getMetadata()
			)
		);
	}

}
