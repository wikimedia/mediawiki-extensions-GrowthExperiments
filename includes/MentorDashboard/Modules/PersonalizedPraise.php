<?php

namespace GrowthExperiments\MentorDashboard\Modules;

use GrowthExperiments\MentorDashboard\PersonalizedPraise\PersonalizedPraiseSettings;
use GrowthExperiments\MentorDashboard\PersonalizedPraise\PraiseworthyConditionsLookup;
use GrowthExperiments\MentorDashboard\PersonalizedPraise\PraiseworthyMenteeSuggester;
use GrowthExperiments\UserImpact\UserImpact;
use MediaWiki\Cache\GenderCache;
use MediaWiki\Context\IContextSource;
use MediaWiki\Html\Html;
use MediaWiki\Json\FormatJson;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\Title\Title;

class PersonalizedPraise extends BaseModule {

	private PraiseworthyMenteeSuggester $praiseworthyMenteeSuggester;
	private PersonalizedPraiseSettings $personalizedPraiseSettings;
	private GenderCache $genderCache;

	public function __construct(
		IContextSource $ctx,
		PraiseworthyMenteeSuggester $praiseworthyMenteeSuggester,
		PersonalizedPraiseSettings $personalizedPraiseSettings,
		GenderCache $genderCache
	) {
		parent::__construct( 'personalized-praise', $ctx );

		$this->praiseworthyMenteeSuggester = $praiseworthyMenteeSuggester;
		$this->personalizedPraiseSettings = $personalizedPraiseSettings;
		$this->genderCache = $genderCache;
	}

	/** @inheritDoc */
	protected function getHeaderText() {
		return $this->msg( 'growthexperiments-mentor-dashboard-personalized-praise-title' )->text();
	}

	/** @inheritDoc */
	protected function getSubheaderTag() {
		return 'div';
	}

	/** @inheritDoc */
	protected function getBody() {
		return Html::rawElement(
			'div',
			[
				'id' => 'vue-root-personalizedpraise',
				'class' => 'growthexperiments-mentor-dashboard-module-mentee-overview-content',
			],
			Html::element(
				'p',
				[ 'class' => 'growthexperiments-mentor-dashboard-no-js-fallback' ],
				$this->msg( 'growthexperiments-mentor-dashboard-mentee-overview-no-js-fallback' )->text()
			)
		);
	}

	/**
	 * List of user impacts for praiseworthy mentees
	 *
	 * @return UserImpact[]
	 */
	private function getPraiseworthyMentees(): array {
		return array_values(
			$this->praiseworthyMenteeSuggester->getPraiseworthyMenteesForMentor(
				$this->getUser()
			)
		);
	}

	/**
	 * Check which users have Flow on their talk pages
	 *
	 * This method checks which praiseworthy mentees make use of Flow in their user talk pages,
	 * so UserCard.vue can calculate the number of topics accordingly. The method can be called
	 * even when Flow is not installed (in that case, it short-circuits and always returns false).
	 *
	 * @return array Dictionary of username => bool
	 */
	private function getFlowEnrollmentStatuses(): array {
		$usernames = array_map( static function ( UserImpact $mentee ) {
			return $mentee->getUser()->getName();
		}, $this->getPraiseworthyMentees() );

		if ( !ExtensionRegistry::getInstance()->isLoaded( 'Flow' ) ) {
			return array_fill_keys( $usernames, false );
		}

		$result = [];
		foreach ( $usernames as $username ) {
			$result[$username] = Title::newFromText( $username, NS_USER_TALK )->getContentModel()
				=== CONTENT_MODEL_FLOW_BOARD;
		}
		return $result;
	}

	/**
	 * Get an array of mentee objects with gender property.
	 *
	 * This function clones the mentee objects from getPraiseworthyMentees()
	 * and adds a gender property, based on the genderCache service.
	 * The gender property can be 'male', 'female', 'unknown' or null.
	 *
	 * @return array An array of mentee objects with gender property
	 */
	protected function getMenteeGenders() {
		$praiseworthyMentees = $this->getPraiseworthyMentees();
		$menteeGenders = [];

		foreach ( $praiseworthyMentees as $mentee ) {
			$userId = $mentee->getUser()->getId();
			$menteeGenders[$userId] = $this->genderCache->getGenderOf( $mentee->getUser()->getName(), __METHOD__ );
		}

		return $menteeGenders;
	}

	/** @inheritDoc */
	protected function getJsConfigVars() {
		return [
			'GEPraiseworthyMentees' => $this->getPraiseworthyMentees(),
			'GEPraiseworthyMenteesByFlowStatus' => $this->getFlowEnrollmentStatuses(),
			'GEPersonalizedPraiseSettings' => FormatJson::encode( $this->personalizedPraiseSettings->toArray(
				$this->getUser()
			) ),
			'GEPraiseworthyMessageSubject' => $this->personalizedPraiseSettings->getPraisingMessageDefaultSubject(
				$this->getUser()
			),
			'GEPraiseworthyMessageUserTitle' => $this->personalizedPraiseSettings->getPraisingMessageUserTitle(
				$this->getUser()
			)->getPrefixedText(),
			'GEPraiseworthyMessageTitle' => $this->personalizedPraiseSettings->getPraisingMessageTitle(
				$this->getUser()
			)->getPrefixedText(),
			'GEPersonalizedPraiseNotificationsEnabled' => $this->getConfig()->get(
				'GEPersonalizedPraiseNotificationsEnabled'
			),
			'GEPersonalizedPraiseSkipMenteesForDays' =>
				PraiseworthyConditionsLookup::SKIP_MENTEES_FOR_DAYS,
			'GEMenteeGenders' => $this->getMenteeGenders(),
		];
	}

	/** @inheritDoc */
	protected function getMobileSummaryBody() {
		return $this->getBody();
	}
}
