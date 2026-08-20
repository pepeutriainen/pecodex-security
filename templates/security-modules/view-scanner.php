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
$scan_tab = isset($_GET['scan_tab']) ? sanitize_text_field($_GET['scan_tab']) : 'active';
$paged = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
$per_page = 30;
$offset = ($paged - 1) * $per_page;

if ($scan_tab === 'quarantined') {
	$where = "type = 'quarantined'";
} elseif ($scan_tab === 'ignored') {
	$where = "type = 'ignored'";
} else {
	$where = "type != 'quarantined' AND type != 'ignored'";
}

$total_items = 0;
if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name) {
	$total_items = (int) $wpdb->get_var("SELECT COUNT(id) FROM $table_name WHERE $where");
	$scan_items = $wpdb->get_results("SELECT * FROM $table_name WHERE $where ORDER BY id DESC LIMIT $per_page OFFSET $offset");
}
$total_pages = ceil($total_items / $per_page);

$count_active = 0;
$count_quarantined = 0;
$count_ignored = 0;
if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name) {
	$count_active = (int) $wpdb->get_var("SELECT COUNT(id) FROM $table_name WHERE type != 'quarantined' AND type != 'ignored'");
	$count_quarantined = (int) $wpdb->get_var("SELECT COUNT(id) FROM $table_name WHERE type = 'quarantined'");
	$count_ignored = (int) $wpdb->get_var("SELECT COUNT(id) FROM $table_name WHERE type = 'ignored'");
}
?>
<div class="ps-module" id="ps-module-scanner" style="display: none;">
	<div class="ps-module-header">
		<h2><span class="material-symbols-outlined">search_check</span> Haittaohjelmaskanneri</h2>
		<button class="ps-module-close material-symbols-outlined">close</button>
	</div>
	<div class="ps-module-content">
		
		<div class="ps-info-banner" style="background: #fff1f2; border: 1px solid #fecdd3; border-left: 4px solid #be123c; padding: 16px; border-radius: 6px; margin-bottom: 20px; display: flex; gap: 12px; align-items: start;">
			<span class="material-symbols-outlined" style="color: #be123c; font-size: 24px;">info</span>
			<div>
				<h4 style="margin: 0 0 4px 0; color: #881337; font-size: 15px;">Miten tämä moduuli suojaa sivustoasi</h4>
				<p style="margin: 0; color: #be123c; font-size: 13px; line-height: 1.5;">Haittaohjelmaskanneri toimii sivustosi virustorjuntana. Se varmistaa, että WordPressin ydintiedostoja ei ole peukaloitu, ja tarkistaa kaikki lisäosat ja teemat tunnettujen haittaohjelma-allekirjoitusten tai takaovien varalta.</p>
			</div>
		</div>
		
		<div class="ps-card">
			<h3>Järjestelmän skannaus</h3>
			<p class="ps-desc">Skannaa WordPressin ydintiedostot, lisäosat ja teemat muutosten tai tunnettujen haittaohjelma-allekirjoitusten varalta.</p>
			<div class="mt-4" id="scan-action-area" style="display: flex; flex-direction: column; gap: 20px;">
				<div id="scan-progress-wrap" style="display: flex; flex-direction: column; gap: 8px;">
					<div style="display: flex; justify-content: space-between; align-items: center;">
						<span id="scan-status-title" style="font-size: 13px; font-weight: 600; color: #64748b; display: flex; align-items: center; gap: 6px;">
							<span id="scan-status-icon" class="material-symbols-outlined" style="font-size: 18px;">pending</span> 
							<span id="scan-status-title-text">Valmiina skannaukseen</span>
						</span>
						<span id="scan-status-text" style="font-size: 13px; font-weight: 500; color: #94a3b8;">Ei käynnissä</span>
					</div>
					<div style="width: 100%; background: #f1f5f9; height: 10px; border-radius: 99px; overflow: hidden; border: 1px solid #e2e8f0;">
						<div id="scan-progress-bar" style="width: 0%; height: 100%; background: #cbd5e1; transition: width 0.3s ease-out, background 0.3s; position: relative;">
							<div id="scan-progress-stripes" style="display: none; position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: linear-gradient(45deg, rgba(255,255,255,0.2) 25%, transparent 25%, transparent 50%, rgba(255,255,255,0.2) 50%, rgba(255,255,255,0.2) 75%, transparent 75%, transparent); background-size: 1rem 1rem; animation: ps-progress-stripes 1s linear infinite;"></div>
						</div>
					</div>
				</div>
				<div>
					<button class="ps-btn" id="scan-now-btn" onclick="window.pmcDeepScannerRun(this)" style="background: #be123c; color: #fff; border: none; padding: 10px 16px; font-weight: 600; font-size: 14px; border-radius: 6px; cursor: pointer; box-shadow: 0 1px 2px rgba(0,0,0,0.1); display: inline-flex; align-items: center; gap: 6px; transition: opacity 0.2s;">
						Suorita Haittaohjelmaskannaus Nyt
					</button>
				</div>
			</div>
		</div>

		<div class="ps-card mt-4">
			<h3>Skannauksen löydökset</h3>
			
			<div class="ps-tabs" style="display: flex; gap: 8px; margin-top: 16px; margin-bottom: 16px; border-bottom: 1px solid #e2e8f0; padding-bottom: 8px;">
				<a href="#" id="ps-tab-active" onclick="window.pmcLoadScannerResults('active', 1); return false;" style="display: flex; align-items: center; text-decoration: none; padding: 8px 16px; font-weight: 600; font-size: 14px; border-radius: 6px; <?php echo $scan_tab === 'active' ? 'background: #fff1f2; color: #be123c;' : 'color: #64748b;'; ?>" <?php if($scan_tab!=='active') echo 'onmouseover="this.style.background=\'#f1f5f9\'" onmouseout="this.style.background=\'transparent\'"'; ?>>
					Aktiiviset <span style="margin-left: 6px; padding: 2px 6px; border-radius: 99px; font-size: 11px; font-weight: 700; <?php echo $scan_tab === 'active' ? 'background: #fecdd3; color: #9f1239;' : 'background: #e2e8f0; color: #475569;'; ?>"><?php echo $count_active; ?></span>
				</a>
				<a href="#" id="ps-tab-quarantined" onclick="window.pmcLoadScannerResults('quarantined', 1); return false;" style="display: flex; align-items: center; text-decoration: none; padding: 8px 16px; font-weight: 600; font-size: 14px; border-radius: 6px; <?php echo $scan_tab === 'quarantined' ? 'background: #fff1f2; color: #be123c;' : 'color: #64748b;'; ?>" <?php if($scan_tab!=='quarantined') echo 'onmouseover="this.style.background=\'#f1f5f9\'" onmouseout="this.style.background=\'transparent\'"'; ?>>
					Karanteenissa <span style="margin-left: 6px; padding: 2px 6px; border-radius: 99px; font-size: 11px; font-weight: 700; <?php echo $scan_tab === 'quarantined' ? 'background: #fecdd3; color: #9f1239;' : 'background: #e2e8f0; color: #475569;'; ?>"><?php echo $count_quarantined; ?></span>
				</a>
				<a href="#" id="ps-tab-ignored" onclick="window.pmcLoadScannerResults('ignored', 1); return false;" style="display: flex; align-items: center; text-decoration: none; padding: 8px 16px; font-weight: 600; font-size: 14px; border-radius: 6px; <?php echo $scan_tab === 'ignored' ? 'background: #fff1f2; color: #be123c;' : 'color: #64748b;'; ?>" <?php if($scan_tab!=='ignored') echo 'onmouseover="this.style.background=\'#f1f5f9\'" onmouseout="this.style.background=\'transparent\'"'; ?>>
					Ohitetut <span style="margin-left: 6px; padding: 2px 6px; border-radius: 99px; font-size: 11px; font-weight: 700; <?php echo $scan_tab === 'ignored' ? 'background: #fecdd3; color: #9f1239;' : 'background: #e2e8f0; color: #475569;'; ?>"><?php echo $count_ignored; ?></span>
				</a>
			</div>
			
			<style>
				.ps-path-scroll::-webkit-scrollbar { display: none; }
				.ps-path-scroll { -ms-overflow-style: none; scrollbar-width: none; }
				.ps-table input[type="checkbox"],
				.ps-scan-cb, 
				#scan-select-all {
					accent-color: #be123c !important;
					width: 16px;
					height: 16px;
					cursor: pointer;
				}
				.ps-table input[type="checkbox"]:checked,
				.ps-scan-cb:checked,
				#scan-select-all:checked {
					background-color: #be123c !important;
					border-color: #be123c !important;
				}
				.ps-table input[type="checkbox"]:focus,
				.ps-scan-cb:focus,
				#scan-select-all:focus {
					border-color: #be123c !important;
					box-shadow: 0 0 0 1px #be123c !important;
				}
			</style>
			<div class="ps-table-wrapper">
				<div style="display: flex; gap: 8px; margin-bottom: 12px; align-items: center;">
					<select id="scan-bulk-action" style="padding: 6px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 13px; color: #334155; background: #fff; box-shadow: 0 1px 2px rgba(0,0,0,0.05);">
						<option value="">-- Valitse massatoiminto --</option>
						<option value="quarantine">Karanteeniin</option>
						<option value="ignore">Ohita</option>
						<option value="delete">Poista pysyvästi</option>
						<option value="restore">Palauta</option>
					</select>
					<button class="ps-btn" onclick="window.pmcBulkScanAction()" style="background: #f1f5f9; color: #475569; border: 1px solid #cbd5e1; padding: 6px 16px; font-weight: 500; font-size: 13px; border-radius: 6px; cursor: pointer; box-shadow: 0 1px 2px rgba(0,0,0,0.05); transition: background 0.2s;" onmouseover="this.style.background='#e2e8f0'" onmouseout="this.style.background='#f1f5f9'">Käytä</button>
				</div>
				<table class="ps-table" id="scan-results-table" style="table-layout: fixed; width: 100%;">
					<thead>
						<tr>
							<th style="width: 32px; padding: 12px 16px; text-align: center;"><input type="checkbox" id="scan-select-all" onclick="window.pmcSelectAll(this)" style="border-radius: 4px; cursor: pointer;"></th>
							<th style="width: 55%;">Tiedostopolku</th>
							<th style="width: 15%;">Tyyppi</th>
							<th style="width: 30%;">Toiminto</th>
						</tr>
					</thead>
					<tbody>
						<?php if (empty($scan_items)): ?>
							<tr><td colspan="4">Epäilyttäviä tiedostoja ei löytynyt. Järjestelmä on puhdas!</td></tr>
						<?php else: ?>
							<?php foreach ($scan_items as $item): ?>
								<tr id="scan-item-<?php echo esc_attr($item->id); ?>">
									<td style="width: 32px; padding: 12px 16px; text-align: center;">
										<input type="checkbox" class="ps-scan-cb" value="<?php echo esc_attr($item->id); ?>" style="border-radius: 4px; cursor: pointer;">
									</td>
									<td style="max-width: 0; padding-right: 16px;">
										<div style="display: flex; align-items: center; gap: 4px; background: #f8fafc; padding: 2px 4px; border-radius: 6px; border: 1px solid #e2e8f0;">
											<button type="button" title="Vieritä vasemmalle" onclick="this.nextElementSibling.scrollBy({left: -150, behavior: 'smooth'})" style="background: transparent; border: none; cursor: pointer; padding: 4px; display: flex; align-items: center; justify-content: center; color: #94a3b8; border-radius: 4px; transition: all 0.2s;" onmouseover="this.style.background='#e2e8f0'; this.style.color='#0f172a'" onmouseout="this.style.background='transparent'; this.style.color='#94a3b8'">
												<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
											</button>
											<div class="ps-path-scroll" style="flex: 1; font-family: monospace; font-size: 14px; line-height: 1.5; white-space: nowrap; overflow-x: auto; color: #334155; padding: 6px 4px;">
												<?php echo esc_html($item->file_path); ?>
											</div>
											<button type="button" title="Vieritä oikealle" onclick="this.previousElementSibling.scrollBy({left: 150, behavior: 'smooth'})" style="background: transparent; border: none; cursor: pointer; padding: 4px; display: flex; align-items: center; justify-content: center; color: #94a3b8; border-radius: 4px; transition: all 0.2s;" onmouseover="this.style.background='#e2e8f0'; this.style.color='#0f172a'" onmouseout="this.style.background='transparent'; this.style.color='#94a3b8'">
												<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
											</button>
										</div>
									</td>
									<td style="white-space: nowrap;">
										<?php if ($item->type === 'modified_core'): ?>
											<span style="background: #fef08a; color: #854d0e; padding: 2px 6px; border-radius: 4px; font-size: 12px; font-weight: 700;">Muokattu ydin</span>
										<?php elseif ($item->type === 'malware'): ?>
											<span style="background: #fecaca; color: #991b1b; padding: 2px 6px; border-radius: 4px; font-size: 12px; font-weight: 700;">Haittaohj. allekirj.</span>
										<?php else: ?>
											<span style="background: #e2e8f0; color: #475569; padding: 2px 6px; border-radius: 4px; font-size: 12px; font-weight: 700;"><?php echo esc_html($item->type); ?></span>
										<?php endif; ?>
									</td>
									<td style="display: flex; gap: 6px;">
										<?php if ($scan_tab === 'active'): ?>
											<button class="ps-btn ps-action-btn" style="padding: 6px 10px; font-size: 13px; font-weight: 500; background: #fff; color: #d97706; border: 1px solid #fcd34d; cursor: pointer; border-radius: 6px; display: flex; align-items: center; gap: 4px; box-shadow: 0 1px 2px rgba(0,0,0,0.05);" onclick="window.pmcScanAction(<?php echo $item->id; ?>, 'quarantine')" onmouseover="this.style.background='#fef3c7'" onmouseout="this.style.background='#fff'">
												<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/><path d="m3.3 7 8.7 5 8.7-5"/><path d="M12 22V12"/></svg>
												Karanteeniin
											</button>
											<button class="ps-btn ps-action-btn" style="padding: 6px 10px; font-size: 13px; font-weight: 500; background: #fff; color: #dc2626; border: 1px solid #fca5a5; cursor: pointer; border-radius: 6px; display: flex; align-items: center; gap: 4px; box-shadow: 0 1px 2px rgba(0,0,0,0.05);" onclick="window.pmcScanAction(<?php echo $item->id; ?>, 'delete')" onmouseover="this.style.background='#fee2e2'" onmouseout="this.style.background='#fff'">
												<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/><line x1="10" x2="10" y1="11" y2="17"/><line x1="14" x2="14" y1="11" y2="17"/></svg>
												Poista
											</button>
											<button class="ps-btn ps-action-btn" style="padding: 6px 10px; font-size: 13px; font-weight: 500; background: #fff; color: #475569; border: 1px solid #cbd5e1; cursor: pointer; border-radius: 6px; display: flex; align-items: center; gap: 4px; box-shadow: 0 1px 2px rgba(0,0,0,0.05);" onclick="window.pmcScanAction(<?php echo $item->id; ?>, 'ignore')" onmouseover="this.style.background='#f1f5f9'" onmouseout="this.style.background='#fff'">
												<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9.88 9.88a3 3 0 1 0 4.24 4.24"/><path d="M10.73 5.08A10.43 10.43 0 0 1 12 5c7 0 10 7 10 7a13.16 13.16 0 0 1-1.67 2.68"/><path d="M6.61 6.61A13.526 13.526 0 0 0 2 12s3 7 10 7a9.74 9.74 0 0 0 5.39-1.61"/><line x1="2" x2="22" y1="2" y2="22"/></svg>
												Ohita
											</button>
										<?php else: ?>
											<button class="ps-btn ps-action-btn" style="padding: 6px 10px; font-size: 13px; font-weight: 500; background: #fff; color: #10b981; border: 1px solid #6ee7b7; cursor: pointer; border-radius: 6px; display: flex; align-items: center; gap: 4px; box-shadow: 0 1px 2px rgba(0,0,0,0.05);" onclick="window.pmcScanAction(<?php echo $item->id; ?>, 'restore')" onmouseover="this.style.background='#d1fae5'" onmouseout="this.style.background='#fff'">
												<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/></svg>
												Palauta
											</button>
											<button class="ps-btn ps-action-btn" style="padding: 6px 10px; font-size: 13px; font-weight: 500; background: #fff; color: #dc2626; border: 1px solid #fca5a5; cursor: pointer; border-radius: 6px; display: flex; align-items: center; gap: 4px; box-shadow: 0 1px 2px rgba(0,0,0,0.05);" onclick="window.pmcScanAction(<?php echo $item->id; ?>, 'delete')" onmouseover="this.style.background='#fee2e2'" onmouseout="this.style.background='#fff'">
												<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/><line x1="10" x2="10" y1="11" y2="17"/><line x1="14" x2="14" y1="11" y2="17"/></svg>
												Poista pysyvästi
											</button>
										<?php endif; ?>
									</td>
								</tr>
							<?php endforeach; ?>
						<?php endif; ?>
					</tbody>
				</table>
				
				<div id="scan-pagination-wrap">
					<?php if ($total_pages > 1): ?>
					<div class="ps-pagination mt-3 flex justify-end gap-2 items-center text-xs" style="display: flex; justify-content: flex-end; align-items: center; gap: 8px; font-size: 12px; margin-top: 12px;">
						<span>Sivu <?php echo $paged; ?> / <?php echo $total_pages; ?> &nbsp;</span>
						<?php if ($paged > 1): ?>
							<button class="ps-btn" style="padding:4px 8px;font-size:12px;background:#f8fafc;border:1px solid #e2e8f0;cursor:pointer;" onclick="window.pmcLoadScannerResults('<?php echo esc_js($scan_tab); ?>', <?php echo $paged - 1; ?>)">Edellinen</button>
						<?php else: ?>
							<button class="ps-btn" style="padding:4px 8px;font-size:12px;background:#f1f5f9;border:1px solid #e2e8f0;color:#94a3b8;cursor:not-allowed;" disabled>Edellinen</button>
						<?php endif; ?>
						<?php if ($paged < $total_pages): ?>
							<button class="ps-btn" style="padding:4px 8px;font-size:12px;background:#f8fafc;border:1px solid #e2e8f0;cursor:pointer;" onclick="window.pmcLoadScannerResults('<?php echo esc_js($scan_tab); ?>', <?php echo $paged + 1; ?>)">Seuraava</button>
						<?php else: ?>
							<button class="ps-btn" style="padding:4px 8px;font-size:12px;background:#f1f5f9;border:1px solid #e2e8f0;color:#94a3b8;cursor:not-allowed;" disabled>Seuraava</button>
						<?php endif; ?>
					</div>
					<?php endif; ?>
				</div>
				
			</div>
		</div>

	</div>
