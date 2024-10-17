<?php

namespace GrowthExperiments\Tests\Integration;

use MediaWiki\Extension\CommunityConfiguration\Tests\SchemaProviderTestCase;

/**
 * @coversNothing
 */
class MentorshipSchemaProviderTest extends SchemaProviderTestCase {

	protected function getExtensionName(): string {
		return 'GrowthExperiments';
	}

	protected function getProviderId(): string {
		return 'Mentorship';
	}

}
