( function () {

	// ── Season filters ────────────────────────────────────────────────────────

	document.querySelectorAll( '.sp-podcast-filters' ).forEach( function ( bar ) {
		var wrap  = bar.closest( '.sp-podcasts-wrap' );
		var cards = wrap ? wrap.querySelectorAll( '.sp-podcast-card' ) : [];

		bar.addEventListener( 'click', function ( e ) {
			var btn = e.target.closest( '.sp-podcast-filter' );
			if ( ! btn ) return;

			bar.querySelectorAll( '.sp-podcast-filter' ).forEach( function ( b ) {
				b.classList.remove( 'is-active' );
			} );
			btn.classList.add( 'is-active' );

			var season = btn.dataset.season;
			cards.forEach( function ( card ) {
				if ( ! season || card.dataset.season === season ) {
					card.classList.remove( 'is-hidden' );
				} else {
					card.classList.add( 'is-hidden' );
				}
			} );
		} );
	} );

	// ── Popup ─────────────────────────────────────────────────────────────────

	document.querySelectorAll( '.sp-podcasts-wrap' ).forEach( function ( wrap ) {
		var popup   = wrap.querySelector( '.sp-podcast-popup' );
		var overlay = wrap.querySelector( '.sp-podcast-overlay' );
		if ( ! popup || ! overlay ) return;

		var activeAudio = null;

		function openPopup( data ) {
			popup.querySelector( '.sp-podcast-popup__meta' ).textContent  = data.meta  || '';
			popup.querySelector( '.sp-podcast-popup__title' ).textContent = data.title || '';
			popup.querySelector( '.sp-podcast-popup__date' ).textContent  = data.date  || '';
			popup.querySelector( '.sp-podcast-popup__desc' ).innerHTML    = data.desc  || '';

			var artEl = popup.querySelector( '.sp-podcast-popup__art' );
			artEl.innerHTML = data.art ? '<img src="' + data.art + '" alt="">' : '';

			var player = popup.querySelector( '.sp-podcast-popup__player' );
			if ( data.audio ) {
				player.src = data.audio;
				player.style.display = '';
				activeAudio = player;
			} else {
				player.src = '';
				player.style.display = 'none';
				activeAudio = null;
			}

			popup.classList.add( 'is-open' );
			overlay.classList.add( 'is-open' );
			popup.setAttribute( 'aria-hidden', 'false' );
			document.body.style.overflow = 'hidden';
		}

		function closePopup() {
			popup.classList.remove( 'is-open' );
			overlay.classList.remove( 'is-open' );
			popup.setAttribute( 'aria-hidden', 'true' );
			document.body.style.overflow = '';
			if ( activeAudio ) {
				activeAudio.pause();
				activeAudio = null;
			}
		}

		wrap.addEventListener( 'click', function ( e ) {
			var btn = e.target.closest( '.sp-podcast-card__more' );
			if ( ! btn ) return;
			var card = btn.closest( '.sp-podcast-card' );
			if ( ! card || ! card.dataset.podcast ) return;
			try {
				openPopup( JSON.parse( atob( card.dataset.podcast ) ) );
			} catch ( err ) {}
		} );

		popup.querySelector( '.sp-podcast-popup__close' ).addEventListener( 'click', closePopup );
		overlay.addEventListener( 'click', closePopup );

		document.addEventListener( 'keydown', function ( e ) {
			if ( e.key === 'Escape' && popup.classList.contains( 'is-open' ) ) closePopup();
		} );
	} );

} )();
