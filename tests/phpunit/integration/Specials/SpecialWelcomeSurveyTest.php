<?php

namespace GrowthExperiments\Tests;

use FauxRequest;
use FormatJson;
use GrowthExperiments\EventLogging\WelcomeSurveyLogger;
use GrowthExperiments\GrowthExperimentsServices;
use GrowthExperiments\Specials\SpecialWelcomeSurvey;
use GrowthExperiments\WelcomeSurvey;
use IDBAccessObject;
use MediaWiki\MediaWikiServices;
use MWTimestamp;
use Psr\Log\NullLogger;
use SpecialPageTestBase;

/**
 * @coversDefaultClass \GrowthExperiments\Specials\SpecialWelcomeSurvey
 */
class SpecialWelcomeSurveyTest extends SpecialPageTestBase {

	/**
	 * @inheritDoc
	 */
	protected function newSpecialPage() {
		$services = MediaWikiServices::getInstance();
		$growthExperimentsServices = GrowthExperimentsServices::wrap( $services );
		return new SpecialWelcomeSurvey(
			$services->getSpecialPageFactory(),
			$growthExperimentsServices->getWelcomeSurveyFactory(),
			new WelcomeSurveyLogger( new NullLogger() )
		);
	}

	/**
	 * @covers ::execute
	 * @covers ::onSubmit
	 * @throws \Exception
	 */
	public function testStoreResponsesForUserWithNONEgroup() {
		$user = $this->getMutableTestUser()->getUser();
		$userOptionsLookup = MediaWikiServices::getInstance()->getUserOptionsLookup();
		$userOptionsManager = MediaWikiServices::getInstance()->getUserOptionsManager();
		$fakeTime = '20200505120000';
		$userOptionsManager->setOption( $user, WelcomeSurvey::SURVEY_PROP, FormatJson::encode( [
			'_group' => 'NONE',
			'_render_date' => $fakeTime
		] ) );

		$params = [
			'reason' => 'placeholder',
			'wpedited' => 'placeholder',
			'wpemail' => '',
			'wplanguages' => [ 'en' ],
		];
		$request = new FauxRequest( $params, true );
		$fakeTime = '20200505120000';
		MWTimestamp::setFakeTime( $fakeTime );
		$this->executeSpecialPage( '', $request, 'en', $user );
		$surveyAnswer = FormatJson::decode( $userOptionsLookup->getOption(
			$user,
			WelcomeSurvey::SURVEY_PROP,
			null,
			false,
			IDBAccessObject::READ_LATEST
		), true );
		$this->assertArrayEquals( [
			'_skip' => true,
			// TODO: it should be "exp2_target_specialpage"
			'_group' => null,
			// TODO: 'reason' should be set to placeholder
			'_submit_date' => $fakeTime,
			'_render_date' => null,
			'_counter' => 1
		], $surveyAnswer );
	}

}
