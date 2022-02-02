<?php

namespace GrowthExperiments\Api;

use ApiBase;
use ApiMain;
use GrowthExperiments\MentorDashboard\MentorTools\MentorWeightManager;
use GrowthExperiments\Mentorship\Provider\MentorProvider;
use Wikimedia\ParamValidator\ParamValidator;

class ApiSetMentorWeight extends ApiBase {

	/** @var MentorProvider */
	private $mentorProvider;

	/** @var MentorWeightManager */
	private $mentorWeightManager;

	/**
	 * @param ApiMain $mainModule
	 * @param string $moduleName
	 * @param MentorProvider $mentorProvider
	 * @param MentorWeightManager $mentorWeightManager
	 */
	public function __construct(
		ApiMain $mainModule,
		$moduleName,
		MentorProvider $mentorProvider,
		MentorWeightManager $mentorWeightManager
	) {
		parent::__construct( $mainModule, $moduleName );

		$this->mentorProvider = $mentorProvider;
		$this->mentorWeightManager = $mentorWeightManager;
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
