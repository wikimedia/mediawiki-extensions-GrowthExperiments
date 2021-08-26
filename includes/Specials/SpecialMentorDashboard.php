<?php

namespace GrowthExperiments\Specials;

use DeferredUpdates;
use EventLogging;
use ExtensionRegistry;
use GrowthExperiments\DashboardModule\IDashboardModule;
use GrowthExperiments\MentorDashboard\MentorDashboardModuleRegistry;
use GrowthExperiments\Mentorship\MentorManager;
use GrowthExperiments\Util;
use Html;
use PermissionsError;
use SpecialPage;
use User;

class SpecialMentorDashboard extends SpecialPage {

	/** @var string Versioned schema URL for $schema field */
	private const SCHEMA_VERSIONED = '/analytics/mediawiki/mentor_dashboard/visit/1.0.0';

	/** @var string Stream name for EventLogging::submit */
	private const STREAM = 'mediawiki.mentor_dashboard.visit';

	/** @var MentorDashboardModuleRegistry */
	private $mentorDashboardModuleRegistry;

	/** @var MentorManager */
	private $mentorManager;

	/**
	 * @param MentorDashboardModuleRegistry $mentorDashboardModuleRegistry
	 * @param MentorManager $mentorManager
	 */
	public function __construct(
		MentorDashboardModuleRegistry $mentorDashboardModuleRegistry,
		MentorManager $mentorManager
	) {
		parent::__construct( 'MentorDashboard' );

		$this->mentorDashboardModuleRegistry = $mentorDashboardModuleRegistry;
		$this->mentorManager = $mentorManager;
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
		$moduleConfig = array_filter( [
			'mentee-overview' => true,
			'resources' => true,
		] );
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
				'resources'
			]
		];
	}

	/**
	 * @inheritDoc
	 */
	public function execute( $par ) {
		$this->requireLogin();
		parent::execute( $par );

		$out = $this->getContext()->getOutput();
		$out->enableOOUI();
		$out->addModules( 'ext.growthExperiments.MentorDashboard' );
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
	 * Check if mentor dashboard is enabled via GEMentorDashboardEnabled
	 *
	 * @return bool
	 */
	private function isEnabled(): bool {
		return $this->getConfig()->get( 'GEMentorDashboardEnabled' );
	}

	/**
	 * @inheritDoc
	 */
	public function userCanExecute( User $user ) {
		// Require both enabled wiki config and user-specific access level to
		// be able to use the special page.
		return $this->isEnabled() &&
			$this->mentorManager->isMentor( $this->getUser() ) &&
			parent::userCanExecute( $user );
	}

	/**
	 * @inheritDoc
	 */
	public function displayRestrictionError() {
		if ( !$this->isEnabled() ) {
			// Mentor dashboard is disabled, display a meaningful restriction error
			throw new PermissionsError(
				null,
				[ 'growthexperiments-mentor-dashboard-disabled' ]
			);
		}

		if ( !$this->mentorManager->isMentor( $this->getUser() ) ) {
			throw new PermissionsError(
				null,
				[ [ 'growthexperiments-mentor-dashboard-must-be-mentor',
					$this->mentorManager->getAutoMentorsListTitle()->getPrefixedText() ] ]
			);
		}

		// Otherwise, defer to the default logic
		parent::displayRestrictionError();
	}
}
