// Tile "Open drafts" (widget sulu_open_items). Sulu's table with the grey header (skin "light"), pen button per row,
// sortable columns, a type filter and "only mine". Shows ten rows, the rest on demand: simple at first sight.
// Filter and sorting are kept in Sulu's user settings (persistent per user, like the column choice of Sulu's lists).
import React from 'react';
import {action, computed, observable} from 'mobx';
import {observer} from 'mobx-react';
import {Button, Checkbox, Icon, PublishIndicator, SingleSelect, Table} from 'sulu-admin-bundle/components';
import Card from 'sulu-admin-bundle/components/Card';
import {userStore} from 'sulu-admin-bundle/stores';
import {translate} from 'sulu-admin-bundle/utils';
import {formatDate, INDICATOR, toDate} from '../utils';
import styles from '../dashboard.scss';

const VISIBLE_ROWS = 10;
const SETTING_KEY = 'sulu_dashboard.open_items';
const DEFAULT_SETTINGS = {type: 'all', onlyMine: false, sortColumn: 'changed', sortOrder: 'desc'};

// same icons as Sulu's navigation, so the type is recognised at a glance
const TYPE_ICONS = {pages: 'su-webspace', articles: 'su-newspaper'};

// view name and attributes of the edit form; articles have one edit view per article type group
const EDIT_VIEWS = {
    pages: (item, locale) => ['sulu_page.page_edit_form', {id: item.id, webspace: item.webspace, locale}],
    articles: (item, locale) => ['sulu_article.article.edit_tabs_' + (item.group || 'default'), {id: item.id, locale}],
};

const rowId = (item) => item.resourceKey + ':' + item.id;

// text in a Sulu table cell sits in a flex box, so the ellipsis needs its own block
const Ellipsis = ({children, title}) => <span className={styles.ellipsis} title={title}>{children}</span>;

@observer
export default class OpenItems extends React.Component {
    @observable settings = {...DEFAULT_SETTINGS, ...(userStore.getPersistentSetting(SETTING_KEY) || {})};
    @observable expanded = false;

    @computed get filteredItems() {
        const {items} = this.props.data;
        const {type, onlyMine, sortColumn, sortOrder} = this.settings;
        const userId = userStore.user ? userStore.user.id : null;

        const filtered = items
            .filter((item) => type === 'all' || item.resourceKey === type)
            .filter((item) => !onlyMine || item.changerId === userId);

        const direction = sortOrder === 'asc' ? 1 : -1;

        return filtered.slice().sort((a, b) => {
            if (sortColumn === 'title') {
                return direction * String(a.title || '').localeCompare(String(b.title || ''));
            }

            return direction * (toDate(a.changed) - toDate(b.changed));
        });
    }

    // the template title as in Sulu's template select ("Blog"), in the admin language of the user; the icon already says
    // page or article
    typeLabel(item) {
        const templateTitles = (this.props.options || {}).templateTitles || {};
        const titles = (templateTitles[item.resourceKey] || {})[item.template] || {};
        const userLocale = userStore.user ? userStore.user.locale : null;

        return titles[userLocale] || Object.values(titles)[0] || translate('sulu_dashboard.type_' + item.resourceKey);
    }

    @action changeSettings(changes) {
        this.settings = {...this.settings, ...changes};
        this.expanded = false;
        userStore.setPersistentSetting(SETTING_KEY, this.settings);
    }

    handleTypeChange = (type) => this.changeSettings({type});

    handleOnlyMineChange = (onlyMine) => this.changeSettings({onlyMine});

    handleSort = (sortColumn, sortOrder) => this.changeSettings({sortColumn, sortOrder});

    @action handleToggleExpanded = () => {
        this.expanded = !this.expanded;
    };

    handleEdit = (id) => {
        const {locale, router} = this.props;
        const item = this.props.data.items.find((openItem) => rowId(openItem) === id);
        const view = item && EDIT_VIEWS[item.resourceKey];

        if (view) {
            router.navigate(...view(item, locale));
        }
    };

    renderHeaderCell(name, label) {
        const {sortColumn, sortOrder} = this.settings;

        return (
            <Table.HeaderCell name={name} onClick={this.handleSort} sortOrder={sortColumn === name ? sortOrder : undefined}>
                {label}
            </Table.HeaderCell>
        );
    }

    render() {
        const {widget} = this.props;
        const items = this.filteredItems;
        const visible = this.expanded ? items : items.slice(0, VISIBLE_ROWS);

        return (
            <Card>
                <h2 className={styles.title}>{translate(widget.title)}</h2>

                <div className={styles.filters}>
                    <div className={styles.typeFilter}>
                        <SingleSelect onChange={this.handleTypeChange} value={this.settings.type}>
                            <SingleSelect.Option value="all">{translate('sulu_dashboard.filter_all')}</SingleSelect.Option>
                            <SingleSelect.Option value="pages">{translate('sulu_dashboard.pages')}</SingleSelect.Option>
                            <SingleSelect.Option value="articles">{translate('sulu_dashboard.articles')}</SingleSelect.Option>
                        </SingleSelect>
                    </div>
                    <Checkbox checked={this.settings.onlyMine} onChange={this.handleOnlyMineChange}>
                        {translate('sulu_dashboard.only_mine')}
                    </Checkbox>
                </div>

                <div className={styles.tableWrap}>
                    <Table
                        buttons={[{icon: 'su-pen', onClick: this.handleEdit}]}
                        placeholderText={translate(
                            this.props.data.items.length === 0 ? 'sulu_dashboard.nothing_open' : 'sulu_dashboard.nothing_found'
                        )}
                        skin="light"
                    >
                        <Table.Header>
                            {this.renderHeaderCell('title', translate('sulu_admin.title'))}
                            <Table.HeaderCell>{translate('sulu_admin.type')}</Table.HeaderCell>
                            {this.renderHeaderCell('changed', translate('sulu_admin.changed'))}
                        </Table.Header>
                        <Table.Body>
                            {visible.map((item) => (
                                <Table.Row id={rowId(item)} key={rowId(item)}>
                                    <Table.Cell>
                                        <PublishIndicator
                                            className={styles.indicator}
                                            {...INDICATOR[item.workflowPlace]}
                                        />
                                        <Ellipsis title={item.title}>
                                            {item.title || translate('sulu_dashboard.untitled')}
                                        </Ellipsis>
                                    </Table.Cell>
                                    <Table.Cell>
                                        <Icon className={styles.typeIcon} name={TYPE_ICONS[item.resourceKey]} />
                                        <Ellipsis>{this.typeLabel(item)}</Ellipsis>
                                    </Table.Cell>
                                    <Table.Cell>
                                        <Ellipsis
                                            title={item.changer
                                                ? translate('sulu_dashboard.changed_by', {name: item.changer})
                                                : undefined}
                                        >
                                            {formatDate(item.changed, 'short', 'short')}
                                        </Ellipsis>
                                    </Table.Cell>
                                </Table.Row>
                            ))}
                        </Table.Body>
                    </Table>
                </div>

                {items.length > VISIBLE_ROWS &&
                    <div className={styles.more}>
                        <Button onClick={this.handleToggleExpanded} skin="link">
                            {this.expanded
                                ? translate('sulu_dashboard.show_less')
                                : translate('sulu_dashboard.show_all', {count: items.length})}
                        </Button>
                    </div>
                }
            </Card>
        );
    }
}
