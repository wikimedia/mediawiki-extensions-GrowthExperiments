<?php

declare( strict_types = 1 );

namespace GrowthExperiments\Api;

use GrowthExperiments\NewcomerTasks\ReviseTone\ReviseToneWeightedTagManager;
use MediaWiki\Api\ApiBase;
use MediaWiki\Api\ApiMain;
use MediaWiki\ParamValidator\TypeDef\TitleDef;
use MediaWiki\Title\TitleFactory;
use Wikimedia\ParamValidator\ParamValidator;

class ApiInvalidateReviseToneRecommendation extends ApiBase {
	public function __construct(
		ApiMain $mainModule,
		string $moduleName,
		private readonly ReviseToneWeightedTagManager $weightedTagsManager,
		private readonly TitleFactory $titleFactory,
		string $modulePrefix = '',
	) {
		parent::__construct( $mainModule, $moduleName, $modulePrefix );
	}

	/**
	 * @inheritDoc
	 */
	public function execute() {
		if ( !$this->getUser()->isNamed() ) {
			$this->dieWithError( [ 'apierror-mustbeloggedin-generic' ] );
		}

		$params = $this->extractRequestParams();
		$pagename = $params['title'];

		$title = $this->titleFactory->makeTitle( 0, $pagename );

		$this->weightedTagsManager->deletePageReviseToneWeightedTag( $title->toPageIdentity() );
	}

	public function needsToken(): string {
		return 'csrf';
	}

	/**
	 * @inheritDoc
	 */
	public function mustBePosted(): bool {
		return true;
	}

	/**
	 * @inheritDoc
	 */
	public function isWriteMode(): bool {
		return true;
	}

	/**
	 * @inheritDoc
	 */
	public function getAllowedParams(): array {
		return [
			'title' => [
				ParamValidator::PARAM_REQUIRED => true,
				ParamValidator::PARAM_TYPE => 'title',
				TitleDef::PARAM_MUST_EXIST => true,
			],
		];
	}

	/** @inheritDoc */
	public function isInternal(): bool {
		return true;
	}
}
