<?php

declare( strict_types = 1 );

namespace GrowthExperiments\PersonalDashboard;

use GrowthExperiments\FeatureManager;
use GrowthExperiments\HomepageModules\SuggestedEdits as HomepageSuggestedEdits;
use GrowthExperiments\NewcomerTasks\NewcomerTasksUserOptionsLookup;
use GrowthExperiments\NewcomerTasks\Task\TaskSetFiltersFactory;
use MediaWiki\Context\IContextSource;
use MediaWiki\Extension\PersonalDashboard\Modules\BaseModule;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\User\Options\UserOptionsLookup;

/**
 * The Newcomer Homepage Suggested Edits module reimagined for the Personal
 * Dashboard: a three-card preview of recommended articles on the dashboard, the
 * full ranked list behind "see all suggestions".
 *
 * An island, unlike the Mentorship and Impact cards beside it. The body is a
 * relevance-ranked list the client fetches from list=growthtasks, so there is no
 * server render to progressively enhance; the card header links to the focused
 * view, which is what a no-JS visitor gets instead.
 *
 * The mobile swipe pane stays with the Homepage module for now. Cards link
 * through Special:Homepage/newcomertask, so structured tasks do start from
 * here, without the quality gate the Homepage carousel runs before navigating.
 */
class SuggestedEdits extends BaseModule {

	public function __construct(
		IContextSource $context,
		private readonly FeatureManager $featureManager,
		private readonly NewcomerTasksUserOptionsLookup $newcomerTasksUserOptionsLookup,
		private readonly TaskSetFiltersFactory $taskSetFiltersFactory,
		private readonly UserOptionsLookup $userOptionsLookup
	) {
		parent::__construct( $context, shouldWrapModuleWithLink: true );
	}

	/**
	 * No card at all for a viewer we have nothing to suggest to: suggested edits
	 * off for the wiki, or every task type filtered out for this viewer by
	 * community configuration. Either way the body could only ever come back
	 * empty, and an empty card reads as a broken one.
	 *
	 * @todo once the header menu opens the task type selector, render the card
	 * for the empty case too and open the selector from it, rather than hiding
	 * the one route to the setting that would bring the card back.
	 * @inheritDoc
	 */
	protected function canRender(): bool {
		return $this->featureManager->isNewcomerTasksAvailable()
			&& $this->newcomerTasksUserOptionsLookup->getTaskTypeFilter( $this->getUser() ) !== [];
	}

	/** @inheritDoc */
	protected function getHeaderText(): string {
		return $this->msg( 'growthexperiments-homepage-suggested-edits-header' )->text();
	}

	/** @inheritDoc */
	protected function getModules(): array {
		return [ 'ext.growthExperiments.PersonalDashboard.SuggestedEdits' ];
	}

	/**
	 * Design puts the filter controls and an explanation of the module behind a
	 * menu in the header. Opting in also drops the header's forward arrow, which
	 * the menu button replaces; "see all suggestions" in the footer is still the
	 * way through to the full list.
	 * @inheritDoc
	 */
	protected function hasHeaderMenu(): bool {
		return true;
	}

	/**
	 * The client picks its task filters from these, and has to land on the same ones
	 * the server built the cached suggestions from.
	 * @inheritDoc
	 */
	public function getJsConfigVars(): array {
		return [
			/*
			 * Whether the card may ask the action API for article descriptions.
			 * prop=description is Wikibase Client's, and an unregistered prop fails the
			 * whole task query rather than dropping one field, so a wiki without it
			 * would get an error card instead of cards with no description line.
			 */
			'GESuggestedEditsDescriptionsAvailable' =>
				ExtensionRegistry::getInstance()->isLoaded( 'WikibaseClient' ),
			// What FiltersStore chooses between topics and interests on. Ask the factory
			// rather than the experiment, so the front end cannot disagree with it.
			'GEHomepageSuggestedEditsEnableTopics' => HomepageSuggestedEdits::isTopicMatchingEnabled(
				$this->getContext(),
				$this->userOptionsLookup
			),
			'GEHomepageSuggestedEditsEnableInterests' => $this->taskSetFiltersFactory
				->usesInterestFilters( $this->getUser() ),
		];
	}
}
