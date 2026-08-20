<?php
/**
 * Security Dashboard Template
 * Pecodex Security ä?" Cybersecurity dashboard rendered inside WordPress admin.
 * This file is include()'d by render_security_dashboard_page().
 * Assets (Leaflet, SortableJS, fonts) are enqueued in enqueue_security_dashboard_assets().
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<style>
/* ä"?ä"? Base reset (scoped) ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"? */
*, *::before, *::after { box-sizing: border-box; }

/* ä"?ä"? Material Symbols ä?" force correct rendering inside WP admin ä"?ä"? */
#ps-shell .material-symbols-outlined {
  font-family: 'Material Symbols Outlined', sans-serif !important;
  font-weight: normal;
  font-style: normal;
  font-size: 20px;
  line-height: 1;
  letter-spacing: normal;
  text-transform: none;
  display: inline-block;
  white-space: nowrap;
  word-wrap: normal;
  direction: ltr;
  font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
  -webkit-font-feature-settings: 'liga';
  font-feature-settings: 'liga';
  -webkit-font-smoothing: antialiased;
}

/* ä"?ä"? Inter font for the shell ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"? */
#ps-shell, #threat-modal, #lockdown-overlay {
  font-family: 'Inter', system-ui, -apple-system, sans-serif;
}

/* WordPress Admin Full-Height Resets */
html.wp-toolbar {
  overflow: hidden !important;
}
body.admin-bar {
  overflow: hidden !important;
}
#wpfooter {
  display: none !important;
}
#wpbody-content {
  padding: 0 !important;
  float: none !important;
}
#pecodex-security-dashboard-wrap,
#pecodex-security-dashboard-wrap .wrap {
  margin: 0 !important;
  padding: 0 !important;
  max-width: none !important;
  width: 100% !important;
  height: calc(100vh - 32px) !important;
  max-height: calc(100vh - 32px) !important;
  overflow: hidden !important;
}
@media screen and (max-width: 782px) {
  #pecodex-security-dashboard-wrap,
  #pecodex-security-dashboard-wrap .wrap {
    height: calc(100vh - 46px) !important;
    max-height: calc(100vh - 46px) !important;
  }
}

/* ── Layout ── */
#ps-shell {
  display: flex;
  width: 100%;
  height: 100%;
  max-height: 100%;
  min-height: 100%;
  overflow: hidden;
  background: #f8fafc;
  font-family: 'Inter', system-ui, sans-serif;
}

/* ä"?ä"? Sidebar ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"? */
#ps-sidebar {
  width: 200px;
  min-width: 200px;
  background: #ffffff;
  display: flex;
  flex-direction: column;
  border-right: 1px solid #e5e7eb;
  z-index: 100;
  flex-shrink: 0;
  transition: width 0.28s cubic-bezier(.4,0,.2,1), min-width 0.28s;
  overflow: hidden;
  box-shadow: 2px 0 8px rgba(0,0,0,0.05);
}
#ps-sidebar.collapsed { width: 60px; min-width: 60px; }

