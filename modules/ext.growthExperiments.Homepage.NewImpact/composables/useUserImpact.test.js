const { mount, flushPromises } = require( '@vue/test-utils' );
const { defineComponent } = require( 'vue' );
const useUserImpact = require( './useUserImpact.js' );

test( 'the data is computed', ( done ) => {
	global.mw.Rest.prototype.get = jest.fn( () => Promise.resolve( {
		lastEditTimestamp: 1663761521,
		editCountByNamespace: {
			3: 12,
			4: 9
		},
		editCountByDay: {
			'2002-03-14': 5,
			'2002-03-15': 5,
			'2002-03-16': 5,
			'2002-03-19': 5,
			'2002-03-20': 5,
			'2002-03-22': 2
		}
	} ) );
	const TestComponent = defineComponent( {
		props: {
			userId: {
				type: Number,
				default: 1
			},
			timeFrame: {
				type: Number,
				default: 30
			}
		},
		setup( props ) {
			const { data, error } = useUserImpact( props.userId, props.timeFrame );
			return {
				data,
				error
			};
		}
	} );

	const wrapper = mount( TestComponent );
	expect( wrapper.vm.data ).toBe( undefined );
	flushPromises().then( () => {
		expect( wrapper.vm.data.totalEditsCount ).toBe( 21 );
		expect( wrapper.vm.data.bestStreak.count ).toBe( 3 );
		expect( wrapper.vm.data.bestStreak.values ).toEqual( [
			'2002-03-14',
			'2002-03-15',
			'2002-03-16'
		] );
		expect( wrapper.vm.data.bestStreak.range ).toEqual( [
			'2002-03-14',
			'2002-03-16'
		] );
		expect( wrapper.vm.data.contributions.count ).toBe( 0 );
		done();
	} );
} );
