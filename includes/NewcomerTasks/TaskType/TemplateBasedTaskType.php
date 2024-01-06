<?php

namespace GrowthExperiments\NewcomerTasks\TaskType;

use MediaWiki\Json\JsonUnserializer;
use MediaWiki\Linker\LinkTarget;
use MediaWiki\Title\TitleValue;

class TemplateBasedTaskType extends TaskType {

	/**
	 * List of templates any one of which identifies a wiki page as a candidate for this task type.
	 * @var LinkTarget[]
	 */
	private $templates;

	/**
	 * @param string $id Task type ID, e.g. 'copyedit'.
	 * @param string $difficulty One of the DIFFICULTY_* constants.
	 * @param array $extraData See TaskType::__construct()
	 * @param LinkTarget[] $templates
	 * @param LinkTarget[] $excludedTemplates
	 * @param LinkTarget[] $excludedCategories
	 */
	public function __construct(
		$id,
		$difficulty,
		array $extraData,
		array $templates,
		array $excludedTemplates = [],
		array $excludedCategories = []
	) {
		parent::__construct( $id, $difficulty, $extraData, $excludedTemplates, $excludedCategories );
		$this->templates = $templates;
	}

	/**
	 * @return LinkTarget[]
	 */
	public function getTemplates(): array {
		return $this->templates;
	}

	/** @inheritDoc */
	public function toJsonArray(): array {
		return array_merge( parent::toJsonArray(), [
			'templates' => array_map( static function ( LinkTarget $template ) {
				return [ $template->getNamespace(), $template->getDBkey() ];
			}, $this->getTemplates() )
		] );
	}

	/** @inheritDoc */
	public static function newFromJsonArray( JsonUnserializer $unserializer, array $json ) {
		$templates = array_map( static function ( array $template ) {
			return new TitleValue( $template[0], $template[1] );
		}, $json['templates'] );

		$taskType = new TemplateBasedTaskType(
			$json['id'],
			$json['difficulty'],
			$json['extraData'],
			$templates,
			self::getExcludedTemplatesTitleValues( $json ),
			self::getExcludedCategoriesTitleValues( $json )
		);
		$taskType->setHandlerId( $json['handlerId'] );
		return $taskType;
	}

}
