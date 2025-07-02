<?php

namespace GrowthExperiments\Tests\Integration;

use GrowthExperiments\GrowthExperimentsServices;
use GrowthExperiments\HelpPanel\QuestionPoster\HelpdeskQuestionPoster;
use GrowthExperiments\HelpPanel\QuestionPoster\HomepageMentorQuestionPoster;
use GrowthExperiments\HomepageModules\Mentorship;
use GrowthExperiments\MentorDashboard\MentorTools\IMentorWeights;
use GrowthExperiments\Mentorship\Mentor;
use GrowthExperiments\Mentorship\StaticMentorManager;
use GrowthExperiments\Mentorship\Store\MentorStore;
use MediaWiki\Api\ApiUsageException;
use MediaWiki\Context\DerivativeContext;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Tests\Api\ApiTestCase;

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
		foreach ( [ Mentorship::QUESTION_PREF, HelpdeskQuestionPoster::QUESTION_PREF ] as $storage ) {
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
		$services = $this->getServiceContainer();
		$geServices = GrowthExperimentsServices::wrap( $services );
		$geServices->getMentorStore()
			->setMentorForUser(
				$user,
				$mentor,
				MentorStore::ROLE_PRIMARY
			);
		$request = new FauxRequest( [], true );
		$context = new DerivativeContext( $this->apiContext );
		$context->setRequest( $request );
		$context->setUser( $user );

		$questionPoster = new HomepageMentorQuestionPoster(
			$services->getWikiPageFactory(),
			$services->getTitleFactory(),
			new StaticMentorManager( [
				$user->getName() => new Mentor( $mentor, '', '', IMentorWeights::WEIGHT_NORMAL ),
			] ),
			$geServices->getMentorStatusManager(),
			$services->getPermissionManager(),
			$services->getStatsFactory(),
			ExtensionRegistry::getInstance()->isLoaded( 'ConfirmEdit' ),
			ExtensionRegistry::getInstance()->isLoaded( 'Flow' ),
			$context,
			'foo'
		);

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
