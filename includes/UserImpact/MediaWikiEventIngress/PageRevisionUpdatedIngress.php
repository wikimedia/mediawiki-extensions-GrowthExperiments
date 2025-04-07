<?php

declare( strict_types = 1 );

namespace GrowthExperiments\UserImpact\MediaWikiEventIngress;

use GrowthExperiments\UserImpact\GrowthExperimentsUserImpactUpdater;
use MediaWiki\DomainEvent\DomainEventIngress;
use MediaWiki\Page\Event\PageRevisionUpdatedEvent;

class PageRevisionUpdatedIngress extends DomainEventIngress {

	private GrowthExperimentsUserImpactUpdater $userImpactUpdater;

	public function __construct(
		GrowthExperimentsUserImpactUpdater $userImpactUpdater
	) {
		$this->userImpactUpdater = $userImpactUpdater;
	}

	public function handlePageRevisionUpdatedEvent( PageRevisionUpdatedEvent $event ): void {
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
