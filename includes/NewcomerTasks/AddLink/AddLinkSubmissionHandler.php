<?php

namespace GrowthExperiments\NewcomerTasks\AddLink;

use GrowthExperiments\NewcomerTasks\AbstractSubmissionHandler;
use GrowthExperiments\NewcomerTasks\NewcomerTasksUserOptionsLookup;
use GrowthExperiments\NewcomerTasks\SubmissionHandler;
use GrowthExperiments\NewcomerTasks\Task\TaskSet;
use GrowthExperiments\NewcomerTasks\Task\TaskSetFilters;
use GrowthExperiments\NewcomerTasks\TaskSuggester\TaskSuggesterFactory;
use GrowthExperiments\NewcomerTasks\TaskType\LinkRecommendationTaskType;
use GrowthExperiments\NewcomerTasks\TaskType\LinkRecommendationTaskTypeHandler;
use GrowthExperiments\NewcomerTasks\TaskType\TaskType;
use MediaWiki\Cache\LinkBatchFactory;
use MediaWiki\Linker\LinkTarget;
use MediaWiki\Message\Message;
use MediaWiki\Page\ProperPageIdentity;
use MediaWiki\Title\MalformedTitleException;
use MediaWiki\Title\TitleFactory;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserIdentityUtils;
use Psr\Log\LoggerInterface;
use StatusValue;
use UnexpectedValueException;
use Wikimedia\Message\ListType;
use Wikimedia\Rdbms\DBReadOnlyError;
use Wikimedia\Rdbms\IDBAccessObject;

/**
 * Record the user's decision on the recommendations for a given page.
 */
class AddLinkSubmissionHandler extends AbstractSubmissionHandler implements SubmissionHandler {

	private LinkRecommendationHelper $linkRecommendationHelper;
	private LinkSubmissionRecorder $addLinkSubmissionRecorder;
	private TitleFactory $titleFactory;
	private UserIdentityUtils $userIdentityUtils;
	private LinkRecommendationStore $linkRecommendationStore;
	private LinkBatchFactory $linkBatchFactory;
	private TaskSuggesterFactory $taskSuggesterFactory;
	private NewcomerTasksUserOptionsLookup $newcomerTasksUserOptionsLookup;
	private LoggerInterface $logger;

	public function __construct(
		LinkRecommendationHelper $linkRecommendationHelper,
		LinkRecommendationStore $linkRecommendationStore,
		LinkSubmissionRecorder $addLinkSubmissionRecorder,
		LinkBatchFactory $linkBatchFactory,
		TitleFactory $titleFactory,
		UserIdentityUtils $userIdentityUtils,
		TaskSuggesterFactory $taskSuggesterFactory,
		NewcomerTasksUserOptionsLookup $newcomerTasksUserOptionsLookup,
		LoggerInterface $logger
	) {
		$this->linkRecommendationHelper = $linkRecommendationHelper;
		$this->addLinkSubmissionRecorder = $addLinkSubmissionRecorder;
		$this->titleFactory = $titleFactory;
		$this->linkRecommendationStore = $linkRecommendationStore;
		$this->linkBatchFactory = $linkBatchFactory;
		$this->userIdentityUtils = $userIdentityUtils;
		$this->taskSuggesterFactory = $taskSuggesterFactory;
		$this->newcomerTasksUserOptionsLookup = $newcomerTasksUserOptionsLookup;
		$this->logger = $logger;
	}

	/** @inheritDoc */
	public function validate(
		TaskType $taskType, ProperPageIdentity $page, UserIdentity $user, ?int $baseRevId, array $data
	): StatusValue {
		$title = $this->titleFactory->castFromPageIdentity( $page );
		if ( !$title ) {
			// Not really possible but makes phan happy.
			throw new UnexpectedValueException( 'Invalid title: '
				. $page->getNamespace() . ':' . $page->getDBkey() );
		}
		if ( !$this->linkRecommendationStore->getByLinkTarget( $title, IDBAccessObject::READ_LATEST ) ) {
			// There's no link recommendation data stored for this page, so it must have been
			// removed from the database during the time the user had the UI open. Don't allow
			// the save to continue.
			return StatusValue::newGood()->error( 'growthexperiments-addlink-notinstore',
				$title->getPrefixedText() );
		}
		$userErrorMessage = self::getUserErrorMessage( $this->userIdentityUtils, $user );
		if ( $userErrorMessage ) {
			return StatusValue::newGood()->error( $userErrorMessage );
		}
		return StatusValue::newGood();
	}

