<?php
/**
 * Security Module: GeoIP & Kirjautuminen
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$blocked_countries = get_option( 'pmc_blocked_countries', array() );
$login_countries   = get_option( 'pmc_geoip_login_countries', array() );
$login_action      = get_option( 'pmc_geoip_login_action', 'hide_form' ); // hide_form, block_admin
?>
<div class="ps-module" id="ps-module-geoip" style="display: none;">
	<div class="ps-module-header">
		<h2><span class="material-symbols-outlined">public</span> Maa-estot & Kirjautuminen</h2>
		<button class="ps-module-close material-symbols-outlined">close</button>
	</div>
	<div class="ps-module-content">
		
		<div class="ps-info-banner" style="background: #eff6ff; border: 1px solid #bfdbfe; border-left: 4px solid #3b82f6; padding: 16px; border-radius: 6px; margin-bottom: 20px; display: flex; gap: 12px; align-items: start;">
			<span class="material-symbols-outlined" style="color: #3b82f6; font-size: 24px;">info</span>
			<div>
				<h4 style="margin: 0 0 4px 0; color: #1e3a8a; font-size: 15px;">GeoIP-hallinta ja Maa-kohtaiset säännöt</h4>
				<p style="margin: 0; color: #3b82f6; font-size: 13px; line-height: 1.5;">Määritä koko sivuston globaalit maarajoitukset sekä erilliset kohdennetut kirjautumisen esto- ja suojaussäännöt. Voit klikata maita suoraan kummankin osion omalta kartalta.</p>
			</div>
		</div>

		<!-- Section 1: Globaali Maa-esto -->
		<div class="ps-card" style="margin-bottom: 24px; padding: 24px;">
			<div class="geoip-section-grid">
				<!-- Vasen sarake: 1/3 Säädöt -->
				<div class="geoip-controls-col">
					<div style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
						<span class="material-symbols-outlined" style="color: #b80048; font-size: 22px;">block</span>
						<h3 style="margin: 0; font-size: 17px; font-weight: 700; color: #0f172a;">1. Globaali Maa-esto</h3>
					</div>
					<p class="ps-desc" style="color: #64748b; font-size: 13px; line-height: 1.4; margin-bottom: 16px;">
						Estää sivuston kaiken sisällön näkymisen ja katkaisee yhteyden välittömästi (403 Forbidden) valituista maista tulevalle liikenteelle.
					</p>

					<div class="ps-input-group" style="margin-bottom: 14px;">
						<label style="font-weight: 600; font-size: 13px; color: #334155; margin-bottom: 6px; display: block;">Valitut maatunnukset (ISO-2):</label>
						<input type="text" id="geoip-global-countries" class="ps-input" style="width: 100%; font-family: monospace; font-size: 13px; padding: 8px 10px; border-radius: 6px; border: 1px solid #cbd5e1;" value="<?php echo esc_attr( implode( ',', $blocked_countries ) ); ?>" placeholder="esim. RU,CN,KP">
					</div>

					<div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 10px 12px; font-size: 12px; color: #64748b; line-height: 1.4;">
						<strong style="color: #334155;">Vinkki:</strong> Klikkaa viereistä karttaa valitaksesi tai poistaaksesi maita globaalista estolistasta.
					</div>
				</div>

				<!-- Oikea sarake: 2/3 Kartta -->
				<div class="geoip-map-col">
					<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
						<span style="font-size: 13px; font-weight: 600; color: #475569;">Globaalin eston karttavalinta</span>
						<span style="font-size: 11px; color: #94a3b8;">Klikkaa maata lisätäksesi/poistaaksesi</span>
					</div>
					<div id="geoip-map-global" style="height: 360px; width: 100%; border-radius: 8px; z-index: 1; border: 1px solid #cbd5e1; box-shadow: inset 0 1px 2px rgba(0,0,0,0.05);"></div>
				</div>
			</div>
		</div>

		<!-- Section 2: Kirjautumissivun (wp-login.php) Maa-esto -->
		<div class="ps-card" style="margin-bottom: 24px; padding: 24px;">
			<div class="geoip-section-grid">
				<!-- Vasen sarake: 1/3 Säädöt -->
				<div class="geoip-controls-col">
					<div style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
						<span class="material-symbols-outlined" style="color: #3b82f6; font-size: 22px;">lock_person</span>
						<h3 style="margin: 0; font-size: 17px; font-weight: 700; color: #0f172a;">2. Kirjautumissivun Maa-esto</h3>
					</div>
					<p class="ps-desc" style="color: #64748b; font-size: 13px; line-height: 1.4; margin-bottom: 16px;">
						Nämä maat pääsevät selaamaan sivuston julkista sisältöä normaalisti, mutta kirjautumiseen (wp-login.php) sovelletaan valittua rajoitusta.
					</p>

					<div class="ps-input-group" style="margin-bottom: 14px;">
						<label style="font-weight: 600; font-size: 13px; color: #334155; margin-bottom: 6px; display: block;">Kirjautumisen estotoimenpide:</label>
						<select id="geoip-login-action" class="ps-input" style="width: 100%; font-size: 13px; padding: 8px; border-radius: 6px; border: 1px solid #cbd5e1; background: #fff;">
							<option value="hide_form" <?php selected( $login_action, 'hide_form' ); ?>>Piilota kirjautumislomake koodista kokonaan (Suositus)</option>
							<option value="block_admin" <?php selected( $login_action, 'block_admin' ); ?>>Salli tavallisten käyttäjien kirjautuminen (Estä vain Adminit)</option>
						</select>
					</div>

					<div class="ps-input-group" style="margin-bottom: 14px;">
						<label style="font-weight: 600; font-size: 13px; color: #334155; margin-bottom: 6px; display: block;">Valitut maatunnukset (ISO-2):</label>
						<input type="text" id="geoip-login-countries" class="ps-input" style="width: 100%; font-family: monospace; font-size: 13px; padding: 8px 10px; border-radius: 6px; border: 1px solid #cbd5e1;" value="<?php echo esc_attr( implode( ',', $login_countries ) ); ?>" placeholder="esim. US,BR,IN">
					</div>

					<div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 10px 12px; font-size: 12px; color: #64748b; line-height: 1.4;">
						<strong style="color: #334155;">Vinkki:</strong> Klikkaa viereistä karttaa valitaksesi tai poistaaksesi maita kirjautumiseston listalta.
					</div>
				</div>

				<!-- Oikea sarake: 2/3 Kartta -->
				<div class="geoip-map-col">
					<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
						<span style="font-size: 13px; font-weight: 600; color: #475569;">Kirjautumiseston karttavalinta</span>
						<span style="font-size: 11px; color: #94a3b8;">Klikkaa maata lisätäksesi/poistaaksesi</span>
					</div>
					<div id="geoip-map-login" style="height: 360px; width: 100%; border-radius: 8px; z-index: 1; border: 1px solid #cbd5e1; box-shadow: inset 0 1px 2px rgba(0,0,0,0.05);"></div>
				</div>
			</div>
		</div>

		<!-- Tallenna -painike -->
		<div style="margin-top: 24px; display: flex; align-items: center; gap: 12px;">
			<button type="button" class="ps-btn ps-btn-primary" style="padding: 10px 24px; font-size: 14px; font-weight: 600;" onclick="window.pmcSaveGeoIPAdvanced(this); return false;">Tallenna kaikki GeoIP-asetukset</button>
			<span id="geoip-save-status" style="color: #16a34a; font-weight: 600; display: none; display: flex; align-items: center; gap: 4px;">
				<span class="material-symbols-outlined" style="font-size: 18px;">check_circle</span> Asetukset tallennettu onnistuneesti!
			</span>
		</div>

	</div>
</div>

<style>
/* 1/3 ja 2/3 asettelu */
.geoip-section-grid {
	display: grid;
	grid-template-columns: 1fr 2fr;
	gap: 24px;
	align-items: start;
}

