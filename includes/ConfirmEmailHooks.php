<?php

declare( strict_types = 1 );

namespace GrowthExperiments;

use MediaWiki\Auth\Hook\AuthPreserveQueryParamsHook;
use MediaWiki\Context\RequestContext;

class ConfirmEmailHooks implements
	AuthPreserveQueryParamsHook
{

	/**
	 * @inheritDoc
	 */
	public function onAuthPreserveQueryParams( array &$params, array $options ): void {
		$request = RequestContext::getMain()->getRequest();
		$experiments = $request->getArray( 'experiments' );
		if ( $experiments ) {
			$params['experiments'] = $experiments;
		}
	}

}
