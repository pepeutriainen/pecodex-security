=== Pecodex Media Control ===
Contributors: Pepe Utriainen, Pecodex
Tags: media library, folders, private media, file manager, pdf preview
Requires at least: 6.2
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 0.8.68
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Pecodex Media Control turns the public-by-default WordPress Media Library into a folder-based, security-aware media manager with protected uploads links, private storage support and document previews.

== Description ==

Pecodex Media Control is a security-focused WordPress media management plugin by Pepe Utriainen / Pecodex.

By default, WordPress stores uploaded media in public `/wp-content/uploads/` URLs. If someone knows, guesses, copies or indexes a direct media URL, that file can often be opened directly even when the page using it is not visible. This is normal WordPress behavior, but it is not enough for sites that need better control over documents, member files, internal images or other restricted media.

Pecodex Media Control adds a controlled layer on top of the standard WordPress Media Library. It keeps attachments compatible with WordPress, themes and other plugins, while giving administrators a visual folder system and tools to protect selected media from public direct-link access.

Protected media can be opened through a Pecodex endpoint that checks the current user's login state and permissions before serving the file. When private storage is enabled, the plugin also moves protected files away from their public uploads URL and stores them in an encrypted private storage format, with best-effort server hardening for common hosting environments.

The goal is simple: keep WordPress media easy to manage, but give site owners a safer choice than leaving every uploaded file permanently public.

Key features:

* Visual media folders and nested subfolders.
* Folder ordering with drag handles.
* New folder, rename, delete and properties modals.
* Folder protection for logged-in users or administrators.
* Bulk protect, unprotect, move, duplicate, export and delete actions.
* Right-click context menus for folders and selected media.
* Marquee selection for selecting many files with the mouse.
* PDF preview with PDF.js.
* DOCX preview with Mammoth.js.
* XLSX preview with SheetJS.
* Native video and audio previews.
* Custom SVG icons for PDF, Word, Excel and PowerPoint files.
* Protected media links through Pecodex endpoints.
* Encrypted private storage for files hidden from public uploads URLs.
* Safe deactivation and uninstall flow that restores protected media back to normal WordPress files before the plugin is removed.
* Diagnostics and repair tools for private media storage and content link sync.

The old PGM class names, option names and text domain are kept only for upgrade compatibility with existing sites. New user-facing labels, endpoints and packages use the Pecodex Media Control name.

== Security Model ==

WordPress keeps the original attachment path in `_wp_attached_file` so media can be restored safely when protection is removed.

When a file is protected, Pecodex Media Control stores the original public URL in plugin meta, rewrites content links to a protected Pecodex endpoint and, when private storage is enabled, removes the public uploads copy after an encrypted private copy has been created.

The preferred private storage location is outside the public WordPress webroot. If a hosting environment does not allow PHP to write there, the plugin falls back to encrypted uploads storage and writes best-effort Apache/LiteSpeed/IIS deny rules.

This means a protected media item can be opened only through the WordPress/Pecodex request flow, where login, capability and nonce checks are applied before the file is served.

== Installation ==

1. Upload the `pecodex-media-control` folder to `/wp-content/plugins/`.
2. Activate "Pecodex Media Control" in WordPress admin.
3. Open Media Library to use the folder sidebar.
4. Open Pecodex Media Control settings to adjust private media and diagnostics.

== Changelog ==

= 0.8.69 =
* Add a secure "Jaa tiedosto" (Share file) panel in Media Library attachment details for private media.
* Share links support optional email recipient, configurable expiry (1 hour, 1 day, 1 week, 30 days, or never) and single-use mode.
* Email invitation is sent via wp_mail when an address is provided.
* Add a centralized "Jaetut tiedostot" admin page under Media showing all active share links with copy and revoke actions.
* Deduplicate duplicate AJAX action registrations for share link handlers.

= 0.8.68 =
* Add short-lived, user-session-bound protected download tokens on top of WordPress nonces.
* Regenerate protected endpoint URLs through the login-start flow when an old endpoint link is missing or has an expired token.
* Add a managed uploads cache guard so media URLs revalidate before protected direct-link rules return Gone responses.

= 0.8.67 =
* Make public restore strict: the canonical WordPress upload file must be restored before protection meta and upload rules are removed, manual protection meta is kept if restore fails, and media privacy AJAX refreshes protection rules before returning to the UI.

