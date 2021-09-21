<?php

namespace GrowthExperiments\Tests;

use DerivativeContext;
use GrowthExperiments\HelpPanel\QuestionPoster\MentorQuestionPoster;
use GrowthExperiments\Mentorship\Mentor;
use GrowthExperiments\Mentorship\MentorManager;
use MediaWiki\MediaWikiServices;
use MediaWikiTestCase;
use PHPUnit\Framework\MockObject\MockObject;
use RequestContext;
use Wikimedia\TestingAccessWrapper;

/**
 * @group medium
 * @group Database
 */
class MentorQuestionPosterTest extends MediaWikiTestCase {

	/**
	 * @throws \MWException
	 * @covers \GrowthExperiments\HelpPanel\QuestionPoster\QuestionPoster::__construct
	 */
	public function testConstruct() {
		$wikiPageFactory = MediaWikiServices::getInstance()->getWikiPageFactory();
		$titleFactory = MediaWikiServices::getInstance()->getTitleFactory();
		$permissionManager = MediaWikiServices::getInstance()->getPermissionManager();
		$mentorManager = $this->getMockMentorManager();
		$mentorUser = $this->getTestSysop()->getUser();
		$mentor = new Mentor( $mentorUser, '*' );
		$mentorManager->method( 'getMentorForUser' )->willReturn( $mentor );
		$mentorManager->method( 'getMentorForUserSafe' )->willReturn( $mentor );
		$context = new DerivativeContext( RequestContext::getMain() );
		$context->setUser( $this->getTestUser()->getUser() );

		$module = $this->getMockBuilder( MentorQuestionPoster::class )
			->setConstructorArgs( [
				$wikiPageFactory,
				$titleFactory,
				$mentorManager,
				$permissionManager,
				$context,
				'foo'
			] )
			->getMockForAbstractClass();
		$spy = TestingAccessWrapper::newFromObject( $module );
		$this->assertTrue( $mentorUser->getTalkPage()->equals( $spy->targetTitle ) );
	}

	/**
	 * @return MockObject|MentorManager
	 */
	private function getMockMentorManager(): MentorManager {
		return $this->getMockBuilder( MentorManager::class )
			->getMockForAbstractClass();
	}

}
