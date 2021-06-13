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
		$configMock = $this->getMockBuilder( Config::class )
			->disableOriginalConstructor()
			->onlyMethods( [ 'get', 'has' ] )
			->getMock();
		$contextMock = $this->getMockBuilder( RequestContext::class )
			->disableOriginalConstructor()
			->getMock();
		$contextMock->expects( $this->once() )
			->method( 'getConfig' )
			->willReturn( $configMock );
		$this->assertInstanceOf(
			WelcomeSurvey::class,
			new WelcomeSurvey(
				$contextMock,
				$this->getLanguageNameUtilsMockObject(),
				$this->getUserOptionsManagerMockObject()
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
		$contextMock = $this->getMockBuilder( RequestContext::class )
			->disableOriginalConstructor()
			->getMock();
		$contextMock->method( 'getConfig' )
			->willReturn( $configMock );
		$userMock = $this->getMockBuilder( User::class )
			->disableOriginalConstructor()
			->getMock();
		$contextMock->expects( $this->atLeastOnce() )
			->method( 'getUser' )
			->willReturn( $userMock );
		return new WelcomeSurvey(
			$contextMock,
			$this->getLanguageNameUtilsMockObject(),
			$this->getUserOptionsManagerMockObject()
		);
	}

	/**
	 * @return \PHPUnit\Framework\MockObject\MockObject|LanguageNameUtils
	 */
	private function getLanguageNameUtilsMockObject() {
		$mock = $this->getMockBuilder( LanguageNameUtils::class )
			->disableOriginalConstructor()
			->getMock();
		$mock->method( 'getLanguageNames' )
			->willReturn( [ 'es', 'el', 'en', 'ar' ] );
		return $mock;
	}

	/**
	 * @return \PHPUnit\Framework\MockObject\MockObject|UserOptionsManager
	 */
	private function getUserOptionsManagerMockObject() {
		return $this->createNoOpMock( UserOptionsManager::class );
	}

}
