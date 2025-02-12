<?php

namespace GrowthExperiments\Api;

use GrowthExperiments\Mentorship\ChangeMentorFactory;
use GrowthExperiments\Mentorship\IMentorManager;
use GrowthExperiments\Mentorship\Mentor;
use MediaWiki\Api\ApiBase;
use MediaWiki\Api\ApiMain;
use MediaWiki\Api\ApiUsageException;
use MediaWiki\ParamValidator\TypeDef\UserDef;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserIdentityUtils;
use Wikimedia\ParamValidator\ParamValidator;
use Wikimedia\Rdbms\IDBAccessObject;

class ApiSetMentor extends ApiBase {
	private IMentorManager $mentorManager;
	private ChangeMentorFactory $changeMentorFactory;
	private UserIdentityUtils $userIdentityUtils;

	public function __construct(
		ApiMain $mainModule,
		string $moduleName,
		IMentorManager $mentorManager,
		ChangeMentorFactory $changeMentorFactory,
		UserIdentityUtils $userIdentityUtils
	) {
		parent::__construct( $mainModule, $moduleName );

		$this->mentorManager = $mentorManager;
		$this->changeMentorFactory = $changeMentorFactory;
		$this->userIdentityUtils = $userIdentityUtils;
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

		if ( !in_array( $this->getUser()->getId(), [ $mentee->getId(), $mentor->getId() ] ) ) {
			// If you're neither the mentee nor the (new) mentor,
			// you must have setmentor rights.
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
