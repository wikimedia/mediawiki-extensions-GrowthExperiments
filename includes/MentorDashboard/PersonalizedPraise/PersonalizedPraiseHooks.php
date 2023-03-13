<?php

namespace GrowthExperiments\MentorDashboard\PersonalizedPraise;

use Config;
use DeferredUpdates;
use GrowthExperiments\Mentorship\MentorManager;
use GrowthExperiments\UserImpact\UserImpactLookup;
use MediaWiki\Preferences\Hook\GetPreferencesHook;
use MediaWiki\Storage\Hook\PageSaveCompleteHook;
use MediaWiki\User\Hook\UserGetDefaultOptionsHook;

class PersonalizedPraiseHooks implements
	PageSaveCompleteHook,
	GetPreferencesHook,
	UserGetDefaultOptionsHook
{

	private Config $config;
	private MentorManager $mentorManager;
	private UserImpactLookup $userImpactLookup;
	private PraiseworthyConditionsLookup $praiseworthyConditionsLookup;
	private PraiseworthyMenteeSuggester $praiseworthyMenteeSuggester;

	/**
	 * @param Config $config
	 * @param MentorManager $mentorManager
	 * @param UserImpactLookup $userImpactLookup
	 * @param PraiseworthyConditionsLookup $praiseworthyConditionsLookup
	 * @param PraiseworthyMenteeSuggester $praiseworthyMenteeSuggester
	 */
	public function __construct(
		Config $config,
		MentorManager $mentorManager,
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
	 * @inheritDoc
	 */
	public function onPageSaveComplete(
		$wikiPage, $user, $summary, $flags, $revisionRecord, $editResult
	) {
		if ( !$this->config->get( 'GEPersonalizedPraiseBackendEnabled' ) ) {
			return;
		}

		DeferredUpdates::addCallableUpdate( function () use ( $user ) {
			$mentor = $this->mentorManager->getMentorForUserSafe( $user );
			if ( !$mentor ) {
				return;
			}

			$menteeImpact = $this->userImpactLookup->getUserImpact( $user );
			if (
				$menteeImpact &&
				$this->praiseworthyConditionsLookup->isMenteePraiseworthyForMentor(
					$menteeImpact,
					$mentor->getUserIdentity()
			) ) {
				$this->praiseworthyMenteeSuggester->markMenteeAsPraiseworthy(
					$menteeImpact, $mentor->getUserIdentity()
				);
			}
		} );
	}

	/**
	 * @inheritDoc
	 */
	public function onGetPreferences( $user, &$preferences ) {
		$preferences[ PraiseworthyConditionsLookup::WAS_PRAISED_PREF ] = [
			'type' => 'api'
		];
		$preferences[ PersonalizedPraiseSettings::PREF_NAME ] = [
			'type' => 'api'
		];
	}

	/**
	 * @inheritDoc
	 */
	public function onUserGetDefaultOptions( &$defaultOptions ) {
		$defaultOptions += [
			PraiseworthyConditionsLookup::WAS_PRAISED_PREF => false,
			PersonalizedPraiseSettings::PREF_NAME => '{}',
		];
	}
}
