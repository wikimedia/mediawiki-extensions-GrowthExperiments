<?php

namespace GrowthExperiments\HomepageModules;

use Exception;
use GrowthExperiments\ExperimentUserManager;
use GrowthExperiments\UserDatabaseHelper;
use GrowthExperiments\UserImpact\ComputedUserImpactLookup;
use GrowthExperiments\UserImpact\ExpensiveUserImpact;
use GrowthExperiments\UserImpact\UserImpactFormatter;
use GrowthExperiments\UserImpact\UserImpactStore;
use MediaWiki\Config\Config;
use MediaWiki\Context\IContextSource;
use MediaWiki\Html\Html;
use MediaWiki\User\UserIdentity;

/**
 * Class for the Impact module.
 */
class Impact extends BaseModule {

	private UserIdentity $userIdentity;
	private UserImpactStore $userImpactStore;
	private UserImpactFormatter $userImpactFormatter;
	private UserDatabaseHelper $userDatabaseHelper;
	private bool $isSuggestedEditsEnabledForUser;
	private bool $isSuggestedEditsActivatedForUser;

	/** @var ExpensiveUserImpact|null|false Lazy-loaded if false */
	private $userImpact = false;
	/** @var array|null|false Lazy-loaded if false */
	private $formattedUserImpact = false;
	private bool $forceShowingForOther = false;
	private ?array $hasMainspaceEditsCache = null;

	/**
	 * @param IContextSource $ctx
	 * @param Config $wikiConfig
	 * @param ExperimentUserManager $experimentUserManager
	 * @param UserIdentity $userIdentity
	 * @param UserImpactStore $userImpactStore
	 * @param UserImpactFormatter $userImpactFormatter
	 * @param UserDatabaseHelper $userDatabaseHelper
	 * @param bool $isSuggestedEditsEnabled
	 * @param bool $isSuggestedEditsActivated
	 */
	public function __construct(
		IContextSource $ctx,
		Config $wikiConfig,
		ExperimentUserManager $experimentUserManager,
		UserIdentity $userIdentity,
		UserImpactStore $userImpactStore,
		UserImpactFormatter $userImpactFormatter,
		UserDatabaseHelper $userDatabaseHelper,
		bool $isSuggestedEditsEnabled,
		bool $isSuggestedEditsActivated
	) {
		parent::__construct( 'impact', $ctx, $wikiConfig, $experimentUserManager );
		$this->userIdentity = $userIdentity;
		$this->userImpactStore = $userImpactStore;
		$this->userImpactFormatter = $userImpactFormatter;
		$this->userDatabaseHelper = $userDatabaseHelper;
		$this->isSuggestedEditsEnabledForUser = $isSuggestedEditsEnabled;
		$this->isSuggestedEditsActivatedForUser = $isSuggestedEditsActivated;
	}

	/** @inheritDoc */
	protected function getJsConfigVars() {
		return [
			'GEImpactRelevantUserName' => $this->userIdentity->getName(),
			'GEImpactRelevantUserId' => $this->userIdentity->getId(),
			'GEImpactRelevantUserUnactivated' => $this->isUnactivated(),
			'GEImpactThirdPersonRender' => $this->shouldShowForOtherUser(),
			'GEImpactIsSuggestedEditsEnabledForUser' => $this->isSuggestedEditsEnabledForUser,
			'GEImpactIsSuggestedEditsActivatedForUser' => $this->isSuggestedEditsActivatedForUser,
			'GEImpactMaxEdits' => $this->getConfig()->get( 'GEUserImpactMaxEdits' ),
			'GEImpactMaxThanks' => $this->getConfig()->get( 'GEUserImpactMaxThanks' ),
		];
	}

	/**
	 * @return string
	 */
	private function getUnactivatedModuleCssClass() {
		// The following classes are used here:
		// * growthexperiments-homepage-module-impact-unactivated-desktop
		// * growthexperiments-homepage-module-impact-unactivated-mobile-details
		// * growthexperiments-homepage-module-impact-unactivated-mobile-overlay
		// * growthexperiments-homepage-module-impact-unactivated-mobile-summary
		return 'growthexperiments-homepage-module-impact-unactivated-' . $this->getMode();
	}

	/**
	 * @inheritDoc
	 */
	protected function getCssClasses() {
		$unactivatedClasses = [];
		if ( $this->isUnactivated() ) {
			$unactivatedClasses[] = $this->getUnactivatedModuleCssClass();
		}
		return array_merge( parent::getCssClasses(), $unactivatedClasses );
	}

	/**
	 * Check if the user requesting the data matches the user data is requested
	 */
	private function isOwnData(): bool {
		return $this->getContext()->getUser()->equals( $this->userIdentity );
	}

	/**
	 * Check if texts should show first person or third person
	 */
	private function shouldShowForOtherUser(): bool {
		return !$this->isOwnData() || $this->forceShowingForOther;
	}

	/** @inheritDoc */
	protected function getHeaderText() {
		$headerText = 'growthexperiments-homepage-impact-header';
		if ( $this->shouldShowForOtherUser() ) {
			$headerText = 'growthexperiments-specialimpact-showing-for-other-user';
		}
		return $this->getContext()->msg( $headerText, $this->userIdentity->getName() )->text();
	}

