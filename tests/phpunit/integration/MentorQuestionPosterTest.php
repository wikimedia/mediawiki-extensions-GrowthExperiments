<?php

namespace GrowthExperiments\Tests;

use DerivativeContext;
use ExtensionRegistry;
use GrowthExperiments\HelpPanel\QuestionPoster\MentorQuestionPoster;
use GrowthExperiments\MentorDashboard\MentorTools\IMentorWeights;
use GrowthExperiments\Mentorship\Mentor;
use GrowthExperiments\Mentorship\MentorManager;
use MediaWiki\MediaWikiServices;
use MediaWikiIntegrationTestCase;
use RequestContext;
use Wikimedia\TestingAccessWrapper;

/**
 * @group medium
 * @group Database
 */
class MentorQuestionPosterTest extends MediaWikiIntegrationTestCase {

	/**
	 * @covers \GrowthExperiments\HelpPanel\QuestionPoster\QuestionPoster::__construct
	 */
	public function testConstruct() {
		$wikiPageFactory = MediaWikiServices::getInstance()->getWikiPageFactory();
		$titleFactory = MediaWikiServices::getInstance()->getTitleFactory();
		$permissionManager = MediaWikiServices::getInstance()->getPermissionManager();
		$mentorManager = $this->createMock( MentorManager::class );
		$mentorUser = $this->getTestSysop()->getUser();
		$mentor = new Mentor( $mentorUser, '*', '', true, IMentorWeights::WEIGHT_NORMAL );
		$mentorManager->method( 'getMentorForUser' )->willReturn( $mentor );
		$mentorManager->method( 'getMentorForUserSafe' )->willReturn( $mentor );
		$mentorManager->method( 'getEffectiveMentorForUser' )->willReturn( $mentor );
		$mentorManager->method( 'getEffectiveMentorForUserSafe' )->willReturn( $mentor );
		$context = new DerivativeContext( RequestContext::getMain() );
		$context->setUser( $this->getTestUser()->getUser() );

		$module = $this->getMockBuilder( MentorQuestionPoster::class )
			->setConstructorArgs( [
				$wikiPageFactory,
				$titleFactory,
				$mentorManager,
				$permissionManager,
				MediaWikiServices::getInstance()->getPerDbNameStatsdDataFactory(),
				ExtensionRegistry::getInstance()->isLoaded( 'ConfirmEdit' ),
				ExtensionRegistry::getInstance()->isLoaded( 'Flow' ),
				$context,
				'foo'
			] )
			->getMockForAbstractClass();
		$spy = TestingAccessWrapper::newFromObject( $module );
		$this->assertTrue( $mentorUser->getTalkPage()->equals( $spy->targetTitle ) );
	}

}
