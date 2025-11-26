<?php

namespace GrowthExperiments\Tests\Integration;

use GrowthExperiments\Api\ApiHelpPanelPostQuestion;
use GrowthExperiments\GrowthExperimentsServices;
use GrowthExperiments\Mentorship\IMentorManager;
use MediaWiki\Api\ApiUsageException;
use MediaWiki\Extension\CommunityConfiguration\Tests\CommunityConfigurationTestHelpers;
use MediaWiki\MainConfigNames;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Status\Status;
use MediaWiki\Tests\Api\ApiTestCase;
use MediaWiki\Title\Title;
use MediaWiki\User\User;
use Wikimedia\Rdbms\IDBAccessObject;

/**
 * @group API
 * @group Database
 * @group medium
 *
 * @covers \GrowthExperiments\Api\ApiHelpPanelPostQuestion
 */
class ApiHelpPanelQuestionPosterTest extends ApiTestCase {
	use CommunityConfigurationTestHelpers;

	/**
	 * @var User
	 */
	protected $mUser = null;

	protected function setUp(): void {
		parent::setUp();
		$this->mUser = $this->getMutableTestUser()->getUser();
		$this->overrideConfigValues( [
			MainConfigNames::EnableEmail => true,
		] );
		$this->overrideProviderConfig( [
			'GEHelpPanelHelpDeskTitle' => 'HelpDeskTest',
		], 'HelpPanel' );
		$this->editPage( 'HelpDeskTest', 'Content' );
	}

	protected function getParams( $body, $source, $relevanttitle = '' ) {
		$params = [
			'action' => 'helppanelquestionposter',
			ApiHelpPanelPostQuestion::API_PARAM_BODY => $body,
			'source' => $source,
		];
		if ( $relevanttitle ) {
			$params += [ ApiHelpPanelPostQuestion::API_PARAM_RELEVANT_TITLE => $relevanttitle ];
		}
		return $params;
	}

	/**
	 * @covers \GrowthExperiments\Api\ApiHelpPanelPostQuestion::execute
	 */
	public function testExecute() {
		$ret = $this->doApiRequestWithToken(
			$this->getParams( 'lorem ipsum', 'helpdesk' ),
			null,
			$this->mUser,
			'csrf'
		);
		$this->assertSame( 'success', $ret[0]['helppanelquestionposter']['result'] );
		$this->assertSame( 1, $ret[0]['helppanelquestionposter']['isfirstedit'] );
		$this->assertGreaterThan( 0, $ret[0]['helppanelquestionposter'] );
	}

	public function testExecuteWhenUserNotLoggedInThrowsAnException() {
		try {
			$this->doApiRequestWithToken(
				$this->getParams( 'lorem ipsum', 'helpdesk' ),
				null,
				new User(),
				'csrf'
			);
		} catch ( ApiUsageException $e ) {
			$this->assertSame(
				'You must be logged in to ask a question.',
				$e->getMessage() );
		}
	}

	public function testExecuteWhenThereAreNoMentorsThrowsAnApiUsageException() {
		$geServices = GrowthExperimentsServices::wrap( $this->getServiceContainer() );
		$mentorProvider = $geServices->getMentorProvider();

		$this->assertCount( 0, $mentorProvider->getMentors() );

		try {
			$this->doApiRequestWithToken(
			$this->getParams( 'lorem ipsum', 'mentor-helppanel' ),
			null,
			$this->mUser,
			'csrf' );
		} catch ( ApiUsageException $e ) {
			$this->assertSame(
				'You cannot ask a question to your mentor if you do not have a mentor.',
				$e->getMessage() );
		}
	}

	public function testExecuteWhenThereAreMentorsButUserOptedOutOfMentorshipThrowsAnException() {
		$geServices = GrowthExperimentsServices::wrap( $this->getServiceContainer() );
		$mentor = $this->getTestUser()->getUser();
		$mentorProvider = $geServices->getMentorProvider();
		$mentorWriter = $geServices->getMentorWriter();
		$mentorManager = $geServices->getMentorManager();
		$mentorWriter->addMentor(
			$mentorProvider->newMentorFromUserIdentity( $mentor ),
			$mentor,
			'adding a mentor who has no mentees assigned'
		);
		$mentorManager->setMentorshipStateForUser( $this->mUser,
			IMentorManager::MENTORSHIP_OPTED_OUT );

		$this->assertCount( 1, $mentorProvider->getMentors() );

		try {
			$this->doApiRequestWithToken(
				$this->getParams( 'lorem ipsum', 'mentor-helppanel' ),
				null,
				$this->mUser,
				'csrf' );
		} catch ( ApiUsageException $e ) {
			$this->assertSame(
				'You cannot ask a question to your mentor if you do not have a mentor.',
				$e->getMessage() );
		}
	}

