<?php

namespace GrowthExperiments\HomepageModules;

use GrowthExperiments\ExperimentUserManager;
use IContextSource;
use MediaWiki\Config\Config;
use MediaWiki\Extension\CommunityConfiguration\Provider\ConfigurationProviderFactory;
use MediaWiki\Extension\CommunityConfiguration\Provider\IConfigurationProvider;
use MediaWiki\Html\Html;

class CommunityUpdates extends BaseModule {
	private ?IConfigurationProvider $provider = null;
	private ConfigurationProviderFactory $providerFactory;

	/**
	 * @param IContextSource $context
	 * @param Config $wikiConfig
	 * @param ExperimentUserManager $experimentUserManager
	 * @param ConfigurationProviderFactory $providerFactory
	 */
	public function __construct(
		IContextSource $context,
		Config $wikiConfig,
		ExperimentUserManager $experimentUserManager,
		ConfigurationProviderFactory $providerFactory
	) {
		parent::__construct( 'community-updates', $context, $wikiConfig, $experimentUserManager );
		$this->providerFactory = $providerFactory;
	}

	private function initializeProvider() {
		if ( !$this->provider ) {
			$this->provider = $this->providerFactory->newProvider( 'CommunityUpdates' );
		}
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
		return $config->GEHomepageCommunityUpdatesEnabled && isset( $config->GEHomepageCommunityUpdatesContentTitle ) &&
			isset( $config->GEHomepageCommunityUpdatesContentBody );
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
		// TODO: Replace hardcoded text with configurable message as per T367223
		$buttonText = 'Learn More';

		return Html::rawElement( 'div', [ 'class' => 'cdx-card-content' ],
			Html::rawElement( 'div', [
				'class' => 'cdx-card__thumbnail ext-growthExperiments-CommunityUpdates__card__thumbnail' ],
				Html::rawElement( 'div', [
					'class' => 'cdx-thumbnail__image ext-growthExperiments-CommunityUpdates__thumbnail__image'
				] )
			) .
			Html::rawElement( 'div', [ 'class' => 'cdx-card__text' ],
				Html::rawElement( 'div', [ 'class' => 'cdx-card__text__title' ], $contentTitle ) .
				Html::rawElement( 'div', [ 'class' => 'cdx-card__text__description' ], $contentBody ) .
				Html::rawElement(
					'button', [ 'class' => 'cdx-button cdx-button--action-progressive' ], $buttonText )
			)
		);
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
