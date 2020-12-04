<?php

namespace GrowthExperiments\NewcomerTasks\TaskType;

use MediaWiki\Json\JsonUnserializer;
use MediaWiki\Linker\LinkTarget;
use TitleValue;

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
	 */
	public function __construct( $id, $difficulty, array $extraData, array $templates ) {
		parent::__construct( $id, $difficulty, $extraData );
		$this->templates = $templates;
	}

	/**
	 * @return LinkTarget[]
	 */
	public function getTemplates() : array {
		return $this->templates;
	}

	/** @inheritDoc */
	public function toJsonArray(): array {
		return [
			'id' => $this->getId(),
			'difficulty' => $this->getDifficulty(),
			'extraData' => [ 'learnMoreLink' => $this->getLearnMoreLink() ],
			'handlerId' => $this->getHandlerId(),
			'templates' => array_map( function ( LinkTarget $template ) {
				return [ $template->getNamespace(), $template->getDBkey() ];
			}, $this->getTemplates() ),
		];
	}

	/** @inheritDoc */
	public static function newFromJsonArray( JsonUnserializer $unserializer, array $json ) {
		$templates = array_map( function ( array $template ) {
			return new TitleValue( $template[0], $template[1] );
		}, $json['templates'] );
		$taskType = new TemplateBasedTaskType( $json['id'], $json['difficulty'], $json['extraData'],
			$templates );
		$taskType->setHandlerId( $json['handlerId'] );
		return $taskType;
	}

}
