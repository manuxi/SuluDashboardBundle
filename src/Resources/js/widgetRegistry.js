// Tiles with their own React component (widget type "custom"), built like Sulu's own registries (viewRegistry,
// fieldRegistry). A bundle registers its component under the key of its tile; the server decides per user which tiles
// are shown (src/Admin/DashboardAdmin.php).
//
// The component gets the props: widget (key, title, type, column), data (the answer of getData()), locale, locales,
// router.
class WidgetRegistry {
    widgets = {};

    clear() {
        this.widgets = {};
    }

    has(name) {
        return !!this.widgets[name];
    }

    add(name, widget) {
        if (name in this.widgets) {
            throw new Error('The key "' + name + '" has already been used for another dashboard widget');
        }

        this.widgets[name] = widget;
    }

    get(name) {
        if (!(name in this.widgets)) {
            throw new Error('The dashboard widget with the key "' + name + '" is not defined.');
        }

        return this.widgets[name];
    }
}

export default new WidgetRegistry();
