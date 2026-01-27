<?php

namespace GrowthExperiments\Rest\Handler;

use GrowthExperiments\FeatureManager;
use GrowthExperiments\GrowthExperimentsServices;
use GrowthExperiments\HelpPanel\Tips\TipsAssembler;
use GrowthExperiments\NewcomerTasks\ConfigurationLoader\ConfigurationLoader;
use MediaWiki\Context\DerivativeContext;
use MediaWiki\Context\RequestContext;
use MediaWiki\MediaWikiServices;
use MediaWiki\Rest\Response;
use MediaWiki\Rest\SimpleHandler;
use Wikimedia\ParamValidator\ParamValidator;

/**
 * Handle incoming requests to obtain tips for a skin, editor, task type id,
 * and language. Returns a JSON response that can be placed into the suggested
 * edits guidance screen in the help panel.
 */
class TipsHandler extends SimpleHandler {

	private const MAX_CACHE_AGE_SECONDS = 3600;

	public function __construct(
		private readonly TipsAssembler $tipsAssembler,
		private readonly ConfigurationLoader $configurationLoader,
		private readonly FeatureManager $featureManager,
	) {
	}

	public function run( string $skin, string $editor, string $tasktypeid, string $uselang ): Response {
		$context = new DerivativeContext( RequestContext::getMain() );
		if ( $uselang ) {
			$context->setLanguage( $uselang );
		}
		// FIXME the context language should be set by the API framework
		$this->tipsAssembler->setMessageLocalizer( $context );
		GrowthExperimentsServices::wrap( MediaWikiServices::getInstance() )
			->getNewcomerTasksConfigurationValidator()->setMessageLocalizer( $context );
		$taskTypes = $this->getTaskTypes();
		$response = $this->getResponseFactory()->createJson(
			$this->tipsAssembler->getTips(
				$skin,
				$editor,
				$taskTypes,
				$tasktypeid,
				$context->getLanguage()->getDir()
			)
		);
		$response->setHeader( 'Cache-Control', 'public, max-age=' . self::MAX_CACHE_AGE_SECONDS );
		return $response;
	}

	private function getTaskTypes(): array {
		// Prevent calls to suggested edits config when feature is disabled, (T369312)
		if ( !$this->featureManager->isNewcomerTasksAvailable() ) {
			return [];
		}
		return $this->configurationLoader->getTaskTypes();
	}

	/** @inheritDoc */
	public function needsWriteAccess() {
		return false;
	}

	/** @inheritDoc */
	public function getParamSettings() {
		return [
			'skin' => [
				self::PARAM_SOURCE => 'path',
				ParamValidator::PARAM_REQUIRED => true,
				ParamValidator::PARAM_TYPE => 'string',
			],
			'editor' => [
				self::PARAM_SOURCE => 'path',
				ParamValidator::PARAM_REQUIRED => true,
				ParamValidator::PARAM_TYPE => 'string',
			],
			'tasktypeid' => [
				self::PARAM_SOURCE => 'path',
				ParamValidator::PARAM_REQUIRED => true,
				ParamValidator::PARAM_TYPE => array_keys( $this->getTaskTypes() ),
			],
			'uselang' => [
				self::PARAM_SOURCE => 'path',
				ParamValidator::PARAM_REQUIRED => true,
			],
		];
	}
}
