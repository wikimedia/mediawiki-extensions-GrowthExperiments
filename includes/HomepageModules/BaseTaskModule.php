<?php

namespace GrowthExperiments\HomepageModules;

/**
 * Homepage module base class for submodules (subtasks) of the Start module.
 */
abstract class BaseTaskModule extends BaseModule {

	/**
	 * @return bool Whether this task has been completed by the user.
	 */
	abstract public function isCompleted();

	/**
	 * @return bool Whether this task should be visible. Return false to hide the task.
	 */
	public function isVisible() {
		return true;
	}

	/**
	 * Determine whether the icon should be inverted (white icon, for darker backgrounds).
	 * By default, the icon is inverted when the module is in the completed state. Subclasses can
	 * override this to change when inverted icons are used.
	 *
	 * @return bool Icon is inverted
	 */
	protected function shouldInvertHeaderIcon() {
		return $this->isCompleted();
	}

	/**
	 * @inheritDoc
	 */
	protected function getHeader() {
		$iconName = $this->isCompleted() && $this->getMode() !== self::RENDER_MOBILE_SUMMARY ?
			'check' :
			$this->getHeaderIconName();

		$icon = $iconName !== null ?
			$this->getHeaderIcon( $iconName, $this->shouldInvertHeaderIcon() ) : '';
		$text = $this->getHeaderTextElement();
		return $icon . $text;
	}

	/**
	 * @inheritDoc
	 */
	protected function getMobileSummaryHeader() {
		return $this->getHeader();
	}

	/**
	 * @inheritDoc
	 */
	protected function getHeaderTag() {
		return 'h3';
	}

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

	/**
	 * @inheritDoc
	 */
	public function getState() {
		return $this->isCompleted() ? self::MODULE_STATE_COMPLETE : self::MODULE_STATE_INCOMPLETE;
	}

	/**
	 * @inheritDoc
	 */
	protected function getMobileSummaryBody() {
		return '';
	}
}
