<?php

namespace GrowthExperiments\MentorDashboard\Modules;

use GrowthExperiments\MentorDashboard\MentorTools\MentorStatusManager;
use GrowthExperiments\MentorDashboard\MentorTools\MentorWeightManager;
use GrowthExperiments\Mentorship\Provider\MentorProvider;
use Html;
use IContextSource;
use LogicException;
use OOUI\ButtonWidget;
use OOUI\DropdownInputWidget;
use SpecialPage;

class MentorTools extends BaseModule {

	/** @var string Base CSS class for this module */
	private const BASE_MODULE_CSS_CLASS = 'growthexperiments-mentor-dashboard-module-mentor-tools';

	/** @var MentorProvider */
	private $mentorProvider;

	/** @var MentorStatusManager */
	private $mentorStatusManager;

	/** @var MentorWeightManager */
	private $mentorWeightManager;

	/**
	 * @param string $name
	 * @param IContextSource $ctx
	 * @param MentorProvider $mentorProvider
	 * @param MentorStatusManager $mentorStatusManager
	 * @param MentorWeightManager $mentorWeightManager
	 */
	public function __construct(
		$name,
		IContextSource $ctx,
		MentorProvider $mentorProvider,
		MentorStatusManager $mentorStatusManager,
		MentorWeightManager $mentorWeightManager
	) {
		parent::__construct( $name, $ctx );

		$this->mentorProvider = $mentorProvider;
		$this->mentorStatusManager = $mentorStatusManager;
		$this->mentorWeightManager = $mentorWeightManager;
	}

	/**
	 * @inheritDoc
	 */
	protected function getHeaderText() {
		return $this->msg( 'growthexperiments-mentor-dashboard-mentor-tools-headline' )->text();
	}

