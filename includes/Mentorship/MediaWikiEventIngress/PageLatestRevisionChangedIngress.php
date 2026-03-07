<?php

declare( strict_types = 1 );

namespace GrowthExperiments\Mentorship\MediaWikiEventIngress;

use GrowthExperiments\Mentorship\MenteeGraduation;
use GrowthExperiments\Mentorship\Store\MentorStore;
use MediaWiki\DomainEvent\DomainEventIngress;
use MediaWiki\Page\Event\PageLatestRevisionChangedEvent;
use MediaWiki\Page\Event\PageLatestRevisionChangedListener;
use MediaWiki\User\UserIdentity;

/**
 * @noinspection PhpUnused
 */
class PageLatestRevisionChangedIngress extends DomainEventIngress implements
	PageLatestRevisionChangedListener
{

	public function __construct(
		private MentorStore $mentorStore,
		private MenteeGraduation $menteeGraduation
	) {
	}

	public function handlePageLatestRevisionChangedEvent(
		PageLatestRevisionChangedEvent $event
	): void {
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
			$this->mentorStore->isMentee( $user ) &&
			$this->menteeGraduation->getIsEnabled() &&
			$this->menteeGraduation->shouldUserBeGraduated( $user )
		) {
			$this->menteeGraduation->graduateUserFromMentorship( $user );
		}
	}
}
