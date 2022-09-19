<?php

namespace GrowthExperiments\Specials\Forms;

use GrowthExperiments\MentorDashboard\MentorTools\IMentorWeights;
use GrowthExperiments\Mentorship\Provider\IMentorWriter;
use GrowthExperiments\Mentorship\Provider\MentorProvider;
use IContextSource;
use MediaWiki\User\UserIdentity;
use Status;

class ManageMentorsEditMentor extends ManageMentorsAbstractForm {

	/**
	 * @param MentorProvider $mentorProvider
	 * @param IMentorWriter $mentorWriter
	 * @param UserIdentity $mentorUser
	 * @param IContextSource $context
	 */
	public function __construct(
		MentorProvider $mentorProvider,
		IMentorWriter $mentorWriter,
		UserIdentity $mentorUser,
		IContextSource $context
	) {
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
		$mentor->setAutoAssigned( $data['automaticallyAssigned'] );
		$mentor->setWeight( (int)$data['weight'] );

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
