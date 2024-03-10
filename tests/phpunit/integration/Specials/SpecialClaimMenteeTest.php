<?php

namespace GrowthExperiments\Tests;

use GrowthExperiments\GrowthExperimentsServices;
use GrowthExperiments\Mentorship\Store\MentorStore;
use GrowthExperiments\Specials\SpecialClaimMentee;
use MediaWiki\Config\GlobalVarConfig;
use MediaWiki\MediaWikiServices;
use MediaWiki\Request\FauxRequest;
use MediaWiki\User\User;
use PermissionsError;
use SpecialPageTestBase;

/**
 * @group Database
 * @coversDefaultClass \GrowthExperiments\Specials\SpecialClaimMentee
 */
class SpecialClaimMenteeTest extends SpecialPageTestBase {

	/**
	 * @inheritDoc
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->setMainCache( CACHE_NONE );
	}

	/**
	 * @inheritDoc
	 */
	protected function newSpecialPage() {
		$geServices = GrowthExperimentsServices::wrap( MediaWikiServices::getInstance() );

		return new SpecialClaimMentee(
			$geServices->getMentorProvider(),
			$geServices->getChangeMentorFactory(),
			// This would normally be GrowthExperimentsMultiConfig, but there
			// is no need to test the on-wiki config here
			GlobalVarConfig::newInstance()
		);
	}

	/**
	 * @covers ::userCanExecute
	 */
	public function testNonMentorCantExecute() {
		$this->expectException( PermissionsError::class );
		$user = $this->getTestSysop()->getUser();
		$this->executeSpecialPage( '', null, null, $user );
	}

	/**
	 * @return User
	 */
	private function getTestMentor(): User {
		$geServices = GrowthExperimentsServices::wrap( $this->getServiceContainer() );
		$mentorProvider = $geServices->getMentorProvider();
		$mentorWriter = $geServices->getMentorWriter();

		$mentorUser = $this->getMutableTestUser()->getUser();
		$mentorWriter->addMentor(
			$mentorProvider->newMentorFromUserIdentity( $mentorUser ),
			$mentorUser,
			''
		);
		return $mentorUser;
	}

	private function submitChangeMentor( array $params, User $performer ): array {
		return $this->executeSpecialPage(
			'',
			new FauxRequest( $params + [
				'wpreason' => 'foo reason',
				'wpstage' => 2,
				'wpconfirm' => false,
			], true ),
			null,
			$performer
		);
	}

	/**
	 * @covers ::getFormFields
	 * @covers ::onSubmit
	 * @covers ::onSuccess
	 */
	public function testMentorExecuteOneMentee() {
		$mentorStore = GrowthExperimentsServices::wrap( $this->getServiceContainer() )
			->getMentorStore();

		$mentee = $this->getMutableTestUser()->getUser();
		$mentorStore->dropMenteeRelationship( $mentee );

		$mentorOne = $this->getTestMentor();
		$mentorTwo = $this->getTestMentor();

		// first run of SpecialClaimMentee successfully changes the mentor
		/** @var string $html */
		[ $html, ] = $this->submitChangeMentor( [
			'wpmentees' => $mentee->getName(),
		], $mentorOne );
		$this->assertStringContainsString( 'growthexperiments-homepage-claimmentee-success', $html );
		$this->assertEquals(
			$mentorOne,
			$mentorStore->loadMentorUser( $mentee, MentorStore::ROLE_PRIMARY )
		);

		// second run of SpecialClaimMentee requires confirmation
		/** @var string $html */
		[ $html, ] = $this->submitChangeMentor( [
			'wpmentees' => $mentee->getName(),
		], $mentorTwo );
		$this->assertStringContainsString( 'growthexperiments-homepage-claimmentee-alreadychanged', $html );
		$this->assertEquals(
			$mentorOne,
			$mentorStore->loadMentorUser( $mentee, MentorStore::ROLE_PRIMARY )
		);

		// when confirmed, mentor successfully changed
		/** @var string $html */
		[ $html, ] = $this->submitChangeMentor( [
			'wpmentees' => $mentee->getName(),
			'wpstage' => 3,
			'wpconfirm' => true,
		], $mentorTwo );
		$this->assertStringContainsString( 'growthexperiments-homepage-claimmentee-success', $html );
		$this->assertEquals(
			$mentorTwo,
			$mentorStore->loadMentorUser( $mentee, MentorStore::ROLE_PRIMARY )
		);
	}
}
