export type SupportedLocale = 'en' | 'pt';

/**
 * Select the first supported language in the browser's preference order.
 * Portuguese variants (including pt-BR) share the Brazilian Portuguese
 * catalog used by this panel.
 */
export const resolveBrowserLocale = (languages: readonly string[] | undefined): SupportedLocale => {
    for (const language of languages || []) {
        const baseLanguage = language.toLowerCase().split(/[-_]/, 1)[0];

        if (baseLanguage === 'pt') return 'pt';
        if (baseLanguage === 'en') return 'en';
    }

    return 'en';
};

export const getBrowserLocale = (): SupportedLocale => {
    const languages =
        typeof navigator === 'undefined'
            ? []
            : navigator.languages?.length
            ? navigator.languages
            : [navigator.language];

    return resolveBrowserLocale(languages);
};
