<?php

namespace GrowthExperiments\MentorDashboard\Modules;

use FormatJson;
use GrowthExperiments\MentorDashboard\PersonalizedPraise\PersonalizedPraiseSettings;
use GrowthExperiments\MentorDashboard\PersonalizedPraise\PraiseworthyMenteeSuggester;
use GrowthExperiments\UserImpact\UserImpact;
use Html;
use IContextSource;

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
		$conditions = $this->personalizedPraiseSettings->getPraiseworthyConditions(
			$this->getUser()
		);
		return Html::rawElement(
			'div',
			[],
			implode( "\n", [
				Html::element(
					'p',
					[],
					$this->msg( 'growthexperiments-mentor-dashboard-personalized-praise-intro' )
						->text()
				),
				Html::element(
					'p',
					[],
					$this->msg( 'growthexperiments-mentor-dashboard-personalized-praise-metrics' )
						->numParams( $conditions->getMinEdits(), $conditions->getDays() )
						->text()
				)
			] )
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

	/** @inheritDoc */
	protected function getJsConfigVars() {
		return [
			'GEPraiseworthyMentees' => $this->getPraiseworthyMentees(),
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
		];
	}

	/** @inheritDoc */
	protected function getMobileSummaryBody() {
		return $this->getBody();
	}
}
