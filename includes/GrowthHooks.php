<?php

namespace GrowthExperiments;

use InvalidArgumentException;

class GrowthHooks {
	/**
	 * Called right after configuration has been loaded.
	 */
	public static function onRegistration() {
		global $wgGEMentorshipMigrationStage;

		$stage = $wgGEMentorshipMigrationStage;
		if ( is_string( $stage ) ) {
			$stage = 0;
			foreach ( explode( '|', $wgGEMentorshipMigrationStage ) as $constant ) {
				$stage |= constant( trim( $constant ) );
			}
		}

		// we do not support PreferenceMentorStore at all (T291188)
		if ( $stage !== SCHEMA_COMPAT_NEW ) {
			throw new InvalidArgumentException( '$stage must be SCHEMA_COMPAT_NEW' );
		}

		$wgGEMentorshipMigrationStage = $stage;
	}
}
