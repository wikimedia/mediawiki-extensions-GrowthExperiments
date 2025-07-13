<?php

declare( strict_types = 1 );

namespace GrowthExperiments\NewcomerTasks\MediaWikiEventIngress;

use GrowthExperiments\NewcomerTasks\AddLink\LinkRecommendationHelper;
use GrowthExperiments\NewcomerTasks\TaskType\ImageRecommendationTaskTypeHandler;
use GrowthExperiments\NewcomerTasks\TaskType\LinkRecommendationTaskTypeHandler;
use GrowthExperiments\NewcomerTasks\TaskType\SectionImageRecommendationTaskTypeHandler;
use GrowthExperiments\NewcomerTasks\TaskType\TaskTypeHandler;
use GrowthExperiments\NewcomerTasks\TaskType\TemplateBasedTaskTypeHandler;
use GrowthExperiments\Util;
use MediaWiki\ChangeTags\ChangeTagsStore;
use MediaWiki\DomainEvent\DomainEventIngress;
use MediaWiki\Page\Event\PageRevisionUpdatedEvent;
use MediaWiki\Page\ProperPageIdentity;
use MediaWiki\Storage\EditResult;
use MediaWiki\WikiMap\WikiMap;
use Wikimedia\Rdbms\DBReadOnlyError;
use Wikimedia\Rdbms\IConnectionProvider;
use Wikimedia\Stats\StatsFactory;

class PageRevisionUpdatedIngress extends DomainEventIngress {

	private ChangeTagsStore $changeTagsStore;
	private IConnectionProvider $connectionProvider;
	private StatsFactory $statsFactory;
	private LinkRecommendationHelper $linkRecommendationHelper;

	public function __construct(
		ChangeTagsStore $changeTagsStore,
		IConnectionProvider $connectionProvider,
		StatsFactory $statsFactory,
		LinkRecommendationHelper $linkRecommendationHelper
	) {
		$this->changeTagsStore = $changeTagsStore;
		$this->connectionProvider = $connectionProvider;
		$this->statsFactory = $statsFactory;
		$this->linkRecommendationHelper = $linkRecommendationHelper;
	}

	public function handlePageRevisionUpdatedEvent( PageRevisionUpdatedEvent $event ): void {
		if (
			Util::isLinkRecommendationsAvailable() &&
			$event->getPage()->getNamespace() === NS_MAIN &&
			!$event->isCreation()
		) {
			$this->clearLinkRecommendationRecordForPage( $event->getPage() );
		}

		$editResult = $event->getEditResult();
		if ( $editResult !== null && $editResult->isRevert() ) {
			$this->trackRevertedNewcomerTaskEdit( $editResult );
		}
	}

	private function clearLinkRecommendationRecordForPage( ProperPageIdentity $pageIdentity ): void {
		try {
			$this->linkRecommendationHelper->deleteLinkRecommendation(
				$pageIdentity,
				true,
				true
			);
		} catch ( DBReadOnlyError ) {
			// Leaving a dangling DB row behind doesn't cause any problems so just ignore this.
		}
	}

	private function trackRevertedNewcomerTaskEdit( EditResult $editResult ): void {
		$revId = $editResult->getNewestRevertedRevisionId();
		if ( !$revId ) {
			return;
		}
		$tags = $this->changeTagsStore->getTags(
			$this->connectionProvider->getReplicaDatabase(),
			null,
			$revId
		);
		$growthTasksChangeTags = array_merge(
			TemplateBasedTaskTypeHandler::NEWCOMER_TASK_TEMPLATE_BASED_ALL_CHANGE_TAGS,
			[
				LinkRecommendationTaskTypeHandler::CHANGE_TAG,
				ImageRecommendationTaskTypeHandler::CHANGE_TAG,
				SectionImageRecommendationTaskTypeHandler::CHANGE_TAG,
			]
		);
		foreach ( $tags as $tag ) {
			// We can use more precise tags, skip this generic one applied to all suggested edits.
			if ( $tag === TaskTypeHandler::NEWCOMER_TASK_TAG ||
				// ...but make sure the tag is one we care about tracking.
				!in_array( $tag, $growthTasksChangeTags ) ) {
				continue;
			}
			// HACK: craft the task type ID from the change tag. We should probably add a method to
			// TaskTypeHandlerRegistry to get a TaskType from a change tag.
			$taskType = str_replace( 'newcomer task ', '', $tag );
			if ( $tag === LinkRecommendationTaskTypeHandler::CHANGE_TAG ) {
				$taskType = LinkRecommendationTaskTypeHandler::TASK_TYPE_ID;
			} elseif ( $tag === ImageRecommendationTaskTypeHandler::CHANGE_TAG ) {
				$taskType = ImageRecommendationTaskTypeHandler::TASK_TYPE_ID;
			} elseif ( $tag === SectionImageRecommendationTaskTypeHandler::CHANGE_TAG ) {
				$taskType = SectionImageRecommendationTaskTypeHandler::TASK_TYPE_ID;
			}
			$wiki = WikiMap::getCurrentWikiId();
			$this->statsFactory
				->withComponent( 'GrowthExperiments' )
				->getCounter( 'newcomertask_reverted_total' )
				->setLabel( 'taskType', $taskType )
				->setLabel( 'wiki', $wiki )
				->increment();
		}
	}

}
