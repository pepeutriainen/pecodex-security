import re

with open(r"C:\Users\pepeu\Local Sites\ht-toimistokalusteet2\app\public\wp-content\plugins\pecodex-security\admin-ui\src\SecurityApp.jsx", "r", encoding="utf-8") as f:
    text = f.read()

text = text.replace(
    "const selectIp = (ip) => setFilters((current) => ({ ...current, query: ip }));",
    "const selectIp = (ip) => { setFilters((current) => ({ ...current, query: ip })); setPage(1); };"
)

with open(r"C:\Users\pepeu\Local Sites\ht-toimistokalusteet2\app\public\wp-content\plugins\pecodex-security\admin-ui\src\SecurityApp.jsx", "w", encoding="utf-8") as f:
    f.write(text)

print("Updated selectIp.")