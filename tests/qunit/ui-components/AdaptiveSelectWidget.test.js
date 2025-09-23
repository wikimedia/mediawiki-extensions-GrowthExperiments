'use strict';

const AdaptiveSelectWidget = require( '../../../modules/ui-components/AdaptiveSelectWidget.js' );

QUnit.module( 'ui-components/AdaptiveSelectWidget.js', QUnit.newMwEnvironment() );

QUnit.test( 'constructor based on isMultiSelect option ', ( assert ) => {
	const options = [ {
		data: 'option1',
		label: 'Option 1',
	}, {
		data: 'option2',
		label: 'Option 2',
	} ];
	const adaptiveSelectWidget = new AdaptiveSelectWidget( { options } );
	const adaptiveSelectWidgetMultiSelect = new AdaptiveSelectWidget( { options, isMultiSelect: true } );
	assert.true( adaptiveSelectWidget.widget instanceof OO.ui.RadioSelectWidget );
	assert.true( adaptiveSelectWidgetMultiSelect.widget instanceof OO.ui.CheckboxMultiselectWidget );
} );

QUnit.test( 'should return the selected options for multi-select widget', ( assert ) => {
	const options = [ {
		data: 'option1',
		label: 'Option 1',
	}, {
		data: 'option2',
		label: 'Option 2',
	}, {
		data: 'option3',
		label: 'Option 3',
	} ];
	const multiSelectWidget = new AdaptiveSelectWidget( { options, isMultiSelect: true } );
	assert.deepEqual( multiSelectWidget.findSelection(), [] );
	const selection = [ 'option1', 'option3' ];
	multiSelectWidget.updateSelection( selection );
	assert.deepEqual( multiSelectWidget.findSelection(), selection );
} );

QUnit.test( 'should return the selected option for single-select widget', ( assert ) => {
	const options = [ {
		data: 'option1',
		label: 'Option 1',
	}, {
		data: 'option2',
		label: 'Option 2',
	} ];
	const selectWidget = new AdaptiveSelectWidget( { options } );
	assert.deepEqual( selectWidget.findSelection(), [] );
	// Support both array and string for single-select widget
	selectWidget.updateSelection( 'option1' );
	assert.deepEqual( selectWidget.findSelection(), [ 'option1' ] );
	selectWidget.updateSelection( [ 'option2' ] );
	assert.deepEqual( selectWidget.findSelection(), [ 'option2' ] );
} );
