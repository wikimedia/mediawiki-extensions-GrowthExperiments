<?php

namespace GrowthExperiments\Tests\Integration;

use GrowthExperiments\EventLogging\WelcomeSurveyLogger;
use GrowthExperiments\GrowthExperimentsServices;
use GrowthExperiments\Specials\SpecialWelcomeSurvey;
use GrowthExperiments\WelcomeSurvey;
use MediaWiki\Json\FormatJson;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Utils\MWTimestamp;
use Psr\Log\NullLogger;
use SpecialPageTestBase;
use Wikimedia\Rdbms\IDBAccessObject;

/**
 * @coversDefaultClass \GrowthExperiments\Specials\SpecialWelcomeSurvey
 * @group Database
 */
class SpecialWelcomeSurveyTest extends SpecialPageTestBase {
	/**
	 * @inheritDoc
	 */
	protected function newSpecialPage() {
		$services = $this->getServiceContainer();
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
		$userOptionsLookup = $this->getServiceContainer()->getUserOptionsLookup();
		$userOptionsManager = $this->getServiceContainer()->getUserOptionsManager();
		$fakeTime = '20200505120000';
		$userOptionsManager->setOption( $user, WelcomeSurvey::SURVEY_PROP, FormatJson::encode( [
			'_group' => 'NONE',
			'_render_date' => $fakeTime,
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
			// TODO: it should be "control"
			'_group' => null,
			// TODO: 'reason' should be set to placeholder
			'_submit_date' => $fakeTime,
			'_render_date' => null,
			'_counter' => 1,
		], $surveyAnswer );
	}

}
