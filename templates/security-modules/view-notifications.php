<?php
/**
 * Security Module: Notifications
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$notifs = get_option( 'pmc_notification_settings', array() );
$subscribers = isset( $notifs['subscribers'] ) ? (array) $notifs['subscribers'] : array();

$available_events = array(
	'firewall'      => 'Palomuurin lukitukset',
	'malware'       => 'Haittaohjelmien hälytykset',
	'core_update'   => 'WordPress Core -päivitykset',
	'plugin_update' => 'Lisäosien päivitykset',
	'theme_update'  => 'Teemojen päivitykset',
	'new_user'      => 'Uusi käyttäjä rekisteröitynyt',
	'admin_login'   => 'Uusi kirjautuminen ylläpitäjänä'
);
?>
<div class="ps-module" id="ps-module-notifications" style="display: none;">
	<div class="ps-module-header">
		<h2><span class="material-symbols-outlined">notifications</span> Ilmoitukset ja asetukset</h2>
		<button class="ps-module-close material-symbols-outlined">close</button>
	</div>
	<div class="ps-module-content">
		
		<div class="ps-info-banner" style="background: #eff6ff; border: 1px solid #bfdbfe; border-left: 4px solid #3b82f6; padding: 16px; border-radius: 6px; margin-bottom: 20px; display: flex; gap: 12px; align-items: start;">
			<span class="material-symbols-outlined" style="color: #3b82f6; font-size: 24px;">info</span>
			<div>
				<h4 style="margin: 0 0 4px 0; color: #1e3a8a; font-size: 15px;">Enterprise-tason ilmoitusjärjestelmä</h4>
				<p style="margin: 0; color: #3b82f6; font-size: 13px; line-height: 1.5;">Voit lisätä useita vastaanottajia ja valita tarkalleen, mitkä tapahtumat laukaisevat sähköpostihälytyksen kullekin henkilölle.</p>
			</div>
		</div>
		
		<div class="ps-card">
			<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
				<div>
					<h3>Vastaanottajat</h3>
					<p class="ps-desc">Määritä, kuka saa ja mitä hälytyksiä.</p>
				</div>
				<button class="ps-btn ps-btn-secondary" onclick="pmcAddSubscriber()" style="display: flex; align-items: center; gap: 6px;">
					<span class="material-symbols-outlined" style="font-size:18px;">add</span> Lisää vastaanottaja
				</button>
			</div>
			
			<div id="ps-subscribers-list" style="display: flex; flex-direction: column; gap: 20px;">
				<?php if ( empty( $subscribers ) ) : ?>
					<p class="ps-no-subscribers" style="color: #64748b; font-style: italic;">Ei vastaanottajia. Klikkaa "Lisää vastaanottaja" aloittaaksesi.</p>
				<?php else: ?>
					<?php foreach ( $subscribers as $index => $sub ) : ?>
						<div class="ps-subscriber-card" style="border: 1px solid #e2e8f0; border-radius: 8px; padding: 20px; background: #f8fafc; position: relative;">
							<button onclick="this.closest('.ps-subscriber-card').remove()" class="material-symbols-outlined" style="position: absolute; top: 12px; right: 12px; background: none; border: none; cursor: pointer; color: #ef4444; font-size: 20px;" title="Poista">delete</button>
							
							<div class="ps-form-group" style="margin-bottom: 16px; max-width: 400px;">
								<label style="font-weight: 600; color: #334155;">Sähköpostiosoite</label>
								<input type="email" class="sub-email" value="<?php echo esc_attr( $sub['email'] ); ?>" placeholder="esim. jouni@example.com" style="width: 100%; padding: 10px 12px; border: 1px solid #cbd5e1; border-radius: 6px; margin-top: 6px;">
							</div>
							
							<label style="font-weight: 600; color: #334155; display: block; margin-bottom: 10px;">Tilattavat ilmoitukset</label>
							<div class="ps-events-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 12px;">
								<?php foreach ( $available_events as $event_id => $event_label ) : ?>
									<?php $is_checked = in_array( $event_id, (array) $sub['events'], true ); ?>
									<div class="ps-control-row" style="margin:0; padding:0; justify-content: flex-start; gap: 10px; min-height: 24px;">
										<label class="ps-switch">
											<input type="checkbox" class="sub-event" value="<?php echo esc_attr( $event_id ); ?>" <?php checked( $is_checked ); ?>>
											<span class="ps-slider"></span>
										</label>
										<span style="font-size: 14px; color: #475569;"><?php echo esc_html( $event_label ); ?></span>
									</div>
								<?php endforeach; ?>
							</div>
						</div>
					<?php endforeach; ?>
				<?php endif; ?>
			</div>
			
			<div class="mt-4" style="display: flex; justify-content: space-between; align-items: center; border-top: 1px solid #f1f5f9; padding-top: 20px; margin-top: 20px;">
				<button class="ps-btn ps-btn-secondary" onclick="pmcSendTestNotifications(this)" style="display: flex; align-items: center; gap: 6px;">
					<span class="material-symbols-outlined" style="font-size:18px;">send</span> Lähetä testisähköpostit
				</button>
				<button class="ps-btn ps-btn-primary" onclick="pmcSaveSubscribers(this)">Tallenna ilmoitukset</button>
			</div>
		</div>

		<div class="ps-card mt-4">
			<h3>Webhooks (Discord/Slack)</h3>
			<p class="ps-desc">Lähetä ilmoitukset suoraan tiimisi viestintäkanavalle webhookin kautta.</p>
			<div class="ps-form-group" style="margin-bottom: 16px; max-width: 500px;">
				<label style="font-weight: 600; color: #334155;">Webhook URL</label>
				<input type="url" id="notif-webhook-url" placeholder="https://discord.com/api/webhooks/..." style="width: 100%; padding: 10px 12px; border: 1px solid #cbd5e1; border-radius: 6px; margin-top: 6px;">
			</div>
			<button class="ps-btn ps-btn-primary" onclick="window.pmcSaveWebhookSettings(this)">Tallenna Webhook</button>
		</div>

	</div>
</div>

<!-- Template for new subscriber -->
<template id="ps-subscriber-template">
	<div class="ps-subscriber-card" style="border: 1px solid #e2e8f0; border-radius: 8px; padding: 20px; background: #f8fafc; position: relative;">
		<button onclick="this.closest('.ps-subscriber-card').remove(); document.querySelector('.ps-no-subscribers')?.remove();" class="material-symbols-outlined" style="position: absolute; top: 12px; right: 12px; background: none; border: none; cursor: pointer; color: #ef4444; font-size: 20px;" title="Poista">delete</button>
		
		<div class="ps-form-group" style="margin-bottom: 16px; max-width: 400px;">
			<label style="font-weight: 600; color: #334155;">Sähköpostiosoite</label>
			<input type="email" class="sub-email" placeholder="esim. jouni@example.com" style="width: 100%; padding: 10px 12px; border: 1px solid #cbd5e1; border-radius: 6px; margin-top: 6px;">
		</div>
		
		<label style="font-weight: 600; color: #334155; display: block; margin-bottom: 10px;">Tilattavat ilmoitukset</label>
		<div class="ps-events-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 12px;">
			<?php foreach ( $available_events as $event_id => $event_label ) : ?>
				<div class="ps-control-row" style="margin:0; padding:0; justify-content: flex-start; gap: 10px; min-height: 24px;">
					<label class="ps-switch">
						<input type="checkbox" class="sub-event" value="<?php echo esc_attr( $event_id ); ?>">
						<span class="ps-slider"></span>
					</label>
					<span style="font-size: 14px; color: #475569;"><?php echo esc_html( $event_label ); ?></span>
				</div>
			<?php endforeach; ?>
		</div>
	</div>
</template>