	/**
	 * @inheritDoc
	 * @throws MalformedTitleException
	 */
	public function handle(
		TaskType $taskType, ProperPageIdentity $page, UserIdentity $user, ?int $baseRevId, ?int $editRevId, array $data
	): StatusValue {
		// The latest revision is the saved edit, so we need to find the link recommendation based on the base
		// revision ID.
		$linkRecommendation = $baseRevId ? $this->linkRecommendationStore->getByRevId(
			$baseRevId,
			IDBAccessObject::READ_LATEST
		) : null;
		$title = $this->titleFactory->castFromPageIdentity( $page );
		if ( !$title ) {
			// This should never happen, it's here to make Phan happy.
			return StatusValue::newFatal( 'invalidtitle' );
		}
		if ( !$linkRecommendation ) {
			$this->logger->warning( 'Unable to find link recommendation for title {title} ' .
			  'with getByRevId(), using base revision ID of {baseRevId} and edit revision ID of ' .
			  '{editRevId}. Additional data: {data}', [
				'title' => $title->getPrefixedDBkey(),
				'baseRevId' => $baseRevId,
				'editRevId' => $editRevId,
				'data' => json_encode( $data )
			] );
			// Try to find the find the link recommendation based on the link target.
			$linkRecommendation = $this->linkRecommendationStore->getByLinkTarget(
				$title,
				IDBAccessObject::READ_LATEST,
				true
			);
		}

		if ( !$linkRecommendation ) {
			$this->logger->error( 'Unable to find link recommendation for title {title} ' .
			  'with getByLinkTarget using base revision ID of {baseRevId} and edit revision ID of ' .
			  '{editRevId}. Additional data: {data}', [
				'title' => $title->getPrefixedDBkey(),
				'baseRevId' => $baseRevId,
				'editRevId' => $editRevId,
				'data' => json_encode( $data )
			] );
			return StatusValue::newFatal( 'growthexperiments-addlink-handler-notfound' );
		}
		$links = $this->normalizeTargets( $linkRecommendation->getLinks() );

		$acceptedTargets = $this->normalizeTargets( $data['acceptedTargets'] ?: [] );
		$rejectedTargets = $this->normalizeTargets( $data['rejectedTargets'] ?: [] );
		$skippedTargets = $this->normalizeTargets( $data['skippedTargets'] ?: [] );

		$allTargets = array_merge( $acceptedTargets, $rejectedTargets, $skippedTargets );
		$unexpectedTargets = array_diff( $allTargets, $links );
		if ( $unexpectedTargets ) {
			return StatusValue::newFatal( 'growthexperiments-addlink-handler-wrongtargets',
				Message::listParam( $unexpectedTargets, ListType::COMMA ) );
		}

		$warnings = [];
		$taskSet = $this->taskSuggesterFactory->create()->suggest(
			$user,
			new TaskSetFilters(
				$this->newcomerTasksUserOptionsLookup->getTaskTypeFilter( $user ),
				$this->newcomerTasksUserOptionsLookup->getTopics( $user ),
				$this->newcomerTasksUserOptionsLookup->getTopicsMatchMode( $user )
			)
		);
		if ( $taskSet instanceof TaskSet ) {
			$qualityGateConfig = $taskSet->getQualityGateConfig();
			if ( $taskType instanceof LinkRecommendationTaskType
				&& isset( $qualityGateConfig[LinkRecommendationTaskTypeHandler::TASK_TYPE_ID]['dailyCount'] )
				&& $qualityGateConfig[LinkRecommendationTaskTypeHandler::TASK_TYPE_ID]['dailyCount']
					>= $taskType->getMaxTasksPerDay() - 1
			) {
				$warnings['gelinkrecommendationdailytasksexceeded'] = true;
			}
		}

		try {
			$this->linkRecommendationHelper->deleteLinkRecommendation(
				$page,
				// FIXME T283606: In theory if $editRevId is set (this is a real edit, not a null edit that
				//   happens when the user accepted nothing), we can leave search index updates to the
				//   SearchDataForIndex hook. In practice that does not work because we delete the DB row
				//   here so the hook logic will assume there's nothing to do. Might want to improve that
				//   in the future.
				true
			);
			$status = $this->addLinkSubmissionRecorder->record( $user, $linkRecommendation, $acceptedTargets,
				$rejectedTargets, $skippedTargets, $editRevId );
			$status->merge(
				StatusValue::newGood( [ 'warnings' => $warnings, 'logId' => $status->getValue() ] ),
				true
			);
		} catch ( DBReadOnlyError ) {
			$status = StatusValue::newFatal( 'readonly' );
		}
		return $status;
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
