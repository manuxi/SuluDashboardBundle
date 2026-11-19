// Tile "Search engine details" (widget sulu_seo): three rings (SEO title, meta description, preview image) with the share
// of the published pages and articles that have it. Under a ring "What is missing?" opens the list of the entries that
// lack exactly this detail, in the same table as the open drafts (title, type, changed, pen button into the tab of the
// form, SEO or excerpt, in the chosen language). Ten rows at first, "Show all" shows the up to 50 the server delivers.
// In a narrow column the type and the date drop out (container query in dashboard.scss), title and pen remain.
// data: {text, items: [{key, label, value, max, missing: {count, rows: [{title, icon, resourceKey, template, changed, link: {view, attributes}}]}}]}
import React from 'react';
import {action, observable} from 'mobx';
import {observer} from 'mobx-react';
import {Button, CircularProgressbar, Icon, Table} from 'sulu-admin-bundle/components';
import Card from 'sulu-admin-bundle/components/Card';
import {translate} from 'sulu-admin-bundle/utils';
import {userStore} from 'sulu-admin-bundle/stores';
import {formatDate, navigate, percent, tx} from '../utils';
import {templateTitle} from '../templates';
import {missingCount, nextOpen, openItem, visibleRows, VISIBLE_ENTRIES} from '../seo';
import styles from '../dashboard.scss';

@observer
export default class SeoDetails extends React.Component {
    @observable open = null;
    @observable showAll = false;

    @action toggle(key) {
        this.open = nextOpen(this.open, key);
        this.showAll = false;
    }

    @action handleToggleShowAll = () => {
        this.showAll = !this.showAll;
    };

    handleEdit = (id) => {
        const item = openItem(this.props.data.items || [], this.open);
        const row = item && item.missing.rows[id];

        navigate(this.props.router, row && row.link);
    };

    typeLabel(row) {
        const userLocale = userStore.user ? userStore.user.locale : null;

        return templateTitle((this.props.options || {}).templateTitles, row.resourceKey, row.template, userLocale)
            || translate('sulu_dashboard.type_' + row.resourceKey);
    }

    renderList(item) {
        const count = item.missing.count;
        const all = item.missing.rows;
        const {rows, canShowAll} = visibleRows(all, this.showAll);

        return (
            <div className={styles.seoDetail}>
                <h3 className={styles.title}>{translate('sulu_dashboard.seo_without_' + item.key) + ' · ' + count}</h3>
                <div className={styles.seoTable + ' ' + styles.tableWrap}>
                    <Table buttons={[{icon: 'su-pen', onClick: this.handleEdit}]} skin="light">
                        <Table.Header>
                            <Table.HeaderCell>{translate('sulu_admin.title')}</Table.HeaderCell>
                            <Table.HeaderCell>{translate('sulu_admin.type')}</Table.HeaderCell>
                            <Table.HeaderCell>{translate('sulu_admin.changed')}</Table.HeaderCell>
                        </Table.Header>
                        <Table.Body>
                            {rows.map((row, index) => (
                                <Table.Row id={index} key={index}>
                                    <Table.Cell>
                                        <span className={styles.ellipsis} title={row.title}>
                                            {row.title || translate('sulu_dashboard.untitled')}
                                        </span>
                                    </Table.Cell>
                                    <Table.Cell>
                                        <Icon className={styles.typeIcon} name={row.icon} />
                                        <span className={styles.ellipsis}>{this.typeLabel(row)}</span>
                                    </Table.Cell>
                                    <Table.Cell>
                                        <span className={styles.ellipsis}>{formatDate(row.changed, 'short', 'short')}</span>
                                    </Table.Cell>
                                </Table.Row>
                            ))}
                        </Table.Body>
                    </Table>
                </div>
                {(canShowAll || this.showAll || count > rows.length) &&
                    <div className={styles.missingFooter}>
                        {count > rows.length &&
                            <span className={styles.meta}>
                                {translate('sulu_dashboard.shown_of', {shown: rows.length, total: count})}
                            </span>
                        }
                        {(canShowAll || (this.showAll && all.length > VISIBLE_ENTRIES)) &&
                            <Button onClick={this.handleToggleShowAll} skin="link">
                                {this.showAll
                                    ? translate('sulu_dashboard.show_less')
                                    : translate('sulu_dashboard.show_all', {count: all.length})}
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
                    {items.map((item) => {
                        const active = this.open === item.key;
                        const hasMissing = missingCount(item) > 0 && item.missing && item.missing.rows.length > 0;

                        return (
                            <div className={styles.ring + (active ? ' ' + styles.ringActive : '')} key={item.key}>
                                <CircularProgressbar percentage={percent(item.value, item.max)} size={84} />
                                <div className={styles.ringLabel}>{tx(item.label)}</div>
                                <div className={styles.meta}>{item.value + ' / ' + item.max}</div>
                                {hasMissing &&
                                    <div className={styles.more}>
                                        <Button
                                            icon={active ? 'su-angle-up' : 'su-angle-down'}
                                            onClick={() => this.toggle(item.key)}
                                            skin="link"
                                        >
                                            {translate(active ? 'sulu_dashboard.whats_missing_hide' : 'sulu_dashboard.whats_missing')}
                                        </Button>
                                    </div>
                                }
                            </div>
                        );
                    })}
                </div>
                {current && this.renderList(current)}
            </Card>
        );
    }
}
