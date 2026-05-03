<?php

namespace GrowthExperiments\Tests;

use GrowthExperiments\HelpPanel;
use MediaWiki\Config\HashConfig;
use MediaWikiIntegrationTestCase;

/**
 * @covers \GrowthExperiments\HelpPanel
 */
class HelpPanelIntegrationTest extends MediaWikiIntegrationTestCase {

	public function testGetHelpDeskTitle() {
		$sitename = $this->getServiceContainer()->getMainConfig()->get( 'Sitename' );
		$helpPanel = new HelpPanel(
			new HashConfig( [
				'GEHelpPanelHelpDeskTitle' => 'HelpDesk/{{SITENAME}}',
			] ),
			$this->getServiceContainer()->getLinkRenderer(),
			$this->getServiceContainer()->getUserOptionsLookup()
		);

		$title = $helpPanel->getHelpDeskTitle();
		$title->resetArticleID( 0 );

		$this->assertSame( "HelpDesk/$sitename", $title->getText() );
		$this->assertTrue( $title->isValid(), 'Title is valid' );
	}
}
