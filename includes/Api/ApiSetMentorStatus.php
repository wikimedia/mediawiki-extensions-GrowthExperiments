<?php

namespace GrowthExperiments\Api;

use ApiBase;
use ApiMain;
use GrowthExperiments\MentorDashboard\MentorTools\MentorStatusManager;
use GrowthExperiments\Mentorship\Provider\MentorProvider;
use LogicException;
use Wikimedia\ParamValidator\ParamValidator;

class ApiSetMentorStatus extends ApiBase {

	/** @var MentorProvider */
	private $mentorProvider;

	/** @var MentorStatusManager */
	private $mentorStatusManager;

	/**
	 * @param ApiMain $mainModule
	 * @param string $moduleName
	 * @param MentorProvider $mentorProvider
	 * @param MentorStatusManager $mentorStatusManager
	 */
	public function __construct(
		ApiMain $mainModule,
		$moduleName,
		MentorProvider $mentorProvider,
		MentorStatusManager $mentorStatusManager
	) {
		parent::__construct( $mainModule, $moduleName );

		$this->mentorProvider = $mentorProvider;
		$this->mentorStatusManager = $mentorStatusManager;
	}

	/**
	 * @inheritDoc
	 */
	public function execute() {
		if (
			!$this->getConfig()->get( 'GEMentorDashboardEnabled' ) ||
			!$this->mentorProvider->isMentor( $this->getUser() )
		) {
			$this->dieWithError( [ 'apierror-permissiondenied-generic' ] );
		}

		$params = $this->extractRequestParams();
		$mentor = $this->getUser();

		switch ( $params['gesstatus'] ) {
			case MentorStatusManager::STATUS_ACTIVE:
				$status = $this->mentorStatusManager->markMentorAsActive( $mentor );
				break;
			case MentorStatusManager::STATUS_AWAY:
				$this->requireAtLeastOneParameter( $params, 'gesbackindays' );

				$status = $this->mentorStatusManager->markMentorAsAway(
					$mentor,
					(int)$params['gesbackindays']
				);
				break;
			default:
				throw new LogicException(
					__METHOD__ . ' got called with unexpected gesstatus'
				);
		}

		if ( !$status->isOK() ) {
			$this->dieStatus( $status );
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
	public function isDeprecated() {
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
