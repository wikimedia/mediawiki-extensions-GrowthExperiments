<?php

declare( strict_types = 1 );

namespace GrowthExperiments\Tests\Integration;

use GrowthExperiments\GrowthExperimentsServices;
use GrowthExperiments\HomepageModules\SuggestedEdits;
use MediaWiki\Context\RequestContext;
use MediaWikiIntegrationTestCase;

/**
 * @covers \GrowthExperiments\NewcomerTasks\NewcomerTasksChangeTagsManager
 * @group Database
 */
class NewcomerTasksChangeTagsManagerTest extends MediaWikiIntegrationTestCase {
	public function testReturnsAddLinkTags(): void {
		$user = $this->getTestUser()->getUser();
		$ctx = RequestContext::getMain();
		$ctx->setUser( $user );
		$userOptionsManager = $this->getServiceContainer()->getUserOptionsManager();
		$userOptionsManager->setOption( $user, SuggestedEdits::ACTIVATED_PREF, true );
		$sut = GrowthExperimentsServices::wrap( $this->getServiceContainer() )->getNewcomerTasksChangeTagsManager();

		$status = $sut->getTags( 'link-recommendation', $user );

		$this->assertStatusGood( $status );
		$this->assertSame( [ 'newcomer task', 'newcomer task add link' ], $status->getValue() );
	}

	public function testReturnsAddLinkReadViewTags(): void {
		$user = $this->getTestUser()->getUser();
		$ctx = RequestContext::getMain();
		$ctx->setUser( $user );
		$userOptionsManager = $this->getServiceContainer()->getUserOptionsManager();
		$userOptionsManager->setOption( $user, SuggestedEdits::ACTIVATED_PREF, true );
		$sut = GrowthExperimentsServices::wrap( $this->getServiceContainer() )->getNewcomerTasksChangeTagsManager();

		$status = $sut->getTags( 'link-recommendation', $user, true );

		$this->assertStatusGood( $status );
		$this->assertSame(
			[
				'newcomer task',
				'newcomer task add link',
				'newcomer task read view suggestion',
			],
			$status->getValue()
		);
	}
}
