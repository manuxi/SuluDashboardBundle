// Widget type "progress": values against a maximum, as rings (Sulu's CircularProgressbar) or bars (ProgressBar).
// data: {text?, display?: 'ring' | 'bar', items: [{label, value, max, text?, note?, skin?}],
//        footer?: {title, rows: [{title, icon?, link?: {view, attributes}}]}}
// Texts are plain text, an admin translation key or {key, parameters}.
import React from 'react';
import {CircularProgressbar} from 'sulu-admin-bundle/components';
import Card from 'sulu-admin-bundle/components/Card';
import ProgressBar from 'sulu-admin-bundle/components/ProgressBar';
import {percent, tx} from '../utils';
import Footer from './Footer';
import styles from '../dashboard.scss';

function Ring({item}) {
    return (
        <div className={styles.ring}>
            <CircularProgressbar percentage={percent(item.value, item.max)} size={84} />
            <div className={styles.ringLabel}>{tx(item.label)}</div>
            <div className={styles.meta}>{item.text ? tx(item.text) : item.value + ' / ' + item.max}</div>
        </div>
    );
}

function Bar({item}) {
    return (
        <li>
            <div className={styles.languageLine}>
                <strong>{tx(item.label)}</strong>
                <span>{item.text ? tx(item.text) : item.value + ' / ' + item.max}</span>
            </div>
            <div className={styles.bar}>
                <ProgressBar max={item.max || 1} skin={item.skin || 'progress'} value={item.value} />
            </div>
            {item.note && <div className={styles.meta}>{tx(item.note)}</div>}
        </li>
    );
}

export default function ProgressTile({widget, data, router}) {
    const items = data.items || [];

    return (
        <Card>
            <h2 className={styles.title}>{tx(widget.title)}</h2>
            {data.text && <p className={styles.meta}>{tx(data.text)}</p>}
            {data.display === 'bar'
                ? <ul className={styles.languages}>{items.map((item, index) => <Bar item={item} key={index} />)}</ul>
                : <div className={styles.rings}>{items.map((item, index) => <Ring item={item} key={index} />)}</div>
            }
            {data.footer && data.footer.rows && data.footer.rows.length > 0 &&
                <Footer footer={data.footer} router={router} />
            }
        </Card>
    );
}
