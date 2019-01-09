<?php

namespace GrowthExperiments;

use ResourceLoader;
use ResourceLoaderContext;
use ResourceLoaderFileModule;

class ResourceLoaderHelpPanelModule extends ResourceLoaderFileModule {
	/**
	 * @inheritDoc
	 */
	public function getScript( ResourceLoaderContext $context ) {
		return ResourceLoader::makeConfigSetScript( [
				'wgGEHelpPanelLinks' => HelpPanel::getHelpPanelLinks( $context, $context->getConfig() ),
				'wgGEHelpPanelHelpDeskTitle' => HelpPanel::getHelpDeskTitle( $context->getConfig() )
			] )
			. "\n"
			. parent::getScript( $context );
	}

	/**
	 * @inheritDoc
	 */
	public function supportsURLLoading() {
		return false;
	}

	/**
	 * @inheritDoc
	 */
	public function enableModuleContentVersion() {
		return true;
	}
}
