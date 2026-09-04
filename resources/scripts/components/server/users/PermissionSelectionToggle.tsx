import React, { useCallback } from 'react';
import { useField } from 'formik';
import { useTranslation } from 'react-i18next';
import tw from 'twin.macro';
import Button from '@/components/elements/Button';
import { areAllPermissionsSelected, toggleAllPermissions } from '@/components/server/users/permissionSelection';

interface Props {
    disabled: boolean;
    permissions: string[];
}

const PermissionSelectionToggle = ({ disabled, permissions }: Props) => {
    const { t } = useTranslation('strings');
    const [{ value }, , { setValue }] = useField<string[]>('permissions');
    const allSelected = areAllPermissionsSelected(value, permissions);

    const toggle = useCallback(() => {
        setValue(toggleAllPermissions(value, permissions));
    }, [permissions, setValue, value]);

    return (
        <div css={tw`flex justify-end mb-4`}>
            <Button
                type={'button'}
                size={'small'}
                isSecondary
                disabled={disabled || permissions.length === 0}
                onClick={toggle}
            >
                {t(allSelected ? 'subuser_permissions.clear_all' : 'subuser_permissions.select_all')}
            </Button>
        </div>
    );
};

export default PermissionSelectionToggle;
