<?php
/**
 * Security Module: Malware Scanner
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $wpdb;
$table_name = $wpdb->prefix . 'pmc_scan_item';
$scan_items = [];
if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name) {
	$scan_items = $wpdb->get_results("SELECT * FROM $table_name ORDER BY id DESC LIMIT 50");
}
?>
<div class="ps-module" id="ps-module-scanner" style="display: none;">
	<div class="ps-module-header">
		<h2><span class="material-symbols-outlined">search_check</span> Haittaohjelmaskanneri</h2>
		<button class="ps-module-close material-symbols-outlined">close</button>
	</div>
	<div class="ps-module-content">
		
		<div class="ps-info-banner" style="background: #eff6ff; border: 1px solid #bfdbfe; border-left: 4px solid #3b82f6; padding: 16px; border-radius: 6px; margin-bottom: 20px; display: flex; gap: 12px; align-items: start;">
			<span class="material-symbols-outlined" style="color: #3b82f6; font-size: 24px;">info</span>
			<div>
				<h4 style="margin: 0 0 4px 0; color: #1e3a8a; font-size: 15px;">Miten tämä moduuli suojaa sivustoasi</h4>
				<p style="margin: 0; color: #3b82f6; font-size: 13px; line-height: 1.5;">Haittaohjelmaskanneri toimii sivustosi virustorjuntana. Se varmistaa, että WordPressin ydintiedostoja ei ole peukaloitu, ja tarkistaa kaikki lisäosat ja teemat tunnettujen haittaohjelma-allekirjoitusten tai takaovien varalta.</p>
			</div>
		</div>
		
		<div class="ps-card">
			<h3>Järjestelmän skannaus</h3>
			<p class="ps-desc">Skannaa WordPressin ydintiedostot, lisäosat ja teemat muutosten tai tunnettujen haittaohjelma-allekirjoitusten varalta.</p>
			<div class="mt-4" style="display: flex; align-items: center; gap: 12px;">
				<button class="ps-btn ps-btn-primary" id="scan-now-btn" onclick="window.pmcRunScan(this)">Suorita perusteellinen skannaus</button>
				<div id="scan-progress-wrap" style="display: none; flex-grow: 1; align-items: center; gap: 10px;">
					<div style="flex-grow: 1; background: #e2e8f0; height: 8px; border-radius: 4px; overflow: hidden;">
						<div id="scan-progress-bar" style="width: 0%; height: 100%; background: #3b82f6; transition: width 0.2s;"></div>
					</div>
					<span id="scan-status-text" style="font-size: 13px; font-weight: 600; color: #475569;">Alustetaan...</span>
				</div>
			</div>
		</div>

		<div class="ps-card mt-4">
			<h3>Skannauksen löydökset (enintään 50)</h3>
			<div class="ps-table-wrapper">
				<table class="ps-table" id="scan-results-table">
					<thead>
						<tr>
							<th>Tiedostopolku</th>
							<th>Tyyppi</th>
							<th>Toiminto</th>
						</tr>
					</thead>
					<tbody>
						<?php if (empty($scan_items)): ?>
							<tr><td colspan="3">Epäilyttäviä tiedostoja ei löytynyt. Järjestelmä on puhdas!</td></tr>
						<?php else: ?>
							<?php foreach ($scan_items as $item): ?>
								<tr id="scan-item-<?php echo esc_attr($item->id); ?>">
									<td style="word-break: break-all; font-family: monospace; font-size: 12px;"><?php echo esc_html($item->file_path); ?></td>
									<td>
										<?php if ($item->type === 'modified_core'): ?>
											<span style="background: #fef08a; color: #854d0e; padding: 2px 6px; border-radius: 4px; font-size: 12px; font-weight: 700;">Muokattu ydin</span>
										<?php elseif ($item->type === 'malware'): ?>
											<span style="background: #fecaca; color: #991b1b; padding: 2px 6px; border-radius: 4px; font-size: 12px; font-weight: 700;">Haittaohj. allekirj.</span>
										<?php else: ?>
											<span style="background: #e2e8f0; color: #475569; padding: 2px 6px; border-radius: 4px; font-size: 12px; font-weight: 700;"><?php echo esc_html($item->type); ?></span>
										<?php endif; ?>
									</td>
									<td style="display: flex; gap: 4px;">
										<?php if ($item->type !== 'quarantined'): ?>
											<button class="ps-btn" style="padding: 4px 8px; font-size: 12px; background: #fbbf24; color: #fff; border: none; cursor: pointer; border-radius: 4px;" onclick="window.pmcScanAction(<?php echo $item->id; ?>, 'quarantine')">Karanteeniin</button>
										<?php endif; ?>
										<button class="ps-btn" style="padding: 4px 8px; font-size: 12px; background: #ef4444; color: #fff; border: none; cursor: pointer; border-radius: 4px;" onclick="window.pmcScanAction(<?php echo $item->id; ?>, 'delete')">Poista</button>
										<button class="ps-btn" style="padding: 4px 8px; font-size: 12px; background: #94a3b8; color: #fff; border: none; cursor: pointer; border-radius: 4px;" onclick="window.pmcScanAction(<?php echo $item->id; ?>, 'ignore')">Ohita</button>
									</td>
								</tr>
							<?php endforeach; ?>
						<?php endif; ?>
					</tbody>
				</table>
			</div>
		</div>

	</div>
</div>

<script>
window.pmcRunScan = async function(btn) {
	btn.disabled = true;
	const progressWrap = document.getElementById('scan-progress-wrap');
	const progressBar = document.getElementById('scan-progress-bar');
	const statusText = document.getElementById('scan-status-text');
	
	btn.style.display = 'none';
	progressWrap.style.display = 'flex';
	statusText.innerText = 'Haetaan ytimen tarkistussummia ja kartoitetaan tiedostoja...';
	progressBar.style.width = '5%';

	// Start scan
	const startRes = await pmcSec.post('pmc_deep_scan_start', {});
	if (!startRes.success) {
		alert('Skannauksen aloitus epäonnistui.');
		btn.style.display = 'inline-block';
		progressWrap.style.display = 'none';
		btn.disabled = false;
		return;
	}

	let total = startRes.data.total;
	let remaining = total;
	
	while (remaining > 0) {
		let percent = Math.round(((total - remaining) / total) * 100);
		progressBar.style.width = Math.max(5, percent) + '%';
		statusText.innerText = `Skannataan... ${total - remaining} / ${total} tiedostoa`;
		
		const stepRes = await pmcSec.post('pmc_deep_scan_step', {});
		if (!stepRes.success) {
			alert('Skannaus keskeytyi virheen vuoksi.');
			break;
		}
		remaining = stepRes.data.remaining;
		
		if (stepRes.data.status === 'complete' || remaining <= 0) {
			break;
		}
	}
	
	progressBar.style.width = '100%';
	statusText.innerText = 'Skannaus valmis! Ladataan löydökset uudelleen...';
	
	setTimeout(() => {
		location.reload();
	}, 1000);
};

window.pmcScanAction = async function(id, action) {
	if (action === 'delete') {
		if (!confirm('Haluatko varmasti POISTAA TÄMÄN TIEDOSTON PYSYVÄSTI? Tätä ei voi peruuttaa ja se voi rikkoa sivustosi.')) return;
	} else if (action === 'quarantine') {
		if (!confirm('Haluatko varmasti asettaa tämän tiedoston karanteeniin? Se nimetään uudelleen ja siirretään, mikä voi rikkoa toiminnallisuuden.')) return;
	}

	const res = await pmcSec.post('pmc_deep_scan_action', { item_id: id, scan_action: action });
	if (res.success) {
		const row = document.getElementById('scan-item-' + id);
		if (row) {
			row.style.opacity = '0.5';
			row.style.pointerEvents = 'none';
			row.querySelector('td:last-child').innerHTML = `<span style="color: #10b981; font-weight: bold;">${res.data}</span>`;
		}
	} else {
		alert('Toiminto epäonnistui: ' + (res.data || 'Tuntematon virhe'));
	}
};
</script>
