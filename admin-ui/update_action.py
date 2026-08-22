import re

with open(r"C:\Users\pepeu\Local Sites\ht-toimistokalusteet2\app\public\wp-content\plugins\pecodex-security\admin-ui\src\SecurityApp.jsx", "r", encoding="utf-8") as f:
    text = f.read()

text = text.replace(
    "const actionName = isHistorical ? 'pmc_security_timelapse_data' : 'pmc_security_live_map_data';",
    "const actionName = 'pmc_security_live_map_data';"
)

with open(r"C:\Users\pepeu\Local Sites\ht-toimistokalusteet2\app\public\wp-content\plugins\pecodex-security\admin-ui\src\SecurityApp.jsx", "w", encoding="utf-8") as f:
    f.write(text)

print("Updated SecurityApp to always use live_map_data.")