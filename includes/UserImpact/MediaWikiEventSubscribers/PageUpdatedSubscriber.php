<?php

declare( strict_types = 1 );

namespace GrowthExperiments\UserImpact\MediaWikiEventSubscribers;

use GrowthExperiments\UserImpact\GrowthExperimentsUserImpactUpdater;
use MediaWiki\DomainEvent\EventSubscriberBase;
use MediaWiki\Page\Event\PageUpdatedEvent;

class PageUpdatedSubscriber extends EventSubscriberBase {

	private GrowthExperimentsUserImpactUpdater $userImpactUpdater;

	public function __construct(
		GrowthExperimentsUserImpactUpdater $userImpactUpdater
	) {
		$this->userImpactUpdater = $userImpactUpdater;
	}

	public function handlePageUpdatedEventAfterCommit( PageUpdatedEvent $event ): void {
		$userIdentity = $event->getAuthor();
		$revisionRecord = $event->getNewRevision();
		// Refresh the user's impact after they've made an edit.
		if ( $this->userImpactUpdater->userIsInCohort( $userIdentity ) &&
			$userIdentity->equals( $revisionRecord->getUser() )
		) {
			$this->userImpactUpdater->refreshUserImpactData( $userIdentity );
		}
	}
}
