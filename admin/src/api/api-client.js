import apiFetch from '@wordpress/api-fetch';

const API_BASE = '/wup/v1';

const withQuery = ( path, params = {} ) => {
	const search = new URLSearchParams();

	Object.entries( params ).forEach( ( [ key, value ] ) => {
		if ( value !== undefined && value !== null && value !== '' ) {
			search.set( key, String( value ) );
		}
	} );

	const query = search.toString();
	return query ? `${ path }?${ query }` : path;
};

export const getCampaigns = ( params = {} ) =>
	apiFetch( { path: withQuery( `${ API_BASE }/campaigns`, params ) } );

export const getCampaign = ( id ) =>
	apiFetch( { path: `${ API_BASE }/campaigns/${ id }` } );

export const createCampaign = ( data ) =>
	apiFetch( { path: `${ API_BASE }/campaigns`, method: 'POST', data } );

export const updateCampaign = ( id, data ) =>
	apiFetch( {
		path: `${ API_BASE }/campaigns/${ id }`,
		method: 'PUT',
		data,
	} );

export const deleteCampaign = ( id ) =>
	apiFetch( { path: `${ API_BASE }/campaigns/${ id }`, method: 'DELETE' } );

export const searchProducts = ( search = '', category = '', perPage = 20 ) =>
	apiFetch( {
		path: withQuery( `${ API_BASE }/products`, {
			search,
			category,
			per_page: perPage,
		} ),
	} );

export const suggestProduct = ( productId ) =>
	apiFetch( {
		path: withQuery( `${ API_BASE }/products/suggest`, {
			product_id: productId,
		} ),
	} );

export const getSettings = () => apiFetch( { path: `${ API_BASE }/settings` } );

export const saveSettings = ( data ) =>
	apiFetch( { path: `${ API_BASE }/settings`, method: 'POST', data } );
