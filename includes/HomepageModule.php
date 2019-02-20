<?php

namespace GrowthExperiments;

use IContextSource;

interface HomepageModule {

	/**
	 * Render this module using data from the given context as needed into the
	 * output provided in the context.
	 *
	 * @param IContextSource $ctx
	 */
	public function render( IContextSource $ctx );
}
