<?php

namespace GrowthExperiments;

use Exception;
use MediaWiki\Context\RequestContext;
use MediaWiki\MediaWikiServices;
use StatusValue;

/**
 * Generic exception class for things that are too rare or unimportant to merit a custom
 * exception class.
 * The exception can wrap localized messages. It isn't feasible to have an exception with a
 * localized message (since getMessage is final and localization has too many dependencies to
 * be doable at exception construction time), so the assumption is that callers always catch
 * this exception and render it appropriately.
 */
class ErrorException extends Exception {

	private StatusValue $status;

	public function __construct( StatusValue $error ) {
		parent::__construct( $error->__toString() );
		$this->status = $error;
	}

	/**
	 * Get the raw error status.
	 */
	public function getStatus(): StatusValue {
		return $this->status;
	}

	/**
	 * Get the error status as a localized string (intended for displaying errors to the user).
	 */
	public function getErrorMessage(): string {
		$statusFormatter = MediaWikiServices::getInstance()->getFormatterFactory()
			->getStatusFormatter( RequestContext::getMain() );
		return $statusFormatter->getWikiText( $this->status );
	}

	/**
	 * Get the error status as an English string (intended for logging).
	 */
	public function getErrorMessageInEnglish(): string {
		$statusFormatter = MediaWikiServices::getInstance()->getFormatterFactory()
			->getStatusFormatter( RequestContext::getMain() );
		return $statusFormatter->getWikiText( $this->status, [ 'lang' => 'en' ] );
	}

}
