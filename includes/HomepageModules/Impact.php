<?php

namespace GrowthExperiments\HomepageModules;

use ActorMigration;
use Config;
use DateTime;
use Exception;
use ExtensionRegistry;
use GrowthExperiments\ExperimentUserManager;
use Html;
use IContextSource;
use MediaWiki\Extension\PageViewInfo\PageViewService;
use MediaWiki\MediaWikiServices;
use MediaWiki\Title\Title;
use MediaWiki\Title\TitleFactory;
use MWTimestamp;
use OOUI\ButtonWidget;
use OOUI\IconWidget;
use PageImages\PageImages;
use SpecialPage;
use Wikimedia\Rdbms\IConnectionProvider;

/**
 * This is the "Impact" module. It shows the page views information
 * of recently edited pages.
 *
 * All timestamps in this file are in UTC. That's also what
 * the pageviews tool expects.
 *
 * @package GrowthExperiments\HomepageModules
 */
class Impact extends BaseModule {

	private const THUMBNAIL_SIZE = 40;

	/**
	 * @var array
	 */
	private $contribs = null;

	/**
	 * @var string
	 */
	private $body = null;

	/**
	 * @var bool
	 */
	private $pageViewsDataExists = false;

	/**
	 * @var string|null
	 */
	private $editsTable = null;

	/** @var IConnectionProvider */
	private $connectionProvider;

	/**
	 * @var bool
	 */
	private $isSuggestedEditsEnabledForUser;

	/**
	 * @var bool
	 */
	private $isSuggestedEditsActivatedForUser;

	/**
	 * @var TitleFactory
	 */
	private $titleFactory;

	/**
	 * @var PageViewService|null
	 */
	private $pageViewService;

	/**
	 * @param IContextSource $context
	 * @param Config $wikiConfig
	 * @param IConnectionProvider $connectionProvider
	 * @param ExperimentUserManager $experimentUserManager
	 * @param array $suggestedEditsConfig
	 * @param TitleFactory $titleFactory
	 * @param PageViewService|null $pageViewService
	 */
	public function __construct(
		IContextSource $context,
		Config $wikiConfig,
		IConnectionProvider $connectionProvider,
		ExperimentUserManager $experimentUserManager,
		array $suggestedEditsConfig,
		TitleFactory $titleFactory,
		PageViewService $pageViewService = null
	) {
		parent::__construct( 'impact', $context,  $wikiConfig, $experimentUserManager );
		$this->connectionProvider = $connectionProvider;
		$this->isSuggestedEditsEnabledForUser = $suggestedEditsConfig['isSuggestedEditsEnabled'];
		$this->isSuggestedEditsActivatedForUser = $suggestedEditsConfig['isSuggestedEditsActivated'];
		$this->titleFactory = $titleFactory;
		$this->pageViewService = $pageViewService;
	}

	/** @inheritDoc */
	public function canRender() {
		return $this->pageViewService !== null;
	}

	/**
	 * @inheritDoc
	 */
	protected function getModuleStyles() {
		return array_merge(
			parent::getModuleStyles(),
			[ 'oojs-ui.styles.icons-media', 'oojs-ui.styles.icons-interactions' ]
		);
	}

	/**
	 * @inheritDoc
	 */
	protected function getHeaderText() {
		return $this->getContext()
			->msg( 'growthexperiments-homepage-impact-header' )
			->params( $this->getContext()->getUser()->getName() )
			->text();
	}

	/**
	 * @inheritDoc
	 */
	protected function getBody() {
		if ( $this->body !== null ) {
			return $this->body;
		}
		if ( $this->isActivated() ) {
			$this->body = $this->getEditsTable();
		} elseif ( $this->isUnactivatedWithSuggestedEdits() ) {
			$this->body = $this->getUnactivatedModuleBody();

		} else {
			$this->body = Html::rawElement(
				'div',
				[],
				$this->getContext()
					->msg( 'growthexperiments-homepage-impact-body-no-edit' )
					->params( $this->getContext()->getUser()->getName() )
					->parse()
			);
		}
		return $this->body;
	}

