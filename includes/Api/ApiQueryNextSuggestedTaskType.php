<?php

namespace GrowthExperiments\Api;

use ApiQuery;
use ApiQueryBase;
use GrowthExperiments\LevelingUp\LevelingUpManager;
use GrowthExperiments\NewcomerTasks\ConfigurationLoader\ConfigurationLoader;
use Wikimedia\ParamValidator\ParamValidator;

/**
 * API module for interacting with the LevelingUpManager, for suggesting new task types to eligible
 * users. {@see LevelingUpManager}
 */
class ApiQueryNextSuggestedTaskType extends ApiQueryBase {

	private LevelingUpManager $levelingUpManager;
	private ConfigurationLoader $configurationLoader;

	/**
	 * @param ApiQuery $queryModule
	 * @param string $moduleName
	 * @param ConfigurationLoader $configurationLoader
	 * @param LevelingUpManager $levelingUpManager
	 */
	public function __construct(
		ApiQuery $queryModule,
		$moduleName,
		ConfigurationLoader $configurationLoader,
		LevelingUpManager $levelingUpManager
	) {
		parent::__construct( $queryModule, $moduleName, 'gnstt' );
		$this->levelingUpManager = $levelingUpManager;
		$this->configurationLoader = $configurationLoader;
	}

	/**
	 * @inheritDoc
	 */
	public function execute() {
		if ( $this->getUser()->isAnon() ) {
			$this->dieWithError( 'apierror-mustbeloggedin-generic' );
		}
		$params = $this->extractRequestParams();
		$this->getResult()->addValue(
			'query',
			$this->getModuleName(),
			$this->levelingUpManager->suggestNewTaskTypeForUser( $this->getUser(), $params['activetasktype'] )
		);
	}

	/**
	 * @inheritDoc
	 * @codeCoverageIgnore
	 */
	public function isInternal() {
		return true;
	}

	/**
	 * @inheritDoc
	 */
	public function getAllowedParams() {
		$taskTypes = $this->configurationLoader->getTaskTypes();
		return [
			'activetasktype' => [
				ParamValidator::PARAM_TYPE => array_keys( $taskTypes ),
				ParamValidator::PARAM_REQUIRED => true,
			]
		];
	}
}
