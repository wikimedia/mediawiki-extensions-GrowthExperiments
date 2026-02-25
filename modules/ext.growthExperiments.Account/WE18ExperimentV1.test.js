'use strict';

const WE18ExperimentV1 = require( './WE18ExperimentV1.js' );

describe( 'WE1.8 Experiment V1', () => {
	it( 'adjusts the username to not trigger the respective warning', () => {
		OO.ui.isMobile = jest.fn( () => true );

		let callback;
		mw.hook = () => ( {
			add( callable ) {
				callback = callable;
			},
		} );
		const $form = $( '<form>' );
		const $input = $( '<input>' ).attr( 'id', 'wpName2' ).appendTo( $form );

		const sut = new WE18ExperimentV1();
		sut.enhanceUsernameInputWithNameAdjustment();

		callback( $form );

		$input.val( ' _ab__cD ' );
		$input.trigger( 'input' );

		expect( $input.val() ).toBe( 'Ab  cD ' );
	} );
} );
