import { useEffect, useState } from '@wordpress/element';
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
import { getSettings, saveSettings } from '../api/api-client';

const defaultSettings = {
	enable_popup: true,
	enable_cart_upsell: true,
	enable_bmsm: true,
	enable_email_coupon: true,
	popup_auto_dismiss: 5,
	popup_heading: 'Customers also bought',
	cart_upsell_heading: 'You might also like',
	cart_upsell_max_products: 3,
	bmsm_tiers: [],
	email_coupon: {
		enabled: true,
		discount_type: 'percent',
		discount_amount: 10,
		expiry_days: 30,
		min_order_amount: 0,
		email_heading: "Thank you! Here's a gift.",
	},
};

export default function SettingsPage() {
	const [ loading, setLoading ] = useState( true );
	const [ saving, setSaving ] = useState( false );
	const [ settings, setSettings ] = useState( defaultSettings );
	const [ error, setError ] = useState( '' );
	const [ notice, setNotice ] = useState( '' );

	useEffect( () => {
		let active = true;

		( async () => {
			try {
				const data = await getSettings();
				if ( ! active ) {
					return;
				}
				setSettings( { ...defaultSettings, ...( data || {} ) } );
			} catch ( err ) {
				setError(
					err?.message ||
						__( 'Failed to load settings.', 'woo-upsell-pro' )
				);
			} finally {
				if ( active ) {
					setLoading( false );
				}
			}
		} )();

		return () => {
			active = false;
		};
	}, [] );

	const updateValue = ( key, value ) => {
		setSettings( ( prev ) => ( { ...prev, [ key ]: value } ) );
	};

	const updateEmailValue = ( key, value ) => {
		setSettings( ( prev ) => ( {
			...prev,
			email_coupon: {
				...prev.email_coupon,
				[ key ]: value,
			},
		} ) );
	};

	const onSave = async () => {
		setSaving( true );
		setError( '' );
		setNotice( '' );

		try {
			const saved = await saveSettings( settings );
			setSettings( { ...defaultSettings, ...( saved || {} ) } );
			setNotice( __( 'Settings saved.', 'woo-upsell-pro' ) );
		} catch ( err ) {
			setError(
				err?.message ||
					__( 'Failed to save settings.', 'woo-upsell-pro' )
			);
		} finally {
			setSaving( false );
		}
	};

	if ( loading ) {
		return <p>{ __( 'Loading…', 'woo-upsell-pro' ) }</p>;
	}

	return (
		<div className="wup-admin-panel">
			<h2>{ __( 'Settings', 'woo-upsell-pro' ) }</h2>

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

			<Panel>
				<PanelBody
					title={ __( 'General', 'woo-upsell-pro' ) }
					initialOpen
				>
					<ToggleControl
						label={ __(
							'Enable Add-to-Cart Popup',
							'woo-upsell-pro'
						) }
						checked={ Boolean( settings.enable_popup ) }
						onChange={ ( value ) =>
							updateValue( 'enable_popup', value )
						}
					/>
					<ToggleControl
						label={ __(
							'Enable Cart Upsell Widget',
							'woo-upsell-pro'
						) }
						checked={ Boolean( settings.enable_cart_upsell ) }
						onChange={ ( value ) =>
							updateValue( 'enable_cart_upsell', value )
						}
					/>
					<ToggleControl
						label={ __(
							'Enable Buy More Save More',
							'woo-upsell-pro'
						) }
						checked={ Boolean( settings.enable_bmsm ) }
						onChange={ ( value ) =>
							updateValue( 'enable_bmsm', value )
						}
					/>
					<ToggleControl
						label={ __(
							'Enable Post-Purchase Coupon',
							'woo-upsell-pro'
						) }
						checked={ Boolean( settings.enable_email_coupon ) }
						onChange={ ( value ) =>
							updateValue( 'enable_email_coupon', value )
						}
					/>
				</PanelBody>

				<PanelBody
					title={ __( 'Popup', 'woo-upsell-pro' ) }
					initialOpen
				>
					<TextControl
						label={ __( 'Heading', 'woo-upsell-pro' ) }
						value={ settings.popup_heading || '' }
						onChange={ ( value ) =>
							updateValue( 'popup_heading', value )
						}
					/>
					<TextControl
						label={ __(
							'Auto-dismiss (seconds)',
							'woo-upsell-pro'
						) }
						type="number"
						min={ 0 }
						value={ String( settings.popup_auto_dismiss ?? 5 ) }
						onChange={ ( value ) =>
							updateValue(
								'popup_auto_dismiss',
								Number( value ) || 0
							)
						}
					/>
				</PanelBody>

				<PanelBody
					title={ __( 'Cart Upsell', 'woo-upsell-pro' ) }
					initialOpen
				>
					<TextControl
						label={ __( 'Heading', 'woo-upsell-pro' ) }
						value={ settings.cart_upsell_heading || '' }
						onChange={ ( value ) =>
							updateValue( 'cart_upsell_heading', value )
						}
					/>
					<TextControl
						label={ __( 'Max products', 'woo-upsell-pro' ) }
						type="number"
						min={ 1 }
						max={ 6 }
						value={ String(
							settings.cart_upsell_max_products ?? 3
						) }
						onChange={ ( value ) =>
							updateValue(
								'cart_upsell_max_products',
								Number( value ) || 3
							)
						}
					/>
				</PanelBody>

				<PanelBody
					title={ __( 'Email Coupon', 'woo-upsell-pro' ) }
					initialOpen
				>
					<ToggleControl
						label={ __( 'Enabled', 'woo-upsell-pro' ) }
						checked={ Boolean( settings.email_coupon?.enabled ) }
						onChange={ ( value ) =>
							updateEmailValue( 'enabled', value )
						}
					/>
					<SelectControl
						label={ __( 'Discount type', 'woo-upsell-pro' ) }
						value={
							settings.email_coupon?.discount_type || 'percent'
						}
						options={ [
							{
								label: __( 'Percent', 'woo-upsell-pro' ),
								value: 'percent',
							},
							{
								label: __( 'Fixed cart', 'woo-upsell-pro' ),
								value: 'fixed_cart',
							},
						] }
						onChange={ ( value ) =>
							updateEmailValue( 'discount_type', value )
						}
					/>
					<TextControl
						label={ __( 'Discount amount', 'woo-upsell-pro' ) }
						type="number"
						min={ 0 }
						value={ String(
							settings.email_coupon?.discount_amount ?? 10
						) }
						onChange={ ( value ) =>
							updateEmailValue(
								'discount_amount',
								Number( value ) || 0
							)
						}
					/>
					<TextControl
						label={ __( 'Expiry days', 'woo-upsell-pro' ) }
						type="number"
						min={ 1 }
						value={ String(
							settings.email_coupon?.expiry_days ?? 30
						) }
						onChange={ ( value ) =>
							updateEmailValue(
								'expiry_days',
								Number( value ) || 1
							)
						}
					/>
					<TextControl
						label={ __( 'Min order amount', 'woo-upsell-pro' ) }
						type="number"
						min={ 0 }
						value={ String(
							settings.email_coupon?.min_order_amount ?? 0
						) }
						onChange={ ( value ) =>
							updateEmailValue(
								'min_order_amount',
								Number( value ) || 0
							)
						}
					/>
					<TextControl
						label={ __( 'Email heading', 'woo-upsell-pro' ) }
						value={ settings.email_coupon?.email_heading || '' }
						onChange={ ( value ) =>
							updateEmailValue( 'email_heading', value )
						}
					/>
				</PanelBody>
			</Panel>

			<div style={ { marginTop: 16 } }>
				<Button variant="primary" isBusy={ saving } onClick={ onSave }>
					{ saving
						? __( 'Saving…', 'woo-upsell-pro' )
						: __( 'Save settings', 'woo-upsell-pro' ) }
				</Button>
			</div>
		</div>
	);
}
