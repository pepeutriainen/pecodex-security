import re

with open(r"C:\Users\pepeu\Local Sites\ht-toimistokalusteet2\app\public\wp-content\plugins\pecodex-security\admin-ui\src\SecurityApp.jsx", "r", encoding="utf-8") as f:
    text = f.read()

# Add sorting to paginatedConnections
text = text.replace(
    """  const paginatedConnections = useMemo(() => {
    const start = (page - 1) * pageSize;
    return filteredConnections.slice(start, start + pageSize);
  }, [filteredConnections, page, pageSize]);""",
    """  const paginatedConnections = useMemo(() => {
    const start = (page - 1) * pageSize;
    // Sort descending by date (newest first)
    const sorted = [...filteredConnections].sort((a, b) => new Date(b.date) - new Date(a.date));
    return sorted.slice(start, start + pageSize);
  }, [filteredConnections, page, pageSize]);"""
)

with open(r"C:\Users\pepeu\Local Sites\ht-toimistokalusteet2\app\public\wp-content\plugins\pecodex-security\admin-ui\src\SecurityApp.jsx", "w", encoding="utf-8") as f:
    f.write(text)

print("Sorting added.")