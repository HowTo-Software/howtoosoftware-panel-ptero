const gray = {
    50: '#f5f7ff',
    100: '#e8ecfb',
    200: '#cbd2ed',
    300: '#a8b1d2',
    400: '#8891b5',
    500: '#697397',
    600: '#4b5578',
    700: '#1a2140',
    800: '#0d1122',
    900: '#080b17',
};

const htsBlue = {
    50: '#f2f6ff',
    100: '#e8effe',
    200: '#cbdcfc',
    300: '#a5c1f8',
    400: '#83a8f3',
    500: '#6b90ee',
    600: '#5273e6',
    700: '#3d5ce0',
    800: '#3048b8',
    900: '#293e91',
};

const htsPurple = {
    50: '#faf5ff',
    100: '#f3e8ff',
    200: '#e2caff',
    300: '#cda4ff',
    400: '#b069ff',
    500: '#9b4dfb',
    600: '#8b32e0',
    700: '#7126c4',
    800: '#5c2499',
    900: '#4c217b',
};

module.exports = {
    content: [
        './resources/scripts/**/*.{js,ts,tsx}',
    ],
    theme: {
        extend: {
            fontFamily: {
                header: ['"IBM Plex Sans"', '"Roboto"', 'system-ui', 'sans-serif'],
            },
            colors: {
                black: '#05060e',
                // "primary" and "neutral" are deprecated, prefer the use of "blue" and "gray"
                // in new code.
                primary: htsPurple,
                gray: gray,
                neutral: gray,
                blue: htsBlue,
                cyan: htsPurple,
            },
            fontSize: {
                '2xs': '0.625rem',
            },
            transitionDuration: {
                250: '250ms',
            },
            borderColor: theme => ({
                default: theme('colors.neutral.400', 'currentColor'),
            }),
        },
    },
    plugins: [
        require('@tailwindcss/line-clamp'),
        require('@tailwindcss/forms')({
            strategy: 'class',
        }),
    ]
};
