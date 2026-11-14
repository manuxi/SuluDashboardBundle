import {viewRegistry} from 'sulu-admin-bundle/containers';
import Dashboard from './Dashboard';
import widgetRegistry from './widgetRegistry';
import Activities from './widgets/Activities';
import OpenItems from './widgets/OpenItems';
import Translations from './widgets/Translations';

viewRegistry.add('sulu_dashboard.dashboard', Dashboard);

// the custom tiles of this bundle use the same registry as the tiles of other bundles
widgetRegistry.add('sulu_open_items', OpenItems);
widgetRegistry.add('sulu_translations', Translations);
widgetRegistry.add('sulu_activities', Activities);

export {widgetRegistry};
