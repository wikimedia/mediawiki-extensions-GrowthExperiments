<?php

use GrowthExperiments\Api\ApiHelpPanelPostQuestion;
use MediaWiki\Block\DatabaseBlock;

/**
 * @group API
 * @group Database
 * @group medium
 *
 * @covers \GrowthExperiments\Api\ApiHelpPanelPostQuestion
 */
class ApiHelpPanelQuestionPosterTest extends ApiTestCase {

	/**
	 * @var User
	 */
	protected $mUser = null;

	public function setUp() {
		parent::setUp();
		$this->mUser = $this->getMutableTestUser()->getUser();
		$this->setMwGlobals( [
			'wgEnableEmail' => true,
			'wgGEHelpPanelHelpDeskTitle' => 'HelpDeskTest',
		] );
		$this->editPage( 'HelpDeskTest', 'Content' );
	}

	protected function getParams( $body, $relevanttitle = '' ) {
		$params = [
			'action' => 'helppanelquestionposter',
			ApiHelpPanelPostQuestion::API_PARAM_BODY => $body,
		];
		if ( $relevanttitle ) {
			$params += [ ApiHelpPanelPostQuestion::API_PARAM_RELEVANT_TITLE => $relevanttitle ];
		}
		return $params;
	}

	/**
	 * @covers \GrowthExperiments\Api\ApiHelpPanelPostQuestion::execute
	 */
	public function testExecute() {
		$ret = $this->doApiRequestWithToken(
			$this->getParams( 'lorem ipsum' ),
			null,
			$this->mUser,
			'csrf'
		);
		$this->assertArraySubset( [
			'result' => 'success',
			'isfirstedit' => true
		], $ret[0]['helppanelquestionposter'] );
		$this->assertGreaterThan( 0, $ret[0]['helppanelquestionposter'] );
	}

	public function testValidRelevantTitle() {
		$this->editPage( 'Real', 'Content' );
		$ret = $this->doApiRequestWithToken(
			$this->getParams( 'a', 'Real' ),
			null,
			$this->mUser,
			'csrf'
		);
		$this->assertArraySubset( [
			'result' => 'success',
		], $ret[0]['helppanelquestionposter'] );
	}

	/**
	 * @covers \GrowthExperiments\HelpPanel\QuestionPoster::checkUserPermissions
	 * @expectedException MWException
	 * @expectedExceptionMessageRegExp /Your username or IP address has been blocked/
	 */
	public function testBlockedUserCantPostQuestion() {
		$block = new DatabaseBlock();
		$block->setTarget( $this->mUser );
		$block->setBlocker( $this->getTestSysop()->getUser() );
		$block->insert();
		$this->doApiRequestWithToken(
			$this->getParams( 'user is blocked' ),
			null,
			$this->mUser,
			'csrf'
		);
	}

	/**
	 * @covers \GrowthExperiments\HelpPanel\QuestionPoster::runEditFilterMergedContentHook
	 * @expectedException ApiUsageException
	 */
	public function testEditFilterMergedContentHookReturnsFalse() {
		$this->setTemporaryHook( 'EditFilterMergedContent',
			function ( $unused1, $unused2, Status $status ) {
				$status->setOK( false );
				return false;
			}
		);
		$this->doApiRequestWithToken(
			$this->getParams( 'abuse filter denies edit' ),
			null,
			$this->mUser,
			'csrf'
		);
	}

}
