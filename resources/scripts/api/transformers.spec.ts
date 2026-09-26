import { rawDataToFileObject } from '@/api/transformers';
import { FractalResponseData } from '@/api/http';

const file = (name: string, mimetype: string) =>
    rawDataToFileObject({
        attributes: {
            is_file: true,
            name,
            mode: '0644',
            mode_bits: '0644',
            size: 1024,
            is_symlink: false,
            mimetype,
            created_at: '2026-01-01T00:00:00Z',
            modified_at: '2026-01-01T00:00:00Z',
        },
        object: 'file',
    } as unknown as FractalResponseData);

describe('file archive detection', () => {
    it('recognizes ZIP files even when the server reports a generic MIME type', () => {
        expect(file('world.ZIP', 'application/octet-stream').isArchiveType()).toBe(true);
    });

    it('recognizes ZIP MIME types and does not treat directories as archives', () => {
        expect(file('world.data', 'application/x-zip-compressed').isArchiveType()).toBe(true);
        expect(file('world.zip', 'application/octet-stream').isArchiveType()).toBe(true);

        const directory = file('folder.zip', 'application/zip');
        directory.isFile = false;
        expect(directory.isArchiveType()).toBe(false);
    });
});
