<?php

namespace GrowthExperiments\HomepageModules;

use GrowthExperiments\ExperimentUserManager;
use IContextSource;
use MediaWiki\Config\Config;
use MediaWiki\Extension\CommunityConfiguration\Provider\ConfigurationProviderFactory;
use MediaWiki\Extension\CommunityConfiguration\Provider\IConfigurationProvider;
use MediaWiki\Html\Html;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\Title\TitleFactory;
use MediaWiki\User\UserEditTracker;
use stdClass;

class CommunityUpdates extends BaseModule {
	private ?IConfigurationProvider $provider = null;
	private ConfigurationProviderFactory $providerFactory;
	private UserEditTracker $userEditTracker;
	private LinkRenderer $linkRenderer;
	private TitleFactory $titleFactory;

	/**
	 * @param IContextSource $context
	 * @param Config $wikiConfig
	 * @param ExperimentUserManager $experimentUserManager
	 * @param ConfigurationProviderFactory $providerFactory
	 * @param UserEditTracker $userEditTracker
	 * @param LinkRenderer $linkRenderer
	 * @param TitleFactory $titleFactory
	 */
	public function __construct(
		IContextSource $context,
		Config $wikiConfig,
		ExperimentUserManager $experimentUserManager,
		ConfigurationProviderFactory $providerFactory,
		UserEditTracker $userEditTracker,
		LinkRenderer $linkRenderer,
		TitleFactory $titleFactory
	) {
		parent::__construct( 'community-updates', $context, $wikiConfig, $experimentUserManager );
		$this->providerFactory = $providerFactory;
		$this->userEditTracker = $userEditTracker;
		$this->linkRenderer = $linkRenderer;
		$this->titleFactory = $titleFactory;
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
	 * @return bool
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

	private function getThumbnail( string $url ): string {
		$thumbnailContent = Html::rawElement( 'span', [ 'class' => 'cdx-thumbnail__placeholder' ],
			Html::rawElement( 'span', [
				'class' => 'cdx-thumbnail__placeholder__icon',
			] )
		);
		if ( $url !== '' ) {
			$thumbnailContent = Html::rawElement( 'div', [
				'class' => 'cdx-thumbnail__image ext-growthExperiments-CommunityUpdates__thumbnail__image',
				'style' => 'background-image: url( ' . $url . ');'
			] );
		}
		return Html::rawElement( 'div', [ 'class' => 'cdx-card__thumbnail' ], $thumbnailContent );
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
				'class' => 'ext-growthExperiments-CommunityUpdates__CallToAction__link'
			] );
		}

		return Html::rawElement( 'div', [ 'class' => 'cdx-card-content' ],
			Html::rawElement( 'div', [ 'class' => 'cdx-card-content-row-1' ],
				$this->getThumbnail( $config->GEHomepageCommunityUpdatesThumbnailFile->url ) .
				Html::rawElement( 'div', [ 'class' => 'cdx-card__text__title' ], $contentTitle )
			) .
			Html::rawElement( 'div', [ 'class' => 'cdx-card-content-row-2' ],
				Html::rawElement( 'div', [ 'class' => 'cdx-card__text__description' ], $contentBody ) .
				$link
			)
		);
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
	protected function getHeaderIconName() {
		return '';
	}
}
