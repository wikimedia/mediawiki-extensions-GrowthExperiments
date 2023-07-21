<?php

namespace GrowthExperiments\Tests;

use FormatJson;
use GrowthExperiments\MentorDashboard\PersonalizedPraise\PersonalizedPraiseSettings;
use GrowthExperiments\MentorDashboard\PersonalizedPraise\PraiseworthyConditions;
use HashConfig;
use MediaWiki\Revision\RevisionLookup;
use MediaWiki\Title\TitleFactory;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserIdentityValue;
use MediaWiki\User\UserOptionsLookup;
use MediaWikiUnitTestCase;
use MessageLocalizer;

/**
 * @coversDefaultClass \GrowthExperiments\MentorDashboard\PersonalizedPraise\PersonalizedPraiseSettings
 */
class PersonalizedPraiseSettingsTest extends MediaWikiUnitTestCase {

	private UserIdentity $mentor;

	/** @inheritDoc */
	protected function setUp(): void {
		parent::setUp();
		$this->mentor = new UserIdentityValue( 123, 'Mentor' );
	}

	private function newUserOptionsLookupMock( string $settingsValue ) {
		$userOptionsLookup = $this->createMock( UserOptionsLookup::class );
		$userOptionsLookup->expects( $this->once() )
			->method( 'getOption' )
			->with( $this->mentor, PersonalizedPraiseSettings::PREF_NAME )
			->willReturn( $settingsValue );
		return $userOptionsLookup;
	}

	/**
	 * @param PraiseworthyConditions $expected
	 * @param string $settingsValue
	 * @param int $maxEdits
	 * @param int $minEdits
	 * @param int $minDays
	 * @covers ::getPraiseworthyConditions
	 * @dataProvider provideGetPraiseworthyConditions
	 */
	public function testGetPraiseworthyConditions(
		PraiseworthyConditions $expected,
		string $settingsValue,
		int $maxEdits,
		int $minEdits,
		int $minDays
	) {
		$settings = new PersonalizedPraiseSettings(
			new HashConfig( [
				'GEPersonalizedPraiseDays' => $minDays,
				'GEPersonalizedPraiseMinEdits' => $minEdits,
				'GEPersonalizedPraiseMaxEdits' => $maxEdits
			] ),
			$this->createMock( MessageLocalizer::class ),
			$this->newUserOptionsLookupMock( $settingsValue ),
			$this->createMock( UserFactory::class ),
			$this->createMock( TitleFactory::class ),
			$this->createMock( RevisionLookup::class )
		);

		$this->assertEquals(
			$expected,
			$settings->getPraiseworthyConditions( $this->mentor )
		);
	}

	public static function provideGetPraiseworthyConditions() {
		return [
			'none set' => [
				new PraiseworthyConditions( 500, 8, 7 ),
				'{}',
				500, 8, 7
			],
			'maxEdits set' => [
				new PraiseworthyConditions( 200, 8, 7 ),
				FormatJson::encode( [ PraiseworthyConditions::SETTING_MAX_EDITS => 200, ] ),
				500, 8, 7
			],
			'minEdits set' => [
				new PraiseworthyConditions( 500, 200, 7 ),
				FormatJson::encode( [ PraiseworthyConditions::SETTING_MIN_EDITS => 200, ] ),
				500, 8, 7
			],
			'something else set' => [
				new PraiseworthyConditions( 500, 8, 7 ),
				FormatJson::encode( [ 'foo' => 200, ] ),
				500, 8, 7
			],
		];
	}
}