= 0.8.66 =
* Harden managed uploads `.htaccess` writes using Defender-style safeguards: own marker block only, backup before write, WordPress filesystem support with direct-file fallback, preserved third-party rules and server-type diagnostics.

= 0.8.65 =
* Block protected legacy `/wp-content/uploads/` URLs with a server-level Gone response instead of redirecting them to the protected login endpoint, so old direct links cannot be used by logged-in users either.

= 0.8.64 =
* Write the protected uploads block before image optimization rewrite rules so optimized image variants cannot bypass Pecodex protection.

= 0.8.63 =
* Add managed uploads `.htaccess` rules for Apache/LiteSpeed hosts so protected media's old direct uploads URLs redirect to the Pecodex protected endpoint before static file serving when the request reaches the server.
* Refresh and remove those managed rules automatically when media is protected, restored to public, updated, deactivated or uninstalled.

= 0.8.62 =
* Improve the plugin header and readme description to explain the security purpose: WordPress uploads are public by default, while Pecodex Media Control adds protected media handling and private storage support.

= 0.8.61 =
* Restore protected media back to normal public WordPress uploads files when the plugin is deactivated.
* On uninstall, restore protected media first and then remove Pecodex/PGM media control metadata, options and folder taxonomy data.

= 0.8.60 =
* Clarify folder-locked media actions in attachment details so manual unlock is not shown while a protected folder controls access.
* Add an explicit action to detach a media item from its protected folder and restore it as a public WordPress media file.

= 0.8.59 =
* Scale DOCX thumbnails as a virtual document page so Word files look like page previews instead of oversized text snippets.

= 0.8.58 =
* Render DOCX thumbnails from Mammoth HTML instead of hidden placeholder bars.
* Show real worksheet cell values and PowerPoint slide text in Media Library thumbnails.

= 0.8.57 =
* Show a compact in-thumbnail loader while PDF, Word, Excel and PowerPoint previews render their first page/content.

= 0.8.56 =
* Hide the large fallback file icon while async document thumbnails are rendering, leaving the card-corner type badge visible.
* Restore the fallback icon only if a PDF, Word, Excel or PowerPoint thumbnail preview cannot be rendered.

= 0.8.55 =
* Increase the card-level file-type SVG badge size so PDF, Word, Excel and PowerPoint types are easier to identify.

= 0.8.54 =
* Move the file-type SVG badge from the rendered document preview to the attachment card corner.
* Keep PDF, Word, Excel and PowerPoint preview content clean while preserving a card-level type indicator.

= 0.8.53 =
* Add a small file-type SVG badge to rendered document thumbnails for PDF, Word, Excel and PowerPoint files.
* Keep the badge separate from preview content so async thumbnail rendering cannot remove it.

= 0.8.52 =
* Add safe Media Library thumbnail previews inside Pecodex file icons for PDF, DOCX, Excel and PowerPoint files.
* Keep WordPress attachment card structure untouched so failed previews fall back to the existing file-type SVG icon.

= 0.8.51 =
* Roll Media Library grid cards back to the stable WordPress card plus Pecodex badge and file-icon renderer.
* Remove the async PDF canvas renderer from grid cards so WordPress attachment refreshes cannot blank protected cards.

= 0.8.50 =
* Route WordPress attachment card badges and PDF previews through one shared Pecodex card enhancer used by both Backbone renders and DOM refreshes.
* Avoid loading a second PDF.js copy on shared admin screens by reusing the admin media PDF.js handle when available.

= 0.8.49 =
* Re-render PDF grid previews after WordPress refreshes an attachment card, instead of trusting stale render flags.
* Clear stale rendered-preview classes before rebuilding document icons so protected cards cannot turn blank after async refresh.

= 0.8.48 =
* Keep PDF and document fallback icons visible until a real rendered preview is available.
* Prevent protected Media Library cards from going blank when a PDF preview cannot render.

= 0.8.47 =
* Hide legacy PDF/document icons when a real media preview has rendered in the Media Library grid.
* Keep rendered document previews above the file-type icon layer while preserving protected badges.

= 0.8.46 =
* Harden folder parent saving with a database-level verification fallback.
* Make folder drag-and-drop easier by using a larger inside-drop zone for subfolders.

