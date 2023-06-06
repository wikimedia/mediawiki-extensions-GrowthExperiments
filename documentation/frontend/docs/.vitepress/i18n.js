// Import two sets of translations (en, ar) for testing purposes
import * as messagesEN from '../../../../i18n/homepage/en.json';
import * as messagesAR from '../../../../i18n/homepage/ar.json';

export const DEFAULT_LOCALE = 'en';
export const LOCALE_READING_DIRECTION = {
	ar: 'rtl',
	en: 'ltr'
};
export const messages = {
	en: messagesEN.default,
	ar: messagesAR.default
};
export const VALID_LOCALES = Object.keys( messages );
