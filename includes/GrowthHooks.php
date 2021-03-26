<?php

namespace GrowthExperiments;

use InvalidArgumentException;

class GrowthHooks {
	/**
	 * Called right after configuration has been loaded.
	 */
	public static function onRegistration() {
		global $wgGEMentorshipMigrationStage;

		$stage = 0;
		foreach ( explode( '|', $wgGEMentorshipMigrationStage ) as $constant ) {
			$stage |= constant( trim( $constant ) );
		}

		// Validation for the mentorship migration stage, stolen from ActorMigration
		if ( ( $stage & SCHEMA_COMPAT_WRITE_BOTH ) === 0 ) {
			throw new InvalidArgumentException( '$stage must include a write mode' );
		}
		if ( ( $stage & SCHEMA_COMPAT_READ_BOTH ) === 0 ) {
			throw new InvalidArgumentException( '$stage must include a read mode' );
		}
		if ( ( $stage & SCHEMA_COMPAT_READ_BOTH ) === SCHEMA_COMPAT_READ_BOTH ) {
			throw new InvalidArgumentException( 'Cannot read both schemas' );
		}
		if ( ( $stage & SCHEMA_COMPAT_READ_OLD ) && !( $stage & SCHEMA_COMPAT_WRITE_OLD ) ) {
			throw new InvalidArgumentException( 'Cannot read the old schema without also writing it' );
		}
		if ( ( $stage & SCHEMA_COMPAT_READ_NEW ) && !( $stage & SCHEMA_COMPAT_WRITE_NEW ) ) {
			throw new InvalidArgumentException( 'Cannot read the new schema without also writing it' );
		}

		$wgGEMentorshipMigrationStage = $stage;
	}
}
