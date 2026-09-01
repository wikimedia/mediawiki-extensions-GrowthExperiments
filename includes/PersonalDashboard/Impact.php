<?php

declare( strict_types = 1 );

namespace GrowthExperiments\PersonalDashboard;

use GrowthExperiments\HomepageModules\SuggestedEdits;
use GrowthExperiments\UserDatabaseHelper;
use MediaWiki\Context\IContextSource;
use MediaWiki\Extension\PersonalDashboard\Modules\BaseModule;
use MediaWiki\Html\Html;
use MediaWiki\User\Options\UserOptionsLookup;

/**
 * A straight port of the Homepage Impact module onto the Personal Dashboard:
 * the same scorecards/trend-chart/streak-graph Vue app, progressively
 * enhanced over the same server-rendered skeleton. First person only; the
 * Homepage module's third-person Special:Impact view has no PD equivalent.
 *
 * No UserImpactStore/UserImpactFormatter here: the client app's REST fallback
 * already covers the no-inline-data case, so a getJsData() override adding
 * the inline JSON payload is a follow-up, not a correctness requirement;
 * inject those services then, not before they're read.
 */
class Impact extends BaseModule {

	private ?array $hasMainspaceEditsCache = null;

	public function __construct(
		IContextSource $context,
		private readonly UserDatabaseHelper $userDatabaseHelper,
		private readonly UserOptionsLookup $userOptionsLookup
	) {
		parent::__construct( $context );
	}

	/**
	 * Always render: the Homepage module never gates on data availability
	 * either, the unactivated state is a variant the card itself renders
	 * (see isUnactivated()), not a reason to omit the card.
	 * @inheritDoc
	 */
	protected function canRender(): bool {
		return true;
	}

	/** @inheritDoc */
	protected function serverRendered(): bool {
		return true;
	}

	/** @inheritDoc */
	protected function getModules(): array {
		return [ 'ext.growthExperiments.Homepage.Impact' ];
	}

	/**
	 * A dedicated module, not ext.growthExperiments.Homepage.styles: that
	 * bundle carries unscoped rules for other Homepage modules (e.g. it
	 * restyles Mentorship's recent-questions block).
	 * @inheritDoc
	 */
	protected function getModuleStyles(): array {
		return [ 'ext.growthExperiments.PersonalDashboard.Impact.styles' ];
	}

	/** @inheritDoc */
	protected function getHeaderText(): string {
		return $this->msg( 'growthexperiments-homepage-impact-header', $this->getUser()->getName() )->text();
	}

	/**
	 * The skeleton the Vue app hydrates into, plus a no-JS fallback. Mirrors
	 * the desktop path of the Homepage module's getBody(): the client
	 * init.js picks a render mode from a 'homepagemodules' config var that
	 * only the Homepage sets, so absent that, it always falls back to
	 * desktop and mounts here.
	 * @inheritDoc
	 */
	protected function getBody(): string {
		return Html::rawElement( 'div',
				[
					'id' => 'impact-vue-root',
					'class' => 'ext-growthExperiments-impact-app-root',
				],
				$this->getBaseMarkup()
			) .
			Html::element( 'p',
				[ 'class' => 'growthexperiments-homepage-impact-no-js-fallback' ],
				$this->msg( 'growthexperiments-homepage-impact-no-js-fallback' )->text()
			);
	}

	/**
	 * ScoreCard server markup, matching modules/vue-components/CScoreCard.vue.
	 */
	private function getScoreCardMarkup(): string {
		return Html::rawElement( 'div', [
			'class' => 'ext-growthExperiments-ScoreCard',
		] );
	}

	/**
	 * ScoreCards server markup, matching modules/vue-components/CScoreCards.vue.
	 */
	private function getScoreCardsMarkup(): string {
		return Html::rawElement( 'div',
			[
				'class' => 'ext-growthExperiments-ScoreCards',
			],
			implode( '', [
				$this->getScoreCardMarkup(),
				$this->getScoreCardMarkup(),
				$this->getScoreCardMarkup(),
				$this->getScoreCardMarkup(),
			] )
		);
	}

	/**
	 * RecentActivity server markup, mimicking
	 * ext.growthExperiments.Homepage.Impact/components/RecentActivity.vue.
	 */
	private function getRecentActivityMarkup(): string {
		return Html::rawElement( 'div', [],
			Html::rawElement( 'div', [
				'class' => [
					'ext-growthExperiments-Skeleton',
					'ext-growthExperiments-Skeleton--darken',
				],
			] ) .
			Html::rawElement( 'div', [
				'class' => [
					'ext-growthExperiments-Skeleton',
					'ext-growthExperiments-Skeleton--double',
				],
			] ) .
			Html::rawElement( 'div', [
				'class' => [
					'ext-growthExperiments-Skeleton',
					'ext-growthExperiments-Skeleton--triple',
				],
			] )
		);
	}

