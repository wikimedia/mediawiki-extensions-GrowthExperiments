<?php

namespace GrowthExperiments;

use Config;
use DeferredUpdates;
use GrowthExperiments\UserImpact\UserImpactLookup;
use GrowthExperiments\UserImpact\UserImpactStore;
use MediaWiki\ResourceLoader\Hook\ResourceLoaderRegisterModulesHook;
use MediaWiki\ResourceLoader\ResourceLoader;
use MediaWiki\Storage\Hook\PageSaveCompleteHook;
use MediaWiki\User\UserOptionsLookup;

class ImpactHooks implements ResourceLoaderRegisterModulesHook, PageSaveCompleteHook {

	private Config $config;
	private UserImpactLookup $userImpactLookup;
	private UserImpactStore $userImpactStore;
	private UserOptionsLookup $userOptionsLookup;

	/**
	 * @param Config $config
	 * @param UserImpactLookup $userImpactLookup
	 * @param UserImpactStore $userImpactStore
	 * @param UserOptionsLookup $userOptionsLookup
	 */
	public function __construct(
		Config $config,
		UserImpactLookup $userImpactLookup,
		UserImpactStore $userImpactStore,
		UserOptionsLookup $userOptionsLookup
	) {
		$this->config = $config;
		$this->userImpactLookup = $userImpactLookup;
		$this->userImpactStore = $userImpactStore;
		$this->userOptionsLookup = $userOptionsLookup;
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

	/** @inheritDoc */
	public function onPageSaveComplete( $wikiPage, $user, $summary, $flags, $revisionRecord, $editResult ) {
		// Refresh the user's impact after they've made an edit.
		if ( $user->isRegistered() &&
			$user->equals( $revisionRecord->getUser() ) &&
			$this->userOptionsLookup->getBoolOption( $user, HomepageHooks::HOMEPAGE_PREF_ENABLE )
		) {
			DeferredUpdates::addCallableUpdate( function () use ( $user ) {
				$impact = $this->userImpactLookup->getExpensiveUserImpact( $user );
				if ( $impact ) {
					$this->userImpactStore->setUserImpact( $impact );
				}
			} );
		}
	}
}
