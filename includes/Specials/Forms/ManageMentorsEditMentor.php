<?php

namespace GrowthExperiments\Specials\Forms;

use GrowthExperiments\MentorDashboard\MentorTools\IMentorWeights;
use GrowthExperiments\MentorDashboard\MentorTools\MentorStatusManager;
use GrowthExperiments\Mentorship\Provider\IMentorWriter;
use GrowthExperiments\Mentorship\Provider\MentorProvider;
use MediaWiki\Context\IContextSource;
use MediaWiki\Status\Status;
use MediaWiki\User\UserIdentity;
use MediaWiki\Utils\MWTimestamp;

class ManageMentorsEditMentor extends ManageMentorsAbstractForm {

	private MentorProvider $mentorProvider;
	private IMentorWriter $mentorWriter;
	private MentorStatusManager $mentorStatusManager;
	private UserIdentity $mentorUser;

	/**
	 * @param MentorProvider $mentorProvider
	 * @param IMentorWriter $mentorWriter
	 * @param MentorStatusManager $mentorStatusManager
	 * @param UserIdentity $mentorUser
	 * @param IContextSource $context
	 */
	public function __construct(
		MentorProvider $mentorProvider,
		IMentorWriter $mentorWriter,
		MentorStatusManager $mentorStatusManager,
		UserIdentity $mentorUser,
		IContextSource $context
	) {
		$this->mentorProvider = $mentorProvider;
		$this->mentorWriter = $mentorWriter;
		$this->mentorStatusManager = $mentorStatusManager;
		$this->mentorUser = $mentorUser;

		parent::__construct(
			$context,
			'growthexperiments-manage-mentors-'
		);

		$this->setPreHtml( $this->msg(
			'growthexperiments-manage-mentors-edit-pretext',
			$mentorUser->getName()
		)->parse() );
	}

	/**
	 * @inheritDoc
	 */
	protected function getFormFields(): array {
		$mentor = $this->mentorProvider->newMentorFromUserIdentity( $this->mentorUser );
		$awayTimestamp = $this->mentorStatusManager->getMentorBackTimestamp( $this->mentorUser );
		$canChangeStatusBool = $this->mentorStatusManager->canChangeStatus(
			$this->mentorUser
		)->isOK();

		return [
			'message' => [
				'type' => 'text',
				'label-message' => 'growthexperiments-manage-mentors-edit-intro-msg',
				'default' => $mentor->getIntroText(),
			],
			'weight' => [
				'type' => 'radio',
				'label-message' => 'growthexperiments-manage-mentors-edit-weight',
				'options-messages' => [
					'growthexperiments-mentor-dashboard-mentor-tools-mentor-weight-none' =>
						IMentorWeights::WEIGHT_NONE,
					'growthexperiments-mentor-dashboard-mentor-tools-mentor-weight-low' =>
						IMentorWeights::WEIGHT_LOW,
					'growthexperiments-mentor-dashboard-mentor-tools-mentor-weight-medium' =>
						IMentorWeights::WEIGHT_NORMAL,
					'growthexperiments-mentor-dashboard-mentor-tools-mentor-weight-high' =>
						IMentorWeights::WEIGHT_HIGH,
				],
				'default' => $mentor->getWeight(),
			],
			'isAway' => [
				'type' => 'check',
				'label-message' => 'growthexperiments-manage-mentors-edit-is-away',
				'disabled' => !$canChangeStatusBool,
				'help' => !$canChangeStatusBool ? $this->msg(
					'growthexperiments-manage-mentors-edit-is-away-blocked'
				)
					->params( $this->mentorUser->getName() )
					->escaped() : '',
				'default' => $this->mentorStatusManager->getMentorStatus(
					$this->mentorUser
					) === MentorStatusManager::STATUS_AWAY,
			],
			'isAwayChangeable' => [
				'type' => 'hidden',
				'default' => $canChangeStatusBool,
			],
			'awayTimestamp' => [
				'type' => 'datetime',
				'label-message' => 'growthexperiments-manage-mentors-edit-away-until',
				'required' => true,
				'hide-if' => [
					'OR',
					[ '!==', 'isAway', '1' ],
					[ '!==', 'isAwayChangeable', '1' ],
				],
				'min' => MWTimestamp::getInstance()->getTimestamp( TS_MW ),
				'default' => MWTimestamp::getInstance( (string)$awayTimestamp )
					->format( 'Y-m-d\TH:m:s\Z' ),
			],
			'reason' => [
				'type' => 'text',
				'label-message' => 'growthexperiments-manage-mentors-edit-reason',
			],
		];
	}

	/**
	 * @inheritDoc
	 */
	public function onSubmit( array $data ) {
		if ( !self::canManageMentors( $this->getAuthority() ) ) {
			return false;
		}

		$mentor = $this->mentorProvider->newMentorFromUserIdentity( $this->mentorUser );

		$mentor->setIntroText( $data['message'] !== '' ? $data['message'] : null );
		$mentor->setWeight( (int)$data['weight'] );

		$status = Status::newGood();
		if ( $data['isAway'] ) {
			$status->merge( $this->mentorStatusManager->markMentorAsAwayTimestamp(
				$this->mentorUser,
				$data['awayTimestamp']
			) );
		} else {
			$status->merge( $this->mentorStatusManager->markMentorAsActive(
				$this->mentorUser
			) );
		}

		$status->merge( $this->mentorWriter->changeMentor(
			$mentor,
			$this->getUser(),
			$data['reason']
		) );
		return $status;
	}

	/**
	 * @inheritDoc
	 */
	protected function onSuccess(): void {
		$out = $this->getOutput();
		$out->addWikiMsg(
			'growthexperiments-manage-mentors-edit-success',
			$this->mentorUser->getName()
		);
		$out->addWikiMsg(
			'growthexperiments-manage-mentors-return-back'
		);
	}
}
