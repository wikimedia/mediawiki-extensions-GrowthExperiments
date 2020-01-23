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

	public function setUp() : void {
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
		$this->assertSame( 'success', $ret[0]['helppanelquestionposter']['result'] );
		$this->assertSame( 1, $ret[0]['helppanelquestionposter']['isfirstedit'] );
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
		$this->assertSame( 'success', $ret[0]['helppanelquestionposter']['result'] );
	}

	/**
	 * @covers \GrowthExperiments\HelpPanel\QuestionPoster::checkUserPermissions
	 */
	public function testBlockedUserCantPostQuestion() {
		$block = new DatabaseBlock();
		$block->setTarget( $this->mUser );
		$block->setBlocker( $this->getTestSysop()->getUser() );
		$block->insert();

		$this->expectException( MWException::class );
		$this->expectExceptionMessage( 'Your username or IP address has been blocked' );

		$this->doApiRequestWithToken(
			$this->getParams( 'user is blocked' ),
			null,
			$this->mUser,
			'csrf'
		);
	}

	/**
	 * @covers \GrowthExperiments\HelpPanel\QuestionPoster::runEditFilterMergedContentHook
	 */
	public function testEditFilterMergedContentHookReturnsFalse() {
		$this->setTemporaryHook( 'EditFilterMergedContent',
			function ( $unused1, $unused2, Status $status ) {
				$status->setOK( false );
				return false;
			}
		);

		$this->expectException( ApiUsageException::class );

		$this->doApiRequestWithToken(
			$this->getParams( 'abuse filter denies edit' ),
			null,
			$this->mUser,
			'csrf'
		);
	}

}
