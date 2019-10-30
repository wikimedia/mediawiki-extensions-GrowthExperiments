<?php

namespace GrowthExperiments\NewcomerTasks\ConfigurationLoader;

use GrowthExperiments\NewcomerTasks\TaskType\TaskType;
use MediaWiki\Linker\LinkTarget;
use MessageLocalizer;
use StatusValue;

/**
 * Helper for retrieving task recommendation configuration.
 */
interface ConfigurationLoader {

	/**
	 * Load configured task types.
	 * @return TaskType[]|StatusValue Set of configured task types, or an error status.
	 */
	public function loadTaskTypes();

	/**
	 * Load the list of templates which prevent a page from ever becoming a task
	 * (meant for things like deletion templates).
	 * @return LinkTarget[]|StatusValue Set of configured templates, or an error status.
	 */
	public function loadTemplateBlacklist();

	/**
	 * Inject the message localizer.
	 * @param MessageLocalizer $messageLocalizer
	 * @internal To be used by ResourceLoader callbacks only.
	 * @note This is an ugly hack. Normal requests use the global RequestContext as a localizer,
	 *   which is a bit of a kitchen sink, but conceptually can be thought of as a service.
	 *   ResourceLoader provides the ResourceLoaderContext, which is not global and can only be
	 *   obtained by code directly invoked by ResourceLoader. The ConfigurationLoader depends
	 *   on whichever of the two is available, so the localizer cannot be injected in the service
	 *   wiring file, and a factory would not make sense conceptually (there should never be
	 *   multiple configuration loaders). So we provide this method so that the ResourceLoader
	 *   callback can finish the dependency injection.
	 */
	public function setMessageLocalizer( MessageLocalizer $messageLocalizer ) : void;

}
