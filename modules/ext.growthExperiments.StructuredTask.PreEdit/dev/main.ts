import { createApp } from 'vue';
import '@wikimedia/codex/dist/codex.style.css';
import '@wikimedia/codex-design-tokens/theme-wikimedia-ui-root.css';
import '@wikimedia/codex-design-tokens/theme-wikimedia-ui.css';
// @ts-expect-error importing types from .vue file does not work yet?
import App from '../App.vue';
import i18nPlugin from './i18nPlugin';
import loggerPlugin from '../../vue-components/plugins/logger';

const devApp = createApp( App, {
	taskType: 'revise-tone',
} );

devApp.use( loggerPlugin, {
	mode: 'dev',
	logger: console,
} );
devApp.use( i18nPlugin );

devApp.mount( '#app' );
