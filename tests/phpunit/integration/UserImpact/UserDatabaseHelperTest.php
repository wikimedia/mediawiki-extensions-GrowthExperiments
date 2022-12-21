<?php

namespace GrowthExperiments\Tests;

use GrowthExperiments\UserDatabaseHelper;
use MediaWikiIntegrationTestCase;
use TitleValue;

/**
 * @group Database
 * @coversDefaultClass \GrowthExperiments\UserDatabaseHelper
 */
class UserDatabaseHelperTest extends MediaWikiIntegrationTestCase {

	/** @inheritDoc */
	protected $tablesUsed = [ 'user', 'page' ];

	/**
	 * @covers ::hasMainspaceEdits
	 * @dataProvider provideHasMainspaceEdits
	 * @param int[] $editTargets title => ns
	 * @param int $limit
	 * @param bool|null $expected
	 * @return void
	 */
	public function testHasMainspaceEdits( array $editTargets, int $limit, ?bool $expected ) {
		$user = $this->getMutableTestUser()->getUser();
		$i = 0;
		foreach ( $editTargets as $title => $namespace ) {
			$i++;
			$editTarget = new TitleValue( $namespace, $title );
			$this->editPage( $editTarget, "Test edit $i", "Test edit $i", NS_MAIN, $user );
		}
		/** @var UserDatabaseHelper $helper */
		$helper = $this->getServiceContainer()->get( 'GrowthExperimentsUserDatabaseHelper' );
		$this->assertSame( $expected, $helper->hasMainspaceEdits( $user, $limit ) );
	}

	public function provideHasMainspaceEdits() {
		return [
			[ [], 3, false ],
			[ [ 'P1' => NS_MAIN, 'P2' => NS_TALK ], 3, true ],
			[ [ 'P1' => NS_TALK, 'P2' => NS_TALK ], 3, false ],
			[ [ 'P1' => NS_TALK, 'P2' => NS_TALK, 'P3' => NS_MAIN, 'P4' => NS_TALK ], 3, true ],
			[ [ 'P1' => NS_TALK, 'P2' => NS_TALK, 'P3' => NS_TALK, 'P4' => NS_MAIN ], 3, null ],
			[ [ 'P1' => NS_TALK, 'P2' => NS_TALK, 'P3' => NS_TALK, 'P4' => NS_TALK ], 3, null ],
		];
	}

}
