<?php

namespace GrowthExperiments\Tests\Integration;

use GrowthExperiments\GrowthExperimentsServices;
use GrowthExperiments\Rest\Handler\UserImpactHandler;
use GrowthExperiments\UserImpact\EditingStreak;
use GrowthExperiments\UserImpact\ExpensiveUserImpact;
use MediaWiki\Permissions\SimpleAuthority;
use MediaWiki\Permissions\UltimateAuthority;
use MediaWiki\Rest\HttpException;
use MediaWiki\Rest\RequestData;
use MediaWiki\Tests\Rest\Handler\HandlerTestTrait;
use MediaWiki\User\User;
use MediaWiki\User\UserIdentityValue;
use MediaWikiIntegrationTestCase;
use Wikimedia\Timestamp\TimestampFormat;

/**
 * @group Database
 * @covers \GrowthExperiments\Rest\Handler\UserImpactHandler
 */
class UserImpactHandlerTest extends MediaWikiIntegrationTestCase {
	use HandlerTestTrait;

	private function newHandler(): UserImpactHandler {
		$services = $this->getServiceContainer();
		return new UserImpactHandler(
			$services->get( 'GrowthExperimentsUserImpactStore' ),
			$services->get( 'GrowthExperimentsUserImpactLookup' ),
			GrowthExperimentsServices::wrap( $services )->getUserImpactFormatter(),
			$services->getStatsFactory(),
			$services->getJobQueueGroup(),
			$services->getUserFactory()
		);
	}

	private function newHiddenUserWithStoredImpact(): User {
		$user = $this->getMutableTestUser()->getUser();
		$today = gmdate( 'Y-m-d' );
		$this->getServiceContainer()->get( 'GrowthExperimentsUserImpactStore' )->setUserImpact(
			new ExpensiveUserImpact(
				$user, 42, 7, [ NS_MAIN => 30 ], [ $today => 5 ], [ 'copyedit' => 3 ], 1, 3,
				(int)wfTimestamp( TimestampFormat::UNIX ), [ $today => 1000 ], [], new EditingStreak(), 2, 30
			)
		);
		$this->assertStatusGood( $this->getServiceContainer()->getBlockUserFactory()->newBlockUser(
			$user, new UltimateAuthority( $this->getTestSysop()->getUser() ), 'infinity', '',
			[ 'isHideUser' => true ]
		)->placeBlock() );
		return $user;
	}

	private function newRequest( User $user ): RequestData {
		return new RequestData( [ 'method' => 'GET', 'pathParams' => [ 'user' => '#' . $user->getId() ] ] );
	}

	public function testHiddenUserIsNotFoundWithoutHideuser() {
		$user = $this->newHiddenUserWithStoredImpact();

		// Call run() directly, so that the core user parameter type does not hide the user first.
		$handler = $this->newHandler();
		$this->initHandler(
			$handler, $this->newRequest( $user ), [], [],
			new SimpleAuthority( new UserIdentityValue( 0, '127.0.0.1' ), [] )
		);
		$this->expectException( HttpException::class );
		$this->expectExceptionCode( 404 );
		$handler->run( $user );
	}

	public function testHiddenUserIsShownWithHideuser() {
		$user = $this->newHiddenUserWithStoredImpact();

		// Core resolves a hidden user to "Unknown user" in REST parameters (T441029).
		// Give the validated parameters, so that this test checks the handler itself.
		$data = $this->executeHandlerAndGetBodyData(
			$this->newHandler(), $this->newRequest( $user ), [], [],
			[ 'user' => $user, 'lang' => null, 'regenerate' => false ], [],
			new SimpleAuthority( new UserIdentityValue( 0, '127.0.0.1' ), [ 'hideuser' ] )
		);

		$this->assertSame( $user->getName(), $data['userName'] );
	}
}
