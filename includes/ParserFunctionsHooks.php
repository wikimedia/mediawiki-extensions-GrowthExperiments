<?php

namespace GrowthExperiments;

use MediaWiki\MediaWikiServices;
use MediaWiki\Parser\Parser;

/**
 * Class that consumes parser-functions related hooks.
 */
class ParserFunctionsHooks implements \MediaWiki\Hook\ParserFirstCallInitHook {

	/** @inheritDoc */
	public function onParserFirstCallInit( $parser ) {
		$parser->setFunctionHook( 'mentor', static function ( Parser $parser, $username ) {
			// Do not use dependency injection here. MentorManager's service wiring
			// tries to read the context language, which fails when sessions are disabled,
			// such as in ResourceLoader callbacks. Having this hook depending on MentorManager
			// would cause an error every time the parser gets initialized in a ResourceLoader callback,
			// which is sometimes done for messages parsing.

			// Accessing the service container late means this would happen only if {{#mentor}}
			// is actually used in a ResourceLoader callback, which should not happen.

			return HomepageParserFunctions::mentorRender(
				MediaWikiServices::getInstance()->getUserFactory(),
				GrowthExperimentsServices::wrap(
					MediaWikiServices::getInstance()
				)->getMentorManager(),
				$parser,
				$username
			);
		} );
	}
}
