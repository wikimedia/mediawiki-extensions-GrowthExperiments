<?php

namespace GrowthExperiments\Tests;

use Config;
use GrowthExperiments\WelcomeSurvey;
use HashConfig;
use MediaWiki\Languages\LanguageNameUtils;
use MediaWiki\User\UserOptionsManager;
use MediaWikiUnitTestCase;
use RequestContext;
use User;

/**
 * @coversDefaultClass \GrowthExperiments\WelcomeSurvey
 */
class WelcomeSurveyTest extends MediaWikiUnitTestCase {

	/**
	 * @covers ::__construct
	 */
	public function testConstruct() {
		$contextMock = $this->createMock( RequestContext::class );
		$contextMock->expects( $this->once() )
			->method( 'getConfig' )
			->willReturn( $this->createMock( Config::class ) );
		$this->assertInstanceOf(
			WelcomeSurvey::class,
			new WelcomeSurvey(
				$contextMock,
				$this->getLanguageNameUtilsMockObject(),
				$this->createNoOpMock( UserOptionsManager::class )
			)
		);
	}

	/**
	 * @covers ::__construct
	 * @covers ::getQuestions
	 */
	public function testAllowFreetextResponsesTrue() {
		$welcomeSurvey = $this->getWelcomeSurveyForFreetextTest( true );
		$questions = $welcomeSurvey->getQuestions( 'exp2_target_specialpage' );
		$this->assertArrayHasKey( 'reason-other', $questions );
	}

	/**
	 * @covers ::__construct
	 * @covers ::getQuestions
	 */
	public function testAllowFreetextResponsesFalse() {
		$welcomeSurvey = $this->getWelcomeSurveyForFreetextTest( false );
		$questions = $welcomeSurvey->getQuestions( 'exp2_target_specialpage' );
		$this->assertArrayNotHasKey( 'reason-other', $questions );
	}

	private function getWelcomeSurveyForFreetextTest( $allowFreetext ) {
		$configMock = new HashConfig( [
			'WelcomeSurveyAllowFreetextResponses' => $allowFreetext,
			'WelcomeSurveyExperimentalGroups' => [ 'exp2_target_specialpage' => [
				"range" => "x",
				"format" => "specialpage",
				"questions" => [
					"reason",
					"edited",
					"email",
					"mentor-info",
					"mentor"
				] ] ]
		] );
		$contextMock = $this->createMock( RequestContext::class );
		$contextMock->method( 'getConfig' )
			->willReturn( $configMock );
		$userMock = $this->createMock( User::class );
		$contextMock->expects( $this->atLeastOnce() )
			->method( 'getUser' )
			->willReturn( $userMock );
		$contextMock->expects( $this->atLeastOnce() )
			->method( 'msg' )
			->willReturn( $this->getMockMessage( 'welcomesurvey-question-mailinglist-help' ) );
		return new WelcomeSurvey(
			$contextMock,
			$this->getLanguageNameUtilsMockObject(),
			$this->createNoOpMock( UserOptionsManager::class )
		);
	}

	/**
	 * @return \PHPUnit\Framework\MockObject\MockObject|LanguageNameUtils
	 */
	private function getLanguageNameUtilsMockObject() {
		$mock = $this->createMock( LanguageNameUtils::class );
		$mock->method( 'getLanguageNames' )
			->willReturn( [ 'es', 'el', 'en', 'ar' ] );
		return $mock;
	}

}
