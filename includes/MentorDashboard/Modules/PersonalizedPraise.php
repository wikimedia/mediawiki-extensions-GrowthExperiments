<?php

namespace GrowthExperiments\MentorDashboard\Modules;

use ExtensionRegistry;
use FormatJson;
use GrowthExperiments\MentorDashboard\PersonalizedPraise\PersonalizedPraiseSettings;
use GrowthExperiments\MentorDashboard\PersonalizedPraise\PraiseworthyMenteeSuggester;
use GrowthExperiments\UserImpact\UserImpact;
use Html;
use IContextSource;
use MediaWiki\Title\Title;

class PersonalizedPraise extends BaseModule {

	private PraiseworthyMenteeSuggester $praiseworthyMenteeSuggester;
	private PersonalizedPraiseSettings $personalizedPraiseSettings;

	/**
	 * @param string $name
	 * @param IContextSource $ctx
	 * @param PraiseworthyMenteeSuggester $praiseworthyMenteeSuggester
	 * @param PersonalizedPraiseSettings $personalizedPraiseSettings
	 */
	public function __construct(
		$name,
		IContextSource $ctx,
		PraiseworthyMenteeSuggester $praiseworthyMenteeSuggester,
		PersonalizedPraiseSettings $personalizedPraiseSettings
	) {
		parent::__construct( $name, $ctx );

		$this->praiseworthyMenteeSuggester = $praiseworthyMenteeSuggester;
		$this->personalizedPraiseSettings = $personalizedPraiseSettings;
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
	protected function getSubheader() {
		return Html::element(
			'p',
			[],
			$this->msg( 'growthexperiments-mentor-dashboard-personalized-praise-intro' )
				->text()
		);
	}

	/** @inheritDoc */
	protected function getBody() {
		return Html::rawElement(
			'div',
			[
				'id' => 'vue-root-personalizedpraise',
				'class' => 'growthexperiments-mentor-dashboard-module-mentee-overview-content'
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
		];
	}

	/** @inheritDoc */
	protected function getMobileSummaryBody() {
		return $this->getBody();
	}
}
