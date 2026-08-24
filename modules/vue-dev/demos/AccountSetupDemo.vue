<template>
	<account-setup></account-setup>
</template>

<script>
const { defineComponent } = require( 'vue' );
const AccountSetup = require( '../../ext.growthExperiments.AccountSetup/AccountSetup.vue' );

// @vue/component
module.exports = defineComponent( {
	name: 'AccountSetupDemo',
	components: { AccountSetup },
} );
</script>
