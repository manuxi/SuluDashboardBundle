// Widget type "number": a figure with its label and an optional split by state.
// data: {value, parts?: [{label, count, state?}]}; state is a workflow place and shows Sulu's PublishIndicator.
import React from 'react';
import {PublishIndicator} from 'sulu-admin-bundle/components';
import Card from 'sulu-admin-bundle/components/Card';
import {INDICATOR, tx} from '../utils';
import styles from '../dashboard.scss';

export default function NumberTile({widget, data}) {
    const parts = data.parts || [];

    return (
        <Card>
            <div className={styles.value}>{data.value}</div>
            <div className={styles.label}>{tx(widget.title)}</div>
            {parts.length > 0 &&
                <ul className={styles.places}>
                    {parts.map((part, index) => (
                        <li key={index}>
                            <span className={styles.placeCount}>{part.count}</span>
                            <span>{tx(part.label)}</span>
                            {part.state && INDICATOR[part.state]
                                ? <PublishIndicator className={styles.placeIndicator} {...INDICATOR[part.state]} />
                                : <span />
                            }
                        </li>
                    ))}
                </ul>
            }
        </Card>
    );
}