	/**
	 * @inheritDoc
	 */
	protected function getMobileSummaryBody() {
		if ( $this->isActivated() ) {
			$purposeElement = '';
			$articleEditsElement = Html::rawElement(
				'div',
				[ 'class' => 'growthexperiments-homepage-impact-subheader-text' ],
				$this->getArticleOrTotalEditCountText()
			);
		} elseif ( $this->isUnactivatedWithSuggestedEdits() ) {
			return $this->getUnactivatedModuleBody() . Html::element(
					'div',
					[ 'class' => $this->getUnactivatedModuleCssClass() . '-description' ],
					$this->getContext()
						->msg( 'growthexperiments-homepage-impact-unactivated-description' )
						->params( $this->getContext()->getUser()->getName() )
						->text()
				);
		} else {
			$purposeElement = Html::element(
				'div',
				[ 'class' => 'growthexperiments-homepage-module-text-light' ],
				$this->getContext()
					->msg( 'growthexperiments-homepage-impact-mobilesummarybody-monitor' )
					->text()
			);
			$articleEditsElement = Html::element(
				'div',
				[ 'class' => [
					'growthexperiments-homepage-module-text-normal',
					'growthexperiments-homepage-impact-subheader-text',
				] ],
				$this->getContext()
					->msg( 'growthexperiments-homepage-impact-subheader-text-no-edit' )
					->text()
			);
		}

		$totalViewsElement = $this->getTotalViewsElement( $this->isActivated() );
		$pageViewsElement = Html::element(
			'div',
			[ 'class' => [
				'growthexperiments-homepage-impact-subheader-subtext',
				'growthexperiments-homepage-module-text-light'
			] ],
			$this->getContext()->msg( 'growthexperiments-homepage-impact-mobilebody-pageviews' )
				->numParams( $this->getTotalPageViews() )
				->text()
		);

		return Html::rawElement(
			'div',
			[ 'class' => 'growthexperiments-homepage-impact-column-text' ],
			$purposeElement . $articleEditsElement . $pageViewsElement
		) . Html::rawElement(
			'div',
			[ 'class' => 'growthexperiments-homepage-impact-column-pageviews' ],
			$totalViewsElement
		);
	}

	/**
	 * Generate the HTML for the edits table.
	 */
	private function generateEditsTable() {
		$articleLinkTooltip = $this->getContext()
			->msg( 'growthexperiments-homepage-impact-article-link-tooltip' )
			->text();
		$pageviewsTooltip = $this->getContext()
			->msg( 'growthexperiments-homepage-impact-pageviews-link-tooltip' )
			->text();
		$emptyImage = new IconWidget( [
			'icon' => 'image',
			'classes' => [ 'placeholder-image' ],
		] );
		$emptyViewsWidget = new ButtonWidget( [
				'classes' => [ 'empty-pageviews' ],
				'framed' => false,
				'icon' => 'clock',
				'title' => $this->getContext()
					->msg( 'growthexperiments-homepage-impact-empty-pageviews-tooltip' )
					->text(),
				'infusable' => true,
				'flags' => [ 'progressive' ],
			] );
		$this->editsTable = implode( "\n", array_map(
			function ( $contrib ) use (
				$articleLinkTooltip, $pageviewsTooltip, $emptyImage, $emptyViewsWidget
			) {
				$titleText = $contrib['title']->getText();
				$titlePrefixedText = $contrib['title']->getPrefixedText();
				$titleUrl = $contrib['title']->getLinkUrl();

				$imageUrl = $this->getImage( $contrib['title'] );
				$image = $imageUrl ?
					Html::element(
						'div',
						[
							'alt' => $titleText,
							'title' => $titlePrefixedText,
							'class' => 'real-image',
							'style' => 'background-image: url(' . $imageUrl . ');',
						]
					) : $emptyImage;
				$imageElement = Html::rawElement(
					'a',
					[
						'class' => 'article-image',
						'href' => $titleUrl,
						'title' => $articleLinkTooltip,
						'data-link-id' => 'impact-article-image',
					],
					$image
				);

				$titleElement = Html::rawElement(
					'span',
					[ 'class' => 'article-title' ],
					Html::element(
						'a',
						[
							'href' => $titleUrl,
							'title' => $articleLinkTooltip,
							'data-link-id' => 'impact-article-title',
						],
						$titlePrefixedText
					)
				);

				// Set this flag to check if page views data exists for at least
				// one article. This is used to determine if the mobile summary
				// should show the clock icon if all article edits have no page view
				// data yet. Once the flag is set to true, don't set it again.
				if ( !$this->pageViewsDataExists ) {
					// 'views' is null if no data exists.
					$this->pageViewsDataExists = isset( $contrib['views'] );
				}
				$viewsElement = isset( $contrib['views'] ) ?
					Html::element(
						'a',
						[
							'class' => 'pageviews',
							'href' => $this->getPageViewToolsUrl(
								$contrib['title'], $contrib['ts']
							),
							'title' => $pageviewsTooltip,
							'data-link-id' => 'impact-pageviews',
						],
						$this->getContext()->getLanguage()->formatNum( $contrib['views'] )
					) : $emptyViewsWidget;

				return Html::rawElement(
					'div',
					[ 'class' => 'impact-row' ],
					$imageElement . $titleElement . $viewsElement
				);
			},
			array_slice( $this->getArticleContributions(), 0, 5 )
		) );
	}

