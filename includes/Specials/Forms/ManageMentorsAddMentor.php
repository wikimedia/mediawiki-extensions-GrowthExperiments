<?php

namespace GrowthExperiments\Specials\Forms;

use GrowthExperiments\MentorDashboard\MentorTools\IMentorWeights;
use GrowthExperiments\Mentorship\Provider\IMentorWriter;
use GrowthExperiments\Mentorship\Provider\MentorProvider;
use MediaWiki\Context\IContextSource;
use MediaWiki\Status\Status;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserIdentityLookup;

class ManageMentorsAddMentor extends ManageMentorsAbstractForm {

	private UserIdentityLookup $userIdentityLookup;
	private MentorProvider $mentorProvider;
	private IMentorWriter $mentorWriter;
	private ?UserIdentity $mentorUser = null;

	public function __construct(
		UserIdentityLookup $userIdentityLookup,
		MentorProvider $mentorProvider,
		IMentorWriter $mentorWriter,
		IContextSource $context,
		string $messagePrefix = ''
	) {
		parent::__construct( $context, $messagePrefix );

		$this->userIdentityLookup = $userIdentityLookup;
		$this->mentorProvider = $mentorProvider;
		$this->mentorWriter = $mentorWriter;

		$this->setPreHtml( $this->msg(
			'growthexperiments-manage-mentors-add-mentor-pretext'
		)->parse() );
	}

	/**
	 * @inheritDoc
	 */
	protected function getFormFields(): array {
		return [
			'username' => [
				'type' => 'user',
				'label-message' => 'growthexperiments-manage-mentors-add-mentor-username',
			],
			'reason' => [
				'type' => 'text',
				'label-message' => 'growthexperiments-manage-mentors-add-mentor-reason',
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

		$this->mentorUser = $this->userIdentityLookup->getUserIdentityByName( $data['username'] );
		if ( !$this->mentorUser ) {
			return Status::newFatal(
				'nosuchuser',
				$data['username']
			);
		}

		$mentor = $this->mentorProvider->newMentorFromUserIdentity( $this->mentorUser );
		$mentor->setWeight( IMentorWeights::WEIGHT_NONE );

		return Status::wrap( $this->mentorWriter->addMentor(
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
			'growthexperiments-manage-mentors-add-mentor-success',
			$this->mentorUser->getName()
		);
		$out->addWikiMsg(
			'growthexperiments-manage-mentors-return-back'
		);
	}
}
