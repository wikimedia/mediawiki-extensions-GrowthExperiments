<?php

namespace GrowthExperiments\Api;

use GrowthExperiments\LevelingUp\LevelingUpManager;
use GrowthExperiments\NewcomerTasks\ConfigurationLoader\ConfigurationLoader;
use GrowthExperiments\NewcomerTasks\TaskType\TaskTypeManager;
use GrowthExperiments\UserImpact\UserImpactLookup;
use MediaWiki\Api\ApiQuery;
use MediaWiki\Api\ApiQueryBase;
use Wikimedia\ParamValidator\ParamValidator;

/**
 * API module for interacting with the LevelingUpManager, for suggesting new task types to eligible
 * users. {@see LevelingUpManager}
 */
class ApiQueryNextSuggestedTaskType extends ApiQueryBase {

	private LevelingUpManager $levelingUpManager;
	private ConfigurationLoader $configurationLoader;
	private UserImpactLookup $userImpactLookup;
	private TaskTypeManager $taskTypeManager;

	public function __construct(
		ApiQuery $queryModule,
		string $moduleName,
		ConfigurationLoader $configurationLoader,
		LevelingUpManager $levelingUpManager,
		UserImpactLookup $userImpactLookup,
		TaskTypeManager $taskTypeManager
	) {
		parent::__construct( $queryModule, $moduleName, 'gnstt' );
		$this->levelingUpManager = $levelingUpManager;
		$this->configurationLoader = $configurationLoader;
		$this->userImpactLookup = $userImpactLookup;
		$this->taskTypeManager = $taskTypeManager;
	}

	/**
	 * @inheritDoc
	 */
	public function execute() {
		if ( !$this->getUser()->isNamed() ) {
			$this->dieWithError( 'apierror-mustbeloggedin-generic' );
		}
		$params = $this->extractRequestParams();
		$this->getResult()->addValue(
			'query',
			$this->getModuleName(),
			$this->levelingUpManager->suggestNewTaskTypeForUser(
				$this->getUser(),
				$params['activetasktype'],
				true,
				$this->taskTypeManager->getAvailableTaskTypesOnNextEdit( $this->getUser() )
			)
		);
		$userImpact = $this->userImpactLookup->getUserImpact( $this->getUser() );
		// User impact should definitely exist, but it's typed to potentially return null, so check to be sure.
		if ( $userImpact ) {
			// For instrumentation, export the edit count by task type data to the client-side.
			// We can also use this to implement the "only show every Nth edit" rule when the
			// user makes multiple edits to an article without reloading the page.
			// This should logically be in a separate API module, but doesn't seem worth the boilerplate
			// until there is a use case separate from the "try next task type" workflow.
			$this->getResult()->addValue(
				'query',
				'editcountbytasktype',
				$userImpact->getEditCountByTaskType()
			);
		}
	}

	/** @inheritDoc */
	public function mustBePosted() {
		return true;
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
			],
		];
	}
}
