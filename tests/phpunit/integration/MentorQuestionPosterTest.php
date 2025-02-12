<?php

namespace GrowthExperiments\Tests\Integration;

use GrowthExperiments\HelpPanel\QuestionPoster\MentorQuestionPoster;
use GrowthExperiments\MentorDashboard\MentorTools\IMentorWeights;
use GrowthExperiments\Mentorship\IMentorManager;
use GrowthExperiments\Mentorship\Mentor;
use MediaWiki\Context\DerivativeContext;
use MediaWiki\Context\RequestContext;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWikiIntegrationTestCase;
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
		$wikiPageFactory = $this->getServiceContainer()->getWikiPageFactory();
		$titleFactory = $this->getServiceContainer()->getTitleFactory();
		$permissionManager = $this->getServiceContainer()->getPermissionManager();
		$mentorManager = $this->createMock( IMentorManager::class );
		$mentorUser = $this->getTestSysop()->getUser();
		$mentor = new Mentor( $mentorUser, '*', '', IMentorWeights::WEIGHT_NORMAL );
		$mentorManager->method( 'getMentorForUserSafe' )->willReturn( $mentor );
		$mentorManager->method( 'getEffectiveMentorForUserSafe' )->willReturn( $mentor );
		$context = new DerivativeContext( RequestContext::getMain() );
		$context->setUser( $this->getTestUser()->getUser() );

		$module = $this->getMockBuilder( MentorQuestionPoster::class )
			->setConstructorArgs( [
				$wikiPageFactory,
				$titleFactory,
				$mentorManager,
				$permissionManager,
				$this->getServiceContainer()->getStatsFactory(),
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
