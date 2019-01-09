<?php

namespace GrowthExperiments\Tests;

use GrowthExperiments\HelpPanel;
use HashConfig;
use MediaWikiTestCase;

/**
 * Class HelpPanelTest
 *
 * @group medium
 */
class HelpPanelTest extends MediaWikiTestCase {

	/**
	 * @covers \GrowthExperiments\HelpPanel::getHelpDeskTitle
	 */
	public function testGetHelpDeskTitle() {
		$this->setMwGlobals( 'wgSitename', 'TestMediaWiki' );
		$config = new HashConfig( [
			'GEHelpPanelHelpDeskTitle' => 'HelpDesk/{{SITENAME}}'
		] );

		$title = HelpPanel::getHelpDeskTitle( $config );

		$this->assertEquals( 'HelpDesk/TestMediaWiki', $title->getText() );
		$this->assertTrue( $title->isValid(), 'Title is valid' );
		$this->assertFalse( $title->exists(), 'Title does not exist' );
	}
}
