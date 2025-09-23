const handleClickOutsideOnElement = ( el, event, fn ) => {
	// here we check that click was outside the el and its children
	if ( !( el === event.target || el.contains( event.target ) ) ) {
		// and if it did, call method provided in attribute value
		fn();
	}
};
module.exports = exports = {
	beforeMount: ( el, binding ) => {
		el.clickOutsideEvent = ( event ) => handleClickOutsideOnElement( el, event, binding.value );
		// set useCapture to true to prevent not reacting to a fired event
		// which propagation has been (wrongly) stopped.
		document.addEventListener( 'click', el.clickOutsideEvent, true );
	},
	// Allows to react to updates on the binding.value. Necessary to be able
	// to use computed properties eg: v-click-outside="someReactiveProp ? someFunction : noop"
	updated: ( el, binding ) => {
		document.removeEventListener( 'click', el.clickOutsideEvent, true );
		el.clickOutsideEvent = ( event ) => handleClickOutsideOnElement( el, event, binding.value );
		// set useCapture to true to prevent not reacting to a fired event
		// which propagation has been (wrongly) stopped.
		document.addEventListener( 'click', el.clickOutsideEvent, true );
	},
	unmounted: ( el ) => {
		document.removeEventListener( 'click', el.clickOutsideEvent, true );
	},
};
