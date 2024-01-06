<?php

namespace GrowthExperiments\Specials\Forms;

use GrowthExperiments\Mentorship\MentorRemover;
use IContextSource;
use MediaWiki\Status\Status;
use MediaWiki\User\UserIdentity;

class ManageMentorsRemoveMentor extends ManageMentorsAbstractForm {

	private MentorRemover $mentorRemover;

	/**
	 * @param MentorRemover $mentorRemover
	 * @param UserIdentity $mentorUser
	 * @param IContextSource $context
	 */
	public function __construct(
		MentorRemover $mentorRemover,
		UserIdentity $mentorUser,
		IContextSource $context
	) {
		parent::__construct(
			$mentorUser,
			$context,
			'growthexperiments-manage-mentors-'
		);

		$this->mentorRemover = $mentorRemover;

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

		return Status::wrap( $this->mentorRemover->removeMentor(
			$this->getUser(),
			$this->mentorUser,
			$data['reason'],
			$this->getContext()
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
