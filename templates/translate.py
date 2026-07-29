import re

file_path = r'c:\Users\pepeu\Local Sites\tem-kumpulanspy\app\public\wp-content\plugins\pecodex-media-control\templates\security-dashboard.php'
with open(file_path, 'r', encoding='utf-8') as f:
    content = f.read()

replacements = {
    '<h2>Operator<br>Console</h2>': '<h2>Operaattorin<br>Konsoli</h2>',
    '<p>Level 4 Clearance</p>': '<p>Tason 4 Oikeudet</p>',
    '<span class="nav-label">Dashboard</span>': '<span class="nav-label">Ohjausnäkymä</span>',
    '<span class="nav-label">Firewall & Lockouts</span>': '<span class="nav-label">Palomuuri & Sulut</span>',
    '<span class="nav-label">Security Hardening</span>': '<span class="nav-label">Järjestelmän Suojaus</span>',
    '<span class="nav-label">Malware Scanner</span>': '<span class="nav-label\">Haittaohjelmien Skanneri</span>',
    '<span class="nav-label">Advanced Tools</span>': '<span class="nav-label">Lisätyökalut</span>',
    '<span class="nav-label">Security Headers</span>': '<span class="nav-label">Tietoturvaotsikot</span>',
    '<span class="nav-label">Audit Log</span>': '<span class="nav-label">Tarkastusloki</span>',
    '<span class="nav-label">Notifications</span>': '<span class="nav-label">Ilmoitukset</span>',
    '<span class="incident-label">Incident Report</span>': '<span class="incident-label">Poikkeamaraportti</span>',
    '<span class="footer-label">Support</span>': '<span class="footer-label">Tuki</span>',
    '<span class="footer-label">System Log</span>': '<span class="footer-label">Järjestelmäloki</span>',
    
    'placeholder="Search across systems…"': 'placeholder="Hae järjestelmistä…"',
    
    'title="Hide All Widgets"': 'title="Piilota kaikki widgetit"',
    'title="Toggle System Resources"': 'title="Vaihda järjestelmäresurssit"',
    'title="Toggle Traffic"': 'title="Vaihda Liikenne"',
    'title="Toggle Threat Origin Heatmap"': 'title="Vaihda Uhkien Lähdekartta"',
    'title="Toggle Live Event Log"': 'title="Vaihda Reaaliaikainen Tapahtumaloki"',
    'title="Toggle WAF Rate Limit"': 'title="Vaihda WAF-nopeusrajoitus"',
    'title="Toggle Node Health"': 'title="Vaihda Solmun Tila"',
    'title="Toggle Blocked Payloads"': 'title="Vaihda Estetyt Kuormat"',
    'title="Toggle Controls"': 'title="Vaihda Hallinta"',
    'title="Toggle Malware & File Integrity"': 'title="Vaihda Haittaohjelmat & Tiedostojen Eheys"',
    'title="Toggle Vulnerability Alerts"': 'title="Vaihda Haavoittuvuushälytykset"',
    'title="Toggle Login Security"': 'title="Vaihda Kirjautumisen Tietoturva"',
    'title="Toggle Audit Trail"': 'title="Vaihda Tarkastusloki"',
    'title="Toggle System Hardening"': 'title="Vaihda Järjestelmän Suojaus"',
    'title="Maximize"': 'title="Suurenna"',
    
    'System Resources\n': 'Järjestelmäresurssit\n',
    '<span>CPU Usage</span>': '<span>Suorittimen Käyttö</span>',
    '<span>Memory</span>': '<span>Muisti</span>',
    
    '<h3 class="font-bold text-slate-800 text-sm">Top Offenders</h3>': '<h3 class="font-bold text-slate-800 text-sm">Pahimmat Hyökkääjät</h3>',
    '<h3 class="font-bold text-slate-800 text-sm">Vulnerabilities</h3>': '<h3 class="font-bold text-slate-800 text-sm">Haavoittuvuudet</h3>',
    '3 Critical': '3 Kriittistä',
    'hover:bg-slate-50">Update</button>': 'hover:bg-slate-50">Päivitä</button>',
    
    'Security Modules (20)': 'Tietoturvamoduulit (20)',
    
    "'id' => 'waf', 'name' => 'WAF Engine'": "'id' => 'waf', 'name' => 'WAF-moottori'",
    "'id' => 'bot', 'name' => 'Bot Protection'": "'id' => 'bot', 'name' => 'Bottisuojaus'",
    "'id' => 'geoip', 'name' => 'GeoIP Rules'": "'id' => 'geoip', 'name' => 'GeoIP-säännöt'",
    "'id' => 'honeypot', 'name' => 'Honeypot Traps'": "'id' => 'honeypot', 'name' => 'Hunajapurkit'",
    "'id' => 'zerotrust', 'name' => 'Zero Trust'": "'id' => 'zerotrust', 'name' => 'Zero Trust'",
    "'id' => 'webauthn', 'name' => 'WebAuthn'": "'id' => 'webauthn', 'name' => 'WebAuthn'",
    "'id' => 'lockdown', 'name' => 'Lockdown Mode'": "'id' => 'lockdown', 'name' => 'Sulkutila'",
    "'id' => 'firewall', 'name' => 'Network Firewall'": "'id' => 'firewall', 'name' => 'Verkkopalomuuri'",
    "'id' => 'hardening', 'name' => 'Core Hardening'": "'id' => 'hardening', 'name' => 'Ytimen Suojaus'",
    "'id' => 'advanced', 'name' => 'Advanced AI Sec'": "'id' => 'advanced', 'name' => 'Kehittynyt AI-turva'",
    "'id' => 'scanner', 'name' => 'Malware Scanner'": "'id' => 'scanner', 'name' => 'Haittaohjelmien Skanneri'",
    "'id' => 'deepscanner', 'name' => 'Deep Heuristics'": "'id' => 'deepscanner', 'name' => 'Syväheuristiikka'",
    "'id' => 'captcha', 'name' => 'Smart Captcha'": "'id' => 'captcha', 'name' => 'Älykäs Captcha'",
    "'id' => 'telemetry', 'name' => 'Live Telemetry'": "'id' => 'telemetry', 'name' => 'Reaaliaikainen Telemetria'",
    "'id' => 'cache', 'name' => 'Secure Cache'": "'id' => 'cache', 'name' => 'Turvallinen Välimuisti'",
    "'id' => 'encryption', 'name' => 'Data Encryption'": "'id' => 'encryption', 'name' => 'Tietojen Salaus'",
    "'id' => 'appsec', 'name' => 'App Security'": "'id' => 'appsec', 'name' => 'Sovellusturva'",
    "'id' => 'audit', 'name' => 'Audit Trail'": "'id' => 'audit', 'name' => 'Tarkastusloki'",
    "'id' => 'auth', 'name' => 'Auth Safeguards'": "'id' => 'auth', 'name' => 'Tunnistautumisen Suojat'",
    "'id' => 'wizard', 'name' => 'API Gateway'": "'id' => 'wizard', 'name' => 'API-yhdyskäytävä'",
    
    'Engage Total Lockdown': 'Käynnistä Täysi Sulkutila',
    
    '<h3>Live Event Log</h3>': '<h3>Reaaliaikainen Tapahtumaloki</h3>',
    'Active Monitoring': 'Aktiivinen Seuranta',
    '<th>Timestamp (UTC)</th>': '<th>Aikaleima (UTC)</th>',
    '<th>Origin</th>': '<th>Lähde</th>',
    '<th>Target</th>': '<th>Kohde</th>',
    '<th>Attack</th>': '<th>Hyökkäys</th>',
    '<th>Status</th>': '<th>Tila</th>',
    'badge-critical">Critical</span>': 'badge-critical">Kriittinen</span>',
    'badge-blocked">Blocked</span>': 'badge-blocked">Estetty</span>',
    'badge-warning">Warning</span>': 'badge-warning">Varoitus</span>',
    
    '<h3 class="font-bold text-slate-800 text-sm">Audit Trail</h3>': '<h3 class="font-bold text-slate-800 text-sm">Tarkastusloki</h3>',
    'Deactivated plugin': 'Deaktivoi lisäosan',
    'Changed WP Settings (Permalinks)': 'Muutti WP-asetuksia (Kestolinkit)',
    'Blocked 45 login attempts from': 'Estetty 45 kirjautumisyritystä osoitteesta',
    
    'Traffic\n': 'Liikenne\n',
    'Ingress': 'Saapuva',
    
    '<h3 class="font-bold text-slate-800 text-sm">System Hardening</h3>': '<h3 class="font-bold text-slate-800 text-sm">Järjestelmän Suojaus</h3>',
    'wp-config.php rights': 'wp-config.php oikeudet',
    'Directory Listing': 'Hakemiston Listaus',
    'Disabled': 'Pois käytöstä',
    'WP Version hidden': 'WP-versio piilotettu',
    'Yes': 'Kyllä',
    'XML-RPC': 'XML-RPC',
    'Enabled!': 'Käytössä!',
    
    '<h3 class="font-bold text-slate-800 text-sm">Blocked Payloads (Live)</h3>': '<h3 class="font-bold text-slate-800 text-sm">Estetyt Kuormat (Reaaliaikainen)</h3>',
    
    '<h3 class="font-bold text-slate-800 text-sm">File Integrity Scanner</h3>': '<h3 class="font-bold text-slate-800 text-sm">Tiedostojen Eheyden Skanneri</h3>',
    'Last scan: 2h ago': 'Viimeisin skannaus: 2h sitten',
    'Infected': 'Saastunut',
    'Modified': 'Muokattu',
    'Quarantine': 'Karanteeni',
    'Notice:': 'Huomio:',
    'was modified recently.': 'on hiljattain muokattu.',
    
    '<h3 class="font-bold text-slate-800 text-sm">WAF Rate Limit</h3>': '<h3 class="font-bold text-slate-800 text-sm">WAF-nopeusrajoitus</h3>',
    'req/s': 'pyyntöä/s',
    'Limit: 500': 'Raja: 500',
    
    '<h3 class="font-bold text-slate-800 text-sm">Login Security</h3>': '<h3 class="font-bold text-slate-800 text-sm">Kirjautumisen Tietoturva</h3>',
    'Failed Logins (24h)': 'Epäonnistuneet Kirjautumiset (24h)',
    'Locked IPs': 'Lukitut IP:t',
    'Active Admins': 'Aktiiviset Järjestelmänvalvojat',
    
    '<h3 class="font-bold text-slate-800 text-sm">Node Health</h3>': '<h3 class="font-bold text-slate-800 text-sm">Solmun Tila</h3>',
    '(Under load)': '(Kuormituksen alaisena)',
    
    'Threat Intelligence Report': 'Uhkaraportti',
    'Threat Origin': 'Uhan Lähde',
    'Country:': 'Maa:',
    'IP:': 'IP:',
    'Attack:': 'Hyökkäys:',
    'Targeted Resource': 'Kohderesurssi',
    'Host:': 'Isäntä:',
    'Endpoint:': 'Päätepiste:',
    'Active Breach Attempt': 'Aktiivinen Murtoyritys',
    'DISMISS': 'OHITA',
    'BAN IP': 'ESTÄ IP',
    
    'SYSTEM LOCKDOWN ACTIVE': 'JÄRJESTELMÄN SULKUTILA AKTIIVINEN',
    'All external connections have been suspended.': 'Kaikki ulkoiset yhteydet on keskeytetty.',
    'DISENGAGE LOCKDOWN': 'POISTA SULKUTILA',
    
    "content: 'DROP HERE';": "content: 'PUDOTA TÄHÄN';",
    "No recent events": "Ei tuoreita tapahtumia",
}

