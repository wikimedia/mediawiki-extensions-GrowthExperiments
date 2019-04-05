<?php

namespace GrowthExperiments\HomepageModules;

use IContextSource;
use Html;
use OOUI\IconWidget;

class Account extends BaseTaskModule {

	const MINUTE = 60;
	const HOUR = 3600;
	const DAY = 86400;
	const WEEK = 604800;
	const MONTH = 2592000;
	const YEAR = 31536000;

	/**
	 * @inheritDoc
	 */
	public function __construct( IContextSource $context ) {
		parent::__construct( 'start-account', $context );
	}

	/**
	 * @inheritDoc
	 */
	public function isCompleted() {
		return true;
	}

	/**
	 * @inheritDoc
	 */
	protected function getUncompletedIcon() {
		return 'check';
	}

	/**
	 * @inheritDoc
	 */
	protected function getHeaderText() {
		return $this->getContext()->msg( 'growthexperiments-homepage-account-header' )->text();
	}

	/**
	 * @inheritDoc
	 */
	protected function getBody() {
		return $this->getUsername() .
			$this->getEditCount() .
			$this->getAccountAge();
	}

	/**
	 * @inheritDoc
	 */
	protected function getModuleStyles() {
		return 'oojs-ui.styles.icons-user';
	}

	private function getUsername() {
		$icon = new IconWidget( [
			'icon' => 'userAvatar',
			// HACK: IconWidget doesn't let us set 'invert' => true, see BaseTaskModule.php for details
			'classes' => [ 'oo-ui-image-invert', 'oo-ui-checkboxInputWidget-checkIcon' ]
		] );
		$name = Html::element( 'span', [], $this->getContext()->getUser()->getName() );
		return $this->buildSection(
			'username',
			$icon . $name
		);
	}

	private function getEditCount() {
		return $this->buildSection(
			'editcount',
			$this->getContext()->msg( 'growthexperiments-homepage-account-editcount' )
				->params( $this->getContext()->getUser()->getEditCount() )
				->escaped()
		);
	}

	private function getAccountAge() {
		$user = $this->getContext()->getUser();
		$elapsedTime = (int)wfTimestamp() -
			(int)wfTimestamp( TS_UNIX, $user->getRegistration() );
		$relativeTime = $this->getContext()->getLanguage()->formatDuration(
			$elapsedTime, $this->getIntervals( $elapsedTime )
		);
		return $this->buildSection(
			'accountage',
			$this->getContext()->msg( 'growthexperiments-homepage-account-age' )
				->params( $relativeTime )
				->escaped()
		);
	}

	/**
	 * Return the intervals passed as second arg to Language->formatDuration().
	 * @param int $time
	 *  Elapsed time since account creation in seconds.
	 * @return array
	 */
	private function getIntervals( $time ) {
		if ( $time < self::MINUTE ) {
			return [ 'seconds' ];
		} elseif ( $time < self::HOUR ) {
			return [ 'minutes' ];
		} elseif ( $time < self::DAY ) {
			return [ 'hours' ];
		} elseif ( $time < self::WEEK ) {
			return [ 'days' ];
		} elseif ( $time < self::MONTH ) {
			return [ 'weeks' ];
		} elseif ( $time < self::YEAR ) {
			return [ 'weeks' ];
		} else {
			return [ 'years', 'weeks' ];
		}
	}
}
