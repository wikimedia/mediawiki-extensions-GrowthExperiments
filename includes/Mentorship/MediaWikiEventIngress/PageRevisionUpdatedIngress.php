<?php

declare( strict_types = 1 );

namespace GrowthExperiments\Mentorship\MediaWikiEventIngress;

use GrowthExperiments\Mentorship\Store\MentorStore;
use MediaWiki\DomainEvent\DomainEventIngress;
use MediaWiki\Page\Event\PageRevisionUpdatedEvent;
use MediaWiki\Page\Event\PageRevisionUpdatedListener;
use MediaWiki\User\UserIdentity;

class PageRevisionUpdatedIngress extends DomainEventIngress implements PageRevisionUpdatedListener {

	private MentorStore $mentorStore;

	public function __construct( MentorStore $mentorStore ) {
		$this->mentorStore = $mentorStore;
	}

	public function handlePageRevisionUpdatedEvent( PageRevisionUpdatedEvent $event ): void {
		$this->setMenteeActive( $event->getAuthor() );
	}

	private function setMenteeActive( UserIdentity $user ): void {
		if ( $this->mentorStore->isMentee( $user ) ) {
			$this->mentorStore->markMenteeAsActive( $user );
		}
	}
}
