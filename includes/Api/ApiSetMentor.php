<?php

namespace GrowthExperiments\Api;

use ApiBase;
use ApiMain;
use GrowthExperiments\Mentorship\ChangeMentor;
use GrowthExperiments\Mentorship\Mentor;
use GrowthExperiments\Mentorship\MentorManager;
use LogEventsList;
use LogPager;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\ParamValidator\TypeDef\UserDef;
use User;
use Wikimedia\ParamValidator\ParamValidator;

class ApiSetMentor extends ApiBase {
	/** @var MentorManager */
	private $mentorManager;

	/**
	 * @param ApiMain $mainModule
	 * @param string $moduleName
	 * @param MentorManager $mentorManager
	 */
	public function __construct(
		ApiMain $mainModule,
		$moduleName,
		MentorManager $mentorManager
	) {
		parent::__construct( $mainModule, $moduleName );
		$this->mentorManager = $mentorManager;
	}

	/**
	 * @inheritDoc
	 */
	public function execute() {
		$params = $this->extractRequestParams();
		/** @var User $mentee */
		$mentee = $params['mentee'];
		/** @var User $mentor */
		$mentor = $params['mentor'];

		if ( !in_array( $this->getUser()->getId(), [ $mentee->getId(), $mentor->getId() ] ) ) {
			// If you're neither the mentee nor the (new) mentor,
			// you must have setmentor rights.
			$this->checkUserRightsAny( 'setmentor' );
		}
		$mentorObj = $this->mentorManager->getMentorForUserSafe( $mentee );

		if ( !$mentee->isRegistered() || !$mentor->isRegistered() ) {
			// User doesn't exist
			$wrongUser = $mentee->isRegistered() ? $mentor : $mentee;
			$this->dieWithError( [ 'nosuchusershort', $wrongUser->getName() ] );
		}

		$changeMentor = new ChangeMentor(
			$mentee,
			$this->getUser(),
			$this->getContext(),
			LoggerFactory::getInstance( 'GrowthExperiments' ),
			$mentorObj,
			new LogPager(
				new LogEventsList( $this->getContext() ),
				[ 'growthexperiments' ],
				'',
				$mentee->getUserPage()
			),
			$this->mentorManager
		);
		$status = $changeMentor->execute( $mentor, $params['reason'] );
		if ( !$status->isOK() ) {
			$this->dieStatus( $status );
		}

		$this->getResult()->addValue( null, $this->getModuleName(), [
			'status' => 'ok',
			'mentee' => $mentee,
			'newMentor' => $mentor,
			'oldMentor' => $mentorObj instanceof Mentor ? $mentorObj->getMentorUser() : false,
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
				ApiBase::PARAM_REQUIRED => true,
				ApiBase::PARAM_TYPE => 'user',
				UserDef::PARAM_ALLOWED_USER_TYPES => [ 'name' ],
				UserDef::PARAM_RETURN_OBJECT => true,
			],
			'mentor' => [
				ApiBase::PARAM_REQUIRED => true,
				ApiBase::PARAM_TYPE => 'user',
				UserDef::PARAM_ALLOWED_USER_TYPES => [ 'name' ],
				UserDef::PARAM_RETURN_OBJECT => true,
			],
			'reason' => [
				ApiBase::PARAM_TYPE => 'string',
				ParamValidator::PARAM_DEFAULT => '',
			],
		];
	}
}
