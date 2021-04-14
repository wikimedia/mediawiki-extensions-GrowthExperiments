<?php

namespace GrowthExperiments\Tests;

use GrowthExperiments\Mentorship\Store\MentorStore;
use GrowthExperiments\Mentorship\Store\PreferenceMentorStore;

/**
 * @group Database
 * @group medium
 * @covers \GrowthExperiments\Mentorship\Store\MentorStore
 * @covers \GrowthExperiments\Mentorship\Store\PreferenceMentorStore
 */
class PreferenceMentorStoreTest extends MentorStoreTestCase {

	protected function getStore( bool $wasPosted ): MentorStore {
		return new PreferenceMentorStore( $this->getServiceContainer()->getUserFactory(),
			$this->getServiceContainer()->getUserOptionsManager(), $wasPosted );
	}

	protected function getJobType(): string {
		return 'userOptionsUpdate';
	}

}
