/**
 * Admin app entrypoint — mounts SettingsPage into #wup-admin-root.
 */

import { render } from '@wordpress/element';
import SettingsPage from './components/SettingsPage';

const root = document.getElementById( 'wup-admin-root' );
if ( root ) {
	render( <SettingsPage />, root );
}
