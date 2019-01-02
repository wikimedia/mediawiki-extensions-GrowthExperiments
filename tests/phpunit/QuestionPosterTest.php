<?php

namespace GrowthExperiments\Tests;

use Config;
use DerivativeContext;
use GrowthExperiments\HelpPanel\QuestionPoster;
use MediaWikiTestCase;
use RequestContext;
use Status;
use Title;

/**
 * Class QuestionPosterTest
 *
 * @group medium
 * @group Database
 */
class QuestionPosterTest extends MediaWikiTestCase {

	/**
	 * @throws \MWException
	 */
	public function setUp() {
		parent::setUp();
		$this->insertPage( 'HelpDeskTest', '' );
	}

	/**
	 * @return \PHPUnit\Framework\MockObject\MockObject
	 */
	private function getConfigMock() {
		$configMock = $this->getMockBuilder( Config::class )
			->getMock();
		$configMock->expects( $this->any() )
			->method( 'get' )
			->with( 'GEHelpPanelHelpDeskTitle' )
			->willReturn( 'HelpDeskTest' );
		return $configMock;
	}

	/**
	 * @throws \MWException
	 * @throws \ConfigException
	 * @group Database
	 * @covers \GrowthExperiments\HelpPanel\QuestionPoster::submit
	 */
	public function testSubmit() {
		$context = new DerivativeContext( RequestContext::getMain() );
		$context->setConfig( $this->getConfigMock() );
		$questionPoster = new QuestionPoster( $context );
		$questionPoster->submit( 'a great question' );
		$revision = $questionPoster->getRevisionId();
		$this->assertGreaterThan( 0, $revision );
		$page = new \WikiPage( Title::newFromText( 'HelpDeskTest' ) );
		$this->assertEquals(
			true,
			strpos( $page->getContent()->getSection( 1 )->serialize(), 'a great question' ) !== false
		);
	}

	/**
	 * @covers \GrowthExperiments\HelpPanel\QuestionPoster::validateRelevantTitle
	 * @throws \ConfigException
	 * @throws \MWException
	 */
	public function testValidateRelevantTitle() {
		$this->insertPage( 'sample' );
		$questionPoster = new QuestionPoster( RequestContext::getMain() );
		$this->assertEquals(
			Status::newGood(),
			$questionPoster->validateRelevantTitle( 'sample' )
		);
		$this->assertEquals(
			Status::newFatal( 'growthexperiments-help-panel-questionposter-invalid-title' ),
			$questionPoster->validateRelevantTitle( '>123' )
		);
	}

}
