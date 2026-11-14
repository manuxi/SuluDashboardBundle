// Start page of the admin (view sulu_dashboard.dashboard on "/", src/Admin/DashboardAdmin.php).
// Built like Sulu's own views: class component with withToolbar (language select in the top bar, stored in the user
// setting "content locale" of Sulu) and Sulu's Grid and Card.
// Layout: a row of figures, then two columns (Grid.Section) whose tiles stack, so tiles of different heights leave no
// holes. As a tab of a tab view (a bundle brings a tab) the tab bar replaces the heading. Sulu's Masonry is made for
// media cards and does not re-layout when a tile grows after loading its data.
//
// Placement (layout.js): every tile asks for a column. Once the tiles have loaded, tiles move from the end of the longer
// column into the shorter one, once (option sulu_dashboard.layout.balance).
import React from 'react';
import classNames from 'classnames';
import {action, observable} from 'mobx';
import {observer} from 'mobx-react';
import {Grid} from 'sulu-admin-bundle/components';
import {withToolbar} from 'sulu-admin-bundle/containers';
import {userStore} from 'sulu-admin-bundle/stores';
import {translate} from 'sulu-admin-bundle/utils';
import {arrange, sameArrangement} from './layout';
import WidgetTile from './WidgetTile';
import styles from './dashboard.scss';

// space between two tiles (the margin of .cell in dashboard.scss)
const GAP = 20;

// the balance waits until all tiles have their data and their size has been quiet for this long (milliseconds) ...
const QUIET = 600;
// ... but not longer than this after opening the page
const MAX_WAIT = 5000;

// below this width Sulu's Grid stacks all items (grid.scss), so there are no two columns to balance
const GRID_MIN_WIDTH = 520;

@observer
class Dashboard extends React.Component {
    @observable locale;
    // {row, main, side, hidden}: keys of the tiles; replaced as a whole
    @observable.ref arrangement;
    // keys of the tiles the balance moved (they fade in, unless the user prefers reduced motion)
    @observable.ref movedKeys = [];

    // data of the tiles for as long as the page is open: a tile that moves to the other column is mounted again
    cache = {};
    // the elements of the tiles (key => element), their measured heights (key => pixels) and ref callbacks (key => function)
    cells = {};
    heights = {};
    cellRefs = {};
    // the tiles that have not got their data yet
    pending = new Set();
    settled = false;

    constructor(props) {
        super(props);

        const locales = this.locales;
        this.locale = locales.includes(userStore.contentLocale) ? userStore.contentLocale : locales[0];

        // first placement without measuring: tiles by their wish, tiles without a wish by count
        this.arrangement = arrange(this.widgets, null, {}, {balance: false, gap: GAP});

        this.settled = !this.balance;
        this.widgets.forEach((widget) => this.pending.add(widget.key));
    }

    componentDidMount() {
        if (this.settled) {
            return;
        }

        this.deadline = setTimeout(this.settle, MAX_WAIT);

        // the tiles that load more data on their own change their size without telling anybody
        if ('undefined' !== typeof ResizeObserver) {
            this.resizeObserver = new ResizeObserver(this.bump);
            Object.keys(this.cells).forEach((key) => this.resizeObserver.observe(this.cells[key]));
        }
    }

    componentWillUnmount() {
        this.stopSettling();
    }

    get locales() {
        return this.props.route.options.locales || ['en'];
    }

    get widgets() {
        return this.props.route.options.widgets || [];
    }

    get balance() {
        return false !== this.props.route.options.balance;
    }

    @action setLocale = (locale) => {
        this.locale = locale;
        userStore.updateContentLocale(locale);
    };

    // --- balance: once, after the tiles have loaded ---

    handleCell(key) {
        if (!this.cellRefs[key]) {
            this.cellRefs[key] = (element) => {
                if (element) {
                    this.cells[key] = element;
                    if (this.resizeObserver) {
                        this.resizeObserver.observe(element);
                    }
                } else {
                    if (this.resizeObserver && this.cells[key]) {
                        this.resizeObserver.unobserve(this.cells[key]);
                    }
                    delete this.cells[key];
                }
            };
        }

        return this.cellRefs[key];
    }

    handleSettled = (key) => {
        this.pending.delete(key);
        this.bump();
    };

    // something changed (a tile has its data, a tile changed its size): settle when everything has loaded and is quiet
    bump = () => {
        if (this.settled || this.pending.size > 0) {
            return;
        }

        clearTimeout(this.quiet);
        this.quiet = setTimeout(this.settle, QUIET);
    };

    stopSettling() {
        clearTimeout(this.deadline);
        clearTimeout(this.quiet);
        if (this.resizeObserver) {
            this.resizeObserver.disconnect();
            this.resizeObserver = undefined;
        }
    }

    measure() {
        Object.keys(this.cells).forEach((key) => {
            const height = this.cells[key].getBoundingClientRect().height;
            if (height > 0) {
                this.heights[key] = height;
            }
        });
    }

    @action settle = () => {
        if (this.settled) {
            return;
        }

        this.settled = true;
        this.stopSettling();

        // two columns next to each other only from the width at which Sulu's Grid does it
        if (window.matchMedia && !window.matchMedia('(min-width: ' + GRID_MIN_WIDTH + 'px)').matches) {
            return;
        }

        this.measure();

        const before = this.arrangement;
        const arrangement = arrange(this.widgets, null, this.heights, {gap: GAP});
        this.movedKeys = ['main', 'side'].flatMap((column) => arrangement[column].filter((key) => !before[column].includes(key)));

        if (!sameArrangement(before, arrangement)) {
            this.arrangement = arrangement;
        }
    };

    // --- display ---

    renderTile(key) {
        const widget = this.widgets.find((candidate) => candidate.key === key);

        return (
            <div
                className={classNames(styles.cell, {[styles.moved]: this.movedKeys.includes(key)})}
                key={key}
                ref={this.handleCell(key)}
            >
                <div className={styles.tileBody}>
                    <div className={styles.content}>
                        <WidgetTile
                            cache={this.cache}
                            key={key + ':' + this.locale}
                            locale={this.locale}
                            locales={this.locales}
                            onSettled={this.handleSettled}
                            options={this.props.route.options}
                            router={this.props.router}
                            widget={widget}
                        />
                    </div>
                </div>
            </div>
        );
    }

    render() {
        const {row, main, side} = this.arrangement;
        // with one column empty the other one takes the full width
        const mainSpan = side.length === 0 ? 12 : 8;
        const sideSpan = main.length === 0 ? 12 : 4;

        return (
            <div className={styles.dashboard}>
                {!this.props.route.parent && <h1>{translate('sulu_dashboard.dashboard')}</h1>}

                <Grid>
                    {row.length > 0 &&
                        <Grid.Section>
                            <Grid.Item colSpan={12}>
                                <div className={styles.rowList} style={{'--columns': Math.min(row.length, 4)}}>
                                    {row.map((key) => this.renderTile(key))}
                                </div>
                            </Grid.Item>
                        </Grid.Section>
                    }
                    {main.length > 0 &&
                        <Grid.Section colSpan={mainSpan}>
                            <Grid.Item colSpan={12}>{main.map((key) => this.renderTile(key))}</Grid.Item>
                        </Grid.Section>
                    }
                    {side.length > 0 &&
                        <Grid.Section colSpan={sideSpan}>
                            <Grid.Item colSpan={12}>{side.map((key) => this.renderTile(key))}</Grid.Item>
                        </Grid.Section>
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
