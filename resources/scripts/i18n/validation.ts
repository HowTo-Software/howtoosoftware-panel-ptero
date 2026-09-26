import { setLocale } from 'yup';
import { translateUiText } from './uiTranslations';

// Yup defaults to English even when the rest of the panel follows the browser
// language. Keep form validation messages in the same PT-BR/EN locale.
setLocale({
    mixed: {
        default: () => translateUiText('The value is invalid.'),
        required: () => translateUiText('This field is required.'),
        oneOf: ({ values }) =>
            translateUiText('Choose one of the allowed values: {{values}}.', { values: String(values) }),
        notOneOf: ({ values }) =>
            translateUiText('Choose a value that is not: {{values}}.', { values: String(values) }),
        notType: () => translateUiText('Enter a value of the expected type.'),
    },
    string: {
        length: ({ length }) => translateUiText('Use exactly {{length}} characters.', { length }),
        min: ({ min }) => translateUiText('Use at least {{min}} characters.', { min }),
        max: ({ max }) => translateUiText('Use no more than {{max}} characters.', { max }),
        matches: () => translateUiText('The value has an invalid format.'),
        email: () => translateUiText('Enter a valid email address.'),
        url: () => translateUiText('Enter a valid URL.'),
        trim: () => translateUiText('Remove spaces from the start and end of this value.'),
        lowercase: () => translateUiText('Use lowercase letters for this value.'),
        uppercase: () => translateUiText('Use uppercase letters for this value.'),
    },
    number: {
        min: ({ min }) => translateUiText('Enter a number greater than or equal to {{min}}.', { min }),
        max: ({ max }) => translateUiText('Enter a number less than or equal to {{max}}.', { max }),
        lessThan: ({ less }) => translateUiText('Enter a number less than {{less}}.', { less }),
        moreThan: ({ more }) => translateUiText('Enter a number greater than {{more}}.', { more }),
        positive: () => translateUiText('Enter a positive number.'),
        negative: () => translateUiText('Enter a negative number.'),
        integer: () => translateUiText('Enter a whole number.'),
    },
});
