<?php

namespace GrowthExperiments\HomepageModules;

use Config;
use GrowthExperiments\ExperimentUserManager;
use GrowthExperiments\UserImpact\ComputedUserImpactLookup;
use GrowthExperiments\UserImpact\SortedFilteredUserImpact;
use GrowthExperiments\UserImpact\UserImpactStore;
use Html;
use IContextSource;
use MediaWiki\User\UserIdentity;

/**
 * Class for the new Impact module.
 */
class NewImpact extends BaseModule {

	private UserIdentity $userIdentity;
	private UserImpactStore $userImpactStore;
	private bool $isSuggestedEditsEnabledForUser;
	private bool $isSuggestedEditsActivatedForUser;

	/** @var SortedFilteredUserImpact|null|false */
	private $userImpact;

	/**
	 * @param IContextSource $ctx
	 * @param Config $wikiConfig
	 * @param ExperimentUserManager $experimentUserManager
	 * @param UserIdentity $userIdentity
	 * @param UserImpactStore $userImpactStore
	 * @param bool $isSuggestedEditsEnabled
	 * @param bool $isSuggestedEditsActivated
	 */
	public function __construct(
		IContextSource $ctx,
		Config $wikiConfig,
		ExperimentUserManager $experimentUserManager,
		UserIdentity $userIdentity,
		UserImpactStore $userImpactStore,
		bool $isSuggestedEditsEnabled,
		bool $isSuggestedEditsActivated
	) {
		parent::__construct( 'impact', $ctx, $wikiConfig, $experimentUserManager );
		$this->userIdentity = $userIdentity;
		$this->userImpactStore = $userImpactStore;
		$this->isSuggestedEditsEnabledForUser = $isSuggestedEditsEnabled;
		$this->isSuggestedEditsActivatedForUser = $isSuggestedEditsActivated;
		$this->userImpact = null;
	}

	/** @inheritDoc */
	protected function getJsConfigVars() {
		return [
			'GENewImpactD3Enabled' => $this->getConfig()->get( 'GENewImpactD3Enabled' ),
			'GENewImpactRelevantUserName' => $this->userIdentity->getName(),
			'GENewImpactRelevantUserId' => $this->userIdentity->getId(),
			'GENewImpactRelevantUserEditCount' => $this->getContext()->getUser()->getEditCount(),
			'GENewImpactIsSuggestedEditsEnabledForUser' => $this->isSuggestedEditsEnabledForUser,
			'GENewImpactIsSuggestedEditsActivatedForUser' => $this->isSuggestedEditsActivatedForUser,
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
		if ( $this->isUnactivatedOrDisabled() ) {
			$unactivatedClasses[] = $this->getUnactivatedModuleCssClass();
		}
		return array_merge( parent::getCssClasses(),  $unactivatedClasses );
	}

	/** @inheritDoc */
	protected function getHeaderText() {
		return $this->getContext()
			->msg( 'growthexperiments-homepage-new-impact-header' )
			->params( $this->getContext()->getUser()->getName() )
			->text();
	}

	/** @inheritDoc */
	protected function getBody() {
		return Html::rawElement( 'div',
				[
					'id' => 'new-impact-vue-root',
					'class' => 'ext-growthExperiments-new-impact-app-root'
				],
			) .
			Html::element( 'p',
				[ 'class' => 'growthexperiments-homepage-new-impact-no-js-fallback' ],
				$this->msg( 'growthexperiments-homepage-new-impact-no-js-fallback' )->text()
			);
	}

	/** @inheritDoc */
	protected function getMobileSummaryBody() {
		return Html::rawElement( 'div',
				[
					'id' => 'new-impact-vue-root--mobile',
					'class' => 'ext-growthExperiments-new-impact-app-root--mobile'
				],
			) .
			Html::element( 'p',
				[ 'class' => 'growthexperiments-homepage-new-impact-no-js-fallback' ],
				$this->msg( 'growthexperiments-homepage-new-impact-no-js-fallback' )->text()
			);
	}

	/** @inheritDoc */
	protected function getHeaderIconName() {
		return 'chart';
	}

	/** @inheritDoc */
	protected function getModules() {
		return [ 'ext.growthExperiments.Homepage.NewImpact' ];
	}

	/**
	 * @inheritDoc
	 */
	public function getState() {
		if ( $this->canRender() ) {
			return $this->getContext()->getUser()->getEditCount() ?
				self::MODULE_STATE_ACTIVATED :
				self::MODULE_STATE_UNACTIVATED;
		}
		return self::MODULE_STATE_NOTRENDERED;
	}

	/**
	 * Check if impact module is unactivated and suggested edits' module is enabled.
	 *
	 * @return bool
	 */
	private function isUnactivatedOrDisabled() {
		return $this->getState() === self::MODULE_STATE_UNACTIVATED || !$this->isSuggestedEditsEnabledForUser;
	}

	/** @inheritDoc */
	public function getJsData( $mode ) {
		$data = parent::getJsData( $mode );
		$impact = $this->getUserImpact();
		// If the impact data's page view information is considered to be stale, then don't export
		// it here. The client-side app's request will be able to get a fresh data generation, and
		// it's ok for that to take longer. We wouldn't want to have the user wait here, though, as
		// this blocks page render.
		if ( !$impact || $impact->isPageViewDataStale() ) {
			$data['impact'] = null;
		} else {
			$data['impact'] = $impact;
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
			$jsonSortedUserImpact = $userImpact->jsonSerialize();
			$data = [
				'timeframe_in_days' => ComputedUserImpactLookup::PAGEVIEW_DAYS,
				'timeframe_edits_count' => $userImpact->getTotalEditsCount(),
				'thanks_count' => $userImpact->getReceivedThanksCount(),
				'last_edit_timestamp' => $userImpact->getLastEditTimestamp(),
				'longest_streak_days_count' => $userImpact->getLongestEditingStreakCount(),
				'top_articles_views_count' => $jsonSortedUserImpact['topViewedArticlesCount']
			];
		}
		return array_merge( parent::getActionData(), $data );
	}

	/**
	 * Get user impact, with an in-process cache.
	 *
	 * @return SortedFilteredUserImpact|null
	 */
	private function getUserImpact(): ?SortedFilteredUserImpact {
		if ( $this->userImpact instanceof SortedFilteredUserImpact ) {
			return $this->userImpact;
		} elseif ( $this->userImpact === false ) {
			return null;
		}
		$userImpact = $this->userImpactStore->getExpensiveUserImpact( $this->userIdentity );
		if ( !$userImpact ) {
			// Set to "false" as an indicator that we already tried accessing, so subsequent
			// calls to getUserImpact can return early.
			$this->userImpact = false;
			return null;
		}
		$this->userImpact = SortedFilteredUserImpact::newFromUnsortedJsonArray(
			$userImpact->jsonSerialize()
		);
		return $this->userImpact;
	}
}
