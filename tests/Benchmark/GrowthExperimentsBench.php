<?php

namespace GrowthExperiments\Tests\Benchmark;

use GrowthExperiments\GrowthExperimentsServices;
use GrowthExperiments\NewcomerTasks\AddLink\LinkRecommendation;
use GrowthExperiments\NewcomerTasks\AddLink\LinkRecommendationMetadata;
use GrowthExperiments\NewcomerTasks\LinkRecommendationFilter;
use GrowthExperiments\NewcomerTasks\Task\Task;
use GrowthExperiments\NewcomerTasks\Task\TaskSet;
use GrowthExperiments\NewcomerTasks\Task\TaskSetFilters;
use GrowthExperiments\NewcomerTasks\TaskSuggester\SearchTaskSuggester;
use GrowthExperiments\NewcomerTasks\TaskSuggester\StaticTaskSuggesterFactory;
use GrowthExperiments\NewcomerTasks\TaskSuggester\TaskSuggester;
use GrowthExperiments\NewcomerTasks\TaskType\LinkRecommendationTaskType;
use GrowthExperiments\NewcomerTasks\TaskType\TaskType;
use MediaWiki\CommentStore\CommentStoreComment;
use MediaWiki\Content\TextContent;
use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\Title\TitleValue;
use MediaWiki\User\User;
use MediaWiki\User\UserIdentityValue;
use StatusValue;

class GrowthExperimentsBench {

	/** @var TaskSuggester */
	private $taskSuggester;
	/** @var LinkRecommendationFilter */
	protected $linkRecommendationFilter;
	/** @var TaskSet|StatusValue */
	protected $tasks;

	public function setUpLinkRecommendation() {
		$tasks = [];
		$services = MediaWikiServices::getInstance();
		$linkRecommendationTaskType = new LinkRecommendationTaskType(
			'link-recommendation', TaskType::DIFFICULTY_EASY, []
		);
		$wikiPageFactory = $services->getWikiPageFactory();
		$growthServices = GrowthExperimentsServices::wrap( $services );
		$linkRecommendationStore = $growthServices->getLinkRecommendationStore();
		$user = User::newSystemUser( 'phpbench' );
		for ( $i = 0; $i < SearchTaskSuggester::DEFAULT_LIMIT; $i++ ) {
			$title = 'PHPBenchPage-' . rand( 0, 100000 );
			$linkTarget = new TitleValue( NS_MAIN, $title );
			$page = $wikiPageFactory->newFromLinkTarget( $linkTarget );
			$pageUpdater = $page->newPageUpdater( $user );
			$pageUpdater->setContent( SlotRecord::MAIN, new TextContent( 'phpbench' ) );
			$revisionRecord = $pageUpdater->saveRevision( CommentStoreComment::newUnsavedComment( 'phpbench' ) );
			$tasks[] = new Task(
				$linkRecommendationTaskType,
				$linkTarget
			);
			$linkRecommendation = new LinkRecommendation(
				$linkTarget,
				$page->getId(),
				$revisionRecord ? $revisionRecord->getId() : $page->getRevisionRecord()->getId(),
				[],
				new LinkRecommendationMetadata( 1, 2, [], 0 )
			);
			$linkRecommendationStore->insertExistingLinkRecommendation( $linkRecommendation );
		}
		$taskSuggesterFactory = new StaticTaskSuggesterFactory(
			$tasks,
			$services->getTitleFactory()
		);
		$this->taskSuggester = $taskSuggesterFactory->create();
		$this->linkRecommendationFilter = $growthServices->getLinkRecommendationFilter();
		// Pre-warm cache.
		$this->tasks = $this->taskSuggester->suggest(
			new UserIdentityValue( 1, 'Admin' ),
			new TaskSetFilters( [ 'link-recommendation' ] )
		);
	}

	public function tearDownLinkRecommendation() {
		$services = MediaWikiServices::getInstance();
		$linkRecommendationStore = GrowthExperimentsServices::wrap( $services )->getLinkRecommendationStore();
		$wikiPageFactory = $services->getWikiPageFactory();
		$deletePageFactory = $services->getDeletePageFactory();
		$phpbenchUser = User::newSystemUser( 'phpbench' );
		foreach ( $this->tasks as $task ) {
			$linkRecommendationStore->deleteByLinkTarget( $task->getTitle() );
			$page = $wikiPageFactory->newFromLinkTarget( $task->getTitle() );
			$deletePage = $deletePageFactory->newDeletePage(
				$page,
				$phpbenchUser
			);
			$deletePage->deleteUnsafe( 'phpbench' );
		}
	}
}
