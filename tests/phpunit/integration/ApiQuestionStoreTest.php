<?php

namespace GrowthExperiments\Tests;

use ApiTestCase;
use ApiUsageException;
use DerivativeContext;
use FauxRequest;
use GrowthExperiments\HelpPanel\HelpPanelQuestionPoster;
use GrowthExperiments\HelpPanel\MentorshipModuleQuestionPoster;
use GrowthExperiments\HomepageModules\Mentorship;
use GrowthExperiments\Mentor;

/**
 * @group API
 * @group Database
 * @group medium
 * @covers \GrowthExperiments\Api\ApiQuestionStore
 */
class ApiQuestionStoreTest extends ApiTestCase {

	/**
	 * @covers \GrowthExperiments\Api\ApiQuestionStore::getAllowedParams
	 */
	public function testRequiredParams() {
		$this->expectException( ApiUsageException::class );
		$this->expectExceptionMessage( 'The "storage" parameter must be set.' );

		$this->doApiRequest(
			[ 'action' => 'homepagequestionstore' ],
			null,
			$this->getMutableTestUser()->getUser()
		);
	}

	/**
	 * @covers \GrowthExperiments\Api\ApiQuestionStore::execute
	 */
	public function testNoQuestionsResponse() {
		foreach ( [ Mentorship::QUESTION_PREF, HelpPanelQuestionPoster::QUESTION_PREF ] as $storage ) {
			$response = $this->doApiRequest( [
				'action' => 'homepagequestionstore',
				'storage' => $storage,
			], null, $this->getMutableTestUser()->getUser() );
			$this->assertArrayEquals( [
				'html' => '<div class="recent-questions-' . $storage . '"></div>',
				'questions' => []
			], $response[0]['homepagequestionstore'] );
		}
	}

	public function testApiResponseHtmlJson() {
		$user = $this->getMutableTestUser()->getUser();
		$mentor = $this->getTestSysop()->getUser();
		$user->setOption( Mentor::MENTOR_PREF, $mentor->getId() );
		$user->saveSettings();
		$request = new FauxRequest( [], true );
		$context = new DerivativeContext( $this->apiContext );
		$context->setRequest( $request );
		$context->setUser( $user );
		$questionPoster = new MentorshipModuleQuestionPoster( $context, 'foo' );
		$questionPoster->submit();
		$response = $this->doApiRequest(
			[
				'action' => 'homepagequestionstore',
				'storage' => Mentorship::QUESTION_PREF
			],
			null,
			null,
			$user->getInstanceForUpdate()
		);
		$this->assertNotEquals( '', $response[0]['homepagequestionstore']['html'] );
		$question = $response[0]['homepagequestionstore']['questions'][0];
		$this->assertStringContainsString( 'foo', $question['questionText' ] );
		$this->assertFalse( $question['isArchived'] );
		$this->assertTrue( $question['isVisible'] );
	}
}
