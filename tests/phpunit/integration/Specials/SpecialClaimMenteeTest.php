<?php

namespace GrowthExperiments\Tests;

use GlobalVarConfig;
use GrowthExperiments\GrowthExperimentsServices;
use GrowthExperiments\Mentorship\Provider\StaticMentorProvider;
use GrowthExperiments\Specials\SpecialClaimMentee;
use MediaWiki\MediaWikiServices;
use PermissionsError;
use SpecialPageTestBase;

/**
 * @group Database
 * @coversDefaultClass \GrowthExperiments\Specials\SpecialClaimMentee
 */
class SpecialClaimMenteeTest extends SpecialPageTestBase {
	/**
	 * @inheritDoc
	 */
	protected function newSpecialPage() {
		return new SpecialClaimMentee(
			new StaticMentorProvider( [] ),
			GrowthExperimentsServices::wrap( MediaWikiServices::getInstance() )
				->getChangeMentorFactory(),
			// This would normally be GrowthExperimentsMultiConfig, but there
			// is no need to test the on-wiki config here
			GlobalVarConfig::newInstance()
		);
	}

	/**
	 * @covers ::userCanExecute
	 */
	public function testNonMentorCantExecute() {
		$this->expectException( PermissionsError::class );
		$user = $this->getTestSysop()->getUser();
		$this->executeSpecialPage( '', null, null, $user );
	}
}
