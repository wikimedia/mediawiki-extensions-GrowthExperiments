<?php

namespace GrowthExperiments\NewcomerTasks\Task;

//phpcs:ignore MediaWiki.Classes.UnusedUseStatement.UnusedUse
use GrowthExperiments\NewcomerTasks\TaskType\TaskType;
use GrowthExperiments\NewcomerTasks\TaskType\TemplateBasedTaskType;
use MediaWiki\Linker\LinkTarget;

/**
 * A task corresponding to a TemplateBasedTaskType.
 * @deprecated remove this once it's cleared out of the task cache
 */
class TemplateBasedTask extends Task {

	/**
	 * List of templates which this task was derived from.
	 * This is a subset of $this->getTaskType()->getTemplates()
	 * @var LinkTarget[]
	 */
	private $templates = [];

	/**
	 * @return TemplateBasedTaskType
	 */
	public function getTaskType(): TaskType {
		return parent::getTaskType();
	}

	/**
	 * @return LinkTarget[]
	 */
	public function getTemplates() : array {
		return $this->templates;
	}

	/**
	 * @param LinkTarget $template
	 * @internal For use by TemplateProvider only.
	 */
	public function addTemplate( LinkTarget $template ) : void {
		$this->templates[] = $template;
	}

}
