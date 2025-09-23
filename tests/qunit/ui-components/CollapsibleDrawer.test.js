'use strict';

const CollapsibleDrawer = require( '../../../modules/ui-components/CollapsibleDrawer.js' );

QUnit.module( 'ui-components/CollapsibleDrawer.js', QUnit.newMwEnvironment() );

const promiseKeys = [ 'opening', 'opened', 'closing', 'closed' ];
const jQueryElementKeys = [ '$content', '$element' ];

QUnit.test( 'constructor with intro content', ( assert ) => {
	const collapsibleDrawer = new CollapsibleDrawer( {
		content: [ 'foo' ],
		$introContent: $( '<div>' ).text( 'Intro' ),
		headerText: 'Header',
	} );
	assert.true( collapsibleDrawer.isIntroContentHidden );
	assert.true( collapsibleDrawer.isContentHidden );
	promiseKeys.forEach( ( key ) => {
		assert.strictEqual( typeof collapsibleDrawer[ key ].promise, 'function' );
	} );
	[ '$introContent', ...jQueryElementKeys ].forEach( ( key ) => {
		assert.strictEqual( typeof collapsibleDrawer[ key ], 'object' );
	} );
} );

QUnit.test( 'constructor without intro content', ( assert ) => {
	const collapsibleDrawer = new CollapsibleDrawer( {
		content: [ 'foo' ],
		headerText: 'Header',
	} );
	assert.true( collapsibleDrawer.isIntroContentHidden );
	assert.true( collapsibleDrawer.isContentHidden );
	promiseKeys.forEach( ( key ) => {
		assert.strictEqual( typeof collapsibleDrawer[ key ].promise, 'function' );
	} );
	jQueryElementKeys.forEach( ( key ) => {
		assert.strictEqual( typeof collapsibleDrawer[ key ], 'object' );
	} );
} );

QUnit.test( 'should resolve opening promise when the drawer is opening', ( assert ) => {
	const collapsibleDrawer = new CollapsibleDrawer( {
		content: [ 'foo' ],
		headerText: 'Header',
	} );
	collapsibleDrawer.open();
	assert.strictEqual( collapsibleDrawer.opening.state(), 'resolved' );
	assert.strictEqual( collapsibleDrawer.opened.state(), 'pending' );
} );

QUnit.test( 'should resolve closing promise when the drawer is closing', ( assert ) => {
	const collapsibleDrawer = new CollapsibleDrawer( {
		content: [ 'foo' ],
		headerText: 'Header',
	} );
	collapsibleDrawer.expand();
	collapsibleDrawer.close();
	assert.strictEqual( collapsibleDrawer.closing.state(), 'resolved' );
	assert.strictEqual( collapsibleDrawer.closed.state(), 'pending' );
} );

QUnit.test( 'should resolve closing and closed promises when close is called when the drawer is collapsed', ( assert ) => {
	const collapsibleDrawer = new CollapsibleDrawer( {
		content: [ 'foo' ],
		headerText: 'Header',
	} );
	collapsibleDrawer.close();
	assert.strictEqual( collapsibleDrawer.closing.state(), 'resolved' );
	assert.strictEqual( collapsibleDrawer.closed.state(), 'resolved' );
} );

QUnit.test( 'should set isContentHidden to true when it\'s collapsed', ( assert ) => {
	const collapsibleDrawer = new CollapsibleDrawer( {
		content: [ 'foo' ],
		headerText: 'Header',
	} );
	collapsibleDrawer.collapse();
	assert.true( collapsibleDrawer.isContentHidden );
	assert.strictEqual( collapsibleDrawer.chevronIcon.getIcon(), 'collapse' );
} );

QUnit.test( 'should set isContentHidden to false when it\'s expanded', ( assert ) => {
	const collapsibleDrawer = new CollapsibleDrawer( {
		content: [ 'foo' ],
		headerText: 'Header',
	} );
	collapsibleDrawer.expand();
	assert.false( collapsibleDrawer.isContentHidden );
	assert.strictEqual( collapsibleDrawer.chevronIcon.getIcon(), 'expand' );
} );