</div>

<style>
@keyframes spin { 100% { transform: rotate(360deg); } }
@keyframes ps-progress-stripes { 0% { background-position: 1rem 0; } 100% { background-position: 0 0; } }
</style>
<script>
let currentScannerTab = '<?php echo esc_js($scan_tab); ?>';
let currentScannerPage = <?php echo (int) $paged; ?>;

window.pmcLoadScannerResults = async function(tab, page) {
	currentScannerTab = tab || 'active';
	currentScannerPage = page || 1;
	const tableBody = document.querySelector('#scan-results-table tbody');
	const paginationWrap = document.getElementById('scan-pagination-wrap');
	
	tableBody.innerHTML = '<tr><td colspan="4" style="text-align: center; padding: 40px; color: #64748b;"><span class="material-symbols-outlined" style="animation: spin 1s linear infinite; font-size: 24px; vertical-align: middle; margin-right: 8px;">refresh</span> Ladataan...</td></tr>';
	
	const tabs = document.querySelectorAll('.ps-tabs a');
	tabs.forEach(t => {
		t.style.background = 'transparent';
		t.style.color = '#64748b';
		const badge = t.querySelector('span');
		if (badge) {
			badge.style.background = '#e2e8f0';
			badge.style.color = '#475569';
		}
		t.onmouseover = function() { this.style.background='#f1f5f9'; };
		t.onmouseout = function() { this.style.background='transparent'; };
	});
	const activeTab = document.getElementById('ps-tab-' + tab);
	if (activeTab) {
		activeTab.style.background = '#fff1f2';
		activeTab.style.color = '#be123c';
		const badge = activeTab.querySelector('span');
		if (badge) {
			badge.style.background = '#fecdd3';
			badge.style.color = '#9f1239';
		}
		activeTab.onmouseover = null;
		activeTab.onmouseout = null;
	}

	const res = await pmcSec.post('pmc_deep_scan_get_results', { scan_tab: tab, paged: page });
	if (res.success) {
		tableBody.innerHTML = res.data.html;
		const selectAll = document.getElementById('scan-select-all');
		if (selectAll) selectAll.checked = false;

		if (res.data.count_active !== undefined) {
			const bAct = document.querySelector('#ps-tab-active span');
			if (bAct) bAct.textContent = res.data.count_active;
			const bQuar = document.querySelector('#ps-tab-quarantined span');
			if (bQuar) bQuar.textContent = res.data.count_quarantined;
			const bIgn = document.querySelector('#ps-tab-ignored span');
			if (bIgn) bIgn.textContent = res.data.count_ignored;
		}
		if (res.data.total_pages > 1) {
			let pagHtml = `<div class="ps-pagination mt-3 flex justify-end gap-2 items-center text-xs" style="display: flex; justify-content: flex-end; align-items: center; gap: 8px; font-size: 12px; margin-top: 12px;">
				<span>Sivu ${res.data.paged} / ${res.data.total_pages} &nbsp;</span>`;
			if (res.data.paged > 1) {
				pagHtml += `<button class="ps-btn" style="padding:4px 8px;font-size:12px;background:#f8fafc;border:1px solid #e2e8f0;cursor:pointer;" onclick="window.pmcLoadScannerResults('${tab}', ${res.data.paged - 1})">Edellinen</button>`;
			} else {
				pagHtml += `<button class="ps-btn" style="padding:4px 8px;font-size:12px;background:#f1f5f9;border:1px solid #e2e8f0;color:#94a3b8;cursor:not-allowed;" disabled>Edellinen</button>`;
			}
			if (res.data.paged < res.data.total_pages) {
				pagHtml += `<button class="ps-btn" style="padding:4px 8px;font-size:12px;background:#f8fafc;border:1px solid #e2e8f0;cursor:pointer;" onclick="window.pmcLoadScannerResults('${tab}', ${res.data.paged + 1})">Seuraava</button>`;
			} else {
				pagHtml += `<button class="ps-btn" style="padding:4px 8px;font-size:12px;background:#f1f5f9;border:1px solid #e2e8f0;color:#94a3b8;cursor:not-allowed;" disabled>Seuraava</button>`;
			}
			pagHtml += `</div>`;
			paginationWrap.innerHTML = pagHtml;
		} else {
			paginationWrap.innerHTML = '';
		}
	} else {
		tableBody.innerHTML = '<tr><td colspan="4" style="color: red; padding: 20px;">Virhe ladattaessa tuloksia.</td></tr>';
	}
};

