<?php

namespace GrowthExperiments\MentorDashboard\PersonalizedPraise\MediaWikiEventIngress;

use GrowthExperiments\MentorDashboard\PersonalizedPraise\PraiseworthyConditionsLookup;
use GrowthExperiments\MentorDashboard\PersonalizedPraise\PraiseworthyMenteeSuggester;
use GrowthExperiments\Mentorship\IMentorManager;
use GrowthExperiments\UserImpact\UserImpactLookup;
use MediaWiki\Config\Config;
use MediaWiki\DomainEvent\DomainEventIngress;
use MediaWiki\Page\Event\PageRevisionUpdatedEvent;
use MediaWiki\Page\Event\PageRevisionUpdatedListener;

/**
 * Event subscriber for PersonalizedPraise functionality.
 * Handles PageUpdated events to check for praiseworthy edits.
 */
class PageRevisionUpdatedIngress extends DomainEventIngress implements PageRevisionUpdatedListener {
	private Config $config;
	private IMentorManager $mentorManager;
	private UserImpactLookup $userImpactLookup;
	private PraiseworthyConditionsLookup $praiseworthyConditionsLookup;
	private PraiseworthyMenteeSuggester $praiseworthyMenteeSuggester;

	public function __construct(
		Config $config,
		IMentorManager $mentorManager,
		UserImpactLookup $userImpactLookup,
		PraiseworthyConditionsLookup $praiseworthyConditionsLookup,
		PraiseworthyMenteeSuggester $praiseworthyMenteeSuggester
	) {
		$this->config = $config;
		$this->mentorManager = $mentorManager;
		$this->userImpactLookup = $userImpactLookup;
		$this->praiseworthyConditionsLookup = $praiseworthyConditionsLookup;
		$this->praiseworthyMenteeSuggester = $praiseworthyMenteeSuggester;
	}

	/**
	 * Handler for PageUpdated events.
	 * Executed after database commit.
	 *
	 * @param PageRevisionUpdatedEvent $event The page update event
	 */
	public function handlePageRevisionUpdatedEvent( PageRevisionUpdatedEvent $event ): void {
		if ( !$this->config->get( 'GEPersonalizedPraiseBackendEnabled' ) ) {
			return;
		}

		$user = $event->getPerformer();

		$mentor = $this->mentorManager->getMentorForUserIfExists( $user );
		if ( !$mentor ) {
			return;
		}

		$menteeImpact = $this->userImpactLookup->getUserImpact( $user );
		if ( !$menteeImpact ) {
			return;
		}

		if ( $this->praiseworthyConditionsLookup->isMenteePraiseworthyForMentor(
			$menteeImpact,
			$mentor->getUserIdentity()
		) ) {
			$this->praiseworthyMenteeSuggester->markMenteeAsPraiseworthy(
				$menteeImpact,
				$mentor->getUserIdentity()
			);
		}
	}
}
