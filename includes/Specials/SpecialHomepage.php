<?php

namespace GrowthExperiments\Specials;

use GrowthExperiments\HomepageModule;
use GrowthExperiments\HomepageModules\Help;
use GrowthExperiments\HomepageModules\Impact;
use Html;
use SpecialPage;

class SpecialHomepage extends SpecialPage {

	public function __construct() {
		parent::__construct( 'Homepage', '', false );
	}

	/**
	 * @inheritDoc
	 */
	public function execute( $par ) {
		$out = $this->getContext()->getOutput();
		$this->requireLogin();
		parent::execute( $par );
		$out->addModuleStyles( 'ext.growthExperiments.Homepage.styles' );
		$out->addHTML( $this->getSubtitle() );
		$out->enableOOUI();
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

	private function getSubtitle() {
		return Html::element(
			'div',
			[ 'class' => 'growthexperiments-homepage-subtitle' ],
			$this->msg( 'growthexperiments-homepage-specialpage-subtitle' )
				->params( $this->getUser()->getName() )
				->text()
		);
	}
}
