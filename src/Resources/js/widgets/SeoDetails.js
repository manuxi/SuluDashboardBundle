// Tile "Search engine details" (widget sulu_seo): three rings (SEO title, meta description, preview image) with the share
// of the published pages and articles that have it. Under a ring "What is missing?" opens the list of the entries that
// lack exactly this detail, each with a link into the tab of its form (SEO or excerpt) in the chosen language. Ten
// entries at first, "Show all" shows the up to 50 the server delivers.
// data: {text, items: [{key, label, value, max, missing: {count, rows: [{title, icon, link: {view, attributes}}]}}]}
import React from 'react';
import {action, observable} from 'mobx';
import {observer} from 'mobx-react';
import {Button, CircularProgressbar, Icon} from 'sulu-admin-bundle/components';
import Card from 'sulu-admin-bundle/components/Card';
import {translate} from 'sulu-admin-bundle/utils';
import {navigate, percent, tx} from '../utils';
import {missingCount, nextOpen, openItem, visibleRows} from '../seo';
import styles from '../dashboard.scss';

@observer
export default class SeoDetails extends React.Component {
    @observable open = null;
    @observable showAll = false;

    @action toggle(key) {
        this.open = nextOpen(this.open, key);
        this.showAll = false;
    }

    @action handleShowAll = () => {
        this.showAll = true;
    };

    renderList(item) {
        const {router} = this.props;
        const count = item.missing.count;
        const {rows, canShowAll} = visibleRows(item.missing.rows, this.showAll);

        return (
            <div className={styles.missing}>
                <div className={styles.meta}>{translate('sulu_dashboard.seo_without_' + item.key)}</div>
                <ul>
                    {rows.map((row, index) => (
                        <li className={styles.missingEntry} key={index}>
                            <Icon className={styles.typeIcon} name={row.icon} />
                            <span className={styles.missingTitle}>
                                <Button onClick={() => navigate(router, row.link)} skin="link">
                                    {row.title || translate('sulu_dashboard.untitled')}
                                </Button>
                            </span>
                        </li>
                    ))}
                </ul>
                {count > rows.length &&
                    <div className={styles.missingFooter}>
                        <span className={styles.meta}>
                            {translate('sulu_dashboard.shown_of', {shown: rows.length, total: count})}
                        </span>
                        {canShowAll &&
                            <Button onClick={this.handleShowAll} skin="link">
                                {translate('sulu_dashboard.show_all_short')}
                            </Button>
                        }
                    </div>
                }
            </div>
        );
    }

    render() {
        const {widget, data} = this.props;
        const items = data.items || [];
        const current = openItem(items, this.open);

        return (
            <Card>
                <h2 className={styles.title}>{tx(widget.title)}</h2>
                {data.text && <p className={styles.meta}>{tx(data.text)}</p>}
                <div className={styles.rings}>
                    {items.map((item) => (
                        <div className={styles.ring} key={item.key}>
                            <CircularProgressbar percentage={percent(item.value, item.max)} size={84} />
                            <div className={styles.ringLabel}>{tx(item.label)}</div>
                            <div className={styles.meta}>{item.value + ' / ' + item.max}</div>
                            {missingCount(item) > 0 && item.missing && item.missing.rows.length > 0 &&
                                <div className={styles.more}>
                                    <Button onClick={() => this.toggle(item.key)} skin="link">
                                        {translate(this.open === item.key
                                            ? 'sulu_dashboard.whats_missing_hide'
                                            : 'sulu_dashboard.whats_missing')}
                                    </Button>
                                </div>
                            }
                        </div>
                    ))}
                </div>
                {current && this.renderList(current)}
            </Card>
        );
    }
}
