<?php

namespace GrowthExperiments\Tests\Integration;

use GrowthExperiments\GrowthExperimentsServices;
use GrowthExperiments\HomepageHooks;
use GrowthExperiments\Mentorship\Store\MentorStore;
use MediaWiki\Json\FormatJson;
use MediaWiki\Title\Title;
use MediaWikiIntegrationTestCase;
use MockHttpTrait;
use MWHttpRequest;
use StatusValue;

/**
 * @group medium
 * @group Database
 * @coversDefaultClass \GrowthExperiments\MentorDashboard\PersonalizedPraise\PersonalizedPraiseHooks
 */
class PersonalizedPraiseHooksTest extends MediaWikiIntegrationTestCase {
	use MockHttpTrait;

	private function mockPageviews() {
		$mwHttpRequest = $this->createMock( MWHttpRequest::class );
		$mwHttpRequest->method( 'execute' )
			->willReturn( new StatusValue() );
		$mwHttpRequest->method( 'getContent' )
			->willReturn( FormatJson::encode( [ 'items' => [] ] ) );
		$this->installMockHttp( $mwHttpRequest );
	}

	/**
	 * @covers ::onPageSaveComplete
	 * @covers \GrowthExperiments\MentorDashboard\PersonalizedPraise\PraiseworthyMenteeSuggester::getPraiseworthyMenteesForMentor
	 * @covers \GrowthExperiments\MentorDashboard\PersonalizedPraise\PraiseworthyMenteeSuggester::refreshPraiseworthyMenteesForMentor
	 */
	public function testCachePopulates() {
		$this->mockPageviews();
		$this->setMwGlobals( [
			'wgGEPersonalizedPraiseBackendEnabled' => true,
			'wgGEPersonalizedPraiseMinEdits' => 1,
		] );

		$geServices = GrowthExperimentsServices::wrap( $this->getServiceContainer() );
		$store = $geServices->getMentorStore();
		$suggester = $geServices->getPraiseworthyMenteeSuggester();
		$userOptionsManager = $this->getServiceContainer()->getUserOptionsManager();

		$mentor = $this->getMutableTestUser()->getUserIdentity();
		$menteeTestUser = $this->getTestUser();
		$store->setMentorForUser( $menteeTestUser->getUserIdentity(), $mentor, MentorStore::ROLE_PRIMARY );
		$userOptionsManager->setOption( $menteeTestUser->getUserIdentity(), HomepageHooks::HOMEPAGE_PREF_ENABLE, 1 );
		$userOptionsManager->saveOptions( $menteeTestUser->getUserIdentity() );

		$this->assertCount( 0, $suggester->getPraiseworthyMenteesForMentor( $mentor ) );
		$this->editPage(
			Title::newFromText( 'Sandbox' ),
			'testing',
			'',
			NS_MAIN,
			$menteeTestUser->getAuthority()
		);
		$this->assertCount( 1, $suggester->getPraiseworthyMenteesForMentor( $mentor ) );
	}
}