	private function getTotalViewsElement( $showPendingIcon = false ) {
		$views = $this->getTotalPageViews();
		if ( $views === 0 && !$this->pageViewsDataExists && $showPendingIcon ) {
			$views = new IconWidget( [
				'icon' => 'clock',
				'flags' => [ 'progressive' ],
				'framed' => false,
				'classes' => [ 'empty-pageviews-summary' ]
			] );
		} else {
			$views = htmlspecialchars( $this->getContext()->getLanguage()->formatNum( $views ) );
		}
		return Html::rawElement(
			'span',
			[ 'class' => 'growthexperiments-homepage-impact-mobile-totalviews' ],
			$views
		);
	}

	/** @inheritDoc */
	protected function getSubheaderText() {
		$textMsgKey = $this->getTotalPageViews() ?
			'growthexperiments-homepage-impact-subheader-text' :
			'growthexperiments-homepage-impact-subheader-text-no-pageviews';
		return Html::element(
			'p',
			[ 'class' => 'growthexperiments-homepage-module-text-normal' ],
			$this->getContext()
				->msg( $textMsgKey )
				->params( $this->getContext()->getUser()->getName() )
				->text()
		);
	}

	private function getSubheaderSubtext() {
		if ( $this->isActivated() ) {
			return Html::element(
				'p',
				[ 'class' => 'growthexperiments-homepage-module-text-light' ],
				$this->getContext()
					->msg( 'growthexperiments-homepage-impact-subheader-subtext' )
					->params( $this->getContext()->getUser()->getName() )
					->text()
			);
		}
		return '';
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
	 * @return string
	 */
	private function getUnactivatedModuleSubheader() {
		$subheader = Html::element(
			'h3',
			[ 'class' => $this->getUnactivatedModuleCssClass() . '-subheader' ],
			$this->getContext()
				->msg( 'growthexperiments-homepage-impact-unactivated-subheader-text' )
				->text()
		);
		$subheaderSubtext = Html::element(
			'h4',
			[ 'class' => $this->getUnactivatedModuleCssClass() . '-subheader-subtext' ],
			$this->getContext()
				->msg( 'growthexperiments-homepage-impact-unactivated-subheader-subtext' )
				->params( $this->getContext()->getUser()->getName() )
				->text()
		);
		return Html::rawElement(
			'div',
			[ 'class' => $this->getUnactivatedModuleCssClass() . '-subheader-container' ],
			$subheader . $subheaderSubtext
		);
	}

	/**
	 * @return string
	 */
	private function getUnactivatedModuleSuggestedEditsButton() {
		if ( in_array( $this->getMode(), [ self::RENDER_MOBILE_DETAILS, self::RENDER_MOBILE_DETAILS_OVERLAY ] ) ) {
			if ( $this->isSuggestedEditsActivatedForUser ) {
				$linkPath = 'Special:Homepage/suggested-edits';
				$linkModulePath = '#/homepage/suggested-edits';
			} else {
				$linkPath = 'Special:Homepage';
				// HACK: We use this to indicate to the client-side to use launchCta() to open the
				// start editing onboarding dialog for suggested edits.
				$linkModulePath = 'launchCta';
			}
			$button = new ButtonWidget( [
				'label' => $this->getContext()
					->msg( 'growthexperiments-homepage-impact-unactivated-suggested-edits-link' )
					->text(),
				'href' => $this->titleFactory->newFromText( $linkPath )->getLinkURL(),
				'classes' => [
					$this->getUnactivatedModuleCssClass() . '-suggested-edits-button',
					'see-suggested-edits-button',
				],
			] );
			$button->setAttributes( [
				'data-link-id' => 'impact-see-suggested-edits',
				'data-link-module-path' => $linkModulePath
			] );
			return $button;
		}
		return '';
	}

	/**
	 * @return string
	 */
	private function getUnactivatedModuleBody() {
		if ( $this->isUnactivatedWithSuggestedEdits() ) {
			return Html::rawElement(
				'div',
				[ 'class' => $this->getUnactivatedModuleCssClass() . '-body' ],
				Html::element(
					'div',
					[ 'class' => $this->getUnactivatedModuleCssClass() . '-image' ]
				) .
				$this->getUnactivatedModuleSubheader() .
				$this->getUnactivatedModuleSuggestedEditsButton()
			);
		}
		return '';
	}

	/**
	 * @inheritDoc
	 */
	protected function getSubheader() {
		if ( $this->isUnactivatedWithSuggestedEdits() ) {
			return '';
		}
		return $this->getSubheaderText() . $this->getSubheaderSubtext();
	}

	/**
	 * @inheritDoc
	 */
	protected function getSubheaderTag() {
		return 'div';
	}

	/**
	 * @inheritDoc
	 */
	protected function getFooter() {
		if ( $this->isUnactivatedWithSuggestedEdits() ) {
			return $this->getContext()
				->msg( 'growthexperiments-homepage-impact-unactivated-suggested-edits-footer' )
				->params( $this->getContext()->getUser()->getName() )
				->parse();
		}

		$user = $this->getContext()->getUser();
		$msgKey = $this->isActivated() ?
			'growthexperiments-homepage-impact-contributions-link' :
			'growthexperiments-homepage-impact-contributions-link-no-edit';
		return Html::rawElement(
			'a',
			[
				'href' => SpecialPage::getTitleFor( 'Contributions', $user->getName() )->getLinkURL(),
				'data-link-id' => 'impact-contributions',
			],
			$this->getContext()
				->msg( $msgKey )
				->numParams( $user->getEditCount() )
				->params( $user->getName() )
				->parse()
		);
	}

	/**
	 * @inheritDoc
	 */
	protected function getCssClasses() {
		$unactivatedClasses = $this->isUnactivatedWithSuggestedEdits() ?
			[ $this->getUnactivatedModuleCssClass() ] :
			[];
		return array_merge(
			parent::getCssClasses(),
			$this->isActivated() ?
				[ 'growthexperiments-homepage-impact-activated' ] :
				$unactivatedClasses
		);
	}

	/**
	 * @inheritDoc
	 */
	public function getState() {
		if ( $this->canRender() ) {
			return (bool)$this->getArticleContributions() ?
				self::MODULE_STATE_ACTIVATED :
				self::MODULE_STATE_UNACTIVATED;
		}
		return self::MODULE_STATE_NOTRENDERED;
	}

	private function isActivated() {
		return $this->getState() === self::MODULE_STATE_ACTIVATED;
	}

	/**
	 * Check if impact module is unactivated and suggested edits module is enabled
	 *
	 * @return bool
	 */
	private function isUnactivatedWithSuggestedEdits() {
		return $this->getState() === self::MODULE_STATE_UNACTIVATED && $this->isSuggestedEditsEnabledForUser;
	}

	/**
	 * @return array Top 10 recently edited articles with pageviews
	 */
	public function getArticleContributions() {
		if ( $this->contribs === null ) {
			$this->contribs = $this->queryArticleEdits();
			if ( count( $this->contribs ) ) {
				// Add pageviews data
				$this->addPageViews( $this->contribs );

				// Sort by pageviews DESC
				usort( $this->contribs, static function ( $a, $b ) {
					return ( $b['views'] ?? -1 ) <=> ( $a['views'] ?? -1 );
				} );
				// Generate the edits table for later use.
				$this->generateEditsTable();
			}
		}
		return $this->contribs;
	}

	private function getTotalPageViews() {
		if ( !$this->isActivated() ) {
			return 0;
		}
		$views = array_reduce(
			$this->getArticleContributions(),
			static function ( $subTotal, $contrib ) {
				return $subTotal + ( $contrib['views'] ?? 0 );
			},
			0
		);
		return $views;
	}

	/**
	 * Query the last 10 edited pages and the timestamp of the first edit for those pages.
	 *
	 * @return array[] like [ 'title' => <Title object>, 'ts' => <DateTime object> ]
	 * @throws Exception
	 */
	private function queryArticleEdits() {
		$actorMigration = ActorMigration::newMigration();
		$dbr = $this->connectionProvider->getReplicaDatabase();
		$actorQuery = $actorMigration->getWhere( $dbr, 'rev_user', $this->getContext()->getUser() );
		$subquery = $dbr->buildSelectSubquery(
			array_merge( [ 'revision' ], $actorQuery[ 'tables' ], [ 'page' ] ),
			[ 'rev_page', 'page_title', 'page_namespace', 'rev_timestamp' ],
			[
				$actorQuery[ 'conds' ],
				'rev_deleted' => 0,
				'page_namespace' => 0,
			],
			__METHOD__,
			[ 'ORDER BY' => 'rev_timestamp DESC', 'limit' => 1000 ],
			[ 'page' => [ 'JOIN', [ 'rev_page = page_id' ] ] ] + $actorQuery[ 'joins' ]
		);
		$result = $dbr->select(
			[ 'latest_edits' => $subquery ],
			[
				'rev_page',
				'page_title',
				'page_namespace',
				'max_ts' => 'MAX(rev_timestamp)',
				'min_ts' => 'MIN(rev_timestamp)',
			],
			[],
			__METHOD__,
			[
				'GROUP BY' => 'rev_page',
				'ORDER BY' => 'max_ts DESC',
				'LIMIT' => 10,
			]
		);
		$contribs = [];
		foreach ( $result as $row ) {
			$contribs[] = [
				'title' => Title::newFromRow( $row ),
				'ts' => new DateTime( $row->min_ts ),
			];
		}
		return $contribs;
	}

	/**
	 * Get the total number of article edits made by the current user.
	 *
	 * @return int
	 * @throws Exception
	 */
	private function getArticleEditCount() {
		$dbr = $this->connectionProvider->getReplicaDatabase();
		return $dbr->newSelectQueryBuilder()
			->select( 'rev_id' )
			->from( 'revision' )
			->join( 'page', null, [ 'rev_page = page_id' ] )
			->where( [
				'rev_actor' => MediaWikiServices::getInstance()->getActorNormalization()->findActorId(
					$this->getUser(),
					$dbr
				),
				'rev_deleted' => 0,
				'page_namespace' => NS_MAIN,
			] )
			->caller( __METHOD__ )
			->fetchRowCount();
	}

	private function getArticleOrTotalEditCountText() {
		$user = $this->getContext()->getUser();
		if ( $user->getEditCount() < 1000 ) {
			$msgKey = 'growthexperiments-homepage-impact-mobilebody-articleedits';
			$count = $this->getArticleEditCount();
		} else {
			$msgKey = 'growthexperiments-homepage-impact-mobilebody-totaledits';
			$count = $user->getEditCount();
		}
		return $this->getContext()->msg( $msgKey )
			->numParams( $count )
			->parse();
	}

	/**
	 * Add pageviews information to the array of recent contributions.
	 *
	 * @param array[] &$contribs Recent contributions
	 */
	private function addPageViews( &$contribs ) {
		$titles = array_column( $contribs, 'title' );
		$days = min( 60, $this->daysSince( end( $contribs )[ 'ts' ] ) );
		$data = $this->pageViewService->getPageData( $titles, $days );
		if ( $data->isGood() ) {
			foreach ( $contribs as &$contrib ) {
				$viewsByDay = $data->getValue()[ $contrib[ 'title' ]->getPrefixedDBkey() ] ?? [];
				if ( $viewsByDay ) {
					$editDate = $contrib[ 'ts' ];
					// go back to the beginning of the day of the edit
					$editDate->setTime( 0, 0 );
					$viewsByDaySinceEdit = array_filter(
						$viewsByDay,
						static function ( $views, $date ) use ( $editDate ) {
							return new DateTime( $date ) >= $editDate;
						},
						ARRAY_FILTER_USE_BOTH
					);
					if ( $viewsByDaySinceEdit ) {
						$contrib['views'] = array_reduce(
							$viewsByDaySinceEdit,
							static function ( $total, $views ) {
								return $total + ( is_numeric( $views ) ? $views : 0 );
							},
							0
						);
					} else {
						$contrib[ 'views' ] = null;
					}
				} else {
					$contrib[ 'views' ] = null;
				}
			}
		}
	}

	/**
	 * Get image URL for a page
	 * Depends on the PageImages extension.
	 *
	 * @param Title $title
	 * @return bool|string
	 */
	private function getImage( Title $title ) {
		if ( !ExtensionRegistry::getInstance()->isLoaded( 'PageImages' ) ) {
			return false;
		}

		$imageFile = PageImages::getPageImage( $title );
		if ( $imageFile ) {
			$ratio = $imageFile->getWidth() / $imageFile->getHeight();
			$options = [
				'width' => $ratio > 1 ?
					self::THUMBNAIL_SIZE / $imageFile->getHeight() * $imageFile->getWidth() :
					self::THUMBNAIL_SIZE
			];
			$thumb = $imageFile->transform( $options );
			if ( $thumb ) {
				return $thumb->getUrl();
			}
		}

		return false;
	}

	/**
	 * @param DateTime $timestamp
	 * @return int Number of days since, and including, the given timestamp
	 * @throws Exception
	 */
	private function daysSince( DateTime $timestamp ) {
		$now = MWTimestamp::getInstance();
		$diff = $now->timestamp->diff( $timestamp );
		return $diff->days;
	}

	/**
	 * @param Title $title
	 * @param DateTime $start
	 * @return string Full URL for the PageViews tool for the given title and start date
	 * @throws Exception
	 */
	private function getPageViewToolsUrl( $title, $start ) {
		$baseUrl = 'https://pageviews.wmcloud.org/';
		$format = 'Y-m-d';
		return wfAppendQuery( $baseUrl, [
			'project' => $this->getContext()->getConfig()->get( 'ServerName' ),
			'userlang' => $this->getContext()->getLanguage()->getCode(),
			'start' => $start->format( $format ),
			'end' => 'latest',
			'pages' => $title->getPrefixedDBkey(),
		] );
	}

	/**
	 * @inheritDoc
	 */
	protected function getHeaderIconName() {
		return 'chart';
	}

	/**
	 * @return string|null
	 */
	protected function getEditsTable() {
		return $this->editsTable;
	}
}