= 0.8.45 =
* Verify protected media storage, public uploads removal and folder taxonomy consistency for the production package.
* Clean diagnostic panel encoding strings and folder parent save error messages.
* Mark the package tested against WordPress 7.0.

= 0.8.44 =
* Refresh attachment privacy state immediately after single and bulk protect/unprotect actions so images and generated sizes do not appear stale in the media UI.
* Add explicit privacy-toggle debug entries with the requested intent and resulting attachment state.

= 0.8.43 =
* Clean stale private-storage metadata from media that has been restored to public uploads, preventing old protected-state leftovers from confusing the UI or diagnostics.

= 0.8.42 =
* Restore stronger browser cache forcing for local development domains and make admin-side cache reload follow redirects while also clearing Cache Storage.

= 0.8.41 =
* Clean optimizer sidecar files during media deletion and return cache-clear URLs to admin AJAX so the current browser session can force-reload stale public media URLs after protect/unprotect.

= 0.8.40 =
* Protect image optimization sidecar files generated next to uploads images, including WebP/AVIF and LiteSpeed .optm variants, and expose a filter for additional optimizer paths.

= 0.8.39 =
* Open protected "Avaa suojatusti" media links in a new browser tab with noopener/noreferrer.

= 0.8.38 =
* Center privacy modal icons correctly and use a WordPress logo icon for the public restore result row.

= 0.8.37 =
* Show the protect/unprotect action panel directly in the WordPress attachment details sidebar, including Gutenberg media modal layouts where the settings container differs from the classic media view.

= 0.8.36 =
* Hide empty regular folders in scoped Gutenberg/media picker views so Image/File modals show only folders that contain matching media, while preserving the active selection and special folders.

= 0.8.35 =
* Make the Gutenberg media modal sidebar counts dynamic so Image, File and other media picker contexts show folder and subfolder totals for the currently filtered media type.
* Keep the folder tree state endpoint scoped to a whitelisted WordPress media query context instead of always counting all attachments.

= 0.8.34 =
* Keep the selected Pecodex folder, sort order and deep-search state attached to every WordPress media modal query so type, date and search filters stay inside the active folder.

= 0.8.33 =
* Make sidebar counts and folder filtering use the same folder scope, including subfolders when that setting is enabled.
* Return both direct and nested folder counts in the media organizer state so parent folder totals stay consistent with filtered results.

= 0.8.32 =
* Set the default startup folder to "Kaikki tiedostot" for both the main Media Library and Gutenberg media modal.
* Migrate the old legacy "-1 / Ilman kansiota" startup default to "Kaikki tiedostot" once on existing sites.

= 0.8.31 =
* Make the Gutenberg media modal use the same Pecodex frame, selection, bulk action and refresh context as the main Media Library grid.
* Scope right-click, drag, marquee, bulk protect/unprotect, move, duplicate, export and delete actions to the active media modal instead of a global media frame.

= 0.8.30 =
* Remove the old Gutenberg/internal private-link endpoint flow so media protection is handled only at attachment/file level.
* Keep a lightweight legacy parser that restores old `pgm_private_link` and `pecodex_private_link` URLs back to their original internal targets.

= 0.8.29 =
* Normalize restored public uploads URLs to HTTPS when the current site/request is HTTPS so Chrome does not block restored DOCX/PPT/XLS downloads as insecure mixed downloads.

= 0.8.28 =
* Use LiteSpeed's quiet URL purge path for Pecodex automatic cache clears so protected media cache invalidation no longer floods wp-admin with "Purge url" notices.

= 0.8.27 =
* Hide protected media href links from logged-out frontend HTML so protected files are not rendered as clickable PDF/DOC/XLS/PPT/ZIP/media links.
* Apply the logged-out no-render rule across protected uploads URLs and protected Pecodex endpoint URLs for all attachment file types.

= 0.8.26 =
* Purge LiteSpeed and common page caches for posts/pages whose content contains media that is being protected or restored.
* Add a final frontend HTML rewrite safety layer so protected image src/srcset values are hidden from logged-out visitors even when block theme output bypasses earlier content filters.

= 0.8.25 =
* Avoid browser console warnings on local HTTP sites by sending Clear-Site-Data only on HTTPS or trusted localhost origins.
* Hide protected embedded media from logged-out visitors with an inline transparent placeholder instead of rendering the protected login endpoint as an image src/srcset.
* Keep protected href links as login-flow links while preventing private image/video poster embeds from making unnecessary protected endpoint requests on public pages.

