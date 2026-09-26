import React from 'react';
import classNames from 'classnames';
import styles from '@/components/server/console/style.module.css';

interface ChartBlockProps {
    title: string;
    legend?: React.ReactNode;
    children: React.ReactNode;
}

export default ({ title, legend, children }: ChartBlockProps) => (
    <div className={classNames(styles.chart_container, 'group')}>
        <div className={'flex items-center justify-between px-3 pt-2 pb-1'}>
            <h3
                className={
                    'font-header text-xs font-semibold tracking-wide text-gray-300 transition-colors duration-100 group-hover:text-gray-50'
                }
            >
                {title}
            </h3>
            {legend && <p className={'text-xs flex items-center'}>{legend}</p>}
        </div>
        <div className={styles.chart_plot}>{children}</div>
    </div>
);
