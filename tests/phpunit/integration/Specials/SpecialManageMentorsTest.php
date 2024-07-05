<?php

namespace GrowthExperiments\Tests\Integration;

use ExtensionRegistry;
use GrowthExperiments\GrowthExperimentsServices;
use GrowthExperiments\MentorDashboard\MentorTools\MentorStatusManager;
use GrowthExperiments\Mentorship\Store\MentorStore;
use GrowthExperiments\Specials\SpecialManageMentors;
use MediaWiki\Request\FauxRequest;
use MediaWiki\User\UserIdentity;
use PermissionsError;
use ReflectionMethod;
use SpecialPage;
use SpecialPageTestBase;
use Wikimedia\Timestamp\ConvertibleTimestamp;

/**
 * @coversDefaultClass \GrowthExperiments\Specials\SpecialManageMentors
 * @group Database
 */
class SpecialManageMentorsTest extends SpecialPageTestBase {

	/** @var UserIdentity */
	private $mentorUser;

	/**
	 * @inheritDoc
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->setMainCache( CACHE_NONE );

		// add one mentor to the system
		$geServices = GrowthExperimentsServices::wrap( $this->getServiceContainer() );
		$this->mentorUser = $this->getMutableTestUser()->getUser();
		$mentor = $geServices->getMentorProvider()
			->newMentorFromUserIdentity( $this->mentorUser );
		$mentor->setIntroText( 'this is intro' );
		$this->assertStatusGood( $geServices->getMentorWriter()
			->addMentor(
				$mentor,
				$this->mentorUser,
				'Test'
			)
		);

		// assign a mentee to the mentor
		$geServices->getMentorStore()->setMentorForUser(
			$this->getMutableTestUser()->getUserIdentity(),
			$this->mentorUser,
			MentorStore::ROLE_PRIMARY
		);
	}

	/**
	 * @inheritDoc
	 */
	protected function newSpecialPage() {
		$geServices = GrowthExperimentsServices::wrap( $this->getServiceContainer() );
		$mainConfig = $this->getServiceContainer()->getMainConfig();

		return new SpecialManageMentors(
			$this->getServiceContainer()->getUserIdentityLookup(),
			$this->getServiceContainer()->getUserEditTracker(),
			$geServices->getMentorProvider(),
			$geServices->getMentorWriter(),
			$geServices->getMentorStatusManager(),
			$geServices->getMentorRemover(),
			$mainConfig
		);
	}

	/**
	 * @covers ::execute
	 * @covers ::getMentorsTableBody
	 * @covers ::getMentorsTable
	 * @covers ::getMentorAsHtmlRow
	 */
	public function testNotAuthorizedRead() {
		[ $html, ] = $this->executeSpecialPage();
		$this->assertStringContainsString(
			$this->mentorUser->getName(),
			$html
		);
		$this->assertStringContainsString(
			'this is intro',
			$html
		);
		$this->assertStringNotContainsStringIgnoringCase(
			'growthexperiments-manage-mentors-remove',
			$html
		);
		$this->assertStringNotContainsStringIgnoringCase(
			'growthexperiments-manage-mentors-edit',
			$html
		);
	}

	/**
	 * @covers ::execute
	 * @covers ::getMentorsTableBody
	 * @covers ::getMentorsTable
	 * @covers ::getMentorAsHtmlRow
	 */
	public function testAuthorizedRead() {
		$performer = $this->getTestSysop()->getUser();
		[ $html, ] = $this->executeSpecialPage( '', null, null, $performer );
		$this->assertStringContainsStringIgnoringCase(
			$this->mentorUser->getName(),
			$html
		);
		$this->assertStringContainsStringIgnoringCase(
			'this is intro',
			$html
		);
		$this->assertStringContainsStringIgnoringCase(
			'growthexperiments-manage-mentors-remove',
			$html
		);
		$this->assertStringContainsStringIgnoringCase(
			'growthexperiments-manage-mentors-edit',
			$html
		);
	}

