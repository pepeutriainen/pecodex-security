import re

with open(r"C:\Users\pepeu\Local Sites\ht-toimistokalusteet2\app\public\wp-content\plugins\pecodex-security\admin-ui\src\SecurityApp.jsx", "r", encoding="utf-8") as f:
    text = f.read()

replacement = """  const mapEvents = useMemo(() => radar.events.filter((event) => {
      if (!isCoordinate(event.lat) || !isCoordinate(event.lng)) return false;
      
      // JOS EVENT ON "Ei paikannettu" (fallback koordinaatti), NÄYTETÄÄN SE VAIN JOS KÄYTTÄJÄ ON FILTTERÖINYT IP:N!
      if (event.city === 'Ei paikannettu' && !filters.query.trim()) return false;
"""

text = re.sub(r"const mapEvents = useMemo\(\(\) => radar\.events\.filter\(\(event\) => \{\s*if \(!isCoordinate\(event\.lat\) \|\| !isCoordinate\(event\.lng\)\) return false;", replacement, text)

with open(r"C:\Users\pepeu\Local Sites\ht-toimistokalusteet2\app\public\wp-content\plugins\pecodex-security\admin-ui\src\SecurityApp.jsx", "w", encoding="utf-8") as f:
    f.write(text)

print("Updated mapEvents filter.")