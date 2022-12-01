<?php

namespace GrowthExperiments\Api;

use ApiBase;
use ApiMain;
use GrowthExperiments\Mentorship\ChangeMentorFactory;
use GrowthExperiments\Mentorship\Mentor;
use GrowthExperiments\Mentorship\MentorManager;
use MediaWiki\ParamValidator\TypeDef\UserDef;
use MediaWiki\User\UserIdentity;
use User;
use Wikimedia\ParamValidator\ParamValidator;

class ApiSetMentor extends ApiBase {
	/** @var MentorManager */
	private $mentorManager;

	/** @var ChangeMentorFactory */
	private $changeMentorFactory;

	/**
	 * @param ApiMain $mainModule
	 * @param string $moduleName
	 * @param MentorManager $mentorManager
	 * @param ChangeMentorFactory $changeMentorFactory
	 */
	public function __construct(
		ApiMain $mainModule,
		$moduleName,
		MentorManager $mentorManager,
		ChangeMentorFactory $changeMentorFactory
	) {
		parent::__construct( $mainModule, $moduleName );

		$this->mentorManager = $mentorManager;
		$this->changeMentorFactory = $changeMentorFactory;
	}

	/**
	 * @inheritDoc
	 */
	public function execute() {
		$block = $this->getUser()->getBlock( User::READ_LATEST );
		if ( $block && $block->isSitewide() ) {
			$this->dieBlocked( $block );
		}

		$params = $this->extractRequestParams();
		/** @var UserIdentity $mentee */
		$mentee = $params['mentee'];
		/** @var UserIdentity $mentor */
		$mentor = $params['mentor'];

		if ( !in_array( $this->getUser()->getId(), [ $mentee->getId(), $mentor->getId() ] ) ) {
			// If you're neither the mentee nor the (new) mentor,
			// you must have setmentor rights.
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