	/**
	 * @covers ::execute
	 * @covers ::handleAction
	 * @covers ::getFormByAction
	 * @covers ::parseSubpage
	 */
	public function testNotAuthorizedRemoveMentor() {
		$this->expectException( PermissionsError::class );
		$this->expectExceptionMessage( 'The action you have requested is limited to users in the group' );

		$mentorProvider = GrowthExperimentsServices::wrap( $this->getServiceContainer() )
			->getMentorProvider();

		$this->assertTrue( $mentorProvider->isMentor( $this->mentorUser ) );
		$this->executeSpecialPage(
			'remove-mentor/' . $this->mentorUser->getId(),
			new FauxRequest( [ 'wpreason' => 'foo' ], true )
		);
		$this->assertTrue( $mentorProvider->isMentor( $this->mentorUser ) );
	}

	/**
	 * @covers ::execute
	 * @covers ::handleAction
	 * @covers ::getFormByAction
	 * @covers ::parseSubpage
	 * @covers \GrowthExperiments\Specials\Forms\ManageMentorsRemoveMentor::onSubmit
	 * @covers \GrowthExperiments\Specials\Forms\ManageMentorsRemoveMentor::onSuccess
	 */
	public function testAuthorizedRemoveMentor() {
		$geServices = GrowthExperimentsServices::wrap( $this->getServiceContainer() );
		$mentorProvider = $geServices->getMentorProvider();
		$mentorStore = $geServices->getMentorStore();

		$this->assertTrue( $mentorProvider->isMentor( $this->mentorUser ) );
		$this->assertTrue( $mentorStore->hasAnyMentees(
			$this->mentorUser,
			MentorStore::ROLE_PRIMARY
		) );
		[ $html, ] = $this->executeSpecialPage(
			'remove-mentor/' . $this->mentorUser->getId(),
			new FauxRequest( [ 'wpreason' => 'foo' ], true ),
			null,
			$this->getTestSysop()->getUser()
		);

		$this->getServiceContainer()->getJobRunner()->run( [
			'type' => 'reassignMenteesJob',
			'maxJobs' => 1,
			'maxTime' => 1,
		] );

		$this->assertStringContainsString(
			'growthexperiments-manage-mentors-remove-mentor-success',
			$html
		);
		$this->assertFalse( $mentorProvider->isMentor( $this->mentorUser ) );

		// run any mentee reassignment jobs and ensure former mentor has no mentees left
		$this->getServiceContainer()->getJobRunner()->run( [
			'type' => 'reassignMenteesJob',
			'maxJobs' => 1,
			'maxTime' => 3,
		] );
		$this->assertFalse( $mentorStore->hasAnyMentees(
			$this->mentorUser,
			MentorStore::ROLE_PRIMARY
		) );
		$this->assertFalse( $mentorStore->hasAnyMentees( $this->mentorUser, MentorStore::ROLE_PRIMARY ) );
	}

	/**
	 * @covers ::execute
	 * @covers ::handleAction
	 * @covers ::getFormByAction
	 * @covers ::parseSubpage
	 * @covers \GrowthExperiments\Specials\Forms\ManageMentorsEditMentor::onSubmit
	 * @covers \GrowthExperiments\Specials\Forms\ManageMentorsEditMentor::onSuccess
	 */
	public function testAuthorizedEditMentor() {
		$mentorProvider = GrowthExperimentsServices::wrap( $this->getServiceContainer() )
			->getMentorProvider();

		$this->assertStringContainsString(
			'this is intro',
			$mentorProvider->newMentorFromUserIdentity( $this->mentorUser )->getIntroText()
		);
		[ $html, ] = $this->executeSpecialPage(
			'edit-mentor/' . $this->mentorUser->getId(),
			new FauxRequest( [
				'wpmessage' => 'new intro',
				'wpautomaticallyAssigned' => 1,
				'wpweight' => 2,
				'wpreason' => 'foo',
				'wpisAway' => 0,
			], true ),
			null,
			$this->getTestSysop()->getUser()
		);
		$this->assertStringContainsString(
			'growthexperiments-manage-mentors-edit-success',
			$html
		);
		$this->assertStringContainsString(
			'new intro',
			$mentorProvider->newMentorFromUserIdentity( $this->mentorUser )->getIntroText()
		);
	}

