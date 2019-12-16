<?php

namespace GrowthExperiments\HomepageModules;

use Config;
use GrowthExperiments\EditInfoService;
use GrowthExperiments\HomepageModule;
use GrowthExperiments\NewcomerTasks\ConfigurationLoader\ConfigurationLoader;
use IContextSource;
use Html;
use ExtensionRegistry;
use MediaWiki\Extensions\PageViewInfo\PageViewService;
use MediaWiki\Logger\LoggerFactory;
use Message;
use Status;
use StatusValue;

/**
 * Homepage module that displays a list of recommended tasks.
 * This is JS-only functionality; most of the logic is in the
 * ext.growthExperiments.Homepage.SuggestedEdits module.
 */
class SuggestedEdits extends BaseModule {

	const ENABLED_PREF = 'growthexperiments-homepage-suggestededits';
	const ACTIVATED_PREF = 'growthexperiments-homepage-suggestededits-activated';
	const PREACTIVATED_PREF = 'growthexperiments-homepage-suggestededits-preactivated';
	const SUGGESTED_EDIT_TAG = 'newcomer task';

	/** @var EditInfoService */
	private $editInfoService;

	/** @var PageViewService|null */
	private $pageViewService;
	/**
	 * @var ConfigurationLoader|null
	 */
	private $configurationLoader;

	/** @var string[] cache key => HTML */
	private $htmlCache = [];

	/**
	 * @param IContextSource $context
	 * @param EditInfoService $editInfoService
	 * @param PageViewService|null $pageViewService
	 * @param ConfigurationLoader|null $configurationLoader
	 */
	public function __construct(
		IContextSource $context,
		EditInfoService $editInfoService,
		?PageViewService $pageViewService,
		?ConfigurationLoader $configurationLoader
	) {
		parent::__construct( 'suggested-edits', $context );
		$this->editInfoService = $editInfoService;
		$this->pageViewService = $pageViewService;
		$this->configurationLoader = $configurationLoader;
	}

	/**
	 * Check whether the suggested edits feature is (or could be) enabled for anyone
	 * on the wiki.
	 * @param Config $config
	 * @return bool
	 */
	public static function isEnabledForAnyone( Config $config ) {
		return $config->get( 'GEHomepageSuggestedEditsEnabled' );
	}

	/**
	 * Check whether the suggested edits feature is enabled for the context user.
	 * @param IContextSource $context
	 * @return bool
	 */
	public static function isEnabled( IContextSource $context ) {
		return self::isEnabledForAnyone( $context->getConfig() ) && (
			!$context->getConfig()->get( 'GEHomepageSuggestedEditsRequiresOptIn' ) ||
			$context->getUser()->getBoolOption( self::ENABLED_PREF )
		);
	}

	/** @inheritDoc */
	public function getHtml() {
		// This method will be called both directly by the homepage and by getJsData() in
		// some cases, so use some lightweight caching.
		$key = $this->getMode() . ':' . $this->getContext()->getLanguage()->getCode();
		if ( !array_key_exists( $key, $this->htmlCache ) ) {
			$this->htmlCache[$key] = parent::getHtml();
		}
		return $this->htmlCache[$key];
	}

	/** @inheritDoc */
	public function getJsData( $mode ) {
		$data = parent::getJsData( $mode );

		// When the module is not activated yet, but can be, include module HTML in the
		// data, for dynamic loading on activation.
		if ( self::isEnabled( $this->getContext() ) &&
			!self::isActivated( $this->getContext() ) &&
			$this->getMode() !== HomepageModule::RENDER_MOBILE_DETAILS
		) {
			$data += [
				'html' => $this->getHtml(),
				'rlModules' => $this->getModules(),
			];
		}

		return $data;
	}

	/**
	 * Check whether suggested edits have been activated for the context user.
	 * Before activation, suggested edits are exposed via the StartEditing module;
	 * after activation (which happens by interacting with that module) via this one.
	 * @param IContextSource $context
	 * @return bool
	 */
	public static function isActivated( IContextSource $context ) {
		return (bool)$context->getUser()->getBoolOption( self::ACTIVATED_PREF );
	}

	/** @inheritDoc */
	public function getState() {
		return self::isActivated( $this->getContext() ) ?
			self::MODULE_STATE_ACTIVATED :
			self::MODULE_STATE_UNACTIVATED;
	}

	/** @inheritDoc */
	protected function canRender() {
		$extensionRegistry = ExtensionRegistry::getInstance();
		return self::isActivated( $this->getContext() ) &&
			   !$this->configurationLoader->loadTaskTypes() instanceof StatusValue &&
			   $extensionRegistry->isLoaded( 'PageImages' );
	}

	/** @inheritDoc */
	protected function getHeaderText() {
		return $this->getContext()
			->msg( 'growthexperiments-homepage-suggested-edits-header' )
			->text();
	}

	/** @inheritDoc */
	protected function getHeaderIconName() {
		return 'lightbulb';
	}

