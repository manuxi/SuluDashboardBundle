// Start page of the admin (view sulu_dashboard.dashboard on "/", src/Admin/DashboardAdmin.php).
// Built like Sulu's own views: class component with withToolbar (language select in the top bar, stored in the user
// setting "content locale" of Sulu) and Sulu's Grid and Card.
// Layout: a row of figures, then two columns (Grid.Section) whose tiles stack, so tiles of different heights leave no
// holes. As a tab of a tab view (a bundle brings a tab) the tab bar replaces the heading. Sulu's Masonry is made for media cards and does not re-layout when a tile grows after loading its data.
import React from 'react';
import {action, observable} from 'mobx';
import {observer} from 'mobx-react';
import {Grid} from 'sulu-admin-bundle/components';
import {withToolbar} from 'sulu-admin-bundle/containers';
import {userStore} from 'sulu-admin-bundle/stores';
import {translate} from 'sulu-admin-bundle/utils';
import WidgetTile from './WidgetTile';
import styles from './dashboard.scss';

// figures per row: 1 to 4 share the width, more wrap into rows of four
const rowSpan = (count) => (count >= 1 && count <= 4 ? Math.floor(12 / count) : 3);

@observer
class Dashboard extends React.Component {
    @observable locale;

    constructor(props) {
        super(props);

        const locales = this.locales;
        this.locale = locales.includes(userStore.contentLocale) ? userStore.contentLocale : locales[0];
    }

    get locales() {
        return this.props.route.options.locales || ['en'];
    }

    @action setLocale = (locale) => {
        this.locale = locale;
        userStore.updateContentLocale(locale);
    };

    renderTile(widget, span = 12) {
        return (
            <Grid.Item className={styles.cell} colSpan={span} key={widget.key}>
                <WidgetTile
                    key={widget.key + ':' + this.locale}
                    locale={this.locale}
                    locales={this.locales}
                    options={this.props.route.options}
                    router={this.props.router}
                    widget={widget}
                />
            </Grid.Item>
        );
    }

    render() {
        const widgets = this.props.route.options.widgets || [];
        const inColumn = (column) => widgets.filter((widget) => widget.column === column);
        const row = inColumn('row');
        const main = inColumn('main');
        const side = inColumn('side');
        // with one column empty the other one takes the full width
        const mainSpan = side.length === 0 ? 12 : 8;
        const sideSpan = main.length === 0 ? 12 : 4;

        return (
            <div className={styles.dashboard}>
                {!this.props.route.parent && <h1>{translate('sulu_dashboard.dashboard')}</h1>}

                <Grid>
                    {row.length > 0 &&
                        <Grid.Section className={styles.row}>
                            {row.map((widget) => this.renderTile(widget, rowSpan(row.length)))}
                        </Grid.Section>
                    }
                    {main.length > 0 &&
                        <Grid.Section colSpan={mainSpan}>{main.map((widget) => this.renderTile(widget))}</Grid.Section>
                    }
                    {side.length > 0 &&
                        <Grid.Section colSpan={sideSpan}>{side.map((widget) => this.renderTile(widget))}</Grid.Section>
                    }
                </Grid>
            </div>
        );
    }
}

export default withToolbar(Dashboard, function() {
    const locales = this.locales;

    return {
        locale: locales.length > 1
            ? {
                value: this.locale,
                onChange: this.setLocale,
                options: locales.map((locale) => ({value: locale, label: locale})),
            }
            : undefined,
        items: [],
    };
});
