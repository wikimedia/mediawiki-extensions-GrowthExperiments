<?php

namespace GrowthExperiments\Api;

use ApiBase;
use GrowthExperiments\Mentorship\ChangeMentor;
use GrowthExperiments\Mentorship\Mentor;
use LogEventsList;
use LogPager;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\ParamValidator\TypeDef\UserDef;
use Wikimedia\ParamValidator\ParamValidator;

class ApiSetMentor extends ApiBase {
	/**
	 * @inheritDoc
	 */
	public function execute() {
		$params = $this->extractRequestParams();
		$mentee = $params['mentee'];
		$mentor = $params['mentor'];

		if ( !in_array( $this->getUser()->getId(), [ $mentee->getId(), $mentor->getId() ] ) ) {
			// If you're neither the mentee nor the (new) mentor,
			// you must have setmentor rights.
			$this->checkUserRightsAny( 'setmentor' );
		}
		$mentorObj = Mentor::newFromMentee( $mentee );

		if ( $mentee->isAnon() || $mentor->isAnon() ) {
			// User doesn't exist
			$wrongUser = $mentee->isAnon() ? $mentee : $mentor;
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
			)
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
			]
		];
	}
}
