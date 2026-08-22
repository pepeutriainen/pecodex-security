import re

with open(r"C:\Users\pepeu\Local Sites\ht-toimistokalusteet2\app\public\wp-content\plugins\pecodex-security\admin-ui\src\SecurityApp.jsx", "r", encoding="utf-8") as f:
    text = f.read()

# Just inject setClientLoading right below setLoading
text = re.sub(
    r"(const \[loading, setLoading\] = useState\(true\);)",
    r"\1\n    const [clientLoading, setClientLoading] = useState(false);",
    text
)

with open(r"C:\Users\pepeu\Local Sites\ht-toimistokalusteet2\app\public\wp-content\plugins\pecodex-security\admin-ui\src\SecurityApp.jsx", "w", encoding="utf-8") as f:
    f.write(text)

print("Injected setClientLoading.")