<?php

namespace GrowthExperiments\Tests\Unit;

use GrowthExperiments\MentorDashboard\PersonalizedPraise\PersonalizedPraiseSettings;
use GrowthExperiments\MentorDashboard\PersonalizedPraise\PraiseworthyConditions;
use MediaWiki\Config\HashConfig;
use MediaWiki\Json\FormatJson;
use MediaWiki\Revision\RevisionLookup;
use MediaWiki\Title\TitleFactory;
use MediaWiki\User\Options\UserOptionsLookup;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserIdentityValue;
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
	 * @param int|null $maxReverts
	 * @param int $minDays
	 * @covers ::getPraiseworthyConditions
	 * @dataProvider provideGetPraiseworthyConditions
	 */
	public function testGetPraiseworthyConditions(
		PraiseworthyConditions $expected,
		string $settingsValue,
		int $maxEdits,
		int $minEdits,
		?int $maxReverts,
		int $minDays
	) {
		$settings = new PersonalizedPraiseSettings(
			new HashConfig( [
				'GEPersonalizedPraiseDays' => $minDays,
				'GEPersonalizedPraiseMinEdits' => $minEdits,
				'GEPersonalizedPraiseMaxEdits' => $maxEdits,
				'GEPersonalizedPraiseMaxReverts' => $maxReverts,
			] ),
			$this->createMock( MessageLocalizer::class ),
			$this->newUserOptionsLookupMock( $settingsValue ),
			$this->createMock( UserFactory::class ),
			$this->createMock( TitleFactory::class ),
			$this->createMock( RevisionLookup::class )
		);

		$this->assertSame(
			$expected->jsonSerialize(),
			$settings->getPraiseworthyConditions( $this->mentor )->jsonSerialize()
		);
	}

	public static function provideGetPraiseworthyConditions() {
		return [
			'none set' => [
				new PraiseworthyConditions( 500, 8, 12, 7 ),
				'{}',
				500, 8, 12, 7,
			],
			'maxEdits set' => [
				new PraiseworthyConditions( 200, 8, 12, 7 ),
				FormatJson::encode( [ PraiseworthyConditions::SETTING_MAX_EDITS => 200 ] ),
				500, 8, 12, 7,
			],
			'minEdits set' => [
				new PraiseworthyConditions( 500, 200, 12, 7 ),
				FormatJson::encode( [ PraiseworthyConditions::SETTING_MIN_EDITS => 200 ] ),
				500, 8, 12, 7,
			],
			'maxReverts set' => [
				new PraiseworthyConditions( 500, 8, 200, 7 ),
				FormatJson::encode( [ PraiseworthyConditions::SETTING_MAX_REVERTS => 200 ] ),
				500, 8, 12, 7,
			],
			'maxReverts null' => [
				new PraiseworthyConditions( 500, 8, null, 7 ),
				'{}',
				500, 8, null, 7,
			],
			'something else set' => [
				new PraiseworthyConditions( 500, 8, 12, 7 ),
				FormatJson::encode( [ 'foo' => 200 ] ),
				500, 8, 12, 7,
			],
		];
	}
}
