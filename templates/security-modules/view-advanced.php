<?php
/**
 * Security Module: Advanced
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="ps-module" id="ps-module-advanced" style="display: none;">
	<div class="ps-module-header">
		<h2><span class="material-symbols-outlined">settings_suggest</span> Edistyneet työkalut</h2>
		<button class="ps-module-close material-symbols-outlined">close</button>
	</div>
	<div class="ps-module-content">
		
		<div class="ps-info-banner" style="background: #eff6ff; border: 1px solid #bfdbfe; border-left: 4px solid #3b82f6; padding: 16px; border-radius: 6px; margin-bottom: 20px; display: flex; gap: 12px; align-items: start;">
			<span class="material-symbols-outlined" style="color: #3b82f6; font-size: 24px;">info</span>
			<div>
				<h4 style="margin: 0 0 4px 0; color: #1e3a8a; font-size: 15px;">Miten tämä moduuli suojaa sivustoasi</h4>
				<p style="margin: 0; color: #3b82f6; font-size: 13px; line-height: 1.5;">Edistynyt todennus suojaa kirjautumissivuasi piilottamalla sen automaattisilta hyökkäyksiltä ja mahdollistamalla kaksivaiheisen tunnistautumisen (2FA) pakottamisen, mikä tekee hakkereiden sisäänpääsyn arvaamisesta lähes mahdotonta.</p>
			</div>
		</div>
		
		<style>
		.custom-mask-card { padding: 24px; }
		.mask-header-row { display: flex; justify-content: space-between; align-items: flex-start; }
		.mask-header-text h3 { margin: 0 0 4px 0 !important; font-size: 18px !important; color: #0f172a !important; font-weight: 600; }
		.mask-header-text p { margin: 0; color: #64748b; font-size: 14px; }
		.mask-toggle-container { background: #f8fafc; border: 1px solid #f1f5f9; border-radius: 8px; padding: 8px 12px 8px 16px; display: flex; align-items: center; gap: 12px; color: #475569; font-size: 14px; font-weight: 500; }
		.mask-divider { border: none; border-top: 1px solid #f1f5f9; margin: 24px -24px; }
		.mask-inputs-row { display: flex; gap: 24px; flex-wrap: wrap; }
		.mask-input-col { flex: 1; min-width: 280px; }
		.mask-input-col > label { display: block; font-weight: 600; color: #334155; font-size: 14px; margin-bottom: 8px; }
		.input-with-prefix { display: flex; align-items: stretch; border: 1px solid #cbd5e1; border-radius: 6px; overflow: hidden; background: #fff; transition: border-color 0.2s; }
		.input-with-prefix:focus-within { border-color: #b80048; }
		.url-prefix { background: #f8fafc; padding: 0 12px; color: #94a3b8; font-size: 14px; border-right: 1px solid #cbd5e1; display: flex; align-items: center; }
		.input-with-prefix input { border: none !important; border-radius: 0 !important; flex: 1; outline: none; padding: 10px 12px; font-size: 14px; color: #0f172a; width: 100%; box-shadow: none !important; }
		.mask-input-col > input { width: 100%; padding: 10px 12px; font-size: 14px; border: 1px solid #cbd5e1; border-radius: 6px; color: #0f172a; transition: border-color 0.2s; box-shadow: none !important; }
		.mask-input-col > input:focus { border-color: #b80048; outline: none; }
		.mask-hint { font-size: 12px; color: #94a3b8; margin: 8px 0 0 0; font-style: italic; }
		.mask-footer-row { display: flex; justify-content: flex-end; }
		.ps-settings-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 24px; margin-top: 24px; }
		</style>
		
		<div class="ps-card custom-mask-card">
			<!-- Top section -->
			<div class="mask-header-row">
				<div class="mask-header-text">
					<h3>Piilota kirjautumisalue</h3>
					<p>Piilota kirjautumis-URL-osoitteesi boteilta.</p>
				</div>
				<div class="mask-header-toggle">
					<label class="mask-toggle-container">
						<span>Ota kirjautumisen piilotus käyttöön</span>
						<label class="ps-switch" style="margin:0;">
							<input type="checkbox" id="adv-mask-toggle">
							<span class="ps-slider"></span>
						</label>
					</label>
				</div>
			</div>

			<hr class="mask-divider">

			<!-- Middle section -->
			<div class="mask-inputs-row">
				<div class="mask-input-col">
					<label>Uusi kirjautumis-URL</label>
					<div class="input-with-prefix">
						<span class="url-prefix"><?php echo esc_html( str_replace( array('http://', 'https://'), '', home_url('/') ) ); ?></span>
						<input type="text" id="adv-mask-url" value="secret-login">
					</div>
					<p class="mask-hint">Kirjoita haluamasi uusi polku tähän.</p>
				</div>
				
				<div class="mask-input-col">
					<label>Ohjaa luvaton liikenne osoitteeseen</label>
					<input type="text" id="adv-mask-redirect" value="/404">
					<p class="mask-hint">Minne wp-admin-sivulle pyrkivät vierailijat tulisi ohjata? Oletus on /404</p>
				</div>
			</div>

			<hr class="mask-divider">

			<!-- Bottom section -->
			<div class="mask-footer-row">
				<button class="ps-btn ps-btn-primary" onclick="pmcSaveAdvancedSettings(this)">Tallenna asetukset</button>
			</div>
		</div>

		<div class="ps-settings-grid">
			<div class="ps-card">
				<h3>Kaksivaiheinen tunnistautuminen (2FA)</h3>
				<p class="ps-desc">Lisää ylimääräinen suojakerros WordPress-sivustollesi.</p>
				<div class="ps-control-row" style="margin-bottom:0;">
					<label class="ps-switch">
						<input type="checkbox" id="adv-2fa-toggle">
						<span class="ps-slider"></span>
					</label>
					<span>Ota 2FA käyttöön</span>
				</div>
			</div>

			<div class="ps-card">
				<h3>Vahvat salasanat</h3>
				<p class="ps-desc">Pakota käyttäjät käyttämään vahvoja salasanoja.</p>
				<div class="ps-control-row" style="margin-bottom:0;">
					<label class="ps-switch">
						<input type="checkbox" id="adv-strongpw-toggle">
						<span class="ps-slider"></span>
					</label>
					<span>Ota vahvat salasanat käyttöön</span>
				</div>
			</div>

			<div class="ps-card">
				<h3>Istuntojen suojaus</h3>
				<p class="ps-desc">Estä istuntojen kaappaus.</p>
				<div class="ps-control-row" style="margin-bottom:0;">
					<label class="ps-switch">
						<input type="checkbox" id="adv-session-toggle">
						<span class="ps-slider"></span>
					</label>
					<span>Ota istuntojen suojaus käyttöön</span>
				</div>
			</div>
		</div>

	</div>
</div>