	/** @inheritDoc */
	protected function getBody() {
		return Html::rawElement( 'div',
				[
					'id' => 'impact-vue-root',
					'class' => 'ext-growthExperiments-impact-app-root'
				],
				$this->getBaseMarkup()
			) .
			Html::element( 'p',
				[ 'class' => 'growthexperiments-homepage-impact-no-js-fallback' ],
				$this->msg( 'growthexperiments-homepage-impact-no-js-fallback' )->text()
			);
	}

	/** @inheritDoc */
	protected function getMobileSummaryBody() {
		return Html::rawElement( 'div',
				[
					'id' => 'impact-vue-root--mobile',
					'class' => [
						'ext-growthExperiments-impact-app-root',
						'ext-growthExperiments-impact-app-root--mobile'
					]
				],
				$this->getRecentActivityMarkup()
			) .
			Html::element( 'p',
				[ 'class' => 'growthexperiments-homepage-impact-no-js-fallback' ],
				$this->msg( 'growthexperiments-homepage-impact-no-js-fallback' )->text()
			);
	}

	/**
	 * ScoreCard server markup. A wrapper using only top-level styles from ScoreCard.less.
	 *
	 * @param int $index Card index, not relevant at the moment.
	 * @return string HTML content of a scorecard
	 * @see modules/vue-components/CScoreCard.{less,vue}
	 */
	private function getScoreCardMarkup( int $index ): string {
		return Html::rawElement( 'div', [
			'class' => 'ext-growthExperiments-ScoreCard'
		] );
	}

	/**
	 * ScoreCards server markup. A wrapper using only top-level styles from ScoreCards.less.
	 *
	 * @see modules/vue-components/CScoreCards.{less,vue}
	 * @return string HTML content of the scorecards section
	 */
	private function getScoreCardsMarkup(): string {
		return Html::rawElement( 'div',
			[
				'class' => 'ext-growthExperiments-ScoreCards'
			],
			implode( '', array_map( [ $this, 'getScoreCardMarkup' ], [ 1, 2, 3, 4 ] ) )
		);
	}

	/**
	 * RecentActivity server markup. Uses only styles from Skeleton.less, mimics
	 * RecentActivity.vue content
	 *
	 * @see modules/ext.growthExperiments.Homepage.Impact/components/RecentActivity.vue
	 * @return string HTML content of the recent activity section
	 */
	private function getRecentActivityMarkup(): string {
		return Html::rawElement( 'div', [],
			Html::rawElement( 'div', [
				'class' => [
					'ext-growthExperiments-Skeleton',
					'ext-growthExperiments-Skeleton--darken'
				]
			] ) .
			Html::rawElement( 'div', [
				'class' => [
					'ext-growthExperiments-Skeleton',
					'ext-growthExperiments-Skeleton--double'
				]
			] ) .
			Html::rawElement( 'div', [
				'class' => [
					'ext-growthExperiments-Skeleton',
					'ext-growthExperiments-Skeleton--triple'
				]
			] )
		);
	}

	/**
	 * ArticlesList server markup. Uses only styles from Skeleton.less, Impact.less and App.less.
	 *
	 * @param int $numberOfArticles The number of article skeletons to render
	 * @return string HTML content of the articles list section
	 * @see modules/ext.growthExperiments.Homepage.Impact/components/{App,Impact}.less
	 */
	private function getArticlesListMarkup( int $numberOfArticles = 5 ): string {
		// Articles list
		return Html::rawElement( 'div', [],
			Html::rawElement( 'div', [
				'class' => [
					'ext-growthExperiments-ArticleListHeading',
					'ext-growthExperiments-Skeleton',
					'ext-growthExperiments-Skeleton--darken'
				]
			] ) .
			implode( "\n", array_map(
					static function ( $index ) {
						// Article animation delay starting at 400ms and increased 200ms for each article
						$delay = 400 + ( $index * 200 );
						return Html::rawElement( 'div', [
							'class' => [
								'ext-growthExperiments-ArticleLoading'
							]
						],
							Html::rawElement( 'div', [
								'class' => [
									'ext-growthExperiments-ArticleLoading__image',
									'ext-growthExperiments-Skeleton',
									'ext-growthExperiments-Skeleton--delay-' . $delay
								]
							] ) .
							Html::rawElement( 'div', [
								'class' => [
									'ext-growthExperiments-ArticleLoading__text',
									'ext-growthExperiments-Skeleton',
									'ext-growthExperiments-Skeleton--darken',
									'ext-growthExperiments-Skeleton--delay-' . $delay
								]
							] )
						);
					}, array_keys( array_fill( 0, $numberOfArticles, 1 ) ) )
			)
		);
	}

