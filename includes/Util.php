<?php

namespace GrowthExperiments;

use Exception;
use FormatJson;
use IContextSource;
use Iterator;
use MediaWiki\Auth\AuthManager;
use MediaWiki\Http\HttpRequestFactory;
use MediaWiki\Logger\LoggerFactory;
use MWExceptionHandler;
use RawMessage;
use RequestContext;
use Sanitizer;
use Skin;
use SkinMinerva;
use Status;
use StatusValue;
use Throwable;
use Traversable;
use User;

class Util {

	const MINUTE = 60;
	const HOUR = 3600;
	const DAY = 86400;
	const WEEK = 604800;
	const MONTH = 2592000;
	const YEAR = 31536000;

	/**
	 * Helper method to check if a user can set their email.
	 *
	 * Called from the Help Panel and the Welcome Survey when a user has no email, or has
	 * an email that has not yet been confirmed.
	 *
	 * To check if a user with no email can set a particular email, pass in only the second
	 * argument; to check if a user with an unconfirmed email can set a particular email set the
	 * third argument to false.
	 *
	 * @param User $user
	 * @param null $newEmail
	 * @param bool $checkConfirmedEmail
	 * @return bool
	 */
	public static function canSetEmail( User $user, $newEmail = null, $checkConfirmedEmail = true ) {
		return ( $checkConfirmedEmail ?
				!$user->getEmail() || !$user->isEmailConfirmed() :
				!$user->getEmail() ) &&
			$user->isAllowed( 'viewmyprivateinfo' ) &&
			$user->isAllowed( 'editmyprivateinfo' ) &&
			AuthManager::singleton()->allowsPropertyChange( 'emailaddress' ) &&
			( $newEmail ? Sanitizer::validateEmail( $newEmail ) : true );
	}

	/**
	 * @param IContextSource $contextSource
	 * @param int $elapsedTime
	 * @return string
	 */
	public static function getRelativeTime( IContextSource $contextSource, $elapsedTime ) {
		return $contextSource->getLanguage()->formatDuration(
			$elapsedTime,
			self::getIntervals( $elapsedTime )
		);
	}

	/**
	 * Return the intervals passed as second arg to Language->formatDuration().
	 * @param int $time
	 *  Elapsed time since account creation in seconds.
	 * @return array
	 */
	private static function getIntervals( $time ) {
		if ( $time < self::MINUTE ) {
			return [ 'seconds' ];
		} elseif ( $time < self::HOUR ) {
			return [ 'minutes' ];
		} elseif ( $time < self::DAY ) {
			return [ 'hours' ];
		} elseif ( $time < self::WEEK ) {
			return [ 'days' ];
		} elseif ( $time < self::MONTH ) {
			return [ 'weeks' ];
		} elseif ( $time < self::YEAR ) {
			return [ 'weeks' ];
		} else {
			return [ 'years', 'weeks' ];
		}
	}

	/**
	 * @param Skin $skin
	 * @return bool Whether the given skin is considered "mobile".
	 */
	public static function isMobile( Skin $skin ) {
		return $skin instanceof SkinMinerva;
	}

	/**
	 * Add the guided tour module if the user is logged-in, hasn't seen the tour already,
	 * and the tour dependencies are loaded.
	 *
	 * @param \OutputPage $out
	 * @param string $pref
	 * @param string|string[] $modules
	 */
	public static function maybeAddGuidedTour( \OutputPage $out, $pref, $modules ) {
		if ( $out->getUser()->isLoggedIn() &&
			!$out->getUser()->getBoolOption( $pref ) &&
			TourHooks::growthTourDependenciesLoaded() ) {
			$out->addModules( $modules );
		}
	}

	/**
	 * Log an error. Configuration errors are logged to the GrowthExperiments channel,
	 * internal errors are logged to the exception channel.
	 * @param Throwable $error Error object from the catch block
	 * @param array $extraData
	 */
	public static function logError( Throwable $error, array $extraData = [] ) {
		if ( $error instanceof WikiConfigException ) {
			LoggerFactory::getInstance( 'GrowthExperiments' )->error(
				$error->getMessage(), $extraData + [ 'exception' => $error ] );
		} else {
			MWExceptionHandler::logException( $error, MWExceptionHandler::CAUGHT_BY_OTHER, $extraData );
		}
	}

