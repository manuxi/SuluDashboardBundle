// Widget type "bars": bars that compare values with each other, for example the number of testimonials per star rating.
// data: {items: [{label, value, link?}], max?}; the longest bar is the highest value unless max is given.
import React from 'react';
import {Button} from 'sulu-admin-bundle/components';
import Card from 'sulu-admin-bundle/components/Card';
import ProgressBar from 'sulu-admin-bundle/components/ProgressBar';
import {translate} from 'sulu-admin-bundle/utils';
import {navigate, tx} from '../utils';
import styles from '../dashboard.scss';

export default function BarsTile({widget, data, router}) {
    const items = data.items || [];
    const max = data.max || Math.max(1, ...items.map((item) => item.value));

    return (
        <Card>
            <h2 className={styles.title}>{tx(widget.title)}</h2>
            {items.length === 0 && <p className={styles.meta}>{translate('sulu_dashboard.empty')}</p>}
            <ul className={styles.languages}>
                {items.map((item, index) => (
                    <li key={index}>
                        <div className={styles.languageLine}>
                            {item.link
                                ? <Button onClick={() => navigate(router, item.link)} skin="link">{tx(item.label)}</Button>
                                : <strong>{tx(item.label)}</strong>
                            }
                            <span>{item.value}</span>
                        </div>
                        <div className={styles.bar}><ProgressBar max={max} skin="progress" value={item.value} /></div>
                    </li>
                ))}
            </ul>
        </Card>
    );
}
