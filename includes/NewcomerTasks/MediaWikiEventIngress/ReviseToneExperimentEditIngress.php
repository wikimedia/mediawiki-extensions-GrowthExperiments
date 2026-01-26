<?php
namespace GrowthExperiments\NewcomerTasks\MediaWikiEventIngress;

use GrowthExperiments\EventLogging\ReviseToneExperimentInteractionLogger;
use MediaWiki\DomainEvent\DomainEventIngress;
use MediaWiki\Page\Event\PageLatestRevisionChangedEvent;
use MediaWiki\Page\Event\PageLatestRevisionChangedListener;

class ReviseToneExperimentEditIngress extends DomainEventIngress implements PageLatestRevisionChangedListener {

	public function __construct(
		private readonly ReviseToneExperimentInteractionLogger $experimentInteractionLogger,
	) {
	}

	public function handlePageLatestRevisionChangedEvent( PageLatestRevisionChangedEvent $event ): void {
		$editResult = $event->getEditResult();
		if ( $editResult == null || $editResult->isNullEdit() || $event->isRevert() ) {
			return;
		}

		$this->experimentInteractionLogger->log( 'edit_saved', [
			'instrument_name' => 'Edit saved',
			'page' => [
				'namespace_id' => $event->getPage()->getNamespace(),
				'revision_id' => $event->getLatestRevisionAfter(),
			],
		] );
	}
}
