import { hu as huLocale } from 'date-fns/locale';

export const isEnglishLocale = window.Laravel.locale === 'en';

export const dateFnsLocale = isEnglishLocale ? undefined : huLocale;
