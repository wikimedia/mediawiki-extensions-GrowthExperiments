<?php

namespace MediaWiki\Extension\EventBus;

class EventBus {

	/**
	 * @return EventFactory|null
	 */
	public function getFactory() {}

	/**
	 * @param array|string $events
	 * @param int $type
	 * @return array|bool|string
	 */
	public function send( $events, $type = 1 ) {}

}
