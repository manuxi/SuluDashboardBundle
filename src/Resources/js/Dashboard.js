// Start page of the admin (view sulu_dashboard.dashboard on "/", src/Admin/DashboardAdmin.php).
// Built like Sulu's own views: class component with withToolbar (language select in the top bar, stored in the user
// setting "content locale" of Sulu) and Sulu's Grid and Card.
// Layout: a row of figures, then two columns (Grid.Section) whose tiles stack, so tiles of different heights leave no
// holes. As a tab of a tab view (a bundle brings a tab) the tab bar replaces the heading. Sulu's Masonry is made for
// media cards and does not re-layout when a tile grows after loading its data.
//
// Placement (layout.js): every tile asks for a column. Once the tiles have loaded, tiles move from the end of the longer
// column into the shorter one, once (option sulu_dashboard.layout.balance). A user can arrange the tiles ("Customize" in
// the toolbar): drag with the handle of Sulu's blocks (react-sortable-hoc, like Sulu's block lists), hide, move to the
// other column. That arrangement is stored per user (user setting) and wins over the balance.
import React from 'react';
import classNames from 'classnames';
import {action, observable} from 'mobx';
import {observer} from 'mobx-react';
import {SortableContainer, SortableElement} from 'react-sortable-hoc';
import {Grid, Icon} from 'sulu-admin-bundle/components';
import SortableHandle from 'sulu-admin-bundle/components/BlockCollection/SortableHandle';
import Card from 'sulu-admin-bundle/components/Card';
import {withToolbar} from 'sulu-admin-bundle/containers';
import {userStore} from 'sulu-admin-bundle/stores';
import {translate} from 'sulu-admin-bundle/utils';
import {arrange, move, sameArrangement} from './layout';
import {tx} from './utils';
import WidgetTile from './WidgetTile';
import styles from './dashboard.scss';

const SETTING_KEY = 'sulu_dashboard.layout';

// space between two tiles (the margin of .cell in dashboard.scss)
const GAP = 20;

// the balance waits until all tiles have their data and their size has been quiet for this long (milliseconds) ...
const QUIET = 600;
// ... but not longer than this after opening the page
const MAX_WAIT = 5000;

// below this width Sulu's Grid stacks all items (grid.scss), so there are no two columns to balance
const GRID_MIN_WIDTH = 520;

const OTHER_COLUMN = {main: 'side', side: 'main'};

const TileList = SortableContainer(({children, className, style}) => <div className={className} style={style}>{children}</div>);

const Tile = SortableElement(({column, editing, hidden, moved, onCell, onHide, onMoveColumn, tile, widget}) => {
    const moveLabel = translate(column === 'main' ? 'sulu_dashboard.layout_to_side' : 'sulu_dashboard.layout_to_main');
    const hideLabel = translate(hidden ? 'sulu_dashboard.layout_show' : 'sulu_dashboard.layout_hide');

    return (
        <div
            className={classNames(styles.cell, {[styles.editing]: editing, [styles.hiddenTile]: hidden, [styles.moved]: moved})}
            ref={onCell}
        >
            {editing && <div className={styles.handle}><SortableHandle /></div>}
            <div className={styles.tileBody}>
                <div className={classNames(styles.content, {[styles.inert]: editing})}>
                    {hidden
                        ? (
                            <Card>
                                <h2 className={styles.title}>{tx(widget.title)}</h2>
                                <p className={styles.meta}>{translate('sulu_dashboard.layout_hidden')}</p>
                            </Card>
                        )
                        : tile
                    }
                </div>
                {editing &&
                    <div className={styles.controls}>
                        {OTHER_COLUMN[column] &&
                            <button aria-label={moveLabel} onClick={onMoveColumn} title={moveLabel} type="button">
                                <Icon name={column === 'main' ? 'su-angle-right' : 'su-angle-left'} />
                            </button>
                        }
                        <button aria-label={hideLabel} onClick={onHide} title={hideLabel} type="button">
                            <Icon name={hidden ? 'su-hide' : 'su-eye'} />
                        </button>
                    </div>
                }
            </div>
        </div>
    );
});

