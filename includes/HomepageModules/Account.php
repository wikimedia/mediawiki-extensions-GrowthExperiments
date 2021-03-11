<?php

namespace GrowthExperiments\HomepageModules;

use Config;
use GrowthExperiments\ExperimentUserManager;
use GrowthExperiments\Util;
use Html;
use IContextSource;
use OOUI\IconWidget;

class Account extends BaseTaskModule {

	/**
	 * @inheritDoc
	 */
	public function __construct(
		IContextSource $context,
		Config $wikiConfig,
		ExperimentUserManager $experimentUserManager
	) {
		parent::__construct( 'start-account', $context, $wikiConfig, $experimentUserManager );
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
	protected function getHeaderIconName() {
		return 'userAvatar';
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
		return $this->getUserInfo() .
			$this->getAccountAge();
	}

	/**
	 * @inheritDoc
	 */
	protected function getModuleStyles() {
		return array_merge(
			parent::getModuleStyles(),
			[ 'oojs-ui.styles.icons-user' ]
		);
	}

	private function getUserInfo() {
		$icon = new IconWidget( [
			'icon' => 'userAvatar',
		] );
		$name = htmlspecialchars( $this->getContext()->getLanguage()->embedBidi(
			$this->getContext()->getUser()->getName()
		) );
		$nameSection = $this->buildSection( 'username', $name, 'span' );
		$editsSection = $this->buildSection(
			'editcount',
			$this->getContext()->msg( 'growthexperiments-homepage-account-editcount' )
				->numParams( $this->getContext()->getUser()->getEditCount() )
				->escaped(),
			'span'
		);
		$nameAndEdits = Html::rawElement( 'div', [], $nameSection . $editsSection );
		return $this->buildSection(
			'userinfo',
			$icon . $nameAndEdits
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
