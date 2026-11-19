// The tile "Storage" of the tab "System" (widget sulu_system_storage): the sum of the measured folders, one bar per folder
// with its share, what changed since the measurement before, the media of Sulu, and a hint where something is bigger than
// it should be. Always with the age of the measurement: the values come from the console command, not from this request.
// data: see StorageReport on the server; what it may hold and how it becomes data for the component: storage.js.
import React from 'react';
import {Icon} from 'sulu-admin-bundle/components';
import Card from 'sulu-admin-bundle/components/Card';
import ProgressBar from 'sulu-admin-bundle/components/ProgressBar';
import {translate} from 'sulu-admin-bundle/utils';
import {barValue, normalize, percentText} from '../storage';
import {tx} from '../utils';
import styles from '../dashboard.scss';

export default function SystemStorage({widget, data}) {
    const storage = normalize(data);

    if (storage.status === 'never') {
        return (
            <Card>
                <h2 className={styles.title}>{tx(widget.title)}</h2>
                <div className={styles.systemMessage}>
                    <Icon className={styles.severityWarning} name="su-exclamation-circle" />
                    <div>
                        <div>{translate('sulu_dashboard.system_storage_none')}</div>
                        <div className={styles.rowText}>{translate('sulu_dashboard.system_not_run_hint', {command: storage.command})}</div>
                    </div>
                </div>
            </Card>
        );
    }

    const since = storage.previousAge ? tx(storage.previousAge) : null;

    return (
        <Card>
            <h2 className={styles.title}>{tx(widget.title)}</h2>
            <div className={styles.storageHead}>
                <div>
                    <div className={styles.value}>{storage.totalText}</div>
                    <div className={styles.label}>{translate('sulu_dashboard.system_storage_total')}</div>
                </div>
                <div className={styles.storageFacts}>
                    {since && (
                        <div className={styles.rowText}>
                            {storage.totalChange
                                ? translate('sulu_dashboard.system_storage_change', {change: storage.totalChange.text, age: since})
                                : translate('sulu_dashboard.system_storage_unchanged', {age: since})
                            }
                        </div>
                    )}
                    {storage.media && (
                        <div className={styles.rowText}>
                            {translate('sulu_dashboard.system_storage_media', {count: storage.media.count, size: storage.media.text})}
                        </div>
                    )}
                </div>
            </div>
            <ul className={styles.languages}>
                {storage.items.map((item, index) => (
                    <li key={index}>
                        <div className={styles.languageLine}>
                            <span title={item.path || undefined}>
                                <strong>{tx(item.label)}</strong>
                                {item.path && <span className={styles.rowText}>{' '}{item.path}</span>}
                            </span>
                            <span>
                                {item.change && <span className={styles.rowText}>{item.change.text}{' · '}</span>}
                                {item.text}
                                <span className={styles.rowText}>{' · '}{percentText(item.percent)}</span>
                            </span>
                        </div>
                        <div className={styles.bar}>
                            <ProgressBar max={item.max} skin={item.skin} value={barValue(item)} />
                        </div>
                    </li>
                ))}
            </ul>
            {storage.hints.map((hint, index) => (
                <div className={[styles.systemMessage, styles.storageHint].join(' ')} key={index}>
                    <Icon className={styles.severityWarning} name="su-exclamation-circle" />
                    <div>
                        <div>{tx(hint.title)}</div>
                        {hint.text && <div className={styles.rowText}>{tx(hint.text)}</div>}
                    </div>
                </div>
            ))}
            <div className={styles.storageMeasured}>
                {storage.stale && <Icon className={styles.severityWarning} name="su-exclamation-circle" />}
                <span className={storage.stale ? undefined : styles.rowText}>
                    {translate('sulu_dashboard.system_storage_measured', {age: tx(storage.age)})}
                    {storage.stale && ' ' + translate('sulu_dashboard.system_stale_hint', {command: storage.command})}
                </span>
            </div>
        </Card>
    );
}
