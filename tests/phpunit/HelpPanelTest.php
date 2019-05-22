<?php

namespace GrowthExperiments\Tests;

use GrowthExperiments\HelpPanel;
use HashConfig;
use MediaWiki\MediaWikiServices;
use MediaWikiTestCase;

/**
 * @group medium
 */
class HelpPanelTest extends MediaWikiTestCase {

	/**
	 * @covers \GrowthExperiments\HelpPanel::getHelpDeskTitle
	 */
	public function testGetHelpDeskTitle() {
		$sitename = MediaWikiServices::getInstance()->getMainConfig()->get( 'Sitename' );
		$config = new HashConfig( [
			'GEHelpPanelHelpDeskTitle' => 'HelpDesk/{{SITENAME}}'
		] );

		$title = HelpPanel::getHelpDeskTitle( $config );

		$this->assertSame( "HelpDesk/$sitename", $title->getText() );
		$this->assertTrue( $title->isValid(), 'Title is valid' );
		$this->assertFalse( $title->exists(), 'Title does not exist' );
	}
}
