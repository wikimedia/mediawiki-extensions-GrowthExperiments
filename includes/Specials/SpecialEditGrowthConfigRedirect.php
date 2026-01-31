<?php

namespace GrowthExperiments\Specials;

use MediaWiki\SpecialPage\UnlistedSpecialPage;

/**
 * An implementation for Special:EditGrowthConfig when CommunityConfiguration extension is enabled
 */
class SpecialEditGrowthConfigRedirect extends UnlistedSpecialPage {

	public function __construct() {
		parent::__construct( 'EditGrowthConfig' );
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
