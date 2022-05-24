<?php

namespace GrowthExperiments;

use LogicException;
use MediaWiki\ResourceLoader as RL;

/**
 * Like a normal file module, but can provide dynamically evaluated LESS variables
 * via a callback, defined in the field 'lessCallback'. As with JS callbacks, the result
 * should not depend on anything but the ResourceLoader context.
 *
 * Basically, this reimplements the old ResourceLoaderGetLessVars hook.
 */
class ResourceLoaderFileModuleWithLessVars extends RL\FileModule {

	/** @var callable|null */
	protected $lessCallback;

	/**
	 * @param array $options See RL\FileModule. Also:
	 *   - lessCallback: callable which takes a RL\Context
	 *     and returns an array of LESS variables (name => value).
	 *     The variables must not depend on anything other than the context.
	 * @param string|null $localBasePath See RL\FileModule.
	 * @param string|null $remoteBasePath See RL\FileModule.
	 */
	public function __construct(
		array $options = [], $localBasePath = null, $remoteBasePath = null
	) {
		parent::__construct( $options, $localBasePath, $remoteBasePath );
		if ( isset( $options['lessCallback'] ) ) {
			$this->lessCallback = $options['lessCallback'];
		}
	}

	/** @inheritDoc */
	protected function getLessVars( RL\Context $context ) {
		$lessVars = parent::getLessVars( $context );
		if ( $this->lessCallback ) {
			if ( !is_callable( $this->lessCallback ) ) {
				$msg = "Invalid 'lessCallback' for module '{$this->getName()}'.";
				$this->getLogger()->error( $msg );
				throw new LogicException( $msg );
			}
			$lessVars = array_merge( $lessVars, ( $this->lessCallback )( $context ) );
		}
		return $lessVars;
	}

}