for k, v in replacements.items():
    content = content.replace(k, v)

# JS / Dynamic / Code replacements
content = content.replace("Manual Action Required", "Vaatii Toimenpiteitä")
content = content.replace("Configure Manually", "Määritä Manuaalisesti")
content = content.replace("Toggle to disable", "Vaihda poistaaksesi")
content = content.replace("Toggle to enable", "Vaihda ottaaksesi käyttöön")

content = content.replace("This security tweak is active and protecting your site.", "Tämä tietoturva-asetus on aktiivinen ja suojaa sivustoasi.")
content = content.replace("This security tweak is NOT active. Your site may be vulnerable.", "Tämä tietoturva-asetus EI ole aktiivinen. Sivustosi saattaa olla haavoittuva.")

content = content.replace('<h4 style="margin:0 0 10px 0; color:#374151; font-weight: 600; font-size:14px;">Overview</h4>', '<h4 style="margin:0 0 10px 0; color:#374151; font-weight: 600; font-size:14px;">Yleiskatsaus</h4>')
content = content.replace('<h4 style="margin:0 0 10px 0; color:#374151; font-weight: 600; font-size:14px;">Status</h4>', '<h4 style="margin:0 0 10px 0; color:#374151; font-weight: 600; font-size:14px;">Tila</h4>')
content = content.replace('<h4 style="margin:0 0 10px 0; color:#374151; font-weight: 600; font-size:14px;">How to fix</h4>', '<h4 style="margin:0 0 10px 0; color:#374151; font-weight: 600; font-size:14px;">Kuinka korjata</h4>')

