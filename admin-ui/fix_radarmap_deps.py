import re

with open(r"C:\Users\pepeu\Local Sites\ht-toimistokalusteet2\app\public\wp-content\plugins\pecodex-security\admin-ui\src\SecurityApp.jsx", "r", encoding="utf-8") as f:
    text = f.read()

# Fix RadarMap useEffect dependency array
text = text.replace(
    "}, [events, hoveredId, onHover, onSelect, ready, server]);",
    "}, [events, hoveredId, onHover, onSelect, ready, server, loading]);"
)

with open(r"C:\Users\pepeu\Local Sites\ht-toimistokalusteet2\app\public\wp-content\plugins\pecodex-security\admin-ui\src\SecurityApp.jsx", "w", encoding="utf-8") as f:
    f.write(text)

print("Added loading to RadarMap useEffect dependencies.")