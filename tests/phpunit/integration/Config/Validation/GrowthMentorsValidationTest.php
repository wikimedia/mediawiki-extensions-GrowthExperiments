<?php

declare( strict_types = 1 );

namespace GrowthExperiments\Tests\Integration;

use MediaWikiIntegrationTestCase;

/**
 * @group Database
 *
 * @covers \GrowthExperiments\Config\Validation\StructuredMentorListValidator
 * @covers \GrowthExperiments\Config\Validation\CommunityStructuredMentorListValidator
 */
class GrowthMentorsValidationTest extends MediaWikiIntegrationTestCase {

	public function testSavingWithNoMentorsIsValid(): void {
		$pageStatus = $this->editPage( 'MediaWiki:GrowthMentors.json', '{"Mentors": {}}' );
		$this->assertStatusGood( $pageStatus );
	}

	public function testSavingWithTooLongMessageIsInvalid(): void {
		$longMessage = str_repeat( 'a', 1001 );
		$pageStatus = $this->editPage(
			'MediaWiki:GrowthMentors.json',
			'{ "Mentors": { "1": {"username": "Mentor1", "weight": 0, "message": "' . $longMessage . '"} } }'
		);
		$this->assertStatusNotOk( $pageStatus );
		$this->assertStatusMessage( 'communityconfiguration-schema-validation-error', $pageStatus );
	}
}
