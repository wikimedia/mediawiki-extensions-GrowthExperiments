<?php

declare( strict_types = 1 );

namespace GrowthExperiments\NewcomerTasks\MediaWikiEventSubscribers;

use GrowthExperiments\NewcomerTasks\AddLink\LinkRecommendationHelper;
use GrowthExperiments\NewcomerTasks\TaskType\ImageRecommendationTaskTypeHandler;
use GrowthExperiments\NewcomerTasks\TaskType\LinkRecommendationTaskTypeHandler;
use GrowthExperiments\NewcomerTasks\TaskType\SectionImageRecommendationTaskTypeHandler;
use GrowthExperiments\NewcomerTasks\TaskType\TaskTypeHandler;
use GrowthExperiments\NewcomerTasks\TaskType\TemplateBasedTaskTypeHandler;
use MediaWiki\ChangeTags\ChangeTagsStore;
use MediaWiki\Config\Config;
use MediaWiki\DomainEvent\EventSubscriberBase;
use MediaWiki\Page\Event\PageUpdatedEvent;
use MediaWiki\Page\ProperPageIdentity;
use MediaWiki\Storage\EditResult;
use MediaWiki\WikiMap\WikiMap;
use Wikimedia\Rdbms\DBReadOnlyError;
use Wikimedia\Rdbms\ILoadBalancer;
use Wikimedia\Stats\StatsFactory;

class PageUpdatedSubscriber extends EventSubscriberBase {

	private ChangeTagsStore $changeTagsStore;
	private Config $config;
	private ILoadBalancer $lb;
	private StatsFactory $statsFactory;
	private LinkRecommendationHelper $linkRecommendationHelper;

	public function __construct(
		ChangeTagsStore $changeTagsStore,
		Config $config,
		ILoadBalancer $lb,
		StatsFactory $statsFactory,
		LinkRecommendationHelper $linkRecommendationHelper
	) {
		$this->changeTagsStore = $changeTagsStore;
		$this->config = $config;
		$this->lb = $lb;
		$this->statsFactory = $statsFactory;
		$this->linkRecommendationHelper = $linkRecommendationHelper;
	}

	public function handlePageUpdatedEventAfterCommit( PageUpdatedEvent $event ): void {
		if (
			$this->config->get( 'GENewcomerTasksLinkRecommendationsEnabled' ) &&
			!$event->isNew() &&
			$event->getPage()->getNamespace() === NS_MAIN
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
		} catch ( DBReadOnlyError $e ) {
			// Leaving a dangling DB row behind doesn't cause any problems so just ignore this.
		}
	}

	private function trackRevertedNewcomerTaskEdit( EditResult $editResult ): void {
		$revId = $editResult->getNewestRevertedRevisionId();
		if ( !$revId ) {
			return;
		}
		$tags = $this->changeTagsStore->getTags(
			$this->lb->getConnection( DB_REPLICA ),
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
				->copyToStatsdAt( sprintf( "$wiki.GrowthExperiments.NewcomerTask.Reverted.%s", $taskType ) )
				->increment();
		}
	}

}
