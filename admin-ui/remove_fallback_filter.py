import re

with open(r"C:\Users\pepeu\Local Sites\ht-toimistokalusteet2\app\public\wp-content\plugins\pecodex-security\admin-ui\src\SecurityApp.jsx", "r", encoding="utf-8") as f:
    text = f.read()

text = re.sub(r"// JOS EVENT ON.*?if \(event\.city === 'Ei paikannettu' && !filters\.query\.trim\(\)\) return false;\s*", "", text, flags=re.DOTALL)

with open(r"C:\Users\pepeu\Local Sites\ht-toimistokalusteet2\app\public\wp-content\plugins\pecodex-security\admin-ui\src\SecurityApp.jsx", "w", encoding="utf-8") as f:
    f.write(text)

print("Removed fallback filter.")