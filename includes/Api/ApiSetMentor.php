<?php

namespace GrowthExperiments\Api;

use GrowthExperiments\Mentorship\ChangeMentorFactory;
use GrowthExperiments\Mentorship\Mentor;
use GrowthExperiments\Mentorship\MentorManager;
use GrowthExperiments\Mentorship\Provider\MentorProvider;
use MediaWiki\Api\ApiBase;
use MediaWiki\Api\ApiMain;
use MediaWiki\Api\ApiUsageException;
use MediaWiki\ParamValidator\TypeDef\UserDef;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserIdentityUtils;
use Wikimedia\ParamValidator\ParamValidator;
use Wikimedia\Rdbms\IDBAccessObject;

class ApiSetMentor extends ApiBase {
	/** @var MentorManager */
	private $mentorManager;

	/** @var MentorProvider */
	private $mentorProvider;

	/** @var ChangeMentorFactory */
	private $changeMentorFactory;

	/** @var UserIdentityUtils */
	private $userIdentityUtils;

	/**
	 * @param ApiMain $mainModule
	 * @param string $moduleName
	 * @param MentorManager $mentorManager
	 * @param MentorProvider $mentorProvider
	 * @param ChangeMentorFactory $changeMentorFactory
	 * @param UserIdentityUtils $userIdentityUtils
	 */
	public function __construct(
		ApiMain $mainModule,
		$moduleName,
		MentorManager $mentorManager,
		MentorProvider $mentorProvider,
		ChangeMentorFactory $changeMentorFactory,
		UserIdentityUtils $userIdentityUtils
	) {
		parent::__construct( $mainModule, $moduleName );

		$this->mentorManager = $mentorManager;
		$this->mentorProvider = $mentorProvider;
		$this->changeMentorFactory = $changeMentorFactory;
		$this->userIdentityUtils = $userIdentityUtils;
	}

	/**
	 * Check whether a permissionless change of assigned mentor is allowed
	 *
	 * We only allow mentor changes when one of the following conditions is met:
	 * 	1. The target mentor is registered, and the performer is either the mentee or the mentor
	 * 	2. The performer has the `setmentor` permission (normally available to sysops)
	 *
	 * This method checks the first condition.
	 */
	private function allowPermissionlessChange(
		UserIdentity $mentor,
		UserIdentity $mentee
	): bool {
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
		$block = $this->getUser()->getBlock( IDBAccessObject::READ_LATEST );
		if ( $block && $block->isSitewide() ) {
			$this->dieBlocked( $block );
		}

		$params = $this->extractRequestParams();
		/** @var UserIdentity $mentee */
		$mentee = $params['mentee'];
		/** @var UserIdentity $mentor */
		$mentor = $params['mentor'];

		if ( !$this->allowPermissionlessChange( $mentor, $mentee ) ) {
			$this->checkUserRightsAny( 'setmentor' );
		}

		$this->assertUserExists( $mentee );
		$this->assertUserExists( $mentor );

		$oldMentorObj = $this->mentorManager->getMentorForUserIfExists( $mentee );

		$changeMentor = $this->changeMentorFactory->newChangeMentor(
			$mentee,
			$this->getUser()
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

	/**
	 * Throw an exception if the user is anonymous or temporary.
	 * @param UserIdentity $user
	 * @throws ApiUsageException
	 */
	private function assertUserExists( UserIdentity $user ) {
		if ( !$this->userIdentityUtils->isNamed( $user ) ) {
			$this->dieWithError( [ 'nosuchusershort', $user->getName() ] );
		}
	}

	/** @inheritDoc */
	public function mustBePosted() {
		return true;
	}

	/**
	 * @inheritDoc
	 */
	public function needsToken() {
		return 'csrf';
	}

	/** @inheritDoc */
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
