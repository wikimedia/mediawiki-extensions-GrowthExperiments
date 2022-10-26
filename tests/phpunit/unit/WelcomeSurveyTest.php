<?php

namespace GrowthExperiments\Tests;

use Config;
use DerivativeContext;
use GrowthExperiments\WelcomeSurvey;
use HashConfig;
use MediaWiki\Languages\LanguageNameUtils;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserOptionsManager;
use MediaWikiUnitTestCase;
use PHPUnit\Framework\MockObject\MockObject;
use RequestContext;
use User;
use Wikimedia\Timestamp\ConvertibleTimestamp;

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

	/**
	 * @covers ::isUnfinished
	 */
	public function testIsUnfinished() {
		$now = '2022-10-01 10:00:00';
		$registrationDate = '2022-10-01 08:00:00';

		$context = new DerivativeContext( RequestContext::getMain() );
		$context->setConfig( new HashConfig( [
			'WelcomeSurveyReminderExpiry' => 100,
			'WelcomeSurveyExperimentalGroups' => [
				'someSurvey' => [ 'questions' => [ 'question' ] ],
				'controlSurvey' => [ 'questions' => [] ],
			],
			'WelcomeSurveyAllowFreetextResponses' => false,
		] ) );

		$userOptions = [];
		/** @var UserOptionsManager|MockObject $mockUserOptionsManager */
		$mockUserOptionsManager = $this->createNoOpMock( UserOptionsManager::class,
			[ 'getOption', 'setOption', 'saveOptions' ] );
		$mockUserOptionsManager->method( 'getOption' )->willReturnCallback(
			static function ( UserIdentity $user, string $option ) use ( &$userOptions ) {
				// WelcomeSurvey::loadSurveyData uses a default override of ''.
				return $userOptions[$user->getName()][$option] ?? '';
			}
		);
		$mockUserOptionsManager->method( 'setOption' )->willReturnCallback(
			static function ( UserIdentity $user, string $option, $value ) use ( &$userOptions ): void {
				$userOptions[$user->getName()][$option] = $value;
			}
		);
		$welcomeSurvey = new WelcomeSurvey(
			$context,
			$this->getLanguageNameUtilsMockObject(),
			$mockUserOptionsManager
		);

		// register
		$context->setUser( $this->getMockUser( $registrationDate ) );
		ConvertibleTimestamp::setFakeTime( $registrationDate );
		$welcomeSurvey->saveGroup( 'someSurvey' );
		ConvertibleTimestamp::setFakeTime( $now );
		$this->assertTrue( $welcomeSurvey->isUnfinished() );

		// fill the survey
		$welcomeSurvey->handleResponses( [], true, 'someSurvey', $now );
		$this->assertFalse( $welcomeSurvey->isUnfinished() );

		// register another user
		$context->setUser( $this->getMockUser( $registrationDate ) );
		ConvertibleTimestamp::setFakeTime( $registrationDate );
		$welcomeSurvey->saveGroup( 'someSurvey' );
		ConvertibleTimestamp::setFakeTime( $now );
		$this->assertTrue( $welcomeSurvey->isUnfinished() );

		// dismiss the survey
		$welcomeSurvey->dismiss();
		$this->assertFalse( $welcomeSurvey->isUnfinished() );

		// expired
		$longAgoRegistrationDate = '2021-10-01 08:00:00';
		$context->setUser( $this->getMockUser( $longAgoRegistrationDate ) );
		ConvertibleTimestamp::setFakeTime( $registrationDate );
		$welcomeSurvey->saveGroup( 'someSurvey' );
		ConvertibleTimestamp::setFakeTime( $now );
		$this->assertFalse( $welcomeSurvey->isUnfinished() );

		// ancient user
		$longAgoRegistrationDate = null;
		$context->setUser( $this->getMockUser( $longAgoRegistrationDate ) );
		ConvertibleTimestamp::setFakeTime( $registrationDate );
		$welcomeSurvey->saveGroup( 'someSurvey' );
		ConvertibleTimestamp::setFakeTime( $now );
		$this->assertFalse( $welcomeSurvey->isUnfinished() );

		// no data
		$context->setUser( $this->getMockUser( $registrationDate ) );
		$this->assertFalse( $welcomeSurvey->isUnfinished() );

		// control group
		$context->setUser( $this->getMockUser( $registrationDate ) );
		ConvertibleTimestamp::setFakeTime( $registrationDate );
		$welcomeSurvey->saveGroup( 'controlSurvey' );
		ConvertibleTimestamp::setFakeTime( $now );
		$this->assertFalse( $welcomeSurvey->isUnfinished() );

		// invalid group
		$context->setUser( $this->getMockUser( $registrationDate ) );
		ConvertibleTimestamp::setFakeTime( $registrationDate );
		$welcomeSurvey->saveGroup( 'noSuchSurvey' );
		ConvertibleTimestamp::setFakeTime( $now );
		$this->assertFalse( $welcomeSurvey->isUnfinished() );
	}

	/**
	 * @param string|null $registrationDate Registration date in any wfTimestamp format.
	 * @return User
	 */
	private function getMockUser( ?string $registrationDate ): User {
		static $counter = 1;
		/** @var User|MockObject $mockUser */
		$mockUser = $this->createNoOpMock( User::class,
			[ 'getName', 'getRegistration', 'getInstanceForUpdate' ] );
		$mockUser->method( 'getName' )->willReturn( 'TestUser' . $counter++ );
		$mockUser->method( 'getRegistration' )->willReturnCallback( static function () use ( $registrationDate ) {
			return wfTimestampOrNull( TS_MW, $registrationDate );
		} );
		$mockUser->method( 'getInstanceForUpdate' )->willReturnSelf();
		return $mockUser;
	}

}