	/**
	 * @inheritDoc
	 */
	protected function getBody() {
		return implode( "\n", [
			Html::rawElement(
				'div',
				[ 'class' => self::BASE_MODULE_CSS_CLASS . '-status' ],
				implode( "\n", [
					Html::element(
						'h4',
						[],
						$this->msg( 'growthexperiments-mentor-dashboard-mentor-tools-mentor-status-headline' )
							->text()
					),
					// keep in sync with ext.growthExperiments.MentorDashboard/MentorTools.js
					new DropdownInputWidget( [
						'id' => 'growthexperiments-mentor-dashboard-mentor-tools-mentor-status-dropdown',
						'infusable' => true,
						'value' => $this->mentorStatusManager->getMentorStatus(
							$this->getUser()
						),
						'options' => [
							[
								'data' => MentorStatusManager::STATUS_ACTIVE,
								'label' => $this->msg(
									'growthexperiments-mentor-dashboard-mentor-tools-mentor-status-active'
								)->text(),
							],
							[
								'data' => MentorStatusManager::STATUS_AWAY,
								'label' => $this->msg(
									'growthexperiments-mentor-dashboard-mentor-tools-mentor-status-away'
								)->text(),
							],
						]
					] ),
					Html::element(
						'p',
						[
							'id' => self::BASE_MODULE_CSS_CLASS . '-status-away-message'
						],
						$this->maybeGetAwayMessage()
					)
				] )
			),
			Html::rawElement(
				'div',
				[ 'class' => self::BASE_MODULE_CSS_CLASS . '-mentor-weight' ],
				implode( "\n", [
					Html::element(
						'h4',
						[],
						$this->msg( 'growthexperiments-mentor-dashboard-mentor-tools-mentor-weight-headline' )
							->text()
					),
					// keep in sync with ext.growthExperiments.MentorDashboard/MentorTools.js
					new DropdownInputWidget( [
						'id' => 'growthexperiments-mentor-dashboard-mentor-tools-mentor-weight-dropdown',
						'infusable' => true,
						'value' => $this->mentorWeightManager->getWeightForMentor(
							$this->getUser()
						),
						'options' => [
							[
								'data' => MentorWeightManager::WEIGHT_LOW,
								'label' => $this->msg(
									'growthexperiments-mentor-dashboard-mentor-tools-mentor-weight-low'
								)->text(),
							],
							[
								'data' => MentorWeightManager::WEIGHT_NORMAL,
								'label' => $this->msg(
									'growthexperiments-mentor-dashboard-mentor-tools-mentor-weight-medium'
								)->text(),
							],
							[
								'data' => MentorWeightManager::WEIGHT_HIGH,
								'label' => $this->msg(
									'growthexperiments-mentor-dashboard-mentor-tools-mentor-weight-high'
								)->text(),
							],
						]
					] )
				] )
			),
			Html::rawElement(
				'div',
				[ 'class' => self::BASE_MODULE_CSS_CLASS . '-message' ],
				implode( "\n", [
					Html::rawElement(
						'div',
						[
							'class' => self::BASE_MODULE_CSS_CLASS . '-mentor-message-headline-container'
						],
						implode( "\n", [
							Html::element(
								'h4',
								[],
								$this->msg( 'growthexperiments-mentor-dashboard-mentor-tools-mentor-message-headline' )
									->text()
							),
							new ButtonWidget( [
								'icon' => 'edit',
								'framed' => false,
								'id' => 'growthexperiments-mentor-dashboard-mentor-tools-signup-button',
								'infusable' => true,
								'href' => $this->mentorProvider->getSignupTitle()->getLocalURL()
							] ),
						] )
					),
					Html::element(
						'div',
						[
							'id' => self::BASE_MODULE_CSS_CLASS . '-message-content'
						],
						$this->mentorProvider->newMentorFromUserIdentity( $this->getUser() )
							->getIntroText()
					)
				] )
			),
			Html::rawElement(
				'div',
				[ 'class' => self::BASE_MODULE_CSS_CLASS . '-other-actions' ],
				implode( "\n", [
					Html::element(
						'h4',
						[],
						$this->msg( 'growthexperiments-mentor-dashboard-mentor-tools-claim-mentee' )
							->text()
					),
					Html::rawElement(
						'div',
						[
							'class' => self::BASE_MODULE_CSS_CLASS . '-claim-mentee'
						],
						implode( "\n", [
							new ButtonWidget( [
								'label' => $this->msg(
									'growthexperiments-mentor-dashboard-mentor-tools-claim-mentee'
								),
								'href' => SpecialPage::getTitleFor( 'ClaimMentee' )->getLocalURL()
							] ),
							Html::element(
								'p',
								[
									'class' => self::BASE_MODULE_CSS_CLASS . '-claim-mentee-footer'
								],
								$this->msg(
									'growthexperiments-mentor-dashboard-mentor-tools-claim-mentee-footer'
								)->text()
							)
						] )
					)
				] )
			)
		] );
	}

	/**
	 * @return string
	 */
	private function maybeGetAwayMessage(): string {
		if (
			$this->mentorStatusManager->getMentorStatus( $this->getUser() ) !== MentorStatusManager::STATUS_AWAY
		) {
			return '';
		}

		$rawTS = $this->mentorStatusManager->getMentorBackTimestamp( $this->getUser() );
		if ( $rawTS === null ) {
			// This should actually never happen
			throw new LogicException(
				'MentorStatusManager::getMentorBackTimestamp should not return null ' .
				'if mentor status is away.'
			);
		}

		return $this->msg(
			'growthexperiments-mentor-dashboard-mentor-tools-mentor-status-away-message',
			$this->getContext()->getLanguage()->date(
				$rawTS,
				true
			)
		)->text();
	}

	/**
	 * @inheritDoc
	 */
	protected function getMobileSummaryBody() {
		return '';
	}

	/**
	 * @inheritDoc
	 */
	protected function getJsConfigVars() {
		$mentor = $this->mentorProvider->newMentorFromUserIdentity( $this->getUser() );

		return [
			'GEMentorDashboardMentorIntroMessage' => $mentor->getIntroText(),
			'GEMentorDashboardMentorAutoAssigned' => $mentor->getAutoAssigned(),
			'GEMentorDashboardMentorIntroMessageMaxLength' => MentorProvider::INTRO_TEXT_LENGTH
		];
	}
}
