<?php
/**
 * Security Module: Tietoturvamoduulien Yleiskatsaus
 * 4-column card grid with descriptions and toggles for all 20 security modules.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$active_modules = get_option('pmc_active_modules', array());
$is_first_run   = empty($active_modules);

$module_cards = [
	[
		'id'       => 'waf',
		'icon'     => 'shield',
		'color'    => '#3b82f6',
		'bg'       => '#eff6ff',
		'name'     => 'WAF-moottori',
		'badge'    => 'Aktiivinen',
		'badge_color' => '#16a34a',
		'desc'     => 'Web Application Firewall -suodatin tarkastaa jokaisen pyynnön ennen kuin se saavuttaa WordPressin. Se estää SQL-injektiot, XSS-hyökkäykset, reittiylitykset ja muut OWASP Top 10 -uhat reaaliaikaisesti.',
		'settings' => '',
	],
	[
		'id'       => 'bot',
		'icon'     => 'smart_toy',
		'color'    => '#0d9488',
		'bg'       => '#f0fdfa',
		'name'     => 'Bottien esto',
		'badge'    => 'Aktiivinen',
		'badge_color' => '#16a34a',
		'desc'     => 'Tunnistaa ja estää automaattiset skannausrobotit, sisältökaavijat ja haitallisen liikenteen User-Agent- ja käyttäytymisanalyysin perusteella. Suojelee palvelimesi resursseja.',
		'settings' => '',
	],
	[
		'id'       => 'geoip',
		'icon'     => 'public',
		'color'    => '#6366f1',
		'bg'       => '#eef2ff',
		'name'     => 'GeoIP-säännöt',
		'badge'    => 'Määritettävissä',
		'badge_color' => '#d97706',
		'desc'     => 'Estää pääsyn sivustollesi tietyistä maista Cloudflaren IP-maarekisterin avulla. Hyödyllinen, jos sivustosi palvelee vain tiettyö aluetta. Käyttöä CF-Ipcountry-otsikkoa.',
		'settings' => '',
	],
	[
		'id'       => 'honeypot',
		'icon'     => 'bug_report',
		'color'    => '#ec4899',
		'bg'       => '#fdf2f8',
		'name'     => 'Honeypot-ansat',
		'badge'    => 'Aktiivinen',
		'badge_color' => '#16a34a',
		'desc'     => 'Luo näkymättömiä ansoja sivuille, jotka vain automaattiset botit löytävät. Kun botti osuu ansaan, sen IP estetään välittömästi. Ihmiskäyttäjät eivät koskaan näe tai aktivoi näitä.',
		'settings' => '',
	],
	[
		'id'       => 'zerotrust',
		'icon'     => 'fingerprint',
		'color'    => '#a855f7',
		'bg'       => '#faf5ff',
		'name'     => 'Zero Trust',
		'badge'    => 'Edistynyt',
		'badge_color' => '#7c3aed',
		'desc'     => 'Tunnistaa tuntemattomat laitteet kirjautumisen yhteydessä. Jos käyttäjä kirjautuu laitteelta, jota ei ole aiemmin hyväksytty, järjestelmänvalvojalle lähetetään välitön varoitussähköposti.',
		'settings' => '',
	],
	[
		'id'       => 'webauthn',
		'icon'     => 'passkey',
		'color'    => '#16a34a',
		'bg'       => '#f0fdf4',
		'name'     => 'WebAuthn / Passkey',
		'badge'    => 'Kehitteillä',
		'badge_color' => '#64748b',
		'desc'     => 'Mahdollistaa salasanattoman kirjautumisen WebAuthn-standardin avulla (sormenjälki, kasvojentunnistus, turva-avain). Poistaa salasanojen arvailuhyökkäykset kokonaan.',
		'settings' => '',
	],
	[
		'id'       => 'lockdown',
		'icon'     => 'lock_person',
		'color'    => '#ef4444',
		'bg'       => '#fef2f2',
		'name'     => 'Sulkutila',
		'badge'    => 'Hätäkäyttö',
		'badge_color' => '#dc2626',
		'desc'     => 'Hätäkytkin, joka siirtää sivuston välittömästi huoltotilaan 24 tunniksi kirjoittamalla WordPress-ylläpitotiedoston. Käytä vain kriisitilanteessa.',
		'settings' => '',
	],
	[
		'id'       => 'firewall',
		'icon'     => 'router',
		'color'    => '#f97316',
		'bg'       => '#fff7ed',
		'name'     => 'Verkkopalomuuri',
		'badge'    => 'Aktiivinen',
		'badge_color' => '#16a34a',
		'desc'     => 'Seuraa epäonnistuneita kirjautumisyrityksiä ja 404-virheitä. Lukitsee hyökkääjän IP-osoitteen automaattisesti määritetyksi ajaksi (15 min – 30 päivää). IP:t voidaan myös estää manuaalisesti.',
		'settings' => 'firewall',
	],
	[
		'id'       => 'hardening',
		'icon'     => 'gpp_good',
		'color'    => '#0d9488',
		'bg'       => '#f0fdfa',
		'name'     => 'Ytimen suojaus',
		'badge'    => 'Aktiivinen',
		'badge_color' => '#16a34a',
		'desc'     => 'Poistaa käytöstä XML-RPC-rajapinnan, piilottaa WordPress-version, estää tiedostojen muokkauksen hallintapaneelista ja rajoittaa tiedostojen käyttöoikeuksia. Pienentää hyökkäyspintaa merkittävästi.',
		'settings' => 'hardening',
	],
	[
		'id'       => 'advanced',
		'icon'     => 'memory',
		'color'    => '#6366f1',
		'bg'       => '#eef2ff',
		'name'     => 'Edistynyt tekoälyturva',
		'badge'    => 'Aktiivinen',
		'badge_color' => '#16a34a',
		'desc'     => 'Tarjoaa kirjautumissivun piilottamisen (Login Masking), pakollisen kaksivaiheisen tunnistautumisen (2FA), vahvojen salasanojen pakon ja istuntojen kaappaussuojan.',
		'settings' => 'advanced',
	],
	[
		'id'       => 'scanner',
		'icon'     => 'troubleshoot',
		'color'    => '#3b82f6',
		'bg'       => '#eff6ff',
		'name'     => 'Haittaohjelmaskanneri',
		'badge'    => 'Aktiivinen',
		'badge_color' => '#16a34a',
		'desc'     => 'Skannaa WordPress-ytimen, lisäosat ja teemat haittaohjelma-allekirjoitusten ja muokattujen tiedostojen varalta. Epäilyttävät tiedostot voidaan asettaa karanteeniin tai poistaa yhdellä klikkauksella.',
		'settings' => 'scanner',
	],
	[
		'id'       => 'deepscanner',
		'icon'     => 'biotech',
		'color'    => '#ec4899',
		'bg'       => '#fdf2f8',
		'name'     => 'Syväheuristiikka',
		'badge'    => 'Edistynyt',
		'badge_color' => '#7c3aed',
		'desc'     => 'Vertaa WordPress-ytimen tiedostoja virallisiin tarkistussummiin (api.wordpress.org) ja tunnistaa muokatut tiedostot. Suorittaa myös päivittäisen automaattisen skannauksen wp-cronin kautta.',
		'settings' => '',
	],
	[
		'id'       => 'captcha',
		'icon'     => 'fact_check',
		'color'    => '#f97316',
		'bg'       => '#fff7ed',
		'name'     => 'Älykäs CAPTCHA',
		'badge'    => 'Määritettävissä',
		'badge_color' => '#d97706',
		'desc'     => 'Lisää Cloudflare Turnstile -CAPTCHA-haasteen kirjautumislomakkeeseen. Estää automaattiset lomakkeiden täyttörobotit ilman käyttäjälle ärsyttäviä kuvatunnistuksia.',
		'settings' => '',
	],
	[
		'id'       => 'telemetry',
		'icon'     => 'radar',
		'color'    => '#3b82f6',
		'bg'       => '#eff6ff',
		'name'     => 'Reaaliaikainen telemetria',
		'badge'    => 'Aktiivinen',
		'badge_color' => '#16a34a',
		'desc'     => 'Kirjaa teema- ja lisäosamuutokset tarkastuslokiin automaattisesti. Näyttää, kuka muutti mitä ja milloin — kriittinen jäljitettävyyttä varten.',
		'settings' => 'audit-log',
	],
	[
		'id'       => 'cache',
		'icon'     => 'cached',
		'color'    => '#16a34a',
		'bg'       => '#f0fdf4',
		'name'     => 'Suojattu välimuisti',
		'badge'    => 'Aktiivinen',
		'badge_color' => '#16a34a',
		'desc'     => 'Tallentaa IP-osoitteiden hakutulokset välimuistiin yksittäisen sivulatauksen ajaksi, jotta samaa IP:tä ei tarkisteta useaan kertaan. Parantaa suorituskykyä kuormitetuilla sivustoilla.',
		'settings' => '',
	],
	[
		'id'       => 'encryption',
		'icon'     => 'key',
		'color'    => '#a855f7',
		'bg'       => '#faf5ff',
		'name'     => 'Tietojen salaus',
		'badge'    => 'Aktiivinen',
		'badge_color' => '#16a34a',
		'desc'     => 'Tarjoaa AES-256-GCM-salausalgoritmiin perustuvat encrypt() ja decrypt() -metodit arkaluonteisten tietojen turvalliseen tallennukseen. Käyttöä wp-config.php:n AUTH_KEY-avainta.',
		'settings' => '',
	],
	[
		'id'       => 'appsec',
		'icon'     => 'app_blocking',
		'color'    => '#ef4444',
		'bg'       => '#fef2f2',
		'name'     => 'Sovellusturva',
		'badge'    => 'Aktiivinen',
		'badge_color' => '#16a34a',
		'desc'     => 'Sulkee XML-RPC-rajapinnan kokonaan ja rajoittaa REST API:n vain kirjautuneille käyttäjille. Estää kaksi yleisintä WordPress-kaukokäyttöhyökkäysreittiä.',
		'settings' => '',
	],
	[
		'id'       => 'audit',
		'icon'     => 'manage_search',
		'color'    => '#0d9488',
		'bg'       => '#f0fdfa',
		'name'     => 'Tarkastuspolku',
		'badge'    => 'Aktiivinen',
		'badge_color' => '#16a34a',
		'desc'     => 'Kirjaa kaikki merkittävät tapahtumat: kirjautumiset, uloskirjautumiset, epäonnistuneet kirjautumisyritykset, asetusmuutokset ja julkaisutoiminnot. Lokia voidaan viedä TXT-, CSV- tai PDF-muotoon.',
		'settings' => 'audit-log',
	],
	[
		'id'       => 'auth',
		'icon'     => 'admin_panel_settings',
		'color'    => '#6366f1',
		'bg'       => '#eef2ff',
		'name'     => 'Todennussuoja',
		'badge'    => 'Aktiivinen',
		'badge_color' => '#16a34a',
		'desc'     => 'Tarkistaa kirjautumiseen käytettyjen salasanojen turvallisuuden havaittujen tietovuotojen tietokannasta (HaveIBeenPwned) ja varoittaa, jos salasana on kompromissoitunut.',
		'settings' => 'advanced',
	],
	[
		'id'       => 'wizard',
		'icon'     => 'api',
		'color'    => '#3b82f6',
		'bg'       => '#eff6ff',
		'name'     => 'API-yhdyskäytävä',
		'badge'    => 'Määritettävissä',
		'badge_color' => '#d97706',
		'desc'     => 'Tarjoaa asennusvelhon, jolla voidaan valita Tiukka tai Rento tietoturvataso. Ohjattu käyttöönotto helpottaa ensimmäistä konfigurointia uusille ylläpitäjille.',
		'settings' => '',
	],
];
?>
<div class="ps-module" id="ps-module-modules-overview" style="display: none;">
	<div class="ps-module-header">
		<h2><span class="material-symbols-outlined">extension</span> Tietoturvamoduulit</h2>
		<button class="ps-module-close material-symbols-outlined">close</button>
	</div>
	<div class="ps-module-content">

		<div class="ps-info-banner" style="background: #eff6ff; border: 1px solid #bfdbfe; border-left: 4px solid #3b82f6; padding: 16px; border-radius: 6px; margin-bottom: 24px; display: flex; gap: 12px; align-items: start;">
			<span class="material-symbols-outlined" style="color: #3b82f6; font-size: 24px;">info</span>
			<div>
				<h4 style="margin: 0 0 4px 0; color: #1e3a8a; font-size: 15px;">Kaikki tietoturvamoduulit yhdellä sivulla</h4>
				<p style="margin: 0; color: #1d4ed8; font-size: 13px; line-height: 1.5;">Täällä voit hallita kaikkia <?php echo count($module_cards); ?> tietoturvamoduulia. Jokainen kortti kertoo mitä moduuli tekee ja miten se suojaa sivustoasi. Käytä kytkintä ottaaksesi moduulin käyttöön tai poistaaksesi sen käytöstä. Klikkaa <strong>Asetukset</strong>-painiketta siirtyöksesi moduulin tarkempiin asetuksiin.</p>
			</div>
		</div>

		<style>
		.pmc-modules-grid {
			display: grid;
			grid-template-columns: repeat(4, 1fr);
			gap: 16px;
		}
		@media (max-width: 1200px) { .pmc-modules-grid { grid-template-columns: repeat(3, 1fr); } }
		@media (max-width: 900px)  { .pmc-modules-grid { grid-template-columns: repeat(2, 1fr); } }
		@media (max-width: 600px)  { .pmc-modules-grid { grid-template-columns: 1fr; } }

		.pmc-mod-card {
			background: #fff;
			border: 1px solid #e5e7eb;
			border-radius: 14px;
			padding: 18px 18px 14px;
			display: flex;
			flex-direction: column;
			gap: 10px;
			transition: box-shadow 0.2s, border-color 0.2s, transform 0.15s;
			position: relative;
		}
		.pmc-mod-card:hover {
			box-shadow: 0 6px 24px rgba(0,0,0,0.09);
			border-color: #c7d2fe;
			transform: translateY(-2px);
		}
		.pmc-mod-card.is-disabled {
			opacity: 0.55;
		}
		.pmc-mod-card-top {
			display: flex;
			align-items: flex-start;
			justify-content: space-between;
			gap: 10px;
		}
		.pmc-mod-icon-wrap {
			width: 42px; height: 42px;
			border-radius: 10px;
			display: flex; align-items: center; justify-content: center;
			flex-shrink: 0;
		}
		.pmc-mod-badge {
			font-size: 10px;
			font-weight: 700;
			padding: 2px 8px;
			border-radius: 12px;
			letter-spacing: 0.3px;
			white-space: nowrap;
			background: rgba(0,0,0,0.06);
		}
		.pmc-mod-name {
			font-size: 14px;
			font-weight: 700;
			color: #111827;
			margin: 0;
			line-height: 1.3;
		}
		.pmc-mod-desc {
			font-size: 12px;
			color: #6b7280;
			line-height: 1.55;
			flex: 1;
			margin: 0;
		}
		.pmc-mod-footer {
			display: flex;
			align-items: center;
			justify-content: space-between;
			border-top: 1px solid #f3f4f6;
			padding-top: 10px;
			margin-top: 2px;
			gap: 8px;
		}
		.pmc-mod-settings-btn {
			font-size: 11px;
			font-weight: 600;
			color: #3b82f6;
			background: #eff6ff;
			border: none;
			border-radius: 6px;
			padding: 4px 10px;
			cursor: pointer;
			display: flex;
			align-items: center;
			gap: 4px;
			text-decoration: none;
			transition: background 0.15s;
		}
		.pmc-mod-settings-btn:hover { background: #dbeafe; color: #1d4ed8; }
		.pmc-mod-settings-btn-ghost {
			font-size: 11px;
			color: #9ca3af;
			background: none;
			border: none;
			padding: 4px 0;
			cursor: default;
		}
		
		/* Dynamic Badges */
		.pmc-mod-card .badge-disabled { display: none; }
		.pmc-mod-card.is-disabled .badge-active { display: none; }
		.pmc-mod-card.is-disabled .badge-disabled { display: inline-block; }
		</style>

		<div class="pmc-modules-grid">
		<?php foreach ($module_cards as $card):
			$is_active = $is_first_run ? true : !empty($active_modules[$card['id']]);
		?>
			<div class="pmc-mod-card <?php echo $is_active ? '' : 'is-disabled'; ?>" id="mod-card-<?php echo esc_attr($card['id']); ?>">
				<div class="pmc-mod-card-top">
					<div class="pmc-mod-icon-wrap" style="background: <?php echo esc_attr($card['bg']); ?>">
						<span class="material-symbols-outlined" style="color: <?php echo esc_attr($card['color']); ?>; font-size: 22px;"><?php echo esc_html($card['icon']); ?></span>
					</div>
					<div class="pmc-mod-badges">
						<span class="pmc-mod-badge badge-active" style="color: <?php echo esc_attr($card['badge_color']); ?>; background: <?php echo esc_attr($card['bg']); ?>;">
							<?php echo esc_html($card['badge']); ?>
						</span>
						<span class="pmc-mod-badge badge-disabled" style="color: #6b7280; background: #f3f4f6;">
							Ei aktiivinen
						</span>
					</div>
				</div>

				<h3 class="pmc-mod-name"><?php echo esc_html($card['name']); ?></h3>
				<p class="pmc-mod-desc"><?php echo esc_html($card['desc']); ?></p>

				<div class="pmc-mod-footer">
					<?php if (!empty($card['settings'])): ?>
						<button class="pmc-mod-settings-btn" onclick="psOpenModule('<?php echo esc_attr($card['settings']); ?>')">
							<span class="material-symbols-outlined" style="font-size:13px;">settings</span>
							Asetukset
						</button>
					<?php else: ?>
						<span class="pmc-mod-settings-btn-ghost">Ei erillisiä asetuksia</span>
					<?php endif; ?>

					<label class="toggle-wrap" title="<?php echo $is_active ? 'Poista käytöstä' : 'Ota käyttöön'; ?>">
						<input type="checkbox"
							class="module-toggle-checkbox"
							data-module="<?php echo esc_attr($card['id']); ?>"
							<?php echo $is_active ? 'checked' : ''; ?>
							onchange="pmcSaveActiveModules(); document.getElementById('mod-card-<?php echo esc_attr($card['id']); ?>').classList.toggle('is-disabled', !this.checked);"
						/>
						<span class="toggle-track"><span class="toggle-thumb"></span></span>
					</label>
				</div>
			</div>
		<?php endforeach; ?>
		</div>

	</div>
</div>
