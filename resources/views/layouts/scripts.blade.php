{{-- Translation strings shared by React pages and the classic administration screens. --}}
<script>
    window.HowTooTranslations = {
        'Select a Nest': @json(__('Select a Nest')),
        'Select a Nest Egg': @json(__('Select a Nest Egg')),
        'Select a Service Pack': @json(__('Select a Service Pack')),
        'Select a Node': @json(__('Select a Node')),
        'Select a Default Allocation': @json(__('Select a Default Allocation')),
        'Select Additional Allocations': @json(__('Select Additional Allocations')),
        'No Service Pack': @json(__('No Service Pack')),
        'ERROR: Startup Not Defined!': @json(__('ERROR: Startup Not Defined!')),
        'Required': @json(__('Required')),
        'Access in Startup:': @json(__('Access in Startup:')),
        'Validation Rules:': @json(__('Validation Rules:')),
        'Startup Command Variable:': @json(__('Startup Command Variable:')),
        'Input Rules:': @json(__('Input Rules:')),
        'None': @json(__('None')),
        'Select eggs..': @json(__('Select eggs..')),
        'Select nodes..': @json(__('Select nodes..')),
        'Delete Variable': @json(__('Delete Variable')),
        'Delete Egg': @json(__('Delete Egg')),
        'Delete Nest': @json(__('Delete Nest')),
        'Error connecting to node! Check browser console for details.': @json(__('Error connecting to node! Check browser console for details.')),
        'Generated password: ': @json(__('Generated password: ')),
        'Pterodactyl Console': @json(__('Pterodactyl Console')),
        'EULA Acceptance': @json(__('EULA Acceptance')),
        'By pressing I Accept below you are indicating your agreement to the': @json(__('By pressing I Accept below you are indicating your agreement to the')),
        'I do not Accept': @json(__('I do not Accept')),
        'I Accept': @json(__('I Accept')),
        'The EULA for this server has been accepted, restarting server now.': @json(__('The EULA for this server has been accepted, restarting server now.')),
        'An error occurred while attempting to set the EULA as accepted: ': @json(__('An error occurred while attempting to set the EULA as accepted: ')),
        'Whoops!': @json(__('Whoops!')),
        'and one other allocation': @json(__('and one other allocation')),
        'and {{count}} other allocations': @json(__('and {{count}} other allocations'))
    };
    window.howTooTranslate = function (key, variables) {
        var translated = window.HowTooTranslations[key] || key;
        Object.keys(variables || {}).forEach(function (name) {
            translated = translated.split('{{' + name + '}}').join(String(variables[name]));
        });
        return translated;
    };
</script>