window.pmcDeepScannerRun = async function(btn) {
	btn.disabled = true;
	btn.style.opacity = '0.7';
	
	const progressBar = document.getElementById('scan-progress-bar');
	const progressStripes = document.getElementById('scan-progress-stripes');
	const statusText = document.getElementById('scan-status-text');
	const statusTitleText = document.getElementById('scan-status-title-text');
	const statusIcon = document.getElementById('scan-status-icon');
	const statusTitle = document.getElementById('scan-status-title');
	
	statusIcon.innerText = 'sync';
	statusIcon.style.animation = 'spin 1.5s linear infinite';
	statusIcon.style.color = '#be123c';
	statusTitle.style.color = '#1e293b';
	statusTitleText.innerText = 'Skannataan järjestelmää...';
	
	progressBar.style.background = 'linear-gradient(90deg, #f43f5e 0%, #be123c 100%)';
	progressStripes.style.display = 'block';
	
	statusText.innerText = 'Valmistellaan skannausta...';
	statusText.style.color = '#64748b';
	progressBar.style.width = '2%';
	
	let simulatedProgress = 2;
	const simInterval = setInterval(() => {
		if (simulatedProgress < 15) {
			simulatedProgress += 1;
			progressBar.style.width = simulatedProgress + '%';
		}
	}, 500);

	// Start scan
	const startRes = await pmcSec.post('pmc_deep_scan_start', {});
	clearInterval(simInterval);
	
	if (!startRes.success) {
		alert('Skannauksen aloitus epäonnistui.');
		btn.disabled = false;
		btn.style.opacity = '1';
		statusIcon.innerText = 'error';
		statusIcon.style.animation = 'none';
		statusTitleText.innerText = 'Skannaus epäonnistui';
		progressBar.style.background = '#ef4444';
		progressStripes.style.display = 'none';
		return;
	}

	let total = startRes.data.total;
	let remaining = total;
	
	while (remaining > 0) {
		let exactPercent = ((total - remaining) / total) * 100;
		// Ensure the bar never visually shrinks backwards from the simulated progress
		let currentWidth = parseFloat(progressBar.style.width) || 0;
		progressBar.style.width = Math.max(currentWidth, exactPercent) + '%';
		
		statusText.innerText = `${Math.round(exactPercent)}% (${total - remaining} / ${total})`;
		
		const stepRes = await pmcSec.post('pmc_deep_scan_step', {});
		if (!stepRes.success) {
			alert('Skannaus keskeytyi virheen vuoksi.');
			statusIcon.innerText = 'error';
			statusIcon.style.animation = 'none';
			progressBar.style.background = '#ef4444';
			progressStripes.style.display = 'none';
			break;
		}
		remaining = stepRes.data.remaining;
		
		if (stepRes.data.status === 'complete' || remaining <= 0) {
			break;
		}
	}
	
	progressBar.style.width = '100%';
	statusIcon.innerText = 'check_circle';
	statusIcon.style.animation = 'none';
	statusIcon.style.color = '#10b981';
	statusTitleText.innerText = 'Skannaus valmis!';
	statusText.innerText = '100%';
	progressBar.style.background = '#10b981';
	progressStripes.style.display = 'none';
	
	setTimeout(() => {
		location.reload();
	}, 1000);
};
</script>

