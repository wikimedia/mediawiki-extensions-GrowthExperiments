<?php

namespace GrowthExperiments\NewcomerTasks\TaskType;

use MediaWiki\Linker\LinkTarget;

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

}
