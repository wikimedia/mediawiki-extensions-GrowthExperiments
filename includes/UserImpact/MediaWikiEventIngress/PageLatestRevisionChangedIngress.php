<?php

declare( strict_types = 1 );

namespace GrowthExperiments\UserImpact\MediaWikiEventIngress;

use GrowthExperiments\UserImpact\GrowthExperimentsUserImpactUpdater;
use MediaWiki\DomainEvent\DomainEventIngress;
use MediaWiki\Page\Event\PageLatestRevisionChangedEvent;
use MediaWiki\Page\Event\PageLatestRevisionChangedListener;

class PageLatestRevisionChangedIngress extends DomainEventIngress implements
	PageLatestRevisionChangedListener
{

	private GrowthExperimentsUserImpactUpdater $userImpactUpdater;

	public function __construct(
		GrowthExperimentsUserImpactUpdater $userImpactUpdater
	) {
		$this->userImpactUpdater = $userImpactUpdater;
	}

	public function handlePageLatestRevisionChangedEvent(
		PageLatestRevisionChangedEvent $event
	): void {
		$userIdentity = $event->getAuthor();
		$revisionRecord = $event->getLatestRevisionAfter();
		// Refresh the user's impact after they've made an edit.
		if ( $this->userImpactUpdater->userIsInCohort( $userIdentity ) &&
			$userIdentity->equals( $revisionRecord->getUser() )
		) {
			$this->userImpactUpdater->refreshUserImpactData( $userIdentity );
		}
	}
}
