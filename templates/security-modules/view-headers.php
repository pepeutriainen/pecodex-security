<?php
/**
 * Security Module: Security Headers
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="ps-module" id="ps-module-headers" style="display: none;">
	<div class="ps-module-header">
		<h2><span class="material-symbols-outlined">http</span> Suojausotsikot</h2>
		<button class="ps-module-close material-symbols-outlined">close</button>
	</div>
	<div class="ps-module-content">
		
		<div class="ps-info-banner" style="background: #eff6ff; border: 1px solid #bfdbfe; border-left: 4px solid #3b82f6; padding: 16px; border-radius: 6px; margin-bottom: 20px; display: flex; gap: 12px; align-items: start;">
			<span class="material-symbols-outlined" style="color: #3b82f6; font-size: 24px;">info</span>
			<div>
				<h4 style="margin: 0 0 4px 0; color: #1e3a8a; font-size: 15px;">Miten tämä moduuli suojaa sivustoasi</h4>
				<p style="margin: 0; color: #3b82f6; font-size: 13px; line-height: 1.5;">Suojausotsikot ovat vierailijan selaimelle lähetettäviä ohjeita, jotka estävät yleisiä hyökkäyksiä kuten Cross-Site Scripting (XSS), clickjacking ja sisällön nuuskiminen. Ne varmistavat, että selaimet ovat vuorovaikutuksessa sivustosi kanssa turvallisesti.</p>
			</div>
		</div>

		<style>
		.ps-settings-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 24px; margin-top: 24px; }
		</style>
		
		<div class="ps-settings-grid" id="security-headers-grid">
			<!-- Headers loaded dynamically via JS -->
			<div class="ps-card"><p>Ladataan otsikoita...</p></div>
		</div>

	</div>
</div>
