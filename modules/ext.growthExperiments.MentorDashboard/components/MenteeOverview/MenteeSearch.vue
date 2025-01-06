<template>
	<div class="mentee-search">
		<cdx-lookup
			v-model:selected="selection"
			:placeholder="$i18n( 'growthexperiments-mentor-dashboard-mentee-overview-search-placeholder' )"
			:clearable="true"
			:start-icon="cdxIconSearch"
			:menu-items="menuItems"
			@input="onInput"
			@update:selected="$emit( 'update:selected', $event )"
		>
		</cdx-lookup>
	</div>
</template>

<script>
const { ref } = require( 'vue' );
const { CdxLookup } = require( '@wikimedia/codex' );
const { cdxIconSearch } = require( '../../../vue-components/icons.json' );

// @vue/component
module.exports = exports = {
	name: 'MenteeSearch',
	compilerOptions: { whitespace: 'condense' },
	components: { CdxLookup },
	emits: [ 'update:selected' ],
	setup() {
		const selection = ref( '' );
		const searchTerm = ref( '' );

		return {
			cdxIconSearch,
			selection,
			searchTerm
		};
	},
	computed: {
		menuItems() {
			return this.$store.getters[ 'menteesSearch/allMentees' ];
		}
	},
	methods: {
		// TODO: this should be debounced.
		onInput( value ) {
			// Do nothing if we have no input.
			if ( !value ) {
				return;
			}

			this.searchTerm = value;
			this.$store.dispatch( 'menteesSearch/findMenteesByTextQuery', { query: value } );
		}
	}
};
</script>
