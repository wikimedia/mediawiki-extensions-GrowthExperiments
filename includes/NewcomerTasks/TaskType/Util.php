<?php

namespace GrowthExperiments\NewcomerTasks\TaskType;

use MediaWiki\Linker\LinkTarget;

class Util {

	/**
	 * Helper method for escaping CirrusSearch query strings.
	 *
	 * @param LinkTarget[] $titles
	 * @return string
	 */
	public static function escapeSearchTitleList( array $titles ): string {
		return '"' . implode( '|', array_map( static function ( LinkTarget $title ) {
				return str_replace( [ '"', '?' ], [ '\"', '\?' ], $title->getDBkey() );
		}, $titles ) ) . '"';
	}
}
