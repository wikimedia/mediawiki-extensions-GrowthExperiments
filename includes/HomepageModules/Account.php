<?php

namespace GrowthExperiments\HomepageModules;

use GrowthExperiments\Util;
use IContextSource;
use Html;
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
		$name = Html::element( 'span', [], $this->getContext()->getLanguage()->embedBidi(
			$this->getContext()->getUser()->getName()
		) );
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
		$relativeTime = Util::getRelativeTime( $this->getContext(), $elapsedTime );
		return $this->buildSection(
			'accountage',
			$this->getContext()->msg( 'growthexperiments-homepage-account-age' )
				->params( $relativeTime )
				->escaped()
		);
	}

}
