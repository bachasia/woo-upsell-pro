import { render, useState } from '@wordpress/element';
import { Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import CampaignList from './components/CampaignList';
import CampaignEditor from './components/CampaignEditor';
import SettingsPage from './components/SettingsPage';

const views = {
	list: 'list',
	edit: 'edit',
	settings: 'settings',
};

function App() {
	const [ view, setView ] = useState( views.list );
	const [ editId, setEditId ] = useState( null );

	const openCreate = () => {
		setEditId( null );
		setView( views.edit );
	};

	const openEdit = ( id ) => {
		setEditId( id );
		setView( views.edit );
	};

	return (
		<div className="wup-admin">
			<div className="wup-admin__nav" style={ { marginBottom: 16 } }>
				<Button
					variant={ view === views.list ? 'primary' : 'secondary' }
					onClick={ () => setView( views.list ) }
				>
					{ __( 'Campaigns', 'woo-upsell-pro' ) }
				</Button>{ ' ' }
				<Button
					variant={
						view === views.settings ? 'primary' : 'secondary'
					}
					onClick={ () => setView( views.settings ) }
				>
					{ __( 'Settings', 'woo-upsell-pro' ) }
				</Button>
			</div>

			{ view === views.list && (
				<CampaignList onCreate={ openCreate } onEdit={ openEdit } />
			) }
			{ view === views.edit && (
				<CampaignEditor
					id={ editId }
					onBack={ () => setView( views.list ) }
				/>
			) }
			{ view === views.settings && <SettingsPage /> }
		</div>
	);
}

const root = document.getElementById( 'wup-admin-root' );

if ( root ) {
	render( <App />, root );
}
