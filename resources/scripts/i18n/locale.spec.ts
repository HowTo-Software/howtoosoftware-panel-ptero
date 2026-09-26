import { resolveBrowserLocale } from './locale';

describe('browser language selection', () => {
    it('uses the first supported browser preference', () => {
        expect(resolveBrowserLocale(['en-US', 'pt-BR'])).toBe('en');
        expect(resolveBrowserLocale(['pt-BR', 'en-US'])).toBe('pt');
    });

    it('maps Brazilian Portuguese and other Portuguese variants to the Portuguese catalog', () => {
        expect(resolveBrowserLocale(['pt-BR'])).toBe('pt');
        expect(resolveBrowserLocale(['pt-PT'])).toBe('pt');
    });

    it('falls back to English when the browser has no supported language', () => {
        expect(resolveBrowserLocale(['es-MX', 'fr-FR'])).toBe('en');
        expect(resolveBrowserLocale([])).toBe('en');
    });
});