	/**
	 * Fetch JSON data from a remote URL, parse it and return the results.
	 * @param HttpRequestFactory $requestFactory
	 * @param string $url
	 * @param bool $isSameFarm Is the URL on the same wiki farm we are making the request from?
	 * @return StatusValue A status object with the parsed JSON value, or any errors.
	 *   (Warnings coming from the HTTP library will be logged and not included here.)
	 */
	public static function getJsonUrl(
		HttpRequestFactory $requestFactory, $url, $isSameFarm = false
	) {
		$options = [
			'method' => 'GET',
			'userAgent' => $requestFactory->getUserAgent() . ' GrowthExperiments',
		];
		if ( $isSameFarm ) {
			$options['originalRequest'] = RequestContext::getMain()->getRequest();
		}
		$request = $requestFactory->create( $url, $options, __METHOD__ );
		$status = $request->execute();
		if ( $status->isOK() ) {
			$status->merge( FormatJson::parse( $request->getContent(), FormatJson::FORCE_ASSOC ), true );
		}
		// Log warnings here. The caller is expected to handle errors so do not double-log them.
		list( $errorStatus, $warningStatus ) = $status->splitByErrorType();
		if ( !$warningStatus->isGood() ) {
			LoggerFactory::getInstance( 'GrowthExperiments' )->warning(
				$warningStatus->getWikiText( null, null, 'en' ),
				[ 'exception' => new Exception( __FUNCTION__ ) ]
			);
		}
		return $errorStatus;
	}

	/**
	 * Fetch data from a remote MediaWiki, parse it and return the results.
	 * Much like getJsonUrl but also handles API errors. GET requests only.
	 * @param HttpRequestFactory $requestFactory
	 * @param string $apiUrl URL of the remote API (should end with 'api.php')
	 * @param string[] $parameters API parameters. Response formatting parameters will be added.
	 * @param bool $isSameFarm Is the URL on the same wiki farm we are making the request from?
	 * @return StatusValue A status object with the parsed JSON response, or any errors.
	 *   (Warnings will be logged and not included here.)
	 */
	public static function getApiUrl(
		HttpRequestFactory $requestFactory,
		$apiUrl,
		$parameters,
		$isSameFarm = false
	) {
		$parameters = [
			'format' => 'json',
			'formatversion' => 2,
			'errorformat' => 'wikitext',
		] + $parameters;
		$status = self::getJsonUrl( $requestFactory, $apiUrl . '?' . wfArrayToCgi( $parameters ),
			$isSameFarm );
		if ( $status->isOK() ) {
			$errorStatus = StatusValue::newGood();
			$warningStatus = StatusValue::newGood();
			$data = $status->getValue();
			if ( isset( $data['errors'] ) ) {
				foreach ( $data['errors'] as $error ) {
					$errorStatus->fatal( new RawMessage( $error['text'] ) );
				}
			}
			if ( isset( $data['warnings'] ) ) {
				foreach ( $data['warnings'] as $warning ) {
					$warningStatus->warning( new RawMessage( $warning['module'] . ': ' . $warning['text'] ) );
				}
			}
			$status->merge( $errorStatus );
			// Log warnings here. The caller is expected to handle errors so do not double-log them.
			if ( !$warningStatus->isGood() ) {
				LoggerFactory::getInstance( 'GrowthExperiments' )->warning(
					Status::wrap( $warningStatus )->getWikiText( null, null, 'en' ),
					[ 'exception' => new Exception( 'getApiUrl' ) ]
				);
			}
		}
		return $status;
	}

	/**
	 * Convert any traversable to an iterator.
	 * This mainly exists to make Phan happy.
	 * @param Traversable $t
	 * @return Iterator
	 */
	public static function getIteratorFromTraversable( Traversable $t ) {
		while ( !( $t instanceof Iterator ) ) {
			// There are only two traversables, Iterator and IteratorAggregate
			/** @var \IteratorAggregate $t */'@phan-var \IteratorAggregate $t';
			$t = $t->getIterator();
		}
		return $t;
	}

}
