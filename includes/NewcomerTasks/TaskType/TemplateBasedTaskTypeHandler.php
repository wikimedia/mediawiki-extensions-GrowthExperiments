<?php

namespace GrowthExperiments\NewcomerTasks\TaskType;

use GrowthExperiments\Config\Schemas\SuggestedEditsSchema;
use GrowthExperiments\NewcomerTasks\ConfigurationLoader\ConfigurationValidator;
use GrowthExperiments\NewcomerTasks\SubmissionHandler;
use GrowthExperiments\NewcomerTasks\TemplateBasedTaskSubmissionHandler;
use InvalidArgumentException;
use MediaWiki\Title\TitleParser;
use StatusValue;

/**
 * A handler for task types that represent an article with a certain maintenance template on it.
 */
class TemplateBasedTaskTypeHandler extends TaskTypeHandler {

	public const ID = 'template-based';

	public const NEWCOMER_TASK_COPYEDIT_TAG = 'newcomer task copyedit';
	public const NEWCOMER_TASK_REFERENCES_TAG = 'newcomer task references';
	public const NEWCOMER_TASK_UPDATE_TAG = 'newcomer task update';
	public const NEWCOMER_TASK_EXPAND_TAG = 'newcomer task expand';
	public const NEWCOMER_TASK_LINKS_TAG = 'newcomer task links';

	public const NEWCOMER_TASK_TEMPLATE_BASED_ALL_CHANGE_TAGS = [
		self::NEWCOMER_TASK_COPYEDIT_TAG,
		self::NEWCOMER_TASK_REFERENCES_TAG,
		self::NEWCOMER_TASK_UPDATE_TAG,
		self::NEWCOMER_TASK_EXPAND_TAG,
		self::NEWCOMER_TASK_LINKS_TAG,
	];

	/** @var TitleParser */
	private $titleParser;

	/** @var TemplateBasedTaskSubmissionHandler */
	private $submissionHandler;

	/**
	 * @param ConfigurationValidator $configurationValidator
	 * @param TemplateBasedTaskSubmissionHandler $submissionHandler
	 * @param TitleParser $titleParser
	 */
	public function __construct(
		ConfigurationValidator $configurationValidator,
		TemplateBasedTaskSubmissionHandler $submissionHandler,
		TitleParser $titleParser
	) {
		parent::__construct( $configurationValidator, $titleParser );
		$this->titleParser = $titleParser;
		$this->submissionHandler = $submissionHandler;
	}

	/** @inheritDoc */
	public function getId(): string {
		return self::ID;
	}

	/** @inheritDoc */
	public function validateTaskTypeConfiguration( string $taskTypeId, array $config ): StatusValue {
		$status = parent::validateTaskTypeConfiguration( $taskTypeId, $config );

		$status->merge( $this->configurationValidator->validateArrayMaxSize(
			SuggestedEditsSchema::MAX_INFOBOX_TEMPLATES, $config['templates'],
			$taskTypeId, 'templates' ) );
		foreach ( $config['templates'] as $template ) {
			$this->validateTemplate( $template, $taskTypeId, $status );
		}
		return $status;
	}

	/** @inheritDoc */
	public function createTaskType( string $taskTypeId, array $config ): TaskType {
		'@phan-var array{group:string,templates:string[]} $config';
		$templates = [];
		foreach ( $config['templates'] as $template ) {
			$templates[] = $this->titleParser->parseTitle( $template, NS_TEMPLATE );
		}
		$taskType = new TemplateBasedTaskType(
			$taskTypeId,
			$config['group'],
			[ 'learnMoreLink' => $config['learnmore'] ?? null ],
			$templates,
			$this->parseExcludedTemplates( $config ),
			$this->parseExcludedCategories( $config )
		);
		$taskType->setHandlerId( $this->getId() );
		return $taskType;
	}

	/** @inheritDoc */
	public function getSearchTerm( TaskType $taskType ): string {
		if ( !$taskType instanceof TemplateBasedTaskType ) {
			throw new InvalidArgumentException( '$taskType must be a TemplateBasedTaskType' );
		}
		return parent::getSearchTerm( $taskType ) .
			'hastemplate:' . Util::escapeSearchTitleList( $taskType->getTemplates() );
	}

	/**
	 * @return TemplateBasedTaskSubmissionHandler
	 */
	public function getSubmissionHandler(): SubmissionHandler {
		return $this->submissionHandler;
	}

	/** @inheritDoc */
	public function getChangeTags( ?string $taskType = null ): array {
		if ( !$taskType ) {
			return self::NEWCOMER_TASK_TEMPLATE_BASED_ALL_CHANGE_TAGS;
		}
		switch ( $taskType ) {
			case 'copyedit':
				$taskTypeSpecificTag = self::NEWCOMER_TASK_COPYEDIT_TAG;
				break;
			case 'references':
				$taskTypeSpecificTag = self::NEWCOMER_TASK_REFERENCES_TAG;
				break;
			case 'update':
				$taskTypeSpecificTag = self::NEWCOMER_TASK_UPDATE_TAG;
				break;
			case 'expand':
				$taskTypeSpecificTag = self::NEWCOMER_TASK_EXPAND_TAG;
				break;
			case 'links':
				$taskTypeSpecificTag = self::NEWCOMER_TASK_LINKS_TAG;
				break;
			default:
				throw new InvalidArgumentException( "$taskType is not valid." );
		}
		return array_merge( parent::getChangeTags(), [ $taskTypeSpecificTag ] );
	}

	/** @inheritDoc */
	public function getTaskTypeIdByChangeTagName( string $changeTagName ): ?string {
		switch ( $changeTagName ) {
			case self::NEWCOMER_TASK_COPYEDIT_TAG:
				return 'copyedit';
			case self::NEWCOMER_TASK_REFERENCES_TAG:
				return 'references';
			case self::NEWCOMER_TASK_UPDATE_TAG:
				return 'update';
			case self::NEWCOMER_TASK_EXPAND_TAG:
				return 'expand';
			case self::NEWCOMER_TASK_LINKS_TAG:
				return 'links';
			default:
				throw new InvalidArgumentException( "$changeTagName is not valid" );
		}
	}

}
