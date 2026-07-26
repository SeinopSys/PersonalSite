type Preference = 'system' | 'light' | 'dark';

const COOKIE_NAME = 'theme';
const ORDER: Preference[] = ['system', 'light', 'dark'];
const ICONS: Record<Preference, string> = {
  system: 'circle-half-stroke',
  light: 'sun',
  dark: 'moon',
};

const media = window.matchMedia('(prefers-color-scheme: dark)');

function getPreference(): Preference {
  const match = document.cookie.match(/(?:^|; )theme=([^;]*)/);
  const value = match ? decodeURIComponent(match[1]) : '';
  return ORDER.includes(value as Preference) ? (value as Preference) : 'system';
}

function setPreference(pref: Preference): void {
  const oneYear = 60 * 60 * 24 * 365;
  document.cookie = `${COOKIE_NAME}=${pref}; path=/; max-age=${oneYear}; SameSite=Lax`;
}

function resolve(pref: Preference): 'light' | 'dark' {
  return pref === 'system' ? (media.matches ? 'dark' : 'light') : pref;
}

function render(pref: Preference): void {
  document.documentElement.setAttribute('data-bs-theme', resolve(pref));

  const $button = $('#theme-toggle');
  $button
    .attr('title', window.Laravel.theme[pref])
    .attr('aria-label', window.Laravel.theme[pref])
    .find('.fa')
    .attr('class', `fa fa-${ICONS[pref]}`);
}

export const Theme = {
  init(): void {
    render(getPreference());

    $('#theme-toggle').on('click', () => {
      const next = ORDER[(ORDER.indexOf(getPreference()) + 1) % ORDER.length];
      setPreference(next);
      render(next);
    });

    media.addEventListener('change', () => {
      if (getPreference() === 'system') render('system');
    });
  },
};
