<?php
/**
 * Security Module: Hardening
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="ps-module" id="ps-module-hardening" style="display: none;">
	<div class="ps-module-header">
		<h2><span class="material-symbols-outlined">health_and_safety</span> Tietoturvan vahvistaminen</h2>
		<button class="ps-module-close material-symbols-outlined">close</button>
	</div>
	<div class="ps-module-content" style="padding: 20px; flex: 1; display: flex; flex-direction: column; min-height: 0;">
		
		<div class="ps-info-banner" style="background: #eff6ff; border: 1px solid #bfdbfe; border-left: 4px solid #3b82f6; padding: 16px; border-radius: 6px; margin-bottom: 20px; display: flex; gap: 12px; align-items: start;">
			<span class="material-symbols-outlined" style="color: #3b82f6; font-size: 24px;">info</span>
			<div>
				<h4 style="margin: 0 0 4px 0; color: #1e3a8a; font-size: 15px;">Miten tämä moduuli suojaa sivustoasi</h4>
				<p style="margin: 0; color: #3b82f6; font-size: 13px; line-height: 1.5;">Järjestelmän vahvistaminen lukitsee WordPressin herkät osat poistamalla käytöstä käyttämättömät ominaisuudet (kuten XML-RPC) ja rajoittamalla tiedostojen käyttöoikeuksia. Tämä pienentää 'hyökkäyspintaa', jotta hakkereilla on vähemmän sisäänpääsypisteitä.</p>
			</div>
		</div>

		<!-- Sidenav Layout -->
		<div style="display: flex; flex: 1; background: #ffffff; min-height: 0;">
			
			<!-- Left Sidenav -->
			<div style="width: 250px; background: #f9fafb; border-right: 1px solid #e5e7eb; padding: 20px; overflow-y: auto;">
				<ul style="list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: 8px;">
					<li>
						<a href="#" class="ps-sidenav-tab active" data-target="#tab-tweak-recommendations" style="display: flex; justify-content: space-between; align-items: center; padding: 10px 15px; color: #4b5563; text-decoration: none; border-radius: 6px;">
							Suositukset 
							<span id="badge-recommendations" style="background: #b80048; color: white; border-radius: 12px; padding: 2px 8px; font-size: 11px; font-weight: bold;">0</span>
						</a>
					</li>
					<li>
						<a href="#" class="ps-sidenav-tab" data-target="#tab-tweak-actioned" style="display: flex; justify-content: space-between; align-items: center; padding: 10px 15px; color: #4b5563; text-decoration: none; border-radius: 6px;">
							Suoritetut 
							<span id="badge-actioned" style="background: #e5e7eb; color: #4b5563; border-radius: 12px; padding: 2px 8px; font-size: 11px; font-weight: bold;">0</span>
						</a>
					</li>
				</ul>
			</div>

			<!-- Right Content Area -->
			<div style="flex: 1; padding: 30px; overflow-y: auto;">
				
				<!-- Recommendations Tab -->
				<div id="tab-tweak-recommendations" class="ps-tweak-tab-content" style="display: block;">
					<div class="ps-card" style="margin-bottom: 20px; border: 1px solid #e5e7eb; background: #fff; border-radius: 8px; padding: 20px;">
						<h3 style="margin: 0; font-size: 18px; display: flex; align-items: center; gap: 10px; color: #111827;">
							Suositukset
							<span id="badge-recommendations-inner" style="background: #b80048; color: white; border-radius: 12px; padding: 2px 8px; font-size: 11px; font-weight: bold;">0</span>
						</h3>
						<p style="color: #6b7280; font-size: 14px; margin-top: 10px; margin-bottom: 0;">Nämä ovat yleisiä tietoturvaparannuksia, joita voit tehdä parantaaksesi sivustosi puolustusta hakkereita ja botteja vastaan.</p>
					</div>

					<!-- Accordion Container -->
					<div id="accordion-recommendations" class="ps-accordion-container" style="border: 1px solid #e5e7eb; border-radius: 6px; overflow: hidden; background: #fff;">
						<!-- Dynamically injected -->
					</div>
				</div>

				<!-- Actioned Tab -->
				<div id="tab-tweak-actioned" class="ps-tweak-tab-content" style="display: none;">
					<div class="ps-card" style="margin-bottom: 20px; border: 1px solid #e5e7eb; background: #fff; border-radius: 8px; padding: 20px;">
						<h3 style="margin: 0; font-size: 18px; display: flex; align-items: center; gap: 10px; color: #111827;">
							Suoritetut
							<span id="badge-actioned-inner" style="background: #10b981; color: white; border-radius: 12px; padding: 2px 8px; font-size: 11px; font-weight: bold;">0</span>
						</h3>
						<p style="color: #6b7280; font-size: 14px; margin-top: 10px; margin-bottom: 0;">Hienoa työtä! Nämä haavoittuvuudet on korjattu.</p>
					</div>

					<!-- Accordion Container -->
					<div id="accordion-actioned" class="ps-accordion-container" style="border: 1px solid #e5e7eb; border-radius: 6px; overflow: hidden; background: #fff;">
						<!-- Dynamically injected -->
					</div>
				</div>

			</div>
		</div>

	</div>
</div>
