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
			$icon = new IconWidget( [
				'icon' => $this->isCompleted() ? 'check' : $uncompletedIcon,
				// FIXME: 'invert' => true doesn't work
				'invert' => true
			] );
		}
		$span = Html::element( 'span', [], $this->getHeaderText() );
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
	protected function getState() {
		return array_merge(
			parent::getState(),
			[ 'completed' => ( $this->isCompleted() ? 'complete' : 'incomplete' ) ]
		);
	}
}
