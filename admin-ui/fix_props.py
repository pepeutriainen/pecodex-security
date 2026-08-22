import re

with open(r"C:\Users\pepeu\Local Sites\ht-toimistokalusteet2\app\public\wp-content\plugins\pecodex-security\admin-ui\src\SecurityApp.jsx", "r", encoding="utf-8") as f:
    text = f.read()

# Fix RadarMap loading prop
text = re.sub(
    r"<RadarMap\s*loading=\{loading\}",
    "<RadarMap\n            loading={loading || clientLoading}",
    text
)

# Fix ConnectionTable loading prop
text = re.sub(
    r"<ConnectionTable\s*loading=\{loading\}",
    "<ConnectionTable\n              loading={loading || clientLoading}",
    text
)

with open(r"C:\Users\pepeu\Local Sites\ht-toimistokalusteet2\app\public\wp-content\plugins\pecodex-security\admin-ui\src\SecurityApp.jsx", "w", encoding="utf-8") as f:
    f.write(text)

print("Props fixed.")