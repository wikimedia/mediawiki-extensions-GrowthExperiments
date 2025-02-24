<?php

namespace GrowthExperiments\NewcomerTasks\AddLink;

use CirrusSearch\WeightedTagsUpdater;
use GrowthExperiments\ErrorException;
use GrowthExperiments\NewcomerTasks\ConfigurationLoader\ConfigurationLoader;
use GrowthExperiments\NewcomerTasks\TaskType\LinkRecommendationTaskType;
use GrowthExperiments\NewcomerTasks\TaskType\LinkRecommendationTaskTypeHandler;
use GrowthExperiments\Util;
use GrowthExperiments\WikiConfigException;
use MediaWiki\Deferred\DeferredUpdates;
use MediaWiki\Linker\LinkTarget;
use MediaWiki\Page\ProperPageIdentity;
use StatusValue;
use Wikimedia\Assert\Assert;
use Wikimedia\Rdbms\IDBAccessObject;

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

	private ?WeightedTagsUpdater $weightedTagsUpdater;

	public function __construct(
		ConfigurationLoader $configurationLoader,
		LinkRecommendationProvider $linkRecommendationProvider,
		LinkRecommendationStore $linkRecommendationStore,
		?WeightedTagsUpdater $weightedTagsUpdaterProvider
	) {
		$this->configurationLoader = $configurationLoader;
		$this->linkRecommendationProvider = $linkRecommendationProvider;
		$this->linkRecommendationStore = $linkRecommendationStore;
		$this->weightedTagsUpdater = $weightedTagsUpdaterProvider;
	}

	/**
	 * Return the link recommendation stored for the given title.
	 * Returns null when all links of the recommendation are invalid.
	 * @param LinkTarget $title
	 * @return LinkRecommendation|null
	 * @throws ErrorException
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
		return $linkRecommendation;
	}

	/**
	 * Delete recommendations for a given page from the database and optionally from the search index.
	 * @param ProperPageIdentity $pageIdentity
	 * @param bool $deleteFromSearchIndex
	 * @param bool $allowJoiningSearchIndexDeletes if true, then CirrusSearch will join this with
	 *                                             other events from the revision, waiting for up to 10 minutes
	 */
	public function deleteLinkRecommendation(
		ProperPageIdentity $pageIdentity,
		bool $deleteFromSearchIndex,
		bool $allowJoiningSearchIndexDeletes = false
	): void {
		if (
			$this->linkRecommendationStore->getByPageId( $pageIdentity->getId(), IDBAccessObject::READ_NORMAL ) !== null
		) {
			DeferredUpdates::addCallableUpdate( function () use ( $pageIdentity ) {
				$this->linkRecommendationStore->deleteByPageIds( [ $pageIdentity->getId() ] );
			} );
		}
		if ( $deleteFromSearchIndex ) {
			Assert::invariant(
				$this->weightedTagsUpdater !== null,
				'CirrusSearch is required if Link Recommendations are enabled'
			);
			DeferredUpdates::addCallableUpdate( function () use ( $pageIdentity, $allowJoiningSearchIndexDeletes ) {
				$this->weightedTagsUpdater->resetWeightedTags(
					$pageIdentity,
					[ LinkRecommendationTaskTypeHandler::WEIGHTED_TAG_PREFIX ],
					$allowJoiningSearchIndexDeletes ? 'revision' : null,
				);
			} );
		}
	}

	private function configError( string $errorMessage ): ErrorException {
		Util::logException( new WikiConfigException( $errorMessage ) );
		return new ErrorException( StatusValue::newFatal( 'rawmessage', $errorMessage ) );
	}

}
