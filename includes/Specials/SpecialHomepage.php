<?php

namespace GrowthExperiments\Specials;

use Exception;
use GrowthExperiments\HomepageModule;
use GrowthExperiments\HomepageModules\Help;
use GrowthExperiments\HomepageModules\Impact;
use GrowthExperiments\HomepageModules\Mentorship;
use MediaWiki\Logger\LoggerFactory;
use GrowthExperiments\HomepageModules\Start;
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
		$out->setSubtitle( $this->getSubtitle() );
		$out->enableOOUI();
		foreach ( $this->getModules() as $module ) {
			try {
				$out->addHTML( $module->render( $this->getContext() ) );
			} catch ( Exception $e ) {
				LoggerFactory::getInstance( 'GrowthExperiments' )->error(
					"Homepage module '{class}' cannot be rendered.",
					[
						'class' => get_class( $module ),
						'msg' => $e->getMessage(),
						'trace' => $e->getTraceAsString(),
					]
				);
			}
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
			new Start(),
			new Impact(),
			new Help(),
			new Mentorship(),
		];
	}

	private function getSubtitle() {
		return $this->msg( 'growthexperiments-homepage-specialpage-subtitle' )
				->params( $this->getUser()->getName() );
	}
}
