<?php

namespace GrowthExperiments\NewcomerTasks\TaskType;

use GrowthExperiments\NewcomerTasks\ConfigurationLoader\ConfigurationValidator;
use InvalidArgumentException;
use MalformedTitleException;
use MediaWiki\Linker\LinkTarget;
use StatusValue;
use TitleParser;

/**
 * A handler for task types that represent an article with a certain maintenance template on it.
 */
class TemplateBasedTaskTypeHandler extends TaskTypeHandler {

	public const ID = 'template-based';

	/** @var TitleParser */
	private $titleParser;

	/**
	 * @param ConfigurationValidator $configurationValidator
	 * @param TitleParser $titleParser
	 */
	public function __construct(
		ConfigurationValidator $configurationValidator,
		TitleParser $titleParser
	) {
		parent::__construct( $configurationValidator );
		$this->titleParser = $titleParser;
	}

	/** @inheritDoc */
	public function getId(): string {
		return self::ID;
	}

	/** @inheritDoc */
	public function validateTaskTypeConfiguration( string $taskTypeId, array $config ): StatusValue {
		$status = parent::validateTaskTypeConfiguration( $taskTypeId, $config );

		$templateFieldStatus = $this->configurationValidator->validateRequiredField( 'templates',
			$config, $taskTypeId );
		$status->merge( $templateFieldStatus );
		if ( $templateFieldStatus->isOK() ) {
			foreach ( $config['templates'] as $template ) {
				try {
					$this->titleParser->parseTitle( $template, NS_TEMPLATE );
				} catch ( MalformedTitleException $e ) {
					$status->fatal( 'growthexperiments-homepage-suggestededits-config-invalidtemplatetitle',
						$template, $taskTypeId );
				}
			}
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
			$templates
		);
		$taskType->setHandlerId( $this->getId() );
		return $taskType;
	}

	/** @inheritDoc */
	public function getSearchTerm( TaskType $taskType ): string {
		if ( !$taskType instanceof TemplateBasedTaskType ) {
			throw new InvalidArgumentException( '$taskType must be a TemplateBasedTaskType' );
		}
		$templates = $taskType->getTemplates();
		return 'hastemplate:' . $this->escapeSearchTitleList( $templates );
	}

	/**
	 * @param LinkTarget[] $titles
	 * @return string
	 */
	protected function escapeSearchTitleList( array $titles ) {
		// FIXME duplicate of SearchStrategy::escapeSearchTitleList
		return '"' . implode( '|', array_map( function ( LinkTarget $title ) {
			return str_replace( [ '"', '?' ], [ '\"', '\?' ], $title->getDBkey() );
		}, $titles ) ) . '"';
	}

}
