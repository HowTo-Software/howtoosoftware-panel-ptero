const fs = require('fs');
const path = require('path');

describe('SafeMarkdown', () => {
    it('uses the safe Markdown pipeline without enabling raw HTML', () => {
        const source = fs.readFileSync(path.join(__dirname, 'SafeMarkdown.tsx'), 'utf8');

        expect(source).toContain("from 'react-markdown'");
        expect(source).toContain("from 'remark-gfm'");
        expect(source).toContain('remarkPlugins={[remarkGfm]}');
        expect(source).toContain("rel={'noreferrer noopener'}");
        expect(source).not.toContain('rehypeRaw');
        expect(source).not.toContain('dangerouslySetInnerHTML');
    });
});
