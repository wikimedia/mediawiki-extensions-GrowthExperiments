<?php

use GrowthExperiments\Api\ApiHelpPanelPostQuestion;

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
			'wgGEHelpPanelHelpDeskTitle' => 'HelpDeskTest'
		] );
		$this->editPage( 'HelpDeskTest', 'Content' );
	}

	protected function getParams( $body, $email = '', $relevanttitle = '' ) {
		$params = [
			'action' => 'helppanelquestionposter',
			ApiHelpPanelPostQuestion::API_PARAM_BODY => $body,
		];
		if ( $email ) {
			$params += [ ApiHelpPanelPostQuestion::API_PARAM_EMAIL => $email ];
		}
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

	public function testHandleNoEmail() {
		$params = [
			'action' => 'helppanelquestionposter',
			'body' => 'lorem ipsum',
			'email' => 'blah@blahblah.com'
		];

		$this->mUser->setEmail( '' );
		$ret = $this->doApiRequestWithToken( $params, null, $this->mUser, 'csrf' );
		$this->assertArraySubset( [
			'result' => 'success',
			'email' => 'set_email_with_confirmation'
		], $ret[0]['helppanelquestionposter'] );

		// no email -> no email.
		$params = [
			'action' => 'helppanelquestionposter',
			'body' => 'lorem ipsum',
			'email' => ''
		];

		$this->mUser->setEmail( '' );
		$ret = $this->doApiRequestWithToken( $params, null, $this->mUser, 'csrf' );
		$this->assertArraySubset( [
			'result' => 'success',
			'email' => 'no_op'
		], $ret[0]['helppanelquestionposter'] );
	}

	public function testInvalidEmail() {
		$params = [
			'action' => 'helppanelquestionposter',
			'body' => 'lorem ipsum',
			'email' => '123'
		];
		$this->mUser->setEmail( 'a@b.com' );
		$ret = $this->doApiRequestWithToken( $params, null, $this->mUser, 'csrf' );
		$this->assertArraySubset( [
			'result' => 'success',
			'email' => 'Insufficient permissions to set email.'
		], $ret[0]['helppanelquestionposter'] );
	}

	public function testBlankEmailFromUnconfirmedEmail() {
		$params = [
			'action' => 'helppanelquestionposter',
			'body' => 'lorem ipsum',
			'email' => ''
		];

		$this->mUser->setEmail( 'a@b.com' );
		$this->assertEquals( 'a@b.com', $this->mUser->getEmail() );
		$ret = $this->doApiRequestWithToken( $params, null, $this->mUser, 'csrf' );
		$this->assertArraySubset( [
				'result' => 'success',
				'email' => 'unset_email'
		], $ret[0]['helppanelquestionposter'] );
		$this->assertEquals( '', $this->mUser->getEmail() );
	}

	public function testHandleUnconfirmedEmail() {
		$this->mUser->setEmail( 'a@b.com' );
		$ret = $this->doApiRequestWithToken(
			$this->getParams( 'lorem ipsum', 'blah@blah.com' ),
			null,
			$this->mUser,
			'csrf'
		);
		$this->assertArraySubset( [
				'result' => 'success',
				'email' => 'set_email_with_confirmation'
			], $ret[0]['helppanelquestionposter'] );

		$ret = $this->doApiRequestWithToken(
			$this->getParams( 'blah', 'change@again.com' ),
			null,
			$this->mUser,
			'csrf'
		);
		$this->assertArraySubset( [
				'result' => 'success',
				'email' => 'set_email_with_confirmation'
			], $ret[0]['helppanelquestionposter'] );

		$this->mUser->setEmail( 'a@b.com' );
		$ret = $this->doApiRequestWithToken(
			$this->getParams( 'blah', 'a@b.com' ),
			null,
			$this->mUser,
			'csrf'
		);
		$this->assertArraySubset( [
				'result' => 'success',
				'email' => 'send_confirm'
			], $ret[0]['helppanelquestionposter'] );
	}

	public function testHandleConfirmedEmail() {
		// User attempts to change confirmed email.
		$this->mUser->setEmailAuthenticationTimestamp( wfTimestamp() );
		$ret = $this->doApiRequestWithToken(
			$this->getParams( 'lorem', 'shouldthrow@error.com' ),
			null,
			$this->mUser,
			'csrf'
		);
		$this->assertArraySubset(
			[ 'email' => 'Insufficient permissions to set email.' ],
			$ret[0]['helppanelquestionposter']
		);
		// No change with confirmed email.
		// User attempts to change confirmed email.
		$this->mUser->setEmail( 'a@b.com' );
		$this->mUser->setEmailAuthenticationTimestamp( wfTimestamp() );
		$ret = $this->doApiRequestWithToken(
			$this->getParams( 'lorem', 'a@b.com' ),
			null,
			$this->mUser,
			'csrf'
		);
		$this->assertArraySubset(
			[ 'email' => 'already_confirmed' ],
			$ret[0]['helppanelquestionposter']
		);

		// User attempts to blank confirmed email.
		$this->mUser->setEmail( 'a@b.com' );
		$this->mUser->setEmailAuthenticationTimestamp( wfTimestamp() );
		$ret = $this->doApiRequestWithToken(
			$this->getParams( 'lorem', '' ),
			null,
			$this->mUser,
			'csrf'
		);
		$this->assertArraySubset(
			[ 'email' => 'Insufficient permissions to set email.' ],
			$ret[0]['helppanelquestionposter']
		);
	}

	public function testValidRelevantTitle() {
		$this->editPage( 'Real', 'Content' );
		$ret = $this->doApiRequestWithToken(
			$this->getParams( 'a', null, 'Real' ),
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
	 * @expectedException ApiUsageException
	 * @expectedExceptionMessageRegExp /Your username or IP address has been blocked/
	 */
	public function testBlockedUserCantPostQuestion() {
		$block = new Block();
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
