<template>
	<div>
		<revise-tone-onboarding :pref-name="prefName"></revise-tone-onboarding>
	</div>
</template>

<script>
const ReviseToneOnboarding = require( './revisetone/ReviseToneOnboarding.vue' );
const { defineComponent } = require( 'vue' );

// @vue/component
module.exports = defineComponent( {
	name: 'StructuredTaskOnboarding',
	components: { ReviseToneOnboarding },
	props: {
		prefName: {
			type: [ String, null ],
			default: null,
		},
	},
} );
</script>
