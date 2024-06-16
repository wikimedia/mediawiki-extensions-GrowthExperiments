<?php

namespace GrowthExperiments\Tests\Integration;

use GrowthExperiments\GrowthExperimentsServices;
use GrowthExperiments\HomepageHooks;
use GrowthExperiments\Mentorship\Store\MentorStore;
use MediaWiki\Json\FormatJson;
use MediaWiki\Permissions\Authority;
use MediaWiki\Title\Title;
use MediaWiki\User\User;
use MediaWiki\User\UserIdentity;
use MediaWikiIntegrationTestCase;
use MockHttpTrait;
use MWHttpRequest;
use StatusValue;

/**
 * @group Database
 * @group medium
 * @coversDefaultClass \GrowthExperiments\MentorDashboard\PersonalizedPraise\PraiseworthyMenteeSuggester
 */
class PraiseworthyMenteeSuggesterIntegrationTest extends MediaWikiIntegrationTestCase {
	use MockHttpTrait;

	/**
	 * @param UserIdentity $mentor
	 * @param int $numOfMentees
	 * @return User[]
	 */
	private function getNMentees( UserIdentity $mentor, int $numOfMentees ): array {
		$userOptionsManager = $this->getServiceContainer()->getUserOptionsManager();
		$mentorStore = GrowthExperimentsServices::wrap( $this->getServiceContainer() )
			->getMentorStore();

		$mentees = [];
		for ( $i = 0; $i < $numOfMentees; $i++ ) {
			// needs to be User for makeNEdits
			$mentee = $this->getMutableTestUser()->getUser();
			$mentorStore->setMentorForUser( $mentee, $mentor, MentorStore::ROLE_PRIMARY );
			$mentorStore->markMenteeAsActive( $mentee );

			// enable Homepage for the user (to ensure ImpactHooks updates impact in database)
			$userOptionsManager->setOption( $mentee, HomepageHooks::HOMEPAGE_PREF_ENABLE, 1 );
			$userOptionsManager->saveOptions( $mentee );

			$mentees[] = $mentee;
		}
		return $mentees;
	}

	private function makeNEdits( Authority $performer, int $edits ): void {
		for ( $i = 0; $i < $edits; $i++ ) {
			$this->editPage(
				Title::newFromText( sprintf( 'Sandbox %d', $i ) ),
				sprintf( 'test %s %d', $performer->getUser()->getName(), $i ),
				'',
				NS_MAIN,
				$performer
			);
		}
	}

	private function mockPageviews() {
		$mwHttpRequest = $this->createMock( MWHttpRequest::class );
		$mwHttpRequest->method( 'execute' )
			->willReturn( new StatusValue() );
		$mwHttpRequest->method( 'getContent' )
			->willReturn( FormatJson::encode( [ 'items' => [] ] ) );
		$this->installMockHttp( $mwHttpRequest );
	}

	/**
	 * @covers ::getPraiseworthyMenteesForMentorUncached
	 */
	public function testGetPraiseworthyMenteesForMentor() {
		$minEdits = 2;
		$this->mockPageviews();
		$this->setMwGlobals( [
			'wgGEPersonalizedPraiseBackendEnabled' => true,
			'wgGEPersonalizedPraiseMinEdits' => $minEdits,
		] );

		$mentor = $this->getTestSysop()->getUserIdentity();
		[ $praiseworthyMentee, $otherMentee ] = $this->getNMentees( $mentor, 2 );

		$this->makeNEdits( $praiseworthyMentee, $minEdits );
		$this->makeNEdits( $otherMentee, $minEdits - 1 );

		$suggester = GrowthExperimentsServices::wrap( $this->getServiceContainer() )
			->getPraiseworthyMenteeSuggester();
		$this->assertCount( 1, $suggester->getPraiseworthyMenteesForMentorUncached( $mentor ) );
	}

}
