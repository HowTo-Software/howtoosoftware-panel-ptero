import { areAllPermissionsSelected, toggleAllPermissions } from '@/components/server/users/permissionSelection';

describe('subuser permission selection', () => {
    const available = ['control.console', 'control.start', 'control.stop'];

    it('selects every available permission without duplicating an existing selection', () => {
        expect(toggleAllPermissions(['control.console'], available)).toEqual(available);
    });

    it('clears only permissions the current user is allowed to edit', () => {
        expect(toggleAllPermissions([...available, 'admin.hidden'], available)).toEqual(['admin.hidden']);
    });

    it('tracks manual permission changes', () => {
        expect(areAllPermissionsSelected(available, available)).toBe(true);
        expect(areAllPermissionsSelected(available.slice(0, 2), available)).toBe(false);
    });
});