content = content.replace("> Active</i>", "> Aktiivinen</i>")
content = content.replace("> Inactive</i>", "> Ei aktiivinen</i>")
content = content.replace("'Active' : 'Inactive'", "'Aktiivinen' : 'Ei aktiivinen'")
content = content.replace("Active\\n", "Aktiivinen\\n")
content = content.replace("Inactive\\n", "Ei aktiivinen\\n")

content = content.replace("btnEl.textContent = 'Scanning...';", "btnEl.textContent = 'Skannataan...';")
content = content.replace("btnEl.textContent = 'Run Malware Scan Now';", "btnEl.textContent = 'Suorita Haittaohjelmaskannaus Nyt';")

content = content.replace("'Scan completed successfully.'", "'Skannaus suoritettu onnistuneesti.'")
content = content.replace("'Scan failed.'", "'Skannaus epäonnistui.'")
content = content.replace("'Failed to toggle tweak: '", "'Tilan vaihto epäonnistui: '")

content = content.replace("btn.textContent = 'Saving...';", "btn.textContent = 'Tallennetaan...';")

content = content.replace("Advanced settings saved to database successfully!", "Lisäasetukset tallennettu tietokantaan onnistuneesti!")
content = content.replace("Failed to save some settings: ", "Joidenkin asetusten tallennus epäonnistui: ")
content = content.replace("Error saving settings.", "Virhe tallennettaessa asetuksia.")

content = content.replace("Firewall settings saved successfully.", "Palomuurin asetukset tallennettu onnistuneesti.")
content = content.replace("Failed to save firewall settings: ", "Palomuurin asetusten tallennus epäonnistui: ")

content = content.replace("Notification settings saved successfully!", "Ilmoitusasetukset tallennettu onnistuneesti!")
content = content.replace("Failed to save notification settings: ", "Ilmoitusasetusten tallennus epäonnistui: ")

# Final write
with open(file_path, 'w', encoding='utf-8') as f:
    f.write(content)

print("Translations applied successfully.")
