<?php

namespace GrowthExperiments\Tests\Integration;

use GrowthExperiments\Maintenance\DeleteOldSurveys;
use GrowthExperiments\WelcomeSurvey;
use MediaWiki\User\Options\UserOptionsLookup;
use MediaWiki\User\User;
use MediaWikiIntegrationTestCase;
use Wikimedia\Rdbms\IDBAccessObject;

require_once __DIR__ . '/../../../maintenance/deleteOldSurveys.php';

/**
 * @group Database
 * @covers \GrowthExperiments\Maintenance\DeleteOldSurveys
 */
class DeleteOldSurveysTest extends MediaWikiIntegrationTestCase {

	public function testExecute() {
		$userOptionsManager = $this->getServiceContainer()->getUserOptionsManager();

		$u1 = $this->getMutableTestUser()->getUser();
		$this->setRegistrationDate( $u1, '2000-01-01 00:00:00' );

		$u2 = $this->getMutableTestUser()->getUser();
		$userOptionsManager->setOption( $u2, WelcomeSurvey::SURVEY_PROP, json_encode( [], JSON_FORCE_OBJECT ) );
		$userOptionsManager->setOption( $u2, 'foo', 'bar' );
		$u2->saveSettings();
		$this->setRegistrationDate( $u2, '2000-01-01 00:00:00' );

		$u3 = $this->getMutableTestUser()->getUser();
		$userOptionsManager->setOption( $u3, WelcomeSurvey::SURVEY_PROP, json_encode( [
			'_submit_date' => wfTimestamp( TS_MW, '2000-01-01 00:00:00' ),
		] ) );
		$u3->saveSettings();
		$this->setRegistrationDate( $u3, '2000-01-01 00:00:00' );

		$u4 = $this->getMutableTestUser()->getUser();
		$userOptionsManager->setOption( $u4, WelcomeSurvey::SURVEY_PROP, json_encode( [
			'_submit_date' => wfTimestamp( TS_MW, '2100-01-01 00:00:00' ),
		] ) );
		$u4->saveSettings();
		$this->setRegistrationDate( $u4, '2000-01-01 00:00:00' );

		$u5 = $this->getMutableTestUser()->getUser();
		$userOptionsManager->setOption( $u5, WelcomeSurvey::SURVEY_PROP, json_encode( [
			'_submit_date' => wfTimestamp( TS_MW, '2000-01-01 00:00:00' ),
		] ) );
		$u5->saveSettings();
		$this->setRegistrationDate( $u1, '2100-01-01 00:00:00' );

		$u6 = $this->getMutableTestUser()->getUser();
		$userOptionsManager->setOption( $u6, WelcomeSurvey::SURVEY_PROP, json_encode( [
			'_submit_date' => wfTimestamp( TS_MW, '2000-01-01 00:00:00' ),
		] ) );
		$u6->saveSettings();
		$this->setRegistrationDate( $u1, '2000-01-01 00:00:00' );

		$output = $this->runScript( [ 'cutoff' => 100, 'verbose' => 1, 'dry-run' => 1 ] );
		// u4 is not deleted because the submit date is past cutoff. u5 and u6 are not deleted
		// because u5's registration date is past cutoff so the script ends there.
		$this->assertDeletedUsers( [ $u2, $u3 ], $output );
		$this->assertUsersNotHavePreference( [ 'u1' => $u1 ] );
		$this->assertUsersHavePreference( [ 'u2' => $u2, 'u3' => $u3, 'u4' => $u4,
			'u5' => $u5, 'u6' => $u6 ] );

		$output = $this->runScript( [ 'cutoff' => 100, 'verbose' => 1 ] );
		$this->assertDeletedUsers( [ $u2, $u3 ], $output );
		$this->assertUsersNotHavePreference( [ 'u1' => $u1, 'u2' => $u2, 'u3' => $u3 ] );
		$this->assertUsersHavePreference( [ 'u4' => $u4, 'u5' => $u5, 'u6' => $u6 ] );
		$this->assertUsersHavePreference( [ 'u2' => $u2 ], 'foo' );
	}

	/**
	 * @param User $user
	 * @param string $date Registration date in an MWTimestamp-compatible format
	 */
	private function setRegistrationDate( User $user, string $date ) {
		$this->getServiceContainer()->getUserRegistrationLookup()
			->setCachedRegistration( $user, wfTimestamp( TS_MW, $date ) );
		$this->getDb()->newUpdateQueryBuilder()
			->update( 'user' )
			->set( [ 'user_registration' => $this->getDb()->timestamp( $date ) ] )
			->where( [ 'user_id' => $user->getId() ] )
			->caller( __METHOD__ )
			->execute();
	}

	/**
	 * @param string[] $options
	 * @return string Script ouput
	 */
	private function runScript( array $options ) {
		ob_start();
		$deleteOldSurveys = new DeleteOldSurveys();
		$deleteOldSurveys->loadParamsAndArgs( 'deleteOldSurveys.php', $options, [] );
		$deleteOldSurveys->setConfig( $this->getServiceContainer()->getMainConfig() );
		$deleteOldSurveys->validateParamsAndArgs();
		$deleteOldSurveys->execute();
		$output = ob_get_contents();
		ob_end_clean();
		return $output;
	}

	/**
	 * @param User[] $users
	 * @param string $output Script output (must be run with -v)
	 */
	private function assertDeletedUsers( array $users, string $output ) {
		$ids = array_map( static function ( User $u ) {
			return $u->getId();
		}, $users );
		preg_match_all( '/Deleting survey data for user:(\d+)/', $output, $matches );
		// Order can be ignored; $ids is ints,  $matches[1] is strings
		$this->assertEquals( $ids, $matches[1] );
	}

	/**
	 * @param User[] $users
	 * @param string $pref
	 */
	private function assertUsersHavePreference(
		array $users, string $pref = WelcomeSurvey::SURVEY_PROP
	) {
		$userOptionsManager = $this->getServiceContainer()->getUserOptionsManager();

		foreach ( $users as $name => $user ) {
			$userOptionsManager->clearUserOptionsCache( $user );
			// sanity
			$this->assertNotSame( 0, $user->getId() );
			$this->assertArrayHasKey( $pref,
				$userOptionsManager->getOptions(
					$user,
					UserOptionsLookup::EXCLUDE_DEFAULTS,
					IDBAccessObject::READ_LATEST
				),
				"$name should have a survey pref but doesn't" );
		}
	}

	/**
	 * @param User[] $users
	 * @param string $pref
	 */
	private function assertUsersNotHavePreference(
		array $users, string $pref = WelcomeSurvey::SURVEY_PROP
	) {
		$userOptionsManager = $this->getServiceContainer()->getUserOptionsManager();

		foreach ( $users as $name => $user ) {
			$userOptionsManager->clearUserOptionsCache( $user );
			// sanity
			$this->assertNotSame( 0, $user->getId() );
			$this->assertArrayNotHasKey( $pref,
				$userOptionsManager->getOptions(
					$user,
					UserOptionsLookup::EXCLUDE_DEFAULTS,
					IDBAccessObject::READ_LATEST
				),
				"$name should not have a survey pref but does" );
		}
	}

}