= 0.8.24 =
* Include WordPress image backup sizes, discovered thumbnail variants and converted WebP/AVIF URLs in protected media storage sync and cache purges.
* Remember protected media variant paths so old direct thumbnail URLs can still be matched after public files are moved out of uploads.

= 0.8.23 =
* Send browser cache-clearing headers on admin media protection changes so the current browser drops same-origin cached uploads as soon as files are protected or restored.
* Route folder-based protection through the same storage sync, content link sync and cache purge path as manual media protection.
* Purge public URL caches when a media item is restored to public uploads through the direct make-public path.

= 0.8.22 =
* Harden protected media cache handling with Clear-Site-Data, stronger robots/cache headers and targeted cache purge hooks when media protection changes.

= 0.8.21 =
* Add a protected legacy upload alias fallback so old shared URLs with small filename differences still redirect to the secured media endpoint.

= 0.8.20 =
* Unify Gutenberg and Media Library protection into one file-level protection model.
* Migrate old Gutenberg link-source metadata to manual media protection so "Poista suojaus" behaves consistently.
* Remove the Gutenberg block/sidebar lock UI while keeping editor-only protected badges for actually protected media.
* Update Media Library, bulk action and diagnostic wording to avoid the old separate Gutenberg source-lock model.

= 0.8.19 =
* Remove the confusing "Suojaa käsin" action from media that is already protected by a Gutenberg or folder source.
* Keep source-protected media UI focused on the actual source page/folder that controls protection.

= 0.8.18 =
* Clarify Media Library protection actions when a file is protected by both manual media protection and a Gutenberg or folder source.
* Add a direct "Suojaa käsin" action for source-protected media that no longer has manual protection.

= 0.8.17 =
* Harden protected media endpoints so they immediately repair or fail when a protected attachment still has a public uploads copy.
* Verified direct uploads URLs, cache-busted uploads URLs, protected login redirects, and private storage URLs for protected media.

= 0.8.16 =
* Restore the media sidebar action label to "Poista suojaus".
* Use consistent "suojaus" wording instead of technical manual hiding language in the Media Library attachment sidebar.
* Clarify when Gutenberg or folder sources still need to be opened to remove source-level protection.

= 0.8.15 =
* Fixed restoring manually protected media back to public uploads.
* Clear protected content-link state when an attachment is made public so old sync metadata cannot keep it locked.
* Keep folder and Gutenberg source locks strict while allowing per-file public overrides for automatic extension rules.

= 0.8.14 =
* Prefer private media storage outside the public WordPress webroot so protection works independently of Apache, Nginx, LiteSpeed or IIS rewrite support.
* Keep reading existing encrypted uploads storage and older legacy private storage paths for backwards compatibility.
* Fall back to encrypted uploads storage only when the hosting environment cannot write outside the public webroot.

= 0.8.13 =
* Force a private-storage sync before returning a protected attachment URL in strict mode.
* Prevent a short race window where a new protected endpoint could be shown while the old public uploads copy still existed.
* Add debug logging for protected URL storage sync readiness.

= 0.8.12 =
* Improved sidebar folder dragging so folders with subfolders auto-expand while hovering anywhere on the folder row.
* Added a subtle auto-expand hover state for better control when moving folders or media into nested folders.
* Reused the same auto-expand behavior for media drag-to-folder workflows.

= 0.8.11 =
* Treat existing Pecodex protected media endpoint links as file-level private sources when strict private storage is enabled.
* Repair legacy protected content links by marking their attachments private and moving public uploads copies into encrypted private storage.
* Prevent old public uploads URLs from continuing to serve files after a protected link has already been generated.

= 0.8.10 =
* Added delayed auto-expand for folders with subfolders while arranging the sidebar folder tree.
* Kept folder drag state visible after an auto-expanded sidebar rerender.

= 0.8.9 =
* Improved folder tree drag arranging with larger before/inside/after drop zones.
* Made folder reorder line indicators thicker and easier to see while dragging.
* Kept the middle of a folder row as the "drop inside as subfolder" target for clearer hierarchy changes.

= 0.8.8 =
* Restored normal Media Library grid AJAX folder navigation by using WordPress' `wp.media.frames.browse` frame on `upload.php`.
* Prevented folder clicks in the main Media Library from falling back to full page reloads while the media collection is still initializing.

