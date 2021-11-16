<?php

namespace GrowthExperiments\Api;

use ApiBase;
use ApiMain;
use GrowthExperiments\Mentorship\MentorManager;
use GrowthExperiments\Mentorship\Store\MentorStore;
use LogicException;
use Wikimedia\ParamValidator\ParamValidator;

class ApiSetMenteeStatus extends ApiBase {

	/** @var MentorManager */
	private $mentorManager;

	/** @var MentorStore */
	private $mentorStore;

	/**
	 * @param ApiMain $mainModule
	 * @param string $moduleName
	 * @param MentorManager $mentorManager
	 * @param MentorStore $mentorStore
	 */
	public function __construct(
		ApiMain $mainModule,
		$moduleName,
		MentorManager $mentorManager,
		MentorStore $mentorStore
	) {
		parent::__construct( $mainModule, $moduleName );

		$this->mentorManager = $mentorManager;
		$this->mentorStore = $mentorStore;
	}

	/**
	 * @inheritDoc
	 */
	public function execute() {
		if ( !$this->getConfig()->get( 'GEMentorshipEnabled' ) ) {
			$this->dieWithError( [ 'apierror-permissiondenied-generic' ] );
		}

		$params = $this->extractRequestParams();
		$user = $this->getAuthority()->getUser();

		switch ( $params['state'] ) {
			case 'enabled':
				$newState = MentorManager::MENTORSHIP_ENABLED;
				break;
			case 'disabled':
				$newState = MentorManager::MENTORSHIP_DISABLED;
				break;
			case 'optout':
				$newState = MentorManager::MENTORSHIP_OPTED_OUT;
				break;
			default:
				$newState = null;
				break;
		}

		if ( $newState === null ) {
			// should not happen, unless getAllowedParams is wrong
			throw new LogicException( 'Invalid mentee status passed through validation' );
		}

		$currentState = $this->mentorManager->getMentorshipStateForUser( $user );
		if ( $currentState === $newState ) {
			$this->dieWithError( [
				'apierror-growthexperiments-setmenteestatus-no-change'
			] );
		}

		$this->mentorManager->setMentorshipStateForUser(
			$user,
			$newState
		);

		$this->getResult()->addValue( null, $this->getModuleName(), [
			'status' => 'ok',
		] );
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
			'state' => [
				ParamValidator::PARAM_REQUIRED => true,
				ParamValidator::PARAM_TYPE => [
					'enabled',
					'disabled',
					'optout'
				],
				ApiBase::PARAM_HELP_MSG_PER_VALUE => [
					'enabled' => 'apihelp-growthsetmenteestatus-param-state-enabled',
					'disabled' => 'apihelp-growthsetmenteestatus-param-state-disabled',
					'optout' => 'apihelp-growthsetmenteestatus-param-state-optout',
				]
			]
		];
	}
}
