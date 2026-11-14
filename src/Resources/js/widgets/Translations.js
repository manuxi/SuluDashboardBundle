// Tile "Translations" (widget sulu_translations). Per language: how many pages and articles exist in it, how many are
// missing and how many are outdated (the original was changed later). Sulu's ProgressBar, no own chart. "What is
// missing?" lists the first entries with a link into the form of that language.
import React from 'react';
import {action, observable} from 'mobx';
import {observer} from 'mobx-react';
import {Button} from 'sulu-admin-bundle/components';
import Card from 'sulu-admin-bundle/components/Card';
import ProgressBar from 'sulu-admin-bundle/components/ProgressBar';
import {translate} from 'sulu-admin-bundle/utils';
import styles from '../dashboard.scss';

// edit form of an entry in the given language (for a missing translation Sulu opens the form of that language empty)
const editView = (item, locale) => (
    item.resourceKey === 'pages'
        ? ['sulu_page.page_edit_form', {id: item.id, webspace: item.webspace, locale}]
        : ['sulu_article.article.edit_tabs_' + (item.group || 'default'), {id: item.id, locale}]
);

@observer
export default class Translations extends React.Component {
    @observable open = null;

    @action toggle(locale) {
        this.open = this.open === locale ? null : locale;
    }

    render() {
        const {widget, data, router} = this.props;
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
                                {this.open === locale &&
                                    <ul className={styles.missing}>
                                        {items.map((item) => (
                                            <li key={item.resourceKey + ':' + item.id}>
                                                <Button
                                                    onClick={() => router.navigate(...editView(item, locale))}
                                                    skin="link"
                                                >
                                                    {item.title || translate('sulu_dashboard.untitled')}
                                                </Button>
                                                <span className={styles.meta}>
                                                    {' '}{translate('sulu_dashboard.state_' + item.state)}
                                                </span>
                                            </li>
                                        ))}
                                    </ul>
                                }
                            </li>
                        );
                    })}
                </ul>
            </Card>
        );
    }
}