	/** @inheritDoc */
	protected function getBody() {
		$isDesktop = $this->getMode() === self::RENDER_DESKTOP;
		$siteViewsCount = $this->getSiteViews();
		$siteViewsMessage = $siteViewsCount ?
			$this->getContext()->msg( 'growthexperiments-homepage-suggestededits-footer' )
				->params( $this->formatSiteViews( $siteViewsCount ) ) :
			$this->getContext()->msg( 'growthexperiments-homepage-suggestededits-footer-noviews' );
		return Html::rawElement(
			'div', [ 'class' => 'suggested-edits-module-wrapper' ],
			( $isDesktop ? Html::element( 'div', [ 'class' => 'suggested-edits-filters' ] ) : '' ) .
			Html::element( 'div', [ 'class' => 'suggested-edits-pager' ] ) .
			Html::rawElement( 'div', [ 'class' => 'suggested-edits-card-wrapper' ],
				Html::element( 'div', [ 'class' => 'suggested-edits-previous' ] ) .
				Html::element( 'div', [ 'class' => 'suggested-edits-card' ] ) .
				Html::element( 'div', [ 'class' => 'suggested-edits-next' ] ) ) .
			Html::element( 'div', [ 'class' => 'suggested-edits-task-explanation' ] ) .
			Html::rawElement( 'div', [ 'class' => 'suggested-edits-footer' ], $siteViewsMessage->parse() )
		);
	}

	/**
	 * @inheritDoc
	 * @suppress SecurityCheck-DoubleEscaped
	 */
	protected function getMobileSummaryBody() {
		// For some reason phan thinks $siteEditsPerDay and/or $metricNumber get double-escaped,
		// but they are escaped just the right amount.
		$siteEditsPerDay = $this->editInfoService->getEditsPerDay();
		if ( $siteEditsPerDay instanceof StatusValue ) {
			LoggerFactory::getInstance( 'GrowthExperiments' )->warning(
				'Failed to load site edits per day stat: {status}',
				[ 'status' => Status::wrap( $siteEditsPerDay )->getWikiText( null, null, 'en' ) ]
			);
			// TODO probably have some kind of fallback message?
			$siteEditsPerDay = 0;
		}
		$metricNumber = $this->getContext()->getLanguage()->formatNum( $siteEditsPerDay );
		$metricSubtitle = $this->getContext()
			->msg( 'growthexperiments-homepage-suggestededits-mobilesummary-metricssubtitle' )
			->text();
		$footerText = $this->getContext()
			->msg( 'growthexperiments-homepage-suggestededits-mobilesummary-footer' )
			->text();
		return Html::rawElement( 'div', [ 'class' => 'suggested-edits-main' ],
				Html::rawElement( 'div', [ 'class' => 'suggested-edits-icon' ] ) .
				Html::rawElement( 'div', [ 'class' => 'suggested-edits-metric' ],
					Html::element( 'div', [ 'class' => 'suggested-edits-metric-number' ], $metricNumber ) .
					Html::element( 'div', [ 'class' => 'suggested-edits-metric-subtitle' ], $metricSubtitle )
				)
			) . Html::element( 'div', [
				'class' => 'suggested-edits-footer'
			], $footerText );
	}

	/** @inheritDoc */
	protected function getSubheader() {
		// Ugly hack to get the filters positioned outside of the module wrapper on mobile.
		$mobileDetails = [ self::RENDER_MOBILE_DETAILS, self::RENDER_MOBILE_DETAILS_OVERLAY ];
		if ( !in_array( $this->getMode(), $mobileDetails, true ) ) {
			return null;
		}
		return Html::rawElement( 'div', [ 'class' => 'suggested-edits-filters' ] );
	}

	/** @inheritDoc */
	protected function getSubheaderTag() {
		return 'div';
	}

	/** @inheritDoc */
	protected function getModules() {
		return array_merge(
			parent::getModules(),
			[ 'ext.growthExperiments.Homepage.SuggestedEdits' ]
		);
	}

	/**
	 * Returns site views in the last 30 days.
	 * @return int|null
	 */
	protected function getSiteViews() {
		if ( !$this->pageViewService ||
			 !$this->pageViewService->supports( PageViewService::METRIC_UNIQUE, PageViewService::SCOPE_SITE )
		) {
			return null;
		}
		// When PageViewService is a WikimediaPageViewService, the pageviews for the last two days
		// or so will be missing due to AQS processing lag. Get some more days and discard the
		// newest ones.
		$status = $this->pageViewService->getSiteData( 32, PageViewService::METRIC_UNIQUE );
		if ( !$status->isOK() ) {
			return null;
		}
		$data = $status->getValue();
		ksort( $data );
		return array_sum( array_slice( $data, 0, 30 ) );
	}

	/**
	 * Format site views count in a human-readable way.
	 * @param int $siteViewsCount
	 * @return string|array A Message::params() parameter
	 */
	protected function formatSiteViews( int $siteViewsCount ) {
		// We only get here when $siteViewsCount is not 0 so log is safe.
		$siteViewsCount = (int)round( $siteViewsCount, -floor( log10( $siteViewsCount ) ) );
		$language = $this->getContext()->getLanguage();
		if ( $this->getContext()->msg( 'growthexperiments-homepage-suggestededits-footer-suffix' )
			->isDisabled()
		) {
			// This language does not use suffixes, just output the rounded number
			return Message::numParam( $siteViewsCount );
		}
		// Abuse Language::formatComputingNumbers into displaying large numbers in a human-readable way
		return $language->formatComputingNumbers( $siteViewsCount, 1000,
			'growthexperiments-homepage-suggestededits-footer-$1suffix' );
	}

	/** @inheritDoc */
	protected function getActionData() {
		return array_merge(
			parent::getActionData(),
			[
				// these will be updated on the client side as needed
				'taskTypes' => json_decode( $this->getContext()->getUser()->getOption(
					'growthexperiments-homepage-se-filters'
				) ),
				'taskCount' => null,
			]
		);
	}

}