@media (max-width: 980px) {
	.geoip-section-grid {
		grid-template-columns: 1fr;
	}
}

/* Poistaa selaimen vakio-fokusreunuksen (musta laatikko) Leaflet-SVG-poluista klikatessa */
#geoip-map-global path.leaflet-interactive:focus,
#geoip-map-login path.leaflet-interactive:focus {
	outline: none !important;
}
</style>

<script>
let pmcGeoDataCache = null;

let pmcMapGlobal = null;
let pmcLayerGlobal = null;
let pmcMarkersGlobal = null;

let pmcMapLogin = null;
let pmcLayerLogin = null;
let pmcMarkersLogin = null;

function pmcExtractIso(feature) {
	let iso = feature.properties['ISO3166-1-Alpha-2'] || feature.properties.ISO_A2 || feature.properties.iso_a2;
	if (!iso && feature.properties['ISO3166-1-Alpha-3']) {
		iso = feature.properties['ISO3166-1-Alpha-3'];
	}
	return (iso && iso !== '-99') ? iso.trim().toUpperCase() : null;
}

// 1. GLOBAALI KARTTA SYNC JA TYYLIT
function pmcGetGlobalStyle(feature) {
	const iso = pmcExtractIso(feature);
	let isSelected = false;
	if (iso) {
		const input = document.getElementById('geoip-global-countries');
		const arr = (input ? input.value : '').split(',').map(s => s.trim().toUpperCase()).filter(Boolean);
		if (arr.includes(iso)) isSelected = true;
	}
	if (isSelected) {
		return { color: '#b80048', weight: 2, fillColor: '#b80048', fillOpacity: 0.6 };
	}
	return { color: 'transparent', weight: 0, fillColor: 'transparent', fillOpacity: 0.0 };
}

