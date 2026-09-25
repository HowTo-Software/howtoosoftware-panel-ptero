import React from 'react';
import Icon from '@/components/elements/Icon';
import { IconDefinition } from '@fortawesome/free-solid-svg-icons';
import classNames from 'classnames';
import styles from './style.module.css';
import useFitText from 'use-fit-text';
import CopyOnClick from '@/components/elements/CopyOnClick';

interface StatBlockProps {
    title: string;
    copyOnClick?: string;
    color?: string | undefined;
    iconColor?: string;
    iconBackground?: string;
    icon: IconDefinition;
    children: React.ReactNode;
    className?: string;
}

export default ({
    title,
    copyOnClick,
    icon,
    color,
    iconColor,
    iconBackground,
    className,
    children,
}: StatBlockProps) => {
    const { fontSize, ref } = useFitText({ minFontSize: 8, maxFontSize: 500 });

    return (
        <CopyOnClick text={copyOnClick}>
            <div className={classNames(styles.stat_block, 'bg-gray-600', className)}>
                <div className={classNames(styles.status_bar, color || 'bg-gray-700')} />
                <div
                    className={classNames(styles.icon, !iconBackground && (color || 'bg-gray-700'))}
                    style={iconBackground ? { backgroundColor: iconBackground } : undefined}
                >
                    <Icon
                        icon={icon}
                        className={classNames(!iconColor && 'text-gray-100')}
                        style={iconColor ? { color: iconColor } : undefined}
                    />
                </div>
                <div className={'flex flex-col justify-center overflow-hidden w-full'}>
                    <p className={'font-header font-medium leading-tight text-xs md:text-sm text-gray-200'}>{title}</p>
                    <div
                        ref={ref}
                        className={'h-[1.75rem] w-full font-semibold text-gray-50 truncate'}
                        style={{ fontSize }}
                    >
                        {children}
                    </div>
                </div>
            </div>
        </CopyOnClick>
    );
};
