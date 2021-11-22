<?php

namespace GrowthExperiments\Api;

use ApiBase;
use ApiMain;
use GrowthExperiments\MentorDashboard\MentorTools\MentorStatusManager;
use GrowthExperiments\Mentorship\MentorManager;
use Wikimedia\ParamValidator\ParamValidator;

class ApiSetMentorStatus extends ApiBase {

	/** @var MentorManager */
	private $mentorManager;

	/** @var MentorStatusManager */
	private $mentorStatusManager;

	/**
	 * @param ApiMain $mainModule
	 * @param string $moduleName
	 * @param MentorManager $mentorManager
	 * @param MentorStatusManager $mentorStatusManager
	 */
	public function __construct(
		ApiMain $mainModule,
		$moduleName,
		MentorManager $mentorManager,
		MentorStatusManager $mentorStatusManager
	) {
		parent::__construct( $mainModule, $moduleName );

		$this->mentorManager = $mentorManager;
		$this->mentorStatusManager = $mentorStatusManager;
	}

	/**
	 * @inheritDoc
	 */
	public function execute() {
		if (
			!$this->getConfig()->get( 'GEMentorDashboardEnabled' ) ||
			!$this->mentorManager->isMentor( $this->getUser() )
		) {
			$this->dieWithError( [ 'apierror-permissiondenied-generic' ] );
		}

		$params = $this->extractRequestParams();
		$mentor = $this->getUser();

		switch ( $params['gesstatus'] ) {
			case MentorStatusManager::STATUS_ACTIVE:
				$this->mentorStatusManager->markMentorAsActive( $mentor );
				break;
			case MentorStatusManager::STATUS_AWAY:
				$this->requireAtLeastOneParameter( $params, 'gesbackindays' );

				$this->mentorStatusManager->markMentorAsAway(
					$mentor,
					(int)$params['gesbackindays']
				);
				break;
		}

		$rawBackTs = $this->mentorStatusManager->getMentorBackTimestamp( $mentor, MentorStatusManager::READ_LATEST );
		$resp = [
			'status' => 'ok',
			'mentorstatus' => $params['gesstatus'],
		];
		if ( $rawBackTs !== null ) {
			$resp['backintimestamp'] = [
				'raw' => $rawBackTs,
				'human' => $this->getContext()->getLanguage()->date( $rawBackTs, true )
			];
		}
		$this->getResult()->addValue( null, $this->getModuleName(), $resp );
	}

	/**
	 * @inheritDoc
	 */
	public function needsToken() {
		return 'csrf';
	}

	/**
	 * @inheritDoc
	 */
	public function isWriteMode() {
		return true;
	}

	/**
	 * @inheritDoc
	 */
	public function mustBePosted() {
		return true;
	}

	/**
	 * @inheritDoc
	 */
	protected function getAllowedParams() {
		return [
			'gesstatus' => [
				ParamValidator::PARAM_REQUIRED => true,
				ParamValidator::PARAM_TYPE => MentorStatusManager::STATUSES
			],
			'gesbackindays' => [
				ParamValidator::PARAM_TYPE => 'integer'
			]
		];
	}
}
