module.exports = exports = {
	beforeMount: ( el, binding ) => {
		el.clickOutsideEvent = ( event ) => {
			// here I check that click was outside the el and its children
			if ( !( el === event.target || el.contains( event.target ) ) ) {
				// and if it did, call method provided in attribute value
				binding.value();
			}
		};
		document.addEventListener( 'click', el.clickOutsideEvent );
	},
	unmounted: ( el ) => {
		document.removeEventListener( 'click', el.clickOutsideEvent );
	}
};
