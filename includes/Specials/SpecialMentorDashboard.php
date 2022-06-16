<?php

namespace GrowthExperiments\Specials;

use DeferredUpdates;
use ErrorPageError;
use ExtensionRegistry;
use GrowthExperiments\DashboardModule\IDashboardModule;
use GrowthExperiments\MentorDashboard\MentorDashboardDiscoveryHooks;
use GrowthExperiments\MentorDashboard\MentorDashboardModuleRegistry;
use GrowthExperiments\MentorDashboard\Modules\MenteeOverviewVue;
use GrowthExperiments\Mentorship\Provider\MentorProvider;
use GrowthExperiments\Util;
use Html;
use MediaWiki\Extension\EventLogging\EventLogging;
use MediaWiki\JobQueue\JobQueueGroupFactory;
use MediaWiki\User\UserOptionsLookup;
use PermissionsError;
use SpecialPage;
use User;
use UserOptionsUpdateJob;

class SpecialMentorDashboard extends SpecialPage {

	/** @var string[][] Mapping of module stability level => accepted deployment modes */
	private const REQUIRED_DEPLOYMENT_MODE = [
		'stable' => [ 'stable', 'beta', 'alpha' ],
		'beta' => [ 'beta', 'alpha' ],
		'alpha' => [ 'alpha' ]
	];

	private const VUE_MODULES = [ 'mentee-overview' ];

	/** @var string Versioned schema URL for $schema field */
	private const SCHEMA_VERSIONED = '/analytics/mediawiki/mentor_dashboard/visit/1.0.0';

	/** @var string Stream name for EventLogging::submit */
	private const STREAM = 'mediawiki.mentor_dashboard.visit';

	/** @var MentorDashboardModuleRegistry */
	private $mentorDashboardModuleRegistry;

	/** @var MentorProvider */
	private $mentorProvider;

	/** @var UserOptionsLookup */
	private $userOptionsLookup;

	/** @var JobQueueGroupFactory */
	private $jobQueueGroupFactory;

	/**
	 * @param MentorDashboardModuleRegistry $mentorDashboardModuleRegistry
	 * @param MentorProvider $mentorProvider
	 * @param UserOptionsLookup $userOptionsLookup
	 * @param JobQueueGroupFactory $jobQueueGroupFactory
	 */
	public function __construct(
		MentorDashboardModuleRegistry $mentorDashboardModuleRegistry,
		MentorProvider $mentorProvider,
		UserOptionsLookup $userOptionsLookup,
		JobQueueGroupFactory $jobQueueGroupFactory
	) {
		parent::__construct( 'MentorDashboard' );

		$this->mentorDashboardModuleRegistry = $mentorDashboardModuleRegistry;
		$this->mentorProvider = $mentorProvider;
		$this->userOptionsLookup = $userOptionsLookup;
		$this->jobQueueGroupFactory = $jobQueueGroupFactory;
	}

	/**
	 * @inheritDoc
	 */
	public function getDescription() {
		return $this->msg( 'growthexperiments-mentor-dashboard-title' )->text();
	}

	/**
	 * @param bool $isMobile
	 * @return IDashboardModule[]
	 */
	private function getModules( bool $isMobile = false ): array {
		$deploymentMode = $this->getConfig()->get( 'GEMentorDashboardDeploymentMode' );
		$rawConfig = [
			'mentee-overview' => 'stable',
			'mentor-tools' => 'stable',
			'resources' => 'stable',
		];
		foreach ( self::VUE_MODULES as $moduleName ) {
			$rawConfig[$moduleName . '-vue'] = $rawConfig[$moduleName];
		}

		$moduleConfig = array_filter( $rawConfig, static function ( $el ) use ( $deploymentMode ) {
			return in_array( $deploymentMode, self::REQUIRED_DEPLOYMENT_MODE[$el] );
		} );
		$modules = [];
		foreach ( $moduleConfig as $moduleId => $_ ) {
			$modules[$moduleId] = $this->mentorDashboardModuleRegistry->get(
				$moduleId,
				$this->getContext()
			);
		}
		return $modules;
	}

	/**
	 * @return string[][]
	 */
	private function getModuleGroups(): array {
		return [
			'main' => [
				'mentee-overview'
			],
			'sidebar' => [
				'mentor-tools',
				'resources'
			]
		];
	}

	/**
	 * Ensure mentor dashboard is enabled
	 *
	 * @throws ErrorPageError
	 */
	private function requireMentorDashboardEnabled() {
		if ( !$this->isEnabled() ) {
			// Mentor dashboard is disabled, display a meaningful restriction error
			throw new ErrorPageError(
				'growthexperiments-mentor-dashboard-title',
				'growthexperiments-mentor-dashboard-disabled'
			);
		}
	}

	/**
	 * Ensure the automatic mentor list is configured
	 *
	 * @throws ErrorPageError if mentor list is missing
	 */
	private function requireMentorList() {
		if ( !$this->mentorProvider->getSignupTitle() ) {
			throw new ErrorPageError(
				'growthexperiments-mentor-dashboard-title',
				'growthexperiments-mentor-dashboard-misconfigured-missing-list'
			);
		}
	}

