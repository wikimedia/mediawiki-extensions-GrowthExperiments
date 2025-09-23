<?php

namespace GrowthExperiments\Tests\Unit;

use GrowthExperiments\Mentorship\MentorshipSummaryCreator;
use InvalidArgumentException;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserIdentityValue;
use MediaWikiUnitTestCase;

/**
 * @covers \GrowthExperiments\Mentorship\MentorshipSummaryCreator
 */
class MentorshipSummaryCreatorTest extends MediaWikiUnitTestCase {

	/**
	 * @param string $expectedMessage
	 * @param string $action
	 * @param UserIdentity $performerUserIdentity
	 * @param UserIdentity $mentorUserIdentity
	 * @param string $reason
	 * @dataProvider provideTestCreateSummary
	 */
	public function testCreateSummary(
		string $expectedMessage,
		string $action,
		UserIdentity $performerUserIdentity,
		UserIdentity $mentorUserIdentity,
		string $reason
	) {
		switch ( $action ) {
			case 'add':
				$actualMessage = MentorshipSummaryCreator::createAddSummary(
					$performerUserIdentity,
					$mentorUserIdentity,
					$reason
				);
				break;
			case 'change':
				$actualMessage = MentorshipSummaryCreator::createChangeSummary(
					$performerUserIdentity,
					$mentorUserIdentity,
					$reason
				);
				break;
			case 'remove':
				$actualMessage = MentorshipSummaryCreator::createRemoveSummary(
					$performerUserIdentity,
					$mentorUserIdentity,
					$reason
				);
				break;
			default:
				throw new InvalidArgumentException(
					"Unrecognized value of \$action: \"$action\""
				);
		}

		$this->assertSame( $expectedMessage, $actualMessage );
	}

	public static function provideTestCreateSummary() {
		$adminUserIdentity = new UserIdentityValue( 1, 'Admin' );
		$mentorUserIdentity = new UserIdentityValue( 2, 'Mentor' );

		foreach ( [ 'add', 'change', 'remove' ] as $action ) {
			yield [
				"/* growthexperiments-manage-mentors-summary-$action-admin-no-reason:Mentor| */",
				$action,
				$adminUserIdentity,
				$mentorUserIdentity,
				'',
			];
			yield [
				"/* growthexperiments-manage-mentors-summary-$action-admin-with-reason:Mentor|foo */",
				$action,
				$adminUserIdentity,
				$mentorUserIdentity,
				'foo',
			];
			yield [
				"/* growthexperiments-manage-mentors-summary-$action-self-no-reason:Mentor| */",
				$action,
				$mentorUserIdentity,
				$mentorUserIdentity,
				'',
			];
			yield [
				"/* growthexperiments-manage-mentors-summary-$action-self-with-reason:Mentor|bar */",
				$action,
				$mentorUserIdentity,
				$mentorUserIdentity,
				'bar',
			];
		}
	}
}