function pmcSyncGlobalMap() {
	if (!pmcLayerGlobal || !pmcMapGlobal) return;
	pmcLayerGlobal.eachLayer(l => pmcLayerGlobal.resetStyle(l));

	if (!pmcMarkersGlobal) {
		pmcMarkersGlobal = L.layerGroup().addTo(pmcMapGlobal);
	} else {
		pmcMarkersGlobal.clearLayers();
	}

	const input = document.getElementById('geoip-global-countries');
	const arr = (input ? input.value : '').split(',').map(s => s.trim().toUpperCase()).filter(Boolean);

	pmcLayerGlobal.eachLayer(layer => {
		const iso = pmcExtractIso(layer.feature);
		if (iso && arr.includes(iso)) {
			const center = layer.getBounds().getCenter();
			const icon = L.divIcon({
				html: '<div style="background-color: #22c55e; border: 2px solid white; border-radius: 50%; width: 22px; height: 22px; display: flex; align-items: center; justify-content: center; box-shadow: 0 2px 5px rgba(0,0,0,0.35);"><span class="material-symbols-outlined" style="color: white; font-size: 15px; font-weight: bold;">check</span></div>',
				className: '',
				iconSize: [22, 22],
				iconAnchor: [11, 11]
			});
			L.marker(center, { icon: icon, interactive: false }).addTo(pmcMarkersGlobal);
		}
	});
}

// 2. KIRJAUTUMISKARTTA SYNC JA TYYLIT
function pmcGetLoginStyle(feature) {
	const iso = pmcExtractIso(feature);
	let isSelected = false;
	if (iso) {
		const input = document.getElementById('geoip-login-countries');
		const arr = (input ? input.value : '').split(',').map(s => s.trim().toUpperCase()).filter(Boolean);
		if (arr.includes(iso)) isSelected = true;
	}
	if (isSelected) {
		return { color: '#b80048', weight: 2, fillColor: '#b80048', fillOpacity: 0.6 };
	}
	return { color: 'transparent', weight: 0, fillColor: 'transparent', fillOpacity: 0.0 };
}

function pmcSyncLoginMap() {
	if (!pmcLayerLogin || !pmcMapLogin) return;
	pmcLayerLogin.eachLayer(l => pmcLayerLogin.resetStyle(l));

	if (!pmcMarkersLogin) {
		pmcMarkersLogin = L.layerGroup().addTo(pmcMapLogin);
	} else {
		pmcMarkersLogin.clearLayers();
	}

	const input = document.getElementById('geoip-login-countries');
	const arr = (input ? input.value : '').split(',').map(s => s.trim().toUpperCase()).filter(Boolean);

	pmcLayerLogin.eachLayer(layer => {
		const iso = pmcExtractIso(layer.feature);
		if (iso && arr.includes(iso)) {
			const center = layer.getBounds().getCenter();
			const icon = L.divIcon({
				html: '<div style="background-color: #22c55e; border: 2px solid white; border-radius: 50%; width: 22px; height: 22px; display: flex; align-items: center; justify-content: center; box-shadow: 0 2px 5px rgba(0,0,0,0.35);"><span class="material-symbols-outlined" style="color: white; font-size: 15px; font-weight: bold;">check</span></div>',
				className: '',
				iconSize: [22, 22],
				iconAnchor: [11, 11]
			});
			L.marker(center, { icon: icon, interactive: false }).addTo(pmcMarkersLogin);
		}
	});
}

