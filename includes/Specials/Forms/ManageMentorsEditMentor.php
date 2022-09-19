<?php

namespace GrowthExperiments\Specials\Forms;

use GrowthExperiments\MentorDashboard\MentorTools\IMentorWeights;
use GrowthExperiments\MentorDashboard\MentorTools\MentorStatusManager;
use GrowthExperiments\Mentorship\Provider\IMentorWriter;
use GrowthExperiments\Mentorship\Provider\MentorProvider;
use IContextSource;
use MediaWiki\User\UserIdentity;
use MWTimestamp;
use Status;

class ManageMentorsEditMentor extends ManageMentorsAbstractForm {

	/** @var MentorStatusManager */
	private $mentorStatusManager;

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
		$this->mentorStatusManager = $mentorStatusManager;
		parent::__construct(
			$mentorProvider,
			$mentorWriter,
			$mentorUser,
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

		return [
			'message' => [
				'type' => 'text',
				'label-message' => 'growthexperiments-manage-mentors-edit-intro-msg',
				'default' => $mentor->getIntroText(),
			],
			'automaticallyAssigned' => [
				'type' => 'check',
				'label-message' => 'growthexperiments-manage-mentors-edit-is-auto-assigned',
				'default' => $mentor->getAutoAssigned(),
			],
			'weight' => [
				'type' => 'radio',
				'label-message' => 'growthexperiments-manage-mentors-edit-weight',
				'options-messages' => [
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
				'default' => (bool)$awayTimestamp,
			],
			'awayTimestamp' => [
				'type' => 'datetime',
				'label-message' => 'growthexperiments-manage-mentors-edit-away-until',
				'hide-if' => [ '!==', 'isAway', '1' ],
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
		$awayTimestamp = $this->mentorStatusManager->getMentorBackTimestamp( $this->mentorUser );

		$mentor->setIntroText( $data['message'] !== '' ? $data['message'] : null );
		$mentor->setAutoAssigned( $data['automaticallyAssigned'] );
		$mentor->setWeight( (int)$data['weight'] );

		if ( (bool)$awayTimestamp !== $data['isAway'] ) {
			// isAway changed, implement the change
			if ( $data['isAway'] ) {
				$this->mentorStatusManager->markMentorAsAwayTimestamp(
					$this->mentorUser,
					$data['awayTimestamp']
				);
			} else {
				$this->mentorStatusManager->markMentorAsActive( $this->mentorUser );
			}
		}

		return Status::wrap( $this->mentorWriter->changeMentor(
			$mentor,
			$this->getUser(),
			$data['reason']
		) );
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
