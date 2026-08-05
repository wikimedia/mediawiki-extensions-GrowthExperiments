const { reactive } = require( 'vue' );

// Shared open state, flipped from the server-rendered controls in init.js; the
// dialog components own the rest. `open` drives the question poster, `confirm`
// the opt-out / opt-in confirmation, `about` the about-mentorship info dialog.
module.exports = reactive( { open: false, confirm: false, about: false } );
