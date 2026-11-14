// Tile "Translations" (widget sulu_translations). Per language: how many pages and articles exist in it, how many are
// missing and how many are outdated (the original was changed later). Sulu's ProgressBar, no own chart. "What is
// missing?" lists the first entries with a link into the form of that language: ten at first, "Show all" shows the
// up to 50 the server delivers.
import React from 'react';
import {action, observable} from 'mobx';
import {observer} from 'mobx-react';
import {Button, Icon} from 'sulu-admin-bundle/components';
import Card from 'sulu-admin-bundle/components/Card';
import ProgressBar from 'sulu-admin-bundle/components/ProgressBar';
import {translate} from 'sulu-admin-bundle/utils';
import styles from '../dashboard.scss';

const VISIBLE_ENTRIES = 10;

// same icons as Sulu's navigation and the table of the open drafts
const TYPE_ICONS = {pages: 'su-webspace', articles: 'su-newspaper'};

// edit form of an entry in the given language (for a missing translation Sulu opens the form of that language empty)
const editView = (item, locale) => (
    item.resourceKey === 'pages'
        ? ['sulu_page.page_edit_form', {id: item.id, webspace: item.webspace, locale}]
        : ['sulu_article.article.edit_tabs_' + (item.group || 'default'), {id: item.id, locale}]
);

@observer
export default class Translations extends React.Component {
    @observable open = null;
    @observable showAll = false;

    @action toggle(locale) {
        this.open = this.open === locale ? null : locale;
        this.showAll = false;
    }

    @action handleShowAll = () => {
        this.showAll = true;
    };

    renderEntries(locale, items, count) {
        const {router} = this.props;
        const visible = this.showAll ? items : items.slice(0, VISIBLE_ENTRIES);

        return (
            <div className={styles.missing}>
                <ul>
                    {visible.map((item) => (
                        <li className={styles.missingEntry} key={item.resourceKey + ":" + item.id}>
                            <Icon className={styles.typeIcon} name={TYPE_ICONS[item.resourceKey]} />
                            <span className={styles.missingTitle}>
                                <Button onClick={() => router.navigate(...editView(item, locale))} skin="link">
                                    {item.title || translate('sulu_dashboard.untitled')}
                                </Button>
                            </span>
                            <span className={item.state === 'outdated' ? styles.stateOutdated : styles.meta}>
                                {translate('sulu_dashboard.state_' + item.state)}
                            </span>
                        </li>
                    ))}
                </ul>
                {count > VISIBLE_ENTRIES &&
                    <div className={styles.missingFooter}>
                        <span className={styles.meta}>
                            {translate('sulu_dashboard.shown_of', {shown: visible.length, total: count})}
                        </span>
                        {!this.showAll && items.length > VISIBLE_ENTRIES &&
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
        const {source, locales} = data;
        const targets = Object.keys(locales).filter((locale) => locale !== source);

        return (
            <Card>
                <h2 className={styles.title}>{translate(widget.title)}</h2>
                <p className={styles.meta}>
                    {translate('sulu_dashboard.translations_hint', {locale: source.toUpperCase()})}
                </p>
                <ul className={styles.languages}>
                    {targets.map((locale) => {
                        const {total, missing, outdated, items} = locales[locale];
                        const done = total - missing;
                        const skin = outdated > 0 ? 'warning' : (missing === 0 ? 'success' : 'progress');

                        return (
                            <li key={locale}>
                                <div className={styles.languageLine}>
                                    <strong>{locale.toUpperCase()}</strong>
                                    <span>{translate('sulu_dashboard.translated', {done, total})}</span>
                                </div>
                                <div className={styles.bar}><ProgressBar max={total || 1} skin={skin} value={done} /></div>
                                {outdated > 0 &&
                                    <div className={styles.meta}>
                                        {translate('sulu_dashboard.outdated', {count: outdated})}
                                    </div>
                                }
                                {items.length > 0 &&
                                    <div className={styles.more}>
                                        <Button onClick={() => this.toggle(locale)} skin="link">
                                            {translate(this.open === locale
                                                ? 'sulu_dashboard.whats_missing_hide'
                                                : 'sulu_dashboard.whats_missing')}
                                        </Button>
                                    </div>
                                }
                                {this.open === locale && this.renderEntries(locale, items, missing + outdated)}
                            </li>
                        );
                    })}
                </ul>
            </Card>
        );
    }
}
