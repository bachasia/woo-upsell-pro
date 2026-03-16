import { useEffect, useState } from '@wordpress/element';
import { Button, Notice, Spinner } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { getCampaigns, deleteCampaign } from '../api/api-client';

export default function CampaignList( { onCreate, onEdit } ) {
	const [ loading, setLoading ] = useState( true );
	const [ campaigns, setCampaigns ] = useState( [] );
	const [ error, setError ] = useState( '' );
	const [ notice, setNotice ] = useState( '' );
	const [ pendingDeleteId, setPendingDeleteId ] = useState( null );

	const loadCampaigns = async () => {
		setLoading( true );
		setError( '' );

		try {
			const response = await getCampaigns( { per_page: 50, page: 1 } );
			setCampaigns( response?.campaigns || [] );
		} catch ( err ) {
			setError(
				err?.message ||
					__( 'Failed to load campaigns.', 'woo-upsell-pro' )
			);
		} finally {
			setLoading( false );
		}
	};

	useEffect( () => {
		loadCampaigns();
	}, [] );

	const onDelete = async ( id ) => {
		try {
			await deleteCampaign( id );
			setNotice( __( 'Campaign deleted.', 'woo-upsell-pro' ) );
			setPendingDeleteId( null );
			await loadCampaigns();
		} catch ( err ) {
			setError(
				err?.message || __( 'Delete failed.', 'woo-upsell-pro' )
			);
		}
	};

	return (
		<div className="wup-admin-panel">
			<div className="wup-admin-panel__header">
				<h2>{ __( 'Campaigns', 'woo-upsell-pro' ) }</h2>
				<Button variant="primary" onClick={ onCreate }>
					{ __( 'Create New', 'woo-upsell-pro' ) }
				</Button>
			</div>

			{ notice && (
				<Notice
					status="success"
					isDismissible
					onRemove={ () => setNotice( '' ) }
				>
					{ notice }
				</Notice>
			) }

			{ error && (
				<Notice
					status="error"
					isDismissible
					onRemove={ () => setError( '' ) }
				>
					{ error }
				</Notice>
			) }

			{ loading ? <Spinner /> : null }
			{ ! loading && campaigns.length === 0 ? (
				<p>{ __( 'No campaigns found.', 'woo-upsell-pro' ) }</p>
			) : null }
			{ ! loading && campaigns.length > 0 ? (
				<table className="widefat striped">
					<thead>
						<tr>
							<th>{ __( 'Name', 'woo-upsell-pro' ) }</th>
							<th>{ __( 'Type', 'woo-upsell-pro' ) }</th>
							<th>{ __( 'Status', 'woo-upsell-pro' ) }</th>
							<th>{ __( 'Actions', 'woo-upsell-pro' ) }</th>
						</tr>
					</thead>
					<tbody>
						{ campaigns.map( ( campaign ) => (
							<tr key={ campaign.id }>
								<td>{ campaign.title }</td>
								<td>{ campaign.type }</td>
								<td>{ campaign.status }</td>
								<td>
									<Button
										variant="secondary"
										onClick={ () => onEdit( campaign.id ) }
									>
										{ __( 'Edit', 'woo-upsell-pro' ) }
									</Button>{ ' ' }
									{ pendingDeleteId === campaign.id ? (
										<>
											<Button
												variant="link"
												isDestructive
												onClick={ () =>
													onDelete( campaign.id )
												}
											>
												{ __(
													'Confirm delete',
													'woo-upsell-pro'
												) }
											</Button>{ ' ' }
											<Button
												variant="secondary"
												onClick={ () =>
													setPendingDeleteId( null )
												}
											>
												{ __(
													'Cancel',
													'woo-upsell-pro'
												) }
											</Button>
										</>
									) : (
										<Button
											variant="link"
											isDestructive
											onClick={ () =>
												setPendingDeleteId(
													campaign.id
												)
											}
										>
											{ __( 'Delete', 'woo-upsell-pro' ) }
										</Button>
									) }
								</td>
							</tr>
						) ) }
					</tbody>
				</table>
			) : null }
		</div>
	);
}
