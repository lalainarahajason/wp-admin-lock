/* global: lbsAdmin */
( function () {
	'use strict';

	const { nonce, restUrl } = window.lbsAdmin || {};

	/**
	 * Wrapper fetch pour l'API REST WordPress.
	 *
	 * @param {string} endpoint   Endpoint relatif (ex: '/config').
	 * @param {Object} [options]  Options fetch.
	 * @returns {Promise<Object>}
	 */
	async function lbsFetch( endpoint, options = {} ) {
		const url = restUrl + endpoint;
		const defaults = {
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': nonce,
			},
		};

		const response = await fetch( url, { ...defaults, ...options } );

		if ( ! response.ok ) {
			const err = await response.json().catch( () => ( { message: response.statusText } ) );
			throw new Error( err.message || 'Request failed' );
		}

		return response.json();
	}

	/**
	 * Afficher une notice admin temporaire.
	 *
	 * @param {string} message   Texte du message.
	 * @param {'success'|'error'|'warning'} type Type de notice.
	 */
	function showNotice( message, type = 'success' ) {
		const notice = document.createElement( 'div' );
		notice.className = `notice notice-${ type } is-dismissible`;
		notice.innerHTML = `<p>${ message }</p>`;

		const wrap = document.querySelector( '.lbs-wrap' );
		if ( wrap ) {
			wrap.insertAdjacentElement( 'afterbegin', notice );
			setTimeout( () => notice.remove(), 4000 );
		}
	}

	/**
	 * Copier le token de récupération au clic.
	 */
	document.querySelectorAll( '[data-lbs-copy]' ).forEach( ( btn ) => {
		btn.addEventListener( 'click', async () => {
			const target = document.getElementById( btn.dataset.lbsCopy );
			if ( ! target ) return;

			try {
				await navigator.clipboard.writeText( target.textContent.trim() );
				showNotice( 'Token copié dans le presse-papier.', 'success' );
			} catch {
				showNotice( 'Impossible de copier automatiquement.', 'warning' );
			}
		} );
	} );

	// Exposer lbsFetch pour les modules futurs.
	window.lbsFetch = lbsFetch;
	window.lbsShowNotice = showNotice;
}() );
