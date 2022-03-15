<?php

namespace GrowthExperiments\Api;

use ApiBase;
use ApiMain;
use ApiUsageException;
use GrowthExperiments\NewcomerTasks\AddImage\AddImageSubmissionHandler;
use GrowthExperiments\NewcomerTasks\NewcomerTasksUserOptionsLookup;
use GrowthExperiments\NewcomerTasks\Task\TaskSetFilters;
use GrowthExperiments\NewcomerTasks\TaskSuggester\TaskSuggesterFactory;
use MediaWiki\Page\ProperPageIdentity;
use MediaWiki\ParamValidator\TypeDef\TitleDef;
use Title;
use Wikimedia\ParamValidator\ParamValidator;

/**
 * Endpoint for invalidating image recommendation.
 *
 * This is used when the recommendation is determined to be invalid upon display (for example,
 * when the article already has an image). See mw.libs.ge.AddImageArticleTarget.
 */
class ApiInvalidateImageRecommendation extends ApiBase {

	private const API_PARAM_TITLE = 'title';

	/** @var AddImageSubmissionHandler */
	private $imageSubmissionHandler;

	/** @var TaskSuggesterFactory */
	private $taskSuggesterFactory;

	/** @var NewcomerTasksUserOptionsLookup */
	private $newcomerTasksUserOptionsLookup;

	/**
	 * @param ApiMain $mainModule
	 * @param string $moduleName
	 * @param AddImageSubmissionHandler $imageSubmissionHandler
	 * @param TaskSuggesterFactory $taskSuggesterFactory
	 * @param NewcomerTasksUserOptionsLookup $newcomerTasksUserOptionsLookup
	 */
	public function __construct(
		ApiMain $mainModule,
		string $moduleName,
		AddImageSubmissionHandler $imageSubmissionHandler,
		TaskSuggesterFactory $taskSuggesterFactory,
		NewcomerTasksUserOptionsLookup $newcomerTasksUserOptionsLookup
	) {
		parent::__construct( $mainModule, $moduleName );
		$this->imageSubmissionHandler = $imageSubmissionHandler;
		$this->taskSuggesterFactory = $taskSuggesterFactory;
		$this->newcomerTasksUserOptionsLookup = $newcomerTasksUserOptionsLookup;
	}

	/**
	 * @inheritDoc
	 * @throws ApiUsageException
	 */
	public function execute() {
		$params = $this->extractRequestParams();
		$titleValue = $params[ self::API_PARAM_TITLE ];
		$page = Title::newFromLinkTarget( $titleValue )->toPageIdentity();
		if ( $this->shouldInvalidatePage( $page ) ) {
			$this->imageSubmissionHandler->invalidateRecommendation( $page );
			$this->getResult()->addValue( null, $this->getModuleName(), [
				'status' => 'ok'
			] );
		} else {
			$this->dieWithError( [ 'apierror-invalidtitle', $titleValue->getDBkey() ] );
		}
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
	public function mustBePosted() {
		return true;
	}

	/**
	 * @inheritDoc
	 */
	public function getAllowedParams() {
		return [
			self::API_PARAM_TITLE => [
				ParamValidator::PARAM_REQUIRED => true,
				ParamValidator::PARAM_TYPE => 'title',
				TitleDef::PARAM_RETURN_OBJECT => true,
			]
		];
	}

	/** @inheritDoc */
	public function isInternal() {
		return true;
	}

	/**
	 * Check whether the specified page exists and is in the user's task set
	 *
	 * @param ProperPageIdentity $page
	 * @return bool
	 */
	private function shouldInvalidatePage( ProperPageIdentity $page ): bool {
		if ( !$page->exists() ) {
			return false;
		}
		$user = $this->getUser();
		$taskSet = $this->taskSuggesterFactory->create()->suggest(
			$user,
			new TaskSetFilters(
				$this->newcomerTasksUserOptionsLookup->getTaskTypeFilter( $user ),
				$this->newcomerTasksUserOptionsLookup->getTopics( $user ),
				$this->newcomerTasksUserOptionsLookup->getTopicsMatchMode( $user )
			)
		);
		return $taskSet->containsPage( $page );
	}
}