@observer
class Dashboard extends React.Component {
    @observable locale;
    @observable editing = false;
    // {row, main, side, hidden}: keys of the tiles; replaced as a whole on every change
    @observable.ref arrangement;
    // keys of the tiles the last balance moved (they fade in, unless the user prefers reduced motion)
    @observable.ref movedKeys = [];

    // data of the tiles for as long as the page is open: a tile that moves to the other column is mounted again
    cache = {};
    // the elements of the tiles (key => element), their last measured heights (key => pixels) and ref callbacks (key => function)
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

        const saved = userStore.getPersistentSetting(SETTING_KEY);
        this.saved = saved && 'object' === typeof saved ? saved : null;

        // first placement without measuring: tiles by their wish, tiles without a wish by count
        this.arrangement = arrange(this.widgets, this.saved, {}, {balance: false, gap: GAP});

        // an arrangement of the user is final, unless tiles are new since: those are placed once they are measured
        this.settled = !this.balance || (null !== this.saved && this.widgets.every((widget) => this.isKnown(widget)));
        this.widgets
            .filter((widget) => !this.arrangement.hidden.includes(widget.key))
            .forEach((widget) => this.pending.add(widget.key));
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

    isKnown(widget) {
        const saved = this.saved || {};

        return 'row' === widget.column || (saved.main || []).includes(widget.key) || (saved.side || []).includes(widget.key);
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
            if (!this.arrangement.hidden.includes(key) && height > 0) {
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
        this.applyArrangement(arrange(this.widgets, this.saved, this.heights, {gap: GAP}));
    };

    @action applyArrangement(arrangement) {
        const before = this.arrangement;
        this.movedKeys = ['main', 'side'].flatMap((column) => arrangement[column].filter((key) => !before[column].includes(key)));

        if (!sameArrangement(before, arrangement) || before.hidden.join() !== arrangement.hidden.join()) {
            this.arrangement = arrangement;
        }
    }

    // --- arranging by the user ---

    @action startEditing = () => {
        // the balance would move tiles under the hand of the user: do it now with what is there
        this.settle();
        this.measure();
        this.movedKeys = [];
        this.editing = true;
    };

    @action stopEditing = () => {
        this.editing = false;
    };

    // saved with every change, so nothing is lost when the user leaves the page without "Done"
    persist(arrangement) {
        const known = this.widgets.map((widget) => widget.key);
        // tiles that are not shown to this user at the moment (no permission, switched off) keep their state
        const unknownHidden = ((this.saved || {}).hidden || []).filter((key) => !known.includes(key));
        const value = {
            row: arrangement.row,
            main: arrangement.main,
            side: arrangement.side,
            hidden: [...arrangement.hidden, ...unknownHidden],
        };

        this.saved = value;
        userStore.setPersistentSetting(SETTING_KEY, value);
    }

    @action change(changes) {
        this.movedKeys = [];
        this.arrangement = {...this.arrangement, ...changes};
        this.persist(this.arrangement);
    }

    handleSortEnd = (column) => ({oldIndex, newIndex}) => {
        if (oldIndex !== newIndex) {
            this.change({[column]: move(this.arrangement[column], oldIndex, newIndex)});
        }
    };

    handleHide = (key) => () => {
        const hidden = this.arrangement.hidden;

        this.change({hidden: hidden.includes(key) ? hidden.filter((entry) => entry !== key) : [...hidden, key]});
    };

    handleMoveColumn = (key, column) => () => {
        const target = OTHER_COLUMN[column];

        this.change({
            [column]: this.arrangement[column].filter((entry) => entry !== key),
            [target]: [...this.arrangement[target], key],
        });
    };

    // deletes the arrangement: from now on the balance decides again, with the sizes measured before (no second wait)
    @action reset = () => {
        userStore.setPersistentSetting(SETTING_KEY, null);
        this.saved = null;
        this.editing = false;
        this.settled = true;
        this.stopSettling();
        this.applyArrangement(arrange(this.widgets, null, this.heights, {balance: this.balance, gap: GAP}));
    };

    // --- display ---

    // the keys of a list as shown: hidden tiles only while the user arranges
    shown(column) {
        const keys = this.arrangement[column];

        return this.editing ? keys : keys.filter((key) => !this.arrangement.hidden.includes(key));
    }

    renderTile(key, column, index) {
        const widget = this.widgets.find((candidate) => candidate.key === key);
        const hidden = this.arrangement.hidden.includes(key);

        return (
            <Tile
                column={column}
                editing={this.editing}
                hidden={hidden}
                index={index}
                key={key}
                moved={this.movedKeys.includes(key)}
                onCell={this.handleCell(key)}
                onHide={this.handleHide(key)}
                onMoveColumn={this.handleMoveColumn(key, column)}
                tile={hidden ? null : (
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
                )}
                widget={widget}
            />
        );
    }

    renderList(column) {
        const keys = this.shown(column);
        const isRow = 'row' === column;

        return (
            <TileList
                axis={isRow ? 'xy' : 'y'}
                className={isRow ? styles.rowList : styles.columnList}
                helperClass={styles.dragging}
                onSortEnd={this.handleSortEnd(column)}
                style={isRow ? {'--columns': Math.min(keys.length, 4)} : undefined}
                useDragHandle={true}
            >
                {keys.map((key, index) => this.renderTile(key, column, index))}
                {this.editing && !isRow && 0 === keys.length &&
                    <div className={styles.emptyColumn}>{translate('sulu_dashboard.layout_empty')}</div>
                }
            </TileList>
        );
    }

    render() {
        const row = this.shown('row');
        const main = this.shown('main');
        const side = this.shown('side');
        // with one column empty the other one takes the full width; while arranging both stay, so a tile can move over
        const mainSpan = !this.editing && side.length === 0 ? 12 : 8;
        const sideSpan = !this.editing && main.length === 0 ? 12 : 4;

        return (
            <div className={styles.dashboard}>
                {!this.props.route.parent && <h1>{translate('sulu_dashboard.dashboard')}</h1>}

                {this.editing && <p className={styles.hint}>{translate('sulu_dashboard.layout_hint')}</p>}

                <Grid>
                    {row.length > 0 &&
                        <Grid.Section>
                            <Grid.Item colSpan={12}>{this.renderList('row')}</Grid.Item>
                        </Grid.Section>
                    }
                    {(main.length > 0 || this.editing) &&
                        <Grid.Section colSpan={mainSpan}>
                            <Grid.Item colSpan={12}>{this.renderList('main')}</Grid.Item>
                        </Grid.Section>
                    }
                    {(side.length > 0 || this.editing) &&
                        <Grid.Section colSpan={sideSpan}>
                            <Grid.Item colSpan={12}>{this.renderList('side')}</Grid.Item>
                        </Grid.Section>
                    }
                </Grid>
            </div>
        );
    }
}

export default withToolbar(Dashboard, function() {
    const locales = this.locales;
    let items = [];

    if (this.editing) {
        items = [
            {type: 'button', label: translate('sulu_dashboard.layout_done'), icon: 'su-check', primary: true, onClick: this.stopEditing},
            {type: 'button', label: translate('sulu_dashboard.layout_reset'), icon: 'su-sync', onClick: this.reset},
        ];
    } else if (this.widgets.length > 0) {
        items = [{type: 'button', label: translate('sulu_dashboard.layout_customize'), icon: 'su-cog', onClick: this.startEditing}];
    }

    return {
        locale: locales.length > 1
            ? {
                value: this.locale,
                onChange: this.setLocale,
                options: locales.map((locale) => ({value: locale, label: locale})),
            }
            : undefined,
        items,
    };
});
