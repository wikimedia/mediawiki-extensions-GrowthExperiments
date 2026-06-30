<?php

namespace GrowthExperiments\Tests\Integration;

use MediaWiki\Tests\Logging\LogFormatterTestCase;
use MediaWiki\Title\TitleValue;

/**
 * @covers \GrowthExperiments\Mentorship\MentorChangeLogFormatter
 */
class MentorChangeLogFormatterTest extends LogFormatterTestCase {

	/**
	 * Provide different rows from the logging table to test
	 * for backward compatibility.
	 * Do not change the existing data, just add a new database row
	 */
	public static function provideDatabaseRows() {
		return [
			'setmentor with a previous mentor' => [
				[
					'type' => 'growthexperiments',
					'action' => 'setmentor',
					'user_text' => 'Performer',
					'namespace' => NS_USER,
					'title' => 'Mentee',
					'params' => [
						'4::previous-mentor' => 'OldMentor',
						'5::new-mentor' => 'NewMentor',
					],
				],
				[
					'text' => 'Performer set NewMentor as the mentor of Mentee '
						. '(previous mentor OldMentor)',
					'api' => [
						'previous-mentor' => 'OldMentor',
						'new-mentor' => 'NewMentor',
					],
					'preload' => [
						new TitleValue( NS_USER, 'OldMentor' ),
						new TitleValue( NS_USER, 'NewMentor' ),
					],
				],
			],
			'setmentor without a previous mentor' => [
				[
					'type' => 'growthexperiments',
					'action' => 'setmentor-no-previous-mentor',
					'user_text' => 'Performer',
					'namespace' => NS_USER,
					'title' => 'Mentee',
					'params' => [
						'5::new-mentor' => 'NewMentor',
					],
				],
				[
					'text' => 'Performer set NewMentor as the mentor of Mentee '
						. '(no previous mentor)',
					'api' => [
						'new-mentor' => 'NewMentor',
					],
					'preload' => [
						new TitleValue( NS_USER, 'NewMentor' ),
					],
				],
			],
			'claimmentee with a previous mentor' => [
				[
					'type' => 'growthexperiments',
					'action' => 'claimmentee',
					'user_text' => 'NewMentor',
					'namespace' => NS_USER,
					'title' => 'Mentee',
					'params' => [
						'4::previous-mentor' => 'OldMentor',
					],
				],
				[
					'text' => 'NewMentor claimed Mentee as their mentee '
						. '(previous mentor OldMentor)',
					'api' => [
						'previous-mentor' => 'OldMentor',
					],
					'preload' => [
						new TitleValue( NS_USER, 'OldMentor' ),
					],
				],
			],
			'claimmentee without a previous mentor' => [
				[
					'type' => 'growthexperiments',
					'action' => 'claimmentee-no-previous-mentor',
					'user_text' => 'NewMentor',
					'namespace' => NS_USER,
					'title' => 'Mentee',
					'params' => [],
				],
				[
					'text' => 'NewMentor claimed Mentee as their mentee '
						. '(no previous mentor)',
					'api' => [],
					'preload' => [],
				],
			],
		];
	}

	/**
	 * @dataProvider provideDatabaseRows
	 */
	public function testLogDatabaseRows( $row, $extra ) {
		$this->doTestLogFormatter( $row, $extra );
	}
}
