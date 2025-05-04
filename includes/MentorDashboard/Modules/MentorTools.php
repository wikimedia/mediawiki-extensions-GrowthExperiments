<?php

namespace GrowthExperiments\MentorDashboard\Modules;

use GrowthExperiments\MentorDashboard\MentorTools\IMentorWeights;
use GrowthExperiments\MentorDashboard\MentorTools\MentorStatusManager;
use GrowthExperiments\Mentorship\Provider\MentorProvider;
use LogicException;
use MediaWiki\Context\IContextSource;
use MediaWiki\Html\Html;
use MediaWiki\SpecialPage\SpecialPage;
use OOUI\ButtonWidget;
use OOUI\DropdownInputWidget;

class MentorTools extends BaseModule {

	/** @var string Base CSS class for this module */
	private const BASE_MODULE_CSS_CLASS = 'growthexperiments-mentor-dashboard-module-mentor-tools';

	private MentorProvider $mentorProvider;
	private MentorStatusManager $mentorStatusManager;

	public function __construct(
		IContextSource $ctx,
		MentorProvider $mentorProvider,
		MentorStatusManager $mentorStatusManager
	) {
		parent::__construct( 'mentor-tools', $ctx );

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
	 * @return int
	 */
	private function getMentorWeight() {
		return $this->mentorProvider
			->newMentorFromUserIdentity( $this->getUser() )
			->getWeight();
	}

	/**
	 * @inheritDoc
	 */
	protected function getBody() {
		$weightOptions = [
			[
				'data' => IMentorWeights::WEIGHT_NONE,
				'label' => $this->msg(
					'growthexperiments-mentor-dashboard-mentor-tools-mentor-weight-none'
				)->text()
			],
			[
				'data' => IMentorWeights::WEIGHT_LOW,
				'label' => $this->msg(
					'growthexperiments-mentor-dashboard-mentor-tools-mentor-weight-low'
				)->text(),
			],
			[
				'data' => IMentorWeights::WEIGHT_NORMAL,
				'label' => $this->msg(
					'growthexperiments-mentor-dashboard-mentor-tools-mentor-weight-medium'
				)->text(),
			],
			[
				'data' => IMentorWeights::WEIGHT_HIGH,
				'label' => $this->msg(
					'growthexperiments-mentor-dashboard-mentor-tools-mentor-weight-high'
				)->text(),
			],
		];

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
			'GEMentorDashboardMentorIntroMessageMaxLength' => MentorProvider::INTRO_TEXT_LENGTH
		];
	}
}
