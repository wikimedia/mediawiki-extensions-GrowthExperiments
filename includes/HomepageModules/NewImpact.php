<?php

namespace GrowthExperiments\HomepageModules;

use Config;
use GrowthExperiments\ExperimentUserManager;
use Html;
use IContextSource;
use MediaWiki\User\UserIdentity;

/**
 * Class for the new Impact module.
 */
class NewImpact extends BaseModule {

	private UserIdentity $userIdentity;

	private bool $isSuggestedEditsEnabledForUser;

	private bool $isSuggestedEditsActivatedForUser;

	/**
	 * @param IContextSource $ctx
	 * @param Config $wikiConfig
	 * @param ExperimentUserManager $experimentUserManager
	 * @param UserIdentity $userIdentity
	 * @param bool $isSuggestedEditsEnabled
	 * @param bool $isSuggestedEditsActivated
	 */
	public function __construct(
		IContextSource $ctx,
		Config $wikiConfig,
		ExperimentUserManager $experimentUserManager,
		UserIdentity $userIdentity,
		bool $isSuggestedEditsEnabled,
		bool $isSuggestedEditsActivated
	) {
		parent::__construct( 'impact', $ctx, $wikiConfig, $experimentUserManager );
		$this->userIdentity = $userIdentity;
		$this->isSuggestedEditsEnabledForUser = $isSuggestedEditsEnabled;
		$this->isSuggestedEditsActivatedForUser = $isSuggestedEditsActivated;
	}

	/** @inheritDoc */
	protected function getJsConfigVars() {
		if ( !$this->userIdentity ) {
			return [];
		}

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
}
