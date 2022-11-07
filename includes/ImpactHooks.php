<?php

namespace GrowthExperiments;

use Config;
use DateTime;
use DeferredUpdates;
use GrowthExperiments\UserImpact\UserImpactLookup;
use GrowthExperiments\UserImpact\UserImpactStore;
use MediaWiki\Hook\ManualLogEntryBeforePublishHook;
use MediaWiki\ResourceLoader\Hook\ResourceLoaderRegisterModulesHook;
use MediaWiki\ResourceLoader\ResourceLoader;
use MediaWiki\Storage\Hook\PageSaveCompleteHook;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserOptionsLookup;
use Wikimedia\Rdbms\LoadBalancer;

class ImpactHooks implements
	ResourceLoaderRegisterModulesHook,
	PageSaveCompleteHook,
	ManualLogEntryBeforePublishHook
{

	private Config $config;
	private UserImpactLookup $userImpactLookup;
	private UserImpactStore $userImpactStore;
	private UserOptionsLookup $userOptionsLookup;
	private UserFactory $userFactory;
	private LoadBalancer $loadBalancer;

	/**
	 * @param Config $config
	 * @param UserImpactLookup $userImpactLookup
	 * @param UserImpactStore $userImpactStore
	 * @param UserOptionsLookup $userOptionsLookup
	 * @param UserFactory $userFactory
	 * @param LoadBalancer $loadBalancer
	 */
	public function __construct(
		Config $config,
		UserImpactLookup $userImpactLookup,
		UserImpactStore $userImpactStore,
		UserOptionsLookup $userOptionsLookup,
		UserFactory $userFactory,
		LoadBalancer $loadBalancer
	) {
		$this->config = $config;
		$this->userImpactLookup = $userImpactLookup;
		$this->userImpactStore = $userImpactStore;
		$this->userOptionsLookup = $userOptionsLookup;
		$this->userFactory = $userFactory;
		$this->loadBalancer = $loadBalancer;
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
		if ( !$this->config->get( 'GEUseNewImpactModule' ) ) {
			return;
		}
		// Refresh the user's impact after they've made an edit.
		if ( $this->userIsInImpactDataCohort( $user ) &&
			$user->equals( $revisionRecord->getUser() )
		) {
			$this->refreshUserImpactDataInDeferredUpdate( $user );

		}
	}

	/** @inheritDoc */
	public function onManualLogEntryBeforePublish( $logEntry ): void {
		if ( !$this->config->get( 'GEUseNewImpactModule' ) ) {
			return;
		}
		if ( $logEntry->getType() === 'thanks' && $logEntry->getSubtype() === 'thank' ) {
			$recipientUserPage = $logEntry->getTarget();
			$user = $this->userFactory->newFromName( $recipientUserPage->getDBkey() );
			if ( $user instanceof UserIdentity && $this->userIsInImpactDataCohort( $user ) ) {
				$this->refreshUserImpactDataInDeferredUpdate( $user );
			}
		}
	}

	/**
	 * Account is considered to be in the Impact module data cohort if:
	 * - registered
	 * - created in the last year
	 * - has homepage preference enabled
	 * @param UserIdentity $userIdentity
	 * @return bool
	 */
	private function userIsInImpactDataCohort( UserIdentity $userIdentity ): bool {
		$dateTime = new DateTime( 'now - 1year' );
		$firstUserId = UserRegistrationLookupHelper::findFirstUserIdForRegistrationTimestamp(
			$this->loadBalancer->getConnection( DB_REPLICA ),
			$dateTime->getTimestamp()
		);
		return $userIdentity->isRegistered() &&
			$this->userOptionsLookup->getBoolOption( $userIdentity, HomepageHooks::HOMEPAGE_PREF_ENABLE ) &&
			$userIdentity->getId() >= $firstUserId;
	}

	/**
	 * @param UserIdentity $userIdentity
	 * @return void
	 */
	private function refreshUserImpactDataInDeferredUpdate( UserIdentity $userIdentity ): void {
		DeferredUpdates::addCallableUpdate( function () use ( $userIdentity ) {
			$impact = $this->userImpactLookup->getExpensiveUserImpact( $userIdentity );
			if ( $impact ) {
				$this->userImpactStore->setUserImpact( $impact );
			}
		} );
	}
}
