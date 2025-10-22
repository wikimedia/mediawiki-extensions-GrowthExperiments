<?php

namespace GrowthExperiments\Specials;

use GrowthExperiments\DashboardModule\IDashboardModule;
use GrowthExperiments\EventLogging\SpecialMentorDashboardLogger;
use GrowthExperiments\MentorDashboard\MentorDashboardDiscoveryHooks;
use GrowthExperiments\MentorDashboard\MentorDashboardModuleRegistry;
use GrowthExperiments\Mentorship\Provider\MentorProvider;
use GrowthExperiments\Util;
use MediaWiki\Deferred\DeferredUpdates;
use MediaWiki\Exception\ErrorPageError;
use MediaWiki\Exception\PermissionsError;
use MediaWiki\Html\Html;
use MediaWiki\JobQueue\JobQueueGroupFactory;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\User\Options\UserOptionsLookup;
use MWCryptRand;
use UserOptionsUpdateJob;

class SpecialMentorDashboard extends SpecialPage {

	/**
	 * @var string Unique identifier for this specific rendering of Special:Homepage.
	 * Used by various EventLogging schemas to correlate events.
	 */
	private string $pageviewToken;
	private MentorDashboardModuleRegistry $mentorDashboardModuleRegistry;
	private MentorProvider $mentorProvider;
	private UserOptionsLookup $userOptionsLookup;
	private JobQueueGroupFactory $jobQueueGroupFactory;

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

		$this->pageviewToken = $this->generatePageviewToken();
		$this->mentorDashboardModuleRegistry = $mentorDashboardModuleRegistry;
		$this->mentorProvider = $mentorProvider;
		$this->userOptionsLookup = $userOptionsLookup;
		$this->jobQueueGroupFactory = $jobQueueGroupFactory;
	}

	/** @inheritDoc */
	protected function getGroupName() {
		return 'growth-tools';
	}

	/**
	 * @inheritDoc
	 */
	public function getDescription() {
		return $this->msg( 'growthexperiments-mentor-dashboard-title' );
	}

	/**
	 * Returns 32-character random string.
	 * The token is used for client-side logging and can be retrieved on Special:MentorDashboard via
	 * the wgGEMentorDashboardPageviewToken JS variable.
	 * @return string
	 */
	private function generatePageviewToken() {
		return \Wikimedia\base_convert( MWCryptRand::generateHex( 40 ), 16, 32, 32 );
	}

	/**
	 * @param bool $isMobile
	 * @return IDashboardModule[]
	 */
	private function getModules( bool $isMobile = false ): array {
		$enabledModules = $this->getConfig()->get( 'GEMentorDashboardEnabledModules' );
		$modules = [];
		foreach ( $enabledModules as $moduleId => $enabled ) {
			if ( !$enabled ) {
				continue;
			}

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
				'mentee-overview',
			],
			'sidebar' => [
				'personalized-praise',
				'mentor-tools',
				'resources',
			],
		];
	}

	/**
	 * Check whether the user is a mentor and redirect to
	 * Special:EnrollAsMentor if they're not AND structured mentor
	 * list is used.
	 */
	private function maybeRedirectToEnrollAsMentor(): void {
		if ( !$this->mentorProvider->isMentor( $this->getUser() ) ) {
			$this->getOutput()->redirect(
				SpecialPage::getTitleFor( 'EnrollAsMentor' )->getFullURL()
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
		$this->requireNamedUser();
		$this->maybeRedirectToEnrollAsMentor();
		$this->requireMentorList();

		parent::execute( $par );

		$out = $this->getContext()->getOutput();
		$out->addJsConfigVars( [
			'wgGEMentorDashboardPageviewToken' => $this->pageviewToken,
		] );

		$out->enableOOUI();
		$dashboardModules = [ 'ext.growthExperiments.MentorDashboard' ];

		$out->addModules( $dashboardModules );
		$out->addModuleStyles( 'ext.growthExperiments.MentorDashboard.styles' );

		$out->addHTML( Html::openElement( 'div', [
			'class' => 'growthexperiments-mentor-dashboard-container',
		] ) );

		$modules = $this->getModules( false );

		foreach ( $this->getModuleGroups() as $group => $moduleNames ) {
			$out->addHTML( Html::openElement(
				'div',
				[
					'class' => "growthexperiments-mentor-dashboard-group-$group",
				]
			) );

			foreach ( $moduleNames as $moduleName ) {
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
				$logger = new SpecialMentorDashboardLogger(
					$this->pageviewToken,
					$this->getUser(),
					$this->getRequest(),
					Util::isMobile( $this->getSkin() )
				);
				$logger->log();
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
					MentorDashboardDiscoveryHooks::MENTOR_DASHBOARD_SEEN_PREF => 1,
				],
			] ) );
		} );
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