	/**
	 * Impact application server markup. Does not use any styles from the CSS classes added.
	 * Should be kept in sync with Vue application component tree (App.vue > Layout.vue > Impact.vue).
	 *
	 * @see modules/ext.growthExperiments.Homepage.Impact/components/Impact.less
	 * @return string HTML content of the recent activity section
	 */
	private function getBaseMarkup(): string {
		return Html::rawElement( 'div',
			[
				'class' => 'ext-growthExperiments-App--UserImpact'
			],
			Html::rawElement( 'div',
				[
					// The following classes are generated here:
					// * ext-growthExperiments-Layout--desktop
					// * ext-growthExperiments-Layout--mobile-details
					// * ext-growthExperiments-Layout--mobile-overlay
					// * ext-growthExperiments-Layout--mobile-summary
					'class' => 'ext-growthExperiments-Layout--' . $this->getMode()
				],
				Html::rawElement( 'div',
					[
						'class' => 'ext-growthExperiments-Impact'
					],
					Html::rawElement( 'div',
						[],
						$this->getScoreCardsMarkup() .
						$this->getRecentActivityMarkup() .
						$this->getArticlesListMarkup()
					)
				)
			)
		);
	}

	/** @inheritDoc */
	protected function getHeaderIconName() {
		return 'chart';
	}

	/** @inheritDoc */
	protected function getModules() {
		return [ 'ext.growthExperiments.Homepage.Impact' ];
	}

	/**
	 * Set the relevant user to return data for. This will also force the module to display third person texts even if
	 * the user requesting it is the same the data is requested
	 */
	public function setUserDataIsFor( UserIdentity $user ) {
		$this->userIdentity = $user;
		$this->forceShowingForOther = true;
	}

	/**
	 * @inheritDoc
	 */
	public function getState() {
		if ( ( $this->canRender()
			// On null (first 1000 edits are non-mainspace) assume rest are non-mainspace as well
			// (chances are it's some kind of bot or role account).
			&& $this->hasMainspaceEdits() )
			// Always show the module activated when a user is looking to another user data
			|| $this->shouldShowForOtherUser()
		) {
			return self::MODULE_STATE_ACTIVATED;
		}
		return self::MODULE_STATE_UNACTIVATED;
	}

	/**
	 * Check if impact module is unactivated.
	 */
	private function isUnactivated(): bool {
		return $this->getState() === self::MODULE_STATE_UNACTIVATED;
	}

	/** @inheritDoc */
	public function getJsData( $mode ) {
		$data = parent::getJsData( $mode );
		$userImpact = $this->getUserImpact();
		$formattedUserImpact = $this->getFormattedUserImpact();
		// If the impact data's page view information is considered to be stale, then don't export
		// it here. The client-side app's request will be able to get a fresh data generation, and
		// it's ok for that to take longer. We wouldn't want to have the user wait here, though, as
		// this blocks page render.
		if ( !$userImpact || $userImpact->isPageViewDataStale() ) {
			$data['impact'] = null;
		} else {
			$data['impact'] = $formattedUserImpact;
		}
		return $data;
	}

	/**
	 * @inheritDoc
	 */
	public function getActionData(): array {
		$userImpact = $this->getUserImpact();
		$data = [
			'no_cached_user_impact' => !$userImpact
		];
		if ( $userImpact ) {
			$formattedUserImpact = $this->getFormattedUserImpact();
			$data = [
				'timeframe_in_days' => ComputedUserImpactLookup::PAGEVIEW_DAYS,
				'timeframe_edits_count' => $userImpact->getTotalEditsCount(),
				'thanks_count' => $userImpact->getReceivedThanksCount(),
				'last_edit_timestamp' => $userImpact->getLastEditTimestamp(),
				'longest_streak_days_count' => $userImpact->getLongestEditingStreakCount(),
				'top_articles_views_count' => $formattedUserImpact['topViewedArticlesCount'],
				'total_pageviews_count' => $formattedUserImpact['totalPageviewsCount'],
			];
		}
		return array_merge( parent::getActionData(), $data );
	}

	/**
	 * Get user impact, with an in-process cache.
	 *
	 * @return ExpensiveUserImpact|null
	 */
	private function getUserImpact(): ?ExpensiveUserImpact {
		if ( $this->userImpact !== false ) {
			return $this->userImpact;
		}
		$this->userImpact = $this->userImpactStore->getExpensiveUserImpact( $this->userIdentity );
		return $this->userImpact;
	}

	/**
	 * Get the output of UserImpactFormatter::format(), with an in-process cache.
	 * @return array
	 * @throws Exception
	 */
	private function getFormattedUserImpact(): array {
		if ( $this->formattedUserImpact !== false ) {
			return $this->formattedUserImpact;
		}
		$userImpact = $this->getUserImpact();
		$this->formattedUserImpact = $userImpact ?
			$this->userImpactFormatter->format( $userImpact, $this->getContext()->getLanguage()->getCode() ) :
			[];
		return $this->formattedUserImpact;
	}

	private function hasMainspaceEdits(): ?bool {
		// The cache has four states: true/false/null (valid hasMainspaceEdits() return values)
		// and uninitialized. Use an array hack to differentiate.
		if ( !$this->hasMainspaceEditsCache ) {
			$this->hasMainspaceEditsCache = [
				$this->userDatabaseHelper->hasMainspaceEdits( $this->userIdentity ),
			];
		}
		return $this->hasMainspaceEditsCache[0];
	}
}
