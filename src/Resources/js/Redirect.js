// View sulu_dashboard.redirect: stands on the path of a view that the dashboard has replaced by a tab (for example
// /log-stats of the log statistics) and sends the browser to that tab. Sulu's own tab view redirects the same way.
// Option redirectTo: name of the view to open.
import React from 'react';

export default class Redirect extends React.Component {
    componentDidMount() {
        const {route, router} = this.props;

        router.redirect(route.options.redirectTo);
    }

    render() {
        return null;
    }
}
