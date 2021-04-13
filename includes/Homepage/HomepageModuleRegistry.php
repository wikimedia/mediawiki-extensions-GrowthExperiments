<?php

namespace GrowthExperiments\Homepage;

use ExtensionRegistry;
use GrowthExperiments\GrowthExperimentsServices;
use GrowthExperiments\HomepageModule;
use GrowthExperiments\HomepageModules\Banner;
use GrowthExperiments\HomepageModules\Help;
use GrowthExperiments\HomepageModules\Impact;
use GrowthExperiments\HomepageModules\Mentorship;
use GrowthExperiments\HomepageModules\Start;
use GrowthExperiments\HomepageModules\StartEditing;
use GrowthExperiments\HomepageModules\StartEmail;
use GrowthExperiments\HomepageModules\SuggestedEdits;
use IContextSource;
use MediaWiki\MediaWikiServices;
use OutOfBoundsException;

/**
 * Container class for handling dependency injection of homepage modules.
 */
class HomepageModuleRegistry {

	/** @var MediaWikiServices */
	private $services;

	/** @var callable[] id => factory method */
	private $wiring;

	/** @var HomepageModule[] id => module */
	private $modules = [];

	/**
	 * @param MediaWikiServices $services
	 */
	public function __construct( MediaWikiServices $services ) {
		$this->services = $services;
	}

	/**
	 * @param string $id
	 * @param IContextSource $contextSource
	 * @return HomepageModule
	 */
	public function get( string $id, IContextSource $contextSource ): HomepageModule {
		if ( $this->modules[$id] ?? null ) {
			return $this->modules[$id];
		}
		if ( $this->wiring === null ) {
			$this->wiring = self::getWiring();
		}
		if ( !array_key_exists( $id, $this->wiring ) ) {
			throw new OutOfBoundsException( 'Module not found: ' . $id );
		}
		$this->modules[$id] = $this->wiring[$id]( $this->services, $contextSource );
		return $this->modules[$id];
	}

	/**
	 * @internal for testing only
	 * @return string[]
	 */
	public static function getModuleIds(): array {
		return array_keys( self::getWiring() );
	}

	/**
	 * Returns wiring callbacks for each module.
	 * The callback receives the service container and the request context,
	 * and must return a homepage module.
	 * @return callable[] module id => callback
	 */
	private static function getWiring() {
		return [
			'banner' => function (
				MediaWikiServices $services,
				IContextSource $context
			) {
				$growthServices = GrowthExperimentsServices::wrap( $services );
				return new Banner( $context, $growthServices->getExperimentUserManager() );
			},

			'start' => function (
				MediaWikiServices $services,
				IContextSource $context
			) {
				$growthServices = GrowthExperimentsServices::wrap( $services );
				return new Start( $context, $growthServices->getExperimentUserManager() );
			},

			'startemail' => function (
				MediaWikiServices $services,
				IContextSource $context
			) {
				$growthServices = GrowthExperimentsServices::wrap( $services );
				return new StartEmail( $context, $growthServices->getExperimentUserManager() );
			},

			'suggested-edits' => function (
				MediaWikiServices $services,
				IContextSource $context
			) {
				$growthServices = GrowthExperimentsServices::wrap( $services );
				$pageViewInfoEnabled = ExtensionRegistry::getInstance()->isLoaded( 'PageViewInfo' );
				return new SuggestedEdits(
					$context,
					$growthServices->getEditInfoService(),
					$growthServices->getExperimentUserManager(),
					$pageViewInfoEnabled ? $services->get( 'PageViewService' ) : null,
					$growthServices->getNewcomerTasksConfigurationLoader(),
					$growthServices->getNewcomerTasksUserOptionsLookup(),
					$growthServices->getTaskSuggesterFactory()->create(),
					$services->getTitleFactory(),
					$growthServices->getProtectionFilter()
				);
			},

			'impact' => function (
				MediaWikiServices $services,
				IContextSource $context
			) {
				$growthServices = GrowthExperimentsServices::wrap( $services );
				$pageViewInfoEnabled = ExtensionRegistry::getInstance()->isLoaded( 'PageViewInfo' );
				return new Impact(
					$context,
					$growthServices->getConfig()->get( 'GEHomepageImpactModuleEnabled' ),
					$services->getDBLoadBalancer()->getLazyConnectionRef( DB_REPLICA ),
					$growthServices->getExperimentUserManager(),
					[
						'isSuggestedEditsEnabled' => SuggestedEdits::isEnabled( $context ),
						'isSuggestedEditsActivated' => SuggestedEdits::isActivated( $context ),
					],
					$services->getTitleFactory(),
					$pageViewInfoEnabled ? $services->get( 'PageViewService' ) : null
				);
			},

			'mentorship' => function (
				MediaWikiServices $services,
				IContextSource $context
			) {
				$growthServices = GrowthExperimentsServices::wrap( $services );
				return new Mentorship(
					$context,
					$growthServices->getExperimentUserManager(),
					$growthServices->getMentorManager()
				);
			},

			'help' => function (
				MediaWikiServices $services,
				IContextSource $context
			) {
				$growthServices = GrowthExperimentsServices::wrap( $services );
				return new Help( $context, $growthServices->getExperimentUserManager() );
			},

			'start-startediting' => function (
				MediaWikiServices $services,
				IContextSource $context
			) {
				$growthServices = GrowthExperimentsServices::wrap( $services );
				return new StartEditing( $context, $growthServices->getExperimentUserManager() );
			}

		];
	}

}
