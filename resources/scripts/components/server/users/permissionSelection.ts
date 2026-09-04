export const areAllPermissionsSelected = (selected: string[], available: string[]): boolean =>
    available.length > 0 && available.every((permission) => selected.includes(permission));

export const toggleAllPermissions = (selected: string[], available: string[]): string[] => {
    if (areAllPermissionsSelected(selected, available)) {
        return selected.filter((permission) => !available.includes(permission));
    }

    return [...selected, ...available.filter((permission) => !selected.includes(permission))];
};
