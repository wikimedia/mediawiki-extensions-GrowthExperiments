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
				'email' => 'eauth'
			], $ret[0]['helppanelquestionposter'] );
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
				'email' => 'eauth'
			], $ret[0]['helppanelquestionposter'] );

		$ret = $this->doApiRequestWithToken(
			$this->getParams( 'blah', 'change@again.com' ),
			null,
			$this->mUser,
			'csrf'
		);
		$this->assertArraySubset( [
				'result' => 'success',
				'email' => 'eauth'
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
		$this->mUser->setEmailAuthenticationTimestamp( wfTimestamp() );
		$ret = $this->doApiRequestWithToken(
			$this->getParams( 'lorem', 'shouldthrow@error.com' ),
			null,
			$this->mUser,
			'csrf'
		);
		$this->assertNull( $ret[0]['helppanelquestionposter']['email'] );
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

}
