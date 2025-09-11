<?php

declare( strict_types = 1 );

namespace GrowthExperiments\Mentorship\MediaWikiEventIngress;

use GrowthExperiments\Mentorship\MenteeGraduation;
use GrowthExperiments\Mentorship\Store\MentorStore;
use MediaWiki\DomainEvent\DomainEventIngress;
use MediaWiki\Page\Event\PageRevisionUpdatedEvent;
use MediaWiki\Page\Event\PageRevisionUpdatedListener;
use MediaWiki\User\UserIdentity;

class PageRevisionUpdatedIngress extends DomainEventIngress implements PageRevisionUpdatedListener {

	public function __construct(
		private MentorStore $mentorStore,
		private MenteeGraduation $menteeGraduation
	) {
	}

	public function handlePageRevisionUpdatedEvent( PageRevisionUpdatedEvent $event ): void {
		$this->setMenteeActive( $event->getAuthor() );
		$this->handleMenteeGraduation( $event->getAuthor() );
	}

	private function setMenteeActive( UserIdentity $user ): void {
		if ( $this->mentorStore->isMentee( $user ) ) {
			$this->mentorStore->markMenteeAsActive( $user );
		}
	}

	private function handleMenteeGraduation( UserIdentity $user ): void {
		if (
			$this->menteeGraduation->getIsEnabled() &&
			$this->menteeGraduation->shouldUserBeGraduated( $user )
		) {
			$this->menteeGraduation->graduateUserFromMentorship( $user );
		}
	}
}
