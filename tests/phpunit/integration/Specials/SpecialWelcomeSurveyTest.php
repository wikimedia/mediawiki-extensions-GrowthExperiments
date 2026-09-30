<?php

namespace GrowthExperiments\Tests\Integration;

use GrowthExperiments\EventLogging\WelcomeSurveyLogger;
use GrowthExperiments\FeatureManager;
use GrowthExperiments\GrowthExperimentsServices;
use GrowthExperiments\Specials\SpecialWelcomeSurvey;
use GrowthExperiments\WelcomeSurvey;
use MediaWiki\Extension\TestKitchen\Sdk\ExperimentInterface;
use MediaWiki\Extension\TestKitchen\Sdk\ExperimentManager;
use MediaWiki\Json\FormatJson;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Tests\Specials\SpecialPageTestBase;
use MediaWiki\Utils\MWTimestamp;
use PHPUnit\Framework\MockObject\Rule\InvocationOrder;
use Psr\Log\NullLogger;
use Wikimedia\Rdbms\IDBAccessObject;

/**
 * @coversDefaultClass \GrowthExperiments\Specials\SpecialWelcomeSurvey
 * @group Database
 */
class SpecialWelcomeSurveyTest extends SpecialPageTestBase {
	private ?ExperimentManager $experimentManager = null;

	/**
	 * @inheritDoc
	 */
	protected function newSpecialPage() {
		$services = $this->getServiceContainer();
		$growthExperimentsServices = GrowthExperimentsServices::wrap( $services );
		return new SpecialWelcomeSurvey(
			$services->getSpecialPageFactory(),
			$growthExperimentsServices->getWelcomeSurveyFactory(),
			new WelcomeSurveyLogger( new NullLogger() ),
			$growthExperimentsServices->getFeatureManager(),
			$this->experimentManager
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

	/**
	 * @covers ::execute
	 */
	public function testSendsExposureWhenFormIsShownToControlGroup(): void {
		$this->markTestSkippedIfExtensionNotLoaded( 'TestKitchen' );
		$this->mockEarlyOnboardingControl( true );
		$this->experimentManager = $this->newExperimentManagerExpectingExposures( $this->once() );

		$this->executeSpecialPage( '', null, 'en', $this->getTestUser()->getUser() );
	}

	/**
	 * @covers ::execute
	 */
	public function testDoesNotSendExposureWhenFormIsPosted(): void {
		$this->markTestSkippedIfExtensionNotLoaded( 'TestKitchen' );
		$this->mockEarlyOnboardingControl( true );
		$this->experimentManager = $this->newExperimentManagerExpectingExposures( $this->never() );

		$request = new FauxRequest( [ 'reason' => 'placeholder' ], true );
		$this->executeSpecialPage( '', $request, 'en', $this->getMutableTestUser()->getUser() );
	}

	/**
	 * @covers ::execute
	 */
	public function testDoesNotSendExposureOutsideControlGroup(): void {
		$this->markTestSkippedIfExtensionNotLoaded( 'TestKitchen' );
		$this->mockEarlyOnboardingControl( false );
		$this->experimentManager = $this->newExperimentManagerExpectingExposures( $this->never() );

		$this->executeSpecialPage( '', null, 'en', $this->getTestUser()->getUser() );
	}

	private function mockEarlyOnboardingControl( bool $isControl ): void {
		$featureManager = $this->createMock( FeatureManager::class );
		$featureManager->method( 'isEarlyOnboardingExperimentControl' )->willReturn( $isControl );
		$this->setService( 'GrowthExperimentsFeatureManager', $featureManager );
	}

	private function newExperimentManagerExpectingExposures(
		InvocationOrder $expectedExposures
	): ExperimentManager {
		$experiment = $this->createMock( ExperimentInterface::class );
		$experiment->expects( $expectedExposures )->method( 'sendExposure' );
		$experimentManager = $this->createMock( ExperimentManager::class );
		$experimentManager->method( 'getExperiment' )->willReturn( $experiment );
		return $experimentManager;
	}

}
