<?php

namespace GrowthExperiments\Specials;

use GrowthExperiments\HomepageModule;
use GrowthExperiments\HomepageModules\Help;
use GrowthExperiments\HomepageModules\Impact;
use SpecialPage;

class SpecialHomepage extends SpecialPage {

	public function __construct() {
		parent::__construct( 'Homepage', '', false );
	}

	/**
	 * @inheritDoc
	 */
	public function execute( $par ) {
		$this->requireLogin();
		parent::execute( $par );
		$this->getContext()->getOutput()->enableOOUI();
		foreach ( $this->getModules() as $module ) {
			$module->render( $this->getContext() );
		}
	}

	/**
	 * Overridden in order to inject the current user's name as message parameter
	 *
	 * @return string
	 */
	public function getDescription() {
		return $this->msg( 'growthexperiments-homepage-specialpage-title' )
			->params( $this->getUser()->getName() )
			->text();
	}

	/**
	 * @return HomepageModule[]
	 */
	private function getModules() {
		return [
			new Impact(),
			new Help(),
		];
	}
}
