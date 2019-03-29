<?php

namespace GrowthExperiments\HomepageModules;

use IContextSource;
use OOUI\IconWidget;

class Account extends BaseTaskModule {

	/**
	 * @inheritDoc
	 */
	public function __construct( IContextSource $context ) {
		parent::__construct( 'start-account', $context );
	}

	/**
	 * @inheritDoc
	 */
	protected function getHeader() {
		// todo: invert => true doesn't work here
		return new IconWidget( [ 'icon' => 'check', 'invert' => true ] ) .
			$this->getContext()->msg( 'growthexperiments-homepage-account-header' )->text();
	}

	/**
	 * @inheritDoc
	 */
	protected function getBody() {
		return $this->getUsername() .
			$this->getEditCount() .
			$this->getAccountAge();
	}

	private function getUsername() {
		return $this->buildSection( 'username', $this->getContext()->getUser()->getName() );
	}

	private function getEditCount() {
		return $this->buildSection(
			'editcount',
			$this->getContext()->msg( 'growthexperiments-homepage-account-editcount' )
				->params( $this->getContext()->getUser()->getEditCount() )
				->text()
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
				->text()
		);
	}

	private function getIntervals( $time ) {
		if ( $time < 60 ) {
			// less than a minute: "30 seconds"
			return [ 'seconds' ];
		} elseif ( $time < 3600 ) {
			// more than a minute, less than an hour: "15 minutes"
			return [ 'minutes' ];
		} elseif ( $time < 86400 ) {
			// more than an hour, less than a day: "23 hours"
			return [ 'hours' ];
		} elseif ( $time < 604800 ) {
			// more than a day, less than a week: "5 days"
			return [ 'days' ];
		} elseif ( $time < 2592000 ) {
			// more than a week, less than a month: "3 weeks"
			return [ 'weeks' ];
		} elseif ( $time < 2592000 ) {
			// more than a month, less than a year: "15 weeks"
			return [ 'weeks' ];
		} else {
			// more than a year: "3 years and 12 weeks"
			return [ 'years', 'weeks' ];
		}
	}

	/**
	 * @inheritDoc
	 */
	public function isCompleted() {
		return true;
	}
}
