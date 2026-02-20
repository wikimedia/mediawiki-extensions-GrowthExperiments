<?php

namespace GrowthExperiments\HomepageModules;

use GrowthExperiments\IExperimentManager;
use GrowthExperiments\Util;
use HtmlArmor;
use MediaWiki\Config\Config;
use MediaWiki\Context\IContextSource;
use MediaWiki\Extension\CommunityConfiguration\Provider\ConfigurationProviderFactory;
use MediaWiki\Extension\CommunityConfiguration\Provider\IConfigurationProvider;
use MediaWiki\Html\Html;
use MediaWiki\Http\HttpRequestFactory;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\Title\TitleFactory;
use MediaWiki\User\UserEditTracker;
use Psr\Log\LoggerInterface;
use stdClass;
use Wikimedia\ObjectCache\WANObjectCache;

class CommunityUpdates extends BaseModule {

	private LoggerInterface $logger;
	private ?IConfigurationProvider $provider = null;
	private ConfigurationProviderFactory $providerFactory;
	private UserEditTracker $userEditTracker;
	private LinkRenderer $linkRenderer;
	private TitleFactory $titleFactory;
	private WANObjectCache $cache;
	private HttpRequestFactory $httpRequestFactory;

	public function __construct(
		LoggerInterface $logger,
		IContextSource $context,
		Config $wikiConfig,
		IExperimentManager $experimentManager,
		ConfigurationProviderFactory $providerFactory,
		UserEditTracker $userEditTracker,
		LinkRenderer $linkRenderer,
		TitleFactory $titleFactory,
		WANObjectCache $cache,
		HttpRequestFactory $httpRequestFactory
	) {
		parent::__construct( 'community-updates', $context, $wikiConfig, $experimentManager );
		$this->logger = $logger;
		$this->providerFactory = $providerFactory;
		$this->userEditTracker = $userEditTracker;
		$this->linkRenderer = $linkRenderer;
		$this->titleFactory = $titleFactory;
		$this->cache = $cache;
		$this->httpRequestFactory = $httpRequestFactory;
	}

	private function initializeProvider() {
		if ( !$this->provider ) {
			$this->provider = $this->providerFactory->newProvider( 'CommunityUpdates' );
		}
	}

	private function shouldShowCommunityUpdatesModule( stdClass $config ): bool {
		return $config->GEHomepageCommunityUpdatesContentTitle !== '' &&
			$config->GEHomepageCommunityUpdatesContentBody !== '' &&
			( $this->userEditTracker->getUserEditCount( $this->getUser() ) >=
				$config->GEHomepageCommunityUpdatesMinEdits );
	}

	/**
	 * @inheritDoc
	 */
	protected function getHeaderText() {
		return $this->getContext()->msg( 'growthexperiments-homepage-community-updates-header' )->text();
	}

	/**
	 * Determines if the CommunityUpdates module can be rendered based on configuration and other conditions.
	 */
	protected function canRender(): bool {
		$this->initializeProvider();
		$configStatus = $this->provider->loadValidConfiguration();
		if ( !$configStatus->isOK() ) {
			return false;
		}
		$config = $configStatus->getValue();
		return $config->GEHomepageCommunityUpdatesEnabled && $this->shouldShowCommunityUpdatesModule( $config );
	}

	private function getThumbnail( string $fileTitle ): string {
		$cacheKey = $this->cache->makeKey( 'community-updates-thumburl', md5( $fileTitle ) );
		$cachedThumbUrl = $this->cache->get( $cacheKey );

		if ( $cachedThumbUrl ) {
			return $this->generateThumbnailHtml( $cachedThumbUrl );
		}

		$thumbUrl = $this->getThumbnailUrlFromCommonsApi( $fileTitle );
		if ( $thumbUrl ) {
			$this->cache->set( $cacheKey, $thumbUrl, $this->cache::TTL_HOUR );
			return $this->generateThumbnailHtml( $thumbUrl );
		}

		return '';
	}

	/**
	 * Generates the HTML for a thumbnail image.
	 *
	 * This method generates HTML on each call rather than caching the full HTML.
	 * This approach was chosen for the following reasons:
	 * 1. Flexibility: Allows easy updates to HTML structure without invalidating caches.
	 * 2. Separation of concerns: Keeps caching logic separate from presentation logic.
	 * 3. Future-proofing: Facilitates easier implementation of responsive images or other
	 *    advanced features in the future.
	 *
	 * Trade-offs and considerations:
	 * - Performance: There's a small performance cost of generating HTML on each request.
	 *   However, this is typically a lightweight operation compared to API calls or DB queries.
	 * - Caching: We're still caching the thumbnail URL, which provides the main performance benefit
	 *   by avoiding repeated API calls to Commons.
	 * - Flexibility vs. Performance: We've prioritized flexibility and maintainability over the
	 *   minor performance gain of caching full HTML.
	 *
	 * Future considerations:
	 * - If performance becomes a critical issue, we can consider implementing a short-lived cache
	 *   for the generated HTML in addition to caching the URL.
	 * - Monitor performance metrics to ensure this approach meets performance requirements.
	 *
	 * @param string $thumbUrl The URL of the thumbnail image
	 * @return string The generated HTML for the thumbnail
	 */
	private function generateThumbnailHtml( string $thumbUrl ): string {
		// mw-no-invert is added to avoid invert color in images for non-Vector night dark modes
		$darkModeClass = $this->shouldAddWhiteBackground() ?
			' ext-growthExperiments-CommunityUpdates__thumbnail__image--dark-mode mw-no-invert' : '';
		$thumbnailContent = Html::rawElement( 'div', [
			'class' => 'cdx-thumbnail__image ext-growthExperiments-CommunityUpdates__thumbnail__image' . $darkModeClass,
			'style' => 'background-image: url( ' . $thumbUrl . ' )',
		] );
		return Html::rawElement( 'div', [ 'class' => 'cdx-card__thumbnail' ], $thumbnailContent );
	}

