const fs = require('fs');
const path = require('path');

describe('AiAssistantContainer form behavior', () => {
    it('prevents native form submission and does not reload the page', () => {
        const source = fs.readFileSync(path.join(__dirname, 'AiAssistantContainer.tsx'), 'utf8');

        expect(source).toContain('event.preventDefault();');
        expect(source).toContain('<Composer onSubmit={submit}>');
        expect(source).toContain("type={'submit'}");
        expect(source).not.toContain('window.location.reload');
        expect(source).not.toMatch(/<Composer[^>]+action=/);
    });
});
