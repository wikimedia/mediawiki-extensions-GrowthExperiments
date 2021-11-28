<?php

namespace GrowthExperiments\Api;

use ApiBase;
use ApiMain;
use GrowthExperiments\MentorDashboard\MentorTools\MentorWeightManager;
use GrowthExperiments\Mentorship\MentorManager;
use Wikimedia\ParamValidator\ParamValidator;

class ApiSetMentorWeight extends ApiBase {

	/** @var MentorManager */
	private $mentorManager;

	/** @var MentorWeightManager */
	private $mentorWeightManager;

	/**
	 * @param ApiMain $mainModule
	 * @param string $moduleName
	 * @param MentorManager $mentorManager
	 * @param MentorWeightManager $mentorWeightManager
	 */
	public function __construct(
		ApiMain $mainModule,
		$moduleName,
		MentorManager $mentorManager,
		MentorWeightManager $mentorWeightManager
	) {
		parent::__construct( $mainModule, $moduleName );

		$this->mentorManager = $mentorManager;
		$this->mentorWeightManager = $mentorWeightManager;
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
		$weight = (int)$params['geweight'];

		$this->mentorWeightManager->setWeightForMentor(
			$this->getUser(),
			$weight
		);

		$this->getResult()->addValue( null, $this->getModuleName(), [
			'status' => 'ok',
			'newweight' => $weight
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
			'geweight' => [
				ParamValidator::PARAM_REQUIRED => true,
				ParamValidator::PARAM_TYPE => array_map( static function ( $el ) {
					// for some reason, this must be a string
					return (string)$el;
				}, MentorWeightManager::WEIGHTS )
			]
		];
	}
}
