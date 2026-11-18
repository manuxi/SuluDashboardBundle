// The footer of a tile: a small title and a list of entries with an icon and a link, for example "Add first".
// footer: {title?, rows: [{title, icon?, link?: {view, attributes}}]}; texts as in tx().
import React from 'react';
import {Button, Icon} from 'sulu-admin-bundle/components';
import {navigate, tx} from '../utils';
import styles from '../dashboard.scss';

export default function Footer({footer, router}) {
    return (
        <div className={styles.footer}>
            {footer.title && <div className={styles.meta}>{tx(footer.title)}</div>}
            <ul>
                {footer.rows.map((row, index) => (
                    <li className={styles.footerEntry} key={index}>
                        {row.icon && <Icon className={styles.typeIcon} name={row.icon} />}
                        <span className={styles.missingTitle}>
                            {row.link
                                ? <Button onClick={() => navigate(router, row.link)} skin="link">{tx(row.title)}</Button>
                                : tx(row.title)
                            }
                        </span>
                    </li>
                ))}
            </ul>
        </div>
    );
}
