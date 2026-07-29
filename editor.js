( function ( wp ) {
	// Jos editorin WordPress-paketit eivät ole vielä käytettävissä, lopetetaan siististi.
	// Tämä estää JavaScript-virheet vanhemmissa tai poikkeavasti ladatuissa editorinäkymissä.
	if ( ! wp || ! wp.hooks || ! wp.blocks || ! wp.compose || ! wp.data || ! wp.element ) {
		return;
	}

	const { addFilter } = wp.hooks;
	const { createHigherOrderComponent } = wp.compose;
	const { useSelect } = wp.data;
	const { createElement: el, useEffect } = wp.element;
	const { __ } = wp.i18n || { __: ( value ) => value };
	const pluginSettings = window.pecodexMediaLibraryEditor || window.pgmPrivateGutenbergMedia || {};
	const brandName = pluginSettings.brandName || __( 'Pecodex Media Control', 'private-gutenberg-media' );
	const i18n = pluginSettings.i18n || {};
	const protectedBadgeLabel = i18n.protectedBadge || __( 'Suojattu', 'private-gutenberg-media' );
	const homeUrl = pluginSettings.homeUrl || window.location.origin + '/';

	// Lohkot, joille pidetään legacy-attribuutti yhteensopivuuden vuoksi.
	const SUPPORTED_BLOCKS = [
		'core/list',
		'core/list-item',
		'core/paragraph',
		'core/file',
		'core/buttons',
		'core/button',
		'core/image',
		'core/media-text',
		'core/cover',
		'core/audio',
		'core/video',
	];
	const BADGE_BLOCKS = [
		'core/file',
		'core/image',
		'core/media-text',
		'core/cover',
		'core/audio',
		'core/video',
	];
	const privateAttachmentIds = {};
	const privateAttachmentUrls = {};
	const privateAttachmentPaths = {};

	( pluginSettings.privateAttachmentIds || [] ).forEach( ( id ) => {
		const numericId = parseInt( id, 10 );
		if ( numericId ) {
			privateAttachmentIds[ numericId ] = true;
		}
	} );

	function decodeSafely( value ) {
		try {
			return decodeURIComponent( value );
		} catch ( error ) {
			return value;
		}
	}

	function comparableUrl( value ) {
		const rawUrl = String( value || '' ).replace( /&amp;/g, '&' ).trim();

		if ( ! rawUrl ) {
			return '';
		}

		try {
			const parsed = new URL( rawUrl, homeUrl );
			parsed.hash = '';
			parsed.search = '';
			return decodeSafely( parsed.href ).toLowerCase();
		} catch ( error ) {
			return decodeSafely( rawUrl.split( '#' )[0].split( '?' )[0] ).toLowerCase();
		}
	}

	function uploadRelativePathFromUrl( value ) {
		const rawUrl = String( value || '' ).replace( /&amp;/g, '&' ).trim();

		if ( ! rawUrl ) {
			return '';
		}

		try {
			const parsed = new URL( rawUrl, homeUrl );
			const fileParam = parsed.searchParams.get( 'file' );
			if ( fileParam ) {
				return decodeSafely( fileParam ).replace( /^\/+/, '' ).replace( /\\/g, '/' ).toLowerCase();
			}

			const marker = '/wp-content/uploads/';
			const pathname = decodeSafely( parsed.pathname || '' );
			const index = pathname.indexOf( marker );
			if ( index !== -1 ) {
				return pathname.slice( index + marker.length ).replace( /^\/+/, '' ).replace( /\\/g, '/' ).toLowerCase();
			}
		} catch ( error ) {
			const marker = 'wp-content/uploads/';
			const normalized = decodeSafely( rawUrl ).replace( /\\/g, '/' );
			const index = normalized.indexOf( marker );
			if ( index !== -1 ) {
				return normalized.slice( index + marker.length ).split( '#' )[0].split( '?' )[0].replace( /^\/+/, '' ).toLowerCase();
			}
		}

		return '';
	}

	( pluginSettings.privateAttachmentUrls || [] ).forEach( ( url ) => {
		const normalized = comparableUrl( url );
		if ( normalized ) {
			privateAttachmentUrls[ normalized ] = true;
		}
	} );

	( pluginSettings.privateAttachmentPaths || [] ).forEach( ( path ) => {
		const normalized = String( path || '' ).replace( /\\/g, '/' ).replace( /^\/+/, '' ).toLowerCase();
		if ( normalized ) {
			privateAttachmentPaths[ normalized ] = true;
		}
	} );

	function isSupportedBlock( name ) {
		return SUPPORTED_BLOCKS.indexOf( name ) !== -1;
	}

	function shouldDisableFilePreview( name ) {
		return name === 'core/file';
	}

	function isBadgeBlock( name ) {
		return BADGE_BLOCKS.indexOf( name ) !== -1;
	}

	function blockAttachmentId( name, attributes ) {
		attributes = attributes || {};

		if ( name === 'core/media-text' ) {
			return parseInt( attributes.mediaId, 10 ) || 0;
		}

		if ( name === 'core/cover' ) {
			return parseInt( attributes.id, 10 ) || 0;
		}

		return parseInt( attributes.id, 10 ) || 0;
	}

	function blockMediaUrls( attributes ) {
		const urls = [];

		attributes = attributes || {};
		[ 'url', 'href', 'mediaUrl', 'src', 'poster', 'linkUrl', 'textLinkHref' ].forEach( ( key ) => {
			if ( attributes[ key ] ) {
				urls.push( String( attributes[ key ] ) );
			}
		} );

		return urls;
	}

	function isPrivateAttachmentUrl( url ) {
		if ( ! url ) {
			return false;
		}

		const normalizedUrl = comparableUrl( url );
		if ( normalizedUrl && privateAttachmentUrls[ normalizedUrl ] ) {
			return true;
		}

		const relativePath = uploadRelativePathFromUrl( url );
		if ( relativePath && privateAttachmentPaths[ relativePath ] ) {
			return true;
		}

		try {
			const parsed = new URL( String( url ).replace( /&amp;/g, '&' ), homeUrl );
			const attachmentId = parseInt( parsed.searchParams.get( 'id' ), 10 );
			if ( attachmentId && privateAttachmentIds[ attachmentId ] ) {
				return true;
			}
		} catch ( error ) {
			return false;
		}

		return false;
	}

	function isProtectedMediaBlock( name, attributes ) {
		const attachmentId = blockAttachmentId( name, attributes );

		if ( ! isBadgeBlock( name ) ) {
			return false;
		}

		if ( attachmentId && privateAttachmentIds[ attachmentId ] ) {
			return true;
		}

		return blockMediaUrls( attributes ).some( ( url ) => {
			return url.indexOf( 'pgm_private_media' ) !== -1
				|| url.indexOf( 'pecodex_private_media' ) !== -1
				|| isPrivateAttachmentUrl( url );
		} );
	}

	function injectEditorUtilityStyles( targetDocument ) {
		const styleId = 'pecodex-media-library-editor-badge-style';
		const doc = targetDocument || document;

		if ( ! doc || ! doc.head || doc.getElementById( styleId ) ) {
			return;
		}

		const style = doc.createElement( 'style' );
		style.id = styleId;
		style.textContent = `
			.block-editor-block-list__block.is-pecodex-private-media {
				position: relative !important;
			}
			.block-editor-block-list__block.is-pecodex-private-media > .pecodex-private-media-badge {
				display: inline-flex;
				align-items: center;
				gap: 5px;
				position: absolute;
				top: 8px;
				right: 8px;
				z-index: 9999;
				box-sizing: border-box;
				width: auto !important;
				height: auto !important;
				min-height: 24px;
				min-width: 0;
				max-width: calc(100% - 16px);
				margin: 0 !important;
				padding: 4px 9px;
				border: 1px solid #991b1b;
				border-radius: 999px;
				background-color: #b91c1c;
				color: #fff;
				font-size: 12px;
				font-weight: 700;
				line-height: 1.2;
				letter-spacing: 0;
				text-align: left;
				white-space: nowrap;
				overflow: hidden;
				text-overflow: ellipsis;
				pointer-events: none;
				transform: none !important;
			}
			.block-editor-block-list__block.is-pecodex-private-media > .pecodex-private-media-badge::before {
				content: "";
				display: block;
				width: 14px;
				height: 14px;
				flex: 0 0 14px;
				background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='white' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Crect x='3' y='11' width='18' height='10' rx='2'/%3E%3Cpath d='M7 11V7a5 5 0 0 1 10 0v4'/%3E%3C/svg%3E");
				background-repeat: no-repeat;
				background-position: center;
				background-size: 14px 14px;
			}
			.block-editor-block-list__block.is-pecodex-private-media[data-align="full"] > .pecodex-private-media-badge,
			.block-editor-block-list__block.is-pecodex-private-media[data-align="wide"] > .pecodex-private-media-badge {
				right: 12px;
			}
			.block-editor-block-list__block.is-pecodex-private-media.is-pecodex-file-no-preview > .pecodex-private-media-badge {
				top: -12px;
				right: 6px;
			}
			.is-pecodex-file-no-preview .wp-block-file__preview,
			.is-pecodex-file-no-preview .wp-block-file__preview-overlay,
			.is-pecodex-file-no-preview .wp-block-file__embed,
			.is-pecodex-file-no-preview object[type="application/pdf"],
			.is-pecodex-file-no-preview .wp-block-file > .components-resizable-box__container,
			.block-editor-block-list__block.is-pecodex-file-no-preview .wp-block-file__preview,
			.block-editor-block-list__block.is-pecodex-file-no-preview .wp-block-file__preview-overlay,
			.block-editor-block-list__block.is-pecodex-file-no-preview .wp-block-file__embed,
			.block-editor-block-list__block.is-pecodex-file-no-preview object[type="application/pdf"],
			.block-editor-block-list__block.is-pecodex-file-no-preview .wp-block-file > .components-resizable-box__container {
				display: none !important;
			}
			.block-editor-block-list__block.is-pecodex-file-no-preview .wp-block-file {
				align-items: center;
				gap: 8px;
			}
			.block-editor-block-list__block.is-pecodex-file-no-preview .wp-block-file__content-wrapper {
				display: flex;
				align-items: center;
				gap: 8px;
				flex-wrap: wrap;
			}
		`;
		doc.head.appendChild( style );
	}

	function ensureEditorUtilityStyles() {
		injectEditorUtilityStyles( document );

		const injectIntoEditorFrames = () => {
			document.querySelectorAll( 'iframe' ).forEach( ( iframe ) => {
				try {
					injectEditorUtilityStyles( iframe.contentDocument );
				} catch ( error ) {
					// Cross-origin iframes are ignored; Gutenberg's editor canvas is same-origin.
				}
			} );
		};

		injectIntoEditorFrames();
		window.setTimeout( injectIntoEditorFrames, 50 );
		window.setTimeout( injectIntoEditorFrames, 250 );
	}

	function addPrivateMediaAttribute( settings, name ) {
		if ( ! isSupportedBlock( name ) ) {
			return settings;
		}

		// Rekisteröidään vanha attribuutti yhteensopivuuden vuoksi, jotta aiemmin
		// tallennetut lohkot eivät muutu Gutenbergissä invalidiksi.
		const attributes = {
			...settings.attributes,
			pgmPrivateMediaLinks: {
				type: 'boolean',
				default: false,
			},
		};

		if ( shouldDisableFilePreview( name ) ) {
			attributes.displayPreview = {
				...( attributes.displayPreview || {} ),
				type: 'boolean',
				default: false,
			};
		}

		return {
			...settings,
			attributes,
		};
	}

	function cssEscape( value ) {
		if ( window.CSS && window.CSS.escape ) {
			return window.CSS.escape( value );
		}

		return String( value || '' ).replace( /["\\]/g, '\\$&' );
	}

	function editorFrameDocuments() {
		const docs = [ document ];

		document.querySelectorAll( 'iframe' ).forEach( ( iframe ) => {
			try {
				if ( iframe.contentDocument ) {
					docs.push( iframe.contentDocument );
				}
			} catch ( error ) {
				// Ignore cross-origin frames.
			}
		} );

		return docs;
	}

	function findEditorBlockElement( clientId ) {
		if ( ! clientId ) {
			return null;
		}

		const selector = '[data-block="' + cssEscape( clientId ) + '"]';
		for ( const doc of editorFrameDocuments() ) {
			const blockElement = doc.querySelector( selector );
			if ( blockElement ) {
				return blockElement;
			}
		}

		return null;
	}

	function directBadgeChild( blockElement ) {
		if ( ! blockElement ) {
			return null;
		}

		return Array.prototype.find.call( blockElement.children || [], ( child ) => {
			return child.classList && child.classList.contains( 'pecodex-private-media-badge' );
		} ) || null;
	}

	function syncProtectedMediaBadgeElement( clientId, isProtected ) {
		const blockElement = findEditorBlockElement( clientId );
		if ( ! blockElement ) {
			return;
		}

		let badge = directBadgeChild( blockElement );
		if ( ! isProtected ) {
			if ( badge ) {
				badge.remove();
			}
			return;
		}

		ensureEditorUtilityStyles();

		if ( ! badge ) {
			badge = blockElement.ownerDocument.createElement( 'span' );
			badge.className = 'pecodex-private-media-badge';
			badge.setAttribute( 'aria-hidden', 'true' );
			badge.setAttribute( 'contenteditable', 'false' );
			badge.setAttribute( 'data-pecodex-editor-only', 'true' );
			blockElement.appendChild( badge );
		}

		badge.textContent = protectedBadgeLabel;
	}

	function useProtectedMediaBadgeElement( clientId, isProtected, isSelected ) {
		useEffect(
			() => {
				let cancelled = false;
				const timers = [];
				const sync = () => {
					if ( ! cancelled ) {
						syncProtectedMediaBadgeElement( clientId, isProtected );
					}
				};

				sync();
				[ 0, 50, 250 ].forEach( ( delay ) => {
					timers.push( window.setTimeout( sync, delay ) );
				} );

				return () => {
					cancelled = true;
					timers.forEach( ( timer ) => window.clearTimeout( timer ) );
				};
			},
			[ clientId, isProtected, isSelected ]
		);
	}

	const withPrivateMediaControls = createHigherOrderComponent( ( BlockEdit ) => {
		return function PrivateMediaControls( props ) {
			if ( ! isSupportedBlock( props.name ) ) {
				return el( BlockEdit, props );
			}

			useEffect(
				() => {
					if ( shouldDisableFilePreview( props.name ) && props.attributes.displayPreview !== false ) {
						props.setAttributes( { displayPreview: false } );
					}
				},
				[ props.name, props.attributes.displayPreview ]
			);
			useProtectedMediaBadgeElement(
				props.clientId,
				isProtectedMediaBlock( props.name, props.attributes ),
				props.isSelected
			);

			return el( BlockEdit, props );
		};
	}, 'withPrivateMediaControls' );

	const withPrivateMediaBadge = createHigherOrderComponent( ( BlockListBlock ) => {
		return function PrivateMediaBadge( props ) {
			const selectedBlock = useSelect(
				( select ) => {
					const editor = select( 'core/block-editor' );
					return editor && editor.getBlock && props.clientId ? editor.getBlock( props.clientId ) : null;
				},
				[ props.clientId ]
			);
			const block = props.block || selectedBlock || {};
			const blockName = props.name || block.name || '';
			const attributes = props.attributes || block.attributes || {};
			const isFileBlock = shouldDisableFilePreview( blockName );
			const isProtected = isProtectedMediaBlock( blockName, attributes );

			if ( ! isFileBlock && ! isProtected ) {
				return el( BlockListBlock, props );
			}

			ensureEditorUtilityStyles();

			const classNames = [ props.className ];
			const pecodexClassNames = [];
			if ( isFileBlock ) {
				pecodexClassNames.push( 'is-pecodex-file-no-preview' );
			}
			if ( isProtected ) {
				pecodexClassNames.push( 'is-pecodex-private-media' );
			}
			classNames.push( ...pecodexClassNames );

			const wrapperProps = { ...( props.wrapperProps || {} ) };
			if ( isProtected ) {
				wrapperProps['data-pecodex-private-media-label'] = protectedBadgeLabel;
			}

			return el( BlockListBlock, {
				...props,
				className: classNames.filter( Boolean ).join( ' ' ),
				wrapperProps,
			} );
		};
	}, 'withPrivateMediaBadge' );

	// Kuuntele mediakirjaston muutoksia (esim. suojauksen päivitys sivupalkista)
	if ( window.jQuery ) {
		window.jQuery( document ).on( 'pgm:attachment_updated', function( event, attachment ) {
			if ( ! attachment || ! attachment.id || ! wp.data || ! wp.data.select || ! wp.data.dispatch ) {
				return;
			}

			// Päivitä editor.js:n paikallinen välimuisti
			if ( attachment.pgm_is_protected ) {
				privateAttachmentIds[ attachment.id ] = true;
				if ( attachment.url ) {
					privateAttachmentUrls[ comparableUrl( attachment.url ) ] = true;
				}
			} else {
				delete privateAttachmentIds[ attachment.id ];
				if ( attachment.url ) {
					delete privateAttachmentUrls[ comparableUrl( attachment.url ) ];
				}
			}

			// Etsi ja päivitä Gutenberg-lohkot
			const selectEditor = wp.data.select( 'core/block-editor' ) || wp.data.select( 'core/editor' );
			const dispatchEditor = wp.data.dispatch( 'core/block-editor' ) || wp.data.dispatch( 'core/editor' );

			if ( ! selectEditor || ! dispatchEditor ) {
				return;
			}

			function updateBlocks( blocks ) {
				if ( ! blocks || ! blocks.length ) return;

				blocks.forEach( ( block ) => {
					const { clientId, name, attributes, innerBlocks } = block;
					let changed = false;
					const newAttributes = {};

					if ( blockAttachmentId( name, attributes ) === attachment.id ) {
						// Päivitä URL-attribuutit
						[ 'url', 'href', 'mediaUrl', 'src', 'linkUrl' ].forEach( ( key ) => {
							if ( attributes[ key ] && attachment.url ) {
								newAttributes[ key ] = attachment.url;
								changed = true;
							}
						} );
					}

					if ( innerBlocks && innerBlocks.length ) {
						updateBlocks( innerBlocks );
					}

					if ( changed ) {
						dispatchEditor.updateBlockAttributes( clientId, newAttributes );
					}
				} );
			}

			updateBlocks( selectEditor.getBlocks() );
		} );
	}

	addFilter( 'blocks.registerBlockType', 'pgm/private-media-attribute', addPrivateMediaAttribute );
	addFilter( 'editor.BlockEdit', 'pgm/private-media-controls', withPrivateMediaControls );
	addFilter( 'editor.BlockListBlock', 'pecodex/private-media-badge', withPrivateMediaBadge );

	if ( wp.plugins && wp.editPost && wp.components && wp.data ) {
		const { registerPlugin } = wp.plugins;
		const { PluginPostStatusInfo } = wp.editPost;
		const { SelectControl, CheckboxControl, TextareaControl, BaseControl, PanelBody, Button, ButtonGroup, RadioControl } = wp.components;
		const { Fragment, useState, useEffect, createPortal } = wp.element;
		const { useDispatch, useSelect } = wp.data;

		const PecodexVisibilityPanel = () => {
			const [ isOpen, setIsOpen ] = useState( false );
			const [ headerToolbar, setHeaderToolbar ] = useState( null );

			useEffect( () => {
				const checkToolbar = setInterval( () => {
					// Jos meillä on jo toolbar ja se on edelleen sivulla, ei tehdä mitään
					if ( headerToolbar && document.contains( headerToolbar ) ) {
						return;
					}
					// Etsi toolbar uudelleen jos sitä ei ole tai se on kadonnut
					const toolbar = document.querySelector( '.edit-post-header-toolbar' ) || 
					                document.querySelector( '.edit-post-header__toolbar' ) ||
					                document.querySelector( '.edit-post-header-toolbar__left' ) ||
					                document.querySelector( '.components-navigate-regions .edit-post-header' );
					
					if ( toolbar && toolbar !== headerToolbar ) {
						setHeaderToolbar( toolbar );
					}
				}, 500 );
				return () => clearInterval( checkToolbar );
			}, [ headerToolbar ] );

			const meta = useSelect( ( select ) => {
				const editor = select( 'core/editor' );
				return editor ? editor.getEditedPostAttribute( 'meta' ) : {};
			} );
			
			if ( ! meta ) {
				return null;
			}
			
			const { editPost } = useDispatch( 'core/editor' );

			const protectionType = meta._pgm_protection_type || 'none';
			const visibilityRoles = meta._pgm_visibility_roles || [];
			const noAccessMessage = meta._pgm_no_access_message || '';

			const wpRoles = pluginSettings.wpRoles || {};
			const roleOptions = Object.keys( wpRoles ).map( ( roleSlug ) => ( {
				label: wpRoles[ roleSlug ],
				value: roleSlug,
			} ) );

			const updateMeta = ( key, value ) => {
				editPost( { meta: { ...meta, [ key ]: value } } );
			};

			const handleRoleChange = ( role, isChecked ) => {
				let newRoles = [ ...visibilityRoles ];
				if ( isChecked ) {
					newRoles.push( role );
				} else {
					newRoles = newRoles.filter( ( r ) => r !== role );
				}
				updateMeta( '_pgm_visibility_roles', newRoles );
			};

			const headerButton = headerToolbar ? createPortal(
				el( Button, {
					icon: protectionType !== 'none' ? 'lock' : 'unlock',
					onClick: () => setIsOpen( ! isOpen ),
					className: 'components-button has-icon has-text',
					style: { 
						marginLeft: '8px',
						color: protectionType !== 'none' ? '#db2777' : 'inherit',
						fontWeight: 600,
						padding: '0 8px'
					}
				}, protectionType !== 'none' ? 'Suojattu (Pecodex Security)' : 'Julkinen (Pecodex Security)' ),
				headerToolbar
			) : null;

			const leftSidebar = isOpen ? createPortal( el( 'div', {
				style: {
					position: 'fixed',
					left: 0,
					top: '60px',
					bottom: 0,
					width: '300px',
					background: '#fff',
					zIndex: 99990,
					borderRight: '1px solid #e2e4e7',
					boxShadow: '2px 0 5px rgba(0,0,0,0.05)',
					display: 'flex',
					flexDirection: 'column'
				}
			}, 
				el( 'div', {
					style: {
						display: 'flex',
						alignItems: 'center',
						justifyContent: 'space-between',
						padding: '16px',
						borderBottom: '1px solid #e2e4e7'
					}
				},
					el( 'strong', { style: { fontSize: '14px' } }, brandName ),
					el( Button, {
						icon: 'no-alt',
						label: __( 'Sulje', 'private-gutenberg-media' ),
						onClick: () => setIsOpen( false ),
						className: 'components-button has-icon'
					} )
				),
				el( 'div', { style: { padding: '20px', overflowY: 'auto', flex: 1, backgroundColor: '#fff', fontFamily: '-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Oxygen-Sans,Ubuntu,Cantarell,"Helvetica Neue",sans-serif' } },
					// 1. Sivun Näkyvyys
					el( 'div', { style: { display: 'flex', flexDirection: 'column', gap: '8px', marginBottom: '24px' } },
						el( 'span', { style: { color: '#334155', fontWeight: 600, fontSize: '14px', textTransform: 'uppercase', letterSpacing: '0.5px' } }, '1. Sivun Näkyvyys' ),
						el( 'div', { style: { display: 'flex', backgroundColor: '#f1f5f9', padding: '4px', borderRadius: '6px', border: '1px solid #e2e4e7' } },
							el( 'button', {
								onClick: () => updateMeta( '_pgm_protection_type', 'none' ),
								style: protectionType === 'none' 
									? { flex: 1, textAlign: 'center', padding: '8px', fontSize: '12px', fontWeight: 700, borderRadius: '4px', transition: 'all 0.2s', backgroundColor: '#fff', color: '#3b82f6', border: '1px solid #bfdbfe', boxShadow: '0 1px 2px 0 rgba(0,0,0,0.05)', cursor: 'pointer' }
									: { flex: 1, textAlign: 'center', padding: '8px', fontSize: '12px', fontWeight: 700, borderRadius: '4px', transition: 'all 0.2s', backgroundColor: 'transparent', color: '#64748b', border: '1px solid transparent', cursor: 'pointer' }
							}, 'Julkinen' ),
							el( 'button', {
								onClick: () => {
									if ( protectionType === 'none' ) updateMeta( '_pgm_protection_type', 'logged_in' );
								},
								style: protectionType !== 'none'
									? { flex: 1, textAlign: 'center', padding: '8px', fontSize: '12px', fontWeight: 700, borderRadius: '4px', transition: 'all 0.2s', backgroundColor: '#db2777', color: '#fff', border: '1px solid #be185d', boxShadow: '0 1px 2px 0 rgba(0,0,0,0.05)', cursor: 'pointer' }
									: { flex: 1, textAlign: 'center', padding: '8px', fontSize: '12px', fontWeight: 700, borderRadius: '4px', transition: 'all 0.2s', backgroundColor: 'transparent', color: '#64748b', border: '1px solid transparent', cursor: 'pointer' }
							}, 'Suojattu' )
						)
					),

					// 2. Kirjautumisjärjestelmä
					protectionType !== 'none' && el( 'div', { style: { display: 'flex', flexDirection: 'column', gap: '8px', marginBottom: '24px' } },
						el( 'span', { style: { color: '#334155', fontWeight: 600, fontSize: '14px', textTransform: 'uppercase', letterSpacing: '0.5px' } }, '2. Kirjautumisjärjestelmä' ),
						el( 'div', { style: { display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '8px' } },
							el( 'button', {
								onClick: () => {
									if ( protectionType === 'floauth' ) updateMeta( '_pgm_protection_type', 'logged_in' );
								},
								style: protectionType !== 'floauth'
									? { display: 'flex', alignItems: 'center', justifyContent: 'center', padding: '10px 8px', fontSize: '12px', fontWeight: 700, border: '1px solid #bfdbfe', borderRadius: '6px', transition: 'all 0.2s', backgroundColor: '#3b82f6', color: '#fff', boxShadow: '0 1px 2px 0 rgba(0,0,0,0.05)', cursor: 'pointer' }
									: { display: 'flex', alignItems: 'center', justifyContent: 'center', padding: '10px 8px', fontSize: '12px', fontWeight: 700, border: '1px solid #e2e4e7', borderRadius: '6px', transition: 'all 0.2s', backgroundColor: '#fff', color: '#3b82f6', cursor: 'pointer' }
							}, 'Perus WP' ),
							el( 'button', {
								onClick: () => {
									if ( protectionType !== 'roles' ) updateMeta( '_pgm_protection_type', 'floauth' );
								},
								style: protectionType === 'floauth'
									? { display: 'flex', alignItems: 'center', justifyContent: 'center', padding: '10px 8px', fontSize: '12px', fontWeight: 700, border: '1px solid #fbcfe8', borderRadius: '6px', transition: 'all 0.2s', backgroundColor: '#fdf2f8', color: '#be185d', boxShadow: '0 1px 2px 0 rgba(0,0,0,0.05)', cursor: 'pointer' }
									: { display: 'flex', alignItems: 'center', justifyContent: 'center', padding: '10px 8px', fontSize: '12px', fontWeight: 700, border: '1px solid #e2e4e7', borderRadius: '6px', transition: 'all 0.2s', backgroundColor: '#fff', color: '#3b82f6', cursor: 'pointer' }
							}, 'FloMembers' )
						)
					),

					// 3. Oikeusvaatimus
					protectionType !== 'none' && el( 'div', { style: { display: 'flex', flexDirection: 'column', gap: '8px', marginBottom: '24px' } },
						el( 'span', { style: { color: '#334155', fontWeight: 600, fontSize: '14px', textTransform: 'uppercase', letterSpacing: '0.5px' } }, '3. Oikeusvaatimus' ),
						el( 'div', { style: { display: 'flex', flexDirection: 'column', gap: '8px' } },
							el( 'label', {
								style: protectionType !== 'roles'
									? { display: 'flex', alignItems: 'center', padding: '12px', border: '1px solid #c7d2fe', borderRadius: '6px', cursor: 'pointer', transition: 'all 0.2s', backgroundColor: '#eef2ff', boxShadow: '0 0 0 1px #6366f1' }
									: { display: 'flex', alignItems: 'center', padding: '12px', border: '1px solid #e2e4e7', borderRadius: '6px', cursor: 'pointer', transition: 'all 0.2s', backgroundColor: '#fff' }
							},
								el( 'input', {
									type: 'radio',
									name: 'accessLevel',
									style: { marginRight: '12px', width: '16px', height: '16px', accentColor: '#4f46e5' },
									checked: protectionType !== 'roles',
									onChange: () => updateMeta( '_pgm_protection_type', 'logged_in' )
								} ),
								el( 'div', { style: { display: 'flex', flexDirection: 'column' } },
									el( 'span', { style: { fontSize: '14px', fontWeight: 700, color: protectionType !== 'roles' ? '#312e81' : '#334155' } }, 'Normaali Jäsensivu' ),
									el( 'span', { style: { fontSize: '11px', color: '#64748b', marginTop: '2px' } }, 'Kaikki kirjautuneet (WP tai FloMembers) pääsevät.' )
								)
							),
							el( 'label', {
								style: protectionType === 'roles'
									? { display: 'flex', alignItems: 'center', padding: '12px', border: '1px solid #fde68a', borderRadius: '6px', cursor: 'pointer', transition: 'all 0.2s', backgroundColor: '#fffbeb', boxShadow: '0 0 0 1px #f59e0b' }
									: { display: 'flex', alignItems: 'center', padding: '12px', border: '1px solid #e2e4e7', borderRadius: '6px', cursor: 'pointer', transition: 'all 0.2s', backgroundColor: '#fff' }
							},
								el( 'input', {
									type: 'radio',
									name: 'accessLevel',
									style: { marginRight: '12px', width: '16px', height: '16px', accentColor: '#d97706' },
									checked: protectionType === 'roles',
									onChange: () => updateMeta( '_pgm_protection_type', 'roles' )
								} ),
								el( 'div', { style: { display: 'flex', flexDirection: 'column' } },
									el( 'span', { style: { fontSize: '14px', fontWeight: 700, color: protectionType === 'roles' ? '#78350f' : '#334155' } }, 'Roolipohjainen' ),
									el( 'span', { style: { fontSize: '11px', color: '#64748b', marginTop: '2px' } }, 'Vaadi tiettyjä rooleja (valitaan alla).' )
								)
							)
						)
					),

					// 4. Aliroolien Pääsyoikeudet
					protectionType === 'roles' && el( 'div', { style: { padding: '20px', borderBottom: '1px solid #e2e4e7', backgroundColor: '#fff', margin: '0 -20px' } },
						el( 'h3', { style: { fontWeight: 600, color: '#1e293b', fontSize: '14px', margin: '0 0 4px 0' } }, 'Aliroolien Pääsyoikeudet (Sivu & Kansio)' ),
						el( 'p', { style: { fontSize: '11px', color: '#64748b', margin: '0 0 16px 0', lineHeight: 1.5 } }, 'Määritä, mille alirooleille tämä sivu (sekä linkitetty mediakansio) näytetään sivustolla.' ),
						el( 'div', { style: { display: 'flex', flexDirection: 'column', gap: '8px' } },
							roleOptions.map( ( role ) => {
								const isSelected = visibilityRoles.indexOf( role.value ) !== -1;
								return el( 'div', {
									key: role.value,
									onClick: () => handleRoleChange( role.value, !isSelected ),
									style: isSelected
										? { display: 'flex', alignItems: 'center', justifyContent: 'space-between', padding: '12px', borderRadius: '6px', border: '1px solid #fbcfe8', backgroundColor: '#fdf2f8', cursor: 'pointer', transition: 'all 0.2s', boxShadow: '0 1px 2px 0 rgba(0,0,0,0.05)' }
										: { display: 'flex', alignItems: 'center', justifyContent: 'space-between', padding: '12px', borderRadius: '6px', border: '1px solid #e2e4e7', backgroundColor: '#fff', cursor: 'pointer', transition: 'all 0.2s' }
								},
									el( 'span', { style: { fontWeight: 600, fontSize: '14px', color: isSelected ? '#831843' : '#475569' } }, role.label ),
									el( 'div', {
										style: isSelected
											? { width: '20px', height: '20px', borderRadius: '4px', display: 'flex', alignItems: 'center', justifyContent: 'center', backgroundColor: '#ec4899', border: '1px solid #ec4899', color: '#fff', transition: 'all 0.2s' }
											: { width: '20px', height: '20px', borderRadius: '4px', display: 'flex', alignItems: 'center', justifyContent: 'center', backgroundColor: '#f1f5f9', border: '1px solid #e2e4e7', color: 'transparent', transition: 'all 0.2s' }
									},
										el( 'svg', { 
											width: '14', height: '14', viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', strokeWidth: '3', strokeLinecap: 'round', strokeLinejoin: 'round'
										},
											el( 'polyline', { points: '20 6 9 17 4 12' } )
										)
									)
								);
							} )
						)
					),

					// 5. Ei käyttöoikeutta -viesti
					protectionType !== 'none' && el( 'div', { style: { padding: '20px', borderTop: '1px solid #e2e4e7', backgroundColor: '#f8fafc', margin: '0 -20px -20px -20px' } },
						el( 'h3', { style: { fontWeight: 600, color: '#1e293b', fontSize: '14px', margin: '0 0 4px 0', textTransform: 'uppercase' } }, 'Ei käyttöoikeutta -viesti' ),
						el( 'p', { style: { fontSize: '11px', color: '#64748b', margin: '0 0 12px 0', lineHeight: 1.5 } }, 'Tämä viesti näytetään kirjautuneille käyttäjille, joilla ei ole vaadittua roolia tälle sivulle.' ),
						el( 'textarea', {
							style: { width: '100%', boxSizing: 'border-box', border: '1px solid #e2e4e7', borderRadius: '6px', padding: '12px', fontSize: '14px', backgroundColor: '#fff', outline: 'none', transition: 'border-color 0.2s', minHeight: '100px', resize: 'vertical' },
							placeholder: 'Esim. Sinulla ei ole pääsy oikeutta testi sivulle, pahoittelut',
							value: noAccessMessage,
							onChange: ( e ) => updateMeta( '_pgm_no_access_message', e.target.value )
						} )
					)
				)
			), document.body ) : null;

			return el(
				Fragment,
				null,
				headerButton,
				leftSidebar,
				el( PluginPostStatusInfo, null, 
					el( 'div', { style: { display: 'flex', justifyContent: 'space-between', width: '100%', alignItems: 'center' } },
						el( 'span', null, __( 'Pecodex Suojaus', 'private-gutenberg-media' ) ),
						el( 'strong', { style: { 
							color: protectionType !== 'none' ? '#db2777' : '#10b981', 
							textTransform: 'uppercase', 
							fontSize: '11px', 
							letterSpacing: '0.5px' 
						} },
							protectionType !== 'none' ? __( 'Suojattu', 'private-gutenberg-media' ) : __( 'Julkinen', 'private-gutenberg-media' )
						)
					)
				)
			);
		};

		registerPlugin( 'pecodex-visibility-settings', {
			render: PecodexVisibilityPanel,
		} );
	}

} )( window.wp );