	private function shouldAddWhiteBackground(): bool {
		$config = $this->provider->loadValidConfiguration()->getValue();
		return $config->GEHomepageCommunityUpdatesThumbnailAddDarkModeBackground;
	}

	private function getThumbnailUrlFromCommonsApi( string $fileTitle ): string {
		$apiUrl = $this->getGrowthWikiConfig()->get( 'CommunityConfigurationCommonsApiURL' );
		if ( !$apiUrl ) {
			$this->logger->debug(
				'CommunityUpdates module displaying without images support: Commons API URL is undefined'
			);
			return '';
		}
		// The thumbnail width is set to 120px, which is 3x the standard Codex thumbnail size (40px).
		// This provides a high-quality image that can be scaled down for various display sizes
		// while maintaining clarity and allowing for high-DPI displays.
		$thumbnailWidth = 120;
		$params = [
			'action' => 'query',
			'format' => 'json',
			'prop' => 'imageinfo',
			'titles' => $fileTitle,
			'iiprop' => 'url|size',
			'iiurlwidth' => $thumbnailWidth,
		];

		$url = wfAppendQuery( $apiUrl, $params );
		$status = Util::getJsonUrl(
			$this->httpRequestFactory,
			$url,
			// Assume we're on the same wikifarm, this enhances request with extra headers that
			// are ocassionally useful for debugging
			true
		);

		if ( !$status->isOK() ) {
			Util::logStatus( $status );
			return '';
		}

		$response = $status->getValue();
		if ( isset( $response['query']['pages'] ) ) {
			$page = array_first( $response['query']['pages'] );
			if ( isset( $page['imageinfo'][0]['thumburl'] ) ) {
				return $page['imageinfo'][0]['thumburl'];
			}
		}

		$this->logger->error(
			'CommunityUpdates failed to find thumburl in Commons API response',
			[
				'exception' => new \RuntimeException,
				'response' => $response,
			]
		);
		return '';
	}

	/**
	 * @inheritDoc
	 */
	protected function getBody() {
		$this->initializeProvider();
		if ( !$this->canRender() ) {
			return '';
		}
		$config = $this->provider->loadValidConfiguration()->getValue();
		$contentTitle = $config->GEHomepageCommunityUpdatesContentTitle;
		$contentBody = $config->GEHomepageCommunityUpdatesContentBody;
		$callToAction = $config->GEHomepageCommunityUpdatesCallToAction;

		$pageTitle = $this->titleFactory->newFromText( $callToAction->pageTitle );
		$link = '';
		if ( $pageTitle ) {
			$buttonText = $callToAction->buttonText ?: $this->getContext()->msg(
				'growthexperiments-homepage-communityupdates-calltoaction-button'
			)->text();

			$link = $this->linkRenderer->makeLink( $pageTitle, $buttonText, [
				'class' => 'ext-growthExperiments-CommunityUpdates__CallToAction__link',
				'data-link-id' => 'community-updates-cta',
				'data-link-data' => $pageTitle->getDBkey(),
			] );
		}

		$thumbnail = '';
		if ( $config->GEHomepageCommunityUpdatesThumbnailFile->title !== '' ) {
			$thumbnail = $this->getThumbnail( $config->GEHomepageCommunityUpdatesThumbnailFile->title );
		}

		return Html::rawElement( 'div', [ 'class' => 'cdx-card-content' ],
			$thumbnail .
			Html::rawElement(
				'div', [ 'class' => 'cdx-card__text__title' ], HtmlArmor::getHtml( $contentTitle )
			) .
			Html::rawElement(
				'div', [ 'class' => 'cdx-card__text__description' ], HtmlArmor::getHtml( $contentBody )
			) . $link
		);
	}

	public function getActionData(): array {
		$result = $this->provider->loadValidConfiguration();
		if ( !$result->isOK() ) {
			return parent::getActionData();
		}
		$config = $result->getValue();
		$cleanTitle = preg_replace( '/[^a-zA-Z0-9_ -]/s', '',
			$config->GEHomepageCommunityUpdatesContentTitle
		);
		$updateTitle = strtolower( implode( "_", explode( " ", $cleanTitle ) ) );

		return array_merge( parent::getActionData(), [
			'context' => $updateTitle,
		] );
	}

	public function shouldWrapModuleWithLink(): bool {
		return false;
	}

	/**
	 * @inheritDoc
	 */
	protected function getMobileSummaryBody() {
		return $this->getBody();
	}

	/**
	 * @inheritDoc
	 */
	protected function getMobileSummaryHeader() {
		return $this->getHeaderTextElement();
	}

	/**
	 * @inheritDoc
	 */
	protected function getHeaderIconName() {
		return '';
	}
}
