<?php

namespace GrowthExperiments\Specials;

use MediaWiki\SpecialPage\SpecialPage;

/**
 * An implementation for Special:EditGrowthConfig when CommunityConfiguration extension is enabled
 */
class SpecialEditGrowthConfigRedirect extends SpecialPage {

	public function __construct() {
		parent::__construct( 'EditGrowthConfig', '', false );
	}

	/**
	 * @inheritDoc
	 */
	public function execute( $subPage ) {
		parent::execute( $subPage );

		$this->getOutput()->redirect(
			$this->getSpecialPageFactory()
				->getTitleForAlias( 'CommunityConfiguration' )
				->getLinkURL()
		);
	}
}
