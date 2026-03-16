( () => {
	const state = {
		timer: null,
		progressRaf: null,
		startedAt: 0,
		durationMs: 5000,
		focusedBeforeOpen: null,
	};

	const selectors = {
		root: '#wup-popup',
		overlay: '.wup-popup__overlay',
		close: '.wup-popup__close',
		continue: '.wup-popup__continue',
		viewCart: '.wup-popup__view-cart',
		progressBar: '.wup-popup__progress-bar',
		addedName: '.wup-popup__product-name',
		addedPrice: '.wup-popup__product-price',
		addedImage: '.wup-popup__image',
		upsellBlock: '.wup-popup__upsell',
		upsellName: '.wup-popup__upsell-name',
		upsellPrice: '.wup-popup__upsell-price',
		upsellImage: '.wup-popup__upsell-image',
		upsellAdd: '.wup-popup__upsell-add',
	};

	const getRoot = () => document.querySelector( selectors.root );

	const getConfig = () => {
		const cfg = window.wupPopupData || {};
		return {
			restUrl: ( cfg.rest_url || '/wp-json/wup/v1/' ).replace(
				/\/+$/,
				'/'
			),
			dismissSec: Number(
				cfg.popup_auto_dismiss || cfg.auto_dismiss_seconds || 5
			),
			storeNonce: cfg.store_api_nonce || '',
		};
	};

	const q = ( root, selector ) => root.querySelector( selector );

	const setText = ( el, text ) => {
		if ( el ) {
			el.textContent = text || '';
		}
	};

	const setImage = ( el, src, alt ) => {
		if ( ! el ) {
			return;
		}
		el.src = src || '';
		el.alt = alt || '';
	};

	const fetchProductDetails = async ( productId ) => {
		const id = Number( productId || 0 );
		if ( ! id ) {
			return null;
		}

		try {
			const response = await fetch(
				`/wp-json/wc/store/v1/products/${ id }`,
				{
					method: 'GET',
					credentials: 'same-origin',
				}
			);

			if ( ! response.ok ) {
				return null;
			}

			return response.json();
		} catch {
			return null;
		}
	};

	const closePopup = () => {
		const root = getRoot();
		if ( ! root ) {
			return;
		}

		root.classList.remove( 'wup-popup--visible' );
		root.setAttribute( 'aria-hidden', 'true' );

		clearTimeout( state.timer );
		state.timer = null;

		if ( state.progressRaf ) {
			window.cancelAnimationFrame( state.progressRaf );
			state.progressRaf = null;
		}

		const progress = q( root, selectors.progressBar );
		if ( progress ) {
			progress.style.width = '0%';
		}

		if (
			state.focusedBeforeOpen &&
			typeof state.focusedBeforeOpen.focus === 'function'
		) {
			state.focusedBeforeOpen.focus();
		}
		state.focusedBeforeOpen = null;
	};

	const animateProgress = ( root, durationMs ) => {
		const progress = q( root, selectors.progressBar );
		if ( ! progress ) {
			return;
		}

		state.startedAt = performance.now();
		progress.style.width = '0%';

		const tick = ( now ) => {
			const elapsed = now - state.startedAt;
			const ratio = Math.min( 1, elapsed / durationMs );
			progress.style.width = `${ Math.round( ratio * 100 ) }%`;

			if ( ratio < 1 ) {
				state.progressRaf = window.requestAnimationFrame( tick );
			}
		};

		state.progressRaf = window.requestAnimationFrame( tick );
	};

	const focusables = ( root ) =>
		Array.from(
			root.querySelectorAll(
				'a[href],button:not([disabled]),textarea,input,select,[tabindex]:not([tabindex="-1"])'
			)
		);

	const trapFocus = ( root ) => {
		root.addEventListener( 'keydown', ( event ) => {
			if ( event.key === 'Escape' ) {
				event.preventDefault();
				closePopup();
				return;
			}

			if ( event.key !== 'Tab' ) {
				return;
			}

			const nodes = focusables( root );
			if ( ! nodes.length ) {
				return;
			}

			const first = nodes[ 0 ];
			const last = nodes[ nodes.length - 1 ];
			const active = root.ownerDocument.activeElement;

			if ( event.shiftKey && active === first ) {
				event.preventDefault();
				last.focus();
			} else if ( ! event.shiftKey && active === last ) {
				event.preventDefault();
				first.focus();
			}
		} );
	};

	const requestUpsell = async ( productId ) => {
		const { restUrl } = getConfig();
		const endpoint = `${ restUrl }products/suggest?product_id=${ encodeURIComponent(
			productId
		) }`;

		const response = await fetch( endpoint, {
			method: 'GET',
			credentials: 'same-origin',
		} );

		if ( ! response.ok ) {
			return null;
		}

		return response.json();
	};

	const requestStoreAdd = async ( productId ) => {
		const { storeNonce } = getConfig();

		const response = await fetch( '/wp-json/wc/store/v1/cart/add-item', {
			method: 'POST',
			credentials: 'same-origin',
			headers: {
				'Content-Type': 'application/json',
				Nonce: storeNonce,
			},
			body: JSON.stringify( { id: Number( productId ), quantity: 1 } ),
		} );

		if ( ! response.ok ) {
			throw new Error( 'Store API add-item failed' );
		}

		return response.json();
	};

	const showPopup = async ( added ) => {
		const root = getRoot();
		if ( ! root ) {
			return;
		}

		state.focusedBeforeOpen = root.ownerDocument.activeElement;

		const name = q( root, selectors.addedName );
		const price = q( root, selectors.addedPrice );
		const image = q( root, selectors.addedImage );

		const resolved = { ...added };

		if (
			resolved.productId &&
			( ! resolved.name || ! resolved.imageUrl || ! resolved.priceHtml )
		) {
			const details = await fetchProductDetails( resolved.productId );
			if ( details ) {
				if ( ! resolved.name ) {
					resolved.name = details.name || '';
				}
				if ( ! resolved.imageUrl ) {
					const firstImage =
						Array.isArray( details.images ) && details.images.length
							? details.images[ 0 ]
							: null;
					resolved.imageUrl = firstImage?.src || '';
				}
				if ( ! resolved.priceHtml ) {
					resolved.priceHtml = details.prices?.price || '';
				}
			}
		}

		setText( name, resolved.name || '' );
		setText( price, resolved.priceHtml || resolved.price || '' );
		setImage( image, resolved.imageUrl || '', resolved.name || '' );

		const upsellBlock = q( root, selectors.upsellBlock );
		const upsellName = q( root, selectors.upsellName );
		const upsellPrice = q( root, selectors.upsellPrice );
		const upsellImage = q( root, selectors.upsellImage );
		const upsellAdd = q( root, selectors.upsellAdd );

		if ( upsellBlock ) {
			upsellBlock.style.display = 'none';
		}

		let upsell = null;

		if ( added.productId ) {
			upsell = await requestUpsell( added.productId );
		}

		if ( upsell && upsellBlock ) {
			setText( upsellName, upsell.name || '' );
			setText( upsellPrice, upsell.price_html || upsell.price || '' );
			setImage( upsellImage, upsell.image_url || '', upsell.name || '' );

			if ( upsellAdd ) {
				upsellAdd.setAttribute(
					'data-product-id',
					String( upsell.id || '' )
				);
			}

			upsellBlock.style.display = '';
		}

		root.classList.add( 'wup-popup--visible' );
		root.setAttribute( 'aria-hidden', 'false' );

		const dismissMs = Math.max( 1000, getConfig().dismissSec * 1000 );

		if (
			! window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches
		) {
			animateProgress( root, dismissMs );
		}

		state.timer = setTimeout( () => {
			closePopup();
		}, dismissMs );

		const closeButton = q( root, selectors.close );
		if ( closeButton ) {
			closeButton.focus();
		}
	};

	const parseAddedFromUrl = () => {
		const params = new URLSearchParams( window.location.search );
		const productId = params.get( 'added-to-cart' );

		if ( ! productId ) {
			return null;
		}

		return {
			productId: Number( productId ),
			name: '',
			price: '',
			priceHtml: '',
			imageUrl: '',
		};
	};

	const bindPopupEvents = () => {
		const root = getRoot();
		if ( ! root ) {
			return;
		}

		const overlay = q( root, selectors.overlay );
		const close = q( root, selectors.close );
		const cont = q( root, selectors.continue );
		const upsellAdd = q( root, selectors.upsellAdd );

		if ( overlay ) {
			overlay.addEventListener( 'click', closePopup );
		}
		if ( close ) {
			close.addEventListener( 'click', closePopup );
		}
		if ( cont ) {
			cont.addEventListener( 'click', closePopup );
		}

		if ( upsellAdd ) {
			upsellAdd.addEventListener( 'click', async ( event ) => {
				event.preventDefault();
				const productId = upsellAdd.getAttribute( 'data-product-id' );
				if ( ! productId ) {
					return;
				}

				const original = upsellAdd.textContent;
				upsellAdd.disabled = true;
				upsellAdd.textContent = '...';

				try {
					await requestStoreAdd( productId );
					upsellAdd.textContent = '✓';

					if (
						window.jQuery &&
						window.jQuery( document.body ).trigger
					) {
						window
							.jQuery( document.body )
							.trigger( 'wc_fragment_refresh' );
					}
				} catch {
					upsellAdd.textContent = 'Error';
					setTimeout( () => {
						upsellAdd.textContent = original || '+ Add';
						upsellAdd.disabled = false;
					}, 1000 );
					return;
				}

				setTimeout( () => {
					upsellAdd.textContent = original || '+ Add';
					upsellAdd.disabled = false;
				}, 1200 );
			} );
		}

		trapFocus( root );
	};

	const bindWooEvent = () => {
		if ( ! window.jQuery ) {
			return;
		}

		window
			.jQuery( document.body )
			.on(
				'added_to_cart',
				( _event, _fragments, _cartHash, $button ) => {
					const productId = Number(
						$button?.data?.( 'product_id' ) ||
							$button?.attr?.( 'data-product_id' ) ||
							0
					);

					const productName =
						$button?.attr?.( 'data-product_name' ) ||
						$button?.data?.( 'product_name' ) ||
						'';

					const added = {
						productId,
						name: productName,
						price: '',
						priceHtml: '',
						imageUrl: '',
					};

					showPopup( added );
				}
			);
	};

	document.addEventListener( 'DOMContentLoaded', () => {
		const root = getRoot();
		if ( ! root ) {
			return;
		}

		bindPopupEvents();
		bindWooEvent();

		const fromUrl = parseAddedFromUrl();
		if ( fromUrl ) {
			showPopup( fromUrl );
		}

		const viewCart = q( root, selectors.viewCart );
		if ( viewCart ) {
			viewCart.addEventListener( 'click', () => {
				closePopup();
			} );
		}
	} );
} )();
