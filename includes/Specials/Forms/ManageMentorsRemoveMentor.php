<?php

namespace GrowthExperiments\Specials\Forms;

use GrowthExperiments\Mentorship\Provider\IMentorWriter;
use GrowthExperiments\Mentorship\Provider\MentorProvider;
use IContextSource;
use MediaWiki\User\UserIdentity;
use Status;

class ManageMentorsRemoveMentor extends ManageMentorsAbstractForm {

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
			'growthexperiments-manage-mentors-remove-mentor-pretext',
			$mentorUser->getName()
		)->parse() );
	}

	/**
	 * @inheritDoc
	 */
	protected function getFormFields(): array {
		return [
			'reason' => [
				'type' => 'text',
				'label-message' => 'growthexperiments-manage-mentors-remove-mentor-reason',
			]
		];
	}

	/**
	 * @inheritDoc
	 */
	public function onSubmit( array $data ) {
		if ( !self::canManageMentors( $this->getAuthority() ) ) {
			return false;
		}

		return Status::wrap( $this->mentorWriter->removeMentor(
			$this->mentorProvider->newMentorFromUserIdentity( $this->mentorUser ),
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
			'growthexperiments-manage-mentors-remove-mentor-success',
			$this->mentorUser->getName()
		);
		$out->addWikiMsg(
			'growthexperiments-manage-mentors-return-back'
		);
	}
}
