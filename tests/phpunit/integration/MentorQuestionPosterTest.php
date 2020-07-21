<?php

namespace GrowthExperiments\Tests;

use DerivativeContext;
use GrowthExperiments\HelpPanel\QuestionPoster\MentorQuestionPoster;
use GrowthExperiments\Mentor;
use MediaWikiTestCase;
use RequestContext;
use Wikimedia\TestingAccessWrapper;

/**
 * @group medium
 * @group Database
 */
class MentorQuestionPosterTest extends MediaWikiTestCase {

	/**
	 * @throws \MWException
	 * @covers \GrowthExperiments\HelpPanel\QuestionPoster\MentorQuestionPoster::__construct
	 */
	public function testConstruct() {
		$mentor = $this->getTestSysop()->getUser();
		$context = $this->buildContext( $mentor->getId() );

		$module = new MentorQuestionPoster( $context, 'foo' );
		$spy = TestingAccessWrapper::newFromObject( $module );
		$this->assertEquals( $mentor->getTalkPage(), $spy->targetTitle );
	}

	private function buildContext( $mentorId ) {
		$context = new DerivativeContext( RequestContext::getMain() );
		$user = $this->getMutableTestUser()->getUser();
		$context->setUser( $user );
		$user->setOption( Mentor::MENTOR_PREF, $mentorId );
		$user->saveSettings();
		return $context;
	}

}
