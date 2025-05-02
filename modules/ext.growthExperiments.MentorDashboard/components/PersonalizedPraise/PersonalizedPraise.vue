<template>
	<section class="ext-growthExperiments-PersonalizedPraise">
		<div class="ext-growthExperiments-PersonalizedPraise__info-wrapper">
			<p class="ext-growthExperiments-PersonalizedPraise__intro">
				{{ $i18n( 'growthexperiments-mentor-dashboard-personalized-praise-intro' ).text() }}
			</p>
			<cdx-toggle-button
				ref="infoToggleButton"
				v-model="showPopover"
				:aria-label="$i18n( 'growthexperiments-mentor-dashboard-personalized-praise-info-icon-label' ).text()"
				:quiet="true"
				class="ext-growthExperiments-PersonalizedPraise__info-button"
			>
				<cdx-icon :icon="cdxIconInfo"></cdx-icon>
			</cdx-toggle-button>
			<!--
				CdxPopover uses the floating-ui library in a way that causes infinite recursion when
				mounted in JSDOM. Shallow rendering the component in turn fails if an anchor reference
				is provided, because vue-test-utils is unable to stringify the HTML element held within
				the ref. Work around the situation by using shallow rendering in tests and use a well-known
				window name to avoid passing the anchor in this case.
			-->
			<cdx-popover
				v-model:open="showPopover"
				:anchor="windowName !== 'PersonalizedPraiseJestTests' ? infoToggleButton : null"
				placement="bottom-start"
				:render-in-place="true"
				:title="$i18n( 'growthexperiments-mentor-dashboard-personalized-praise-info-headline' ).text()"
				:use-close-button="true"
				:icon="cdxIconInfo"
			>
				<div class="ext-growthExperiments-PersonalizedPraise__info-content">
					<p>{{ $i18n( 'growthexperiments-mentor-dashboard-personalized-praise-info-par1' ).text() }}</p>
					<p>{{ $i18n( 'growthexperiments-mentor-dashboard-personalized-praise-info-par2' ).text() }}</p>
					<p>{{ $i18n( 'growthexperiments-mentor-dashboard-personalized-praise-info-par3' ).text() }}</p>
				</div>
			</cdx-popover>
		</div>
		<p
			v-i18n-html:growthexperiments-mentor-dashboard-personalized-praise-metrics="[
				$filters.convertNumber( settings.minEdits ),
				$filters.convertNumber( settings.days )
			]"
		>
		</p>
		<personalized-praise-settings
			:settings-data="settings"
			@update:settings="onSettingsUpdate"
		></personalized-praise-settings>
		<no-results v-if="!hasData"></no-results>
		<user-card
			v-if="hasData"
			:mentee="mentee"
			@skip="onMenteeSkipped"
		></user-card>
		<personalized-praise-pagination
			v-if="hasData"
			:current-page="currentPage"
			:total-pages="totalPages"
			@previous="previousMentee"
			@next="nextMentee"
		></personalized-praise-pagination>
	</section>
</template>

<script>
const { inject, ref } = require( 'vue' );
const { CdxIcon, CdxToggleButton, CdxPopover } = require( '@wikimedia/codex' );
const PersonalizedPraiseSettings = require( './PersonalizedPraiseSettings.vue' );
const PersonalizedPraisePagination = require( './PersonalizedPraisePagination.vue' );
const UserCard = require( './UserCard.vue' );
const NoResults = require( './NoResults.vue' );
const { cdxIconInfo } = require( '../../../vue-components/icons.json' );

// @vue/component
module.exports = exports = {
	compilerOptions: { whitespace: 'condense' },
	components: {
		CdxIcon,
		CdxToggleButton,
		CdxPopover,
		PersonalizedPraiseSettings,
		PersonalizedPraisePagination,
		UserCard,
		NoResults
	},
	setup() {
		const log = inject( '$log' );
		const showPopover = ref( false );
		const infoToggleButton = ref( null );
		return {
			log,
			cdxIconInfo,
			showPopover,
			infoToggleButton
		};
	},
	computed: {
		windowName() {
			return window.name;
		},
		currentPage() {
			return this.$store.getters[ 'praiseworthyMentees/currentPage' ];
		},
		totalPages() {
			return this.$store.getters[ 'praiseworthyMentees/totalPages' ];
		},
		hasData() {
			return this.mentee !== undefined;
		},
		mentee() {
			return this.$store.getters[ 'praiseworthyMentees/mentee' ];
		},
		settings() {
			return this.$store.getters[ 'praiseworthyMentees/settings' ];
		}
	},
	methods: {
		previousMentee() {
			this.log( 'pp-prev-page', {
				mentee: JSON.stringify( this.mentee )
			} );
			this.$store.dispatch( 'praiseworthyMentees/previousPage' );
		},
		nextMentee() {
			this.log( 'pp-next-page', {
				mentee: JSON.stringify( this.mentee )
			} );
			this.$store.dispatch( 'praiseworthyMentees/nextPage' );
		},
		onSettingsUpdate( settings ) {
			this.$store.dispatch( 'praiseworthyMentees/saveSettings', settings );
		},
		onMenteeSkipped( mentee ) {
			this.$store.dispatch( 'praiseworthyMentees/removeMentee', mentee );
		}
	},
	created() {
		this.$store.dispatch( 'praiseworthyMentees/fetchMentees' );
		this.log( 'impression', {
			totalMentees: this.totalPages,
			mentee: JSON.stringify( this.mentee )
		} );
	}
};
</script>

<style lang="less">
@import 'mediawiki.skin.variables.less';
@import '../../../utils/mixins.less';

/* stylelint-disable-next-line selector-class-pattern */
.growthexperiments-mentor-dashboard-module-personalized-praise {
	position: relative;
}

.ext-growthExperiments-PersonalizedPraise {
	&__intro {
		display: inline;
	}

	&__info-button {
		display: inline-block;
		.codex-icon-only-button( @color-subtle, 24px);
	}

	&__info-content {
		max-width: 410px;
	}
}
</style>