function pmcBuildMap(containerId, isGlobal) {
	const map = L.map(containerId).setView([25, 0], 1.5);
	L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png', {
		maxZoom: 19
	}).addTo(map);

	const layer = L.geoJSON(pmcGeoDataCache, {
		style: isGlobal ? pmcGetGlobalStyle : pmcGetLoginStyle,
		onEachFeature: function(feature, l) {
			if (feature.properties && feature.properties.name) {
				l.bindTooltip(feature.properties.name, { sticky: true });
			}
			l.on('click', function() {
				const iso = pmcExtractIso(feature);
				if (!iso) return;
				const inputId = isGlobal ? 'geoip-global-countries' : 'geoip-login-countries';
				const input = document.getElementById(inputId);
				if (!input) return;

				let current = input.value.split(',').map(s => s.trim().toUpperCase()).filter(Boolean);
				if (current.includes(iso)) {
					current = current.filter(c => c !== iso);
				} else {
					current.push(iso);
				}
				input.value = current.join(',');

				if (isGlobal) {
					pmcSyncGlobalMap();
				} else {
					pmcSyncLoginMap();
				}
			});

			l.on('mouseover', function(e) {
				e.target.setStyle({ color: '#b80048', weight: 2, fillColor: '#b80048', fillOpacity: 0.6 });
			});
			l.on('mouseout', function(e) {
				const currentLayer = isGlobal ? pmcLayerGlobal : pmcLayerLogin;
				if (currentLayer) currentLayer.resetStyle(e.target);
			});
		}
	}).addTo(map);

	return { map, layer };
}

function pmcInitBothGeoIpMaps() {
	if (typeof L === 'undefined') {
		setTimeout(pmcInitBothGeoIpMaps, 400);
		return;
	}

	if (pmcMapGlobal && pmcMapLogin) {
		pmcMapGlobal.invalidateSize();
		pmcMapLogin.invalidateSize();
		return;
	}

	const loadData = pmcGeoDataCache 
		? Promise.resolve(pmcGeoDataCache)
		: fetch('https://raw.githubusercontent.com/datasets/geo-countries/master/data/countries.geojson').then(r => r.json());

	loadData.then(data => {
		pmcGeoDataCache = data;

		if (!pmcMapGlobal && document.getElementById('geoip-map-global')) {
			const resG = pmcBuildMap('geoip-map-global', true);
			pmcMapGlobal = resG.map;
			pmcLayerGlobal = resG.layer;
			pmcSyncGlobalMap();
		}

		if (!pmcMapLogin && document.getElementById('geoip-map-login')) {
			const resL = pmcBuildMap('geoip-map-login', false);
			pmcMapLogin = resL.map;
			pmcLayerLogin = resL.layer;
			pmcSyncLoginMap();
		}
	}).catch(err => console.error("Could not load map data:", err));

	const globalInput = document.getElementById('geoip-global-countries');
	if (globalInput) globalInput.addEventListener('input', pmcSyncGlobalMap);

	const loginInput = document.getElementById('geoip-login-countries');
	if (loginInput) loginInput.addEventListener('input', pmcSyncLoginMap);
}

// Observe tab visibility to invalidate sizes & load
document.addEventListener('DOMContentLoaded', () => {
	const observer = new MutationObserver((mutations) => {
		mutations.forEach((mutation) => {
			if (mutation.target.id === 'ps-module-geoip' && mutation.target.style.display !== 'none') {
				setTimeout(pmcInitBothGeoIpMaps, 100);
			}
		});
	});
	const geoipModule = document.getElementById('ps-module-geoip');
	if (geoipModule) {
		observer.observe(geoipModule, { attributes: true, attributeFilter: ['style'] });
		if (geoipModule.style.display !== 'none') {
			setTimeout(pmcInitBothGeoIpMaps, 100);
		}
	}
});

window.pmcSaveGeoIPAdvanced = async (btn) => {
	const origText = btn.textContent;
	btn.textContent = 'Tallennetaan...';
	btn.disabled = true;

	const globalCountries = document.getElementById('geoip-global-countries').value;
	const loginCountries  = document.getElementById('geoip-login-countries').value;
	const loginAction     = document.getElementById('geoip-login-action').value;

	const formData = new FormData();
	formData.append('action', 'pmc_save_geoip_settings');
	formData.append('nonce', window.pmcSecurityNonce);
	formData.append('global_countries', globalCountries);
	formData.append('login_countries', loginCountries);
	formData.append('login_action', loginAction);

	try {
		const res = await fetch(window.pmcSecurityAjaxUrl, {
			method: 'POST',
			body: formData
		});
		
		const data = await res.json();
		console.log('GeoIP Save Response:', data);
		
		if (data.success) {
			const status = document.getElementById('geoip-save-status');
			if (status) {
				status.style.display = 'inline-flex';
				setTimeout(() => status.style.display = 'none', 3500);
			}
		} else {
			alert('Palvelin palautti virheen: ' + (data.data || 'Tuntematon virhe'));
		}
	} catch (e) {
		console.error('Save error:', e);
		alert('Virhe tallennettaessa: ' + e.message);
	}

	btn.textContent = origText;
	btn.disabled = false;
};
</script>
