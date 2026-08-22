import re

with open(r"C:\Users\pepeu\Local Sites\ht-toimistokalusteet2\app\public\wp-content\plugins\pecodex-security\admin-ui\src\SecurityApp.jsx", "r", encoding="utf-8") as f:
    text = f.read()

replacement = """<td className="px-4 py-3 font-mono text-xs">
                    <div className="flex items-center gap-1.5">
                      <button onClick={() => onSelect(connection.ip)} className="hover:text-[#b80048] hover:underline cursor-pointer font-bold">{connection.ip}</button>
                      {isCoordinate(connection.lat) && isCoordinate(connection.lng) && (
                        <MapPin className="h-3 w-3 text-[#b80048]" title="Sijainti kartalla" />
                      )}
                    </div>"""

text = text.replace('<td className="px-4 py-3 font-mono text-xs">\n                    <button onClick={() => onSelect(connection.ip)} className="hover:text-[#b80048] hover:underline cursor-pointer font-bold">{connection.ip}</button>', replacement)

with open(r"C:\Users\pepeu\Local Sites\ht-toimistokalusteet2\app\public\wp-content\plugins\pecodex-security\admin-ui\src\SecurityApp.jsx", "w", encoding="utf-8") as f:
    f.write(text)

print("Updated ConnectionTable.")