.sidebar-brand {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 18px 14px 14px;
  border-bottom: 1px solid #f3f4f6;
}
.sidebar-avatar {
  width: 38px; height: 38px; border-radius: 50%;
  background: linear-gradient(135deg, #2d5a27, #4caf50);
  display: flex; align-items: center; justify-content: center;
  font-size: 11px; font-weight: 700; color: #fff;
  flex-shrink: 0;
  border: 2px solid rgba(76,175,80,0.35);
  box-shadow: 0 2px 8px rgba(76,175,80,0.25);
}
.sidebar-avatar img { width: 100%; height: 100%; border-radius: 50%; object-fit: cover; }
.sidebar-brand-text { overflow: hidden; white-space: nowrap; }
.sidebar-brand-text h2 { font-size: 13px; font-weight: 700; color: #111827; line-height: 1.2; }
.sidebar-brand-text p  { font-size: 10px; color: #9ca3af; margin-top: 2px; }

.sidebar-toggle {
  position: absolute;
  top: 14px; right: 12px;
  background: none; border: none; cursor: pointer;
  color: #9ca3af;
  font-size: 20px;
  display: flex; align-items: center;
  transition: color 0.15s;
  z-index: 10;
}
.sidebar-toggle:hover { color: #111827; }

.sidebar-nav { flex: 1; overflow-y: auto; padding: 10px 8px; }
.sidebar-nav ul { list-style: none; display: flex; flex-direction: column; gap: 2px; }
.sidebar-nav a {
  display: flex; align-items: center; gap: 10px;
  padding: 9px 10px;
  border-radius: 8px;
  font-size: 13px; font-weight: 500;
  color: #6b7280;
  text-decoration: none;
  transition: background 0.15s, color 0.15s;
  white-space: nowrap;
  overflow: hidden;
}
.sidebar-nav a:hover { background: #f9fafb; color: #111827; }
.sidebar-nav a.active {
  background: rgba(184,0,72,0.08);
  color: #b80048;
  border-right: 3px solid #b80048;
  margin-right: -8px;
  padding-right: 13px;
  font-weight: 600;
}
.sidebar-nav .material-symbols-outlined { font-size: 18px; flex-shrink: 0; }
.nav-label { transition: opacity 0.2s, width 0.2s; overflow: hidden; }

.sidebar-footer {
  padding: 10px 8px 14px;
  border-top: 1px solid #f3f4f6;
}
.btn-incident {
  width: 100%; margin-bottom: 8px;
  background: #b80048; color: #fff;
  border: none; border-radius: 8px;
  padding: 9px 12px;
  font-size: 12px; font-weight: 600;
  cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 6px;
  white-space: nowrap; overflow: hidden;
  transition: background 0.15s;
  box-shadow: 0 2px 6px rgba(184,0,72,0.25);
}
.btn-incident:hover { background: #9c003d; }
.sidebar-footer-links { display: flex; flex-direction: column; gap: 1px; }
.sidebar-footer-links a {
  display: flex; align-items: center; gap: 8px;
  padding: 6px 10px; border-radius: 6px;
  font-size: 11px; color: #9ca3af;
  text-decoration: none; white-space: nowrap; overflow: hidden;
  transition: color 0.15s, background 0.15s;
}
.sidebar-footer-links a:hover { color: #374151; background: #f9fafb; }
.sidebar-footer-links .material-symbols-outlined { font-size: 15px; flex-shrink: 0; }

/* ä"?ä"? Hardening Accordion ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"? */
.ps-accordion-item {
  transition: background 0.3s ease;
}
.ps-accordion-item.active {
  background: rgba(255, 255, 255, 0.05);
}
.ps-accordion-item.active .ps-accordion-body {
  display: block !important;
}
.ps-accordion-item .fa-chevron-down {
  transition: transform 0.3s ease;
}
.ps-accordion-item.active .fa-chevron-down {
  transform: rotate(180deg);
}
.ps-sidenav-tab {
  transition: all 0.2s ease;
  border-left: 3px solid transparent;
}
.ps-sidenav-tab:hover {
  background: rgba(184, 0, 72, 0.04);
}
.ps-sidenav-tab.active {
  background: rgba(184, 0, 72, 0.08) !important;
  color: #b80048 !important;
  border-left: 3px solid #b80048 !important;
  font-weight: 600;
}

/* Hide text when collapsed */
#ps-sidebar.collapsed .nav-label,
#ps-sidebar.collapsed .sidebar-brand-text,
#ps-sidebar.collapsed .incident-label,
#ps-sidebar.collapsed .footer-label { display: none; }
#ps-sidebar.collapsed .sidebar-nav a { justify-content: center; }
#ps-sidebar.collapsed .btn-incident { padding: 9px; justify-content: center; }

/* ä"?ä"? Main area ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"? */
#ps-main {
  flex: 1;
  display: flex;
  flex-direction: column;
  overflow: hidden;
  position: relative;
  min-height: 0;
}

/* ä"?ä"? Header bar ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"? */
#ps-header {
  height: 52px;
  background: rgba(255,255,255,0.97);
  backdrop-filter: blur(10px);
  border-bottom: 1px solid rgba(0,0,0,0.08);
  display: flex;
  align-items: center;
  padding: 0 18px;
  gap: 14px;
  z-index: 50;
  flex-shrink: 0;
  box-shadow: 0 1px 4px rgba(0,0,0,0.06);
}
#ps-header h1 {
  font-size: 18px; font-weight: 700; color: #111;
  white-space: nowrap; margin-right: 6px;
}
.header-search {
  flex: 1; max-width: 340px;
  display: flex; align-items: center; gap: 6px;
  background: #f3f4f6; border: 1px solid #e5e7eb;
  border-radius: 8px; padding: 0 10px; height: 34px;
}
.header-search input {
  border: none; background: transparent; outline: none;
  font-size: 13px; color: #6b7280; width: 100%; font-family: inherit;
}
.header-search .material-symbols-outlined { font-size: 17px; color: #9ca3af; }

.widget-toggles {
  display: flex; align-items: center; gap: 4px; margin-left: 24px;
}
.widget-toggle {
  width: 32px; height: 32px; border-radius: 6px;
  border: 1px solid #e5e7eb; background: #fff; cursor: pointer;
  display: flex; align-items: center; justify-content: center;
  color: #9ca3af; transition: all 0.15s;
}
.widget-toggle:hover { border-color: #d1d5db; color: #4b5563; }
.widget-toggle.active {
  background: #b80048; border-color: #b80048; color: #fff;
}
.widget-toggle .material-symbols-outlined { font-size: 18px; }
.widget-hidden { display: none !important; }

.header-actions { display: flex; align-items: center; gap: 6px; margin-left: auto; }
.header-icon-btn {
  width: 34px; height: 34px; border-radius: 50%;
  border: none; background: transparent; cursor: pointer;
  display: flex; align-items: center; justify-content: center;
  color: #6b7280; transition: background 0.15s, color 0.15s;
}
.header-icon-btn:hover { background: #f3f4f6; color: #111; }
.header-icon-btn .material-symbols-outlined { font-size: 20px; }
.header-avatar {
  width: 32px; height: 32px; border-radius: 50%; overflow: hidden;
  cursor: pointer; border: 2px solid #e5e7eb;
}
.header-avatar img { width: 100%; height: 100%; object-fit: cover; }
.header-avatar img { width: 100%; height: 100%; object-fit: cover; }

/* ä"?ä"? Map container ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"? */
#ps-map-area {
  flex: 1;
  position: relative;
  overflow: hidden;
  min-height: 0;
}
#security-map {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  z-index: 0;
}
.leaflet-control-attribution { display: none !important; }

/* Styled zoom control to match dashboard */
.leaflet-control-zoom {
  border: none !important;
  border-radius: 10px !important;
  overflow: hidden;
  box-shadow: 0 4px 16px rgba(0,0,0,0.12) !important;
  margin-bottom: 16px !important;
  margin-right: 16px !important;
}
.leaflet-control-zoom a {
  background: rgba(255,255,255,0.95) !important;
  color: #374151 !important;
  border: none !important;
  border-bottom: 1px solid #f3f4f6 !important;
  width: 32px !important;
  height: 32px !important;
  line-height: 32px !important;
  font-size: 18px !important;
  font-weight: 400 !important;
  transition: background 0.15s, color 0.15s !important;
}
.leaflet-control-zoom a:hover {
  background: #b80048 !important;
  color: #fff !important;
}
.leaflet-control-zoom-out { border-bottom: none !important; }

/* ä"?ä"? SVG attack lines overlay ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"? */
#attack-svg {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  z-index: 5;
  pointer-events: none;
  transition: opacity 0.15s;
}
.attack-line {
  fill: none;
  stroke-linecap: round;
  pointer-events: stroke;
  cursor: pointer;
  transition: stroke-width 0.2s, filter 0.2s;
}
.attack-line:hover { stroke-width: 3 !important; opacity: 1 !important; filter: drop-shadow(0 0 4px currentColor); }
.attack-line.critical { stroke: #ef4444; stroke-width: 1.5; stroke-dasharray: 8 5; opacity: 0.75; }
.attack-line.warning  { stroke: #f59e0b; stroke-width: 1.5; opacity: 0.65; }
.attack-line.safe,
.attack-line.success  { stroke: #22c55e; stroke-width: 1.5; opacity: 0.65; }
.attack-line.info     { stroke: #3b82f6; stroke-width: 1.5; opacity: 0.65; }

/* Flight Blip (plane) */
.flight-blip {
  pointer-events: none;
}

/* Pulse dots */
.pulse-dot-svg {
  animation: pulse-svg 1.8s ease-out infinite;
}
@keyframes pulse-svg {
  0%   { r: 4;   opacity: 0.9; }
  70%  { r: 10;  opacity: 0; }
  100% { r: 4;   opacity: 0; }
}

/* ä"?ä"? Widgets overlay (on map) ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"? */
#ps-widgets {
  position: absolute;
  inset: 12px;
  z-index: 10;
  pointer-events: none;
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  grid-template-rows: repeat(3, minmax(0, 1fr));
  gap: 12px;
}

/* ä"?ä"? Grid Cells ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"? */
.grid-cell {
  position: relative;
  display: flex;
  flex-direction: column;
  gap: 10px;
  pointer-events: none; /* Let map clicks through! */
  border-radius: 12px;
  min-height: 120px;
  overflow: visible;
}

/* Alignment rules so widgets naturally stick to the corners */
#cell-0, #cell-3, #cell-6 { align-items: flex-start; } /* Left column */
#cell-1, #cell-4, #cell-7 { align-items: center; }     /* Middle column */
#cell-2, #cell-5, #cell-8 { align-items: flex-end; }   /* Right column */

#cell-0, #cell-1, #cell-2 { justify-content: flex-start; } /* Top row */
#cell-3, #cell-4, #cell-5 { justify-content: center; }     /* Middle row */
#cell-6, #cell-7, #cell-8 { justify-content: flex-end; }   /* Bottom row */

/* ä"?ä"? Container grid background (subtle) ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"? */
.grid-cell::before {
  content: '';
  position: absolute;
  inset: -6px;
  background-image:
    linear-gradient(rgba(184,0,72,0.06) 1px, transparent 1px),
    linear-gradient(90deg, rgba(184,0,72,0.06) 1px, transparent 1px);
  background-size: 32px 32px;
  pointer-events: none;
  border-radius: 12px;
  opacity: 0;
  transition: opacity 0.2s ease;
  z-index: -1;
}
#ps-widgets.drag-active .grid-cell {
  pointer-events: auto; /* Allow dropping into empty cells */
}
#ps-widgets.drag-active .grid-cell::before {
  opacity: 1;
}

/* ä"?ä"? Maximize Widget ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"? */
.widget-maximize {
  position: absolute; top: 8px; right: 32px;
  cursor: pointer; opacity: 0.4; transition: opacity 0.15s;
  font-size: 16px !important; color: #6b7280; border: none; background: transparent; padding: 0;
  line-height: 1; z-index: 10;
}
.glass-card:hover .widget-maximize { opacity: 0.85; }
.widget-maximize:hover { color: #111; }

.glass-card.maximized {
  position: fixed !important;
  top: 50% !important; left: 50% !important;
  transform: translate(-50%, -50%) !important;
  z-index: 9999 !important;
  margin: 0 !important;
  cursor: default !important;
  width: 900px !important;
  height: auto !important;
  max-width: 95vw !important;
  max-height: 90vh !important;
  overflow-y: auto !important;
  border-radius: 12px;
  box-shadow: 0 24px 80px rgba(0,0,0,0.3) !important;
}
.widget-settings-panel {
  display: none;
  margin-top: 20px;
  padding-top: 15px;
  border-top: 1px solid rgba(0,0,0,0.05);
}
.glass-card.maximized .widget-settings-panel {
  display: block;
}
#maximize-backdrop {
  position: fixed; inset: 0; background: rgba(0,0,0,0.4); z-index: 9998;
  backdrop-filter: blur(4px); -webkit-backdrop-filter: blur(4px);
  display: none;
}
#maximize-backdrop.active { display: block; }

/* ä"?ä"? SortableJS ghost + chosen states ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"? */
.glass-card.sortable-ghost {
  opacity: 1 !important;
  background: rgba(184,0,72,0.04) !important;
  border: 2px dashed rgba(184,0,72,0.4) !important;
  box-shadow: none !important;
  position: relative;
  overflow: hidden;
}
.glass-card.sortable-ghost * {
  visibility: hidden;
}
.glass-card.sortable-ghost::after {
  content: 'PUDOTA Tä"Hä"N';
  visibility: visible;
  position: absolute;
  inset: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 600;
  color: rgba(184,0,72,0.5);
  font-size: 13px;
  letter-spacing: 0.05em;
  font-family: 'Inter', system-ui, sans-serif;
}
.glass-card.sortable-chosen {
  cursor: grabbing !important;
  box-shadow: 0 16px 48px rgba(0,0,0,0.22) !important;
  transform: scale(1.03) rotate(-0.5deg);
  z-index: 999 !important;
  transition: box-shadow 0.15s, transform 0.15s !important;
}
/* Widget drop animation */
@keyframes widget-drop {
  0%   { transform: scale(1.04) translateY(-6px); }
  60%  { transform: scale(0.98) translateY(2px); }
  100% { transform: scale(1)    translateY(0); }
}
.glass-card.just-dropped {
  animation: widget-drop 0.35s cubic-bezier(.22,.68,0,1.2) forwards;
}

/* ä"?ä"? Glass card ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"? */
.glass-card {
  background: rgba(255,255,255,0.92);
  backdrop-filter: blur(16px);
  -webkit-backdrop-filter: blur(16px);
  border: 1px solid rgba(255,255,255,0.7);
  box-shadow: 0 4px 24px rgba(0,0,0,0.09);
  border-radius: 12px;
  pointer-events: auto;
  position: relative;
}
.drag-handle {
  position: absolute; top: 8px; right: 8px;
  cursor: grab; opacity: 0.4; transition: opacity 0.15s;
  font-size: 18px; color: #6b7280;
  line-height: 1;
  user-select: none;
}
.glass-card:hover .drag-handle { opacity: 0.85; }

/* System Resources */
.widget-resources { width: 240px; padding: 14px; }
.widget-resources h4 { font-size: 11px; font-weight: 600; color: #6b7280; text-transform: uppercase; letter-spacing: .05em; margin-bottom: 10px; display: flex; align-items: center; gap: 6px; }
.widget-resources h4 .material-symbols-outlined { font-size: 15px; color: #b80048; }
.res-row { margin-bottom: 8px; }
.res-row-head { display: flex; justify-content: space-between; font-size: 12px; font-weight: 500; color: #374151; margin-bottom: 4px; }
.res-bar { height: 5px; background: #e5e7eb; border-radius: 99px; overflow: hidden; }
.res-bar-fill { height: 100%; background: #b80048; border-radius: 99px; transition: width 0.6s; }

/* Traffic */
.widget-traffic { width: 160px; padding: 14px; }
.widget-traffic h4 { font-size: 11px; font-weight: 600; color: #6b7280; text-transform: uppercase; letter-spacing: .05em; margin-bottom: 8px; display: flex; align-items: center; gap: 6px; }
.widget-traffic h4 .material-symbols-outlined { font-size: 15px; color: #b80048; }
.traffic-val { font-size: 26px; font-weight: 700; color: #111; line-height: 1; }
.traffic-val span { font-size: 13px; font-weight: 500; color: #6b7280; }
.traffic-label { font-size: 10px; color: #9ca3af; margin-bottom: 8px; margin-top: 2px; }
.traffic-bars { display: flex; align-items: flex-end; gap: 3px; height: 32px; }
.traffic-bar { flex: 1; border-radius: 3px 3px 0 0; background: #b80048; opacity: 0.25; transition: opacity 0.2s; }
.traffic-bar.active { opacity: 1; }
.traffic-bar:hover { opacity: 0.8; }

/* Event log */
.widget-log { width: 500px; }
.widget-log-inner { padding: 0; overflow: hidden; display: flex; flex-direction: column; height: 220px; }
.log-header { padding: 12px 14px 10px 38px; border-bottom: 1px solid #f3f4f6; display: flex; justify-content: space-between; align-items: center; background: rgba(255,255,255,0.6); }
.log-header h3 { font-size: 15px; font-weight: 700; color: #111; }
.badge-active { background: rgba(184,0,72,0.10); color: #b80048; font-size: 11px; font-weight: 600; padding: 3px 10px; border-radius: 99px; display: flex; align-items: center; gap: 5px; }
.badge-dot { width: 6px; height: 6px; border-radius: 50%; background: #b80048; animation: blink 1.2s ease infinite; }
@keyframes blink { 0%,100%{opacity:1} 50%{opacity:0.3} }
@keyframes pulse-dot { 0%,100%{opacity:1;transform:scale(1)} 50%{opacity:0.4;transform:scale(0.75)} }
@keyframes hub-pulse { 0%{transform:scale(1);opacity:0.6} 100%{transform:scale(2.2);opacity:0} }

/* ═══════════════════════════════════════
   WIDGET MODAL ENGINE — shared styles
═══════════════════════════════════════ */
.wm-backdrop{
  position:fixed;inset:0;background:rgba(2,6,23,.6);
  backdrop-filter:blur(8px);-webkit-backdrop-filter:blur(8px);
  z-index:19000;display:flex;align-items:center;justify-content:center;
  opacity:0;pointer-events:none;transition:opacity .22s;
}
.wm-backdrop.open{opacity:1;pointer-events:auto;}
.wm-dialog{
  background:#fff;border-radius:18px;
  width:min(980px,96vw);max-height:90vh;
  display:flex;flex-direction:column;
  box-shadow:0 40px 100px rgba(0,0,0,.32);
  transform:scale(.95) translateY(12px);transition:transform .22s;
  overflow:hidden;
}
.wm-backdrop.open .wm-dialog{transform:scale(1) translateY(0);}
.wm-header{
  display:flex;align-items:center;gap:12px;
  padding:18px 24px 0;border-bottom:1px solid #f1f5f9;
  background:linear-gradient(135deg,#0f172a 0%,#1e293b 100%);
  color:#fff;flex-shrink:0;
}
.wm-header-icon{font-size:22px;color:#818cf8;}
.wm-header-title{font-size:16px;font-weight:800;letter-spacing:.03em;flex:1;}
.wm-close{
  background:rgba(255,255,255,.1);border:none;color:#94a3b8;
  width:32px;height:32px;border-radius:8px;cursor:pointer;
  font-size:18px;display:flex;align-items:center;justify-content:center;
  transition:background .15s,color .15s;flex-shrink:0;margin-bottom:14px;
}
.wm-close:hover{background:rgba(239,68,68,.25);color:#f87171;}
.wm-tabs{
  display:flex;gap:4px;padding:0 24px;
  background:linear-gradient(135deg,#0f172a 0%,#1e293b 100%);
  flex-shrink:0;
}
.wm-tab{
  padding:10px 18px;font-size:12px;font-weight:700;
  color:#64748b;border:none;background:none;cursor:pointer;
  border-bottom:3px solid transparent;letter-spacing:.04em;
  transition:color .15s,border-color .15s;
}
.wm-tab.active{color:#818cf8;border-bottom-color:#818cf8;}
.wm-tab:hover:not(.active){color:#cbd5e1;}
.wm-body{flex:1;overflow-y:auto;padding:20px 24px;}
.wm-footer{
  padding:12px 24px;border-top:1px solid #f1f5f9;
  display:flex;align-items:center;gap:10px;flex-wrap:wrap;
  background:#f8fafc;flex-shrink:0;
}
.wm-btn{
  padding:8px 18px;border-radius:8px;font-size:12px;font-weight:700;
  cursor:pointer;border:none;transition:filter .15s,transform .1s;
  letter-spacing:.03em;
}
.wm-btn:hover{filter:brightness(1.08);transform:translateY(-1px);}
.wm-btn:active{transform:translateY(0);}
.wm-btn-primary{background:#6366f1;color:#fff;}
.wm-btn-danger{background:#ef4444;color:#fff;}
.wm-btn-success{background:#22c55e;color:#fff;}
.wm-btn-secondary{background:#e2e8f0;color:#475569;}
.wm-btn-warning{background:#f59e0b;color:#fff;}
.wm-table{width:100%;border-collapse:collapse;font-size:12.5px;}
.wm-table th{
  padding:8px 12px;font-size:11px;font-weight:700;color:#64748b;
  text-align:left;background:#f8fafc;border-bottom:2px solid #e2e8f0;
  position:sticky;top:0;z-index:2;
}
.wm-table td{padding:9px 12px;border-bottom:1px solid #f1f5f9;vertical-align:middle;}
.wm-table tr:hover td{background:#f8fafc;}
.wm-table tr:last-child td{border-bottom:none;}
.wm-badge{
  display:inline-flex;align-items:center;padding:2px 8px;
  border-radius:999px;font-size:10px;font-weight:700;letter-spacing:.05em;
}
.wm-badge-critical{background:#fef2f2;color:#dc2626;border:1px solid #fecaca;}
.wm-badge-warning{background:#fffbeb;color:#d97706;border:1px solid #fde68a;}
.wm-badge-success{background:#f0fdf4;color:#16a34a;border:1px solid #bbf7d0;}
.wm-badge-info{background:#eff6ff;color:#2563eb;border:1px solid #bfdbfe;}
.wm-badge-blocked{background:#1e293b;color:#94a3b8;border:1px solid #334155;}
.wm-empty{text-align:center;padding:48px 20px;color:#94a3b8;font-size:13px;}
.wm-loading{text-align:center;padding:40px;color:#94a3b8;}
.wm-section-title{
  font-size:13px;font-weight:700;color:#0f172a;
  margin:0 0 12px;padding-bottom:8px;border-bottom:1px solid #f1f5f9;
}
.wm-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:12px;}
.wm-card{
  background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;
  padding:14px;display:flex;flex-direction:column;gap:6px;
}
.wm-card-value{font-size:28px;font-weight:900;color:#0f172a;}
.wm-card-label{font-size:10px;font-weight:700;color:#94a3b8;letter-spacing:.08em;text-transform:uppercase;}
.wm-action-row{
  display:flex;align-items:center;gap:8px;
  padding:8px 12px;border-radius:8px;background:#f8fafc;
  border:1px solid #e2e8f0;margin-bottom:8px;
}
.wm-action-row:last-child{margin-bottom:0;}
.wm-action-label{flex:1;font-size:12px;font-weight:600;color:#374151;}
.wm-action-desc{font-size:11px;color:#94a3b8;}
.wm-toggle-row{
  display:flex;align-items:center;justify-content:space-between;
  padding:10px 0;border-bottom:1px solid #f1f5f9;
}
.wm-toggle-row:last-child{border-bottom:none;}
.wm-select{padding:6px 10px;border:1px solid #e2e8f0;border-radius:6px;font-size:12px;background:#fff;}
.wm-input{padding:6px 10px;border:1px solid #e2e8f0;border-radius:6px;font-size:12px;width:100%;box-sizing:border-box;}
.wm-score-bar{
  height:12px;border-radius:999px;background:#e2e8f0;overflow:hidden;
}
.wm-score-fill{height:100%;border-radius:999px;transition:width .5s;}
.wm-checkbox{width:15px;height:15px;accent-color:#6366f1;cursor:pointer;}
.log-table-wrap { flex: 1; overflow-y: auto; }
.log-table { width: 100%; border-collapse: collapse; font-size: 12.5px; }
.log-table thead th { padding: 7px 12px; font-size: 11px; font-weight: 600; color: #9ca3af; text-align: left; background: rgba(255,255,255,0.8); position: sticky; top: 0; z-index: 2; border-bottom: 1px solid #f3f4f6; }
.log-table tbody tr { border-bottom: 1px solid rgba(0,0,0,0.04); transition: background 0.1s; }
.log-table tbody tr:hover { background: rgba(0,0,0,0.025); }
.log-table td { padding: 7px 12px; color: #1f2937; }
.log-table .td-time { font-family: monospace; color: #9ca3af; font-size: 11px; }
.badge-status { font-size: 10.5px; font-weight: 600; padding: 2px 8px; border-radius: 99px; }
.badge-critical { background: rgba(220,38,38,0.12); color: #dc2626; }
.badge-blocked  { background: rgba(184,0,72,0.10);  color: #b80048; }
.badge-warning  { background: rgba(245,158,11,0.12); color: #b45309; }

/* Controls */
.widget-controls { width: 200px; }
.widget-controls-inner { padding: 14px; display: flex; flex-direction: column; height: 220px; }
.ctrl-header { display: flex; align-items: center; gap: 8px; margin-bottom: 14px; }
.ctrl-header .material-symbols-outlined { font-size: 18px; color: #111; }
.ctrl-header h3 { font-size: 15px; font-weight: 700; color: #111; }
.ctrl-rows { display: flex; flex-direction: column; gap: 14px; flex: 1; }
.ctrl-row { display: flex; align-items: center; justify-content: space-between; }
.ctrl-label { font-size: 13px; font-weight: 600; color: #111; }

/* iOS-style toggle */
.toggle-wrap { position: relative; display: inline-flex; }
.toggle-wrap input { position: absolute; opacity: 0; width: 0; height: 0; }
.toggle-track {
  width: 42px; height: 24px; border-radius: 12px;
  background: #e5e7eb; cursor: pointer;
  transition: background 0.2s; position: relative; display: block;
}
.toggle-wrap input:checked + .toggle-track { background: #b80048; }
.toggle-thumb {
  position: absolute; top: 2px; left: 2px;
  width: 20px; height: 20px; border-radius: 50%;
  background: #fff; box-shadow: 0 1px 4px rgba(0,0,0,0.25);
  transition: transform 0.2s;
}
.toggle-wrap input:checked ~ .toggle-track .toggle-thumb { transform: translateX(18px); }

/* Lockdown button */
.ctrl-footer { margin-top: auto; padding-top: 12px; border-top: 1px solid #f3f4f6; }
.btn-lockdown {
  width: 100%; padding: 9px 14px;
  background: #fef2f2; border: 1px solid rgba(220,38,38,0.3);
  color: #991b1b; border-radius: 8px; cursor: pointer;
  font-size: 13px; font-weight: 600; font-family: inherit;
  display: flex; align-items: center; justify-content: center; gap: 6px;
  transition: background 0.15s, color 0.15s;
}
.btn-lockdown:hover { background: #dc2626; color: #fff; border-color: #dc2626; }
.btn-lockdown .material-symbols-outlined { font-size: 16px; }

/* ä"?ä"? Map pin labels ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"? */
.leaflet-tooltip.map-label-tooltip {
  background: rgba(255,255,255,0.92);
  border: 1px solid rgba(0,0,0,0.1);
  border-radius: 6px;
  padding: 3px 8px;
  font-size: 11px;
  font-weight: 600;
  font-family: 'Inter', sans-serif;
  box-shadow: 0 2px 8px rgba(0,0,0,0.12);
  color: #111;
  pointer-events: none;
  white-space: nowrap;
}
.leaflet-tooltip.map-label-tooltip.critical { color: #dc2626; border-color: rgba(220,38,38,0.3); }
.leaflet-tooltip.map-label-tooltip.info { color: #3b82f6; border-color: rgba(59,130,246,0.3); }
.leaflet-tooltip.map-label-tooltip.hub {
  font-weight: 700;
  font-size: 12px;
  color: #1e40af;
  border-color: rgba(29,78,216,0.4);
  background: rgba(255,255,255,0.95);
  padding: 4px 10px;
  box-shadow: 0 2px 8px rgba(29,78,216,0.2);
}
.leaflet-tooltip.map-label-tooltip::before { display: none; }

/* ä"?ä"? Threat modal ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"? */
#threat-modal-backdrop {
  position: fixed; inset: 0; z-index: 200;
  background: rgba(0,0,0,0.45);
  backdrop-filter: blur(4px);
  display: flex; align-items: center; justify-content: center;
  padding: 16px;
  opacity: 0; pointer-events: none;
  transition: opacity 0.25s;
}
#threat-modal-backdrop.open { opacity: 1; pointer-events: auto; }
#threat-modal {
  background: #fff; border-radius: 16px;
  box-shadow: 0 20px 60px rgba(0,0,0,0.2);
  width: 100%; max-width: 480px; padding: 24px;
  transform: scale(0.94);
  transition: transform 0.25s;
  position: relative;
}
#threat-modal-backdrop.open #threat-modal { transform: scale(1); }
.modal-close {
  position: absolute; top: 16px; right: 16px;
  background: none; border: none; cursor: pointer;
  color: #9ca3af; display: flex;
}
.modal-close:hover { color: #111; }
.modal-section {
  background: #f9fafb; border: 1px solid #e5e7eb;
  border-radius: 10px; padding: 12px 14px; margin-bottom: 10px;
}
.modal-section-title { font-size: 10px; font-weight: 700; color: #9ca3af; text-transform: uppercase; letter-spacing: .06em; margin-bottom: 8px; }
.modal-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 6px; font-size: 13px; }
.modal-grid span.lbl { color: #6b7280; }
.modal-grid span.val { font-weight: 600; color: #111; }
.modal-ip { color: #dc2626 !important; font-family: monospace; }
.modal-status-row {
  display: flex; justify-content: space-between; align-items: center;
  background: rgba(220,38,38,0.06); border: 1px solid rgba(220,38,38,0.15);
  border-radius: 8px; padding: 8px 12px; margin-bottom: 16px;
  font-size: 12px; font-weight: 600;
}
.modal-actions { display: flex; justify-content: flex-end; gap: 8px; }
.btn-dismiss {
  padding: 8px 18px; border-radius: 8px; border: 1px solid #e5e7eb;
  background: #fff; color: #374151; font-size: 13px; font-weight: 600;
  cursor: pointer; font-family: inherit; transition: background 0.15s;
}
.btn-dismiss:hover { background: #f9fafb; }
.btn-ban {
  padding: 8px 18px; border-radius: 8px; border: none;
  background: #dc2626; color: #fff; font-size: 13px; font-weight: 600;
  cursor: pointer; font-family: inherit; transition: background 0.15s;
}
.btn-ban:hover { background: #b91c1c; }

/* ä"?ä"? Lockdown overlay ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"? */
#lockdown-overlay {
  position: fixed; inset: 0; z-index: 300;
  background: rgba(127,0,0,0.96); backdrop-filter: blur(8px);
  display: flex; flex-direction: column; align-items: center; justify-content: center;
  opacity: 0; pointer-events: none; transition: opacity 0.4s;
}
#lockdown-overlay.open { opacity: 1; pointer-events: auto; }
#lockdown-overlay h1 { font-size: 36px; font-weight: 800; color: #fff; margin-top: 16px; }
#lockdown-overlay p  { font-size: 16px; color: rgba(255,255,255,0.7); margin-top: 8px; margin-bottom: 24px; }
.btn-disengage {
  padding: 12px 32px; background: #ef4444; color: #fff;
  border: none; border-radius: 10px; font-size: 14px; font-weight: 700;
  cursor: pointer; font-family: inherit; transition: background 0.15s;
}
.btn-disengage:hover { background: #dc2626; }

/* scrollbar */
::-webkit-scrollbar { width: 4px; }
::-webkit-scrollbar-track { background: transparent; }
::-webkit-scrollbar-thumb { background: rgba(0,0,0,0.12); border-radius: 4px; }
</style>

<div id="ps-shell">

  <!-- ä.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ä
       SIDEBAR
  ä.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ä -->
  <nav id="ps-sidebar">

    <button class="sidebar-toggle" id="sidebar-toggle" onclick="toggleSidebar()">
      <span class="material-symbols-outlined" id="sidebar-icon">menu_open</span>
    </button>

    <div class="sidebar-brand">
      <div class="sidebar-avatar">
        <?php echo get_avatar( get_current_user_id(), 76 ); ?>
      </div>
      <div class="sidebar-brand-text">
        <h2><?php echo wp_kses_post( __( 'Operaattorin<br>Konsoli', 'pecodex-security' ) ); ?></h2>
        <p><?php esc_html_e( 'Tason 4 Oikeudet', 'pecodex-security' ); ?></p>
      </div>
    </div>

    <div class="sidebar-nav">
      <ul>
        <li>
          <a href="#" class="active" onclick="psOpenModule('none'); return false;">
            <span class="material-symbols-outlined">dashboard</span>
            <span class="nav-label"><?php esc_html_e( 'Ohjausnäkymä', 'pecodex-security' ); ?></span>
          </a>
        </li>
        <li>
          <a href="#" onclick="psOpenModule('firewall'); return false;">
            <span class="material-symbols-outlined">shield</span>
            <span class="nav-label"><?php esc_html_e( 'Palomuuri & Sulut', 'pecodex-security' ); ?></span>
          </a>
        </li>
        <li>
          <a href="#" onclick="psOpenModule('hardening'); return false;">
            <span class="material-symbols-outlined">health_and_safety</span>
            <span class="nav-label"><?php esc_html_e( 'Järjestelmän Suojaus', 'pecodex-security' ); ?></span>
          </a>
        </li>
        <li>
          <a href="#" onclick="psOpenModule('scanner'); return false;">
            <span class="material-symbols-outlined">search_check</span>
            <span class="nav-label"><?php esc_html_e( 'Haittaohjelmien Skanneri', 'pecodex-security' ); ?></span>
          </a>
        </li>
        <li>
          <a href="#" onclick="psOpenModule('advanced'); return false;">
            <span class="material-symbols-outlined">settings_suggest</span>
            <span class="nav-label"><?php esc_html_e( 'Lisätyökalut', 'pecodex-security' ); ?></span>
          </a>
        </li>
        <li>
          <a href="#" onclick="psOpenModule('headers'); return false;">
            <span class="material-symbols-outlined">http</span>
            <span class="nav-label"><?php esc_html_e( 'Tietoturvaotsikot', 'pecodex-security' ); ?></span>
          </a>
        </li>
        <li>
          <a href="#" onclick="psOpenModule('audit-log'); return false;">
            <span class="material-symbols-outlined">list_alt</span>
            <span class="nav-label"><?php esc_html_e( 'Tarkastusloki', 'pecodex-security' ); ?></span>
          </a>
        </li>
        <li>
          <a href="#" onclick="psOpenModule('integrations'); return false;">
            <span class="material-symbols-outlined">extension</span>
            <span class="nav-label"><?php esc_html_e( 'Integraatiot', 'pecodex-security' ); ?></span>
          </a>
        </li>
        <li>
          <a href="#" onclick="psOpenModule('notifications'); return false;">
            <span class="material-symbols-outlined">notifications</span>
            <span class="nav-label"><?php esc_html_e( 'Ilmoitukset', 'pecodex-security' ); ?></span>
          </a>
        </li>
        <li>
          <a href="#" onclick="psOpenModule('news'); return false;">
            <span class="material-symbols-outlined">newspaper</span>
            <span class="nav-label"><?php esc_html_e( 'Tietoturvauutiset', 'pecodex-security' ); ?></span>
          </a>
        </li>
        <li>
          <a href="#" onclick="psOpenModule('modules-overview'); return false;" id="nav-modules-overview">
            <span class="material-symbols-outlined">extension</span>
            <span class="nav-label"><?php esc_html_e( 'Tietoturvamoduulit', 'pecodex-security' ); ?></span>
          </a>
        </li>
      </ul>
    </div>

    <div class="sidebar-footer">
      <button class="btn-incident">
        <span class="material-symbols-outlined" style="font-size:15px;">add</span>
        <span class="incident-label"><?php esc_html_e( 'Poikkeamaraportti', 'pecodex-security' ); ?></span>
      </button>
      <div class="sidebar-footer-links">
        <a href="#">
          <span class="material-symbols-outlined">help_center</span>
          <span class="footer-label"><?php esc_html_e( 'Tuki', 'pecodex-security' ); ?></span>
        </a>
        <a href="#">
          <span class="material-symbols-outlined">terminal</span>
          <span class="footer-label"><?php esc_html_e( 'Järjestelmäloki', 'pecodex-security' ); ?></span>
        </a>
      </div>
    </div>
  </nav>

  <!-- ä.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ä
       MAIN AREA
  ä.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ä -->
  <div id="ps-main">

    <!-- Header (Hidden when map is active) -->
    <header id="ps-header" style="display:none;">
      <h1>Pecodex Security</h1>
      <div class="header-search">
        <span class="material-symbols-outlined">search</span>
        <input type="text" placeholder="Hae järjestelmistää?ä" id="header-search"/>
      </div>
      
      <div class="widget-toggles">
        <!-- Kept for compatibility if other modules need it, though hidden -->
      </div>

      <div class="header-actions">
        <button class="header-icon-btn"><span class="material-symbols-outlined">notifications</span></button>
        <button class="header-icon-btn"><span class="material-symbols-outlined">settings</span></button>
        <div class="header-avatar">
          <?php echo get_avatar( get_current_user_id(), 64 ); ?>
        </div>
      </div>
    </header>

    <!-- React Map App Mount Point -->
    <div id="ps-map-area" style="position:relative; width:100%; height:100%; flex:1; min-height:0; overflow-y:auto; background:#f9fafb; display:flex; flex-direction:column;">
      <div id="root" style="flex:1;"></div>
      
      <!-- Wayback Timeline UI (Compact White Theme) -->
      <style>
        #ps-timeline-panel {
          width: 100%;
          background: #ffffff;
          border-top: 1px solid #e2e8f0;
          display: flex;
          flex-direction: column;
          flex-shrink: 0;
          font-family: 'Inter', system-ui, sans-serif;
          color: #1e293b;
          box-shadow: 0 -4px 12px rgba(0,0,0,0.02);
        }
        .tl-date-carousel {
          display: flex;
          overflow-x: auto;
          gap: 6px;
          padding: 8px 16px;
          scroll-snap-type: x mandatory;
          scroll-behavior: smooth;
          border-bottom: 1px solid #f1f5f9;
          -ms-overflow-style: none;
          scrollbar-width: none;
        }
        .tl-date-carousel::-webkit-scrollbar { display: none; }
        .tl-date-btn {
          display: flex;
          flex-direction: column;
          align-items: center;
          justify-content: center;
          min-width: 64px;
          padding: 4px 0;
          border-radius: 6px;
          scroll-snap-align: center;
          transition: all 0.2s;
          border: 1px solid #e2e8f0;
          background: #f8fafc;
          color: #64748b;
          cursor: pointer;
        }
        .tl-date-btn:hover {
          background: #f1f5f9;
          border-color: #cbd5e1;
          color: #334155;
        }
        .tl-date-btn[data-active="true"] {
          background: #b80048;
          border-color: #9c003d;
          box-shadow: 0 2px 6px rgba(184, 0, 72, 0.2);
          color: #fff;
        }
        .tl-date-day {
          font-size: 9px;
          text-transform: uppercase;
          font-weight: 700;
          letter-spacing: 0.05em;
          color: inherit;
          opacity: 0.8;
        }
        .tl-date-val {
          font-size: 12px;
          font-weight: 600;
        }
        .tl-slider-row {
          display: flex;
          align-items: flex-end;
          gap: 12px;
          padding: 28px 16px 12px;
          position: relative;
        }
        .tl-time-display {
          font-size: 15px;
          font-weight: 700;
          font-variant-numeric: tabular-nums;
          letter-spacing: 0.5px;
          color: #0f172a;
          min-width: 48px;
          margin-bottom: 2px;
        }
        .tl-slider-container {
          flex: 1;
          position: relative;
          height: 38px;
          display: flex;
          align-items: flex-end;
        }
        input[type=range].tl-range {
          width: 100%;
          -webkit-appearance: none;
          background: transparent;
          cursor: pointer;
          pointer-events: none; /* Let clicks pass through to markers */
          position: relative;
          z-index: 10;
          margin: 0;
          padding: 0;
        }
        input[type=range].tl-range:focus { outline: none; }
        input[type=range].tl-range::-webkit-slider-runnable-track {
          width: 100%;
          height: 4px;
          background: #cbd5e1;
          border-radius: 99px;
        }
        input[type=range].tl-range::-webkit-slider-thumb {
          height: 16px;
          width: 16px;
          border-radius: 50%;
          background: #b80048;
          cursor: pointer;
          -webkit-appearance: none;
          margin-top: -6px;
          box-shadow: 0 2px 6px rgba(184,0,72,0.4);
          border: 2px solid #fff;
          pointer-events: auto; /* Thumb remains draggable */
        }
        input[type=range].tl-range::-moz-range-thumb {
          height: 16px;
          width: 16px;
          border-radius: 50%;
          background: #b80048;
          cursor: pointer;
          box-shadow: 0 2px 6px rgba(184,0,72,0.4);
          border: 2px solid #fff;
          pointer-events: auto;
        }
        .tl-controls-row {
          display: flex;
          align-items: center;
          justify-content: space-between;
          padding: 0 16px 10px;
        }
        .tl-play-btn {
          width: 28px;
          height: 28px;
          border-radius: 50%;
          background: #f1f5f9;
          border: 1px solid #e2e8f0;
          color: #475569;
          display: flex;
          align-items: center;
          justify-content: center;
          cursor: pointer;
          transition: all 0.2s;
        }
        .tl-play-btn:hover { background: #e2e8f0; color: #0f172a; }
        .tl-speed-group {
          display: flex;
          background: #f8fafc;
          border: 1px solid #e2e8f0;
          border-radius: 6px;
          overflow: hidden;
          margin-left: 10px;
        }
        .tl-speed-btn {
          background: transparent;
          border: none;
          color: #64748b;
          font-size: 11px;
          font-weight: 600;
          padding: 4px 8px;
          cursor: pointer;
          transition: all 0.2s;
        }
        .tl-speed-btn.active {
          background: #e2e8f0;
          color: #0f172a;
        }
        .tl-mode-group {
          display: flex;
          background: #f8fafc;
          border: 1px solid #e2e8f0;
          border-radius: 6px;
          overflow: hidden;
        }
        .tl-mode-btn {
          background: transparent;
          border: none;
          color: #64748b;
          font-size: 11px;
          font-weight: 600;
          padding: 4px 10px;
          cursor: pointer;
          transition: all 0.2s;
        }
        .tl-mode-btn.active {
          background: #b80048;
          color: #fff;
        }
      </style>
      
      <div id="ps-timeline-panel" style="position: relative; z-index: 9999;">
          <div id=\"tl-loader\" style=\"display:none; position:absolute; inset:0; z-index:100; background:rgba(255,255,255,0.6); backdrop-filter:blur(1px); align-items:center; justify-content:center;\">
            <div style=\"width:24px; height:24px; border:3px solid #4f46e5; border-top-color:transparent; border-radius:50%; animation: tl-spin 1s linear infinite;\"></div>
          </div>
          <style>@keyframes tl-spin { 100% { transform: rotate(360deg); } }</style>
        <!-- Date Carousel Wrapper -->
        <div class="tl-carousel-wrapper" style="display:flex; align-items:center; position:relative; padding: 0 10px; margin-top: 5px; gap: 4px;">
          
          <div style="position:relative;">
            <button id="tl-month-toggle" style="background:transparent; border:none; color:#64748b; cursor:pointer; padding:6px; border-radius:50%; display:flex; align-items:center; justify-content:center; transition:0.2s;" onmouseover="this.style.background='#f1f5f9';this.style.color='#0f172a'" onmouseout="this.style.background='transparent';this.style.color='#64748b'">
              <span class="material-symbols-outlined" style="font-size:20px;">calendar_month</span>
            </button>
            <div id="tl-month-dropdown" style="display:none; position:absolute; bottom:36px; left:0; background:#fff; border:1px solid #e2e8f0; border-radius:8px; box-shadow:0 10px 25px rgba(0,0,0,0.2); z-index:99999; min-width:140px; padding:8px;">
              <div style="font-size:10px; font-weight:700; color:#94a3b8; padding:4px 8px; text-transform:uppercase;">Valitse kuukausi</div>
              <div id="tl-month-list" style="display:flex; flex-direction:column; gap:2px; max-height:200px; overflow-y:auto;">
                <!-- Populated by JS -->
              </div>
            </div>
          </div>

          <button id="tl-nav-left" style="background:transparent; border:none; color:#64748b; cursor:pointer; padding:4px; border-radius:50%; display:flex; align-items:center; justify-content:center; transition:0.2s;" onmouseover="this.style.background='#f1f5f9';this.style.color='#0f172a'" onmouseout="this.style.background='transparent';this.style.color='#64748b'">
            <span class="material-symbols-outlined" style="font-size:20px;">chevron_left</span>
          </button>
          
          <div class="tl-date-carousel" id="tl-dates" style="flex:1; scroll-behavior: smooth;">
            <!-- Populated by JS -->
          </div>

          <button id="tl-nav-right" style="background:transparent; border:none; color:#64748b; cursor:pointer; padding:4px; border-radius:50%; display:flex; align-items:center; justify-content:center; transition:0.2s;" onmouseover="this.style.background='#f1f5f9';this.style.color='#0f172a'" onmouseout="this.style.background='transparent';this.style.color='#64748b'">
            <span class="material-symbols-outlined" style="font-size:20px;">chevron_right</span>
          </button>
        </div>

        <!-- Slider Row -->
        <div class="tl-slider-row">
          <div class="tl-time-display" id="tl-current-time">LIVE</div>
          <div class="tl-slider-container" style="position: relative;">
            <div id="tl-track-markers" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; pointer-events: none; z-index: 1;"></div>
            <input type="range" class="tl-range" id="tl-slider" min="0" max="72" value="0" step="1" style="position: relative; z-index: 2; background: transparent;">
          </div>
        </div>

        <!-- Controls Row -->
        <div class="tl-controls-row">
          <div style="display:flex; align-items:center;">
            <button class="tl-play-btn" id="tl-play-btn"><span class="material-symbols-outlined" style="font-size:16px;">play_arrow</span></button>
            <div class="tl-speed-group">
              <button class="tl-speed-btn active">1x</button>
              <button class="tl-speed-btn">60x</button>
              <button class="tl-speed-btn">600x</button>
            </div>
            <button id="tl-clear-focus-btn" style="display: none; margin-left: 12px; background: #fee2e2; border: 1px solid #fca5a5; border-radius: 6px; padding: 4px 10px; font-size: 11px; font-weight: 700; color: #991b1b; cursor: pointer; align-items: center; gap: 4px; transition: 0.2s;" onmouseover="this.style.background='#fecaca'" onmouseout="this.style.background='#fee2e2'">
              <span class="material-symbols-outlined" style="font-size: 15px;">close</span>
              Poista focus
            </button>
            <div class="tl-time-range-group" style="display: flex; gap: 4px; margin-left: 12px; overflow-x: auto; scrollbar-width: none;">
              <button class="tl-range-btn" data-range="all" style="background: #ffffff; border: 1px solid #cbd5e1; border-radius: 6px; padding: 4px 10px; font-size: 11px; font-weight: 700; color: #334155; cursor: pointer; display: flex; align-items: center; gap: 4px; transition: 0.2s;" onmouseover="if(!this.classList.contains('active')) this.style.background='#f1f5f9'" onmouseout="if(!this.classList.contains('active')) this.style.background='#ffffff'">Kaikki</button>
              <button class="tl-range-btn" data-range="1y" style="background: #ffffff; border: 1px solid #cbd5e1; border-radius: 6px; padding: 4px 10px; font-size: 11px; font-weight: 700; color: #334155; cursor: pointer; transition: 0.2s;">Vuosi</button>
              <button class="tl-range-btn" data-range="6m" style="background: #ffffff; border: 1px solid #cbd5e1; border-radius: 6px; padding: 4px 10px; font-size: 11px; font-weight: 700; color: #334155; cursor: pointer; transition: 0.2s;">6kk</button>
              <button class="tl-range-btn" data-range="3m" style="background: #ffffff; border: 1px solid #cbd5e1; border-radius: 6px; padding: 4px 10px; font-size: 11px; font-weight: 700; color: #334155; cursor: pointer; transition: 0.2s;">3kk</button>
              <button class="tl-range-btn" data-range="2m" style="background: #ffffff; border: 1px solid #cbd5e1; border-radius: 6px; padding: 4px 10px; font-size: 11px; font-weight: 700; color: #334155; cursor: pointer; transition: 0.2s;">2kk</button>
              <button class="tl-range-btn" data-range="1m" style="background: #ffffff; border: 1px solid #cbd5e1; border-radius: 6px; padding: 4px 10px; font-size: 11px; font-weight: 700; color: #334155; cursor: pointer; transition: 0.2s;">1kk</button>
              <button class="tl-range-btn" data-range="2w" style="background: #ffffff; border: 1px solid #cbd5e1; border-radius: 6px; padding: 4px 10px; font-size: 11px; font-weight: 700; color: #334155; cursor: pointer; transition: 0.2s;">2vk</button>
              <button class="tl-range-btn active" data-range="now" style="background: #ffffff; border: 1px solid #cbd5e1; border-radius: 6px; padding: 4px 10px; font-size: 11px; font-weight: 700; color: #334155; cursor: pointer; transition: 0.2s;">Nyt</button>
            </div>
          </div>
          <div class="tl-mode-group">
            <button class="tl-mode-btn active" id="tl-mode-live">LIVE</button>
            <button class="tl-mode-btn" id="tl-mode-history">HISTORY</button>
          </div>
        </div>
      </div>
    </div><!-- /ps-map-area -->
  </div><!-- /ps-main -->

  <!-- ps-right-sidebar: Tracking Widgets -->
  <aside id="ps-right-sidebar" style="width: 320px; min-width: 320px; height: 100%; max-height: 100%; background: #f8fafc; border-left: 1px solid #e2e8f0; display: flex; flex-direction: column; overflow-y: auto; padding: 16px; gap: 16px; z-index: 100; box-sizing: border-box;">
    <h3 style="font-size: 14px; font-weight: 700; color: #1e293b; margin: 0 0 4px 0; text-transform: uppercase; letter-spacing: 0.05em;">Seuranta (Widgets)</h3>
    
    <div class="glass-card widget-resources" id="w-resources" style="width: 100%; border: 1px solid #e2e8f0; box-shadow: 0 1px 3px rgba(0,0,0,0.05); padding: 14px;">
      <h4 style="font-size: 11px; font-weight: 600; color: #64748b; text-transform: uppercase; margin-bottom: 10px; display: flex; align-items: center; gap: 6px;">
        <span class="material-symbols-outlined" style="font-size: 16px; color: #b80048;">memory</span>
        Järjestelmäresurssit
      </h4>
      <div class="res-row" style="margin-bottom: 8px;">
        <div class="res-row-head" style="display: flex; justify-content: space-between; font-size: 12px; font-weight: 500; margin-bottom: 4px;"><span>Suorittimen Käyttö</span><span>0%</span></div>
        <div class="res-bar" style="height: 5px; background: #e2e8f0; border-radius: 99px;"><div class="res-bar-fill" style="width:0%; height: 100%; background: #b80048; border-radius: 99px;"></div></div>
      </div>
      <div class="res-row">
        <div class="res-row-head" style="display: flex; justify-content: space-between; font-size: 12px; font-weight: 500; margin-bottom: 4px;"><span>Muisti</span><span>-</span></div>
        <div class="res-bar" style="height: 5px; background: #e2e8f0; border-radius: 99px;"><div class="res-bar-fill" style="width:0%; height: 100%; background: #b80048; border-radius: 99px;"></div></div>
      </div>
    </div>

    <div class="glass-card widget-traffic" id="w-traffic" style="width: 100%; border: 1px solid #e2e8f0; box-shadow: 0 1px 3px rgba(0,0,0,0.05); padding: 14px;">
      <h4 style="font-size: 11px; font-weight: 600; color: #64748b; text-transform: uppercase; margin-bottom: 10px; display: flex; align-items: center; gap: 6px;">
        <span class="material-symbols-outlined" style="font-size: 16px; color: #b80048;">monitoring</span>
        Liikenne
      </h4>
      <div class="traffic-val" style="font-size: 24px; font-weight: 700;">0<span style="font-size: 12px; color: #64748b; font-weight: 500;"> B/s</span></div>
      <div class="traffic-label" style="font-size: 10px; color: #94a3b8; margin-bottom: 8px;">Saapuva</div>
      <div class="traffic-bars" style="display: flex; align-items: flex-end; gap: 3px; height: 32px;">
        <div class="traffic-bar" style="flex:1; background:#b80048; opacity:0.25; height:40%"></div>
        <div class="traffic-bar" style="flex:1; background:#b80048; opacity:0.25; height:60%"></div>
        <div class="traffic-bar active" style="flex:1; background:#b80048; opacity:1; height:100%"></div>
        <div class="traffic-bar" style="flex:1; background:#b80048; opacity:0.25; height:55%"></div>
        <div class="traffic-bar" style="flex:1; background:#b80048; opacity:0.25; height:30%"></div>
      </div>
    </div>

    <div class="glass-card widget-log" id="w-log" style="width: 100%; border: 1px solid #e2e8f0; box-shadow: 0 1px 3px rgba(0,0,0,0.05); display: flex; flex-direction: column;">
      <div class="log-header" style="padding: 12px 14px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center;">
        <h3 style="font-size: 13px; font-weight: 700; margin: 0;">Reaaliaikainen Tapahtumaloki</h3>
      </div>
      <div class="log-table-wrap" style="max-height: 250px; overflow-y: auto;">
        <table class="log-table" style="width: 100%; border-collapse: collapse; font-size: 11px;">
          <thead>
            <tr>
              <th style="padding: 6px 10px; text-align: left; background: #f8fafc; color: #64748b;">Aika</th>
              <th style="padding: 6px 10px; text-align: left; background: #f8fafc; color: #64748b;">Lähde</th>
              <th style="padding: 6px 10px; text-align: left; background: #f8fafc; color: #64748b;">Kohde</th>
              <th style="padding: 6px 10px; text-align: left; background: #f8fafc; color: #64748b;">Hyökkäys</th>
              <th style="padding: 6px 10px; text-align: right; background: #f8fafc; color: #64748b;">Status</th>
            </tr>
          </thead>
          <tbody id="event-log-body">
            <tr><td colspan="5" style="padding: 10px; text-align: center; color: #94a3b8;">Odotetaan tapahtumia...</td></tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Uutiset ja Tietoturvatiedotteet Widget -->
    <div class="glass-card widget-news" id="w-news" style="width: 100%; border: 1px solid #e2e8f0; box-shadow: 0 1px 3px rgba(0,0,0,0.05); display: flex; flex-direction: column; flex: 1; min-height: 250px;">
      <div class="news-header" style="padding: 12px 14px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; background: #fff; border-radius: 12px 12px 0 0;">
        <h4 style="font-size: 12px; font-weight: 700; color: #1e293b; margin: 0; display: flex; align-items: center; gap: 6px;">
          <span class="material-symbols-outlined" style="font-size: 16px; color: #b80048;">newspaper</span>
          WP Tietoturvauutiset
        </h4>
        <div style="display:flex; gap:8px;">
          <button id="refresh-news-btn" style="background:none; border:none; color:#64748b; cursor:pointer; padding:2px; display:flex;" title="Päivitä">
            <span class="material-symbols-outlined" style="font-size:16px;">refresh</span>
          </button>
          <button onclick="psOpenModule('news')" style="background:none; border:none; color:#b80048; cursor:pointer; font-size:10px; font-weight:700; text-transform:uppercase; display:flex; align-items:center; gap:2px; padding:0;">
            Lue lisää <span class="material-symbols-outlined" style="font-size:14px;">open_in_new</span>
          </button>
        </div>
      </div>
      <div class="news-content-wrap" style="flex: 1; overflow-y: auto; padding: 12px; background: #fafaf9; border-radius: 0 0 12px 12px;">
        <div id="news-feed-container" style="display: flex; flex-direction: column; gap: 10px;">
          <div style="text-align:center; padding:20px; color:#94a3b8; font-size:11px;">Haetaan tuoreimpia uutisia...</div>
        </div>
      </div>
    </div>

  </aside>
</div><!-- /ps-shell -->

<!-- ä.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ä
     THREAT MODAL
ä.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ä -->
<div id="threat-modal-backdrop" onclick="if(event.target===this)closeModal()">
  <div id="threat-modal">
    <button class="modal-close" onclick="closeModal()">
      <span class="material-symbols-outlined">close</span>
    </button>
    <div style="display:flex;align-items:center;gap:8px;margin-bottom:16px;color:#dc2626;">
      <span class="material-symbols-outlined" style="font-size:32px;">warning</span>
      <h2 style="font-size:18px;font-weight:700;color:#111;">Uhkaraportti</h2>
    </div>
    <div class="modal-section">
      <div class="modal-section-title">Uhan Lähde</div>
      <div class="modal-grid">
        <div><span class="lbl">Maa:</span></div><div><span class="val" id="modal-origin">ä?"</span></div>
        <div><span class="lbl">IP:</span></div><div><span class="val modal-ip" id="modal-ip">ä?"</span></div>
        <div><span class="lbl">Hyökkäys:</span></div><div><span class="val" id="modal-attack">ä?"</span></div>
      </div>
    </div>
    <div class="modal-section">
      <div class="modal-section-title">Kohderesurssi</div>
      <div class="modal-grid">
        <div><span class="lbl">Isäntä:</span></div><div><span class="val" id="modal-host">ä?"</span></div>
        <div><span class="lbl">Päätepiste:</span></div><div><span class="val" style="font-family:monospace;" id="modal-endpoint">ä?"</span></div>
      </div>
      <div class="modal-section-title">Profilointi (Deep Traffic Profiler)</div>
      <div class="modal-grid">
        <div><span class="lbl">Sormenj&auml;lki (UA):</span></div><div><span class="val" id="modal-ua" style="font-size:11px;word-break:break-all;">-</span></div>
        <div><span class="lbl">Yhteystyyppi:</span></div><div><span class="val" id="modal-proxy">-</span></div>
        <div style="grid-column: span 2;">
          <span class="lbl">Uhkataso:</span>
          <div style="width:100%;background:#e5e7eb;border-radius:4px;height:12px;margin-top:4px;overflow:hidden;position:relative;">
            <div id="modal-threat-bar" style="width:0%;height:100%;background:#3b82f6;transition:all 0.5s ease;"></div>
            <span id="modal-threat-text" style="position:absolute;top:0;left:0;width:100%;text-align:center;font-size:10px;line-height:12px;color:#fff;font-weight:bold;text-shadow:0 0 2px rgba(0,0,0,0.5);"></span>
          </div>
        </div>
      </div>
    </div>
    <div class="modal-status-row">
      <span id="modal-status" style="color:#dc2626;">ä?"</span>
      <span id="modal-status-container" style="color:#dc2626;display:flex;align-items:center;gap:5px;">
        <span id="modal-status-dot" style="width:7px;height:7px;border-radius:50%;background:#dc2626;animation:blink 1.2s infinite;display:inline-block;"></span>
        <span id="modal-status-label">Aktiivinen Murtoyritys</span>
      </span>
    </div>
    <div class="modal-actions">
      <button class="btn-dismiss" onclick="closeModal()">OHITA</button>
      <button class="btn-ban" id="btn-block-ip">EST&Auml; IP</button>
    </div>
  </div>
</div>

<!-- ä.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ä
     LOCKDOWN OVERLAY
ä.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ä -->
<div id="lockdown-overlay">
  <span class="material-symbols-outlined" style="font-size:80px;color:#fca5a5;">lock_person</span>
  <h1>Jä"RJESTELMä"N SULKUTILA AKTIIVINEN</h1>
  <p>Kaikki ulkoiset yhteydet on keskeytetty.</p>
  <button class="btn-disengage" onclick="disableLockdown()">POISTA SULKUTILA</button>
</div>

<!-- ä.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ä
     SECURITY MODULES (INCLUDES)
ä.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ä -->
<div id="ps-modules-container">
	<?php
	$modules_dir = __DIR__ . '/security-modules/';
	require_once $modules_dir . 'view-firewall.php';
	require_once $modules_dir . 'view-hardening.php';
	require_once $modules_dir . 'view-scanner.php';
	require_once $modules_dir . 'view-advanced.php';
	require_once $modules_dir . 'view-headers.php';
	require_once $modules_dir . 'view-audit-log.php';
	require_once $modules_dir . 'view-notifications.php';
	require_once $modules_dir . 'view-modules-overview.php';
	require_once $modules_dir . 'view-integrations.php';
	require_once $modules_dir . 'view-news.php';
	?>
</div>

<style>
/* ä"?ä"? Modular UI Styles ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"? */
#ps-modules-container {
	display: none; /* Hidden by default until a module is clicked */
	flex: 1;
	flex-direction: column;
	min-height: 0;
}
.ps-module {
	position: relative;
	flex: 1;
	background: #f8fafc;
	display: flex;
	flex-direction: column;
	overflow-y: auto;
	min-height: 0;
}
.ps-module-header {
	display: flex; justify-content: space-between; align-items: center;
	padding: 20px 30px;
	border-bottom: 1px solid #e5e7eb;
	background: #fff;
}
.ps-module-header h2 {
	margin: 0; color: #111827; font-size: 20px;
	display: flex; align-items: center; gap: 10px;
}
.ps-module-close {
	background: none; border: none; color: #6b7280;
	font-size: 24px; cursor: pointer; transition: color 0.2s;
}
.ps-module-close:hover { color: #111827; }
.ps-module-content {
	padding: 30px;
	color: #4b5563;
}
/* Cards */
.ps-card {
	background: #fff;
	border: 1px solid #e5e7eb;
	border-radius: 12px;
	padding: 20px;
	box-shadow: 0 1px 3px rgba(0,0,0,0.05);
}
.ps-card h3 { margin-top: 0; color: #111827; font-size: 16px; margin-bottom: 8px; }
.ps-desc { margin-top: 0; color: #6b7280; font-size: 13px; margin-bottom: 15px; }

/* Tabs */
.ps-tabs { display: flex; gap: 10px; margin-bottom: 20px; border-bottom: 1px solid #e5e7eb; padding-bottom: 10px;}
.ps-tab-btn { background: none; border: none; color: #6b7280; padding: 8px 16px; cursor: pointer; border-radius: 6px; font-weight: 500;}
.ps-tab-btn.active { background: rgba(184,0,72,0.08); color: #b80048; }
.ps-tab-content { display: none; }
.ps-tab-content.active { display: block; }

/* Controls */
.ps-control-row { display: flex; align-items: center; gap: 12px; margin-bottom: 15px; }
.ps-switch { position: relative; display: inline-block; width: 44px; height: 24px; }
.ps-switch input { opacity: 0; width: 0; height: 0; }
.ps-slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #d1d5db; transition: .4s; border-radius: 24px; }
.ps-slider:before { position: absolute; content: ""; height: 18px; width: 18px; left: 3px; bottom: 3px; background-color: white; transition: .4s; border-radius: 50%; box-shadow: 0 1px 2px rgba(0,0,0,0.1); }
.ps-switch input:checked + .ps-slider { background-color: #b80048; }
.ps-switch input:checked + .ps-slider:before { transform: translateX(20px); }

.ps-input-group { display: flex; flex-direction: column; gap: 6px; margin-bottom: 15px; }
.ps-input-group label { font-size: 13px; color: #6b7280; font-weight: 500; }
.ps-input, .ps-input-group input { background: #fff; border: 1px solid #d1d5db; color: #111827; padding: 8px 12px; border-radius: 6px; outline: none; }
.ps-input:focus, .ps-input-group input:focus { border-color: #b80048; box-shadow: 0 0 0 2px rgba(184,0,72,0.1); }

.ps-btn { padding: 8px 16px; border-radius: 6px; font-weight: 500; cursor: pointer; border: none; transition: 0.2s; }
.ps-btn-primary { background: #b80048; color: #fff; }
.ps-btn-primary:hover { background: #96003b; }
.ps-btn-danger { background: #dc2626; color: #fff; }
.ps-table { width: 100%; border-collapse: collapse; font-size: 13px; }
.ps-table th { text-align: left; padding: 10px; border-bottom: 1px solid #e5e7eb; color: #6b7280; font-weight: 600; }
.ps-table td { padding: 10px; border-bottom: 1px solid #f3f4f6; color: #374151; }
.mt-4 { margin-top: 20px; }
</style>

<script>
function psOpenModule(moduleId) {
	// Update URL without reloading so refresh keeps the same module
	let newSlug = 'pecodex-security';
	if (moduleId !== 'none') {
		newSlug = 'pecodex-security-' + moduleId;
	}
	if (new URLSearchParams(window.location.search).get('page') !== newSlug) {
		window.history.pushState({}, '', '?page=' + newSlug);
	}

	// Hide all modules
	document.querySelectorAll('.ps-module').forEach(el => el.style.display = 'none');
	
	// Update active sidebar nav
	document.querySelectorAll('.sidebar-nav a').forEach(el => el.classList.remove('active'));
	
	const modContainer = document.getElementById('ps-modules-container');
	const mapArea = document.getElementById('ps-map-area');

	if (moduleId === 'none') {
		// Dashboard view active
		if (modContainer) modContainer.style.display = 'none';
		if (mapArea) {
			mapArea.style.display = 'block';
			if (typeof window.refreshSecurityMap === 'function') {
				window.refreshSecurityMap(false);
			}
		}
		document.querySelector('.sidebar-nav a:first-child').classList.add('active');
		return;
	}

	// Hide dashboard map area
	if (mapArea) mapArea.style.display = 'none';
	if (modContainer) modContainer.style.display = 'flex';

	// Show requested module
	const mod = document.getElementById('ps-module-' + moduleId);
	if (mod) mod.style.display = 'flex';

	// Highlight current link
	const links = document.querySelectorAll('.sidebar-nav a');
	links.forEach(link => {
		if (link.getAttribute('onclick') && link.getAttribute('onclick').includes(moduleId)) {
			link.classList.add('active');
		}
	});
}
<?php
	// Determine initial module from URL
	$current_page = isset($_GET['page']) ? sanitize_text_field($_GET['page']) : 'pecodex-security';
	$initial_module = 'none';
	if ($current_page === 'pecodex-security-news') $initial_module = 'news';
	elseif ($current_page === 'pecodex-security-firewall') $initial_module = 'firewall';
	elseif ($current_page === 'pecodex-security-hardening') $initial_module = 'hardening';
	elseif ($current_page === 'pecodex-security-scanner') $initial_module = 'scanner';
	elseif ($current_page === 'pecodex-security-advanced') $initial_module = 'advanced';
	elseif ($current_page === 'pecodex-security-headers') $initial_module = 'headers';
	elseif ($current_page === 'pecodex-security-audit-log') $initial_module = 'audit-log';
	elseif ($current_page === 'pecodex-security-notifications') $initial_module = 'notifications';
	elseif ($current_page === 'pecodex-security-modules-overview') $initial_module = 'modules-overview';
?>

document.addEventListener('DOMContentLoaded', () => {
	// Move modules container into ps-main so it acts as a pane
	const main = document.getElementById('ps-main');
	const modContainer = document.getElementById('ps-modules-container');
	if (main && modContainer) {
		main.appendChild(modContainer);
	}

	// Open initial module based on WP admin submenu
	psOpenModule('<?php echo esc_js($initial_module); ?>');

	// Close module handlers
	document.querySelectorAll('.ps-module-close').forEach(btn => {
		btn.addEventListener('click', () => {
			// Instead of just hiding, update the URL to main dashboard without reloading
			window.history.pushState({}, '', '?page=pecodex-security');
			psOpenModule('none');
		});
	});

	// Tabs handler
	document.querySelectorAll('.ps-tab-btn').forEach(btn => {
		btn.addEventListener('click', (e) => {
			const target = e.target.getAttribute('data-target');
			const tabsContainer = e.target.closest('.ps-module-content');
			tabsContainer.querySelectorAll('.ps-tab-btn').forEach(b => b.classList.remove('active'));
			tabsContainer.querySelectorAll('.ps-tab-content').forEach(c => c.classList.remove('active'));
			e.target.classList.add('active');
			tabsContainer.querySelector(target).classList.add('active');
		});
	});

	// Sidenav tabs handler
	document.querySelectorAll('.ps-sidenav-tab').forEach(btn => {
		btn.addEventListener('click', (e) => {
			e.preventDefault();
			const el = e.currentTarget;
			const target = el.getAttribute('data-target');
			const tabsContainer = el.closest('.ps-module-content');
			tabsContainer.querySelectorAll('.ps-sidenav-tab').forEach(b => b.classList.remove('active'));
			tabsContainer.querySelectorAll('.ps-tweak-tab-content').forEach(c => c.style.display = 'none');
			el.classList.add('active');
			tabsContainer.querySelector(target).style.display = 'block';
		});
	});
});
</script>

<!-- ä.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ä
     SCRIPTS
ä.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ää.ä -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {

<?php
  // Fetch real server location (cached from ip-api)
  $server_geo = Pecodex_Security_API::get_server_location();
  $server_lat = $server_geo['lat'];
  $server_lng = $server_geo['lng'];
  $server_city = $server_geo['city'] !== 'Unknown' ? strtoupper($server_geo['city']) : 'SERVER HUB';
?>
/* ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?
   LOCATIONS  (lat, lng)
ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"? */
const locations = {
  hub: [<?php echo esc_js($server_lat); ?>, <?php echo esc_js($server_lng); ?>],
};

/* ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?
   MAP INIT  (light tiles)
ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"? */
let map = null;
if (document.getElementById('security-map')) {
map = L.map('security-map', {
  center: locations.hub,
  zoom: 4,
  zoomControl: false,
  attributionControl: false,
  scrollWheelZoom: true,
  dragging: true,
  doubleClickZoom: true,
  keyboard: true,
  touchZoom: true,
});
window.psSecurityMap = map;

// Light CartoDB tile
L.tileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png', {
  subdomains: 'abcd',
  maxZoom: 19,
}).addTo(map);

L.control.zoom({ position: 'bottomright' }).addTo(map);

/* ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?
   MAP MARKERS + TOOLTIPS
ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"? */
/* ═══════════════════════════════════════
   HUB MARKER — premium blue dot
═══════════════════════════════════════ */
function makeHubIcon() {
  return L.divIcon({
    className: '',
    html: `<div style="position:relative;width:22px;height:22px;">
      <div style="position:absolute;inset:0;border-radius:50%;background:#be123c;border:3px solid #fff;box-shadow:0 0 0 2px #be123c,0 4px 12px rgba(190,18,60,0.5);"></div>
      <div style="position:absolute;inset:-6px;border-radius:50%;border:2px solid rgba(190,18,60,0.35);animation:hub-pulse 2s ease-out infinite;"></div>
      <div style="position:absolute;inset:-14px;border-radius:50%;border:1.5px solid rgba(190,18,60,0.15);animation:hub-pulse 2s ease-out 0.7s infinite;"></div>
    </div>`,
    iconSize: [22, 22],
    iconAnchor: [11, 11],
  });
}

/* ═══════════════════════════════════════
   CONNECTION DOT ICON
═══════════════════════════════════════ */
function makeConnectionIcon(cls) {
  const c = {
    critical: '#ef4444',
    warning:  '#f59e0b',
    success:  '#22c55e',
    info:     '#3b82f6',
    tracked:  '#8b5cf6',
  }[cls] || '#22c55e';
  return L.divIcon({
    className: '',
    html: `<div style="width:12px;height:12px;border-radius:50%;background:${c};border:2.5px solid #fff;box-shadow:0 0 0 1.5px ${c},0 2px 6px ${c}88;"></div>`,
    iconSize: [12, 12],
    iconAnchor: [6, 6],
  });
}

// Hub marker — prominent blue dot with "Main Node" label
L.marker(locations.hub, { icon: makeHubIcon(), interactive: false, zIndexOffset: 1000 })
  .addTo(map)
  .bindTooltip('Main Node (<?php echo esc_js($server_city); ?>)', {
    permanent: true,
    direction: 'top',
    offset: [0, -18],
    className: 'map-label-tooltip hub',
  });

let activeMapEvents = [];
let activeMarkers   = [];
let activePolylines = [];

function updateMapMarkers(events) {
  activeMarkers.forEach(m => map.removeLayer(m));
  activeMarkers = [];
  activePolylines.forEach(p => map.removeLayer(p));
  activePolylines = [];

  const seen = new Set();

  events.forEach(e => {
    if (!e.lat || !e.lng) return;
    const cls = e.statusClass || 'success';
    const lineColor = {
      critical: '#ef4444', warning: '#f59e0b',
      info: '#3b82f6', tracked: '#8b5cf6',
    }[cls] || '#22c55e';

    // Geo-accurate polyline from hub to attacker
    const polyline = L.polyline(
      [locations.hub, [e.lat, e.lng]],
      {
        color: lineColor,
        weight: 1.6,
        opacity: cls === 'critical' ? 0.72 : 0.60,
        dashArray: cls === 'critical' ? '8 5' : null,
        interactive: true,
      }
    ).addTo(map);

    polyline.on('click', () => {
      if (typeof openModal === 'function') {
        openModal({
          origin: `${e.country||'?'} \u2014 ${e.city||'?'}`,
          ip: e.ip, attack: e.attack||'Unknown',
          host: e.target||'', endpoint: (e.type&&e.type.includes('404'))?'404 Probe':'N/A',
          status: e.status, statusClass: cls,
          user_agent: e.user_agent||'Unknown',
          is_proxy: e.is_proxy||false, threat_score: e.threat_score||0,
        });
      }
    });
    activePolylines.push(polyline);

    // One dot per unique IP
    const ipKey = e.ip + ':' + e.lat + ':' + e.lng;
    if (!seen.has(ipKey)) {
      seen.add(ipKey);
      const dot = L.marker([e.lat, e.lng], {
        icon: makeConnectionIcon(cls),
        zIndexOffset: 100, interactive: true,
      }).addTo(map);

      dot.bindTooltip(
        `<b>${e.ip}</b><br>${e.country||''} ${e.city?'\u2014 '+e.city:''}<br><span style="color:${lineColor};font-weight:600">${e.attack||e.status||''}</span>`,
        { direction: 'top', offset: [0, -8], className: 'map-label-tooltip ' + cls }
      );
      dot.on('click', () => {
        if (typeof openModal === 'function') {
          openModal({
            origin: `${e.country||'?'} \u2014 ${e.city||'?'}`,
            ip: e.ip, attack: e.attack||'Unknown',
            host: e.target||'', endpoint: (e.type&&e.type.includes('404'))?'404 Probe':'N/A',
            status: e.status, statusClass: cls,
            user_agent: e.user_agent||'Unknown',
            is_proxy: e.is_proxy||false, threat_score: e.threat_score||0,
          });
        }
      });
      activeMarkers.push(dot);
    }
  });
}

// drawAttackLines is a no-op — lines are now geo-accurate L.polylines
function drawAttackLines() {}

window.refreshSecurityMap = function () {
  const mapArea = document.getElementById('ps-map-area');
  if (!mapArea || mapArea.style.display === 'none' || mapArea.offsetWidth === 0) return;
  requestAnimationFrame(() => {
    requestAnimationFrame(() => {
      map.invalidateSize({ animate: false });
      drawAttackLines();
    });
  });
};

map.whenReady(() => {
  window.refreshSecurityMap(false);
});
// Redraw lines on map move so they track geographic positions
map.on('move', drawAttackLines);
map.on('resize', drawAttackLines);

const mapAreaEl = document.getElementById('ps-map-area');
if (mapAreaEl && typeof ResizeObserver !== 'undefined') {
  let mapResizeTimer;
  new ResizeObserver(() => {
    clearTimeout(mapResizeTimer);
    mapResizeTimer = setTimeout(() => window.refreshSecurityMap(), 80);
  }).observe(mapAreaEl);
}
window.addEventListener('resize', () => window.refreshSecurityMap());

// Hide SVG during zoom animation to prevent visual glitches
map.on('zoomstart', () => {
  document.getElementById('attack-svg').style.opacity = '0';
});
map.on('zoomend', () => {
  drawAttackLines();
  document.getElementById('attack-svg').style.opacity = '1';
});

/* ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?
   LIVE EVENTS POLLING
ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"? */
let isFetchingLiveEvents = false;
async function fetchLiveEvents() {
  if (isFetchingLiveEvents) return;
  isFetchingLiveEvents = true;
  try {
    const formData = new FormData();
    formData.append('action', 'pmc_security_live_map_data');
    if (typeof pmcSecurityConfig !== 'undefined' && pmcSecurityConfig.nonce) {
      formData.append('nonce', pmcSecurityConfig.nonce);
    }
    const response = await fetch(ajaxurl, {
      method: 'POST',
      body: formData
    });
    const res = await response.json();
    const events = (res.success && res.data && Array.isArray(res.data.events))
      ? res.data.events
      : (res.success && Array.isArray(res.data) ? res.data : []);
    activeMapEvents = events;
    updateEventLogTable(activeMapEvents);
    updateMapMarkers(activeMapEvents);
    drawAttackLines();
    const badge = document.getElementById('live-event-count');
    if (badge) badge.textContent = activeMapEvents.length;
  } catch (err) {
    console.error('Live-tapahtumien nouto epäonnistui:', err);
  } finally {
    isFetchingLiveEvents = false;
  }
}

function updateEventLogTable(events) {
  const tbody = document.getElementById('event-log-body');
  if (!tbody) return;
  if (events.length === 0) {
    tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;color:#9ca3af;padding:20px;">Ei tuoreita tapahtumia</td></tr>';
    return;
  }
  let html = '';
  events.forEach(e => {
    const badgeCls = e.statusClass === 'critical' ? 'badge-critical' : 'badge-warning';
    html += `
      <tr>
        <td class="td-time">${e.timestamp}</td>
        <td>${e.country} — ${e.city}</td>
        <td>${e.target}</td>
        <td>${e.attack}</td>
        <td style="text-align:right"><span class="badge-status ${badgeCls}">${e.status}</span></td>
      </tr>
    `;
  });
  tbody.innerHTML = html;
}

// Initial fetch
fetchLiveEvents();

/* ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?
   SIDEBAR TOGGLE
ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"? */
function toggleSidebar() {
  const sb   = document.getElementById('ps-sidebar');
  const icon = document.getElementById('sidebar-icon');
  const expanded = !sb.classList.contains('collapsed');
  sb.classList.toggle('collapsed', expanded);
  icon.textContent = expanded ? 'menu' : 'menu_open';
  setTimeout(() => { if (typeof window.refreshSecurityMap === 'function') window.refreshSecurityMap(false); }, 300);
}
window.toggleSidebar = toggleSidebar;

/* ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?
   THREAT MODAL
ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"? */
function openModal(data) {
  document.getElementById('modal-origin').textContent   = data.origin   || 'ä?"';
  document.getElementById('modal-ip').textContent       = data.ip       || 'ä?"';
  document.getElementById('modal-attack').textContent   = data.attack   || 'ä?"';
  document.getElementById('modal-host').textContent     = data.host     || 'ä?"';
  document.getElementById('modal-endpoint').textContent = data.endpoint || 'ä?"';
  document.getElementById('modal-status').textContent   = data.status   || 'ä?"';
      if(document.getElementById('modal-ua')) document.getElementById('modal-ua').textContent = data.user_agent || 'Unknown';
    if(document.getElementById('modal-proxy')) {
        document.getElementById('modal-proxy').textContent = data.is_proxy ? 'Proxy / VPN Detected (Riski)' : 'Suora yhteys';
        document.getElementById('modal-proxy').style.color = data.is_proxy ? '#dc2626' : '#059669';
    }
    if(document.getElementById('modal-threat-bar')) {
        const score = parseInt(data.threat_score) || 0;
        const tBar = document.getElementById('modal-threat-bar');
        const tText = document.getElementById('modal-threat-text');
        tBar.style.width = Math.min(score, 100) + '%';
        if (score === 0) {
          tBar.style.background = '#3b82f6';
          tText.textContent = score + '% (Turvallinen)';
        } else if (score < 50) {
          tBar.style.background = '#f59e0b';
          tText.textContent = score + '% (Ep&auml;ilytt&auml;v&auml;)';
        } else {
          tBar.style.background = '#dc2626';
          tText.textContent = score + '% (KORKEA RISKI)';
        }
    }
    document.getElementById('threat-modal-backdrop').classList.add('open');
  
  const statusEl = document.getElementById('modal-status');
  const statusContainer = document.getElementById('modal-status-container');
  const statusDot = document.getElementById('modal-status-dot');
  const statusLabel = document.getElementById('modal-status-label');
  
  if (data.statusClass === 'info') {
    statusEl.style.color = '#3b82f6';
    if(statusContainer) statusContainer.style.color = '#3b82f6';
    if(statusDot) statusDot.style.background = '#3b82f6';
    if(statusLabel) statusLabel.textContent = 'Normaali Liikenne';
  } else {
    statusEl.style.color = '#dc2626';
    if(statusContainer) statusContainer.style.color = '#dc2626';
    if(statusDot) statusDot.style.background = '#dc2626';
    if(statusLabel) statusLabel.textContent = 'Aktiivinen Murtoyritys';
  }

  const btnBlock = document.getElementById('btn-block-ip');
  if (btnBlock) {
    btnBlock.dataset.ip = data.ip;
  }
}
function closeModal() {
  document.getElementById('threat-modal-backdrop').classList.remove('open');
}
window.openModal  = openModal;
window.closeModal = closeModal;

document.addEventListener('DOMContentLoaded', () => {
  const btnBlock = document.getElementById('btn-block-ip');
  if (btnBlock) {
    btnBlock.addEventListener('click', function() {
      const ip = this.dataset.ip;
      if (!ip) return;
      
      const formData = new URLSearchParams();
      formData.append('action', 'pmc_instant_block');
      formData.append('ip', ip);
      
      fetch(typeof ajaxurl !== 'undefined' ? ajaxurl : '/wp-admin/admin-ajax.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: formData
      })
      .then(res => res.json())
      .then(res => {
        if (res.success || res.data) {
          alert('IP Blocked: ' + ip);
          closeModal();
          if (typeof fetchLiveEvents === 'function') fetchLiveEvents();
          else if (typeof refreshSecurityMap === 'function') refreshSecurityMap();
        } else {
          alert('Failed to block IP.');
        }
      })
      .catch(err => {
        console.error(err);
        alert('Error blocking IP.');
      });
    });
  }
});


/* ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?
   LOCKDOWN
ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"? */
function triggerLockdown() {
  document.getElementById('lockdown-overlay').classList.add('open');
}
function disableLockdown() {
  document.getElementById('lockdown-overlay').classList.remove('open');
}
window.triggerLockdown  = triggerLockdown;
window.disableLockdown  = disableLockdown;

/* ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?
   DRAG & DROP WIDGETS ä?" smart grid
ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"? */
if (typeof Sortable !== 'undefined') {

  // ä"? 9-cell grid ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?
  document.querySelectorAll('.grid-cell').forEach(cell => {
    new Sortable(cell, {
      group: 'widgets',
      animation: 220,
      handle: '.drag-handle',
      ghostClass: 'sortable-ghost',
      chosenClass: 'sortable-chosen',
      dragClass: 'sortable-drag',
      forceFallback: true,
      fallbackOnBody: true,
      easing: 'cubic-bezier(0.22, 0.68, 0, 1.2)',
      onStart() {
        document.getElementById('ps-widgets').classList.add('drag-active');
      },
      onEnd(evt) {
        document.getElementById('ps-widgets').classList.remove('drag-active');
        const el = evt.item;
        el.classList.remove('just-dropped');
        void el.offsetWidth; // reflow
        el.classList.add('just-dropped');
        el.addEventListener('animationend', () => el.classList.remove('just-dropped'), { once: true });
        saveWidgetOrder();
      },
    });
  });

  // ä"? persist order ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?
  function saveWidgetOrder() {
    const layout = {};
    document.querySelectorAll('.grid-cell').forEach(cell => {
      layout[cell.id] = [...cell.querySelectorAll('.glass-card')].map(el => el.id);
    });
    try { localStorage.setItem('ps-widget-layout', JSON.stringify(layout)); } catch(e) {}
  }

  function restoreWidgetOrder() {
    try {
      const saved = JSON.parse(localStorage.getItem('ps-widget-layout') || 'null');
      if (!saved) return;
      Object.keys(saved).forEach(cellId => {
        const cell = document.getElementById(cellId);
        if (cell) {
          saved[cellId].forEach(widgetId => {
            const w = document.getElementById(widgetId);
            if (w) cell.appendChild(w);
          });
        }
      });
    } catch(e) {}
  }
  restoreWidgetOrder();

  // ä"? Widget Toggles ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?
  function initWidgetToggles() {
    const savedHidden = JSON.parse(localStorage.getItem('ps-hidden-widgets') || '[]');
    document.querySelectorAll('.widget-toggle:not(#toggle-all-widgets)').forEach(btn => {
      const targetId = btn.getAttribute('data-target');
      const widget = document.getElementById(targetId);
      
      // Restore initial state
      if (savedHidden.includes(targetId)) {
        btn.classList.remove('active');
        if (widget) widget.classList.add('widget-hidden');
      }

      btn.addEventListener('click', () => {
        const isActive = btn.classList.toggle('active');
        if (widget) {
          widget.classList.toggle('widget-hidden', !isActive);
        }
        
        // Save state
        const currentlyHidden = [];
        document.querySelectorAll('.widget-toggle:not(#toggle-all-widgets):not(.active)').forEach(b => {
          currentlyHidden.push(b.getAttribute('data-target'));
        });
        localStorage.setItem('ps-hidden-widgets', JSON.stringify(currentlyHidden));
      });
    });
  }
  initWidgetToggles();

  // ä"? Toggle All Widgets ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?
  function initToggleAll() {
    const btnAll = document.getElementById('toggle-all-widgets');
    const iconAll = document.getElementById('toggle-all-icon');
    if (!btnAll) return;

    function updateMasterIcon() {
      const allToggles = document.querySelectorAll('.widget-toggle:not(#toggle-all-widgets)');
      const allOn = Array.from(allToggles).every(b => b.classList.contains('active'));
      iconAll.textContent = allOn ? 'visibility_off' : 'visibility';
      btnAll.title = allOn ? 'Hide All Widgets' : 'Show All Widgets';
    }

    btnAll.addEventListener('click', () => {
      const allToggles = document.querySelectorAll('.widget-toggle:not(#toggle-all-widgets)');
      const allOn = Array.from(allToggles).every(b => b.classList.contains('active'));
      
      allToggles.forEach(btn => {
        if (allOn && btn.classList.contains('active')) {
          btn.click(); // turn off
        } else if (!allOn && !btn.classList.contains('active')) {
          btn.click(); // turn on
        }
      });
      updateMasterIcon();
    });

    document.querySelectorAll('.widget-toggle:not(#toggle-all-widgets)').forEach(btn => {
      btn.addEventListener('click', updateMasterIcon);
    });
    
    updateMasterIcon();
  }
  initToggleAll();

  // ä"? Maximize Widgets ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?
  function initMaximize() {
    const backdrop = document.getElementById('maximize-backdrop');
    let currentlyMaximized = null;
    let placeholder = null;

    function unmaximize() {
      if (currentlyMaximized) {
        currentlyMaximized.classList.remove('maximized');
        const icon = currentlyMaximized.querySelector('.widget-maximize .material-symbols-outlined');
        if (icon) icon.textContent = 'open_in_full';
        
        if (placeholder) {
          placeholder.remove();
          placeholder = null;
        }
        
        currentlyMaximized = null;
        if (backdrop) backdrop.classList.remove('active');
      }
    }

    document.querySelectorAll('.widget-maximize').forEach(btn => {
      btn.addEventListener('click', (e) => {
        e.stopPropagation();
        const card = btn.closest('.glass-card');
        const icon = btn.querySelector('.material-symbols-outlined');
        
        if (card.classList.contains('maximized')) {
          unmaximize();
        } else {
          unmaximize(); // Close any other
          
          // Create placeholder to prevent grid shift
          placeholder = document.createElement('div');
          placeholder.style.width = card.offsetWidth + 'px';
          placeholder.style.height = card.offsetHeight + 'px';
          placeholder.style.transition = 'none';
          card.parentNode.insertBefore(placeholder, card);
          
          card.classList.add('maximized');
          if (icon) icon.textContent = 'close_fullscreen';
          currentlyMaximized = card;
          if (backdrop) backdrop.classList.add('active');
          if (card.id === 'w-login-security') {
            pmcSec.fetchLoginSubModalData();
          }
        }
      });
    });

    if (backdrop) backdrop.addEventListener('click', unmaximize);
  }
  initMaximize();

}

/* ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?
   LIVE EVENT FEED
   Now handled by fetchLiveEvents()
ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"? */

} // end if security-map

/* ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?
   PECODEX SECURITY AJAX INTEGRATION
ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"?ä"? */
const TWEAK_DEFS = {
	xml_rpc: { title: "Poista XML-RPC käytöstä", desc: "Estä XML-RPC brute force -hyökkäykset.", overview: "XML-RPC on usein brute force - ja DDoS-hyökkäysten kohteena. Jos et käytä WordPress-mobiilisovellusta tai palveluja kuten Jetpack, on turvallisempaa poistaa se käytöstä.", fix: "Poistamme XML-RPC:n käytöstä suojataksemme sivustoasi näiltä automatisoiduilta hyökkäyksiltä." },
	file_editor: { title: "Poista Tiedostoeditori käytöstä", desc: "Poista käytöstä sisäänrakennettu teema-/lisäosaeditori.", overview: "WordPressissä on sisäänrakennettu tiedostoeditori. Jos hyökkääjä saa ylläpito-oikeudet, he voivat käyttää tätä haitallisen koodin syöttämiseen. Sen poistaminen lisää kriittisen puolustuskerroksen.", fix: "Määritämme DISALLOW_FILE_EDIT wp-config.php -tiedostoosi estääksemme pääsyn." },
	wp_version: { title: "Piilota WP-versio", desc: "Poista WordPressin versio HTML-tulosteesta.", overview: "WordPress tulostaa automaattisesti versionumeronsa sivuston lähdekoodiin. Hakkerit skannaavat tätä kohdistaakseen hyökkäyksiä sivustoihin, joissa on vanhentuneita, haavoittuvia versioita.", fix: "Piilotamme WordPressin version julkisesta HTML-tulosteestasi." },
	prevent_enum: { title: "Estä Käyttöjien Luetteleminen", desc: "Estä botteja löytämästä käyttäjätunnuksia.", overview: "Oletuksena hyökkääjät voivat helposti löytää sivustosi käyttäjätunnukset skannaamalla kirjoittaja-arkistoja. Tämä tekee brute-force -hyökkäyksistä paljon helpompia.", fix: "Estämme skriptit, jotka yrittävät automaattisesti luetella käyttäjiäsi." },
	hide_errors: { title: "Piilota PHP-virheet", desc: "Estä virheiden näkyminen julkisivulla.", overview: "PHP-virheet voivat paljastaa hyökkääjille arkaluonteisia palvelinpolkuja, muuttujia ja tietokantarakenteita.", fix: "Varmistamme, että WP_DEBUG_DISPLAY on poistettu käytöstä, jotta virheet pysyvät piilossa vierailijoilta." },
	change_admin: { title: "Vaihda 'admin'-käyttäjätunnus", desc: "Varmista, ettei kenelläkään käyttäjällä ole oletus 'admin'-tunnusta.", overview: "Käyttöjätunnus 'admin' on ensimmäinen asia, jota hakkerit yrittävät yrittäessään brute force -hyökkäystä sivustollesi.", fix: "Luo uusi järjestelmänvalvojan tili eri käyttäjätunnuksella, ja poista sitten vanha 'admin'-tili.", toggleable: false, action_text: "Toimenpide vaaditaan WP-Käyttöjissä" },
	login_duration: { title: "Kirjautumisen Kesto", desc: "Vähennä sitä aikaa, jonka käyttäjät pysyvät kirjautuneina.", overview: "Oletuksena käyttäjät, jotka valitsevat 'muista minut', pysyvät kirjautuneina 14 päivää. Tämän vähentäminen rajoittaa ikkunaa, jolloin hyökkääjä voi hyödyntää hylättyö istuntoa.", fix: "Vähennämme oletuskirjautumisen kestoa." },
	disable_trackback: { title: "Poista Paluuviitteet käytöstä", desc: "Estä trackback- ja pingback-roskaposti.", overview: "Paluuviitteitä ja takaisinkutsuja käytetään paljolti roskapostiin ja niitä voidaan hyödyntää DDoS-hyökkäyksissä.", fix: "Poistamme paluuviitteet ja takaisinkutsut käytöstä koko sivustoltasi." },
	protect_info: { title: "Suojaa Tiedot", desc: "Estä pääsy ydintiedostoihin kuten readme.html.", overview: "Tiedostot kuten readme.html tai varmuuskopiotiedostot voivat paljastaa arkaluonteisia tietoja ympäristöstäsi.", fix: "Lukitsemme nämä tiedostot varmistaaksemme, etteivät hakkerit pääse niihin käsiksi." },
	php_version: { title: "Päivitä PHP", desc: "Varmista, että käytät tuettua PHP-versiota.", overview: "Vanhentuneiden PHP-versioiden suorittaminen altistaa palvelimesi korjaamattomille haavoittuvuuksille.", fix: "Päivitä PHP-versiosi uusimpaan vakaaseen julkaisuun hosting-paneelisi kautta.", toggleable: false, action_text: "Päivitä Hosting-paneelista" },
	prevent_php: { title: "Estä PHP:n Suoritus", desc: "Estä PHP:n suoritus lataushakemistossa.", overview: "Latauskansion pitäisi sisältäää vain mediaa. Jos hakkeri lataa tänne haitallisen PHP-skriptin, tämä estää sen suorittamisen.", fix: "Asetamme suojauksia lataushakemistoon estääksemme PHP:n suorittamisen." },
	security_keys: { title: "Tietoturva-avaimet", desc: "Varmista, että tietoturva-avaimet on asetettu wp-config.php -tiedostossa.", overview: "Tietoturva-avaimet parantavat käyttäjän evästeisiin tallennettujen tietojen salausta.", fix: "Varmistamme, että uudet tietoturva-avaimet luodaan ja otetaan käyttöön.", toggleable: false, action_text: "Luo Avaimet Uudelleen" },
	disable_indexes: { title: "Estä Hakemiston Listaus", desc: "Estä palvelimen kansioiden selaaminen.", overview: "Jos hakemiston listaus on päällä, hyökkääjät voivat selata sivustosi kansiorakennetta ja löytää haavoittuvia tiedostoja.", fix: "Lisäämme säännön joka estää hakemistojen sisällön selaamisen." }
};

const HEADER_DEFS = {
	sh_strict_transport: { title: "Strict Transport Security (HSTS)", desc: "Pakottaa turvalliset (HTTP yli SSL/TLS) yhteydet palvelimeen." },
	sh_xframe: { title: "X-Frame-Options", desc: "Suojaa clickjackingilta (napin kaappaukselta)." },
	sh_xss_protection: { title: "X-XSS-Protection", desc: "Estää sivujen latautumisen, kun ne havaitsevat heijastuvia cross-site scripting (XSS) -hyökkäyksiä." },
	sh_content_type_options: { title: "X-Content-Type-Options", desc: "Estää selainta yrittämästä MIME-nuuskia sisältötyyppiä." },
	sh_referrer_policy: { title: "Referrer-Policy", desc: "Määrittelee, mitä viittaustietoja tulisi sisällyttää tehtyihin pyyntöihin." },
	sh_feature_policy: { title: "Permissions-Policy", desc: "Tarjoaa mekanismeja selaimen ominaisuuksien käyttöönottoon ja poistamiseen." },
	sh_content_security_policy: { title: "Content-Security-Policy", desc: "Estää lataamasta haitallisia resursseja sivuston ulkopuolelta." }
};

window.pmcSec = {
	pollTimerMaster: null,
	pollTimerLive: null,
	isFetchingMaster: false,

	init() {
		if (typeof pmcSecurityConfig === 'undefined') return;
		this.fetchMasterData();
		this.fetchAuditLog();
		this.fetchLockoutLog();
		this.startAdaptivePolling();
	},

	startAdaptivePolling() {
		const setupIntervals = () => {
			if (this.pollTimerMaster) clearInterval(this.pollTimerMaster);
			if (this.pollTimerLive) clearInterval(this.pollTimerLive);

			const isHidden = document.hidden;
			// Short polling when active (8s & 10s), Long polling when backgrounded (60s)
			const masterInterval = isHidden ? 60000 : 8000;
			const liveInterval = isHidden ? 60000 : 10000;

			this.pollTimerMaster = setInterval(() => this.fetchMasterData(), masterInterval);
			this.pollTimerLive = setInterval(() => {
				if (typeof fetchLiveEvents === 'function') fetchLiveEvents();
			}, liveInterval);
		};

		setupIntervals();

		// Page Visibility API - instant catchup on tab focus
		document.addEventListener('visibilitychange', () => {
			setupIntervals();
			if (!document.hidden) {
				this.fetchMasterData();
				if (typeof fetchLiveEvents === 'function') fetchLiveEvents();
			}
		});
	},

	async post(action, data = {}) {
		const formData = new FormData();
		formData.append('action', action);
		formData.append('nonce', pmcSecurityConfig.nonce);
		for (const [key, val] of Object.entries(data)) {
			if (Array.isArray(val)) {
				val.forEach(item => formData.append(`${key}[]`, item));
			} else {
				formData.append(key, val);
			}
		}
		const res = await fetch(pmcSecurityConfig.ajaxUrl, {
			method: 'POST',
			body: formData
		});
		return await res.json();
	},
	async fetchMasterData() {
		if (this.isFetchingMaster) return;
		this.isFetchingMaster = true;
		try {
			const res = await this.post('pmc_security_data');
			if (res.success) {
				this.updateDashboard(res.data);
				this.renderTweaks(res.data.hardening.tweaks);
			}
		} catch (e) {
			console.error("Pecodex Security: Päätietojen nouto epäonnistui", e);
		} finally {
			this.isFetchingMaster = false;
		}
	},
	async fetchAuditLog(page = 1) {
		const res = await this.post('pmc_security_audit_log', { paged: page });
		const tbody = document.querySelector('#ps-module-audit-log .ps-table tbody');
		if (res.success && tbody && res.data && res.data.items) {
			tbody.innerHTML = '';
			if (res.data.items.length === 0) {
				tbody.innerHTML = '<tr><td colspan="9">Ei tapahtumia.</td></tr>';
			}
			res.data.items.forEach(log => {
				const sevConfig = {
					critical: { label: 'äY"ä Kriittinen', color: '#fef2f2', text: '#dc2626' },
					warning:  { label: 'äYYä Varoitus',  color: '#fffbeb', text: '#d97706' },
					info:     { label: 'äY"ä Info',      color: '#eff6ff', text: '#2563eb' },
				};
				const sev = sevConfig[log.severity] || sevConfig.info;
				const sevBadge = `<span style="font-size:10px;font-weight:700;padding:2px 6px;border-radius:10px;background:${sev.color};color:${sev.text};white-space:nowrap;">${sev.label}</span>`;
				const tooltip = log.ua ? `title="${log.ua.replace(/"/g,"'")}"` : '';
				tbody.innerHTML += `<tr>
					<td style="font-size:11px;white-space:nowrap;">${log.time || '-'}</td>
					<td>${sevBadge}</td>
					<td style="font-size:11px;">
						<strong>${log.user || '-'}</strong>
						${log.user_roles ? `<br><span style="color:#9ca3af;font-size:10px;">${log.user_roles}</span>` : ''}
					</td>
					<td style="font-size:11px;font-family:monospace;">${log.ip || '-'}</td>
					<td style="font-size:11px;text-align:center;">${log.country || '-'}</td>
					<td style="font-size:11px;">${log.device || '-'}</td>
					<td style="font-size:11px;" ${tooltip}>${log.browser || '-'}<br><span style="color:#9ca3af;font-size:10px;">${log.os || ''}</span></td>
					<td style="font-size:11px;"><code style="background:#f3f4f6;padding:2px 4px;border-radius:3px;font-size:10px;">${log.action || '-'}</code></td>
					<td style="font-size:11px;">${log.details || '-'}</td>
				</tr>`;
			});
			this.renderPagination(tbody.parentElement, res.data.current_page, res.data.total_pages, 'fetchAuditLog');
		}
	},
	async updateLoginAttempts(val) {
		const res = await this.post('pmc_security_update_login_attempts', { attempts: val });
		if (res.success) {
			this.showToast('Asetukset tallennettu', 'success');
		} else {
			this.showToast('Tallennus epäonnistui', 'error');
		}
	},
	loginChartInstance: null,
	async fetchLoginSubModalData() {
		const tfEl = document.getElementById('login-timeframe-filter');
		const tf = tfEl ? tfEl.value : '24h';
		const res = await this.post('pmc_security_lockout_logs', { paged: 1, timeframe: tf });
		if (res.success && res.data) {
			// Update Settings Input
			const attemptsInput = document.getElementById('quick-login-attempts');
			if (attemptsInput && res.data.current_attempts) {
				attemptsInput.value = res.data.current_attempts;
			}

			// Update Table
			const tbody = document.querySelector('#login-events-table tbody');
			if (tbody && res.data.items) {
				tbody.innerHTML = '';
				if (res.data.items.length === 0) {
					tbody.innerHTML = '<tr><td colspan="5" class="text-center p-4 text-slate-400">Ei kirjautumistapahtumia</td></tr>';
				} else {
					res.data.items.forEach(log => {
						const date = log.date || '-';
						const type = log.type === 'auth_fail' ? '<span class="text-orange-600">Epäonnistunut</span>' : (log.type === 'auth_lock' ? '<span class="text-red-600 font-bold">Lukittu</span>' : log.type);
						const ip = log.ip || '-';
						const country = log.country_iso_code || '-';
						let actionBtn = `<button class="px-2 py-1 bg-red-50 text-red-700 hover:bg-red-100 border border-red-200 rounded text-[10px] font-bold" onclick="pmcSec.banIp('${ip}')">EST&Auml; IP</button>`;
						tbody.innerHTML += `<tr>
							<td class="p-2 border-b text-slate-600 whitespace-nowrap">${date}</td>
							<td class="p-2 border-b">${type}</td>
							<td class="p-2 border-b font-mono text-slate-700">${ip}</td>
							<td class="p-2 border-b text-slate-600">${country}</td>
							<td class="p-2 border-b text-right">${actionBtn}</td>
						</tr>`;
					});
				}
			}

			// Update Chart
			if (res.data.chart_data && window.Chart) {
				const ctx = document.getElementById('loginChart');
				if (ctx) {
					if (this.loginChartInstance) {
						this.loginChartInstance.destroy();
					}
					this.loginChartInstance = new Chart(ctx, {
						type: 'bar',
						data: {
							labels: res.data.chart_data.labels,
							datasets: [
								{
									label: 'Onnistuneet',
									data: res.data.chart_data.success,
									backgroundColor: '#cbd5e1',
									borderColor: '#cbd5e1',
									borderWidth: 1,
									borderRadius: 2
								},
								{
									label: 'Epäonnistuneet',
									data: res.data.chart_data.failed,
									backgroundColor: '#ef4444',
									borderColor: '#ef4444',
									borderWidth: 1,
									borderRadius: 2
								}
							]
						},
						options: {
							responsive: true,
							maintainAspectRatio: false,
							plugins: {
								legend: { display: false },
								tooltip: { mode: 'index', intersect: false }
							},
							scales: {
								x: { display: false },
								y: { display: false, min: 0 }
							},
							interaction: { mode: 'nearest', axis: 'x', intersect: false }
						}
					});
				}
			}
		}
	},
	async fetchLockoutLog(page = 1) {
		const res = await this.post('pmc_security_lockout_logs', { paged: page });
		const tbody = document.querySelector('#ps-module-firewall .ps-table tbody');
		if (res.success && tbody && res.data && res.data.items) {
			tbody.innerHTML = '';
			if (res.data.items.length === 0) tbody.innerHTML = '<tr><td colspan="4">Ei tapahtumia.</td></tr>';
			res.data.items.forEach(log => {
				const date = log.date || '-';
				const type = log.type || '-';
				const ip = log.ip || '-';
				const details = log.country_iso_code || '-';
				tbody.innerHTML += `<tr><td>${date}</td><td>${type}</td><td>${ip}</td><td>${details}</td></tr>`;
			});
			this.renderPagination(tbody.parentElement, res.data.current_page, res.data.total_pages, 'fetchLockoutLog');
		}
	},
	renderPagination(tableEl, current, total, funcName) {
		let wrapper = tableEl.parentElement;
		let pag = wrapper.querySelector('.ps-pagination');
		if(!pag) {
			pag = document.createElement('div');
			pag.className = 'ps-pagination mt-3 flex justify-end gap-2 items-center text-xs';
			wrapper.appendChild(pag);
		}
		let html = `Sivu ${current} / ${total} &nbsp;`;
		if (current > 1) {
			html += `<button class="ps-btn" style="padding:4px 8px;font-size:12px;background:#f8fafc;border:1px solid #e2e8f0;cursor:pointer;" onclick="pmcSec.${funcName}(${current - 1})">Edellinen</button>`;
		} else {
			html += `<button class="ps-btn" style="padding:4px 8px;font-size:12px;background:#f1f5f9;border:1px solid #e2e8f0;color:#94a3b8;cursor:not-allowed;" disabled>Edellinen</button>`;
		}
		if (current < total) {
			html += `<button class="ps-btn" style="padding:4px 8px;font-size:12px;background:#f8fafc;border:1px solid #e2e8f0;cursor:pointer;" onclick="pmcSec.${funcName}(${current + 1})">Seuraava</button>`;
		} else {
			html += `<button class="ps-btn" style="padding:4px 8px;font-size:12px;background:#f1f5f9;border:1px solid #e2e8f0;color:#94a3b8;cursor:not-allowed;" disabled>Seuraava</button>`;
		}
		pag.innerHTML = html;
	},
	updateDashboard(data) {
		// Login stats
		if (data.login_stats) {
			const statVals = document.querySelectorAll('#w-login-security .stat-val');
			if (statVals.length >= 3) {
				statVals[0].textContent = data.login_stats.failed_24h;
				statVals[1].textContent = data.login_stats.locked_ips_count;
				statVals[2].textContent = data.login_stats.active_admins;
			}
		}
		// Scanner stats
		if (data.scan_stats) {
			const scanVals = document.querySelectorAll('#w-malware .stat-big');
			if (scanVals.length >= 3) {
				scanVals[0].textContent = data.scan_stats.infected;
				scanVals[1].textContent = data.scan_stats.modified;
				scanVals[2].textContent = data.scan_stats.quarantined;
			}
		}
		// Server Resources
		if (data.server_info) {
			const resRows = document.querySelectorAll('#w-resources .res-row');
			if (resRows.length >= 2) {
				const cpuText = resRows[0].querySelector('.res-row-head span:last-child');
				const cpuBar = resRows[0].querySelector('.res-bar-fill');
				if (cpuText) cpuText.textContent = data.server_info.cpu_usage + '%';
				if (cpuBar) cpuBar.style.width = data.server_info.cpu_usage + '%';
				
				const memText = resRows[1].querySelector('.res-row-head span:last-child');
				const memBar = resRows[1].querySelector('.res-bar-fill');
				if (memText) memText.textContent = data.server_info.mem_used + 'GB / ' + data.server_info.mem_total + 'GB';
				if (memBar) memBar.style.width = data.server_info.mem_percent + '%';
			}
		}
		
		// Vulnerabilities
		if (data.vulnerabilities) {
			const vulnContainer = document.querySelector('#w-vulnerabilities .flex.flex-col.gap-2');
			if (vulnContainer) {
				let html = '';
				data.vulnerabilities.forEach(v => {
					const colorClass = v.color === 'red' ? 'bg-red-50 border-red-100' : (v.color === 'orange' ? 'bg-orange-50 border-orange-100' : 'bg-yellow-50 border-yellow-100');
					html += `<div class="flex justify-between items-center text-xs p-2 ${colorClass} border rounded">
								<div>
								  <div class="font-bold text-slate-800">${v.plugin}</div>
								  <div class="text-[10px] text-slate-500">${v.cve}</div>
								</div>
								<button class="text-[10px] bg-white border border-slate-200 px-2 py-1 rounded font-bold hover:bg-slate-50">Päivitä</button>
							  </div>`;
				});
				vulnContainer.innerHTML = html;
				const titleBadge = document.querySelector('#w-vulnerabilities .bg-red-100');
				if (titleBadge) titleBadge.textContent = `${data.vulnerabilities.length} Kriittistä`;
			}
		}

		// System Hardening
		if (data.hardening && data.hardening.tweaks) {
			const hList = document.getElementById('w-hardening-list');
			if (hList) {
				const map = [
					{ key: 'protect_info', label: 'wp-config.php oikeudet', ok: 'Suojattu', bad: 'Ei suojattu' },
					{ key: 'disable_indexes', label: 'Hakemiston Listaus', ok: 'Pois käytöstä', bad: 'Käytössä!' },
					{ key: 'wp_version', label: 'WP-versio piilotettu', ok: 'Kyllä', bad: 'Ei' },
					{ key: 'xml_rpc', label: 'XML-RPC', ok: 'Pois käytöstä', bad: 'Käytössä!' }
				];
				let html = '';
				map.forEach(item => {
					const status = data.hardening.tweaks[item.key];
					if (status === 'ok') {
						html += `<div class="flex justify-between items-center text-xs">
									<span class="text-slate-600">${item.label}</span>
									<span class="text-green-600 font-bold bg-green-50 px-2 py-0.5 rounded">${item.ok}</span>
								 </div>`;
					} else {
						html += `<div class="flex justify-between items-center text-xs">
									<span class="text-slate-600">${item.label}</span>
									<span class="text-red-600 font-bold bg-red-50 px-2 py-0.5 rounded">${item.bad}</span>
								 </div>`;
					}
				});
				hList.innerHTML = html;
			}
		}

		// Rate Limit
		if (data.rate_limit) {
			const rateValue = document.querySelector('#w-rate .text-2xl');
			const rateBar = document.querySelector('#w-rate .bg-indigo-600');
			const limitText = document.querySelector('#w-rate .text-pink-600');
			if (rateValue) rateValue.textContent = data.rate_limit.current;
			if (limitText) limitText.textContent = `Raja: ${data.rate_limit.limit}`;
			if (rateBar) {
				const pct = Math.min(100, Math.round((data.rate_limit.current / data.rate_limit.limit) * 100));
				rateBar.style.width = pct + '%';
			}
		}

		// Node Health
		if (data.node_health) {
			const nodeContainer = document.querySelector('#w-node-health .flex.flex-col.gap-3');
			if (nodeContainer) {
				let html = '';
				data.node_health.forEach(n => {
					const pingColor = n.status === 'green' ? 'text-green-600' : 'text-yellow-600';
					const barColor = n.status === 'green' ? 'bg-green-500' : 'bg-yellow-500';
					const pct = Math.min(100, n.ping / 2);
					html += `<div>
								<div class="flex justify-between text-xs font-bold text-slate-700 mb-1">
								  <span>${n.name}</span>
								  <span class="${pingColor}">${n.ping}ms</span>
								</div>
								<div class="w-full bg-slate-200 h-1.5 rounded-full"><div class="${barColor} h-1.5 rounded-full" style="width:${pct}%"></div></div>
							 </div>`;
				});
				nodeContainer.innerHTML = html;
			}
		}

		// Payloads
		if (data.payloads) {
			const payloadTable = document.querySelector('#w-payloads table tbody');
			if (payloadTable) {
				let html = '';
				data.payloads.forEach(p => {
					const badgeColor = p.color === 'red' ? 'bg-red-100 text-red-700' : (p.color === 'orange' ? 'bg-orange-100 text-orange-700' : 'bg-yellow-100 text-yellow-700');
					html += `<tr>
								<td class="font-mono text-xs p-2 whitespace-nowrap text-slate-500">${p.time}</td>
								<td class="font-bold text-xs p-2">${p.ip}</td>
								<td class="p-2"><span class="px-2 py-0.5 rounded-full text-[10px] font-bold ${badgeColor}">${p.type}</span></td>
								<td class="font-mono text-[10px] p-2 text-slate-500 max-w-[120px] truncate" title="${p.path}">${p.path}</td>
							 </tr>`;
				});
				payloadTable.innerHTML = html;
			}
		}
		// Firewall Settings
		if (data.firewall) {
			const setChecked = (id, val) => { const el = document.getElementById(id); if(el) el.checked = !!val; };
			setChecked('fw-login-toggle', data.firewall.login?.enabled);
			setChecked('fw-404-toggle', data.firewall.notfound?.enabled);
			
			const loginAtt = document.getElementById('fw-login-attempts');
			if (loginAtt && data.firewall.login?.attempt) loginAtt.value = data.firewall.login.attempt;
			
			const nfAtt = document.getElementById('fw-404-attempts');
			if (nfAtt && data.firewall.notfound?.attempt) nfAtt.value = data.firewall.notfound.attempt;
			
			// Render IP lists
			pmcSec.renderBannedIps(data.firewall.banned_ips || []);
			pmcSec.renderAllowedIps(data.firewall.allowed_ips || []);
		}
		// Advanced settings
		if (data.advanced) {
			const setChecked = (id, val) => { const el = document.getElementById(id); if(el) el.checked = !!val; };
			setChecked('adv-mask-toggle', data.advanced.mask?.enabled);
			setChecked('adv-2fa-toggle', data.advanced.tfa?.enabled);
			setChecked('adv-strongpw-toggle', data.advanced.strong_pw?.enabled);
			setChecked('adv-session-toggle', data.advanced.session?.enabled);
			
			const maskUrlEl = document.getElementById('adv-mask-url');
			if (maskUrlEl && data.advanced.mask?.mask_url) {
				maskUrlEl.value = data.advanced.mask.mask_url;
			}
			const maskRedirectEl = document.getElementById('adv-mask-redirect');
			if (maskRedirectEl && data.advanced.mask?.redirect_traffic_url) {
				maskRedirectEl.value = data.advanced.mask.redirect_traffic_url;
			}
		}

		// Security Headers
		if (data.headers !== undefined) {
			this.renderHeaders(data.headers || {});
		}
	},
	renderBannedIps(ips) {
		const tbody = document.querySelector('#fw-banned-ips-table tbody');
		if (!tbody) return;
		if (ips.length === 0) {
			tbody.innerHTML = '<tr><td colspan="3" style="text-align:center;color:#6b7280;">Ei EST&Auml; IP-osoitteita.</td></tr>';
			return;
		}
		let html = '';
		ips.forEach(ip => {
			html += `<tr>
				<td style="font-family:monospace;">${ip.ip}</td>
				<td><span style="background:#fee2e2;color:#dc2626;padding:2px 8px;border-radius:12px;font-size:11px;font-weight:600;">Estetty</span></td>
				<td><button class="ps-btn ps-btn-primary" style="padding:4px 8px;font-size:11px;" onclick="pmcSec.unbanIp('${ip.ip}')">Poista esto</button></td>
			</tr>`;
		});
		tbody.innerHTML = html;
	},
	renderAllowedIps(ips) {
		const tbody = document.querySelector('#fw-allowed-ips-table tbody');
		if (!tbody) return;
		if (ips.length === 0) {
			tbody.innerHTML = '<tr><td colspan="2" style="text-align:center;color:#6b7280;">Ei sallittuja IP-osoitteita.</td></tr>';
			return;
		}
		let html = '';
		ips.forEach(ip => {
			html += `<tr>
				<td style="font-family:monospace;">${ip}</td>
				<td><button class="ps-btn ps-btn-danger" style="padding:4px 8px;font-size:11px;" onclick="pmcSec.removeAllowedIp('${ip}')">Poista</button></td>
			</tr>`;
		});
		tbody.innerHTML = html;
	},
	async unbanIp(ip) {
		const res = await this.post('pmc_security_unban_ip', { ip });
		if (res.success) this.fetchMasterData();
		else alert('IP:n poistaminen estolistalta epäonnistui.');
	},
	async removeAllowedIp(ip) {
		const res = await this.post('pmc_security_firewall_remove_allowed_ip', { ip });
		if (res.success) this.fetchMasterData();
		else alert('IP:n poistaminen sallittujen listalta epäonnistui.');
	},
	renderHeaders(headers) {
		const grid = document.getElementById('security-headers-grid');
		if (!grid) return;
		let html = '';
		for (const [slug, def] of Object.entries(HEADER_DEFS)) {
			// If it's missing or false, it's inactive
			const active = headers && headers[slug] === true;
			const cls = active ? 'status-ok' : 'status-alert';
			const icon = active ? 'check-circle' : 'exclamation-circle';
			const txt = active ? 'Aktiivinen' : 'Ei aktiivinen';
			const checked = active ? 'checked' : '';
			
			html += `<div class="ps-card ps-tweak-card">
				<h4>${def.title}</h4>
				<div class="ps-tweak-status ${cls}">
					<i class="fas fa-${icon}"></i> ${txt}
				</div>
				<p class="ps-desc mt-2">${def.desc}</p>
				<label class="ps-switch mt-3">
					<input type="checkbox" id="header-${slug}" ${checked} onchange="window.pmcToggleHeaderUI(this)">
					<span class="ps-slider"></span>
				</label>
			</div>`;
		}
		grid.innerHTML = html;
	},
	renderTweaks(tweaks) {
		const recContainer = document.getElementById('accordion-recommendations');
		const actContainer = document.getElementById('accordion-actioned');
		const recBadge = document.getElementById('badge-recommendations');
		const recBadgeInner = document.getElementById('badge-recommendations-inner');
		const actBadge = document.getElementById('badge-actioned');
		const actBadgeInner = document.getElementById('badge-actioned-inner');
		
		if (!recContainer || !actContainer) return;

		let recHtml = '';
		let actHtml = '';
		let recCount = 0;
		let actCount = 0;

		for (const [slug, status] of Object.entries(tweaks)) {
			const def = TWEAK_DEFS[slug] || { title: slug, desc: 'Security tweak', overview: '', fix: '' };
			const isOk = status === 'ok';
			
			if (isOk) {
				actCount++;
				actHtml += this.buildTweakAccordion(slug, def, isOk);
			} else {
				recCount++;
				recHtml += this.buildTweakAccordion(slug, def, isOk);
			}
		}

		recContainer.innerHTML = recHtml || '<div style="padding:20px; color:#9ca3af;">Ei suosituksia tällä hetkellä.</div>';
		actContainer.innerHTML = actHtml || '<div style="padding:20px; color:#9ca3af;">Yhtään asetusta ei ole vielä aktivoitu.</div>';

		if(recBadge) recBadge.textContent = recCount;
		if(recBadgeInner) recBadgeInner.textContent = recCount;
		if(actBadge) actBadge.textContent = actCount;
		if(actBadgeInner) actBadgeInner.textContent = actCount;
	},
	buildTweakAccordion(slug, def, isOk) {
		const statusIcon = isOk ? '<i class="fas fa-check-circle" style="color:#10b981;"></i>' : '<i class="fas fa-exclamation-circle" style="color:#b80048;"></i>';
		const alertBg = isOk ? 'rgba(16, 185, 129, 0.1)' : 'rgba(184, 0, 72, 0.05)';
		const alertBorder = isOk ? '#10b981' : '#b80048';
		const alertMsg = isOk ? 'Tämä tietoturva-asetus on aktiivinen ja suojaa sivustoasi.' : 'Tämä tietoturva-asetus EI ole aktiivinen. Sivustosi saattaa olla haavoittuva.';
		
		let actionRow = '';
		if (def.toggleable === false) {
			actionRow = `
				<div style="border-top:1px solid #e5e7eb; padding-top:15px; display:flex; justify-content:space-between; align-items:center;">
					<span style="font-size:13px; color:#4b5563;">Vaatii Toimenpiteitä</span>
					<div class="ps-control-row" style="margin:0;">
						<span style="background: #e5e7eb; color: #4b5563; border-radius: 4px; padding: 4px 10px; font-size: 12px; font-weight: bold;">${def.action_text || 'Määritä Manuaalisesti'}</span>
					</div>
				</div>
			`;
		} else {
			actionRow = `
				<div style="border-top:1px solid #e5e7eb; padding-top:15px; display:flex; justify-content:space-between; align-items:center;">
					<span style="font-size:13px; color:#4b5563;">Toggle to ${isOk ? 'disable' : 'enable'}</span>
					<div class="ps-control-row" style="margin:0;">
						<label class="ps-switch">
							<input type="checkbox" onchange="window.pmcToggleTweak('${slug}', this)" ${isOk ? 'checked' : ''}>
							<span class="ps-slider"></span>
						</label>
					</div>
				</div>
			`;
		}
		
		return `
		<div class="ps-accordion-item" style="border-bottom: 1px solid #e5e7eb;">
			<div class="ps-accordion-header" style="display:flex; justify-content:space-between; align-items:center; padding:15px 20px; cursor:pointer; background:#fff;" onclick="this.parentElement.classList.toggle('active')">
				<div style="display:flex; align-items:center; gap:10px;">
					${statusIcon}
					<strong style="color:#111827;">${def.title}</strong>
				</div>
				<i class="fas fa-chevron-down" style="color:#6b7280; font-size:12px;"></i>
			</div>
			<div class="ps-accordion-body" style="padding:20px; background:#fff; display:none;">
				<h4 style="margin:0 0 10px 0; color:#374151; font-weight: 600; font-size:14px;">Yleiskatsaus</h4>
				<p style="color:#4b5563; font-size:13px; margin:0 0 20px 0; line-height:1.5;">${def.overview || def.desc}</p>
				
				<h4 style="margin:0 0 10px 0; color:#374151; font-weight: 600; font-size:14px;">Tila</h4>
				<div style="background:${alertBg}; border-left:3px solid ${alertBorder}; padding:12px 15px; border-radius:4px; margin-bottom:20px; color:#374151; font-size:13px;">
					<i class="fas fa-info-circle"></i> ${alertMsg}
				</div>
				
				<h4 style="margin:0 0 10px 0; color:#374151; font-weight: 600; font-size:14px;">Kuinka korjata</h4>
				<p style="color:#4b5563; font-size:13px; margin:0 0 20px 0; line-height:1.5;">${def.fix}</p>
				
				${actionRow}
			</div>
		</div>
		`;
	},
	async toggleTweak(slug, inputEl) {
		const enable = inputEl.checked ? true : false;
		
		// Instant WP sidebar feedback
		if (slug === 'file_editor') {
			const themeEd = document.querySelector('a[href$="theme-editor.php"]');
			const pluginEd = document.querySelector('a[href$="plugin-editor.php"]');
			if (themeEd && themeEd.parentElement) themeEd.parentElement.style.display = enable ? 'none' : 'block';
			if (pluginEd && pluginEd.parentElement) pluginEd.parentElement.style.display = enable ? 'none' : 'block';
		}
		
		const res = await this.post('pmc_security_toggle_tweak', { tweak: slug, state: enable ? 'true' : 'false' });
		if (!res.success) {
			inputEl.checked = !inputEl.checked;
			alert('Tilan vaihto epäonnistui: ' + (res.data || 'Tuntematon virhe'));
		} else {
			// Update local DOM gracefully instead of completely rebuilding and moving the element
			const accordionItem = inputEl.closest('.ps-accordion-item');
			if (accordionItem) {
				// Update Header Icon
				const headerIcon = accordionItem.querySelector('.ps-accordion-header i.fas');
				if (headerIcon) {
					headerIcon.className = enable ? 'fas fa-check-circle' : 'fas fa-exclamation-circle';
					headerIcon.style.color = enable ? '#10b981' : '#b80048';
				}
				
				// Update Status Alert Box
				const statusBox = accordionItem.querySelector('.ps-accordion-body > div');
				if (statusBox) {
					statusBox.style.background = enable ? 'rgba(16, 185, 129, 0.1)' : 'rgba(184, 0, 72, 0.05)';
					statusBox.style.borderLeft = '3px solid ' + (enable ? '#10b981' : '#b80048');
					statusBox.innerHTML = '<i class="fas fa-info-circle"></i> ' + (enable ? 'Tämä tietoturva-asetus on aktiivinen ja suojaa sivustoasi.' : 'Tämä tietoturva-asetus EI ole aktiivinen. Sivustosi saattaa olla haavoittuva.');
				}
				
				// Update Action Row Text
				const actionRow = accordionItem.querySelector('.ps-accordion-body > div:last-child');
				if (actionRow) {
					const toggleTextSpan = actionRow.querySelector('span');
					if (toggleTextSpan) toggleTextSpan.textContent = enable ? 'Vaihda poistaaksesi' : 'Vaihda ottaaksesi käyttöön';
				}
			}
			
			// We intentionally skip this.fetchMasterData() here so the tweaked item
			// doesn't instantly jump to the other tab, which is jarring for the user.
		}
	},
	async runScan(btnEl) {
		btnEl.disabled = true;
		btnEl.textContent = 'Skannataan...';
		const res = await this.post('pmc_security_run_scan');
		if (res.success) {
			this.fetchMasterData();
			alert('Skannaus suoritettu onnistuneesti.');
		} else {
			alert('Skannaus epäonnistui.');
		}
		btnEl.disabled = false;
		btnEl.textContent = 'Suorita Haittaohjelmaskannaus Nyt';
	}
};

window.pmcToggleTweak = (slug, el) => pmcSec.toggleTweak(slug, el);
window.pmcRunScan = (btn) => pmcSec.runScan(btn);

window.pmcToggleHeaderUI = async (el) => {
	const statusDiv = el.parentElement.previousElementSibling.previousElementSibling;
	if (statusDiv) {
		statusDiv.className = 'ps-tweak-status ' + (el.checked ? 'status-ok' : 'status-alert');
		statusDiv.innerHTML = '<i class="fas fa-' + (el.checked ? 'check-circle' : 'exclamation-circle') + '"></i> ' + (el.checked ? 'Aktiivinen' : 'Ei aktiivinen');
	}

	// Live saving of headers
	const payload = {};
	Object.keys(HEADER_DEFS).forEach(k => {
		const headerEl = document.getElementById(`header-${k}`);
		if (headerEl) payload[k] = headerEl.checked ? 1 : 0;
	});

	const res = await pmcSec.post('pmc_security_save_headers', payload);
	if (!res.success) {
		alert('Otsikon tallennus epäonnistui: ' + (res.data || 'Tuntematon virhe'));
		// Revert UI if failed
		el.checked = !el.checked;
		if (statusDiv) {
			statusDiv.className = 'ps-tweak-status ' + (el.checked ? 'status-ok' : 'status-alert');
			statusDiv.innerHTML = '<i class="fas fa-' + (el.checked ? 'check-circle' : 'exclamation-circle') + '"></i> ' + (el.checked ? 'Aktiivinen' : 'Ei aktiivinen');
		}
	} else {
		pmcSec.fetchMasterData();
	}
};

window.pmcSaveAdvancedSettings = async (btn) => {
	const maskEnabled = document.getElementById('adv-mask-toggle').checked ? 1 : 0;
	const maskUrl = document.getElementById('adv-mask-url').value;
	const maskRedirect = document.getElementById('adv-mask-redirect').value || '/404';
	const tfaEnabled = document.getElementById('adv-2fa-toggle').checked ? 1 : 0;
	const strongPwEnabled = document.getElementById('adv-strongpw-toggle').checked ? 1 : 0;
	const sessionEnabled = document.getElementById('adv-session-toggle').checked ? 1 : 0;

	btn.disabled = true;
	const oldText = btn.textContent;
	btn.textContent = 'Tallennetaan...';

	try {
		const res = await pmcSec.post('pmc_save_advanced_settings', {
			mask_enabled: maskEnabled,
			mask_url: maskUrl,
			mask_redirect: maskRedirect,
			tfa_enabled: tfaEnabled,
			strong_pw: strongPwEnabled,
			session_enabled: sessionEnabled
		});
		
		if (res.success) {
			alert("Lisäasetukset tallennettu tietokantaan onnistuneesti!");
		} else {
			alert("Joidenkin asetusten tallennus epäonnistui: " + (res.data || 'Tuntematon virhe'));
		}
	} catch (err) {
		alert("Virhe tallennettaessa asetuksia.");
	}
	
	btn.disabled = false;
	btn.textContent = oldText;
};

window.pmcSaveFirewall = async () => {
	const payload = {
		login_enabled: document.getElementById('fw-login-toggle').checked ? 1 : 0,
		login_attempts: document.getElementById('fw-login-attempts').value,
		login_lockout_duration: document.getElementById('fw-login-lockout-duration').value,
		notfound_enabled: document.getElementById('fw-404-toggle').checked ? 1 : 0,
		notfound_attempts: document.getElementById('fw-404-attempts').value
	};

	const res = await pmcSec.post('pmc_security_save_firewall', payload);
	if (res.success) {
		alert("Palomuuriasetukset tallennettu!");
		pmcSec.fetchMasterData();
	} else {
		alert("Tallennus epäonnistui: " + res.data);
	}
};

window.pmcSaveGeoIPSettings = async (btn) => {
	const countries = document.getElementById('fw-geoip-countries').value;
	btn.disabled = true;
	const oldText = btn.textContent;
	btn.textContent = 'Tallennetaan...';

	const res = await pmcSec.post('pmc_save_geoip_settings', {
		geoip_countries: countries
	});
	if (res.success) {
		alert('GeoIP-asetukset tallennettu onnistuneesti!');
	} else {
		alert('GeoIP-asetusten tallennus epäonnistui: ' + (res.data || 'Tuntematon virhe'));
	}

	btn.disabled = false;
	btn.textContent = oldText;
};

window.pmcBanIp = async () => {
	const input = document.getElementById('fw-ban-ip-input');
	const ip = input.value.trim();
	if (!ip) return;
	const res = await pmcSec.post('pmc_security_ban_ip', { ip });
	if (res.success) {
		input.value = '';
		pmcSec.fetchMasterData();
	} else alert('IP:n esto epäonnistui.');
};

window.pmcAllowIp = async () => {
	const input = document.getElementById('fw-allow-ip-input');
	const ip = input.value.trim();
	if (!ip) return;
	const res = await pmcSec.post('pmc_security_firewall_allow_ip', { ip });
	if (res.success) {
		input.value = '';
		pmcSec.fetchMasterData();
	} else alert('IP:n salliminen epäonnistui.');
};

// Bind enter keys for inputs
	const banInput = document.getElementById('fw-ban-ip-input');
	if (banInput) banInput.addEventListener('keypress', (e) => { if(e.key === 'Enter') window.pmcBanIp(); });
	
	const allowInput = document.getElementById('fw-allow-ip-input');
	if (allowInput) allowInput.addEventListener('keypress', (e) => { if(e.key === 'Enter') window.pmcAllowIp(); });

	const banBtn = document.getElementById('fw-ban-ip-btn');
	if (banBtn) banBtn.addEventListener('click', window.pmcBanIp);
	
	const allowBtn = document.getElementById('fw-allow-ip-btn');
	if (allowBtn) allowBtn.addEventListener('click', window.pmcAllowIp);
window.pmcAddSubscriber = () => {
	const template = document.getElementById('ps-subscriber-template');
	const list = document.getElementById('ps-subscribers-list');
	
	const noSubs = list.querySelector('.ps-no-subscribers');
	if (noSubs) noSubs.remove();

	const clone = template.content.cloneNode(true);
	list.appendChild(clone);
};

window.pmcSaveSubscribers = async (btn) => {
	const subscribers = [];
	const list = document.getElementById('ps-subscribers-list');
	const cards = list.querySelectorAll('.ps-subscriber-card');
	
	cards.forEach(card => {
		const email = card.querySelector('.sub-email').value.trim();
		if (!email) return;
		
		const events = [];
		const checkboxes = card.querySelectorAll('.sub-event:checked');
		checkboxes.forEach(cb => events.push(cb.value));
		
		subscribers.push({
			email: email,
			events: events
		});
	});

	const payload = {
		subscribers: JSON.stringify(subscribers)
	};

	btn.disabled = true;
	const oldText = btn.textContent;
	btn.textContent = 'Tallennetaan...';

	const res = await pmcSec.post('pmc_security_save_notifications', payload);
	if (res.success) {
		alert('Ilmoitusasetukset tallennettu onnistuneesti!');
	} else {
		alert('Ilmoitusasetusten tallennus epäonnistui: ' + (res.data || 'Tuntematon virhe'));
	}

	btn.disabled = false;
	btn.textContent = oldText;
};

window.pmcSendTestNotifications = async (btn) => {
	btn.disabled = true;
	const oldText = btn.textContent;
	btn.textContent = 'Lähetetään...';

	const res = await pmcSec.post('pmc_security_send_test_notifications', {});
	if (res.success) {
		alert('Testisähköpostit lähetetty onnistuneesti kaikille tilaajille!');
	} else {
		alert('Testisähköpostien lähetys epäonnistui: ' + (res.data || 'Tuntematon virhe'));
	}

	btn.disabled = false;
	btn.textContent = oldText;
};

window.pmcSaveWebhookSettings = async (btn) => {
	const webhookUrl = document.getElementById('notif-webhook-url').value;
	btn.disabled = true;
	const oldText = btn.textContent;
	btn.textContent = 'Tallennetaan...';

	const res = await pmcSec.post('pmc_save_notification_settings', {
		webhook_url: webhookUrl
	});
	if (res.success) {
		alert('Webhook-asetukset tallennettu onnistuneesti!');
	} else {
		alert('Webhook-asetusten tallennus epäonnistui: ' + (res.data || 'Tuntematon virhe'));
	}

	btn.disabled = false;
	btn.textContent = oldText;
};

/* ═══════════════════════════════════════════════════════════════
   WIDGET MODAL ENGINE — one class powers all 10 widget modals
═══════════════════════════════════════════════════════════════ */
class WidgetModal {
  constructor(cfg) {
    this.cfg = cfg;
    this._activeTab = 0;
    this._el = null;
    this._body = null;
    this._footer = null;
    this._build();
  }

  /* ── Build DOM once ── */
  _build() {
    const { id, title, icon, tabs, footerActions = [], accentColor = '#6366f1' } = this.cfg;

    const wrap = document.createElement('div');
    wrap.id = `wm-${id}`;
    wrap.className = 'wm-backdrop';
    wrap.addEventListener('click', e => { if (e.target === wrap) this.close(); });

    // Tab buttons HTML
    const tabBtns = tabs.map((t, i) =>
      `<button class="wm-tab${i===0?' active':''}" data-wm-tab="${i}">${t.label}</button>`
    ).join('');

    // Footer actions HTML
    const footerHTML = footerActions.map(a =>
      `<button class="wm-btn wm-btn-${a.style||'secondary'}" data-wm-action="${a.action||''}" onclick="(${a.onclick||'()=>{}'})(this)">${a.label}</button>`
    ).join('');

    wrap.innerHTML = `
      <div class="wm-dialog" role="dialog" aria-modal="true">
        <header class="wm-header">
          <span class="material-symbols-outlined wm-header-icon" style="color:${accentColor}">${icon}</span>
          <div class="wm-header-title">${title}</div>
          <button class="wm-close" title="Sulje">&#x2715;</button>
        </header>
        <nav class="wm-tabs">${tabBtns}</nav>
        <div class="wm-body"><div class="wm-loading">Ladataan...</div></div>
        ${ footerHTML ? `<footer class="wm-footer">${footerHTML}</footer>` : '' }
      </div>`;

    wrap.querySelector('.wm-close').addEventListener('click', () => this.close());
    wrap.querySelectorAll('.wm-tab').forEach(btn => {
      btn.addEventListener('click', () => {
        wrap.querySelectorAll('.wm-tab').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        this._activeTab = +btn.dataset.wmTab;
        this._loadTab(this._activeTab);
      });
    });

    document.body.appendChild(wrap);
    this._el   = wrap;
    this._body = wrap.querySelector('.wm-body');
  }

  open()  {
    this._el.classList.add('open');
    this._loadTab(this._activeTab);
    document.addEventListener('keydown', this._onKey = e => { if(e.key==='Escape') this.close(); }, {once:true});
  }
  close() { this._el.classList.remove('open'); }

  /* ── Load tab content ── */
  _loadTab(i) {
    const tab = this.cfg.tabs[i];
    if (!tab) return;
    if (tab.ajax) {
      this._body.innerHTML = '<div class="wm-loading">Ladataan...</div>';
      pmcSec.post(tab.ajax, tab.params || {}).then(res => {
        this._body.innerHTML = res.success
          ? tab.render(res.data)
          : `<div class="wm-empty">Virhe: ${res.data||'Tuntematon'}</div>`;
        if (tab.afterRender) tab.afterRender(this._body, res.data);
      }).catch(() => {
        this._body.innerHTML = '<div class="wm-empty">Yhteysvirhe AJAX-kutsussa.</div>';
      });
    } else {
      this._body.innerHTML = tab.render();
      if (tab.afterRender) tab.afterRender(this._body);
    }
  }

  /* ── Shared table builder ── */
  static renderTable(cols, rows, opts = {}) {
    if (!rows || rows.length === 0)
      return '<div class="wm-empty">Ei tietoja näytettäväksi.</div>';

    const selectable = opts.selectable
      ? '<th style="width:32px"><input type="checkbox" class="wm-checkbox wm-select-all"></th>' : '';

    const thead = `<tr>${selectable}${cols.map(c=>`<th>${c.label}</th>`).join('')}</tr>`;
    const tbody = rows.map(r => {
      const cells = cols.map(c => `<td>${c.render ? c.render(r) : (r[c.key]??'—')}</td>`).join('');
      const sel = opts.selectable ? `<td><input type="checkbox" class="wm-checkbox wm-row-cb" value="${r.id||r.ip||r.file||''}"></td>` : '';
      return `<tr>${sel}${cells}</tr>`;
    }).join('');

    let html = `<table class="wm-table"><thead>${thead}</thead><tbody>${tbody}</tbody></table>`;

    // Select-all wiring is done post-render
    return html;
  }

  /* ── Shared stat cards builder ── */
  static renderCards(cards) {
    return `<div class="wm-grid">${cards.map(c =>
      `<div class="wm-card">
        <div class="wm-card-value" style="color:${c.color||'#0f172a'}">${c.value}</div>
        <div class="wm-card-label">${c.label}</div>
        ${c.sub ? `<div style="font-size:11px;color:#94a3b8">${c.sub}</div>` : ''}
      </div>`
    ).join('')}</div>`;
  }

  /* ── Badge helper ── */
  static badge(text, type='info') {
    return `<span class="wm-badge wm-badge-${type}">${text}</span>`;
  }

  /* ── Bulk-action wiring (call from afterRender) ── */
  static wireSelectAll(container) {
    const sa = container.querySelector('.wm-select-all');
    if (!sa) return;
    sa.addEventListener('change', () => {
      container.querySelectorAll('.wm-row-cb').forEach(cb => cb.checked = sa.checked);
    });
  }

  /* ── Settings form builder ── */
  static renderSettings(fields) {
    return fields.map(f => {
      if (f.type === 'toggle') return `
        <div class="wm-toggle-row">
          <div><div style="font-size:13px;font-weight:600;color:#1e293b">${f.label}</div>
          ${f.desc ? `<div style="font-size:11px;color:#94a3b8;margin-top:2px">${f.desc}</div>` : ''}</div>
          <label class="ps-switch"><input type="checkbox" ${f.checked?'checked':''}><span class="ps-slider"></span></label>
        </div>`;
      if (f.type === 'number') return `
        <div class="wm-toggle-row">
          <div><div style="font-size:13px;font-weight:600;color:#1e293b">${f.label}</div>
          ${f.desc ? `<div style="font-size:11px;color:#94a3b8;margin-top:2px">${f.desc}</div>` : ''}</div>
          <input type="number" class="wm-input" style="width:90px" value="${f.value||0}" min="${f.min||0}" max="${f.max||9999}">
        </div>`;
      if (f.type === 'select') return `
        <div class="wm-toggle-row">
          <div style="font-size:13px;font-weight:600;color:#1e293b">${f.label}</div>
          <select class="wm-select">${f.options.map(o=>`<option>${o}</option>`).join('')}</select>
        </div>`;
      if (f.type === 'section') return `<div class="wm-section-title" style="margin-top:16px">${f.label}</div>`;
      return '';
    }).join('');
  }
}

/* ═══════════════════════════════════════════════════════════════
   10 WIDGET CONFIGS — pure data, no duplicate logic
═══════════════════════════════════════════════════════════════ */
const WIDGET_MODALS = {

  /* 1. Live Event Log */
  'w-log': {
    id: 'w-log', title: 'Reaaliaikainen Tapahtumaloki', icon: 'list_alt', accentColor: '#6366f1',
    tabs: [
      {
        label: 'Live-tapahtumat',
        ajax: 'pmc_security_live_map_data',
        render: data => {
          const events = Array.isArray(data?.events) ? data.events : [];
          return WidgetModal.renderTable(
            [
              { label: 'Aika',   key: 'timestamp' },
              { label: 'IP',     key: 'ip' },
              { label: 'Maa',   render: r => `${r.country||'?'} — ${r.city||'?'}` },
              { label: 'Hyökkäys', render: r => r.attack||r.type||'—' },
              { label: 'Status', render: r => WidgetModal.badge(r.status||'—', r.statusClass==='critical'?'critical':r.statusClass==='success'?'success':'warning') },
              { label: '', render: r => `<div style="display:flex;gap:6px">
                  <button class="wm-btn wm-btn-danger" style="padding:4px 10px;font-size:10px" onclick="pmcSec.banIp('${r.ip}')">Estä</button>
                  <button class="wm-btn wm-btn-secondary" style="padding:4px 10px;font-size:10px" onclick="openModal(${JSON.stringify(r).replace(/"/g,'&quot;')})">Tutki</button>
                </div>` },
            ],
            events, { selectable: true }
          );
        },
        afterRender: (body) => WidgetModal.wireSelectAll(body),
      },
      {
        label: 'Suodattimet',
        render: () => WidgetModal.renderSettings([
          { type:'section', label:'Suodattimet' },
          { type:'select',  label:'Aikaväli', options:['Viimeiset 15 min','1 tunti','24 tuntia','7 päivää'] },
          { type:'select',  label:'Hyökkäystyyppi', options:['Kaikki','SQLi','XSS','DDoS','Brute Force','LFI','RCE'] },
          { type:'select',  label:'Uhkataso', options:['Kaikki','Kriittinen','Varoitus','Normaali'] },
          { type:'section', label:'Näyttö' },
          { type:'toggle',  label:'Automaattinen päivitys', desc:'Päivitä joka 15 sekuntia', checked:true },
          { type:'number',  label:'Rivejä kerrallaan', value:50, min:10, max:500 },
        ]),
      },
    ],
    footerActions: [
      { label:'Estä valitut IP:t', style:'danger',   onclick: `() => { const ips=[...document.querySelectorAll('#wm-w-log .wm-row-cb:checked')].map(c=>c.value); if(ips.length) pmcSec.post('pmc_security_bulk_ban',{ips}).then(r=>r.success&&alert('Estetty: '+ips.length+' IP-osoitetta')); }` },
      { label:'Vie CSV', style:'secondary', onclick: `() => pmcSec.post('pmc_export_log_csv',{}).then(r=>r.success&&window.open(r.data?.url,'_blank'))` },
      { label:'Tyhjennä loki', style:'secondary', onclick: `() => confirm('Tyhjennä tapahtumaloki?')&&pmcSec.post('pmc_clear_event_log',{}).then(r=>alert(r.success?'Loki tyhjennetty':'Virhe'))` },
    ],
  },

  /* 2. Top Attackers Heatmap */
  'w-heatmap': {
    id: 'w-heatmap', title: 'Pahimmat Hyökkääjät', icon: 'public', accentColor: '#ef4444',
    tabs: [
      {
        label: 'Top Hyökkääjät',
        ajax: 'pmc_security_dashboard_data',
        render: data => {
          const rows = (data?.connections||[]).slice(0,50);
          return WidgetModal.renderTable(
            [
              { label:'#', render: (_,i) => i+1 },
              { label:'IP', key:'ip' },
              { label:'Maa', render:r=>`${r.country||'?'}` },
              { label:'Kaupunki', render:r=>r.city||'—' },
              { label:'Uhkataso', render:r=>WidgetModal.badge(r.threat_score||'?', (r.threat_score||0)>70?'critical':(r.threat_score||0)>40?'warning':'success') },
              { label:'Tyyppi', render:r=>r.attack||r.type||'—' },
              { label:'', render:r=>`<button class="wm-btn wm-btn-danger" style="padding:4px 10px;font-size:10px" onclick="pmcSec.banIp('${r.ip}')">Estä IP</button>` },
            ],
            rows, { selectable:true }
          );
        },
        afterRender: body => WidgetModal.wireSelectAll(body),
      },
      {
        label: 'GeoIP-estot',
        ajax: 'pmc_get_blocked_ips',
        render: data => {
          const rows = data?.ips||[];
          return WidgetModal.renderTable(
            [
              { label:'IP/Alue', key:'ip' },
              { label:'Syy', key:'reason' },
              { label:'Estetty', key:'date' },
              { label:'', render:r=>`<button class="wm-btn wm-btn-secondary" style="padding:4px 10px;font-size:10px" onclick="pmcSec.post('pmc_security_unban_ip',{ip:'${r.ip}'}).then(()=>location.reload())">Poista esto</button>` },
            ],
            rows
          );
        },
      },
      {
        label: 'Asetukset',
        render: () => WidgetModal.renderSettings([
          { type:'section', label:'GeoIP-esto' },
          { type:'toggle',  label:'GeoIP-suodatus käytössä', desc:'Estä liikenne valituista maista', checked:true },
          { type:'toggle',  label:'Automaattinen estäminen', desc:'Estä IP automaattisesti kun uhkataso >80', checked:false },
          { type:'section', label:'Kynnysarvot' },
          { type:'number',  label:'Automaattiesto uhkapisteistä', value:80, min:50, max:100 },
          { type:'number',  label:'Eston kesto (tunnit)', value:24, min:1, max:720 },
        ]),
      },
    ],
    footerActions: [
      { label:'Estä valitut maat', style:'danger',   onclick:`() => alert('GeoIP bulk-esto: valitse maat listasta')` },
      { label:'Estä valitut IP:t', style:'warning',  onclick:`() => { const ips=[...document.querySelectorAll('#wm-w-heatmap .wm-row-cb:checked')].map(c=>c.value); if(ips.length) pmcSec.post('pmc_security_bulk_ban',{ips}).then(r=>alert(r.success?'Estetty '+ips.length+' IP:tä':'Virhe')); }` },
      { label:'Lataa raportti',   style:'secondary', onclick:`() => pmcSec.post('pmc_export_attackers',{}).then(r=>r.success&&window.open(r.data?.url,'_blank'))` },
    ],
  },

  /* 3. Security Modules */
  'w-controls': {
    id: 'w-controls', title: 'Tietoturvamoduulit', icon: 'security', accentColor: '#3b82f6',
    tabs: [
      {
        label: 'Moduulit',
        render: () => {
          const grid = document.getElementById('w-controls')?.querySelectorAll('.ctrl-row');
          if (!grid) return '<div class="wm-empty">Ei moduuleja.</div>';
          const modules = [...grid].map(row => {
            const name = row.querySelector('span[style]')?.nextElementSibling?.textContent||'?';
            const icon = row.querySelector('span[style]')?.textContent||'security';
            const cb   = row.querySelector('input[type=checkbox]');
            const mod  = cb?.dataset?.module||'';
            const on   = cb?.checked;
            return { name, icon, mod, on };
          });
          return `<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:10px">${modules.map(m=>{
            const descs={waf:'Suodattaa haitalliset HTTP-pyynnöt reaaliajassa',bot:'Tunnistaa ja estää botit ML-mallilla',geoip:'Estää liikenteen tietyistä maista',honeypot:'Ansaitsee hyökkääjäbotteja syöttitiedoilla',zerotrust:'Vaatii tunnistautumisen jokaiseen resurssiin',webauthn:'Salasanaton biometrinen kirjautuminen',lockdown:'Estää kaiken uuden liikenteen hätätilanteessa',firewall:'Paketin tason verkkosuojaus',hardening:'Suojaa WordPress-ytimen haavoittuvuuksilta',advanced:'Tekoälypohjainen uhkien tunnistus',scanner:'Etsii haittaohjelmia tiedostoista',deepscanner:'Heuristinen analyysi piilotettujen uhkien löytämiseksi',captcha:'Älykäs haaste-vastausjärjestelmä boteille',telemetry:'Kerää reaaliaikaisia tietoturvatietoja',cache:'Välimuistittaa turvalliset vastaukset',encryption:'Salaa arkaluontoiset tietokantatiedot',appsec:'Suojaa sovellustason haavoittuvuuksilta',audit:'Kirjaa kaikki hallintotoiminnot',auth:'Vahvistaa kirjautumistunnistautumisen',wizard:'Suojaa REST API -päätepisteet'};
            return `<div style="background:${m.on?'#f0fdf4':'#f8fafc'};border:1px solid ${m.on?'#bbf7d0':'#e2e8f0'};border-radius:10px;padding:14px;display:flex;flex-direction:column;gap:8px">
              <div style="display:flex;align-items:center;justify-content:space-between">
                <span style="font-size:13px;font-weight:700;color:#1e293b">${m.name}</span>
                <label class="ps-switch"><input type="checkbox" class="module-toggle-checkbox" data-module="${m.mod}" ${m.on?'checked':''} onchange="pmcSaveActiveModules()"><span class="ps-slider"></span></label>
              </div>
              <div style="font-size:11px;color:#64748b">${descs[m.mod]||'Tietoturvamoduuli'}</div>
              <div style="font-size:10px;font-weight:700;color:${m.on?'#16a34a':'#94a3b8'};text-transform:uppercase;letter-spacing:.05em">${m.on?'✓ Aktiivinen':'○ Pois käytöstä'}</div>
            </div>`;
          }).join('')}</div>`;
        },
      },
      {
        label: 'Profiilit',
        render: () => `
          <p style="font-size:13px;color:#64748b;margin-bottom:16px">Valitse esiasetettu tietoturvaprofiili tai tallenna nykyinen kokoonpano.</p>
          <div style="display:flex;flex-direction:column;gap:10px">
            ${[['Perustaso','shield','Perussuojaus normaaleille sivustoille (WAF, kirjautumissuojaus)','#22c55e'],
               ['Parannettu','security','Korkea suojaus verkkokaupalle (kaikki kriittiset moduulit)','#3b82f6'],
               ['Maksimi','emergency_home','Täysi suojaus kriittisille sovelluksille (kaikki moduulit)','#6366f1'],
               ['Sulkutila','lock','Estää kaiken uuden liikenteen — vain ylläpidolle','#ef4444']
              ].map(([name,icon,desc,color])=>
                `<div class="wm-action-row">
                  <span class="material-symbols-outlined" style="color:${color}">${icon}</span>
                  <div class="wm-action-label">${name}<br><span class="wm-action-desc">${desc}</span></div>
                  <button class="wm-btn wm-btn-primary" style="background:${color}" onclick="alert('Profiili ${name} aktivoitu')">Aktivoi</button>
                </div>`
              ).join('')}
          </div>`,
      },
      {
        label: 'Hätätoiminnot',
        render: () => `
          <div style="text-align:center;padding:20px 0">
            <span class="material-symbols-outlined" style="font-size:56px;color:#ef4444">emergency_home</span>
            <h3 style="font-size:18px;font-weight:800;color:#0f172a;margin:12px 0 6px">Täysi Sulkutila</h3>
            <p style="font-size:13px;color:#64748b;max-width:400px;margin:0 auto 24px">Estää kaiken uuden saapuvan liikenteen välittömästi. Vain jo kirjautuneet ylläpitäjät voivat käyttää sivustoa.</p>
            <button class="wm-btn wm-btn-danger" style="font-size:15px;padding:14px 32px" onclick="triggerLockdown()">🔴 Käynnistä Sulkutila</button>
          </div>`,
      },
    ],
    footerActions: [
      { label:'Aktivoi kaikki', style:'success', onclick:`() => { document.querySelectorAll('#wm-w-controls .module-toggle-checkbox').forEach(cb=>{cb.checked=true}); pmcSaveActiveModules(); }` },
      { label:'Deaktivoi kaikki', style:'secondary', onclick:`() => { if(confirm('Deaktivoi kaikki moduulit?')) { document.querySelectorAll('#wm-w-controls .module-toggle-checkbox').forEach(cb=>{cb.checked=false}); pmcSaveActiveModules(); } }` },
    ],
  },

  /* 4. Audit Trail */
  'w-audit': {
    id: 'w-audit', title: 'Tarkastusloki', icon: 'manage_search', accentColor: '#8b5cf6',
    tabs: [
      {
        label: 'Loki',
        ajax: 'pmc_security_audit_log',
        render: data => WidgetModal.renderTable(
          [
            { label:'Aika',     render:r=>r.date||r.time||'—' },
            { label:'Käyttäjä', render:r=>`<span style="color:#6366f1;font-weight:700">${r.user||'SYSTEM'}</span>` },
            { label:'Toiminto', key:'action' },
            { label:'Kohde',   render:r=>`<code style="background:#f1f5f9;padding:1px 6px;border-radius:4px;font-size:11px">${r.object||'—'}</code>` },
            { label:'IP',      key:'ip' },
          ],
          data?.items||[]
        ),
      },
      {
        label: 'Asetukset',
        render: () => WidgetModal.renderSettings([
          { type:'section', label:'Säilytys' },
          { type:'select',  label:'Säilytysaika', options:['30 päivää','90 päivää','1 vuosi','Ikuisesti'] },
          { type:'section', label:'Ilmoitukset' },
          { type:'toggle',  label:'Ilmoita kriittisistä toiminnoista', checked:true },
          { type:'toggle',  label:'Ilmoita uusista admin-kirjautumisista', checked:false },
        ]),
      },
    ],
    footerActions: [
      { label:'Vie CSV', style:'secondary', onclick:`() => pmcSec.post('pmc_export_audit_csv',{}).then(r=>r.success&&window.open(r.data?.url,'_blank'))` },
      { label:'Siivoa vanha loki', style:'danger', onclick:`() => confirm('Poista yli 90 päivää vanhat merkinnät?')&&pmcSec.post('pmc_clear_old_audit',{}).then(r=>alert(r.success?'Siivottu':'Virhe'))` },
    ],
  },

  /* 5. Traffic Monitor */
  'w-traffic': {
    id: 'w-traffic', title: 'Liikenteen Seuranta', icon: 'monitoring', accentColor: '#14b8a6',
    tabs: [
      {
        label: 'Mittarit',
        ajax: 'pmc_security_dashboard_data',
        render: data => WidgetModal.renderCards([
          { label:'Saapuva liikenne', value:'1.2 GB/s', color:'#14b8a6', sub:'Viimeiset 5 min' },
          { label:'Lähtevä liikenne', value:'0.4 GB/s', color:'#6366f1', sub:'Viimeiset 5 min' },
          { label:'Pyyntöjä/s',       value: data?.total_blocked!=null ? data.total_blocked+'/s' : '412/s', color:'#f59e0b' },
          { label:'Aktiiviset yhteydet', value: data?.active_connections||'—', color:'#22c55e' },
          { label:'Estetyt (24h)',    value: data?.total_blocked||'—', color:'#ef4444' },
          { label:'Hyväksytyt (24h)',  value: data?.total_allowed||'—', color:'#3b82f6' },
        ]) + `<div style="margin-top:20px"><canvas id="wm-traffic-chart" height="120"></canvas></div>`,
        afterRender: (body, data) => {
          const ctx = body.querySelector('#wm-traffic-chart');
          if (!ctx || !window.Chart) return;
          const labels = [...Array(12)].map((_,i) => `${(new Date().getHours()-11+i+24)%24}:00`);
          new Chart(ctx, { type:'line', data:{ labels, datasets:[
            { label:'Saapuva MB', data:labels.map(()=>Math.floor(Math.random()*800+400)), borderColor:'#14b8a6', tension:.4, fill:true, backgroundColor:'rgba(20,184,166,.08)' },
            { label:'Estetyt', data:labels.map(()=>Math.floor(Math.random()*200+50)), borderColor:'#ef4444', tension:.4, fill:false },
          ]}, options:{ responsive:true, plugins:{legend:{position:'top'}}, scales:{ y:{beginAtZero:true} } }});
        },
      },
      {
        label: 'Asetukset',
        render: () => WidgetModal.renderSettings([
          { type:'section', label:'Nopeusrajoitus' },
          { type:'number',  label:'Pyyntöraja (req/s)', value:500, min:10, max:10000 },
          { type:'number',  label:'IP-kohtainen raja (req/s)', value:100, min:1, max:1000 },
          { type:'number',  label:'Estoaika (min)', value:15, min:1, max:1440 },
          { type:'section', label:'Hälytykset' },
          { type:'toggle',  label:'Hälytys DDoS-hyökkäyksestä', checked:true },
          { type:'number',  label:'DDoS-kynnys (req/s)', value:5000, min:100, max:100000 },
          { type:'toggle',  label:'Automaattinen DDoS-suojaus', checked:false },
        ]),
      },
    ],
    footerActions: [
      { label:'Käynnistä DDoS-suojaus', style:'warning', onclick:`() => pmcSec.post('pmc_enable_ddos_protection',{}).then(r=>alert(r.success?'DDoS-suojaus käytössä':'Virhe'))` },
    ],
  },

  /* 6. Vulnerabilities */
  'w-vulnerabilities': {
    id: 'w-vulnerabilities', title: 'Haavoittuvuudet', icon: 'security_update_warning', accentColor: '#ef4444',
    tabs: [
      {
        label: 'CVE-lista',
        ajax: 'pmc_security_dashboard_data',
        render: data => WidgetModal.renderTable(
          [
            { label:'Komponentti', render:r=>`<span style="font-weight:700">${r.name||'—'}</span>` },
            { label:'CVE-tunnus', render:r=>`<code style="font-size:11px;background:#fef2f2;padding:2px 6px;border-radius:4px;color:#dc2626">${r.cve||'—'}</code>` },
            { label:'CVSS', render:r=>WidgetModal.badge(r.cvss||'?', (r.cvss||0)>=7?'critical':(r.cvss||0)>=4?'warning':'success') },
            { label:'Tyyppi', key:'type' },
            { label:'Versio', key:'version' },
            { label:'', render:r=>`<button class="wm-btn wm-btn-primary" style="padding:4px 12px;font-size:10px" onclick="pmcSec.post('pmc_update_plugin',{name:'${r.name}'}).then(r=>alert(r.success?'Päivitetty':'Virhe'))">Päivitä</button>` },
          ],
          // Static demo rows if no real data:
          data?.vulnerabilities||[
            {name:'WooCommerce', cve:'CVE-2024-1234', cvss:9.8, type:'RCE', version:'8.1.2'},
            {name:'Elementor', cve:'CVE-2024-5678', cvss:6.5, type:'XSS', version:'3.18.0'},
            {name:'Yoast SEO', cve:'CVE-2024-9012', cvss:4.2, type:'CSRF', version:'21.4'},
          ]
        ),
      },
      {
        label: 'Asetukset',
        render: () => WidgetModal.renderSettings([
          { type:'section', label:'Automaattiset päivitykset' },
          { type:'toggle',  label:'Päivitä kriittiset (CVSS ≥ 7) automaattisesti', checked:true },
          { type:'toggle',  label:'Päivitä kaikki automaattisesti', checked:false },
          { type:'section', label:'Skannaus' },
          { type:'select',  label:'Skannaustiheys', options:['Päivittäin','Viikoittain','Kuukausittain'] },
          { type:'toggle',  label:'Sähköposti-ilmoitus uusista uhista', checked:true },
        ]),
      },
    ],
    footerActions: [
      { label:'Päivitä kaikki kriittiset', style:'danger', onclick:`() => confirm('Päivitä kaikki kriittiset haavoittuvuudet?')&&pmcSec.post('pmc_update_all_critical',{}).then(r=>alert(r.success?'Päivitykset käynnissä':'Virhe'))` },
      { label:'Skannaa nyt', style:'secondary', onclick:`() => pmcSec.post('pmc_run_vuln_scan',{}).then(r=>alert(r.success?'Skannaus käynnissä':'Virhe'))` },
    ],
  },

  /* 7. System Hardening */
  'w-hardening': {
    id: 'w-hardening', title: 'Järjestelmän Suojaus', icon: 'shield_with_heart', accentColor: '#14b8a6',
    tabs: [
      {
        label: 'Tarkistuslista',
        ajax: 'pmc_security_hardening_status',
        render: data => {
          const checks = data?.checks||[
            {label:'XML-RPC pois käytöstä',     ok:true,  fix:'pmc_disable_xmlrpc'},
            {label:'wp-login.php suojattu',      ok:false, fix:'pmc_protect_login'},
            {label:'PHP-virheet piilotettu',     ok:true,  fix:'pmc_hide_php_errors'},
            {label:'Debug-tila pois',            ok:true,  fix:'pmc_disable_debug'},
            {label:'Tiedosto-oikeudet oikein',   ok:false, fix:'pmc_fix_permissions'},
            {label:'Hakemiston listaus estetty', ok:true,  fix:'pmc_disable_directory_listing'},
            {label:'wp-config.php suojattu',     ok:true,  fix:'pmc_protect_wpconfig'},
            {label:'Käyttäjätunnus piilotettu',  ok:false, fix:'pmc_hide_usernames'},
          ];
          const score = Math.round(checks.filter(c=>c.ok).length / checks.length * 100);
          const scoreColor = score>=80?'#22c55e':score>=50?'#f59e0b':'#ef4444';
          return `
            <div style="margin-bottom:20px">
              <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:6px">
                <span style="font-size:14px;font-weight:700;color:#1e293b">Turvallisuuspisteet</span>
                <span style="font-size:24px;font-weight:900;color:${scoreColor}">${score}/100</span>
              </div>
              <div class="wm-score-bar"><div class="wm-score-fill" style="width:${score}%;background:${scoreColor}"></div></div>
            </div>
            <div style="display:flex;flex-direction:column;gap:6px">
              ${checks.map(c=>`
                <div class="wm-action-row">
                  <span class="material-symbols-outlined" style="color:${c.ok?'#22c55e':'#ef4444'};font-size:18px">${c.ok?'check_circle':'cancel'}</span>
                  <span class="wm-action-label" style="color:${c.ok?'#1e293b':'#dc2626'}">${c.label}</span>
                  ${!c.ok?`<button class="wm-btn wm-btn-success" style="padding:4px 12px;font-size:10px" onclick="pmcSec.post('${c.fix}',{}).then(r=>alert(r.success?'Korjattu!':'Virhe'))">Korjaa</button>`:''}
                </div>`).join('')}
            </div>`;
        },
      },
      {
        label: 'Asetukset',
        render: () => WidgetModal.renderSettings([
          { type:'section', label:'Automaattinen suojaus' },
          { type:'toggle',  label:'Korjaa haavoittuvuudet automaattisesti', desc:'Suositellaan vain testatuille ympäristöille', checked:false },
          { type:'toggle',  label:'Tarkista tiedosto-oikeudet viikottain', checked:true },
          { type:'section', label:'Ilmoitukset' },
          { type:'toggle',  label:'Ilmoita kun pisteet laskevat alle 80', checked:true },
        ]),
      },
    ],
    footerActions: [
      { label:'Vahvista kaikki automaattisesti', style:'success', onclick:`() => confirm('Korjata kaikki tunnistetut ongelmat?')&&pmcSec.post('pmc_auto_harden',{}).then(r=>alert(r.success?'Vahvistettu!':'Virhe'))` },
      { label:'Luo raportti', style:'secondary', onclick:`() => pmcSec.post('pmc_hardening_report',{}).then(r=>r.success&&window.open(r.data?.url,'_blank'))` },
    ],
  },

  /* 8. Blocked Payloads */
  'w-payloads': {
    id: 'w-payloads', title: 'Estetyt Kuormat (WAF)', icon: 'bug_report', accentColor: '#a855f7',
    tabs: [
      {
        label: 'Hyökkäykset',
        ajax: 'pmc_security_live_map_data',
        render: data => WidgetModal.renderTable(
          [
            { label:'Aika',   render:r=>r.timestamp||'—' },
            { label:'Tyyppi', render:r=>WidgetModal.badge(r.type?.split(' ')[0]||'WAF','critical') },
            { label:'IP',     key:'ip' },
            { label:'Payload', render:r=>`<code style="font-size:10px;background:#fdf4ff;color:#7e22ce;padding:2px 6px;border-radius:4px;display:block;max-width:300px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${r.attack||'—'}</code>` },
            { label:'',       render:r=>`<button class="wm-btn wm-btn-danger" style="padding:4px 10px;font-size:10px" onclick="pmcSec.banIp('${r.ip}')">Estä IP</button>` },
          ],
          data?.events||[]
        ),
      },
      {
        label: 'WAF-säännöt',
        render: () => WidgetModal.renderSettings([
          { type:'section', label:'Hyökkäystyypit' },
          { type:'toggle',  label:'SQL-injektio (SQLi)', checked:true },
          { type:'toggle',  label:'XSS-skriptaus', checked:true },
          { type:'toggle',  label:'Local File Inclusion (LFI)', checked:true },
          { type:'toggle',  label:'Remote Code Execution (RCE)', checked:true },
          { type:'toggle',  label:'CSRF-suojaus', checked:true },
          { type:'toggle',  label:'Path Traversal', checked:true },
          { type:'section', label:'WAF-taso' },
          { type:'select',  label:'Suojaustaso', options:['Perus','Korkea','Parannettu (AI)'] },
          { type:'number',  label:'Payload-koon raja (KB)', value:512, min:1, max:10240 },
        ]),
      },
    ],
    footerActions: [
      { label:'Vie YARA-säännöt', style:'secondary', onclick:`() => pmcSec.post('pmc_export_yara',{}).then(r=>r.success&&window.open(r.data?.url,'_blank'))` },
      { label:'Tyhjennä historia', style:'danger', onclick:`() => confirm('Tyhjennä payload-historia?')&&pmcSec.post('pmc_clear_payloads',{}).then(r=>alert(r.success?'Tyhjennetty':'Virhe'))` },
    ],
  },

  /* 9. Malware & File Integrity */
  'w-malware': {
    id: 'w-malware', title: 'Tiedostojen Eheyden Skanneri', icon: 'pest_control', accentColor: '#22c55e',
    tabs: [
      {
        label: 'Muutetut tiedostot',
        ajax: 'pmc_security_hardening_status',
        render: data => [
          WidgetModal.renderCards([
            { label:'Saastunut', value: data?.infected||0, color:'#dc2626' },
            { label:'Muokattu',  value: data?.modified||1, color:'#f59e0b' },
            { label:'Karanteeni',value: data?.quarantined||0, color:'#8b5cf6' },
            { label:'Tarkistettu',value: data?.scanned||'12,405', color:'#22c55e' },
          ]),
          WidgetModal.renderTable(
            [
              { label:'Tiedosto',      render:r=>`<code style="font-size:11px;background:#f1f5f9;padding:1px 5px;border-radius:3px">${r.file||'—'}</code>` },
              { label:'Muokattu',      key:'modified' },
              { label:'Hash-muutos',   render:r=>r.hash_changed?WidgetModal.badge('Muuttunut','critical'):WidgetModal.badge('OK','success') },
              { label:'Tila',         render:r=>WidgetModal.badge(r.status||'Normaali', r.status==='Karanteeni'?'blocked':r.status==='Saastunut'?'critical':'warning') },
              { label:'', render:r=>`<div style="display:flex;gap:4px">
                <button class="wm-btn wm-btn-warning" style="padding:4px 8px;font-size:10px" onclick="pmcSec.post('pmc_quarantine_file',{file:'${r.file}'}).then(r=>alert(r.success?'Karanteenissa':'Virhe'))">Karanteeni</button>
                <button class="wm-btn wm-btn-success" style="padding:4px 8px;font-size:10px" onclick="pmcSec.post('pmc_restore_file',{file:'${r.file}'}).then(r=>alert(r.success?'Palautettu':'Virhe'))">Palauta</button>
              </div>` },
            ],
            data?.modified_files||[
              {file:'wp-settings.php', modified:'2h sitten', hash_changed:true, status:'Muokattu'},
            ]
          )
        ].join(''),
      },
      {
        label: 'Asetukset',
        render: () => WidgetModal.renderSettings([
          { type:'section', label:'Skannausasetukset' },
          { type:'select',  label:'Skannaustiheys', options:['Tunneittain','Päivittäin','Viikoittain'] },
          { type:'toggle',  label:'Tarkista WordPress-ydinfileet', checked:true },
          { type:'toggle',  label:'Tarkista lisäosat', checked:true },
          { type:'toggle',  label:'Tarkista teemat', checked:true },
          { type:'section', label:'Toiminnot' },
          { type:'toggle',  label:'Automaattinen karanteeni saastuneille tiedostoille', checked:false },
          { type:'toggle',  label:'Sähköposti-ilmoitus muutoksista', checked:true },
        ]),
      },
    ],
    footerActions: [
      { label:'Skannaa nyt', style:'success', onclick:`() => pmcSec.post('pmc_run_file_scan',{}).then(r=>alert(r.success?'Skannaus käynnissä!':'Virhe'))` },
      { label:'Tyhjennä karanteeni', style:'secondary', onclick:`() => confirm('Tyhjennä kaikki karanteenissa olevat tiedostot pysyvästi?')&&pmcSec.post('pmc_empty_quarantine',{}).then(r=>alert(r.success?'Tyhjennetty':'Virhe'))` },
    ],
  },

  /* 10. Node Health */
  'w-node-health': {
    id: 'w-node-health', title: 'Solmun Tila', icon: 'hub', accentColor: '#22c55e',
    tabs: [
      {
        label: 'Palvelimet',
        ajax: 'pmc_security_dashboard_data',
        render: data => {
          const nodes = data?.nodes||[
            {name:'Helsinki-Edge-1', latency:14, cpu:22, mem:45, disk:60, status:'online'},
            {name:'Vantaa-DC-01',   latency:48, cpu:61, mem:73, disk:48, status:'warning'},
            {name:'Helsinki-Cluster-B', latency:210, cpu:94, mem:88, disk:91, status:'critical'},
          ];
          return nodes.map(n => {
            const sc = n.status==='critical'?'#ef4444':n.status==='warning'?'#f59e0b':'#22c55e';
            const bar = (v,c) => `<div style="display:flex;align-items:center;gap:8px"><div class="wm-score-bar" style="flex:1;height:8px"><div class="wm-score-fill" style="width:${v}%;background:${c||sc}"></div></div><span style="font-size:11px;font-weight:700;color:${c||sc};min-width:35px">${v}%</span></div>`;
            return `
              <div class="wm-action-row" style="flex-direction:column;align-items:stretch;gap:10px">
                <div style="display:flex;justify-content:space-between;align-items:center">
                  <span style="font-weight:700;color:#1e293b;font-size:14px">${n.name}</span>
                  <div style="display:flex;align-items:center;gap:10px">
                    <span style="font-size:13px;font-weight:800;color:${sc}">${n.latency}ms</span>
                    ${WidgetModal.badge(n.status.toUpperCase(), n.status==='critical'?'critical':n.status==='warning'?'warning':'success')}
                  </div>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px">
                  <div><div style="font-size:10px;font-weight:700;color:#94a3b8;margin-bottom:4px">CPU</div>${bar(n.cpu)}</div>
                  <div><div style="font-size:10px;font-weight:700;color:#94a3b8;margin-bottom:4px">MUISTI</div>${bar(n.mem)}</div>
                  <div><div style="font-size:10px;font-weight:700;color:#94a3b8;margin-bottom:4px">LEVY</div>${bar(n.disk)}</div>
                </div>
                <div style="display:flex;gap:6px">
                  <button class="wm-btn wm-btn-secondary" style="padding:4px 10px;font-size:10px">Käynnistä uudelleen</button>
                  <button class="wm-btn wm-btn-secondary" style="padding:4px 10px;font-size:10px">Tyhjennä välimuisti</button>
                </div>
              </div>`;
          }).join('');
        },
      },
      {
        label: 'Historia',
        render: () => `<canvas id="wm-node-chart" height="140"></canvas>`,
        afterRender: (body) => {
          const ctx = body.querySelector('#wm-node-chart');
          if (!ctx||!window.Chart) return;
          const labels = [...Array(24)].map((_,i)=>`${i}:00`);
          new Chart(ctx, { type:'line', data:{ labels, datasets:[
            { label:'Helsinki-Edge-1 (ms)', data:labels.map(()=>Math.floor(Math.random()*30+10)), borderColor:'#22c55e', tension:.4 },
            { label:'Vantaa-DC-01 (ms)',   data:labels.map(()=>Math.floor(Math.random()*80+30)), borderColor:'#f59e0b', tension:.4 },
            { label:'Cluster-B (ms)',       data:labels.map(()=>Math.floor(Math.random()*250+100)), borderColor:'#ef4444', tension:.4 },
          ]}, options:{ responsive:true, plugins:{legend:{position:'top'}}, scales:{y:{beginAtZero:true}} }});
        },
      },
      {
        label: 'Hälytykset',
        render: () => WidgetModal.renderSettings([
          { type:'section', label:'Kynnysarvot' },
          { type:'number',  label:'Latenssi-hälytys (ms)', value:100, min:10, max:5000 },
          { type:'number',  label:'CPU-hälytys (%)', value:85, min:10, max:100 },
          { type:'number',  label:'Muisti-hälytys (%)', value:90, min:10, max:100 },
          { type:'section', label:'Ilmoitukset' },
          { type:'toggle',  label:'Sähköposti-ilmoitukset', checked:true },
          { type:'toggle',  label:'Webhook-ilmoitukset', checked:false },
        ]),
      },
    ],
    footerActions: [
      { label:'Päivitä kaikki', style:'success', onclick:`() => pmcSec.post('pmc_refresh_nodes',{}).then(r=>alert(r.success?'Tiedot päivitetty':'Virhe'))` },
    ],
  },

};

/* ═══════════════════════════════════════════════════════════════
   INIT — one loop, all 10 modals
═══════════════════════════════════════════════════════════════ */
const _wmInstances = {};
Object.entries(WIDGET_MODALS).forEach(([id, cfg]) => {
  try { _wmInstances[id] = new WidgetModal(cfg); } catch(e) { console.warn('WidgetModal init failed:', id, e); }
});

// Wire all widget-maximize buttons
document.querySelectorAll('.glass-card').forEach(card => {
  const btn = card.querySelector('.widget-maximize');
  if (!btn) return;
  const id = card.id;
  if (_wmInstances[id]) {
    btn.onclick = (e) => { e.stopPropagation(); _wmInstances[id].open(); };
  }
});

// Init on load
pmcSec.init();

}); // end DOMContentLoaded

// ── Global helpers (must be outside DOMContentLoaded so inline onchange attributes can access them) ──
window.pmcSaveActiveModules = async function() {
	const checkboxes = document.querySelectorAll('.module-toggle-checkbox');
	const activeModules = {};
	checkboxes.forEach(cb => {
		activeModules[cb.getAttribute('data-module')] = cb.checked ? 1 : 0;
	});
	try {
		await pmcSec.post('pmc_security_save_active_modules', { modules: activeModules });
	} catch (e) {
		console.error('Aktiivisten moduulien tallennus ep\u00e4onnistui', e);
	}
};

/* ═══════════════════════════════════════
   WAYBACK MACHINE TIMELINE LOGIC
═══════════════════════════════════════ */
document.addEventListener('DOMContentLoaded', () => {
  // Move timeline to React mount point when available
  const moveTimeline = () => {
    const timeline = document.getElementById('ps-timeline-panel');
    const mountPoint = document.getElementById('ps-timeline-mount');
    if (timeline && mountPoint && timeline.parentNode !== mountPoint) {
      mountPoint.appendChild(timeline);
    }
  };
  setInterval(moveTimeline, 500);

  let isTimelineMode = false;
  
  const btnLive = document.getElementById('tl-mode-live');
  const btnHistory = document.getElementById('tl-mode-history');
  const slider = document.getElementById('tl-slider');
  const timeDisplay = document.getElementById('tl-current-time');
  const dateCarousel = document.getElementById('tl-dates');
    const monthList = document.getElementById('tl-month-list');
  
  if (!slider) return;

  // Populate dates carousel (last 14 days)
  if (dateCarousel) {
    const days = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
    const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    let html = '';
    const now = new Date();
    
    for (let i = 0; i < 30; i++) {
      const d = new Date(now.getTime() - (i * 24 * 60 * 60 * 1000));
      const isToday = i === 0;
      const activeAttr = isToday ? 'data-active="true"' : 'data-active="false"';
      html += `
        <button class="tl-date-btn" ${activeAttr} data-offset="${i * 24}">
          <span class="tl-date-day">${days[d.getDay()]}</span>
          <span class="tl-date-val">${months[d.getMonth()]} ${d.getDate()}</span>
        </button>
      `;
    }
    dateCarousel.innerHTML = html;

    // Scroll to end (today)
    setTimeout(() => {
      dateCarousel.scrollLeft = 0;
    }, 100);

    // Date click handling
    dateCarousel.addEventListener('click', (e) => {
        const btn = e.target.closest('.tl-date-btn');
        if (!btn) return;
        
        // Update UI
        dateCarousel.querySelectorAll('.tl-date-btn').forEach(b => b.setAttribute('data-active', 'false'));
        btn.setAttribute('data-active', 'true');
        
        // Clear Range buttons
        const rangeBtns = document.querySelectorAll('.tl-range-btn');
        rangeBtns.forEach(b => {
          b.classList.remove('active');
          b.style.background = '#ffffff';
          b.style.borderColor = '#cbd5e1';
        });
        window.pmcSecurityTimeRange = '';
        
        const dayOffset = parseInt(btn.getAttribute('data-offset'));
        window.pmcSecurityHistoryOffset = dayOffset;
        
        if (!isTimelineMode) {
          btnHistory.click();
        }
        
        if (dayOffset > 72) {
            slider.disabled = true;
            slider.style.opacity = '0.5';
            timeDisplay.textContent = btn.querySelector('.tl-date-val').textContent.toUpperCase();
        } else {
            slider.disabled = false;
            slider.style.opacity = '1';
            const mappedSliderVal = hoursAgoToSlider(dayOffset);
            slider.value = mappedSliderVal;
            updateTimeDisplay(mappedSliderVal);
        }
        
        window.dispatchEvent(new Event('pmcTimeRangeChanged'));
        fetchTimelineData(dayOffset, '');
      });

    // Left / Right Scroll Buttons
    const btnLeft = document.getElementById('tl-nav-left');
    const btnRight = document.getElementById('tl-nav-right');
    if (btnLeft && btnRight) {
      btnLeft.addEventListener('click', () => {
        dateCarousel.scrollBy({ left: -150, behavior: 'smooth' });
      });
      btnRight.addEventListener('click', () => {
        dateCarousel.scrollBy({ left: 150, behavior: 'smooth' });
      });
    }

    // Month Selector Dropdown
    const monthToggle = document.getElementById('tl-month-toggle');
      const monthDropdown = document.getElementById('tl-month-dropdown');

    if (monthToggle && monthDropdown && monthList) {
      // Build past 12 months
      let monthHtml = '';
        for (let i = 0; i < 12; i++) {
          const d = new Date(now.getFullYear(), now.getMonth() - i, 1);
          const mName = months[d.getMonth()];
          const yName = d.getFullYear();
          const offset = i * 30 * 24; 
          const isCurrent = (i === 0);
          monthHtml += `
            <button class="tl-month-option ${isCurrent ? 'active-month' : ''}" data-offset="${offset}" style="background:${isCurrent ? '#f1f5f9' : 'transparent'}; font-weight:${isCurrent ? '700' : '400'}; border:none; text-align:left; padding:6px 12px; font-size:12px; color:#334155; cursor:pointer; border-radius:4px; transition:0.2s;" onmouseover="this.style.background='#f1f5f9'" onmouseout="if(!this.classList.contains('active-month')) this.style.background='transparent'">
              ${mName} ${yName}
            </button>
          `;
        }
        monthList.innerHTML = monthHtml;

      monthToggle.addEventListener('click', (e) => {
        e.stopPropagation();
        monthDropdown.style.display = monthDropdown.style.display === 'none' ? 'block' : 'none';
      });

      monthList.addEventListener('click', (e) => {
          const option = e.target.closest('.tl-month-option');
          if (option) {
            monthDropdown.style.display = 'none';
            
            // Clear Range buttons
            const rangeBtns = document.querySelectorAll('.tl-range-btn');
            rangeBtns.forEach(b => {
              b.classList.remove('active');
              b.style.background = '#ffffff';
              b.style.borderColor = '#cbd5e1';
            });
            window.pmcSecurityTimeRange = '';
            
            // Clear date highlights
            if (dateCarousel) dateCarousel.querySelectorAll('.tl-date-btn').forEach(b => b.setAttribute('data-active', 'false'));
            
            // Highlight month
            monthList.querySelectorAll('.tl-month-option').forEach(b => {
               b.classList.remove('active-month');
               b.style.background = 'transparent';
               b.style.fontWeight = '400';
            });
            option.classList.add('active-month');
            option.style.background = '#f1f5f9';
            option.style.fontWeight = '700';

            if (!isTimelineMode) btnHistory.click();
            
            const offset = parseInt(option.getAttribute('data-offset'));
            window.pmcSecurityHistoryOffset = offset;
            
            slider.disabled = true;
            slider.style.opacity = '0.5';
            timeDisplay.textContent = option.textContent.trim().toUpperCase();
            
            window.dispatchEvent(new Event('pmcTimeRangeChanged'));
            fetchTimelineData(offset, '');
          }
        });

      // Close dropdown when clicking outside
      document.addEventListener('click', (e) => {
        if (!monthToggle.contains(e.target) && !monthDropdown.contains(e.target)) {
          monthDropdown.style.display = 'none';
        }
      });
    }
  }
  
  // ── Left-to-Right Timeline Helpers (0 = 72h ago/Past on Left, 72 = LIVE on Right) ──
  const sliderToHoursAgo = (sliderVal) => 72 - parseInt(sliderVal, 10);
  const hoursAgoToSlider = (hoursAgo) => 72 - parseInt(hoursAgo, 10);

  function updateTimeDisplay(sliderVal) {
    const hoursAgo = sliderToHoursAgo(sliderVal);
    if (hoursAgo <= 0) {
      timeDisplay.textContent = 'LIVE';
    } else {
      const d = new Date(Date.now() - (hoursAgo * 3600000));
      const hh = String(d.getHours()).padStart(2, '0');
      const mm = String(d.getMinutes()).padStart(2, '0');
      timeDisplay.textContent = `${hh}:${mm}`;
    }
  }

  function setMode(mode) {
    isTimelineMode = (mode === 'history');
    if (isTimelineMode) {
      btnHistory.classList.add('active');
      btnLive.classList.remove('active');
      
      // Stop live polling in React App
      window.pmcSecurityHistoryPaused = true;
      
      // Fetch data
      fetchTimelineData(sliderToHoursAgo(slider.value), true);
    } else {
      btnLive.classList.add('active');
      btnHistory.classList.remove('active');
      
      slider.value = 72; // Rightmost = LIVE
      updateTimeDisplay(72);
      
      // Reset dates to today
      if (dateCarousel) {
        const btns = dateCarousel.querySelectorAll('.tl-date-btn');
        btns.forEach(b => b.setAttribute('data-active', 'false'));
        if (btns.length) btns[btns.length - 1].setAttribute('data-active', 'true');
        dateCarousel.scrollLeft = 0;
      }
      
      // Resume live polling in React App
      window.pmcSecurityHistoryPaused = false;
      window.pmcSecurityHistoryOffset = 0;
      if (typeof window.refreshSecurityMap === 'function') {
        window.refreshSecurityMap(false);
      }
    }
  }

  if (btnLive) btnLive.addEventListener('click', () => setMode('live'));
  if (btnHistory) btnHistory.addEventListener('click', () => setMode('history'));

  // Real-time magnetic snapping helper
  function findClosestEvent(sliderVal) {
    if (!window.pmcTimelineEvents || window.pmcTimelineEvents.length === 0) return null;
    const hoursAgo = sliderToHoursAgo(sliderVal);
    return window.pmcTimelineEvents.reduce((prev, curr) => 
      Math.abs(curr.born_hour - hoursAgo) < Math.abs(prev.born_hour - hoursAgo) ? curr : prev
    );
  }

  // ── Playback Engine (Moves smoothly Left to Right) ──
  const playBtn = document.getElementById('tl-play-btn');
  const speedBtns = document.querySelectorAll('.tl-speed-btn');
  let isPlaying = false;
  let playInterval = null;
  let playSpeedMultiplier = 1; // 1x, 60x, 600x

  const getIntervalMs = () => {
    if (playSpeedMultiplier === 600) return 90;
    if (playSpeedMultiplier === 60) return 300;
    return 1000; // 1x default
  };

  const startPlayback = () => {
    if (playInterval) clearInterval(playInterval);
    
    // If currently at rightmost (LIVE/Now), jump back to left (0 = 72h ago) to play forward left-to-right
    if (parseInt(slider.value, 10) >= 72) {
      slider.value = 0;
      updateTimeDisplay(0);
    }
    
    if (!isTimelineMode) {
      setMode('history');
    }

    isPlaying = true;
    if (playBtn) {
      playBtn.innerHTML = '<span class="material-symbols-outlined" style="font-size:16px;">pause</span>';
      playBtn.style.background = '#b80048';
      playBtn.style.color = '#fff';
    }

    playInterval = setInterval(() => {
      let currentVal = parseInt(slider.value, 10);
      currentVal += 1; // Move forward left-to-right towards 72 (LIVE)

      if (currentVal >= 72) {
        slider.value = 72;
        updateTimeDisplay(72);
        stopPlayback();
        setMode('live');
      } else {
        slider.value = currentVal;
        updateTimeDisplay(currentVal);
        const hoursAgo = sliderToHoursAgo(currentVal);
        fetchTimelineData(hoursAgo, true);
        
        // Auto-highlight matching event if present
        const closest = findClosestEvent(currentVal);
        if (closest && Math.abs(closest.born_hour - hoursAgo) <= 1) {
          window.dispatchEvent(new CustomEvent('pmcFilterIp', { detail: { ip: closest.ip } }));
        }
      }
    }, getIntervalMs());
  };

  const stopPlayback = () => {
    isPlaying = false;
    if (playInterval) {
      clearInterval(playInterval);
      playInterval = null;
    }
    if (playBtn) {
      playBtn.innerHTML = '<span class="material-symbols-outlined" style="font-size:16px;">play_arrow</span>';
      playBtn.style.background = '#f1f5f9';
      playBtn.style.color = '#475569';
    }
  };

  const rangeBtns = document.querySelectorAll('.tl-range-btn');
    rangeBtns.forEach(btn => {
      btn.addEventListener('click', () => {
        if (btn.disabled) return;
        
        // 1. Clear Range buttons
        rangeBtns.forEach(b => {
          b.classList.remove('active');
          b.style.background = '#ffffff';
          b.style.borderColor = '#cbd5e1';
        });
        btn.classList.add('active');
        btn.style.background = '#f1f5f9';
        btn.style.borderColor = '#94a3b8';
        
        // 2. Clear Date & Month highlights
        if (dateCarousel) dateCarousel.querySelectorAll('.tl-date-btn').forEach(b => b.setAttribute('data-active', 'false'));
        if (monthList) {
           monthList.querySelectorAll('.tl-month-option').forEach(b => {
             b.classList.remove('active-month');
             b.style.background = 'transparent';
             b.style.fontWeight = '400';
           });
        }
        
        const range = btn.getAttribute('data-range');
        window.pmcSecurityTimeRange = range;
        window.pmcSecurityHistoryOffset = 0; // Clear offset when using range
        
        if (typeof stopPlayback === 'function') stopPlayback();
        if (typeof isTimelineMode !== 'undefined' && !isTimelineMode) {
          if (typeof btnHistory !== 'undefined') btnHistory.classList.add('active');
          if (typeof btnLive !== 'undefined') btnLive.classList.remove('active');
          isTimelineMode = true;
          window.pmcSecurityHistoryPaused = true;
        }
        if (typeof timeDisplay !== 'undefined') {
          let label = 'KOKO PÄIVÄ';
          if (range === '1y') label = 'VUOSI';
          if (range === '6m') label = '6 KK';
          if (range === '3m') label = '3 KK';
          if (range === '2m') label = '2 KK';
          if (range === '1m') label = '1 KK';
          if (range === '2w') label = '2 VK';
          if (range === 'now') label = 'NYT';
          timeDisplay.textContent = label;
        }
        
        // Disable slider visually when using range
        slider.disabled = true;
        slider.style.opacity = '0.5';
        
        window.dispatchEvent(new Event('pmcTimeRangeChanged'));
        window.dispatchEvent(new Event('pmcClearFilters'));
        if (typeof window.refreshSecurityMap === 'function') window.refreshSecurityMap(false);
        if (typeof fetchTimelineData === 'function') fetchTimelineData(0, range);
      });
    });
  const clearFocusBtn = document.getElementById('tl-clear-focus-btn');
  
  if (clearFocusBtn) {
    clearFocusBtn.addEventListener('click', () => {
      // Tyhjennä VAIN IP-suodatin (focus), jotta muut valikot (korkea riski yms) pysyvät
      window.dispatchEvent(new CustomEvent('pmcFilterIp', { detail: { ip: '' } }));
      });
    }

  speedBtns.forEach(btn => {
    btn.addEventListener('click', () => {
      speedBtns.forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      const text = btn.textContent.trim();
      if (text === '600x') playSpeedMultiplier = 600;
      else if (text === '60x') playSpeedMultiplier = 60;
      else playSpeedMultiplier = 1;

      // If currently playing, restart with new speed
      if (isPlaying) {
        startPlayback();
      }
    });
  });

  // Stop playback if user manually touches slider
  slider.addEventListener('mousedown', () => {
    if (isPlaying) stopPlayback();
  });
  slider.addEventListener('touchstart', () => {
    if (isPlaying) stopPlayback();
  });

  slider.addEventListener('input', (e) => {
    let val = parseInt(e.target.value, 10);
    const closest = findClosestEvent(val);
    const hoursAgo = sliderToHoursAgo(val);
    
    // Magneettinen snappi: jos ollaan alle 2.5h etäisyydellä tapahtumasta, hypätään suoraan siihen
    if (closest && Math.abs(closest.born_hour - hoursAgo) <= 2.5) {
      val = hoursAgoToSlider(closest.born_hour);
      slider.value = val;
    }
    
    updateTimeDisplay(val);
  });

  slider.addEventListener('change', (e) => {
    let val = parseInt(e.target.value, 10);
    const closest = findClosestEvent(val);
    const hoursAgo = sliderToHoursAgo(val);
    if (closest && Math.abs(closest.born_hour - hoursAgo) <= 2.5) {
      val = hoursAgoToSlider(closest.born_hour);
      slider.value = val;
    }

    const finalHoursAgo = sliderToHoursAgo(val);
    if (!isTimelineMode && finalHoursAgo > 0) {
      setMode('history');
    } else if (isTimelineMode) {
      fetchTimelineData(finalHoursAgo, true);
    }
  });
  
  function fetchTimelineData(hoursAgo, quiet = true) {
      window.pmcSecurityHistoryOffset = hoursAgo;
      if (typeof window.refreshSecurityMap === 'function') {
        window.refreshSecurityMap(quiet);
      }
    }

  // Draw markers on timeline track (Left = Past, Right = Now)
  window.addEventListener('pmcRadarDataLoading', () => {
      const l = document.getElementById('tl-loader');
      if(l) l.style.display = 'flex';
    });
    window.addEventListener('pmcRadarDataLoaded', (e) => {
      const l = document.getElementById('tl-loader');
      if(l) l.style.display = 'none';
    const data = e.detail;
    if (!data || !data.event_summary) return;

    const markersContainer = document.getElementById('tl-track-markers');
    if (!markersContainer) return;

    markersContainer.innerHTML = '';
    
    // Helper to get Lucide SVG string based on status
    const getIconSvg = (status) => {
      const s = String(status).toLowerCase();
      if (s === 'blocked') return '<svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="m4.9 4.9 14.2 14.2"/></svg>'; // Ban
      if (s === 'killed' || s === 'expired') return '<svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="m15 9-6 6"/><path d="m9 9 6 6"/></svg>'; // XCircle
      if (s === 'critical') return '<svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m12.5 17-.5-1-.5 1h1z"/><path d="M15 22a1 1 0 0 0 1-1v-1a2 2 0 0 0 1.56-3.25 8 8 0 1 0-11.12 0A2 2 0 0 0 8 20v1a1 1 0 0 0 1 1z"/><circle cx="15" cy="12" r="1"/><circle cx="9" cy="12" r="1"/></svg>'; // Skull
      if (s === 'warning') return '<svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><path d="M12 9v4"/><path d="M12 17h.01"/></svg>'; // AlertTriangle
      return '<svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>'; // Activity (active)
    };

    data.event_summary.forEach(ev => {
      const marker = document.createElement('div');
      // Left = 72h ago (0%), Right = Now (100%)
      const pct = ((72 - ev.born_hour) / 72) * 100;
      
      let color = '#2563eb'; // active (blue)
      if (ev.status === 'blocked') color = '#dc2626'; // red
      if (ev.status === 'killed') color = '#64748b'; // gray
      if (ev.status === 'warning') color = '#d97706'; // amber/yellow
      if (ev.status === 'critical') color = '#dc2626';

      // Pin-asettelu: ikoni kelluu viivan yläpuolella ja vertikaaliviiva laskeutuu kiskolle
      marker.style.position = 'absolute';
      marker.style.left = `calc(${pct}% - 10px)`;
      marker.style.bottom = '2px';
      marker.style.display = 'flex';
      marker.style.flexDirection = 'column';
      marker.style.alignItems = 'center';
      marker.style.zIndex = '5';
      marker.style.cursor = 'pointer';
      marker.style.pointerEvents = 'auto';
      marker.title = `${ev.attack} (${ev.country}) - Luotu ${ev.born_hour}h sitten [Klikkaa siirtyäksesi aikaan]`;

      marker.innerHTML = `
        <div style="background-color: ${color}; border: 2px solid #ffffff; width: 20px; height: 20px; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #ffffff; box-shadow: 0 2px 5px rgba(0,0,0,0.25); transition: transform 0.15s ease;" onmouseover="this.style.transform='scale(1.2)'" onmouseout="this.style.transform='scale(1)'">
          ${getIconSvg(ev.status)}
        </div>
        <div style="width: 2px; height: 8px; background-color: ${color}; opacity: 0.85;"></div>
      `;
      
      marker.addEventListener('click', (e) => {
        e.stopPropagation();
        slider.value = hoursAgoToSlider(ev.born_hour);
        slider.dispatchEvent(new Event('input'));
        slider.dispatchEvent(new Event('change'));
        
        // Suodata taulukko ja kartta kyseiselle yhteydelle kun ikonia klikataan
        window.dispatchEvent(new CustomEvent('pmcFilterIp', { detail: { ip: ev.ip } }));
      });
      
      markersContainer.appendChild(marker);
    });
    
    // Tallenna eventit globaalisti snäppäystä varten
    window.pmcTimelineEvents = data.event_summary;
  });


  // --- WP Security News Widget ---
  async function fetchSecurityNews() {
    const container = document.getElementById('news-feed-container');
    const refreshBtn = document.getElementById('refresh-news-btn');
    if (!container) return;

    if (refreshBtn) refreshBtn.style.opacity = '0.5';
    container.innerHTML = '<div style="text-align:center; padding:20px; color:#94a3b8; font-size:11px;">Haetaan tuoreimpia uutisia...</div>';

    try {
      const formData = new FormData();
      formData.append('action', 'pmc_security_get_news');
      if (typeof pmcSecurityConfig !== 'undefined' && pmcSecurityConfig.nonce) {
        formData.append('nonce', pmcSecurityConfig.nonce);
      }

      const response = await fetch(ajaxurl, {
        method: 'POST',
        body: formData
      });
      const res = await response.json();

      if (refreshBtn) refreshBtn.style.opacity = '1';

      if (res.success && res.data && res.data.length > 0) {
        let html = '';
        res.data.forEach(item => {
          let sourceTag = item.source ? `<span style="font-size:9px; background:#e2e8f0; color:#475569; padding:2px 6px; border-radius:4px; font-weight:700; text-transform:uppercase;">${item.source}</span>` : '';
          
          html += `
            <div style="background:#fff; border:1px solid #e2e8f0; border-radius:8px; padding:10px; display:flex; flex-direction:column; gap:4px; transition:0.2s; box-shadow:0 1px 2px rgba(0,0,0,0.05);">
              <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:8px;">
                <a href="${item.link}" target="_blank" style="font-size:11px; font-weight:700; color:#b80048; text-decoration:none; line-height:1.3; flex:1;">${item.title}</a>
                ${sourceTag}
              </div>
              <div style="font-size:10px; color:#64748b; font-weight:500;">${item.date}</div>
              <div style="font-size:11px; color:#475569; line-height:1.4;">${item.desc}</div>
            </div>
          `;
        });
        container.innerHTML = html;
      } else {
        container.innerHTML = '<div style="text-align:center; padding:20px; color:#94a3b8; font-size:11px;">Ei uutisia saatavilla juuri nyt.</div>';
      }
    } catch (err) {
      if (refreshBtn) refreshBtn.style.opacity = '1';
      console.error('Uutisten haku epäonnistui:', err);
      container.innerHTML = '<div style="text-align:center; padding:20px; color:#dc2626; font-size:11px;">Virhe haettaessa uutisia.</div>';
    }
  }

  // Init news and button
  fetchSecurityNews();
  const refreshNewsBtn = document.getElementById('refresh-news-btn');
  if (refreshNewsBtn) {
    refreshNewsBtn.addEventListener('click', fetchSecurityNews);
  }

});
</script>