<div id="ps-delete-modal-overlay" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(15, 23, 42, 0.7); z-index: 99999; justify-content: center; align-items: center; backdrop-filter: blur(4px);">
	<div style="background: #fff; width: 850px; max-width: 95%; border-radius: 12px; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04); display: flex; flex-direction: column; overflow: hidden; font-family: system-ui, -apple-system, sans-serif;">
		<div style="padding: 20px 24px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; background: #f8fafc;">
			<h3 style="margin: 0; font-size: 18px; color: #0f172a; display: flex; align-items: center; gap: 8px;">
				<span id="ps-modal-icon" class="material-symbols-outlined" style="color: #ef4444;">warning</span> 
				<span id="ps-modal-title">Vahvista tiedoston poisto</span>
			</h3>
			<button onclick="document.getElementById('ps-delete-modal-overlay').style.display='none'" style="background: transparent; border: none; cursor: pointer; color: #64748b; display: flex; align-items: center; padding: 4px; border-radius: 4px;" onmouseover="this.style.background='#e2e8f0'" onmouseout="this.style.background='transparent'">
				<span class="material-symbols-outlined">close</span>
			</button>
		</div>
		<div style="display: flex; padding: 24px; gap: 32px;">
			<!-- Left side info -->
			<div style="flex: 1; display: flex; flex-direction: column; gap: 16px;">
				<div id="ps-modal-info-box" style="background: #fef2f2; border-left: 4px solid #ef4444; padding: 16px; border-radius: 6px;">
					<h4 id="ps-modal-info-title" style="margin: 0 0 8px 0; color: #991b1b; font-size: 15px;">Olet poistamassa tiedostoa pysyvästi</h4>
					<p id="ps-modal-info-text" style="margin: 0; color: #b91c1c; font-size: 13px; line-height: 1.5;">Tätä toimintoa ei voi peruuttaa. Varmista ettei kyseessä ole kriittinen teeman tai lisäosan ydintiedosto. Väärän tiedoston poistaminen voi rikkoa sivustosi toiminnallisuuden täysin (esim. White Screen of Death).</p>
				</div>
				<p id="ps-modal-info-hint" style="margin: 0; font-size: 13px; color: #475569; line-height: 1.5;">
					Jos et ole täysin varma onko tiedosto haitallinen, on suositeltavaa joko <strong>Ohittaa</strong> se listalta tai asettaa se <strong>Karanteeniin</strong>. 
				</p>
			</div>
			<!-- Right side path visualizer -->
			<div style="flex: 1.2; display: flex; flex-direction: column; gap: 8px;">
				<h4 style="margin: 0; font-size: 14px; color: #334155;">Tiedoston sijainti:</h4>
				<div id="ps-modal-folder-tree" style="background: #1e293b; color: #cbd5e1; padding: 16px; border-radius: 8px; font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace; font-size: 13px; overflow-x: auto; max-height: 250px; overflow-y: auto; border: 1px solid #0f172a; box-shadow: inset 0 2px 4px 0 rgba(0, 0, 0, 0.06);">
					<!-- Tree will be injected here -->
				</div>
			</div>
		</div>
		<div style="padding: 16px 24px; border-top: 1px solid #e2e8f0; display: flex; justify-content: flex-end; gap: 12px; background: #f8fafc;">
			<button onclick="document.getElementById('ps-delete-modal-overlay').style.display='none'" style="padding: 8px 16px; background: #fff; border: 1px solid #cbd5e1; color: #475569; border-radius: 6px; cursor: pointer; font-weight: 500; font-size: 14px;">Peruuta</button>
			<button id="ps-modal-confirm-btn" style="padding: 8px 16px; background: #ef4444; border: 1px solid #dc2626; color: #fff; border-radius: 6px; cursor: pointer; font-weight: 500; font-size: 14px; display: flex; align-items: center; gap: 6px;">
				<span id="ps-modal-confirm-icon" class="material-symbols-outlined" style="font-size: 18px;">delete_forever</span>
				<span id="ps-modal-confirm-text">Poista tiedosto</span>
			</button>
		</div>
	</div>
