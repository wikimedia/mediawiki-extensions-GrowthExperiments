<?php

declare( strict_types = 1 );

namespace GrowthExperiments\Specials;

use GrowthExperiments\FeatureManager;
use MediaWiki\Exception\ErrorPageError;
use MediaWiki\Extension\PersonalDashboard\Specials\AbstractSpecialDashboard;
use MediaWiki\Extension\PersonalDashboard\Specials\DashboardPageDependencies;
use MediaWiki\Message\Message;

/**
 * Home, at Special:Home.
 *
 * The page renders the `home` module group, which GrowthExperiments registers
 * under the `PersonalDashboard.ModuleGroups` attribute. PersonalDashboard owns
 * the render, so this class holds configuration only.
 *
 * @see https://phabricator.wikimedia.org/T438582
 */
class SpecialHome extends AbstractSpecialDashboard {

	public function __construct(
		DashboardPageDependencies $dependencies,
		private readonly FeatureManager $featureManager,
	) {
		parent::__construct( $dependencies );
	}

	/**
	 * The pilot cohort comes from the users that have the newcomer homepage
	 * enabled. Home uses the same preference, so a user that turned the
	 * homepage off does not get Home.
	 *
	 * @inheritDoc
	 */
	protected function beforeExecute( $subPage ) {
		// Run this first. The preference defaults to false, so an anonymous user
		// must get the login form, and not the preference error page.
		// AbstractSpecialDashboard::execute() calls this again. That is harmless,
		// because requireNamedUser() only throws. Do not remove this call.
		// execute() runs after beforeExecute(), so the base class call is too late.
		$this->requireNamedUser();

		// Gate Home to users who already have the Homepage, this is only for the pilot
		// wikis release of this feature, needs revisit after.
		if ( !$this->featureManager->isHomeEnabledForUser( $this->getUser() ) ) {
			throw new ErrorPageError(
				'growthexperiments-home-tab',
				'growthexperiments-home-enable-preference'
			);
		}
		// TODO handle /newcomertask route. Currently PersonalDashboard.SuggestedEdits/TaskCard.vue and PostEditPanel.js
		// hardcode the page URL Special:Homepage/newcomertask so the post-edit panel tasks point back to Homepage
		// instead of Home
	}

	/** @inheritDoc */
	protected function getPageName(): string {
		return 'Home';
	}

	/** @inheritDoc */
	protected function getBaselineModuleGroup(): string {
		return 'home';
	}

	/**
	 * Report the render timings under this extension's own component, to tell
	 * them apart from the PersonalDashboard timings in Grafana.
	 *
	 * @inheritDoc
	 */
	protected function getStatsComponent(): string {
		return 'GrowthExperiments';
	}

	/** @inheritDoc */
	protected function getGroupName(): string {
		return 'growth-tools';
	}

	/**
	 * The h1 greets the user by name.
	 *
	 * SpecialPage::setHeaders() sets the h1 from getDescription(), but
	 * Special:SpecialPages uses getDescription() for the link label. A label
	 * must not hold a user name, so this method sets the h1 on its own.
	 *
	 * AbstractSpecialDashboard::execute() is final, but it calls setHeaders()
	 * through SpecialPage::execute(), so this override runs.
	 *
	 * @inheritDoc
	 */
	protected function setHeaders() {
		parent::setHeaders();
		$this->getOutput()->setPageTitleMsg(
			$this->msg( 'growthexperiments-home-specialpage-title' )
				->params( $this->getUser()->getName() )
		);
	}

	/**
	 * The label of the page in Special:SpecialPages. Keep it static.
	 *
	 * @see self::setHeaders(), for the h1
	 * @inheritDoc
	 */
	public function getDescription(): Message {
		return $this->msg( 'growthexperiments-home-tab' );
	}
}
