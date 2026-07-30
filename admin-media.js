( function( wp, $ ) {
	'use strict';

	if ( ! wp || ! wp.media || ! wp.media.view || ! $ ) {
		return;
	}

	function adminConfig() {
		return window.pecodexMediaLibraryAdmin || window.pgmPrivateGutenbergMediaAdmin || {};
	}

	function text( key, fallback ) {
		var config = adminConfig();
		return config.i18n && config.i18n[ key ] ? config.i18n[ key ] : fallback;
	}

	function attachmentStatus( model ) {
		if ( ! model || 'function' !== typeof model.get ) { return null; }
		return model.get( 'pgmPrivateMedia' ) || null;
	}

	function applyAttachmentDataToModel( model, attachment ) {
		if ( ! model || ! attachment || 'function' !== typeof model.set ) { return; }
		model.set( attachment );
		if ( wp.media && wp.media.attachment && attachment.id ) {
			wp.media.attachment( attachment.id ).set( attachment );
		}
	}

	function forceBrowserCacheReload( urls ) {
		if ( ! window.fetch || ! Array.isArray( urls ) || ! urls.length ) { return; }
		if ( window.caches && window.caches.keys ) {
			window.caches.keys().then( function( keys ) {
				return Promise.all( keys.map( function( key ) { return window.caches.delete( key ); } ) );
			} ).catch( function() {} );
		}
		urls.slice( 0, 80 ).forEach( function( url ) {
			if ( ! url ) { return; }
			[ 'reload', 'no-cache' ].forEach( function( cacheMode ) {
				try { window.fetch( url, { method: 'GET', cache: cacheMode, credentials: 'same-origin', mode: 'same-origin', redirect: 'follow' } ).catch( function() {} ); } catch ( e ) {}
			} );
			try { window.fetch( url, { method: 'HEAD', cache: 'reload', credentials: 'same-origin', mode: 'same-origin', redirect: 'follow' } ).catch( function() {} ); } catch ( e ) {}
		} );
	}

	function badgeTitle( status ) {
		var title = status.description || status.label || '';
		if ( status.hasFolderSources && status.folderSourceCount ) { title += ' Kansioita: ' + status.folderSourceCount + '.'; }
		return title;
	}

	function lockIconSvg() {
		return '<svg class="pgm-media-badge-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><rect width="18" height="11" x="3" y="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>';
	}

	function unlockIconSvg() {
		return '<svg class="pgm-media-badge-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><rect width="18" height="11" x="3" y="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 9.9-1"></path></svg>';
	}

	function shareIconSvg() {
		return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="14" height="14" aria-hidden="true">' +
			'<circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/>' +
			'<line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/>' +
			'</svg>';
	}

	function attachmentModelFromElement( $attachment, fallbackModel ) {
		var id;
		if ( fallbackModel ) { return fallbackModel; }
		if ( ! $attachment || ! $attachment.length || ! wp || ! wp.media || ! wp.media.attachment ) { return null; }
		id = parseInt( $attachment.attr( 'data-id' ), 10 ) || 0;
		return id ? wp.media.attachment( id ) : null;
	}

	function renderPrivateBadgeForElement( $attachment, model ) {
		var status, badgeClass, $preview, $badge;
		if ( ! $attachment || ! $attachment.length ) { return; }
		status = attachmentStatus( model );
		$attachment.find( '.pgm-media-badge' ).remove();
		$attachment.removeClass( 'pgm-media-is-private' );
		if ( ! status || ! status.isPrivate ) { return; }
		$preview = $attachment.find( '.attachment-preview' ).first();
		if ( ! $preview.length ) { return; }
		badgeClass = 'pgm-media-badge' + ( status.className ? ' ' + status.className : '' );
		$badge = $( '<span />', { 'class': badgeClass, 'title': badgeTitle( status ) } )
			.append( lockIconSvg() )
			.append( $( '<span />', { text: text( 'protectedState', 'Suojattu' ) } ) );
		$attachment.addClass( 'pgm-media-is-private' );
		$preview.append( $badge );
	}

	function enhanceAttachmentCardElement( element, model ) {
		var $attachment = $( element );
		var resolvedModel = attachmentModelFromElement( $attachment, model );
		if ( ! $attachment.length ) { return; }
		renderPrivateBadgeForElement( $attachment, resolvedModel );
	}

	window.pecodexEnhanceMediaAttachmentCard = enhanceAttachmentCardElement;

	function isHiddenFromUploads( status ) {
		return !! ( status && status.isPrivate && ! status.hasPublicCopy && status.hasPrivateCopy );
	}

	function privacyStateLabel( status ) {
		if ( ! status || ! status.isPrivate ) {
			return status && status.isPublicOverride ? 'Julkinen poikkeus' : text( 'publicState', 'Julkinen' );
		}
		if ( status.className === 'pgm-status-warning' || status.className === 'pgm-status-policy-pending' ) { return status.label || 'Siirtoa odottaa'; }
		if ( status.isPolicyProtected ) { return 'Asetuksella suojattu'; }
		return text( 'protectedState', 'Suojattu' );
	}

	function privacySummary( status ) {
		if ( ! status || ! status.isPrivate ) {
			if ( status && status.isPublicOverride ) { return 'Tiedosto on julkinen. Lisäosan tiedostotyyppisääntö ohitetaan vain tämän tiedoston osalta.'; }
			return 'Tiedosto on julkinen. Sen normaali mediaosoite toimii kaikille, joilla on linkki.';
		}
		if ( isHiddenFromUploads( status ) ) { return 'Suora uploads-osoite on estetty. Tiedosto avataan vain suojatun linkin kautta.'; }
		if ( status.className === 'pgm-status-warning' || status.className === 'pgm-status-policy-pending' || status.className === 'pgm-status-missing' ) { return status.description || 'Tiedoston suojaustila tarvitsee tarkistuksen.'; }
		if ( status.isPolicyProtected ) { return 'Tiedosto suojataan lisäosan asetussäännöllä. Voit tehdä tästä yksittäisestä tiedostosta julkisen poikkeuksen.'; }
		return status.description || 'Tiedosto on suojattu tämän lisäosan kautta.';
	}

	function sourceItems( status ) {
		var items = [];
		if ( ! status ) { return items; }
		if ( status.isManuallyPrivate ) { items.push( { label: 'Käsin', text: 'Tämä tiedosto on suojattu erillisellä käsin tehdyllä valinnalla.' } ); }
		( status.folderSources || [] ).forEach( function( source ) {
			items.push( { label: 'Kansio', text: source.name || ( 'Kansio #' + source.id ), url: source.editUrl || '' } );
		} );
		if ( status.isPolicyProtected ) {
			items.push( { label: 'Asetus', text: status.isPublicOverride && ! status.isPrivate ? 'Asetussääntö ohitetaan tämän tiedoston osalta.' : status.className === 'pgm-status-policy-pending' ? 'Tämä vanha julkinen tiedosto osuu nyt tiedostotyyppisääntöön.' : 'Tämä tiedostotyyppi suojataan lisäosan asetuksissa.' } );
		}
		if ( status.isPrivate && ! items.length ) { items.push( { label: 'Suojaus', text: 'Tiedosto on suojattu vanhan merkinnän tai automaattisen säännön kautta.' } ); }
		return items;
	}

	function appendSourceList( $target, status ) {
		var items = sourceItems( status );
		var $list;
		if ( ! items.length ) { return; }
		$list = $( '<div />', { 'class': 'pgm-media-source-list' } );
		items.forEach( function( item ) {
			var $item = $( '<div />', { 'class': 'pgm-media-source-item' } )
				.append( $( '<span />', { 'class': 'pgm-media-source-label', text: item.label } ) );
			if ( item.url ) { $item.append( $( '<a />', { href: item.url, text: item.text } ) ); }
			else { $item.append( $( '<span />', { text: item.text } ) ); }
			$list.append( $item );
		} );
		$target.append( $list );
	}

	function appendSourceLine( $notice, status ) {
		var $line;
		if ( ! status ) { return; }
		if ( status.folderSources && status.folderSources.length ) {
			$line = $( '<p />' ).text( 'Suojaus tulee kansiosta: ' );
			status.folderSources.forEach( function( source, index ) {
				if ( index > 0 ) { $line.append( ', ' ); }
				$line.append( document.createTextNode( source.name || ( 'Kansio #' + source.id ) ) );
			} );
			$line.append( '.' );
			$notice.append( $line );
		}
	}

	function renderDetailsNotice( view ) {
		var status, $details, $notice, $heading;
		if ( ! view || ! view.$el ) { return; }
		status = attachmentStatus( view.model );
		view.$el.find( '.pgm-media-details-notice' ).remove();
		view.$el.find( '.setting[data-setting="url"]' ).removeClass( 'pgm-private-protected-url' );
		view.$el.find( '.pgm-media-url-help' ).remove();
		if ( ! status || ! status.isPrivate ) { return; }
		$details = view.$el.find( '.attachment-info .details' ).first();
		if ( ! $details.length ) { return; }
		$notice = $( '<div />', { 'class': 'pgm-media-details-notice ' + ( status.className || '' ), 'role': 'status' } );
		$( '<strong />', { text: privacyStateLabel( status ) } ).appendTo( $notice );
		$( '<p />', { text: privacySummary( status ) } ).appendTo( $notice );
		$heading = $details.children( 'h2' ).first();
		if ( $heading.length ) { $heading.after( $notice ); } else { $details.prepend( $notice ); }
	}

	function updateUrlAndDownloadControls( view ) {
		var status, $urlSetting, $urlInput, $copyButton, $downloadLink;
		if ( ! view || ! view.$el ) { return; }
		status = attachmentStatus( view.model );
		if ( ! status || ! status.isPrivate || ! status.adminDownloadUrl ) { return; }
		$downloadLink = view.$el.find( '.actions a[download]' ).first();
		if ( $downloadLink.length ) {
			$downloadLink.attr( 'href', status.adminDownloadUrl ).attr( 'target', '_blank' ).attr( 'rel', 'noopener noreferrer' ).removeAttr( 'download' ).attr( 'title', 'Avaa tiedosto suojatusti kirjautuneena.' ).text( 'Avaa suojatusti' );
		}
		if ( ! status.preferProtectedUrl ) { return; }
		$urlSetting = view.$el.find( '.setting[data-setting="url"]' ).first();
		$urlInput = $urlSetting.find( 'input.attachment-details-copy-link' ).first();
		if ( ! $urlSetting.length || ! $urlInput.length ) { return; }
		$urlSetting.addClass( 'pgm-private-protected-url' );
		$urlSetting.find( 'label.name' ).text( 'Suojattu avausosoite:' );
		$urlInput.val( status.adminDownloadUrl ).attr( 'title', 'Kirjautuneen käyttäjän suojattu avauslinkki.' );
		$copyButton = $urlSetting.find( '.copy-attachment-url' ).first();
		if ( $copyButton.length ) { $copyButton.text( 'Kopioi suojattu avauslinkki' ); }
		if ( ! $urlSetting.find( '.pgm-media-url-help' ).length ) {
			$( '<p />', { 'class': 'pgm-media-url-help', text: 'Tätä linkkiä voi käyttää tiedoston tarkistamiseen kirjautuneena adminina. Julkisella sivulla suojattu media avataan tämän lisäosan suojatun endpointin kautta.' } ).appendTo( $urlSetting );
		}
	}

	function toggleAttachmentPrivacy( view, intent, $panel ) {
		var config = adminConfig();
		var attachmentId, $button, $message;
		if ( ! config.ajaxUrl || ! config.nonce || ! view || ! view.model ) { return; }
		attachmentId = view.model.get( 'id' );
		$button = $panel.find( '.pgm-media-action-button' ).first();
		$message = $panel.find( '.pgm-media-action-message' ).first();
		$button.prop( 'disabled', true ).text( text( 'saving', 'Tallennetaan...' ) );
		$message.text( '' );
		$.post( config.ajaxUrl, {
			action: config.ajaxActions && config.ajaxActions.toggleAttachmentPrivacy ? config.ajaxActions.toggleAttachmentPrivacy : 'pecodex_toggle_attachment_privacy',
			nonce: config.nonce, attachment_id: attachmentId, intent: intent
		} ).done( function( response ) {
			if ( ! response || ! response.success || ! response.data || ! response.data.attachment ) {
				$message.text( response && response.data && response.data.message ? response.data.message : text( 'error', 'Toimintoa ei voitu suorittaa.' ) );
				$button.prop( 'disabled', false );
				return;
			}
			applyAttachmentDataToModel( view.model, response.data.attachment );
			forceBrowserCacheReload( response.data.cacheClearUrls );
			view.render();
			$( document ).trigger( 'pgmMoFolderStateUpdate', [ response.data ] );
			if ( wp.a11y && wp.a11y.speak && response.data.message ) { wp.a11y.speak( response.data.message ); }
		} ).fail( function( xhr ) {
			var message = text( 'error', 'Toimintoa ei voitu suorittaa.' );
			if ( xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message ) { message = xhr.responseJSON.data.message; }
			$message.text( message );
			$button.prop( 'disabled', false );
		} );
	}

	// -------------------------------------------------------------------------
	// Share Modal
	// -------------------------------------------------------------------------

	function formatExpiry( ts ) {
		if ( ! ts ) { return 'Ei vanhene'; }
		var d = new Date( ts * 1000 );
		return d.getDate() + '.' + ( d.getMonth() + 1 ) + '.' + d.getFullYear() + ' ' +
			String( d.getHours() ).padStart( 2, '0' ) + ':' + String( d.getMinutes() ).padStart( 2, '0' );
	}

	function buildShareUrl( attachmentId, token ) {
		var config = adminConfig();
		var base = config.ajaxUrl ? config.ajaxUrl.replace( 'admin-ajax.php', 'admin-post.php' ) : '';
		return base + '?action=pecodex_private_media&id=' + attachmentId + '&share_token=' + encodeURIComponent( token );
	}

	function closeShareModal() {
		$( '#modalOverlay, #toast' ).remove();
		$( document ).off( 'keydown.pgmShare click.pgmShare' );
	}

			function openShareModal( view ) {
		var config      = adminConfig();
		var status      = attachmentStatus( view.model );
		var attachmentId = view.model.get( 'id' );
		var filename    = ( status && status.originalFilename ) ? status.originalFilename : ( view.model.get( 'filename' ) || 'tiedosto' );
		var shareLinks  = ( status && Array.isArray( status.shareLinks ) ) ? status.shareLinks : [];

		closeShareModal();

		if ( ! document.getElementById( 'pgm-share-tailwind' ) ) {
			var oldWarn = console.warn;
			console.warn = function(msg) {
				if (typeof msg === 'string' && msg.includes('cdn.tailwindcss.com should not be used in production')) return;
				oldWarn.apply(console, arguments);
			};
			$( 'head' ).append( '<script id="pgm-share-tailwind" src="https://cdn.tailwindcss.com"></script>' );
			setTimeout(function(){ console.warn = oldWarn; }, 2000);
			$( 'head' ).append( '<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">' );
			$( 'head' ).append( '<style>' +
				'.custom-scrollbar::-webkit-scrollbar { width: 6px; }' +
				'.custom-scrollbar::-webkit-scrollbar-track { background: #f1f1f1; border-radius: 4px; }' +
				'.custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }' +
				'.custom-scrollbar::-webkit-scrollbar-thumb:hover { background: #94a3b8; }' +
				'@keyframes fadeIn { from { opacity: 0; transform: translateY(-10px) scale(0.98); } to { opacity: 1; transform: translateY(0) scale(1); } }' +
				'.animate-modal { animation: fadeIn 0.2s ease-out forwards; }' +
				'@keyframes slideUpFade { 0% { opacity: 0; transform: translateY(20px) translateX(-50%); } 10% { opacity: 1; transform: translateY(0) translateX(-50%); } 90% { opacity: 1; transform: translateY(0) translateX(-50%); } 100% { opacity: 0; transform: translateY(-20px) translateX(-50%); } }' +
				'.toast-animate { animation: slideUpFade 3s ease-in-out forwards; }' +
				'#modalOverlay { font-family: "Inter", sans-serif; }' +
			'</style>' );
		}

		var activeLinksHtml = '';
		if ( shareLinks.length > 0 ) {
			shareLinks.forEach( function( link ) {
				var tok = link.token || '';
				var email = link.email || 'Yleinen jakolinkki';
				var iconHtml = link.email 
					? '<div class="w-10 h-10 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center font-semibold text-sm">' + link.email.charAt(0).toUpperCase() + '</div>'
					: '<div class="w-10 h-10 rounded-full bg-gray-100 text-gray-600 flex items-center justify-center"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"></path><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"></path></svg></div>';
				
				activeLinksHtml += `
					<div class="flex items-center justify-between p-2 hover:bg-gray-50 rounded-lg transition-colors" data-token="${tok}">
						<div class="flex items-center gap-3">
							${iconHtml}
							<div>
								<p class="text-sm font-medium text-gray-900">${email}</p>
								<p class="text-xs text-gray-500">Aktiivinen</p>
							</div>
						</div>
						<div class="relative inline-block text-left group">
							<select class="role-select appearance-none bg-transparent text-sm text-gray-700 font-medium py-1 pl-2 pr-6 rounded focus:outline-none focus:ring-2 focus:ring-blue-500 cursor-pointer hover:bg-gray-200">
								<option value="viewer" selected>Katselija</option>
								<option value="remove">Poista oikeus</option>
							</select>
							<div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-1 text-gray-500">
								<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"></polyline></svg>
							</div>
						</div>
					</div>
				`;
			} );
		} else {
			activeLinksHtml = '<p class="text-sm text-gray-500 p-2">Ei aktiivisia jakolinkkejä.</p>';
		}

		var modalHtml = `
    <!-- Modal Overlay -->
    <div class="fixed inset-0 bg-gray-900/60 backdrop-blur-sm z-[999999] flex items-center justify-center p-4" id="modalOverlay">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg overflow-hidden flex flex-col max-h-[90vh] animate-modal" id="modalContent">
            <div class="px-6 py-5 border-b border-gray-100 flex justify-between items-center bg-white sticky top-0 z-10">
                <h2 class="text-xl font-semibold text-gray-800">Jaa "${filename}"</h2>
                <button class="p-2 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-full transition-colors focus:outline-none" aria-label="Sulje" onclick="closeModal()">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                </button>
            </div>
            <div class="p-6 space-y-6 overflow-y-auto custom-scrollbar">
                
                <!-- Search & Add Input -->
                <div class="relative">
                    <div class="relative flex items-center">
                        <input type="text" id="searchInput" placeholder="Lisää ihmisiä ja ryhmiä (esim. nimi tai sähköposti)" 
                            class="w-full border border-gray-300 rounded-xl py-3 pl-4 pr-12 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-shadow">
                        <div class="absolute right-3 text-gray-400">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                        </div>
                    </div>
                    <div id="searchDropdown" class="hidden absolute left-0 right-0 mt-1 bg-white border border-gray-200 rounded-xl shadow-lg z-20 py-2 max-h-60 overflow-y-auto custom-scrollbar">
                    </div>
                </div>

                <!-- People with Access -->
                <div>
                    <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-3 px-1">Ihmiset, joilla on käyttöoikeus</h3>
                    <div class="space-y-1" id="activeLinksContainer">
                        ${activeLinksHtml}
                    </div>
                </div>
            </div>
            <div class="px-6 py-4 border-t border-gray-100 bg-gray-50 flex items-center justify-between rounded-b-2xl">
                <button onclick="copyLink()" class="flex items-center gap-2 text-blue-600 font-medium text-sm hover:bg-blue-100 px-4 py-2 rounded-xl transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"></path><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"></path></svg>
                    Kopioi jaettu linkki
                </button>
                <button onclick="closeModal()" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-full font-medium text-sm shadow-sm transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    Valmis
                </button>
            </div>
        </div>
    </div>
    <div id="toast" class="fixed top-10 left-1/2 transform -translate-x-1/2 bg-gray-800 text-white px-6 py-3 rounded-full shadow-lg flex items-center gap-2 opacity-0 pointer-events-none z-[999999]">
        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#4ade80" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
        <span>Huomio!</span>
    </div>
`;

		$( 'body' ).append( modalHtml );

		window.closeModal = function() {
			var content = document.getElementById( 'modalContent' );
			var overlay = document.getElementById( 'modalOverlay' );
			if ( content ) { content.style.transform = 'scale(0.95)'; content.style.opacity = '0'; }
			if ( overlay ) { overlay.style.opacity = '0'; }
			setTimeout( function() { closeShareModal(); }, 300 );
		};

		window.showToast = function( message ) {
			var toast = document.getElementById( 'toast' );
			if ( ! toast ) { return; }
			$(toast).find('span').text(message);
			toast.classList.remove( 'toast-animate' );
			void toast.offsetWidth;
			toast.classList.add( 'toast-animate' );
		};

		window.selectUser = function( email ) {
			var dropdown = document.getElementById( 'searchDropdown' );
			if ( dropdown ) { dropdown.classList.add( 'hidden' ); }
			document.getElementById('searchInput').value = '';
			
			$.post( config.ajaxUrl, {
				action: 'pgm_create_share_link', nonce: config.shareNonce || config.nonce,
				attachment_id: attachmentId, email: email, expires_hours: 0, single_use: 0
			} ).done( function( resp ) {
				if ( resp && resp.success && resp.data ) {
					var s = attachmentStatus( view.model );
					if ( s ) { s.shareLinks = resp.data.links || []; view.model.set( 'pgmPrivateMedia', s ); }
					closeShareModal();
					openShareModal( view );
				} else {
					window.showToast( 'Virhe luotaessa linkkiä.' );
				}
			} );
		};

		window.copyLink = function() {
			if ( shareLinks.length > 0 ) {
				var url = config.ajaxUrl ? config.ajaxUrl.replace( 'admin-ajax.php', 'admin-post.php' ) : '';
				url += '?action=pecodex_private_media&id=' + attachmentId + '&share_token=' + encodeURIComponent( shareLinks[0].token );
				if ( navigator.clipboard ) {
					navigator.clipboard.writeText( url ).then( function() {
						window.showToast( 'Linkki kopioitu leikepöydälle!' );
					} );
				}
			} else {
				window.showToast( 'Ei aktiivisia linkkejä kopioitavaksi.' );
			}
		};

		var sInput = document.getElementById( 'searchInput' );
		var sDrop = document.getElementById( 'searchDropdown' );
		var searchTimeout;
		if ( sInput && sDrop ) {
			sInput.addEventListener( 'input', function( e ) {
				var val = e.target.value.trim();
				clearTimeout(searchTimeout);
				if ( val.length < 2 ) {
					sDrop.classList.add( 'hidden' );
					sDrop.innerHTML = '';
					return;
				}
				searchTimeout = setTimeout(function() {
					$.post( config.ajaxUrl, { action: 'pgm_search_users', nonce: config.shareNonce || config.nonce, q: val } )
						.done( function( r ) {
							var html = '';
							if ( r && r.success && r.data && r.data.length ) {
								r.data.forEach( function( u ) {
									var initial = u.display_name.charAt(0).toUpperCase();
									html += `
									<button class="w-full text-left px-4 py-2 hover:bg-gray-50 flex items-center gap-3" onclick="selectUser('${u.email}')">
										<div class="w-8 h-8 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center font-semibold text-xs">${initial}</div>
										<div>
											<p class="text-sm font-medium text-gray-800">${u.display_name}</p>
											<p class="text-xs text-gray-500">${u.email}</p>
										</div>
									</button>
									`;
								} );
							}
							
							html += `
							<button class="w-full text-left px-4 py-2 hover:bg-gray-50 flex items-center gap-3 border-t border-gray-100" onclick="selectUser('${val}')">
								<div class="w-8 h-8 rounded-full bg-green-100 text-green-600 flex items-center justify-center font-semibold text-xs"><svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg></div>
								<div>
									<p class="text-sm font-medium text-gray-800">Lähetä kutsu sähköpostitse</p>
									<p class="text-xs text-gray-500">${val.includes('@') ? val : val + '@...'}</p>
								</div>
							</button>
							`;
							
							sDrop.innerHTML = html;
							sDrop.classList.remove( 'hidden' );
						} );
				}, 300);
			} );
			document.addEventListener( 'click', function( e ) {
				if ( ! sInput.contains( e.target ) && ! sDrop.contains( e.target ) ) {
					sDrop.classList.add( 'hidden' );
				}
			} );
		}

		$('#activeLinksContainer').on('change', '.role-select', function() {
			if ($(this).val() === 'remove') {
				var token = $(this).closest('[data-token]').attr('data-token');
				var $row = $(this).closest('[data-token]');
				$row.css('opacity', '0.5');
				$.post( config.ajaxUrl, { action: 'pgm_delete_share_link', nonce: config.shareNonce || config.nonce, attachment_id: attachmentId, token: token } )
					.done( function( r ) {
						if ( r && r.success ) {
							var s = attachmentStatus( view.model );
							if ( s ) { s.shareLinks = ( s.shareLinks || [] ).filter( function( l ) { return l.token !== token; } ); view.model.set( 'pgmPrivateMedia', s ); }
							$row.slideUp(200, function() { $(this).remove(); });
							window.showToast('Käyttöoikeus poistettu');
						} else {
							$row.css('opacity', '1');
							window.showToast('Virhe poistettaessa oikeutta');
						}
					} );
			}
		});

		$( document ).on( 'keydown.pgmShare', function( e ) {
			if ( e.key === 'Escape' ) { window.closeModal(); }
		} );
	}

	// -------------------------------------------------------------------------
	// Privacy card + "Jaa tiedosto" -nappi
	// -------------------------------------------------------------------------

	function renderPrivacyActionControls( view ) {
		var status, config, $settings, $notice, $fallbackTarget, $panel, $header, $actions, $button;
		var intent = '', buttonText = '', description = '', firstFolder;

		if ( ! view || ! view.$el ) { return; }

		view.$el.find( '.pgm-media-action-panel, .pgm-media-privacy-card' ).remove();

		status = attachmentStatus( view.model );
		if ( ! status || ! status.canManage ) { return; }

		config          = adminConfig();
		$settings       = view.$el.find( '.attachment-info .settings, .attachment-details .settings, .media-sidebar .settings, .settings' ).first();
		$notice         = view.$el.find( '.pgm-media-details-notice' ).first();
		$fallbackTarget = view.$el.find( '.attachment-info .details, .attachment-details, .media-sidebar' ).first();

		if ( status.canMakePublic ) {
			intent = 'make_public'; buttonText = text( 'makePublic', 'Poista suojaus' );
			description = text( 'makePublicDescription', 'Tiedosto palautetaan julkiseen uploads-kansioon ja asetussääntö ohitetaan vain tälle tiedostolle.' );
		} else if ( status.canDetachMakePublic ) {
			intent = 'detach_make_public'; buttonText = text( 'detachMakePublic', 'Irrota kansiosta ja tee julkiseksi' );
			description = text( 'detachMakePublicDescription', 'Kansio lukitsee tämän median. Poista suojaus siirtämällä media pois suojatusta kansiosta.' );
		} else if ( status.canHide ) {
			intent = 'hide'; buttonText = text( 'protect', 'Suojaa tiedosto' );
			description = text( 'protectDescription', 'Tiedosto merkitään suojatuksi ja avataan jatkossa vain suojatun linkin kautta.' );
		} else if ( status.canUnhide ) {
			intent = 'unhide';
			buttonText = status.hasSourceLocks ? text( 'unhideManual', 'Poista käsin tehty suojaus' ) : text( 'unhide', 'Poista suojaus' );
			description = status.hasSourceLocks
				? text( 'unhideSourceLockedDescription', 'Poistaa vain tämän median käsin tehdyn suojauksen.' )
				: text( 'unhideDescription', 'Palauttaa tiedoston julkiseksi ja ohittaa asetussäännön tälle tiedostolle.' );
		} else if ( status.isPrivate ) {
			description = status.hasFolderSources || ( status.folderAccess && status.folderAccess !== 'public' )
				? text( 'folderManaged', 'Kansio lukitsee tämän median. Yksittäistä tiedostoa ei voi avata julkiseksi.' )
				: text( 'blockManaged', 'Tiedosto on suojattu kansion tai asetussäännön kautta.' );
		}

		$panel = $( '<div />', { 'class': 'pgm-media-action-panel pgm-media-privacy-card ' + ( status.isPrivate ? 'is-private' : 'is-public' ) } );

		$header = $( '<div />', { 'class': 'pgm-media-privacy-header' } )
			.append( $( '<h3 />', { text: text( 'visibilityTitle', 'Julkisuus' ) } ) )
			.append( $( '<span />', { 'class': 'pgm-media-privacy-pill ' + ( status.isPrivate ? 'is-private' : 'is-public' ), text: privacyStateLabel( status ) } ) );

		$panel.append( $header, $( '<p />', { 'class': 'pgm-media-privacy-summary', text: privacySummary( status ) } ) );
		appendSourceList( $panel, status );

		$actions = $( '<div />', { 'class': 'pgm-media-action-row' } );

		if ( intent ) {
			$button = $( '<button />', { type: 'button', 'class': 'button ' + ( intent === 'hide' ? 'button-primary' : 'button-secondary' ) + ' pgm-media-action-button' } )
				.append( $( '<span />', { html: intent === 'hide' ? lockIconSvg() : unlockIconSvg() } ) )
				.append( $( '<span />', { text: buttonText } ) );
			$button.on( 'click', function() { toggleAttachmentPrivacy( view, intent, $panel ); } );
			$actions.append( $button );
		}

		if ( status.adminDownloadUrl ) {
			$actions.append( $( '<a />', { 'class': 'button button-secondary', href: status.adminDownloadUrl, target: '_blank', rel: 'noopener noreferrer', text: text( 'openSecurely', 'Avaa suojatusti' ) } ) );
		}

		firstFolder = status.folderSources && status.folderSources.length ? status.folderSources[0] : null;
		if ( firstFolder && firstFolder.editUrl ) {
			$actions.append( $( '<a />', { 'class': 'button button-secondary', href: firstFolder.editUrl, text: text( 'openFolder', 'Avaa kansio' ) } ) );
		}

		if ( status.isPolicyProtected && config.settingsUrl ) {
			$actions.append( $( '<a />', { 'class': 'button button-secondary', href: config.settingsUrl, text: text( 'openSettings', 'Avaa suojausasetukset' ) } ) );
		}

		// "Jaa tiedosto" -nappi avaa share-modalin
		var $shareBtn = $( '<button />', { type: 'button', 'class': 'button button-secondary pgm-open-share-modal' } )
			.append( shareIconSvg() )
			.append( $( '<span />', { text: ' Jaa tiedosto' } ) );
		$shareBtn.on( 'click', function() { openShareModal( view ); } );
		$actions.append( $shareBtn );

		if ( $actions.children().length ) { $panel.append( $actions ); }

		if ( description ) { $( '<p />', { 'class': 'description pgm-media-privacy-help', text: description } ).appendTo( $panel ); }

		$( '<span />', { 'class': 'pgm-media-action-message', 'role': 'status' } ).appendTo( $panel );

		if ( $notice.length ) { $notice.after( $panel ); }
		else if ( $settings.length ) { $settings.prepend( $panel ); }
		else if ( $fallbackTarget.length ) { $fallbackTarget.prepend( $panel ); }
	}

	function patchAttachmentView( ViewConstructor ) {
		var originalRender;
		if ( ! ViewConstructor || ! ViewConstructor.prototype || Object.prototype.hasOwnProperty.call( ViewConstructor.prototype, 'pgmPrivateMediaPatched' ) ) { return; }
		originalRender = ViewConstructor.prototype.render;
		if ( 'function' !== typeof originalRender ) { return; }
		ViewConstructor.prototype.render = function() {
			var result = originalRender.apply( this, arguments );
			try { enhanceAttachmentCardElement( this.$el, this.model ); } catch ( e ) {}
			try { renderDetailsNotice( this ); } catch ( e ) {}
			try { updateUrlAndDownloadControls( this ); } catch ( e ) {}
			try { renderPrivacyActionControls( this ); } catch ( e ) {}
			return result;
		};
		ViewConstructor.prototype.pgmPrivateMediaPatched = true;
	}

	if ( wp.media.view.Attachment ) {
		patchAttachmentView( wp.media.view.Attachment );
		patchAttachmentView( wp.media.view.Attachment.Library );
		patchAttachmentView( wp.media.view.Attachment.Selection );
		patchAttachmentView( wp.media.view.Attachment.EditLibrary );
		if ( wp.media.view.Attachment.Details ) {
			patchAttachmentView( wp.media.view.Attachment.Details );
			patchAttachmentView( wp.media.view.Attachment.Details.TwoColumn );
		}
	}

}( window.wp, window.jQuery ) );
