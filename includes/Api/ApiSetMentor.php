<?php

namespace GrowthExperiments\Api;

use ApiBase;
use ApiMain;
use GrowthExperiments\Mentorship\ChangeMentorFactory;
use GrowthExperiments\Mentorship\Mentor;
use GrowthExperiments\Mentorship\MentorManager;
use GrowthExperiments\Mentorship\Provider\MentorProvider;
use MediaWiki\ParamValidator\TypeDef\UserDef;
use MediaWiki\User\UserIdentity;
use Wikimedia\ParamValidator\ParamValidator;

class ApiSetMentor extends ApiBase {
	/** @var MentorManager */
	private $mentorManager;

	/** @var MentorProvider */
	private $mentorProvider;

	/** @var ChangeMentorFactory */
	private $changeMentorFactory;

	/**
	 * @param ApiMain $mainModule
	 * @param string $moduleName
	 * @param MentorManager $mentorManager
	 * @param MentorProvider $mentorProvider
	 * @param ChangeMentorFactory $changeMentorFactory
	 */
	public function __construct(
		ApiMain $mainModule,
		$moduleName,
		MentorManager $mentorManager,
		MentorProvider $mentorProvider,
		ChangeMentorFactory $changeMentorFactory
	) {
		parent::__construct( $mainModule, $moduleName );

		$this->mentorManager = $mentorManager;
		$this->mentorProvider = $mentorProvider;
		$this->changeMentorFactory = $changeMentorFactory;
	}

	/**
	 * Check whether a permissionless change of assigned mentor is allowed
	 *
	 * We only allow mentor changes when one of the following conditions is met:
	 * 	1. The target mentor is registered, and the performer is either the mentee or the mentor
	 * 	2. The performer has the `setmentor` permission (normally available to sysops)
	 *
	 * This method checks the first condition.
	 *
	 * @param UserIdentity $mentor
	 * @param UserIdentity $mentee
	 * @return bool
	 */
	private function allowPermissionlessChange( $mentor, $mentee ) {
		return $this->mentorProvider->isMentor( $mentor ) &&
			(
				$this->getUser()->equals( $mentee ) ||
				$this->getUser()->equals( $mentor )
			);
	}

	/**
	 * @inheritDoc
	 */
	public function execute() {
		$params = $this->extractRequestParams();
		/** @var UserIdentity $mentee */
		$mentee = $params['mentee'];
		/** @var UserIdentity $mentor */
		$mentor = $params['mentor'];

		if ( !$this->allowPermissionlessChange( $mentor, $mentee ) ) {
			$this->checkUserRightsAny( 'setmentor' );
		}

		if ( !$mentee->isRegistered() || !$mentor->isRegistered() ) {
			// User doesn't exist
			$wrongUser = $mentee->isRegistered() ? $mentor : $mentee;
			$this->dieWithError( [ 'nosuchusershort', $wrongUser->getName() ] );
		}

		$oldMentorObj = $this->mentorManager->getMentorForUserIfExists( $mentee );

		$changeMentor = $this->changeMentorFactory->newChangeMentor(
			$mentee,
			$this->getUser(),
			$this->getContext()
		);
		$status = $changeMentor->execute( $mentor, $params['reason'] );
		if ( !$status->isOK() ) {
			$this->dieStatus( $status );
		}

		$this->getResult()->addValue( null, $this->getModuleName(), [
			'status' => 'ok',
			'mentee' => $mentee,
			'newMentor' => $mentor,
			'oldMentor' => $oldMentorObj instanceof Mentor ? $oldMentorObj->getUserIdentity() : false,
		] );
	}

	public function mustBePosted() {
		return true;
	}

	/**
	 * @inheritDoc
	 */
	public function needsToken() {
		return 'csrf';
	}

	public function isWriteMode() {
		return true;
	}

	/**
	 * @inheritDoc
	 */
	public function getAllowedParams() {
		return [
			'mentee' => [
				ParamValidator::PARAM_REQUIRED => true,
				ParamValidator::PARAM_TYPE => 'user',
				UserDef::PARAM_ALLOWED_USER_TYPES => [ 'name' ],
				UserDef::PARAM_RETURN_OBJECT => true,
			],
			'mentor' => [
				ParamValidator::PARAM_REQUIRED => true,
				ParamValidator::PARAM_TYPE => 'user',
				UserDef::PARAM_ALLOWED_USER_TYPES => [ 'name' ],
				UserDef::PARAM_RETURN_OBJECT => true,
			],
			'reason' => [
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_DEFAULT => '',
			],
		];
	}
}