= 0.8.7 =
* Scoped Media Library Organizer AJAX refreshes to the sidebar/media picker instance that triggered the action.
* Fixed Gutenberg media picker folder filtering when multiple WordPress media frames exist.
* Fixed the sidebar resize drag typo that could leave the organizer script in a broken state after resizing.

= 0.8.4 =
* Fixed folder filtering in WordPress media AJAX queries by reading the canonical `pecodex_media_folder` value from the raw media query request before WordPress strips unknown query keys.
* Restored correct folder filtering for the main Media Library grid and Gutenberg media picker sidebars.

= 0.8.3 =
* Adjusted protected badges for Gutenberg File blocks so the badge floats above the file row instead of squeezing or covering the link/download controls.
* Marked editor badges as non-editable editor-only UI so they are not treated as block content and do not appear on the frontend.

= 0.8.2 =
* Replaced the Gutenberg protected badge pseudo-element with a real badge element injected into the block wrapper, avoiding WordPress selection and resize pseudo-element conflicts.
* Keeps the badge visually stable when selecting, resizing or opening links in protected Image/File/Media blocks.

= 0.8.1 =
* Fixed the Gutenberg badge placement by removing the editor-only React text node and using a strict BlockListBlock wrapper pseudo-element only.
* Avoided the previous image-selection glitch by no longer applying generic pseudo-element selectors to inner media markup.

= 0.8.0 =
* Reworked the Gutenberg protected badge from a CSS pseudo-element into a real editor-only React badge element to avoid image selection and resize glitches.
* Kept BlockListBlock responsible only for the editor wrapper class, matching WordPress block editor extension guidance.

= 0.7.99 =
* Moved Gutenberg protected badge CSS into WordPress editor settings so the badge styles load inside the iframed block editor canvas.
* Kept the BlockListBlock wrapper class/data approach aligned with the WordPress Block Editor Handbook.

= 0.7.98 =
* Fixed Gutenberg protected badges by resolving block attributes from the editor store and matching protected media by attachment ID, original URL, active Pecodex URL and upload-relative file paths.
* Added image-size URL matching so protected badges also appear when Gutenberg uses resized image URLs.

= 0.7.97 =
* Hardened Gutenberg File blocks so PDF previews are disabled by default, removed again after media selection and hidden in the editor for old saved blocks.
* Kept File blocks in the normal WordPress file-link/download-button layout while Pecodex still protects the underlying URL when requested.

= 0.7.96 =
* Disabled WordPress File block inline PDF previews in the editor so inserted files behave as normal file links with a download button.
* Added a render-time safeguard that removes old File block PDF preview embeds from Pecodex-rendered content.

= 0.7.95 =
* Added a red protected badge with a lock icon directly on protected Gutenberg media blocks.
* The editor badge detects protected attachment IDs and media blocks whose links are locked by Pecodex.

= 0.7.94 =
* Switched Media Library JavaScript calls to canonical Pecodex AJAX action names while keeping legacy fallbacks registered.
* Added the `pecodex_media_gallery` shortcode and made the gallery builder generate the Pecodex shortcode by default.

= 0.7.93 =
* Added direct uploads URL diagnostics so admins can see whether protected files are physically blocked from public uploads.
* Removed duplicate delete-folder translation keys so the custom delete modal has one predictable body copy.

= 0.7.92 =
* Added a repeatable Pecodex package builder for canonical WordPress zip releases.
* The package builder emits `pecodex-media-control.php` as the plugin entry file and keeps legacy source naming out of release zips.

= 0.7.91 =
* Packaged the canonical plugin entry file as `pecodex-media-control.php`.
* Added a safe fallback so the organizer can resolve either the canonical Pecodex entry file or the legacy entry file.

= 0.7.90 =
* Added Pecodex package readme.
* Prepared WordPress zip package with `pecodex-media-control` as the top-level folder.
* Kept legacy PGM internals for safe upgrades while preserving Pecodex user-facing branding.

= 0.7.89 =
* Added a dedicated Media Library View submodal for grid, sorting, search and folder tree preferences.
* Moved About and Support card before the save button in settings.

= 0.7.88 =
* Improved protected link synchronization for old upload URLs saved with another site domain.
