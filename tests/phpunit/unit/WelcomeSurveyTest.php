<?php

namespace GrowthExperiments\Tests\Unit;

use GrowthExperiments\WelcomeSurvey;
use MediaWiki\Config\HashConfig;
use MediaWiki\Context\DerivativeContext;
use MediaWiki\Context\RequestContext;
use MediaWiki\Language\LanguageNameUtils;
use MediaWiki\User\Options\UserOptionsManager;
use MediaWiki\User\Registration\UserRegistrationLookup;
use MediaWiki\User\User;
use MediaWiki\User\UserIdentity;
use MediaWikiUnitTestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Wikimedia\Timestamp\ConvertibleTimestamp;

/**
 * @coversDefaultClass \GrowthExperiments\WelcomeSurvey
 */
class WelcomeSurveyTest extends MediaWikiUnitTestCase {

	/**
	 * @covers ::__construct
	 * @covers ::getQuestions
	 */
	public function testAllowFreetextResponsesTrue() {
		$welcomeSurvey = $this->getWelcomeSurveyForFreetextTest( true );
		$questions = $welcomeSurvey->getQuestions( 'control' );
		$this->assertArrayHasKey( 'reason-other', $questions );
	}

	/**
	 * @covers ::__construct
	 * @covers ::getQuestions
	 */
	public function testAllowFreetextResponsesFalse() {
		$welcomeSurvey = $this->getWelcomeSurveyForFreetextTest( false );
		$questions = $welcomeSurvey->getQuestions( 'control' );
		$this->assertArrayNotHasKey( 'reason-other', $questions );
	}

	private function getWelcomeSurveyForFreetextTest( $allowFreetext ) {
		$configMock = new HashConfig( [
			'WelcomeSurveyAllowFreetextResponses' => $allowFreetext,
			'WelcomeSurveyExperimentalGroups' => [ 'control' => [
				"format" => "specialpage",
				"questions" => [
					"reason",
					"edited",
					"email",
				] ] ],
			'WelcomeSurveyPrivacyStatementUrl' => 'http://privacy.link',
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
			$this->createNoOpMock( UserOptionsManager::class ),
			$this->createNoOpMock( UserRegistrationLookup::class ),
			false
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
		$registrationDates = [];
		$mockUserRegistrationLookup = $this->createNoOpMock( UserRegistrationLookup::class, [ 'getRegistration' ] );
		$mockUserRegistrationLookup->method( 'getRegistration' )
			->willReturnCallback(
				static function ( UserIdentity $user ) use ( &$registrationDates ) {
					return $registrationDates[$user->getName()] ?? null;
				}
			);
		$welcomeSurvey = new WelcomeSurvey(
			$context,
			$this->getLanguageNameUtilsMockObject(),
			$mockUserOptionsManager,
			$mockUserRegistrationLookup,
			false
		);

		// register
		$context->setUser( $this->getMockUser( $registrationDate, $registrationDates ) );
		ConvertibleTimestamp::setFakeTime( $registrationDate );
		$welcomeSurvey->saveGroup( 'someSurvey' );
		ConvertibleTimestamp::setFakeTime( $now );
		$this->assertTrue( $welcomeSurvey->isUnfinished() );

		// fill the survey
		$welcomeSurvey->handleResponses( [], true, 'someSurvey', $now );
		$this->assertFalse( $welcomeSurvey->isUnfinished() );

		// register another user
		$context->setUser( $this->getMockUser( $registrationDate, $registrationDates ) );
		ConvertibleTimestamp::setFakeTime( $registrationDate );
		$welcomeSurvey->saveGroup( 'someSurvey' );
		ConvertibleTimestamp::setFakeTime( $now );
		$this->assertTrue( $welcomeSurvey->isUnfinished() );

		// dismiss the survey
		$welcomeSurvey->dismiss();
		$this->assertFalse( $welcomeSurvey->isUnfinished() );

		// expired
		$longAgoRegistrationDate = '2021-10-01 08:00:00';
		$context->setUser( $this->getMockUser( $longAgoRegistrationDate, $registrationDates ) );
		ConvertibleTimestamp::setFakeTime( $registrationDate );
		$welcomeSurvey->saveGroup( 'someSurvey' );
		ConvertibleTimestamp::setFakeTime( $now );
		$this->assertFalse( $welcomeSurvey->isUnfinished() );

		// ancient user
		$longAgoRegistrationDate = null;
		$context->setUser( $this->getMockUser( $longAgoRegistrationDate, $registrationDates ) );
		ConvertibleTimestamp::setFakeTime( $registrationDate );
		$welcomeSurvey->saveGroup( 'someSurvey' );
		ConvertibleTimestamp::setFakeTime( $now );
		$this->assertFalse( $welcomeSurvey->isUnfinished() );

		// no data
		$context->setUser( $this->getMockUser( $registrationDate, $registrationDates ) );
		$this->assertFalse( $welcomeSurvey->isUnfinished() );

		// control group
		$context->setUser( $this->getMockUser( $registrationDate, $registrationDates ) );
		ConvertibleTimestamp::setFakeTime( $registrationDate );
		$welcomeSurvey->saveGroup( 'controlSurvey' );
		ConvertibleTimestamp::setFakeTime( $now );
		$this->assertFalse( $welcomeSurvey->isUnfinished() );

		// invalid group
		$context->setUser( $this->getMockUser( $registrationDate, $registrationDates ) );
		ConvertibleTimestamp::setFakeTime( $registrationDate );
		$welcomeSurvey->saveGroup( 'noSuchSurvey' );
		ConvertibleTimestamp::setFakeTime( $now );
		$this->assertFalse( $welcomeSurvey->isUnfinished() );
	}

	/**
	 * @param string|null $registrationDate Registration date in any wfTimestamp format.
	 * @return User
	 */
	private function getMockUser(
		?string $registrationDate,
		array &$registrationDates
	): User {
		static $counter = 1;
		$userName = 'TestUser' . $counter++;
		/** @var User|MockObject $mockUser */
		$mockUser = $this->createNoOpMock( User::class,
			[ 'getName', 'getInstanceFromPrimary' ] );
		$mockUser->method( 'getName' )->willReturn( $userName );
		$mockUser->method( 'getInstanceFromPrimary' )->willReturnSelf();

		$registrationDates[$userName] = $registrationDate;

		return $mockUser;
	}

}