	/**
	 * @covers ::execute
	 * @covers ::handleAction
	 * @covers ::getFormByAction
	 * @covers ::parseSubpage
	 * @covers \GrowthExperiments\Specials\Forms\ManageMentorsEditMentor::onSubmit
	 * @covers \GrowthExperiments\Specials\Forms\ManageMentorsEditMentor::onSuccess
	 */
	public function testAuthorizedEditMentorMarkAway() {
		$mentorStatusManager = GrowthExperimentsServices::wrap( $this->getServiceContainer() )
			->getMentorStatusManager();

		ConvertibleTimestamp::setFakeTime( strtotime( '2011-04-01T12:00Z' ) );

		$this->assertEquals(
			MentorStatusManager::STATUS_ACTIVE,
			$mentorStatusManager->getMentorStatus( $this->mentorUser )
		);
		$this->assertNull( $mentorStatusManager->getMentorBackTimestamp( $this->mentorUser ) );
		$this->executeSpecialPage(
			'edit-mentor/' . $this->mentorUser->getId(),
			new FauxRequest( [
				'wpmessage' => 'new intro',
				'wpautomaticallyAssigned' => 1,
				'wpweight' => 2,
				'wpreason' => 'foo',
				'wpisAway' => 1,
				'wpawayTimestamp' => '2011-05-01T12:00Z',
			], true ),
			null,
			$this->getTestSysop()->getUser()
		);
		$this->assertSame(
			MentorStatusManager::STATUS_AWAY,
			$mentorStatusManager->getMentorStatus( $this->mentorUser )
		);
		$this->assertSame(
			'20110501120000',
			$mentorStatusManager->getMentorBackTimestamp( $this->mentorUser )
		);
	}

	/**
	 * Data provider for testDisplayMentorshipWarningMessage
	 *
	 * @return array
	 */
	public function configProvider(): array {
		return [
			[ false, SpecialPage::getTitleFor( 'EditGrowthConfig' )->getPrefixedText() ],
			[ true, SpecialPage::getTitleFor(
				'CommunityConfiguration', 'Mentorship' )->getPrefixedText() ]
		];
	}

	/**
	 * @dataProvider configProvider
	 * @covers ::displayMentorshipWarningMessage
	 */
	public function testDisplayMentorshipWarningMessage( $useCommunityConfig, $expectedConfigPage ) {
		$this->setMwGlobals( [
			'wgGEMentorshipEnabled' => false,
			'wgGEUseCommunityConfiguration' => $useCommunityConfig
		] );
		$extensionRegistry = $this->getMockBuilder( ExtensionRegistry::class )
			->disableOriginalConstructor()
			->getMock();
		// Simulate CC extension is loaded
		$extensionRegistry->method( 'isLoaded' )
			->willReturn( true );
		$this->overrideConfigValue( 'GEUseCommunityConfigurationExtension', $useCommunityConfig );
		$this->getMockBuilder( GrowthExperimentsServices::class )
			->disableOriginalConstructor()
			->getMock();
		$specialPage = $this->newSpecialPage();
		$reflectionMethod = new ReflectionMethod(
			SpecialManageMentors::class, 'displayMentorshipWarningMessage' );
		$reflectionMethod->setAccessible( true );
		$result = $reflectionMethod->invoke( $specialPage );

		$expectedMessage = wfMessage( 'growthexperiments-mentor-dashboard-mentorship-disabled-with-link' )
			->params( $expectedConfigPage )
			->parse();

		$this->assertIsString( $result );
		$this->assertStringContainsString( 'cdx-message cdx-message--block cdx-message--warning', $result );
		$this->assertStringContainsString( $expectedMessage, $result );
		$this->assertStringContainsString( $expectedConfigPage, $result );
	}
}
