<?php

namespace GrowthExperiments\Specials;

use GrowthExperiments\DashboardModule\IDashboardModule;
use GrowthExperiments\MentorDashboard\MentorDashboardModuleRegistry;
use Html;
use PermissionsError;
use SpecialPage;
use User;

class SpecialMentorDashboard extends SpecialPage {

	/** @var MentorDashboardModuleRegistry */
	private $mentorDashboardModuleRegistry;

	/**
	 * @param MentorDashboardModuleRegistry $mentorDashboardModuleRegistry
	 */
	public function __construct(
		MentorDashboardModuleRegistry $mentorDashboardModuleRegistry
	) {
		parent::__construct( 'MentorDashboard' );

		$this->mentorDashboardModuleRegistry = $mentorDashboardModuleRegistry;
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
		return $this->isEnabled() && parent::userCanExecute( $user );
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

		// Otherwise, defer to the default logic
		parent::displayRestrictionError();
	}
}
