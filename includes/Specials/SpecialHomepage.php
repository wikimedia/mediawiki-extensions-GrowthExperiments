<?php

namespace GrowthExperiments\Specials;

use Exception;
use GrowthExperiments\HomepageModule;
use GrowthExperiments\HomepageModules\Help;
use GrowthExperiments\HomepageModules\Impact;
use GrowthExperiments\HomepageModules\Mentorship;
use MediaWiki\Logger\LoggerFactory;
use GrowthExperiments\HomepageModules\Start;
use MediaWiki\Session\SessionManager;
use SpecialPage;

class SpecialHomepage extends SpecialPage {

	/**
	 * @var string Unique identifier for this specific rendering of Special:Homepage.
	 * Used by various EventLogging schemas to correlate events.
	 */
	private $pageviewToken;

	public function __construct() {
		parent::__construct( 'Homepage', '', false );
		$this->pageviewToken = $this->generateUniqueToken();
	}

	/**
	 * @inheritDoc
	 */
	public function execute( $par ) {
		$out = $this->getContext()->getOutput();
		$this->requireLogin();
		parent::execute( $par );
		$out->setSubtitle( $this->getSubtitle() );
		$out->addJsConfigVars( [
			'wgGEHomepagePageviewToken' => $this->pageviewToken,
			'wgGEHomepageLoggingEnabled' => $this->getConfig()->get( 'GEHomepageLoggingEnabled' ),
		] );
		$out->addModules( 'ext.growthExperiments.Homepage' );
		$out->enableOOUI();
		foreach ( $this->getModules() as $module ) {
			try {
				$out->addHTML( $module->render() );
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
			new Start( $this->getContext() ),
			new Impact( $this->getContext() ),
			new Help( $this->getContext() ),
			new Mentorship( $this->getContext() ),
		];
	}

	private function getSubtitle() {
		return $this->msg( 'growthexperiments-homepage-specialpage-subtitle' )
				->params( $this->getUser()->getName() );
	}

	/**
	 * @return string
	 */
	private function generateUniqueToken() {
		// Can't use SessionManager::singleton() here because while it returns an
		// instance of SessionManager, the code comment says it returns SessionManagerInterface
		// and that doesn't have generateSessionId(). So the code works but phan rejects it.
		$sessionManager = new SessionManager();
		return $sessionManager->generateSessionId();
	}
}
