<?php

declare( strict_types = 1 );

namespace GrowthExperiments\Tests\Integration;

use GrowthExperiments\Maintenance\CleanMentorList;
use GrowthExperiments\Mentorship\Cleaner\MentorListCleaner;
use MediaWiki\Language\RawMessage;
use MediaWiki\Tests\Maintenance\MaintenanceBaseTestCase;
use StatusValue;

/**
 * @covers \GrowthExperiments\Maintenance\CleanMentorList
 */
class CleanMentorListTest extends MaintenanceBaseTestCase {

	protected function getMaintenanceClass(): string {
		return CleanMentorList::class;
	}

	private function setMentorListCleaner( ?StatusValue $status ): void {
		$cleaner = $this->createMock( MentorListCleaner::class );
		if ( $status === null ) {
			$cleaner->expects( $this->never() )->method( 'processMentors' );
		} else {
			$cleaner->expects( $this->once() )->method( 'processMentors' )->willReturn( $status );
		}
		$this->setService( 'GrowthExperimentsMentorListCleaner', $cleaner );
	}

	public function testCleanupDisabled(): void {
		$this->overrideConfigValue( 'GEMentorshipCleanupEnabled', false );
		$this->setMentorListCleaner( null );

		$this->maintenance->execute();

		$this->expectOutputString( 'Mentor list cleanup is not enabled on this wiki!' . PHP_EOL );
	}

	public function testCleanupSuccess(): void {
		$this->overrideConfigValue( 'GEMentorshipCleanupEnabled', true );
		$this->setMentorListCleaner( StatusValue::newGood() );

		$this->maintenance->execute();

		$this->expectOutputRegex( '/^Completed successfully! Duration: [\d.E\-]+ seconds\.\n$/' );
	}

	public function testCleanupFailure(): void {
		$this->overrideConfigValue( 'GEMentorshipCleanupEnabled', true );
		$this->setMentorListCleaner( StatusValue::newFatal( new RawMessage( 'Mentor cleanup failed' ) ) );

		$this->maintenance->execute();

		$this->expectOutputRegex( '/^Mentor cleanup failed.*Duration: [\d.E\-]+ seconds\.$/s' );
	}
}
