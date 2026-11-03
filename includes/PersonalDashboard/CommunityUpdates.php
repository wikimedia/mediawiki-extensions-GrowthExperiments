<?php

declare( strict_types = 1 );

namespace GrowthExperiments\PersonalDashboard;

use GrowthExperiments\Util;
use MediaWiki\Config\Config;
use MediaWiki\Context\IContextSource;
use MediaWiki\Extension\CommunityConfiguration\Provider\ConfigurationProviderFactory;
use MediaWiki\Extension\CommunityConfiguration\Provider\IConfigurationProvider;
use MediaWiki\Extension\PersonalDashboard\Modules\BaseModule;
use MediaWiki\Html\Html;
use MediaWiki\Http\HttpRequestFactory;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\Title\TitleFactory;
use MediaWiki\User\UserEditTracker;
use Psr\Log\LoggerInterface;
use stdClass;
use Wikimedia\HtmlArmor\HtmlArmor;
use Wikimedia\ObjectCache\WANObjectCache;

/**
 * The CommunityUpdates module is rendered completely server-side.
 * It will appear for a user who disables JavaScript.
 */
class CommunityUpdates extends BaseModule {

	private ?IConfigurationProvider $provider = null;

	public function __construct(
		IContextSource $context,
		private readonly UserEditTracker $userEditTracker,
		private readonly ConfigurationProviderFactory $providerFactory,
		private readonly LinkRenderer $linkRenderer,
		private readonly TitleFactory $titleFactory,
		private readonly WANObjectCache $cache,
		private readonly HttpRequestFactory $httpRequestFactory,
		private readonly Config $growthConfig,
		private readonly LoggerInterface $logger
	) {
		parent::__construct( $context );
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
	 * Determines if the CommunityUpdates module can be rendered.
	 * @return bool
	 */
	protected function canRender(): bool {
		if ( !$this->growthConfig->get( 'GECommunityUpdatesEnabled' ) ) {
			return false;
		}
		$this->initializeProvider();
		$configStatus = $this->provider->loadValidConfiguration();
		if ( !$configStatus->isOK() ) {
			return false;
		}
		$config = $configStatus->getValue();
		return $config->GEHomepageCommunityUpdatesEnabled && $this->shouldShowCommunityUpdatesModule( $config );
	}

	/**
	 * The server renders the Community Updates module.
	 * @return bool
	 */
	protected function serverRendered(): bool {
		return true;
	}

	/** @inheritDoc */
	protected function outputDependencies(): void {
		$out = $this->getContext()->getOutput();
		$out->addModuleStyles( [ 'ext.growthExperiments.PersonalDashboard.CommunityUpdates.styles' ] );

		parent::outputDependencies();
	}

	/** @inheritDoc */
	protected function getHeaderText(): string {
		return $this->getContext()->msg( 'growthexperiments-homepage-community-updates-header' )->text();
	}

	/** @inheritDoc */
	protected function getBody(): string {
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

		return Html::rawElement( 'div', [ 'class' => 'cdx-card' ],
			Html::rawElement( 'div', [ 'class' => 'cdx-card-content' ],
			$thumbnail .
			Html::rawElement(
				'div', [ 'class' => 'cdx-card__text__title' ], HtmlArmor::getHtml( $contentTitle )
			) .
			Html::rawElement(
				'div', [ 'class' => 'cdx-card__text__description' ], HtmlArmor::getHtml( $contentBody )
			) . $link
		) );
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

	private function shouldAddWhiteBackground(): bool {
		$config = $this->provider->loadValidConfiguration()->getValue();
		return $config->GEHomepageCommunityUpdatesThumbnailAddDarkModeBackground;
	}

	private function getThumbnailUrlFromCommonsApi( string $fileTitle ): string {
		$apiUrl = $this->growthConfig->get( 'CommunityConfigurationCommonsApiURL' );
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
			// Assume we’re on the same wikifarm, this enhances request with extra headers that
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

	private function generateThumbnailHtml( string $thumbUrl ): string {
		// mw-no-invert is added to avoid invert color in images for non-Vector night dark modes
		$darkModeClass = $this->shouldAddWhiteBackground() ?
			' ext-growthExperiments-PersonalDashboard-CommunityUpdates__thumbnail__image--dark-mode mw-no-invert' : '';
		$thumbnailContent = Html::rawElement( 'div', [
			'class' => 'cdx-thumbnail__image ext-growthExperiments-PersonalDashboard-CommunityUpdates__thumbnail__image'
				. $darkModeClass,
			'style' => 'background-image: url( ' . $thumbUrl . ' )',
		] );
		return Html::rawElement( 'div', [ 'class' => 'cdx-card__thumbnail' ], $thumbnailContent );
	}
}
