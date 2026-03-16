( () => {
	const requestAddToCart = async ( productId ) => {
		const body = JSON.stringify( { id: Number( productId ), quantity: 1 } );
		const nonce = window.wupCartUpsellData?.store_api_nonce || '';

		const response = await fetch( '/wp-json/wc/store/v1/cart/add-item', {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json',
				Nonce: nonce,
			},
			body,
			credentials: 'same-origin',
		} );

		if ( ! response.ok ) {
			throw new Error( 'Add to cart failed' );
		}

		return response.json();
	};

	const setButtonState = ( button, state, text ) => {
		button.classList.remove(
			'wup-cart-upsell__add--loading',
			'wup-cart-upsell__add--success',
			'wup-cart-upsell__add--error'
		);

		if ( state ) {
			button.classList.add( `wup-cart-upsell__add--${ state }` );
		}

		button.textContent = text;
	};

	const onAddClick = async ( button ) => {
		const productId = button.getAttribute( 'data-product-id' );
		if ( ! productId ) {
			return;
		}

		const originalText = button.textContent;
		button.disabled = true;

		try {
			setButtonState( button, 'loading', '...' );
			await requestAddToCart( productId );

			setButtonState( button, 'success', '✓' );

			if ( window.jQuery && window.jQuery( document.body ).trigger ) {
				window.jQuery( document.body ).trigger( 'wc_fragment_refresh' );
			}

			setTimeout( () => {
				const card = button.closest( '.wup-cart-upsell__card' );
				if ( card ) {
					card.style.opacity = '0';
					card.style.pointerEvents = 'none';
					setTimeout( () => {
						card.remove();

						const grid = document.querySelector(
							'.wup-cart-upsell__grid'
						);
						const wrapper =
							document.querySelector( '.wup-cart-upsell' );
						if ( grid && wrapper && grid.children.length === 0 ) {
							wrapper.remove();
						}
					}, 220 );
				}
			}, 700 );
		} catch {
			setButtonState( button, 'error', 'Error' );
			setTimeout( () => {
				button.disabled = false;
				setButtonState( button, '', originalText || '+ Add' );
			}, 1200 );
			return;
		}

		button.disabled = false;
	};

	document.addEventListener( 'DOMContentLoaded', () => {
		document
			.querySelectorAll( '.wup-cart-upsell__add' )
			.forEach( ( button ) => {
				button.addEventListener( 'click', ( event ) => {
					event.preventDefault();
					onAddClick( button );
				} );
			} );
	} );
} )();
