<?php
namespace GrowthExperiments\NewcomerTasks\MediaWikiEventIngress;

use GrowthExperiments\ExperimentXLabManager;
use MediaWiki\DomainEvent\DomainEventIngress;
use MediaWiki\Extension\MetricsPlatform\XLab\ExperimentManager;
use MediaWiki\Page\Event\PageLatestRevisionChangedEvent;
use MediaWiki\Page\Event\PageLatestRevisionChangedListener;
use Psr\Log\LoggerInterface;

class ReviseToneExperimentEditIngress extends DomainEventIngress implements PageLatestRevisionChangedListener {

	public function __construct(
		private readonly LoggerInterface $logger,
		private readonly ?ExperimentManager $experimentManager = null,
	) {
	}

	public function handlePageLatestRevisionChangedEvent( PageLatestRevisionChangedEvent $event ): void {
		$revId = $event->getLatestRevisionAfter()->getId();
		$this->logger->debug( 'ReviseToneExperimentEditIngress, processing {rev_id}: start', [
			'rev_id' => $revId,
		] );
		// Is MetricsPlatform loaded?
		if ( !$this->experimentManager ) {
			return;
		}
		$this->logger->debug( 'ReviseToneExperimentEditIngress, processing {rev_id}: MetricsPlatform loaded', [
			'rev_id' => $revId,
		] );

		$editResult = $event->getEditResult();
		if ( $editResult == null || $editResult->isNullEdit() || $event->isRevert() ) {
			return;
		}
		$this->logger->debug( 'ReviseToneExperimentEditIngress, processing {rev_id}: get experiment info', [
			'rev_id' => $revId,
		] );
		$experiment = $this->experimentManager->getExperiment(
			ExperimentXLabManager::REVISE_TONE_EXPERIMENT
		);
		$this->logger->debug( 'ReviseToneExperimentEditIngress, processing {rev_id}: experiment configuration', [
			'rev_id' => $revId,
			...$experiment->getExperimentConfig(),
		] );
		$experiment->send( 'edit_saved', [
			'page' => [
				'revision_id' => $revId,
				'namespace_id' => $event->getLatestRevisionAfter()->getPage()->getNamespace(),
			],
		] );
		$this->logger->debug( 'ReviseToneExperimentEditIngress, processing {rev_id}: end', [
			'rev_id' => $revId,
			...$experiment->getExperimentConfig(),
		] );
	}
}
