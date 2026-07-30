# Graph Report - .  (2026-07-30)

## Corpus Check
- 71 files · ~150,828 words
- Verdict: corpus is large enough that graph structure adds value.

## Summary
- 1103 nodes · 2697 edges · 77 communities (57 shown, 20 thin omitted)
- Extraction: 99% EXTRACTED · 1% INFERRED · 0% AMBIGUOUS · INFERRED: 35 edges (avg confidence: 0.8)
- Token cost: 0 input · 0 output

## Community Hubs (Navigation)
- [[_COMMUNITY_Community 0|Community 0]]
- [[_COMMUNITY_Community 1|Community 1]]
- [[_COMMUNITY_Community 2|Community 2]]
- [[_COMMUNITY_Community 4|Community 4]]
- [[_COMMUNITY_Community 6|Community 6]]
- [[_COMMUNITY_Community 9|Community 9]]
- [[_COMMUNITY_Community 10|Community 10]]
- [[_COMMUNITY_Community 11|Community 11]]
- [[_COMMUNITY_Community 12|Community 12]]
- [[_COMMUNITY_Community 13|Community 13]]
- [[_COMMUNITY_Community 14|Community 14]]
- [[_COMMUNITY_Community 15|Community 15]]
- [[_COMMUNITY_Community 18|Community 18]]
- [[_COMMUNITY_Community 19|Community 19]]
- [[_COMMUNITY_Community 21|Community 21]]
- [[_COMMUNITY_Community 23|Community 23]]
- [[_COMMUNITY_Community 24|Community 24]]
- [[_COMMUNITY_Community 25|Community 25]]
- [[_COMMUNITY_Community 26|Community 26]]
- [[_COMMUNITY_Community 27|Community 27]]
- [[_COMMUNITY_Community 30|Community 30]]
- [[_COMMUNITY_Community 31|Community 31]]
- [[_COMMUNITY_Community 35|Community 35]]
- [[_COMMUNITY_Community 36|Community 36]]
- [[_COMMUNITY_Community 37|Community 37]]
- [[_COMMUNITY_Community 38|Community 38]]
- [[_COMMUNITY_Community 39|Community 39]]
- [[_COMMUNITY_Community 40|Community 40]]
- [[_COMMUNITY_Community 41|Community 41]]
- [[_COMMUNITY_Community 42|Community 42]]
- [[_COMMUNITY_Community 43|Community 43]]
- [[_COMMUNITY_Community 49|Community 49]]
- [[_COMMUNITY_Community 50|Community 50]]
- [[_COMMUNITY_Community 51|Community 51]]

## God Nodes (most connected - your core abstractions)
1. `PGM_Private_Gutenberg_Media` - 333 edges
2. `PGM_Media_Organizer` - 148 edges
3. `t()` - 61 edges
4. `Pecodex_Security_API` - 61 edges
5. `icon()` - 26 edges
6. `renderFolderNode()` - 22 edges
7. `Pecodex_Firewall` - 20 edges
8. `refreshGridIfPossible()` - 19 edges
9. `renderAllSidebars()` - 19 edges
10. `renderSettingsMain()` - 18 edges

## Surprising Connections (you probably didn't know these)
- `persistSidebarWidth()` --calls--> `normalizeOptions()`  [EXTRACTED]
  assets/media-organizer.js → assets/media-organizer.js  _Bridges community 10 → community 26_
- `assignAttachmentsToFolder()` --calls--> `t()`  [EXTRACTED]
  assets/media-organizer.js → assets/media-organizer.js  _Bridges community 10 → community 14_
- `buildSecondaryToolbar()` --calls--> `t()`  [EXTRACTED]
  assets/media-organizer.js → assets/media-organizer.js  _Bridges community 10 → community 27_
- `createMediaDragGhost()` --calls--> `t()`  [EXTRACTED]
  assets/media-organizer.js → assets/media-organizer.js  _Bridges community 10 → community 0_
- `performCreateFolder()` --calls--> `t()`  [EXTRACTED]
  assets/media-organizer.js → assets/media-organizer.js  _Bridges community 10 → community 11_

## Import Cycles
- None detected.

## Communities (77 total, 20 thin omitted)

### Community 0 - "Community 0"
Cohesion: 0.06
Nodes (56): appendOptionChoices(), applyAttachmentDataMapToModels(), applyAttachmentDataToModel(), applyBodyClasses(), attachmentDataFileType(), attachmentDragIconSrc(), attachmentDragMeta(), attachmentDragThumbnail() (+48 more)

### Community 2 - "Community 2"
Cohesion: 0.05
Nodes (6): Pecodex_Advanced_Security, Pecodex_Bot_Protection, Pecodex_Firewall, Pecodex_GeoIP, Pecodex_Rate_Limit, Pecodex_Telemetry

### Community 4 - "Community 4"
Cohesion: 0.05
Nodes (6): Pecodex_Cron, Pecodex_Deep_Scanner, Pecodex_Notifications, Pecodex_Vulnerabilities, Pecodex_WAF, Pecodex_Zero_Trust

### Community 10 - "Community 10"
Cohesion: 0.13
Nodes (36): aboutRow(), appendAboutSettingsCard(), applyColumnVisibility(), attributeFields(), attributeGroups(), displayAlignChoices(), displayDefaultsSummaryText(), displayLinkChoices() (+28 more)

