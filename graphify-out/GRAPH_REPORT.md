# Graph Report - .  (2026-07-30)

## Corpus Check
- 65 files · ~91,770 words
- Verdict: corpus is large enough that graph structure adds value.

## Summary
- 668 nodes · 1415 edges · 53 communities (36 shown, 17 thin omitted)
- Extraction: 98% EXTRACTED · 2% INFERRED · 0% AMBIGUOUS · INFERRED: 23 edges (avg confidence: 0.8)
- Token cost: 0 input · 0 output

## Community Hubs (Navigation)
- [[_COMMUNITY_Community 0|Community 0]]
- [[_COMMUNITY_Community 1|Community 1]]
- [[_COMMUNITY_Community 4|Community 4]]
- [[_COMMUNITY_Community 5|Community 5]]
- [[_COMMUNITY_Community 7|Community 7]]
- [[_COMMUNITY_Community 9|Community 9]]
- [[_COMMUNITY_Community 10|Community 10]]
- [[_COMMUNITY_Community 11|Community 11]]
- [[_COMMUNITY_Community 12|Community 12]]
- [[_COMMUNITY_Community 15|Community 15]]
- [[_COMMUNITY_Community 19|Community 19]]
- [[_COMMUNITY_Community 20|Community 20]]
- [[_COMMUNITY_Community 21|Community 21]]
- [[_COMMUNITY_Community 22|Community 22]]
- [[_COMMUNITY_Community 23|Community 23]]
- [[_COMMUNITY_Community 25|Community 25]]
- [[_COMMUNITY_Community 26|Community 26]]
- [[_COMMUNITY_Community 27|Community 27]]
- [[_COMMUNITY_Community 28|Community 28]]
- [[_COMMUNITY_Community 33|Community 33]]
- [[_COMMUNITY_Community 34|Community 34]]
- [[_COMMUNITY_Community 35|Community 35]]

## God Nodes (most connected - your core abstractions)
1. `PGM_Private_Gutenberg_Media` - 331 edges
2. `Pecodex_Security_API` - 61 edges
3. `Pecodex_Firewall` - 20 edges
4. `Pecodex_Audit` - 15 edges
5. `Pecodex_Hardening` - 15 edges
6. `Pecodex_Notifications` - 11 edges
7. `Pecodex_Authentication` - 10 edges
8. `Pecodex_WebAuthn` - 10 edges
9. `Pecodex_Advanced_Security` - 9 edges
10. `Pecodex_Rate_Limit` - 9 edges

## Surprising Connections (you probably didn't know these)
- None detected - all connections are within the same source files.

## Import Cycles
- None detected.

## Communities (53 total, 17 thin omitted)

### Community 4 - "Community 4"
Cohesion: 0.05
Nodes (6): Pecodex_Advanced_Security, Pecodex_Bot_Protection, Pecodex_Firewall, Pecodex_GeoIP, Pecodex_Rate_Limit, Pecodex_Telemetry

### Community 5 - "Community 5"
Cohesion: 0.05
Nodes (6): Pecodex_Cron, Pecodex_Deep_Scanner, Pecodex_Notifications, Pecodex_Vulnerabilities, Pecodex_WAF, Pecodex_Zero_Trust

### Community 7 - "Community 7"
Cohesion: 0.08
Nodes (23): dependencies, lucide-react, react, react-dom, @tailwindcss/vite, devDependencies, autoprefixer, oxlint (+15 more)

### Community 9 - "Community 9"
Cohesion: 0.17
Nodes (18): addPrivateMediaAttribute(), blockAttachmentId(), blockMediaUrls(), comparableUrl(), cssEscape(), decodeSafely(), directBadgeChild(), editorFrameDocuments() (+10 more)

### Community 21 - "Community 21"
Cohesion: 0.33
Nodes (5): plugins, rules, react/only-export-components, react/rules-of-hooks, $schema

## Knowledge Gaps
- **24 isolated node(s):** `$schema`, `plugins`, `react/rules-of-hooks`, `react/only-export-components`, `name` (+19 more)
  These have ≤1 connection - possible missing edges or undocumented components.
- **17 thin communities (<3 nodes) omitted from report** — run `graphify query` to explore isolated nodes.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **Why does `PGM_Private_Gutenberg_Media` connect `Community 0` to `Community 32`, `Community 2`, `Community 3`, `Community 4`, `Community 5`, `Community 6`, `Community 8`, `Community 13`, `Community 14`, `Community 16`, `Community 17`, `Community 18`, `Community 24`, `Community 29`, `Community 30`, `Community 31`?**
  _High betweenness centrality (0.479) - this node is a cross-community bridge._
- **Why does `Pecodex_Firewall` connect `Community 4` to `Community 1`, `Community 5`?**
  _High betweenness centrality (0.149) - this node is a cross-community bridge._
- **Why does `Pecodex_Security_API` connect `Community 1` to `Community 12`, `Community 5`?**
  _High betweenness centrality (0.145) - this node is a cross-community bridge._
- **Are the 9 inferred relationships involving `Pecodex_Firewall` (e.g. with `.mask_login_area()` and `.check_bot_signatures()`) actually correct?**
  _`Pecodex_Firewall` has 9 INFERRED edges - model-reasoned connections that need verification._
- **What connects `$schema`, `plugins`, `react/rules-of-hooks` to the rest of the system?**
  _24 weakly-connected nodes found - possible documentation gaps or missing edges._
- **Should `Community 0` be split into smaller, more focused modules?**
  _Cohesion score 0.03825136612021858 - nodes in this community are weakly interconnected._
- **Should `Community 1` be split into smaller, more focused modules?**
  _Cohesion score 0.059322033898305086 - nodes in this community are weakly interconnected._