import re

file_path = r'c:\Users\pepeu\Local Sites\tem-kumpulanspy\app\public\wp-content\plugins\pecodex-media-control\templates\security-dashboard.php'
with open(file_path, 'r', encoding='utf-8') as f:
    content = f.read()

replacements = {
    "'Unknown error'": "'Tuntematon virhe'",
    "'Failed to save header: '": "'Otsikon tallennus epäonnistui: '",
    "'Failed to fetch live events:'": "'Live-tapahtumien nouto epäonnistui:'",
    "\"Pecodex Security: Master data fetch failed\"": "\"Pecodex Security: Päätietojen nouto epäonnistui\"",
    "\"Failed to save active modules\"": "\"Aktiivisten moduulien tallennus epäonnistui\"",
    
    # TWEAK_DEFS
    'title: "Disable XML-RPC"': 'title: "Poista XML-RPC käytöstä"',
    'desc: "Prevent XML-RPC brute force attacks."': 'desc: "Estä XML-RPC brute force -hyökkäykset."',
    'overview: "XML-RPC is often targeted by brute force and DDoS attacks. If you don\'t use the WordPress mobile app or services like Jetpack, it\'s safer to disable it."': 'overview: "XML-RPC on usein brute force - ja DDoS-hyökkäysten kohteena. Jos et käytä WordPress-mobiilisovellusta tai palveluja kuten Jetpack, on turvallisempaa poistaa se käytöstä."',
    'fix: "We will disable XML-RPC to secure your site against these automated attacks."': 'fix: "Poistamme XML-RPC:n käytöstä suojataksemme sivustoasi näiltä automatisoiduilta hyökkäyksiltä."',
    
    'title: "Disable File Editor"': 'title: "Poista Tiedostoeditori käytöstä"',
    'desc: "Disable the built-in theme/plugin file editor."': 'desc: "Poista käytöstä sisäänrakennettu teema-/lisäosaeditori."',
    'overview: "WordPress has a built-in file editor. If an attacker gains admin access, they can use this to inject malicious code. Disabling it adds a critical layer of defense."': 'overview: "WordPressissä on sisäänrakennettu tiedostoeditori. Jos hyökkääjä saa ylläpito-oikeudet, he voivat käyttää tätä haitallisen koodin syöttämiseen. Sen poistaminen lisää kriittisen puolustuskerroksen."',
    'fix: "We will define DISALLOW_FILE_EDIT in your wp-config.php file to block access."': 'fix: "Määritämme DISALLOW_FILE_EDIT wp-config.php -tiedostoosi estääksemme pääsyn."',
    
    'title: "Hide WP Version"': 'title: "Piilota WP-versio"',
    'desc: "Remove WordPress version from HTML output."': 'desc: "Poista WordPressin versio HTML-tulosteesta."',
    'overview: "WordPress automatically prints its version number in the site\'s source code. Hackers scan for this to target sites running outdated, vulnerable versions."': 'overview: "WordPress tulostaa automaattisesti versionumeronsa sivuston lähdekoodiin. Hakkerit skannaavat tätä kohdistaakseen hyökkäyksiä sivustoihin, joissa on vanhentuneita, haavoittuvia versioita."',
    'fix: "We will hide the WordPress version from your public-facing HTML output."': 'fix: "Piilotamme WordPressin version julkisesta HTML-tulosteestasi."',
    'action_text: "Update WordPress"': 'action_text: "Päivitä WordPress"',
    
    'title: "Prevent User Enumeration"': 'title: "Estä Käyttäjien Luetteleminen"',
    'desc: "Block bots from discovering usernames."': 'desc: "Estä botteja löytämästä käyttäjätunnuksia."',
    'overview: "By default, attackers can easily discover your site\'s usernames by scanning author archives. This makes brute-force attacks much easier."': 'overview: "Oletuksena hyökkääjät voivat helposti löytää sivustosi käyttäjätunnukset skannaamalla kirjoittaja-arkistoja. Tämä tekee brute-force -hyökkäyksistä paljon helpompia."',
    'fix: "We will block scripts that attempt to automatically enumerate your users."': 'fix: "Estämme skriptit, jotka yrittävät automaattisesti luetella käyttäjiäsi."',
    
    'title: "Hide PHP Errors"': 'title: "Piilota PHP-virheet"',
    'desc: "Prevent errors from displaying on the frontend."': 'desc: "Estä virheiden näkyminen julkisivulla."',
    'overview: "PHP errors can reveal sensitive server paths, variables, and database structures to attackers."': 'overview: "PHP-virheet voivat paljastaa hyökkääjille arkaluonteisia palvelinpolkuja, muuttujia ja tietokantarakenteita."',
    'fix: "We will ensure WP_DEBUG_DISPLAY is disabled to keep errors hidden from visitors."': 'fix: "Varmistamme, että WP_DEBUG_DISPLAY on poistettu käytöstä, jotta virheet pysyvät piilossa vierailijoilta."',
    
    'title: "Change \'admin\' Username"': 'title: "Vaihda \'admin\'-käyttäjätunnus"',
    'desc: "Ensure no user has the default \'admin\' username."': 'desc: "Varmista, ettei kenelläkään käyttäjällä ole oletus \'admin\'-tunnusta."',
    'overview: "The username \'admin\' is the first thing hackers try when attempting to brute force into your site."': 'overview: "Käyttäjätunnus \'admin\' on ensimmäinen asia, jota hakkerit yrittävät yrittäessään brute force -hyökkäystä sivustollesi."',
    'fix: "Create a new administrator account with a different username, then delete the old \'admin\' account."': 'fix: "Luo uusi järjestelmänvalvojan tili eri käyttäjätunnuksella, ja poista sitten vanha \'admin\'-tili."',
    'action_text: "Action required in WP Users"': 'action_text: "Toimenpide vaaditaan WP-Käyttäjissä"',
    
    'title: "Login Duration"': 'title: "Kirjautumisen Kesto"',
    'desc: "Reduce how long users stay logged in."': 'desc: "Vähennä sitä aikaa, jonka käyttäjät pysyvät kirjautuneina."',
    'overview: "By default, users checking \'remember me\' stay logged in for 14 days. Reducing this limits the window an attacker can exploit an abandoned session."': 'overview: "Oletuksena käyttäjät, jotka valitsevat \'muista minut\', pysyvät kirjautuneina 14 päivää. Tämän vähentäminen rajoittaa ikkunaa, jolloin hyökkääjä voi hyödyntää hylättyä istuntoa."',
    'fix: "We will reduce the default login duration."': 'fix: "Vähennämme oletuskirjautumisen kestoa."',
    
    'title: "Disable Trackbacks"': 'title: "Poista Paluuviitteet käytöstä"',
    'desc: "Prevent trackback and pingback spam."': 'desc: "Estä trackback- ja pingback-roskaposti."',
    'overview: "Trackbacks and pingbacks are largely used for spam and can be exploited for DDoS attacks."': 'overview: "Paluuviitteitä ja takaisinkutsuja käytetään paljolti roskapostiin ja niitä voidaan hyödyntää DDoS-hyökkäyksissä."',
    'fix: "We will disable trackbacks and pingbacks globally on your site."': 'fix: "Poistamme paluuviitteet ja takaisinkutsut käytöstä koko sivustoltasi."',
    
    'title: "Protect Information"': 'title: "Suojaa Tiedot"',
    'desc: "Prevent access to core files like readme.html."': 'desc: "Estä pääsy ydintiedostoihin kuten readme.html."',
    'overview: "Files like readme.html or backup files can reveal sensitive data about your environment."': 'overview: "Tiedostot kuten readme.html tai varmuuskopiotiedostot voivat paljastaa arkaluonteisia tietoja ympäristöstäsi."',
    'fix: "We will lock down these files to ensure they cannot be accessed by hackers."': 'fix: "Lukitsemme nämä tiedostot varmistaaksemme, etteivät hakkerit pääse niihin käsiksi."',
    
    'title: "Update PHP"': 'title: "Päivitä PHP"',
    'desc: "Ensure you are using a supported PHP version."': 'desc: "Varmista, että käytät tuettua PHP-versiota."',
    'overview: "Running outdated PHP versions exposes your server to unpatched vulnerabilities."': 'overview: "Vanhentuneiden PHP-versioiden suorittaminen altistaa palvelimesi korjaamattomille haavoittuvuuksille."',
    'fix: "Please upgrade your PHP version to the latest stable release via your hosting panel."': 'fix: "Päivitä PHP-versiosi uusimpaan vakaaseen julkaisuun hosting-paneelisi kautta."',
    'action_text: "Update via Hosting Panel"': 'action_text: "Päivitä Hosting-paneelista"',
    
    'title: "Prevent PHP Execution"': 'title: "Estä PHP:n Suoritus"',
    'desc: "Block PHP execution in uploads directory."': 'desc: "Estä PHP:n suoritus lataushakemistossa."',
    'overview: "The uploads folder should only contain media. If a hacker uploads a malicious PHP script here, this prevents it from executing."': 'overview: "Latauskansion pitäisi sisältää vain mediaa. Jos hakkeri lataa tänne haitallisen PHP-skriptin, tämä estää sen suorittamisen."',
    'fix: "We will place protections in the uploads directory to prevent PHP execution."': 'fix: "Asetamme suojauksia lataushakemistoon estääksemme PHP:n suorittamisen."',
    
    'title: "Security Keys"': 'title: "Tietoturva-avaimet"',
    'desc: "Ensure security keys are set in wp-config.php."': 'desc: "Varmista, että tietoturva-avaimet on asetettu wp-config.php -tiedostossa."',
    'overview: "Security keys improve the encryption of information stored in the user\'s cookies."': 'overview: "Tietoturva-avaimet parantavat käyttäjän evästeisiin tallennettujen tietojen salausta."',
    'fix: "We will ensure fresh security keys are generated and applied."': 'fix: "Varmistamme, että uudet tietoturva-avaimet luodaan ja otetaan käyttöön."',
    'action_text: "Regenerate Keys"': 'action_text: "Luo Avaimet Uudelleen"',

    # HEADER_DEFS
    'title: "Strict Transport Security (HSTS)"': 'title: "Strict Transport Security (HSTS)"',
    'desc: "Enforces secure (HTTP over SSL/TLS) connections to the server."': 'desc: "Pakottaa turvalliset (HTTP yli SSL/TLS) yhteydet palvelimeen."',
    
    'title: "X-Frame-Options"': 'title: "X-Frame-Options"',
    'desc: "Protects against clickjacking."': 'desc: "Suojaa clickjackingilta (napin kaappaukselta)."',
    
    'title: "X-XSS-Protection"': 'title: "X-XSS-Protection"',
    'desc: "Stops pages from loading when they detect reflected cross-site scripting (XSS) attacks."': 'desc: "Estää sivujen latautumisen, kun ne havaitsevat heijastuvia cross-site scripting (XSS) -hyökkäyksiä."',
    
    'title: "X-Content-Type-Options"': 'title: "X-Content-Type-Options"',
    'desc: "Stops the browser from trying to MIME-sniff the content type."': 'desc: "Estää selainta yrittämästä MIME-nuuskia sisältötyyppiä."',
    
    'title: "Referrer-Policy"': 'title: "Referrer-Policy"',
    'desc: "Governs which referrer information should be included with requests made."': 'desc: "Määrittelee, mitä viittaustietoja tulisi sisällyttää tehtyihin pyyntöihin."',
    
    'title: "Permissions-Policy"': 'title: "Permissions-Policy"',
    'desc: "Provides mechanisms to enable, and disable the use of browser features."': 'desc: "Tarjoaa mekanismeja selaimen ominaisuuksien käyttöönottoon ja poistamiseen."',
    
    # Missing words
    'No recommendations at this time.': 'Ei suosituksia tällä hetkellä.',
    'No tweaks have been actioned yet.': 'Yhtään asetusta ei ole vielä aktivoitu.',
    
}

for k, v in replacements.items():
    content = content.replace(k, v)

with open(file_path, 'w', encoding='utf-8') as f:
    f.write(content)

print("Translations part 2 applied successfully.")
