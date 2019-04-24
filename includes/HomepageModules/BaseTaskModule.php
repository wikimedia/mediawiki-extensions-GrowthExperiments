<?php

namespace GrowthExperiments\HomepageModules;

use Html;
use OOUI\IconWidget;

abstract class BaseTaskModule extends BaseModule {

	/**
	 * @return bool Whether this task has been completed by the user.
	 */
	abstract public function isCompleted();

	/**
	 * The header of a BaseTaskModule contains an icon and a message. The icon is an inverted check
	 * mark when the task is completed, or the icon name returned by this method when the task
	 * is not completed.
	 *
	 * If this method returns false, no icon will be displayed (even if the task is completed).
	 *
	 * @return string|bool Icon name to use when the task is not completed, or false to disable icons
	 */
	abstract protected function getUncompletedIcon();

	/**
	 * @return string Text to use in the header next to the icon
	 */
	abstract protected function getHeaderText();

	/**
	 * @inheritDoc
	 */
	protected function getHeader() {
		$uncompletedIcon = $this->getUncompletedIcon();
		if ( $uncompletedIcon === false ) {
			$icon = '';
		} else {
			$icon = Html::rawElement(
				'div',
				[ 'class' => self::BASE_CSS_CLASS . '-header-icon' ],
				new IconWidget( [
					'icon' => $this->isCompleted() ? 'check' : $uncompletedIcon,
					// HACK: IconWidget doesn't let us set 'invert' => true, and setting
					// 'classes' => [ 'oo-ui-image-invert' ] doesn't work either, because
					// Theme::getElementClasses() will unset it again. So instead, trick that code into
					// thinking this is a checkbox icon, which will cause it to invert the icon
					'classes' => $this->isCompleted() ?
						[ 'oo-ui-image-invert', 'oo-ui-checkboxInputWidget-checkIcon' ] :
						[]
				] )
			);
		}
		$span = Html::element(
			'span',
			[ 'class' => self::BASE_CSS_CLASS . '-header-text' ],
			$this->getHeaderText()
		);
		return $icon . $span;
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
}
