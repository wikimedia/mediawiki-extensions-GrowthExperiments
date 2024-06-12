<?php

namespace GrowthExperiments\Tests\Integration;

use GrowthExperiments\GrowthExperimentsServices;
use MediaWikiIntegrationTestCase;

/**
 * @group medium
 * @group Database
 * @coversDefaultClass \GrowthExperiments\UserDatabaseHelper
 */
class UserDatabaseHelperTest extends MediaWikiIntegrationTestCase {

	/**
	 * @covers ::hasMainspaceEdits
	 */
	public function testHasMainspaceEditsWithNoEdits() {
		$this->assertSame(
			false,
			GrowthExperimentsServices::wrap( $this->getServiceContainer() )
				->getUserDatabaseHelper()
				->hasMainspaceEdits( $this->getTestUser()->getUserIdentity() )
		);
	}

	/**
	 * @covers ::hasMainspaceEdits
	 * @dataProvider provideHasMainspaceEdits
	 * @param bool|null $expectedResult
	 * @param int $namespaceId
	 * @param bool $overLimit
	 */
	public function testHasMainspaceEditsWithEdits(
		?bool $expectedResult,
		int $namespaceId,
		bool $overLimit
	) {
		$user = $this->getMutableTestUser()->getUser();
		$limit = 5;
		$editsToSave = $overLimit ? $limit : $limit - 1;
		for ( $i = 0; $i < $editsToSave; $i++ ) {
			$this->editPage(
				'Sandbox',
				"test $i",
				'',
				$namespaceId,
				$user
			);
		}

		$this->assertSame(
			$expectedResult,
			GrowthExperimentsServices::wrap( $this->getServiceContainer() )
				->getUserDatabaseHelper()
				->hasMainspaceEdits( $user, $limit )
		);
	}

	public static function provideHasMainspaceEdits() {
		return [
			'NS_MAIN edits, over limit' => [ true, NS_MAIN, true ],
			'NS_MAIN edits, under limit' => [ true, NS_MAIN, false ],
			'NS_PROJECT edits, over limit' => [ null, NS_PROJECT, true ],
			'NS_PROJECT edits, under limit' => [ false, NS_PROJECT, false ],
		];
	}
}
