<?php

namespace GrowthExperiments\NewcomerTasks\AddLink;

use GrowthExperiments\ErrorException;
use GrowthExperiments\NewcomerTasks\ConfigurationLoader\ConfigurationLoader;
use GrowthExperiments\NewcomerTasks\TaskType\LinkRecommendationTaskType;
use GrowthExperiments\NewcomerTasks\TaskType\LinkRecommendationTaskTypeHandler;
use GrowthExperiments\Util;
use GrowthExperiments\WikiConfigException;
use MalformedTitleException;
use MediaWiki\Cache\LinkBatchFactory;
use MediaWiki\Linker\LinkTarget;
use StatusValue;
use TitleFactory;

/**
 * Shared functionality for various classes that consume link recommendations.
 */
class LinkRecommendationHelper {

	/** @var ConfigurationLoader */
	private $configurationLoader;

	/** @var LinkRecommendationProvider */
	private $linkRecommendationProvider;

	/** @var LinkRecommendationStore */
	private $linkRecommendationStore;

	/** @var LinkBatchFactory */
	private $linkBatchFactory;

	/** @var TitleFactory */
	private $titleFactory;

	/** @var bool */
	private $pruneRedLinks;

	/**
	 * @param ConfigurationLoader $configurationLoader
	 * @param LinkRecommendationProvider $linkRecommendationProvider
	 * @param LinkRecommendationStore $linkRecommendationStore
	 * @param LinkBatchFactory $linkBatchFactory
	 * @param TitleFactory $titleFactory
	 * @param bool $pruneRedLinks Prune red links in pruneLinkRecommendation()?
	 */
	public function __construct(
		ConfigurationLoader $configurationLoader,
		LinkRecommendationProvider $linkRecommendationProvider,
		LinkRecommendationStore $linkRecommendationStore,
		LinkBatchFactory $linkBatchFactory,
		TitleFactory $titleFactory,
		bool $pruneRedLinks
	) {
		$this->configurationLoader = $configurationLoader;
		$this->linkRecommendationProvider = $linkRecommendationProvider;
		$this->linkRecommendationStore = $linkRecommendationStore;
		$this->linkBatchFactory = $linkBatchFactory;
		$this->titleFactory = $titleFactory;
		$this->pruneRedLinks = $pruneRedLinks;
	}

	/**
	 * Return the link recommendation stored for the given title.
	 * Returns null when all links of the recommendation are invalid.
	 * @param LinkTarget $title
	 * @return LinkRecommendation|null
	 * @throws ErrorException
	 * @throws MalformedTitleException
	 */
	public function getLinkRecommendation( LinkTarget $title ): ?LinkRecommendation {
		$taskTypeId = LinkRecommendationTaskTypeHandler::TASK_TYPE_ID;
		$taskType = $this->configurationLoader->getTaskTypes()[$taskTypeId] ?? null;
		if ( !$taskType ) {
			throw $this->configError( "No such task type: $taskTypeId" );
		} elseif ( !$taskType instanceof LinkRecommendationTaskType ) {
			throw $this->configError( "Not a link recommendation task type: $taskTypeId" );
		}
		$linkRecommendation = $this->linkRecommendationProvider->get( $title, $taskType );
		if ( $linkRecommendation instanceof StatusValue ) {
			throw new ErrorException( $linkRecommendation );
		}

		return $this->pruneLinkRecommendation( $linkRecommendation );
	}

	/**
	 * Removes exclusion-listed links and optionally red links from a LinkRecommendation.
	 * Returns null when all links have been removed.
	 * @param LinkRecommendation $linkRecommendation
	 * @return LinkRecommendation|null
	 * @throws MalformedTitleException
	 */
	public function pruneLinkRecommendation( LinkRecommendation $linkRecommendation ): ?LinkRecommendation {
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
		);
		$goodLinks = array_filter( $linkRecommendation->getLinks(),
			function ( LinkRecommendationLink $link ) use ( $excludedLinkIds ) {
				$pageId = $this->titleFactory->newFromTextThrow( $link->getLinkTarget() )->getArticleID();
				if ( $this->pruneRedLinks && !$pageId ) {
					return false;
				}
				return !in_array( $pageId, $excludedLinkIds );
			} );

		if ( !$goodLinks ) {
			return null;
		}
		// In most cases we could just return the original object; opt for consistency instead.
		return new LinkRecommendation(
			$linkRecommendation->getTitle(),
			$linkRecommendation->getPageId(),
			$linkRecommendation->getRevisionId(),
			$goodLinks
		);
	}

	/**
	 * @param string $errorMessage
	 * @return ErrorException
	 */
	private function configError( string $errorMessage ): ErrorException {
		Util::logError( new WikiConfigException( $errorMessage ) );
		return new ErrorException( StatusValue::newFatal( 'rawmessage', $errorMessage ) );
	}

}
