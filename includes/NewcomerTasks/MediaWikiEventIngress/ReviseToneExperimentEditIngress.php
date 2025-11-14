<?php
namespace GrowthExperiments\NewcomerTasks\MediaWikiEventIngress;

use GrowthExperiments\ExperimentXLabManager;
use MediaWiki\DomainEvent\DomainEventIngress;
use MediaWiki\Extension\MetricsPlatform\XLab\ExperimentManager;
use MediaWiki\Page\Event\PageLatestRevisionChangedEvent;
use MediaWiki\Page\Event\PageLatestRevisionChangedListener;

class ReviseToneExperimentEditIngress extends DomainEventIngress implements PageLatestRevisionChangedListener {

	public function __construct(
		private readonly ?ExperimentManager $experimentManager = null
	) {
	}

	public function handlePageLatestRevisionChangedEvent( PageLatestRevisionChangedEvent $event ): void {
		// Is MetricsPlatform loaded?
		if ( !$this->experimentManager ) {
			return;
		}

		$editResult = $event->getEditResult();
		if ( $editResult == null || $editResult->isNullEdit() || $event->isRevert() ) {
			return;
		}

		$experiment = $this->experimentManager->getExperiment(
			ExperimentXLabManager::REVISE_TONE_EXPERIMENT
		);
		$experiment->send( 'edit_saved', [
			// TODO add additional props, maybe nothing is needed but to check that all contextual attributes are
			// properly added by the Metrics client
		] );
	}
}