	/**
	 * @inheritDoc
	 */
	public function execute( $par ) {
		$this->requireLogin();
		$this->requireMentorDashboardEnabled();
		$this->requireMentorList();
		parent::execute( $par );

		$out = $this->getContext()->getOutput();
		$out->enableOOUI();
		$dashboardModules = [ 'ext.growthExperiments.MentorDashboard' ];
		if ( $this->shouldUseVueModule() ) {
			array_push( $dashboardModules, 'ext.growthExperiments.MentorDashboard.Vue' );
		}
		$out->addModules( $dashboardModules );
		$out->addModuleStyles( 'ext.growthExperiments.MentorDashboard.styles' );

		$out->addHTML( Html::openElement( 'div', [
			'class' => 'growthexperiments-mentor-dashboard-container'
		] ) );

		$modules = $this->getModules( false );

		foreach ( $this->getModuleGroups() as $group => $moduleNames ) {
			$out->addHTML( Html::openElement(
				'div',
				[
					'class' => "growthexperiments-mentor-dashboard-group-$group"
				]
			) );

			foreach ( $moduleNames as $moduleName ) {
				if ( $this->shouldUseVueModule() && in_array( $moduleName, self::VUE_MODULES ) ) {
					$moduleName .= '-vue';
				}
				$module = $modules[$moduleName] ?? null;
				if ( !$module ) {
					continue;
				}
				$out->addHTML( $module->render( IDashboardModule::RENDER_DESKTOP ) );
			}

			$out->addHTML( Html::closeElement( 'div' ) );
		}

		$out->addHTML( Html::closeElement( 'div' ) );

		$this->maybeLogVisit();
		$this->maybeSetSeenPreference();
	}

	/**
	 * Log visit to the mentor dashboard, if EventLogging is installed
	 */
	private function maybeLogVisit(): void {
		if ( ExtensionRegistry::getInstance()->isLoaded( 'EventLogging' ) ) {
			DeferredUpdates::addCallableUpdate( function () {
				EventLogging::submit(
					self::STREAM,
					[
						'$schema' => self::SCHEMA_VERSIONED,
						'user_id' => $this->getUser()->getId(),
						'is_mobile' => Util::isMobile( $this->getSkin() )
					]
				);
			} );
		}
	}

	/**
	 * If applicable, record that the user seen the dashboard
	 *
	 * This is used by MentorDashboardDiscoveryHooks to decide whether or not
	 * to add a blue dot informing the mentors about their dashboard.
	 *
	 * Happens via a DeferredUpdate, because it doesn't affect what the user
	 * sees in their dashboard (and is not time-sensitive as it depends on a job).
	 */
	private function maybeSetSeenPreference(): void {
		DeferredUpdates::addCallableUpdate( function () {
			$user = $this->getUser();
			if ( $this->userOptionsLookup->getBoolOption(
				$user,
				MentorDashboardDiscoveryHooks::MENTOR_DASHBOARD_SEEN_PREF
			) ) {
				// no need to set the option again
				return;
			}

			// we're in a GET context, set the seen pref via a job rather than directly
			$this->jobQueueGroupFactory->makeJobQueueGroup()->lazyPush( new UserOptionsUpdateJob( [
				'userId' => $user->getId(),
				'options' => [
					MentorDashboardDiscoveryHooks::MENTOR_DASHBOARD_SEEN_PREF => 1
				]
			] ) );
		} );
	}

	/**
	 * Check if mentor dashboard is enabled via GEMentorDashboardEnabled
	 *
	 * @return bool
	 */
	private function isEnabled(): bool {
		return $this->getConfig()->get( 'GEMentorDashboardEnabled' );
	}

	/**
	 * Check via wgGEMentorDashboardUseVue if mentor dashboard should use
	 * the Vue module ( ext.growthExperiments.MentorDashboard.Vue )
	 * or the "standard" ResourceLoader module ( ext.growthExperiments.MentorDashboard )
	 *
	 * @see MenteeOverviewVue
	 * @return bool
	 */
	private function shouldUseVueModule(): bool {
		return $this->getConfig()->get( 'GEMentorDashboardUseVue' );
	}

	/**
	 * @inheritDoc
	 */
	public function userCanExecute( User $user ) {
		// Require both enabled wiki config and user-specific access level to
		// be able to use the special page.
		return $this->mentorProvider->isMentor( $this->getUser() ) &&
			parent::userCanExecute( $user );
	}

	/**
	 * @inheritDoc
	 */
	public function displayRestrictionError() {
		$signupTitle = $this->mentorProvider->getSignupTitle();

		if ( $signupTitle === null ) {
			throw new PermissionsError(
				null,
				[ 'growthexperiments-homepage-mentors-list-missing-or-misconfigured-generic' ]
			);
		}

		throw new PermissionsError(
			null,
			[ [ 'growthexperiments-mentor-dashboard-must-be-mentor',
				$signupTitle->getPrefixedText() ] ]
		);
	}
}
