<?php

namespace GrowthExperiments\MentorDashboard\Modules;

use GrowthExperiments\MentorDashboard\MentorTools\MentorStatusManager;
use GrowthExperiments\Mentorship\MentorManager;
use Html;
use IContextSource;
use OOUI\ButtonWidget;
use OOUI\DropdownInputWidget;
use SpecialPage;

class MentorTools extends BaseModule {

	/** @var string Base CSS class for this module */
	private const BASE_MODULE_CSS_CLASS = 'growthexperiments-mentor-dashboard-module-mentor-tools';

	/** @var MentorManager */
	private $mentorManager;

	/** @var MentorStatusManager */
	private $mentorStatusManager;

	/**
	 * @param string $name
	 * @param IContextSource $ctx
	 * @param MentorManager $mentorManager
	 * @param MentorStatusManager $mentorStatusManager
	 */
	public function __construct(
		$name,
		IContextSource $ctx,
		MentorManager $mentorManager,
		MentorStatusManager $mentorStatusManager
	) {
		parent::__construct( $name, $ctx );

		$this->mentorManager = $mentorManager;
		$this->mentorStatusManager = $mentorStatusManager;
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
								'href' => $this->mentorManager->getAutoMentorsListTitle()
									->getLocalURL()
							] ),
						] )
					),
					Html::element(
						'div',
						[
							'id' => self::BASE_MODULE_CSS_CLASS . '-message-content'
						],
						$this->mentorManager->newMentorFromUserIdentity( $this->getUser() )->getIntroText()
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
						$this->msg( 'growthexperiments-mentor-dashboard-mentor-tools-other-actions-headline' )
							->text()
					),
					Html::rawElement(
						'div',
						[
							'class' => self::BASE_MODULE_CSS_CLASS . '-claim-mentee'
						],
						implode( "\n", [
							Html::element(
								'p',
								[],
								$this->msg(
									'growthexperiments-mentor-dashboard-mentor-tools-other-actions-claim-mentee'
								)->text()
							),
							new ButtonWidget( [
								'label' => $this->msg(
									'growthexperiments-mentor-dashboard-mentor-tools-other-actions-claim-mentee'
								),
								'href' => SpecialPage::getTitleFor( 'ClaimMentee' )->getLocalURL()
							] ),
							Html::element(
								'p',
								[
									'class' => self::BASE_MODULE_CSS_CLASS . '-claim-mentee-footer'
								],
								$this->msg(
									'growthexperiments-mentor-dashboard-mentor-tools-other-actions-claim-mentee-footer'
								)->text()
							)
						] )
					)
				] )
			)
		] );
	}

	/**
	 * @inheritDoc
	 */
	protected function getMobileSummaryBody() {
		return '';
	}
}