### Community 11 - "Community 11"
Cohesion: 0.12
Nodes (33): applyMediaQueryPropsToObject(), applyMediaQueryPropsToString(), applyPropsToLibrary(), checkPecodexSyncStatus(), currentLibrary(), currentMediaFrame(), extractMediaQueryFiltersFromObject(), folderUrl() (+25 more)

### Community 12 - "Community 12"
Cohesion: 0.11
Nodes (32): assignSelectedToFolder(), cancelFolderAutoExpand(), canReorderFolder(), clearFolderDragClasses(), clearFolderReorderClasses(), clearMediaDragTarget(), enableDragging(), finishMediaPointerDrag() (+24 more)

### Community 13 - "Community 13"
Cohesion: 0.14
Nodes (31): attachmentPreviewUrl(), buildAttachmentDetailPreview(), buildAttachmentIcon(), buildThumbnailPreviewLoader(), cloneArrayBuffer(), configurePdfWorker(), enhanceAttachmentDetailPreview(), fetchPreviewArrayBuffer() (+23 more)

### Community 14 - "Community 14"
Cohesion: 0.17
Nodes (29): ajax(), assignAttachmentsToFolder(), buildBulkPrivacyToolbar(), bulkTogglePrivacy(), clearAttachmentSelection(), currentAttachmentSelection(), deleteAttachmentsNow(), duplicateAttachmentsNow() (+21 more)

### Community 15 - "Community 15"
Cohesion: 0.18
Nodes (26): adminConfig(), appendSourceLine(), appendSourceList(), applyAttachmentDataToModel(), attachmentModelFromElement(), attachmentStatus(), badgeTitle(), buildShareUrl() (+18 more)

### Community 18 - "Community 18"
Cohesion: 0.08
Nodes (23): dependencies, lucide-react, react, react-dom, @tailwindcss/vite, devDependencies, autoprefixer, oxlint (+15 more)

### Community 21 - "Community 21"
Cohesion: 0.17
Nodes (18): addPrivateMediaAttribute(), blockAttachmentId(), blockMediaUrls(), comparableUrl(), cssEscape(), decodeSafely(), directBadgeChild(), editorFrameDocuments() (+10 more)

### Community 26 - "Community 26"
Cohesion: 0.20
Nodes (15): applySidebarWidth(), buildPrimaryToolbar(), buildSidebar(), buildSidebarResizer(), clampSidebarWidth(), createFolder(), expandRootFolders(), folderAccessChoices() (+7 more)

### Community 27 - "Community 27"
Cohesion: 0.22
Nodes (14): appendContextAction(), buildSecondaryToolbar(), closeContextMenu(), compactContextActions(), deleteFolder(), exportUrl(), focusContextMenuAction(), handleContextMenuKeydown() (+6 more)

### Community 37 - "Community 37"
Cohesion: 0.33
Nodes (5): plugins, rules, react/only-export-components, react/rules-of-hooks, $schema

## Knowledge Gaps
- **24 isolated node(s):** `$schema`, `plugins`, `react/rules-of-hooks`, `react/only-export-components`, `name` (+19 more)
  These have ≤1 connection - possible missing edges or undocumented components.
- **20 thin communities (<3 nodes) omitted from report** — run `graphify query` to explore isolated nodes.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **Why does `PGM_Private_Gutenberg_Media` connect `Community 6` to `Community 2`, `Community 3`, `Community 4`, `Community 5`, `Community 7`, `Community 8`, `Community 17`, `Community 20`, `Community 22`, `Community 32`, `Community 33`, `Community 34`, `Community 44`, `Community 45`, `Community 46`, `Community 47`, `Community 48`, `Community 52`, `Community 53`, `Community 54`, `Community 55`, `Community 56`, `Community 57`?**
  _High betweenness centrality (0.210) - this node is a cross-community bridge._
- **Why does `PGM_Media_Organizer` connect `Community 9` to `Community 33`, `Community 2`, `Community 4`, `Community 7`, `Community 8`, `Community 16`, `Community 17`, `Community 19`, `Community 22`, `Community 28`, `Community 29`?**
  _High betweenness centrality (0.115) - this node is a cross-community bridge._
- **Why does `Pecodex_Security_API` connect `Community 1` to `Community 25`, `Community 4`?**
  _High betweenness centrality (0.097) - this node is a cross-community bridge._
- **Are the 2 inferred relationships involving `PGM_Private_Gutenberg_Media` (e.g. with `.ajax_save_private_settings()` and `.private_media_diagnostics_payload()`) actually correct?**
  _`PGM_Private_Gutenberg_Media` has 2 INFERRED edges - model-reasoned connections that need verification._
- **Are the 10 inferred relationships involving `PGM_Media_Organizer` (e.g. with `.activate()` and `.attachment_effective_folder_access()`) actually correct?**
  _`PGM_Media_Organizer` has 10 INFERRED edges - model-reasoned connections that need verification._
- **What connects `$schema`, `plugins`, `react/rules-of-hooks` to the rest of the system?**
  _24 weakly-connected nodes found - possible documentation gaps or missing edges._
- **Should `Community 0` be split into smaller, more focused modules?**
  _Cohesion score 0.059907834101382486 - nodes in this community are weakly interconnected._