<?php

namespace GrowthExperiments\MentorDashboard;

use GrowthExperiments\DashboardModule\IDashboardModule;
use GrowthExperiments\GrowthExperimentsServices;
use GrowthExperiments\MentorDashboard\Modules\MenteeOverview;
use GrowthExperiments\MentorDashboard\Modules\MentorTools;
use GrowthExperiments\MentorDashboard\Modules\Resources;
use IContextSource;
use MediaWiki\MediaWikiServices;

class MentorDashboardModuleRegistry {
	/** @var MediaWikiServices */
	private $services;

	/** @var callable[] id => module factory function */
	private $wiring;

	/** @var IDashboardModule[] */
	private $modules;

	/**
	 * @param MediaWikiServices $services
	 */
	public function __construct(
		MediaWikiServices $services
	) {
		$this->services = $services;
	}

	/**
	 * @param string $moduleId
	 * @param IContextSource $context
	 * @return IDashboardModule
	 */
	public function get( string $moduleId, IContextSource $context ): IDashboardModule {
		if ( isset( $this->modules[$moduleId] ) ) {
			return $this->modules[$moduleId];
		}

		if ( $this->wiring === null ) {
			$this->wiring = self::getWiring();
		}

		$this->modules[$moduleId] = $this->wiring[$moduleId]( $this->services, $context );
		return $this->modules[$moduleId];
	}

	/**
	 * @return array
	 */
	public static function getModules(): array {
		return array_keys( self::getWiring() );
	}

	/**
	 * @return callable[]
	 */
	private static function getWiring() {
		return [
			'mentee-overview' => static function (
				MediaWikiServices $services,
				IContextSource $context
			): IDashboardModule {
				return new MenteeOverview(
					'mentee-overview',
					$context
				);
			},
			'resources' => static function (
				MediaWikiServices $services,
				IContextSource $context
			): IDashboardModule {
				$geServices = GrowthExperimentsServices::wrap( $services );
				return new Resources(
					'resources',
					$context,
					$services->getTitleParser(),
					$services->getLinkRenderer(),
					$geServices->getMentorProvider()
				);
			},
			'mentor-tools' => static function (
				MediaWikiServices $services,
				IContextSource $context
			): IDashboardModule {
				$geServices = GrowthExperimentsServices::wrap( $services );
				return new MentorTools(
					'mentor-tools',
					$context,
					$geServices->getMentorProvider(),
					$geServices->getMentorStatusManager()
				);
			}
		];
	}
}
