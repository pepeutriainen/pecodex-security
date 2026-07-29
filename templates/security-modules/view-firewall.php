<?php
/**
 * Security Module: Firewall & Lockouts
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="ps-module" id="ps-module-firewall" style="display: none;">
	<div class="ps-module-header">
		<h2><span class="material-symbols-outlined">shield</span> Palomuuri ja Lukitukset</h2>
		<button class="ps-module-close material-symbols-outlined">close</button>
	</div>
	<div class="ps-module-content">
		
		<div class="ps-info-banner" style="background: #eff6ff; border: 1px solid #bfdbfe; border-left: 4px solid #3b82f6; padding: 16px; border-radius: 6px; margin-bottom: 20px; display: flex; gap: 12px; align-items: start;">
			<span class="material-symbols-outlined" style="color: #3b82f6; font-size: 24px;">info</span>
			<div>
				<h4 style="margin: 0 0 4px 0; color: #1e3a8a; font-size: 15px;">Miten tämä moduuli suojaa sivustoasi</h4>
				<p style="margin: 0; color: #3b82f6; font-size: 13px; line-height: 1.5;">Palomuuri tunnistaa ja estää automaattisesti haitalliset botit tai hyökkääjät, jotka yrittävät arvata salasanoja (Brute Force) tai etsiä haavoittuvuuksia (404-tunnistus). Tämä pitää luvattomat käyttäjät täysin ulkona palvelimelta.</p>
			</div>
		</div>
		
		<div class="ps-tabs">
			<button class="ps-tab-btn active" data-target="#tab-fw-settings">Asetukset</button>
			<button class="ps-tab-btn" data-target="#tab-fw-ips">IP-estolista</button>
			<button class="ps-tab-btn" data-target="#tab-fw-allow-ips">Sallitut IP:t</button>
			<button class="ps-tab-btn" data-target="#tab-fw-log">Lukitusloki</button>
		</div>

		<div class="ps-tab-content active" id="tab-fw-settings">
			<div class="ps-card">
				<div class="mask-header-row" style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 16px; margin-bottom: 24px;">
					<div class="mask-header-text">
						<h3 style="margin: 0 0 4px 0; font-size: 16px; font-weight: 600; color: #0f172a;">Kirjautumissuojaus</h3>
						<p style="margin: 0; font-size: 14px; color: #64748b;">Lukitse käyttäjät väliaikaisesti useiden epäonnistuneiden kirjautumisyritysten jälkeen.</p>
					</div>
					<div class="mask-header-toggle" style="background: #f8fafc; padding: 12px 16px; border-radius: 8px; border: 1px solid #f1f5f9;">
						<label class="mask-toggle-container" style="display: flex; align-items: center; gap: 12px; cursor: pointer;">
							<span style="font-size: 14px; font-weight: 500; color: #475569;">Ota kirjautumissuojaus käyttöön</span>
							<label class="ps-switch" style="margin:0;">
								<input type="checkbox" id="fw-login-toggle" onchange="window.pmcSaveFirewall()" checked>
								<span class="ps-slider"></span>
							</label>
						</label>
					</div>
				</div>
				<hr class="mask-divider" style="border: 0; height: 1px; background: #e2e8f0; margin: 20px 0;">
				<div class="mask-inputs-row" style="margin-top: 15px; display: flex; gap: 20px;">
					<div class="mask-input-col" style="flex: 1;">
						<label style="display: block; margin-bottom: 8px; font-size: 13px; font-weight: 600; color: #475569;">Kirjautumisyritysten enimmäismäärä</label>
						<input type="number" id="fw-login-attempts" onchange="window.pmcSaveFirewall()" value="5" min="1" class="ps-input" style="width: 100%;">
					</div>
					<div class="mask-input-col" style="flex: 1;">
						<label style="display: block; margin-bottom: 8px; font-size: 13px; font-weight: 600; color: #475569;">Lukituksen kesto</label>
						<select id="fw-login-lockout-duration" onchange="window.pmcSaveFirewall()" class="ps-input" style="width: 100%;">
							<option value="900">15 minuuttia</option>
							<option value="3600">1 tunti</option>
							<option value="14400" selected>4 tuntia</option>
							<option value="86400">24 tuntia</option>
							<option value="604800">7 päivää</option>
							<option value="2592000">30 päivää</option>
						</select>
					</div>
				</div>
			</div>

			<div class="ps-card mt-4">
				<h3>404-tunnistus</h3>
				<p class="ps-desc">Lukitse käyttäjät, jotka etsivät toistuvasti olemattomia sivuja.</p>
				<div class="ps-control-row">
					<label class="ps-switch">
						<input type="checkbox" id="fw-404-toggle" onchange="window.pmcSaveFirewall()" checked>
						<span class="ps-slider"></span>
					</label>
					<span>Ota 404-tunnistus käyttöön</span>
				</div>
				<div class="ps-input-group">
					<label>Lukitse seuraavien (404-virheiden) jälkeen</label>
					<input type="number" id="fw-404-attempts" onchange="window.pmcSaveFirewall()" value="20" min="1">
				</div>
			</div>
		</div>

		<div class="ps-tab-content" id="tab-fw-ips">
			<div class="ps-card">
				<h3>Estä IP-osoite</h3>
				<div style="display: flex; gap: 10px; margin-top: 10px;">
					<input type="text" id="fw-ban-ip-input" placeholder="esim. 192.168.1.100" class="ps-input" style="flex:1;">
					<button class="ps-btn ps-btn-danger" id="fw-ban-ip-btn">Estä IP</button>
				</div>
			</div>
			
			<div class="ps-card mt-4">
				<h3>Aktiiviset lukitukset ja estetyt IP:t</h3>
				<div class="ps-table-wrapper">
					<table class="ps-table" id="fw-banned-ips-table">
						<thead>
							<tr>
								<th>IP-osoite</th>
								<th>Tila</th>
								<th>Toiminto</th>
							</tr>
						</thead>
						<tbody>
							<tr><td colspan="3">Ladataan...</td></tr>
						</tbody>
					</table>
				</div>
			</div>
		</div>

		<div class="ps-tab-content" id="tab-fw-allow-ips">
			<div class="ps-card">
				<h3>Salli IP-osoite</h3>
				<p class="ps-desc">Nämä IP-osoitteet ohittavat kaikki palomuurin lukitukset (esim. omat ylläpito-osoitteet tai turvaskannerit).</p>
				<div style="display: flex; gap: 10px; margin-top: 10px;">
					<input type="text" id="fw-allow-ip-input" placeholder="esim. 192.168.1.100" class="ps-input" style="flex:1;">
					<button class="ps-btn ps-btn-primary" id="fw-allow-ip-btn">Salli IP</button>
				</div>
			</div>
			
			<div class="ps-card mt-4">
				<h3>Sallitut IP-osoitteet</h3>
				<div class="ps-table-wrapper">
					<table class="ps-table" id="fw-allowed-ips-table">
						<thead>
							<tr>
								<th>IP-osoite</th>
								<th>Toiminto</th>
							</tr>
						</thead>
						<tbody>
							<tr><td colspan="2">Ladataan...</td></tr>
						</tbody>
					</table>
				</div>
			</div>
		</div>

		<div class="ps-tab-content" id="tab-fw-log">
			<div class="ps-card">
				<h3>Viimeisimmät palomuuritapahtumat (viimeiset 50)</h3>
				<div class="ps-table-wrapper">
					<table class="ps-table" id="fw-log-table">
						<thead>
							<tr>
								<th>Päivämäärä ja aika</th>
								<th>Tyyppi</th>
								<th>IP-osoite</th>
								<th>Lisätiedot</th>
							</tr>
						</thead>
						<tbody>
							<tr><td colspan="4">Ladataan...</td></tr>
						</tbody>
					</table>
				</div>
			</div>
		</div>

	</div>
</div>
