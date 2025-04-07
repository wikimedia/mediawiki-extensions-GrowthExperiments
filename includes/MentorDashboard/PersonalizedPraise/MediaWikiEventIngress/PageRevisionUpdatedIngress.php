<?php

namespace GrowthExperiments\MentorDashboard\PersonalizedPraise\MediaWikiEventIngress;

use GrowthExperiments\MentorDashboard\PersonalizedPraise\PraiseworthyConditionsLookup;
use GrowthExperiments\MentorDashboard\PersonalizedPraise\PraiseworthyMenteeSuggester;
use GrowthExperiments\Mentorship\IMentorManager;
use GrowthExperiments\UserImpact\UserImpactLookup;
use MediaWiki\Config\Config;
use MediaWiki\DomainEvent\DomainEventIngress;
use MediaWiki\Page\Event\PageRevisionUpdatedEvent;

/**
 * Event subscriber for PersonalizedPraise functionality.
 * Handles PageUpdated events to check for praiseworthy edits.
 *
 */
class PageRevisionUpdatedIngress extends DomainEventIngress {
	private Config $config;
	private IMentorManager $mentorManager;
	private UserImpactLookup $userImpactLookup;
	private PraiseworthyConditionsLookup $praiseworthyConditionsLookup;
	private PraiseworthyMenteeSuggester $praiseworthyMenteeSuggester;

	/**
	 * @param Config $config Config
	 * @param IMentorManager $mentorManager Service to get mentor information
	 * @param UserImpactLookup $userImpactLookup Service to evaluate user contributions
	 * @param PraiseworthyConditionsLookup $praiseworthyConditionsLookup Service to check praiseworthy conditions
	 * @param PraiseworthyMenteeSuggester $praiseworthyMenteeSuggester Service to mark mentees as praiseworthy
	 */
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
