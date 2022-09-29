<?php

namespace GrowthExperiments;

use Config;
use MediaWiki\ResourceLoader\Hook\ResourceLoaderRegisterModulesHook;
use MediaWiki\ResourceLoader\ResourceLoader;

class ImpactHooks implements ResourceLoaderRegisterModulesHook {
	/** @var Config */
	private $config;

	/**
	 * @param Config $config
	 */
	public function __construct( Config $config ) {
		$this->config = $config;
	}

	/**
	 * Register ResourceLoader modules for the homepage that are feature flagged.
	 * @inheritDoc
	 */
	public function onResourceLoaderRegisterModules( ResourceLoader $resourceLoader ): void {
		$modules = [];
		$moduleTemplate = [
			'localBasePath' => dirname( __DIR__ ) . '/modules',
			'remoteExtPath' => 'GrowthExperiments/modules'
		];
		if ( $this->config->get( 'GENewImpactD3Enabled' ) ) {
			$modules[ 'ext.growthExperiments.d3' ] = $moduleTemplate + [
					"packageFiles" => [
						"lib/d3/d3.min.js"
					],
					"targets" => [
						"desktop",
						"mobile"
					]
				];
		}
		if ( !$modules ) {
			return;
		}
		$resourceLoader->register( $modules );
	}
}
