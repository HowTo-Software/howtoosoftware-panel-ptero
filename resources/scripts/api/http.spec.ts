import { httpErrorToHuman } from './http';

describe('HTTP errors follow the selected panel language', () => {
    const originalLanguages = navigator.languages;

    afterEach(() => {
        Object.defineProperty(navigator, 'languages', { configurable: true, value: originalLanguages });
    });

    it('translates known backend errors to Brazilian Portuguese', () => {
        Object.defineProperty(navigator, 'languages', { configurable: true, value: ['pt-BR'] });

        expect(
            httpErrorToHuman({
                response: { data: { errors: [{ detail: 'The Steam Workshop URL is invalid.' }] } },
            })
        ).toBe('A URL da Steam Workshop é inválida.');
    });

    it('keeps backend errors in English for English browsers', () => {
        Object.defineProperty(navigator, 'languages', { configurable: true, value: ['en-US'] });

        expect(
            httpErrorToHuman({
                response: { data: { errors: [{ detail: 'The Steam Workshop URL is invalid.' }] } },
            })
        ).toBe('The Steam Workshop URL is invalid.');
    });
});