</div>

<script>
let currentActionContext = null;

window.pmcSelectAll = function(el) {
	const checkboxes = document.querySelectorAll('.ps-scan-cb');
	checkboxes.forEach(cb => cb.checked = el.checked);
};

window.pmcBulkScanAction = async function() {
	const action = document.getElementById('scan-bulk-action').value;
	if (!action) {
		alert('Valitse massatoiminto ensin.');
		return;
	}
	
	const checkboxes = document.querySelectorAll('.ps-scan-cb:checked');
	if (checkboxes.length === 0) {
		alert('Valitse ainakin yksi rivi.');
		return;
	}
	
	const actionNames = {
		'quarantine': 'Karanteeniin siirto',
		'ignore': 'Ohita havainnot',
		'delete': 'Poista pysyvästi',
		'restore': 'Palauta'
	};
	const actionName = actionNames[action] || action;
	
	if (!confirm(`Haluatko varmasti suorittaa toiminnon "${actionName}" valituille (${checkboxes.length} kpl) tiedostoille?`)) {
		return;
	}
	
	const ids = Array.from(checkboxes).map(cb => cb.value).join(',');
	
	const res = await pmcSec.post('pmc_deep_scan_bulk_action', {
		scan_action: action,
		ids: ids
	});
	
	if (res.success) {
		window.pmcLoadScannerResults(currentScannerTab, currentScannerPage);
	} else {
		alert('Virhe massatoiminnossa: ' + (res.data || 'Tuntematon virhe'));
	}
};

