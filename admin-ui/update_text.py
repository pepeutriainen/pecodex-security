import re

with open(r"C:\Users\pepeu\Local Sites\ht-toimistokalusteet2\app\public\wp-content\plugins\pecodex-security\admin-ui\src\SecurityApp.jsx", "r", encoding="utf-8") as f:
    text = f.read()

text = text.replace(
    '<Globe className="h-3.5 w-3.5 text-[#b80048]" />\n            Uhkaradar\n          </div>',
    '<Globe className="h-3.5 w-3.5 text-[#b80048]" />\n            Yhteydet\n          </div>'
)

with open(r"C:\Users\pepeu\Local Sites\ht-toimistokalusteet2\app\public\wp-content\plugins\pecodex-security\admin-ui\src\SecurityApp.jsx", "w", encoding="utf-8") as f:
    f.write(text)

print("Updated text.")