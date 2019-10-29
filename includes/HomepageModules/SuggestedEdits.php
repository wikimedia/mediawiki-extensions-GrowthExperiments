<?php

namespace GrowthExperiments\HomepageModules;

use GrowthExperiments\EditInfoService;
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

	const ACTIVATED_PREF = 'growthexperiments-homepage-suggestededits-activated';

	/** @var EditInfoService */
	private $editInfoService;

	/** @var PageViewService|null */
	private $pageViewService;

	/**
	 * @param IContextSource $context
	 * @param EditInfoService $editInfoService
	 * @param PageViewService|null $pageViewService
	 */
	public function __construct(
		IContextSource $context,
		EditInfoService $editInfoService,
		?PageViewService $pageViewService
	) {
		parent::__construct( 'suggested-edits', $context );
		$this->editInfoService = $editInfoService;
		$this->pageViewService = $pageViewService;
	}

	/**
	 * Check whether suggested edits have been activated.
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
			   $extensionRegistry->isLoaded( 'TextExtracts' ) &&
			   $extensionRegistry->isLoaded( 'PageViewInfo' ) &&
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
		$siteViewsCount = $this->getSiteViews();
		$siteViewsMessage = $siteViewsCount ?
			$this->getContext()->msg( 'growthexperiments-homepage-suggestededits-footer' )
				->params( $this->formatSiteViews( $siteViewsCount ) ) :
			$this->getContext()->msg( 'growthexperiments-homepage-suggestededits-footer-noviews' );
		return Html::rawElement(
			'div', [ 'class' => 'suggested-edits-module-wrapper' ],
			Html::element( 'div', [ 'class' => 'suggested-edits-filters' ] ) .
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
		// TODO make this more human-readable
		return Message::numParam( $siteViewsCount );
	}

}
