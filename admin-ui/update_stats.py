import re

with open(r"C:\Users\pepeu\Local Sites\ht-toimistokalusteet2\app\public\wp-content\plugins\pecodex-security\admin-ui\src\SecurityApp.jsx", "r", encoding="utf-8") as f:
    text = f.read()

replacement = """  const stats = useMemo(() => {
    let total = 0, normal = 0, suspicious = 0, blocked = 0;
    radar.connections.forEach(connection => {
      if (filters.source !== 'all' && connection.source !== filters.source) return;
      if (filters.score === 'high' && Number(connection.threat_score || 0) < 60) return;
      if (filters.score === 'low' && Number(connection.threat_score || 0) >= 60) return;
      const matchSearch = [connection.ip, connection.country, connection.city, connection.endpoint, connection.type, connection.attack]
        .join(' ').toLowerCase().includes(filters.query.trim().toLowerCase());
      if (!matchSearch) return;

      total++;
      const s = String(connection.statusClass || connection.status || '').toLowerCase();
      if (s === 'critical' || s === 'blocked') blocked++;
      else if (s === 'warning' || s === 'suspicious') suspicious++;
      else normal++;
    });
    return {
      total_connections: total,
      normal_connections: normal,
      suspicious_connections: suspicious,
      blocked_connections: blocked,
      request_rate: radar.stats?.request_rate || 0
    };
  }, [radar.connections, radar.stats, filters.source, filters.score, filters.query]);"""

text = text.replace("const stats = radar.stats;", replacement)

with open(r"C:\Users\pepeu\Local Sites\ht-toimistokalusteet2\app\public\wp-content\plugins\pecodex-security\admin-ui\src\SecurityApp.jsx", "w", encoding="utf-8") as f:
    f.write(text)

print("Updated stats calculation.")