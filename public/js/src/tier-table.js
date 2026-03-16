( () => {
	const getQuantityInput = () =>
		document.querySelector( 'form.cart input.qty' ) ||
		document.querySelector( 'input.qty' );

	const updateActiveTierRow = ( table, qty ) => {
		const rows = Array.from(
			table.querySelectorAll( '.wup-tier-table__row' )
		);

		if ( ! rows.length ) {
			return;
		}

		let activeIndex = -1;

		rows.forEach( ( row, index ) => {
			const minQty = Number( row.getAttribute( 'data-min-qty' ) || '1' );
			if ( qty >= minQty ) {
				activeIndex = index;
			}
		} );

		rows.forEach( ( row, index ) => {
			row.classList.toggle(
				'wup-tier-table__row--active',
				index === activeIndex
			);
			const status = row.querySelector( '.wup-tier-table__status' );
			if ( ! status ) {
				return;
			}

			status.classList.toggle(
				'wup-tier-table__status--active',
				index === activeIndex
			);
			status.classList.toggle(
				'wup-tier-table__status--locked',
				index !== activeIndex
			);
			status.textContent = index === activeIndex ? 'current' : 'locked';
		} );
	};

	const initProductTable = ( table ) => {
		const qtyInput = getQuantityInput();
		if ( ! qtyInput ) {
			return;
		}

		const sync = () => {
			const qty = Number( qtyInput.value || '1' );
			updateActiveTierRow( table, Number.isNaN( qty ) ? 1 : qty );
		};

		qtyInput.addEventListener( 'change', sync );
		qtyInput.addEventListener( 'input', sync );
		sync();
	};

	document.addEventListener( 'DOMContentLoaded', () => {
		const tables = Array.from(
			document.querySelectorAll( '.wup-tier-table' )
		);

		if ( ! tables.length ) {
			return;
		}

		tables.forEach( ( table ) => {
			const mode = table.classList.contains( 'wup-tier-table--product' )
				? 'product'
				: 'cart';

			if ( mode === 'product' ) {
				initProductTable( table );
			}
		} );
	} );
} )();
