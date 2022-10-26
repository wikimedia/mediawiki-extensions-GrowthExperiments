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

	/** @var int Pseudo weight, only recognized within MentorTools */
	public const WEIGHT_NONE = 'none';

	/** @var MentorProvider */
	private $mentorProvider;

	/** @var MentorStatusManager */
	private $mentorStatusManager;

	/**
	 * @param string $name
	 * @param IContextSource $ctx
	 * @param MentorProvider $mentorProvider
	 * @param MentorStatusManager $mentorStatusManager
	 */
	public function __construct(
		$name,
		IContextSource $ctx,
		MentorProvider $mentorProvider,
		MentorStatusManager $mentorStatusManager
	) {
		parent::__construct( $name, $ctx );

		$this->mentorProvider = $mentorProvider;
		$this->mentorStatusManager = $mentorStatusManager;
	}

	/**
	 * @inheritDoc
	 */
	protected function getHeaderText() {
		return $this->msg( 'growthexperiments-mentor-dashboard-mentor-tools-headline' )->text();
	}

	/**
	 * @return bool
	 */
	private function isMentorListStructured(): bool {
		return $this->getContext()->getConfig()->get( 'GEMentorProvider' ) ===
			MentorProvider::PROVIDER_STRUCTURED;
	}

	/**
	 * @return int|string
	 */
	private function getMentorWeight() {
		$mentor = $this->mentorProvider
			->newMentorFromUserIdentity( $this->getUser() );

		if (
			$this->isMentorListStructured() &&
			!$mentor->getAutoAssigned()
		) {
			return self::WEIGHT_NONE;
		}

		return $mentor->getWeight();
	}

	/**
	 * @inheritDoc
	 */
	protected function getBody() {
		$weightOptions = [
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
		];
		if ( $this->isMentorListStructured() ) {
			array_unshift( $weightOptions, [
				'data' => self::WEIGHT_NONE,
				'label' => $this->msg(
					'growthexperiments-mentor-dashboard-mentor-tools-mentor-weight-none'
				)->text()
			] );
		}

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
						'disabled' => !$this->mentorStatusManager->canChangeStatus(
							$this->getUser()
						)->isOK(),
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
						'value' => $this->getMentorWeight(),
						'options' => $weightOptions
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
		$awayReason = $this->mentorStatusManager->getAwayReason( $this->getUser() );
		if ( $awayReason === null ) {
			// user is not away
			return '';
		}

		switch ( $awayReason ) {
			case MentorStatusManager::AWAY_BECAUSE_TIMESTAMP:
				return $this->msg(
					'growthexperiments-mentor-dashboard-mentor-tools-mentor-status-away-message',
					$this->getContext()->getLanguage()->date(
						(string)$this->mentorStatusManager->getMentorBackTimestamp( $this->getUser() ),
						true
					)
				)->text();
			case MentorStatusManager::AWAY_BECAUSE_BLOCK:
				return $this->msg(
					'growthexperiments-mentor-dashboard-mentor-tools-mentor-status-away-block'
				)->text();
			default:
				throw new LogicException(
					'MentorStatusManager::getAwayReason returned unknown reason'
				);
		}
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
