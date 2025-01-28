<?php

namespace GrowthExperiments;

use Wikimedia\NormalizedException\NormalizedException;

/**
 * Used to signal configuration errors which are within the purview of the wiki community
 * (e.g. a wiki page the extension is configured to interact with does not exist).
 * These errors are important enough that the wiki community should eventually be notified
 * about them, and important for debugging, but they are not relevant for site reliability
 * engineering.
 */
class WikiConfigException extends NormalizedException {
}
