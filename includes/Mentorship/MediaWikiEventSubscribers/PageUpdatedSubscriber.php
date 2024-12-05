<?php

declare( strict_types = 1 );

namespace GrowthExperiments\Mentorship\MediaWikiEventSubscribers;

use GrowthExperiments\Mentorship\Store\MentorStore;
use MediaWiki\DomainEvent\EventSubscriberBase;
use MediaWiki\Storage\PageUpdatedEvent;
use MediaWiki\User\UserIdentity;

class PageUpdatedSubscriber extends EventSubscriberBase {

	private MentorStore $mentorStore;

	public function __construct( MentorStore $mentorStore ) {
		$this->mentorStore = $mentorStore;
	}

	public function handlePageUpdatedEventAfterCommit( PageUpdatedEvent $event ): void {
		$this->setMenteeActive( $event->getAuthor() );
	}

	private function setMenteeActive( UserIdentity $user ): void {
		if ( $this->mentorStore->isMentee( $user ) ) {
			$this->mentorStore->markMenteeAsActive( $user );
		}
	}
}
