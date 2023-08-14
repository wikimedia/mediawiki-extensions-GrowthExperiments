<template>
	<section class="ext-growthExperiments-PersonalizedPraise">
		<div class="ext-growthExperiments-PersonalizedPraise__info-wrapper">
			<p class="ext-growthExperiments-PersonalizedPraise__intro">
				{{ $i18n( 'growthexperiments-mentor-dashboard-personalized-praise-intro' ).text() }}
			</p>
			<c-popover
				class="ext-growthExperiments-PersonalizedPraise__info-box"
				:icon="cdxIconInfo"
				:header-icon="cdxIconInfo"
				:header-icon-label="$i18n( 'growthexperiments-mentor-dashboard-personalized-praise-info-icon-label' ).text()"
				:title="$i18n( 'growthexperiments-mentor-dashboard-personalized-praise-info-headline' ).text()"
				:close-icon="cdxIconClose"
				:close-icon-label="$i18n( 'growthexperiments-info-tooltip-close-label' ).text()"
			>
				<template #trigger="{ onClick }">
					<cdx-button
						weight="quiet"
						class="ext-growthExperiments-PersonalizedPraise__info-button"
						:aria-label="$i18n( 'growthexperiments-mentor-dashboard-personalized-praise-info-icon-label' ).text()"
						@click="onClick"
					>
						<cdx-icon :icon="cdxIconInfo"></cdx-icon>
					</cdx-button>
				</template>
				<template #content>
					<div class="ext-growthExperiments-PersonalizedPraise__info-content">
						<p>{{ $i18n( 'growthexperiments-mentor-dashboard-personalized-praise-info-par1' ).text() }}</p>
						<p>{{ $i18n( 'growthexperiments-mentor-dashboard-personalized-praise-info-par2' ).text() }}</p>
						<p>{{ $i18n( 'growthexperiments-mentor-dashboard-personalized-praise-info-par3' ).text() }}</p>
					</div>
				</template>
			</c-popover>
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
const { inject } = require( 'vue' );
const { CdxIcon, CdxButton } = require( '@wikimedia/codex' );
const PersonalizedPraiseSettings = require( './PersonalizedPraiseSettings.vue' );
const PersonalizedPraisePagination = require( './PersonalizedPraisePagination.vue' );
const UserCard = require( './UserCard.vue' );
const NoResults = require( './NoResults.vue' );
const CPopover = require( '../../../vue-components/CPopover.vue' );
const { cdxIconInfo, cdxIconClose } = require( '../../../vue-components/icons.json' );

// @vue/component
module.exports = exports = {
	compatConfig: { MODE: 3 },
	compilerOptions: { whitespace: 'condense' },
	components: {
		CdxIcon,
		CdxButton,
		CPopover,
		PersonalizedPraiseSettings,
		PersonalizedPraisePagination,
		UserCard,
		NoResults
	},
	setup() {
		const log = inject( '$log' );
		return {
			log,
			cdxIconInfo,
			cdxIconClose
		};
	},
	computed: {
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

	// NOTE: This is needed to make the selector more specific than .ext-growthExperiments-Popover
	// from CPopover.vue.
	& &__info-box {
		display: inline-block;
	}

	&__info-button {
		.codex-icon-only-button( @color-subtle, 24px);
	}

	&__info-content {
		max-width: 410px;
	}
}
</style>
