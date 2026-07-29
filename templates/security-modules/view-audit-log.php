<?php
/**
 * Security Module: Audit Log
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="ps-module" id="ps-module-audit-log" style="display: none;">
	<div class="ps-module-header">
		<h2><span class="material-symbols-outlined">list_alt</span> Tarkastusloki</h2>
		<div style="display: flex; align-items: center; gap: 10px;">
			<div style="display: flex; gap: 6px;">
				<button onclick="pmcExportAuditLog('txt')" style="display:flex;align-items:center;gap:5px;padding:7px 12px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:7px;font-size:12px;font-weight:600;color:#374151;cursor:pointer;transition:background 0.15s;" onmouseover="this.style.background='#f1f5f9'" onmouseout="this.style.background='#f8fafc'">
					<span class="material-symbols-outlined" style="font-size:16px;">text_snippet</span> Lataa TXT
				</button>
				<button onclick="pmcExportAuditLog('csv')" style="display:flex;align-items:center;gap:5px;padding:7px 12px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:7px;font-size:12px;font-weight:600;color:#374151;cursor:pointer;transition:background 0.15s;" onmouseover="this.style.background='#f1f5f9'" onmouseout="this.style.background='#f8fafc'">
					<span class="material-symbols-outlined" style="font-size:16px;">table_chart</span> Lataa CSV
				</button>
				<button onclick="pmcExportAuditLogPDF()" style="display:flex;align-items:center;gap:5px;padding:7px 12px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:7px;font-size:12px;font-weight:600;color:#374151;cursor:pointer;transition:background 0.15s;" onmouseover="this.style.background='#f1f5f9'" onmouseout="this.style.background='#f8fafc'">
					<span class="material-symbols-outlined" style="font-size:16px;">picture_as_pdf</span> Lataa PDF
				</button>
			</div>
			<button class="ps-module-close material-symbols-outlined">close</button>
		</div>
	</div>
	<div class="ps-module-content">
		
		<div class="ps-info-banner" style="background: #eff6ff; border: 1px solid #bfdbfe; border-left: 4px solid #3b82f6; padding: 16px; border-radius: 6px; margin-bottom: 20px; display: flex; gap: 12px; align-items: start;">
			<span class="material-symbols-outlined" style="color: #3b82f6; font-size: 24px;">info</span>
			<div>
				<h4 style="margin: 0 0 4px 0; color: #1e3a8a; font-size: 15px;">Miten tämä moduuli toimii</h4>
				<p style="margin: 0; color: #3b82f6; font-size: 13px; line-height: 1.5;">Tarkastusloki pitää peukalointisuojattua kirjaa jokaisesta merkittävästä sivustollasi tehdystä hallinnollisesta toiminnasta (esim. lisäosien aktivoinnit, asetusmuutokset), auttaen sinua jäljittämään tarkalleen mitä tapahtui ja kuka sen teki.</p>
			</div>
		</div>
		
		<div class="ps-card">
			<h3>Järjestelmänvalvojan toimintaloki</h3>
			<div class="ps-table-wrapper mt-4" style="overflow-x: auto;">
				<table class="ps-table" id="audit-log-table" style="min-width: 1100px;">
					<thead>
						<tr>
							<th style="width:130px;">Aika</th>
							<th style="width:90px;">Vakavuus</th>
							<th style="width:100px;">Käyttöjä</th>
							<th style="width:90px;">IP-osoite</th>
							<th style="width:65px;">Maa</th>
							<th style="width:80px;">Laite</th>
							<th style="width:80px;">Selain / OS</th>
							<th style="width:120px;">Toiminto</th>
							<th>Lisätiedot</th>
						</tr>
					</thead>
					<tbody>
						<tr><td colspan="9">Ladataan...</td></tr>
					</tbody>
				</table>
			</div>
		</div>

	</div>
</div>

<script>
async function pmcExportAuditLog(format) {
	if (typeof pmcSecurityConfig === 'undefined') {
		alert('Virhe: tietoturvakonfiguraatio puuttuu. Päivitä sivu ja yritä uudelleen.');
		return;
	}
	const nonce   = pmcSecurityConfig.nonce;
	const ajaxUrl = pmcSecurityConfig.ajaxUrl;

	try {
		const formData = new FormData();
		formData.append('action', 'pmc_export_audit_log');
		formData.append('nonce', nonce);
		formData.append('format', format);

		const response = await fetch(ajaxUrl, {
			method: 'POST',
			body: formData
		});

		if (!response.ok) {
			throw new Error('Verkkovirhe latauksessa.');
		}

		const blob = await response.blob();
		const downloadUrl = window.URL.createObjectURL(blob);
		const a = document.createElement('a');
		a.style.display = 'none';
		a.href = downloadUrl;
		a.download = `tarkastusloki-${new Date().toISOString().slice(0,10)}.${format}`;
		document.body.appendChild(a);
		a.click();
		window.URL.revokeObjectURL(downloadUrl);
		document.body.removeChild(a);
	} catch (error) {
		console.error('Lataus epäonnistui:', error);
		alert('Tiedoston lataus epäonnistui.');
	}
}

function pmcExportAuditLogPDF() {
	const table = document.getElementById('audit-log-table');
	if (!table) return;
	const win = window.open('', '_blank');
	const styles = [
		'body { font-family: Arial, sans-serif; font-size: 11px; color: #111; margin: 20px; }',
		'h1 { font-size: 16px; margin-bottom: 4px; }',
		'p { margin: 0 0 12px; color: #6b7280; font-size: 10px; }',
		'table { width: 100%; border-collapse: collapse; }',
		'th { background: #1e3a8a; color: #fff; padding: 6px 8px; text-align: left; font-size: 10px; }',
		'td { padding: 5px 8px; border-bottom: 1px solid #e5e7eb; font-size: 10px; }',
		'tr:nth-child(even) td { background: #f8fafc; }',
		'@media print { button { display: none; } }'
	].join('\n');
	const html = '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Tarkastusloki \u2014 Pecodex Security<\/title>'
		+ '<style>' + styles + '<\/style>'
		+ '<\/head><body>'
		+ '<h1>Tarkastusloki \u2014 Pecodex Security<\/h1>'
		+ '<p>Tulostettu: ' + new Date().toLocaleString('fi-FI') + '<\/p>'
		+ '<button onclick="window.print()" style="margin-bottom:12px;padding:6px 14px;background:#1e3a8a;color:#fff;border:none;border-radius:4px;cursor:pointer;font-size:12px;">\uD83D\uDDA8\uFE0F Tulosta \/ Tallenna PDF<\/button>'
		+ table.outerHTML
		+ '<\/body><\/html>';
	win.document.write(html);
	win.document.close();
	win.focus();
}
</script>
