// The tiles "Condition", "Versions" and "Environment" of the tab "System" (widgets sulu_system_status, _versions,
// _environment): one component for all three, so they look the same. A slim list instead of a table: the name on the
// left, the value on the right, an optional grey line below, Sulu's icon in front for ok, warning and error. No indent:
// the first line is flush with the title of the card.
// data: see SystemTileData on the server; what it may hold and how it becomes rows: facts.js.
import React from 'react';
import {Icon} from 'sulu-admin-bundle/components';
import Card from 'sulu-admin-bundle/components/Card';
import {normalize, SEVERITY_ICONS} from '../facts';
import {tx} from '../utils';
import styles from '../dashboard.scss';

const ICON_CLASSES = {error: styles.severityError, warning: styles.severityWarning, ok: styles.severityOk};

const HEAD_CLASSES = {error: styles.factsError, warning: styles.factsWarning, ok: styles.factsOk};

const Mark = ({severity}) => (
    severity
        ? <Icon className={[styles.factIcon, ICON_CLASSES[severity]].join(' ')} name={SEVERITY_ICONS[severity]} />
        : <span className={styles.factIcon} />
);

export default function SystemFacts({widget, data}) {
    const facts = normalize(data);

    return (
        <Card>
            <h2 className={styles.title}>{tx(widget.title)}</h2>
            {facts.headline && (
                <div className={[styles.factsHead, facts.status ? HEAD_CLASSES[facts.status] : ''].join(' ')}>
                    {facts.status && <Icon className={styles.factsHeadIcon} name={SEVERITY_ICONS[facts.status]} />}
                    <span>{tx(facts.headline)}</span>
                </div>
            )}
            <ul className={styles.facts}>
                {facts.rows.map((row, index) => (
                    <li className={styles.fact} key={index}>
                        {facts.hasIcons && <Mark severity={row.severity} />}
                        <div className={styles.factBody}>
                            <div className={styles.factLine}>
                                <span className={styles.factName}>{tx(row.title)}</span>
                                {row.value && (
                                    <span className={[styles.factValue, row.severity === 'error' ? styles.severityTitleError : ''].join(' ')}>
                                        {tx(row.value)}
                                    </span>
                                )}
                            </div>
                            {row.text && <div className={styles.rowText}>{tx(row.text)}</div>}
                        </div>
                    </li>
                ))}
            </ul>
        </Card>
    );
}
