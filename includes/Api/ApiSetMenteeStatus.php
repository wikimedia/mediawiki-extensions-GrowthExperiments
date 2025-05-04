<?php

namespace GrowthExperiments\Api;

use GrowthExperiments\Mentorship\IMentorManager;
use LogicException;
use MediaWiki\Api\ApiBase;
use MediaWiki\Api\ApiMain;
use MediaWiki\Config\Config;
use Wikimedia\ParamValidator\ParamValidator;

class ApiSetMenteeStatus extends ApiBase {

	private Config $wikiConfig;
	private IMentorManager $mentorManager;

	public function __construct(
		ApiMain $mainModule,
		string $moduleName,
		Config $wikiConfig,
		IMentorManager $mentorManager
	) {
		parent::__construct( $mainModule, $moduleName );

		$this->wikiConfig = $wikiConfig;
		$this->mentorManager = $mentorManager;
	}

	/**
	 * @inheritDoc
	 */
	public function execute() {
		if ( !$this->wikiConfig->get( 'GEMentorshipEnabled' ) ) {
			$this->dieWithError( [ 'apierror-permissiondenied-generic' ] );
		}
		if ( !$this->getUser()->isNamed() ) {
			$this->dieWithError( [ 'apierror-permissiondenied-generic' ] );
		}

		$params = $this->extractRequestParams();
		$user = $this->getAuthority()->getUser();

		switch ( $params['state'] ) {
			case 'enabled':
				$newState = IMentorManager::MENTORSHIP_ENABLED;
				break;
			case 'disabled':
				$newState = IMentorManager::MENTORSHIP_DISABLED;
				break;
			case 'optout':
				$newState = IMentorManager::MENTORSHIP_OPTED_OUT;
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