	/**
	 * ArticlesList server markup.
	 *
	 * @param int $numberOfArticles The number of article skeletons to render
	 */
	private function getArticlesListMarkup( int $numberOfArticles = 5 ): string {
		return Html::rawElement( 'div', [],
			Html::rawElement( 'div', [
				'class' => [
					'ext-growthExperiments-ArticleListHeading',
					'ext-growthExperiments-Skeleton',
					'ext-growthExperiments-Skeleton--darken',
				],
			] ) .
			implode( "\n", array_map(
					static function ( $index ) {
						// Article animation delay starting at 400ms and increased 200ms for each article
						$delay = 400 + ( $index * 200 );
						return Html::rawElement( 'div', [
							'class' => [
								'ext-growthExperiments-ArticleLoading',
							],
						],
							Html::rawElement( 'div', [
								'class' => [
									'ext-growthExperiments-ArticleLoading__image',
									'ext-growthExperiments-Skeleton',
									'ext-growthExperiments-Skeleton--delay-' . $delay,
								],
							] ) .
							Html::rawElement( 'div', [
								'class' => [
									'ext-growthExperiments-ArticleLoading__text',
									'ext-growthExperiments-Skeleton',
									'ext-growthExperiments-Skeleton--darken',
									'ext-growthExperiments-Skeleton--delay-' . $delay,
								],
							] )
						);
					}, array_keys( array_fill( 0, $numberOfArticles, 1 ) ) )
			)
		);
	}

	/**
	 * Impact application server markup, hardcoded to the desktop layout (see
	 * getBody()); should be kept in sync with the Vue component tree
	 * (App.vue > Layout.vue > Impact.vue).
	 */
	private function getBaseMarkup(): string {
		return Html::rawElement( 'div',
			[
				'class' => 'ext-growthExperiments-App--UserImpact',
			],
			Html::rawElement( 'div',
				[
					'class' => 'ext-growthExperiments-Layout--desktop',
				],
				Html::rawElement( 'div',
					[
						'class' => 'ext-growthExperiments-Impact',
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

	/**
	 * Whether the user has no mainspace edits yet. Same computation as the
	 * Homepage module's getState(): the client Vue app uses this to switch
	 * from the impact scorecards to a "start editing" prompt.
	 */
	private function isUnactivated(): bool {
		return $this->hasMainspaceEdits() !== true;
	}

	/**
	 * Cached: PD re-enters module methods per module before the enabled gate
	 * (see Mentorship.php's getMentor()/getMentorshipState() for the same
	 * pattern), and the underlying query is a LIMIT-1000 revision scan.
	 */
	private function hasMainspaceEdits(): ?bool {
		// The cache has four states: true/false/null (valid hasMainspaceEdits()
		// return values) and uninitialized. Use an array hack to differentiate.
		if ( !$this->hasMainspaceEditsCache ) {
			$this->hasMainspaceEditsCache = [
				$this->userDatabaseHelper->hasMainspaceEdits( $this->getUser() ),
			];
		}
		return $this->hasMainspaceEditsCache[0];
	}

	/**
	 * The Vue app's client-side config. First person only: PD never renders
	 * one user's impact for a different viewer, so GEImpactThirdPersonRender
	 * is always false; explicit, not omitted, since the client's
	 * Boolean-typed prop rejects a missing/null value.
	 * @inheritDoc
	 */
	public function getJsConfigVars(): array {
		return [
			'GEImpactRelevantUserName' => $this->getUser()->getName(),
			'GEImpactRelevantUserId' => $this->getUser()->getId(),
			'GEImpactRelevantUserUnactivated' => $this->isUnactivated(),
			'GEImpactThirdPersonRender' => false,
			'GEImpactIsSuggestedEditsEnabledForUser' =>
				SuggestedEdits::isEnabledForAnyone( $this->getConfig() ),
			'GEImpactIsSuggestedEditsActivatedForUser' =>
				SuggestedEdits::isActivated( $this->getUser(), $this->userOptionsLookup ),
			'GEImpactMaxEdits' => $this->getConfig()->get( 'GEUserImpactMaxEdits' ),
			'GEImpactMaxThanks' => $this->getConfig()->get( 'GEUserImpactMaxThanks' ),
		];
	}
}
