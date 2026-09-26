import React from 'react';
import ReactDOM from 'react-dom';
// Initialize language and validation defaults before importing screens that
// build Yup schemas at module load time.
import './i18n';
import './i18n/validation';
import App from '@/components/App';
import { setConfig } from 'react-hot-loader';

// Prevents page reloads while making component changes which
// also avoids triggering constant loading indicators all over
// the place in development.
//
// @see https://github.com/gaearon/react-hot-loader#hook-support
setConfig({ reloadHooks: false });

ReactDOM.render(<App />, document.getElementById('app'));
