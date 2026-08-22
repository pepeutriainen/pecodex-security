import re

with open(r"C:\Users\pepeu\Local Sites\ht-toimistokalusteet2\app\public\wp-content\plugins\pecodex-security\admin-ui\src\SecurityApp.jsx", "r", encoding="utf-8") as f:
    text = f.read()

text = text.replace(
    "if (!quiet) { setLoading(true); window.dispatchEvent(new Event('pmcRadarDataLoading')); }",
    "if (!quiet) { setLoading(true); setPage(1); window.dispatchEvent(new Event('pmcRadarDataLoading')); }"
)

with open(r"C:\Users\pepeu\Local Sites\ht-toimistokalusteet2\app\public\wp-content\plugins\pecodex-security\admin-ui\src\SecurityApp.jsx", "w", encoding="utf-8") as f:
    f.write(text)

print("Added setPage(1) to loadRadar.")