	public function testExecuteForMentorHelpPanelWhenUserHasNoMentorAssignsAMentor() {
		$geServices = GrowthExperimentsServices::wrap( $this->getServiceContainer() );
		$mentorUser = $this->getTestUser()->getUser();
		$mentorProvider = $geServices->getMentorProvider();
		$mentorWriter = $geServices->getMentorWriter();
		$mentorManager = $geServices->getMentorManager();
		$mentor = $mentorProvider->newMentorFromUserIdentity( $mentorUser );
		$mentorWriter->addMentor(
			$mentor,
			$mentorUser,
			'adding a mentor who has no mentees assigned'
		);
		$this->assertNull( $mentorManager->getMentorForUserIfExists( $this->mUser ) );

		$this->doApiRequestWithToken(
			$this->getParams( 'lorem ipsum', 'mentor-helppanel' ),
			null,
			$this->mUser,
			'csrf' );
		$this->assertNotNull( $mentorManager->getMentorForUserIfExists( $this->mUser ) );
		$this->assertEquals( $mentorUser->mId, $mentorManager->getMentorForUserIfExists(
			$this->mUser )->getUserIdentity()->getId()
		);
	}

	public function testValidRelevantTitle() {
		$this->editPage( 'Real', 'Content' );
		$ret = $this->doApiRequestWithToken(
			$this->getParams( 'a', 'helpdesk', 'Real' ),
			null,
			$this->mUser,
			'csrf'
		);
		$this->assertSame( 'success', $ret[0]['helppanelquestionposter']['result'] );
	}

	/**
	 * @covers \GrowthExperiments\HelpPanel\QuestionPoster\QuestionPoster::checkUserPermissions
	 */
	public function testBlockedUserCantPostQuestion() {
		$this->getServiceContainer()->getDatabaseBlockStore()
			->insertBlockWithParams( [
				'targetUser' => $this->mUser,
				'by' => $this->getTestSysop()->getUser(),
			] );

		$this->expectException( ApiUsageException::class );
		$this->expectExceptionMessage( 'Your username or IP address has been blocked' );

		$this->doApiRequestWithToken(
			$this->getParams( 'user is blocked', 'helpdesk' ),
			null,
			$this->mUser,
			'csrf'
		);
	}

	/**
	 * @covers \GrowthExperiments\HelpPanel\QuestionPoster\QuestionPoster::runEditFilterMergedContentHook
	 */
	public function testEditFilterMergedContentHookReturnsFalse() {
		$this->setTemporaryHook( 'EditFilterMergedContent',
			static function ( $unused1, $unused2, Status $status ) {
				$status->setOK( false );
				return false;
			}
		);

		$this->expectException( ApiUsageException::class );

		$this->doApiRequestWithToken(
			$this->getParams( 'abuse filter denies edit', 'helpdesk' ),
			null,
			$this->mUser,
			'csrf'
		);
	}

	/**
	 * @covers \GrowthExperiments\HelpPanel\QuestionPoster\QuestionPoster::getTargetTitle
	 */
	public function testRedirectTarget() {
		$this->editPage( 'HelpDeskTest', '#REDIRECT [[HelpDeskTest2]]' );
		Title::clearCaches();
		$ret = $this->doApiRequestWithToken(
			$this->getParams( 'lorem ipsum', 'helpdesk' ),
			null,
			$this->mUser,
			'csrf'
		);
		$this->assertSame( 'success', $ret[0]['helppanelquestionposter']['result'] );
		$revisionId = $ret[0]['helppanelquestionposter']['revision'];
		$revision = $this->getServiceContainer()->getRevisionLookup()->getRevisionById(
			$revisionId, IDBAccessObject::READ_LATEST );
		$this->assertInstanceOf( RevisionRecord::class, $revision );
		$this->assertSame( 'HelpDeskTest2', $revision->getPageAsLinkTarget()->getDBkey() );
	}

}
