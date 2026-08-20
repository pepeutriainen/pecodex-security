<?php
/**
 * Integrations View
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="ps-module" id="ps-module-integrations" style="display: none;">
	<div class="ps-module-header">
		<h2><span class="material-symbols-outlined">extension</span> Integraatiot</h2>
		<button class="ps-module-close material-symbols-outlined">close</button>
	</div>
	<div class="ps-module-content">
		<div class="ps-card">
			<div class="ps-card-header">
				<h2 class="ps-card-title">
					<span class="material-symbols-outlined">settings_input_component</span>
					Moduulien Asetukset
				</h2>
				<p class="ps-card-desc">Hallitse ulkoisia API-avaimia ja palveluita, kuten Cloudflare Turnstile ja Google reCAPTCHA.</p>
			</div>
			<div class="ps-card-body">
				
				<div class="integration-module" style="background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 8px; padding: 20px; margin-bottom: 20px;">
					<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
						<div>
							<h3 style="margin: 0; font-size: 16px; font-weight: 600; color: #111;">Cloudflare Turnstile</h3>
							<p style="margin: 4px 0 0; font-size: 13px; color: #6b7280;">Näkymätön bottisuojaus lomakkeisiin ja kirjautumiseen.</p>
						</div>
						<label class="ps-switch">
							<input type="checkbox" id="turnstile_enabled">
							<span class="ps-slider"></span>
						</label>
					</div>
					
					<div class="ps-form-group">
						<label>Site Key</label>
						<input type="text" id="turnstile_site_key" class="ps-input" placeholder="0x4AAAAAA..." style="width: 100%; max-width: 400px; padding: 8px; border: 1px solid #d1d5db; border-radius: 4px;">
					</div>
					<div class="ps-form-group" style="margin-top: 10px;">
						<label>Secret Key</label>
						<input type="password" id="turnstile_secret_key" class="ps-input" placeholder="0x4AAAAAA..." style="width: 100%; max-width: 400px; padding: 8px; border: 1px solid #d1d5db; border-radius: 4px;">
					</div>
				</div>

				<div class="integration-module" style="background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 8px; padding: 20px; margin-bottom: 20px;">
					<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
						<div>
							<h3 style="margin: 0; font-size: 16px; font-weight: 600; color: #111;">Google reCAPTCHA v3</h3>
							<p style="margin: 4px 0 0; font-size: 13px; color: #6b7280;">Googlen taustalla toimiva bottisuojaus.</p>
						</div>
						<label class="ps-switch">
							<input type="checkbox" id="recaptcha_enabled">
							<span class="ps-slider"></span>
						</label>
					</div>
					
					<div class="ps-form-group">
						<label>Site Key</label>
						<input type="text" id="recaptcha_site_key" class="ps-input" placeholder="Syötä Site Key..." style="width: 100%; max-width: 400px; padding: 8px; border: 1px solid #d1d5db; border-radius: 4px;">
					</div>
					<div class="ps-form-group" style="margin-top: 10px;">
						<label>Secret Key</label>
						<input type="password" id="recaptcha_secret_key" class="ps-input" placeholder="Syötä Secret Key..." style="width: 100%; max-width: 400px; padding: 8px; border: 1px solid #d1d5db; border-radius: 4px;">
					</div>
				</div>

				<button type="button" class="ps-btn ps-btn-primary" onclick="saveIntegrationSettings()" style="background: #b80048; color: #fff; padding: 10px 20px; border: none; border-radius: 6px; cursor: pointer; font-weight: 500;">
					Tallenna Asetukset
				</button>
				<span id="integrations-save-status" style="margin-left: 15px; font-size: 13px; color: #059669; display: none;"></span>
			</div>
		</div>
	</div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
	// Hae asetukset kun sivu ladataan
	const formData = new FormData();
	formData.append('action', 'pmc_security_get_integrations');
	formData.append('nonce', window.pmcSecurityConfig ? window.pmcSecurityConfig.nonce : '');

	fetch(ajaxurl, {
		method: 'POST',
		body: formData
	})
	.then(res => res.json())
	.then(res => {
		if (res.success && res.data) {
			const d = res.data;
			if (d.turnstile) {
				document.getElementById('turnstile_enabled').checked = d.turnstile.enabled || false;
				document.getElementById('turnstile_site_key').value = d.turnstile.site_key || '';
				document.getElementById('turnstile_secret_key').value = d.turnstile.secret_key || '';
			}
			if (d.recaptcha) {
				document.getElementById('recaptcha_enabled').checked = d.recaptcha.enabled || false;
				document.getElementById('recaptcha_site_key').value = d.recaptcha.site_key || '';
				document.getElementById('recaptcha_secret_key').value = d.recaptcha.secret_key || '';
			}
		}
	})
	.catch(err => console.error(err));
});

function saveIntegrationSettings() {
	const btn = document.querySelector('#ps-module-integrations .ps-btn-primary');
	const status = document.getElementById('integrations-save-status');
	
	btn.textContent = 'Tallennetaan...';
	btn.disabled = true;

	const settings = {
		turnstile: {
			enabled: document.getElementById('turnstile_enabled').checked,
			site_key: document.getElementById('turnstile_site_key').value,
			secret_key: document.getElementById('turnstile_secret_key').value
		},
		recaptcha: {
			enabled: document.getElementById('recaptcha_enabled').checked,
			site_key: document.getElementById('recaptcha_site_key').value,
			secret_key: document.getElementById('recaptcha_secret_key').value
		}
	};

	const formData = new FormData();
	formData.append('action', 'pmc_security_save_integrations');
	formData.append('nonce', window.pmcSecurityConfig ? window.pmcSecurityConfig.nonce : '');
	formData.append('settings', JSON.stringify(settings));

	fetch(ajaxurl, {
		method: 'POST',
		body: formData
	})
	.then(res => res.json())
	.then(res => {
		btn.textContent = 'Tallenna Asetukset';
		btn.disabled = false;
		if (res.success) {
			status.textContent = 'Tallennus onnistui!';
			status.style.color = '#059669';
			status.style.display = 'inline';
			setTimeout(() => { status.style.display = 'none'; }, 3000);
		} else {
			status.textContent = 'Virhe: ' + (res.data || 'Tuntematon virhe');
			status.style.color = '#dc2626';
			status.style.display = 'inline';
		}
	})
	.catch(err => {
		btn.textContent = 'Tallenna Asetukset';
		btn.disabled = false;
		status.textContent = 'Verkkovirhe!';
		status.style.color = '#dc2626';
		status.style.display = 'inline';
	});
}
</script>
