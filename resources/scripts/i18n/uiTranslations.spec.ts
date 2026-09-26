import fs from 'fs';
import path from 'path';
import ts from 'typescript';
import { hasUiTranslation, translateUiText } from './uiTranslations';

const collectSourceFiles = (directory: string): string[] =>
    fs.readdirSync(directory, { withFileTypes: true }).flatMap((entry) => {
        const entryPath = path.join(directory, entry.name);

        return entry.isDirectory() ? collectSourceFiles(entryPath) : /\.tsx?$/.test(entry.name) ? [entryPath] : [];
    });

describe('panel interface translations', () => {
    const originalLanguages = navigator.languages;

    afterEach(() => {
        Object.defineProperty(navigator, 'languages', { configurable: true, value: originalLanguages });
    });

    it('translates English interface text into Brazilian Portuguese', () => {
        Object.defineProperty(navigator, 'languages', { configurable: true, value: ['pt-BR'] });

        expect(translateUiText('Dashboard')).toBe('Painel');
        expect(translateUiText('Create schedule')).toBe('Criar agendamento');
    });

    it('translates Portuguese interface text into English', () => {
        Object.defineProperty(navigator, 'languages', { configurable: true, value: ['en-US'] });

        expect(translateUiText('Seus servidores')).toBe('Your servers');
        expect(translateUiText('Abrir servidor')).toBe('Open server');
    });

    it('keeps product names and other unmapped values intact', () => {
        Object.defineProperty(navigator, 'languages', { configurable: true, value: ['pt-BR'] });

        expect(translateUiText('Project Zomboid')).toBe('Project Zomboid');
    });

    it('substitutes dynamic values literally without altering replacement markers', () => {
        Object.defineProperty(navigator, 'languages', { configurable: true, value: ['pt-BR'] });

        expect(translateUiText('File actions for {{name}}', { name: 'Save $& data' })).toBe(
            'Ações do arquivo Save $& data'
        );
    });

    it('has a catalog entry for every static interface phrase passed to the translator', () => {
        const neutralValues = new Set([
            '/cache/Server/Pterodactyl.ini · Malaio Code',
            '000000',
            '0 0 * * MON',
            '/home/container/',
            'LF',
            'Minecraft®',
            'Pterodactyl',
            'PROJECT ZOMBOID',
            'Project Zomboid',
            'save',
            'SHA256:',
            'UTF-8',
        ]);
        const componentsRoot = path.resolve(__dirname, '../components');
        const files = collectSourceFiles(componentsRoot);
        const missing = new Set<string>();

        files.forEach((file) => {
            const sourceFile = ts.createSourceFile(file, fs.readFileSync(file, 'utf8'), ts.ScriptTarget.Latest, true);

            const visit = (node: ts.Node): void => {
                if (
                    ts.isCallExpression(node) &&
                    node.expression.getText(sourceFile) === 'translateUiText' &&
                    node.arguments.length > 0 &&
                    (ts.isStringLiteral(node.arguments[0]) || ts.isNoSubstitutionTemplateLiteral(node.arguments[0]))
                ) {
                    const phrase = node.arguments[0].text;
                    if (!hasUiTranslation(phrase) && !neutralValues.has(phrase)) missing.add(phrase);
                }

                ts.forEachChild(node, visit);
            };

            visit(sourceFile);
        });

        expect([...missing]).toEqual([]);
    });

    it('does not leave readable JSX text or accessibility labels outside the translator', () => {
        const untranslated = new Set<string>();
        const neutralText = new Set(['&nbsp;|&nbsp;', '/home/container/']);
        const componentsRoot = path.resolve(__dirname, '../components');

        const isTechnicalMarkup = (node: ts.Node): boolean => {
            let current: ts.Node | undefined = node.parent;
            while (current) {
                if (ts.isJsxElement(current)) {
                    const tag = current.openingElement.tagName.getText();
                    if (tag === 'code' || tag === 'pre' || tag === 'kbd') return true;
                }
                current = current.parent;
            }

            return false;
        };

        collectSourceFiles(componentsRoot).forEach((file) => {
            const sourceFile = ts.createSourceFile(file, fs.readFileSync(file, 'utf8'), ts.ScriptTarget.Latest, true);
            const visit = (node: ts.Node): void => {
                if (ts.isJsxText(node)) {
                    const phrase = node.text.trim();
                    const isEntityOnly = /^(?:&(?:nbsp|mdash|ndash|infin|reg|copy);\s*)+$/.test(phrase);
                    if (
                        /[A-Za-z]{2}/.test(phrase) &&
                        !isEntityOnly &&
                        !neutralText.has(phrase) &&
                        !isTechnicalMarkup(node)
                    ) {
                        untranslated.add(
                            `${file}:${
                                sourceFile.getLineAndCharacterOfPosition(node.getStart(sourceFile)).line + 1
                            }: ${phrase}`
                        );
                    }
                }

                if (
                    ts.isJsxAttribute(node) &&
                    ['title', 'placeholder', 'alt', 'aria-label'].includes(node.name.getText(sourceFile))
                ) {
                    const value = node.initializer;
                    if (value && ts.isStringLiteral(value) && value.text.trim() && /[A-Za-z]{2}/.test(value.text)) {
                        untranslated.add(
                            `${file}:${
                                sourceFile.getLineAndCharacterOfPosition(node.getStart(sourceFile)).line + 1
                            }: ${node.name.getText(sourceFile)}=${value.text}`
                        );
                    }
                }

                ts.forEachChild(node, visit);
            };

            visit(sourceFile);
        });

        expect([...untranslated]).toEqual([]);
    });
});