window.pmcScanAction = async function(id, action) {
	if (action === 'delete' || action === 'quarantine') {
		currentActionContext = { id, action };
		const row = document.getElementById('scan-item-' + id);
		if (!row) return;
		
		const pathCell = row.querySelector('td');
		const fullPath = pathCell ? pathCell.innerText.trim() : '';
		
		const treeContainer = document.getElementById('ps-modal-folder-tree');
		treeContainer.innerHTML = '';
		const parts = fullPath.split(/[/\\]+/).filter(p => p.length > 0);
		
		const isDelete = (action === 'delete');
		const colorIcon = isDelete ? '#ef4444' : '#d97706';
		
		// Update modal content based on action
		document.getElementById('ps-modal-icon').style.color = colorIcon;
		document.getElementById('ps-modal-title').innerText = isDelete ? 'Vahvista tiedoston poisto' : 'Vahvista karanteeniin siirto';
		
		const infoBox = document.getElementById('ps-modal-info-box');
		infoBox.style.background = isDelete ? '#fef2f2' : '#fffbeb';
		infoBox.style.borderLeftColor = colorIcon;
		
		document.getElementById('ps-modal-info-title').innerText = isDelete ? 'Olet poistamassa tiedostoa pysyvästi' : 'Olet siirtämässä tiedostoa karanteeniin';
		document.getElementById('ps-modal-info-title').style.color = isDelete ? '#991b1b' : '#b45309';
		
		document.getElementById('ps-modal-info-text').innerText = isDelete 
			? 'Tätä toimintoa ei voi peruuttaa. Varmista ettei kyseessä ole kriittinen teeman tai lisäosan ydintiedosto. Väärän tiedoston poistaminen voi rikkoa sivustosi toiminnallisuuden täysin (esim. White Screen of Death).'
			: 'Tiedosto siirretään turvalliseen eristettyyn hakemistoon ja nimetään uudelleen, mikä estää sen suorittamisen. Jos kyseessä on virheellinen löydös, tiedoston poistaminen käytöstä voi silti rikkoa sivuston toiminnallisuutta.';
		document.getElementById('ps-modal-info-text').style.color = isDelete ? '#b91c1c' : '#b45309';
		
		document.getElementById('ps-modal-info-hint').style.display = isDelete ? 'block' : 'none';
		
		const confirmBtn = document.getElementById('ps-modal-confirm-btn');
		confirmBtn.style.background = colorIcon;
		confirmBtn.style.borderColor = isDelete ? '#dc2626' : '#b45309';
		document.getElementById('ps-modal-confirm-icon').innerText = isDelete ? 'delete_forever' : 'move_to_inbox';
		document.getElementById('ps-modal-confirm-text').innerText = isDelete ? 'Poista tiedosto' : 'Siirrä karanteeniin';
		
		let indent = 0;
		parts.forEach((part, index) => {
			const isLast = index === parts.length - 1;
			const el = document.createElement('div');
			el.style.marginLeft = (indent * 12) + 'px';
			el.style.padding = '3px 0';
			el.style.display = 'flex';
			el.style.alignItems = 'center';
			el.style.gap = '6px';
			
			const icon = document.createElement('span');
			icon.className = 'material-symbols-outlined';
			icon.style.fontSize = '16px';
			
			if (isLast) {
				icon.innerText = 'description';
				icon.style.color = colorIcon;
				el.style.color = colorIcon;
				el.style.fontWeight = 'bold';
			} else {
				icon.innerText = 'folder';
				icon.style.color = '#64748b';
			}
			
			el.appendChild(icon);
			el.appendChild(document.createTextNode(part));
			treeContainer.appendChild(el);
			indent++;
		});
		
		document.getElementById('ps-delete-modal-overlay').style.display = 'flex';
		return;
	}

	await executeAction(id, action);
};

