<?php

declare( strict_types = 1 );

namespace GrowthExperiments\PersonalDashboard;

use GrowthExperiments\FeatureManager;
use GrowthExperiments\HomepageModules\SuggestedEdits as HomepageSuggestedEdits;
use GrowthExperiments\NewcomerTasks\NewcomerTasksUserOptionsLookup;
use MediaWiki\Context\IContextSource;
use MediaWiki\Extension\PersonalDashboard\Modules\BaseModule;
use MediaWiki\Extension\PersonalDashboard\Modules\FeedLoadingMarkup;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\User\Options\UserOptionsLookup;

/**
 * The Newcomer Homepage Suggested Edits module for the Personal Dashboard: a
 * short preview of recommended articles that grows in place on a wide viewport,
 * and the full ranked list in the focused view of the module.
 *
 * An island, unlike the Mentorship and Impact cards beside it. The body is a
 * relevance-ranked list the client fetches from list=growthtasks, so there is no
 * server render to progressively enhance.
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
		private readonly UserOptionsLookup $userOptionsLookup
	) {
		// The design has no link on the module header. On a narrow viewport, the
		// show more button opens the focused view.
		parent::__construct( $context );
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
	 * The loading cards of the island, so the card has its loaded height from the
	 * first paint.
	 * @inheritDoc
	 */
	protected function getLoadingMarkup(): string {
		return FeedLoadingMarkup::getHtml( $this, 'large', $this->isFocused() );
	}

	/**
	 * The design puts the filter controls and the module explanation in a header
	 * menu. On a narrow viewport, the show more button leads to the full list.
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
			// What FiltersStore chooses between topics and interests on.
			'GEHomepageSuggestedEditsEnableTopics' => HomepageSuggestedEdits::isTopicMatchingEnabled(
				$this->getContext(),
				$this->userOptionsLookup
			),
			// Home filters by interests for every user it serves, and not only for the users
			// that the experiment gives interest filters to. FiltersStore reads this flag.
			// GrowthTasksApi then sends the useinterests parameter, which makes the API
			// apply the stored interests for these users too.
			'GEHomepageSuggestedEditsEnableInterests' => true,
			// The number of interests above which the selector stops suggesting more.
			// Keep the name: the shared interest selector reads it, and Special:Homepage
			// gives it the same one.
			'wgGENewcomerTasksMaxInterestsForQueries' => $this->getConfig()
				->get( 'GENewcomerTasksMaxInterestsForQueries' ),
			// The interests the user stored. FiltersStore reads them for the menu and
			// for the selector, which otherwise opens empty and saves over them.
			'wgGEInterestArticles' => $this->newcomerTasksUserOptionsLookup
				->getValidatedInterests( $this->getUser() ),
		];
	}
}
