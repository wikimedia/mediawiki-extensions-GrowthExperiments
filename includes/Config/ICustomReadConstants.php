<?php

namespace GrowthExperiments\Config;

interface ICustomReadConstants {
	/**
	 * @var int Bypass any sort of cache, but read from replica DB.
	 *
	 * This is a non-standard flag that's not exposed to classes WikiPageConfigLoader
	 * depends on to read wiki pages.
	 *
	 * Can be used together with standard IDBAccessObject flags.
	 *
	 * @hack 2**20 is a high power of two that's unlikely to be ever added to IDBAccessObject
	 * flags.
	 */
	public const READ_UNCACHED = 2 ** 20;
}
