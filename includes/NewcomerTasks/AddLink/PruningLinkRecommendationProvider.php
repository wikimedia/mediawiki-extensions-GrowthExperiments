<?php

declare( strict_types = 1 );

namespace GrowthExperiments\NewcomerTasks\AddLink;

use GrowthExperiments\NewcomerTasks\TaskType\LinkRecommendationTaskType;
use GrowthExperiments\NewcomerTasks\TaskType\TaskType;
use MediaWiki\Cache\LinkBatchFactory;
use MediaWiki\Linker\LinkTarget;
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
		$recommendation = $this->innerProvider->get( $title, $taskType );
		if ( $recommendation instanceof StatusValue ) {
			return $recommendation;
		}

		return $this->pruneLinkRecommendation( $recommendation );
	}

	/**
	 * Remove exclusion-listed links and optionally red links from a LinkRecommendation.
	 * Returns a warning status when all links have been removed.
	 * @param LinkRecommendation $linkRecommendation
	 * @return LinkRecommendation|StatusValue
	 * @throws MalformedTitleException
	 */
	private function pruneLinkRecommendation( LinkRecommendation $linkRecommendation ) {
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
		$goodLinks = array_filter( $linkRecommendation->getLinks(),
			function ( LinkRecommendationLink $link ) use ( $excludedLinkIds ) {
				$pageId = $this->titleFactory->newFromTextThrow( $link->getLinkTarget() )->getArticleID();
				if ( $this->pruneRedLinks && !$pageId ) {
					return false;
				}
				return !in_array( $pageId, $excludedLinkIds );
			} );

		if ( !$goodLinks ) {
			// Message used for debugging, keep it in English to reduce translator burden.
			return StatusValue::newGood()->warning( 'rawmessage',
				'All of the links in the recommendation have been pruned' );
		}
		// In most cases we could just return the original object; opt for consistency instead.
		return new LinkRecommendation(
			$linkRecommendation->getTitle(),
			$linkRecommendation->getPageId(),
			$linkRecommendation->getRevisionId(),
			$goodLinks,
			$linkRecommendation->getMetadata()
		);
	}

}
