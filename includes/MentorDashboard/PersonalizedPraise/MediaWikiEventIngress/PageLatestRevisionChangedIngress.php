<?php

declare( strict_types = 1 );

namespace GrowthExperiments\MentorDashboard\PersonalizedPraise\MediaWikiEventIngress;

use GrowthExperiments\MentorDashboard\PersonalizedPraise\PraiseworthyConditionsLookup;
use GrowthExperiments\MentorDashboard\PersonalizedPraise\PraiseworthyMenteeSuggester;
use GrowthExperiments\Mentorship\IMentorManager;
use GrowthExperiments\UserImpact\UserImpactLookup;
use MediaWiki\Config\Config;
use MediaWiki\DomainEvent\DomainEventIngress;
use MediaWiki\Page\Event\PageLatestRevisionChangedEvent;
use MediaWiki\Page\Event\PageLatestRevisionChangedListener;
use MediaWiki\User\UserIdentityUtils;

/**
 * Event subscriber for PersonalizedPraise functionality.
 * Handles PageUpdated events to check for praiseworthy edits.
 */
class PageLatestRevisionChangedIngress extends DomainEventIngress implements PageLatestRevisionChangedListener {

	public function __construct(
		private readonly Config $config,
		private readonly UserIdentityUtils $userIdentityUtils,
		private readonly IMentorManager $mentorManager,
		private readonly UserImpactLookup $userImpactLookup,
		private readonly PraiseworthyConditionsLookup $praiseworthyConditionsLookup,
		private readonly PraiseworthyMenteeSuggester $praiseworthyMenteeSuggester
	) {
	}

	public function handlePageLatestRevisionChangedEvent( PageLatestRevisionChangedEvent $event ): void {
		if ( !$this->config->get( 'GEPersonalizedPraiseBackendEnabled' ) ) {
			return;
		}

		$user = $event->getPerformer();

		if ( !$this->userIdentityUtils->isNamed( $user ) ) {
			return;
		}

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
