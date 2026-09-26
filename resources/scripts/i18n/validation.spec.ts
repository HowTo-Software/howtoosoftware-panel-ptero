import { object, string } from 'yup';
import './validation';

describe('automatic form validation language', () => {
    const originalLanguages = navigator.languages;

    afterEach(() => {
        Object.defineProperty(navigator, 'languages', { configurable: true, value: originalLanguages });
    });

    it('shows required and length errors in Brazilian Portuguese', () => {
        Object.defineProperty(navigator, 'languages', { configurable: true, value: ['pt-BR'] });
        const schema = object({ email: string().required(), password: string().min(8) });

        try {
            schema.validateSync({ email: '', password: 'short' }, { abortEarly: false });
            throw new Error('Expected validation to fail.');
        } catch (error) {
            expect((error as any).errors).toEqual(['Este campo é obrigatório.', 'Use pelo menos 8 caracteres.']);
        }
    });

    it('shows required and length errors in English', () => {
        Object.defineProperty(navigator, 'languages', { configurable: true, value: ['en-US'] });
        const schema = object({ email: string().required(), password: string().min(8) });

        try {
            schema.validateSync({ email: '', password: 'short' }, { abortEarly: false });
            throw new Error('Expected validation to fail.');
        } catch (error) {
            expect((error as any).errors).toEqual(['This field is required.', 'Use at least 8 characters.']);
        }
    });
});
