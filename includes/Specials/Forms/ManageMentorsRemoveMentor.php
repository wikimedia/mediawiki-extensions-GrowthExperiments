<?php

namespace GrowthExperiments\Specials\Forms;

use GrowthExperiments\Mentorship\Provider\IMentorWriter;
use GrowthExperiments\Mentorship\Provider\MentorProvider;
use GrowthExperiments\Mentorship\ReassignMenteesFactory;
use IContextSource;
use MediaWiki\User\UserIdentity;
use Status;

class ManageMentorsRemoveMentor extends ManageMentorsAbstractForm {

	/** @var ReassignMenteesFactory */
	private $reassignMenteesFactory;

	/**
	 * @param MentorProvider $mentorProvider
	 * @param IMentorWriter $mentorWriter
	 * @param ReassignMenteesFactory $reassignMenteesFactory
	 * @param UserIdentity $mentorUser
	 * @param IContextSource $context
	 */
	public function __construct(
		MentorProvider $mentorProvider,
		IMentorWriter $mentorWriter,
		ReassignMenteesFactory $reassignMenteesFactory,
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

		$this->reassignMenteesFactory = $reassignMenteesFactory;

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

		$status = $this->mentorWriter->removeMentor(
			$this->mentorProvider->newMentorFromUserIdentity( $this->mentorUser ),
			$this->getUser(),
			$data['reason']
		);
		if ( $status->isOK() ) {
			$this->reassignMenteesFactory->newReassignMentees(
				$this->getUser(),
				$this->mentorUser,
				$this->getContext()
			)->reassignMentees(
				'growthexperiments-quit-mentorship-reassign-mentees-log-message-removed',
				$this->getUser()->getName()
			);
		}
		return Status::wrap( $status );
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