async function executeAction(id, action) {
	const res = await pmcSec.post('pmc_deep_scan_action', { item_id: id, scan_action: action });
	if (res.success) {
		const row = document.getElementById('scan-item-' + id);
		if (row) {
			const actionCell = row.querySelector('td:last-child');
			actionCell.innerHTML = `<span style="color: #10b981; font-weight: 600; display: flex; align-items: center; gap: 4px; font-size: 13px;"><span class="material-symbols-outlined" style="font-size: 18px;">check_circle</span> ${res.data}</span>`;
			
			setTimeout(() => {
				row.style.transition = 'all 0.4s cubic-bezier(0.4, 0, 0.2, 1)';
				row.style.transform = 'translateX(20px)';
				row.style.opacity = '0';
				
				setTimeout(() => {
					Array.from(row.children).forEach(td => {
						td.style.transition = 'all 0.3s cubic-bezier(0.4, 0, 0.2, 1)';
						td.style.padding = '0';
						td.style.height = '0';
						td.style.border = 'none';
						td.style.lineHeight = '0';
					});
					
					setTimeout(() => {
						row.remove();
						
						const tableBody = document.querySelector('#scan-results-table tbody');
						if (tableBody.querySelectorAll('tr').length === 0) {
							// Auto-reload current tab to fetch next page or show empty state
							window.pmcLoadScannerResults(currentScannerTab, 1);
						}
					}, 300);
				}, 400);
			}, 800);
		}
	} else {
		alert('Toiminto epäonnistui: ' + (res.data || 'Tuntematon virhe'));
	}
}

document.getElementById('ps-modal-confirm-btn').addEventListener('click', async () => {
	document.getElementById('ps-delete-modal-overlay').style.display = 'none';
	if (currentActionContext) {
		await executeAction(currentActionContext.id, currentActionContext.action);
		currentActionContext = null;
	}
});
</script>
