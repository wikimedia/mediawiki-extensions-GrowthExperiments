<?php

namespace GrowthExperiments\MentorDashboard\PersonalizedPraise;

use GrowthExperiments\Mentorship\IMentorManager;
use GrowthExperiments\UserImpact\UserImpactLookup;
use MediaWiki\Config\Config;
use MediaWiki\Deferred\DeferredUpdates;
use MediaWiki\Preferences\Hook\GetPreferencesHook;
use MediaWiki\Storage\Hook\PageSaveCompleteHook;
use MediaWiki\User\Hook\UserGetDefaultOptionsHook;

class PersonalizedPraiseHooks implements
	PageSaveCompleteHook,
	GetPreferencesHook,
	UserGetDefaultOptionsHook
{

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
	 * @inheritDoc
	 */
	public function onPageSaveComplete(
		$wikiPage, $user, $summary, $flags, $revisionRecord, $editResult
	) {
		if ( !$this->config->get( 'GEPersonalizedPraiseBackendEnabled' ) ) {
			return;
		}

		DeferredUpdates::addCallableUpdate( function () use ( $user ) {
			$mentor = $this->mentorManager->getMentorForUserIfExists( $user );
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
		$preferences[ PraiseworthyConditionsLookup::SKIPPED_UNTIL_PREF ] = [
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
			PraiseworthyConditionsLookup::SKIPPED_UNTIL_PREF => null,
			PersonalizedPraiseSettings::PREF_NAME => '{}',
		];
	}
}
