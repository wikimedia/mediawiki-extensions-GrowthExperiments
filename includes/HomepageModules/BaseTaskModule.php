<?php

namespace GrowthExperiments\HomepageModules;

abstract class BaseTaskModule extends BaseModule {

	/**
	 * @return bool Whether this task has been completed by the user.
	 */
	abstract public function isCompleted();

	/**
	 * Add the -completed class so tasks can be styled differently when they are completed.
	 *
	 * @return string[]
	 */
	protected function getCssClasses() {
		if ( $this->isCompleted() ) {
			return [ self::BASE_CSS_CLASS . '-completed' ];
		}
		return [];
	}
}
