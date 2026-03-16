import { useEffect, useMemo, useState } from '@wordpress/element';
import {
	Button,
	Notice,
	Panel,
	PanelBody,
	SelectControl,
	TextControl,
	ToggleControl,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import {
	createCampaign,
	getCampaign,
	searchProducts,
	updateCampaign,
} from '../api/api-client';

const TYPES = [
	{ label: 'Popup', value: 'popup' },
	{ label: 'Cart Upsell', value: 'cart_upsell' },
	{ label: 'Buy More Save More', value: 'bmsm' },
	{ label: 'Email Coupon', value: 'email_coupon' },
];

export default function CampaignEditor( { id, onBack } ) {
	const [ loading, setLoading ] = useState( Boolean( id ) );
	const [ saving, setSaving ] = useState( false );
	const [ error, setError ] = useState( '' );
	const [ notice, setNotice ] = useState( '' );

	const [ title, setTitle ] = useState( '' );
	const [ type, setType ] = useState( 'popup' );
	const [ status, setStatus ] = useState( 'draft' );
	const [ productSearch, setProductSearch ] = useState( '' );
	const [ searchResults, setSearchResults ] = useState( [] );
	const [ products, setProducts ] = useState( [] );
	const [ tiers, setTiers ] = useState( [
		{ qty: 2, discount: 5, type: 'percent' },
	] );

	const titleText = useMemo(
		() =>
			id
				? __( 'Edit Campaign', 'woo-upsell-pro' )
				: __( 'Create Campaign', 'woo-upsell-pro' ),
		[ id ]
	);

	useEffect( () => {
		let active = true;

		if ( ! id ) {
			return undefined;
		}

		( async () => {
			try {
				const campaign = await getCampaign( id );
				if ( ! active || ! campaign ) {
					return;
				}

				setTitle( campaign.title || '' );
				setType( campaign.type || 'popup' );
				setStatus( campaign.status || 'draft' );
				setProducts(
					Array.isArray( campaign.products ) ? campaign.products : []
				);
				if (
					Array.isArray( campaign.discount_tiers ) &&
					campaign.discount_tiers.length > 0
				) {
					setTiers( campaign.discount_tiers );
				}
			} catch ( err ) {
				setError(
					err?.message ||
						__( 'Failed to load campaign.', 'woo-upsell-pro' )
				);
			} finally {
				setLoading( false );
			}
		} )();

		return () => {
			active = false;
		};
	}, [ id ] );

	useEffect( () => {
		if ( ! productSearch || productSearch.length < 2 ) {
			setSearchResults( [] );
			return undefined;
		}

		const timer = setTimeout( async () => {
			try {
				const results = await searchProducts( productSearch, '', 20 );
				setSearchResults( Array.isArray( results ) ? results : [] );
			} catch {
				setSearchResults( [] );
			}
		}, 300 );

		return () => clearTimeout( timer );
	}, [ productSearch ] );

	const addProduct = ( productId ) => {
		setProducts( ( prev ) =>
			prev.includes( productId ) ? prev : [ ...prev, productId ]
		);
	};

	const removeProduct = ( productId ) => {
		setProducts( ( prev ) =>
			prev.filter( ( idValue ) => idValue !== productId )
		);
	};

	const addTier = () => {
		setTiers( ( prev ) => [
			...prev,
			{ qty: 1, discount: 1, type: 'percent' },
		] );
	};

	const updateTier = ( index, key, value ) => {
		setTiers( ( prev ) =>
			prev.map( ( tier, tierIndex ) =>
				tierIndex === index ? { ...tier, [ key ]: value } : tier
			)
		);
	};

	const removeTier = ( index ) => {
		setTiers( ( prev ) =>
			prev.filter( ( _, tierIndex ) => tierIndex !== index )
		);
	};

	const onSave = async () => {
		setSaving( true );
		setError( '' );
		setNotice( '' );

		if ( ! title.trim() ) {
			setSaving( false );
			setError( __( 'Campaign name is required.', 'woo-upsell-pro' ) );
			return;
		}

		if ( products.length < 1 ) {
			setSaving( false );
			setError(
				__( 'Select at least one target product.', 'woo-upsell-pro' )
			);
			return;
		}

		const payload = {
			title,
			type,
			status,
			products,
			discount_tiers: type === 'bmsm' ? tiers : [],
			rules: {},
			settings: {},
		};

		try {
			if ( id ) {
				await updateCampaign( id, payload );
			} else {
				await createCampaign( payload );
			}

			setNotice( __( 'Campaign saved.', 'woo-upsell-pro' ) );
			setTimeout( () => onBack(), 400 );
		} catch ( err ) {
			setError( err?.message || __( 'Save failed.', 'woo-upsell-pro' ) );
		} finally {
			setSaving( false );
		}
	};

	if ( loading ) {
		return <p>{ __( 'Loading…', 'woo-upsell-pro' ) }</p>;
	}

	return (
		<div className="wup-admin-panel">
			<div className="wup-admin-panel__header">
				<h2>{ titleText }</h2>
				<Button variant="secondary" onClick={ onBack }>
					{ __( 'Back', 'woo-upsell-pro' ) }
				</Button>
			</div>

			{ notice && <Notice status="success">{ notice }</Notice> }
			{ error && <Notice status="error">{ error }</Notice> }

			<Panel>
				<PanelBody
					title={ __( 'General', 'woo-upsell-pro' ) }
					initialOpen
				>
					<TextControl
						label={ __( 'Campaign name', 'woo-upsell-pro' ) }
						value={ title }
						onChange={ setTitle }
					/>

					<SelectControl
						label={ __( 'Type', 'woo-upsell-pro' ) }
						value={ type }
						options={ TYPES }
						onChange={ setType }
					/>

					<ToggleControl
						label={ __( 'Active', 'woo-upsell-pro' ) }
						checked={ status === 'active' }
						onChange={ ( checked ) =>
							setStatus( checked ? 'active' : 'paused' )
						}
					/>
				</PanelBody>

				<PanelBody
					title={ __( 'Target products', 'woo-upsell-pro' ) }
					initialOpen
				>
					<TextControl
						label={ __( 'Search products', 'woo-upsell-pro' ) }
						value={ productSearch }
						onChange={ setProductSearch }
					/>

					{ searchResults.length > 0 && (
						<ul>
							{ searchResults.map( ( product ) => (
								<li key={ product.id }>
									<Button
										variant="link"
										onClick={ () =>
											addProduct( product.id )
										}
									>
										{ product.name }{ ' ' }
										{ product.sku
											? `(${ product.sku })`
											: '' }
									</Button>
								</li>
							) ) }
						</ul>
					) }

					{ products.length > 0 && (
						<ul>
							{ products.map( ( productId ) => (
								<li key={ productId }>
									#{ productId }{ ' ' }
									<Button
										variant="link"
										isDestructive
										onClick={ () =>
											removeProduct( productId )
										}
									>
										{ __( 'Remove', 'woo-upsell-pro' ) }
									</Button>
								</li>
							) ) }
						</ul>
					) }
				</PanelBody>

				{ type === 'bmsm' && (
					<PanelBody
						title={ __( 'Discount tiers', 'woo-upsell-pro' ) }
						initialOpen
					>
						{ tiers.map( ( tier, index ) => (
							<div
								key={ `${ index }-${ tier.qty }-${ tier.discount }` }
								style={ {
									display: 'grid',
									gridTemplateColumns: '1fr 1fr auto',
									gap: 8,
									marginBottom: 8,
								} }
							>
								<TextControl
									label={ __( 'Qty', 'woo-upsell-pro' ) }
									type="number"
									min={ 1 }
									value={ String( tier.qty ) }
									onChange={ ( value ) =>
										updateTier(
											index,
											'qty',
											Number( value ) || 1
										)
									}
								/>
								<TextControl
									label={ __(
										'Discount %',
										'woo-upsell-pro'
									) }
									type="number"
									min={ 1 }
									value={ String( tier.discount ) }
									onChange={ ( value ) =>
										updateTier(
											index,
											'discount',
											Number( value ) || 1
										)
									}
								/>
								<Button
									variant="link"
									isDestructive
									onClick={ () => removeTier( index ) }
								>
									{ __( 'Remove', 'woo-upsell-pro' ) }
								</Button>
							</div>
						) ) }

						<Button variant="secondary" onClick={ addTier }>
							{ __( 'Add tier', 'woo-upsell-pro' ) }
						</Button>
					</PanelBody>
				) }
			</Panel>

			<div style={ { marginTop: 16 } }>
				<Button variant="primary" isBusy={ saving } onClick={ onSave }>
					{ saving
						? __( 'Saving…', 'woo-upsell-pro' )
						: __( 'Save campaign', 'woo-upsell-pro' ) }
				</Button>
			</div>
		</div>
	);
}
