import React, { useEffect, useMemo, useRef, useState } from 'react';
import {
  Activity, AlertTriangle, Ban, CheckCircle, ChevronLeft, ChevronRight,
  ChevronsLeft, ChevronsRight, Eye, Globe, RefreshCw, Search, Server,
  Shield, ShieldAlert, ShieldCheck, UserCheck, X, Info,
} from 'lucide-react';

const emptyRadar = {
  events: [], connections: [], logs: [], server: null,
  stats: { total_connections: 0, normal_connections: 0, suspicious_connections: 0, blocked_connections: 0, request_rate: 0 },
};

const isCoordinate = (value) => Number.isFinite(Number(value));
const escapeHtml = (value) => String(value ?? '').replace(/[&<>'"]/g, (char) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;' }[char]));

const statusMeta = (connection) => {
  const status = String(connection.statusClass || connection.status || '').toLowerCase();
  if (status === 'critical' || status === 'blocked') return { label: 'Estetty', color: '#dc2626', chip: 'bg-red-50 text-red-700 border-red-200', icon: ShieldAlert };
  if (status === 'warning' || status === 'suspicious') return { label: 'Epäilyttävä', color: '#d97706', chip: 'bg-amber-50 text-amber-700 border-amber-200', icon: AlertTriangle };
  return { label: 'Normaali', color: '#059669', chip: 'bg-emerald-50 text-emerald-700 border-emerald-200', icon: CheckCircle };
};

const request = async (action, values = {}) => {
  const config = window.pmcSecurityConfig || { ajaxUrl: window.ajaxurl, nonce: '' };
  const form = new FormData();
  form.append('action', action);
  if (config.nonce) form.append('nonce', config.nonce);
  Object.entries(values).forEach(([key, value]) => form.append(key, value));
  const response = await fetch(config.ajaxUrl || window.ajaxurl || '/wp-admin/admin-ajax.php', { method: 'POST', credentials: 'same-origin', body: form });
  const payload = await response.json();
  if (!payload.success) throw new Error(typeof payload.data === 'string' ? payload.data : 'Toiminto epäonnistui.');
  return payload.data;
};

/* ─── Animated counter ───────────────────────────────────────── */
function AnimatedNumber({ value }) {
  const [display, setDisplay] = useState(value);
  const prev = useRef(value);
  useEffect(() => {
    if (prev.current === value) return;
    const start = prev.current;
    const end = value;
    const duration = 600;
    const startTime = performance.now();
    const tick = (now) => {
      const elapsed = Math.min((now - startTime) / duration, 1);
      const eased = 1 - Math.pow(1 - elapsed, 3);
      setDisplay(Math.round(start + (end - start) * eased));
      if (elapsed < 1) requestAnimationFrame(tick);
      else { setDisplay(end); prev.current = end; }
    };
    requestAnimationFrame(tick);
  }, [value]);
  return <span>{display}</span>;
}

/* ─── Risk bar ───────────────────────────────────────────────── */
function RiskBar({ score }) {
  const s = Number(score || 0);
  const color = s >= 60 ? '#dc2626' : s >= 30 ? '#d97706' : '#059669';
  return (
    <div className="flex items-center gap-2">
      <span style={{ color, minWidth: 38 }} className="font-mono text-sm font-bold">{s}/100</span>
      <div className="h-1.5 w-16 rounded-full bg-slate-100 overflow-hidden">
        <div style={{ width: `${s}%`, background: color, transition: 'width 0.4s ease' }} className="h-full rounded-full" />
      </div>
    </div>
  );
}

/* ─── Radar Map ──────────────────────────────────────────────── */
function RadarMap({ events, server, hoveredId, onHover, onSelect, loading }) {
  const mapElement = useRef(null);
  const map = useRef(null);
  const layer = useRef(null);
  const clusterLayer = useRef(null);
  const [ready, setReady] = useState(false);
  const [mapError, setMapError] = useState('');

  useEffect(() => {
    if (!window.L || !mapElement.current) {
      setMapError('Karttakirjasto ei latautunut. Yhteydet näkyvät silti alla olevassa taulukossa.');
      return undefined;
    }
    map.current = window.L.map(mapElement.current, { zoomControl: false }).setView([24, 10], 2);
    window.L.control.zoom({ position: 'bottomright' }).addTo(map.current);

    // ── KORJAUS: Vaihdettu dark_all → rastertiles/voyager (värikäs kartta) ──
    window.L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png', {
      attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> &copy; <a href="https://carto.com/attributions">CARTO</a>',
      subdomains: 'abcd',
      maxZoom: 19,
    }).addTo(map.current);

    layer.current = window.L.layerGroup().addTo(map.current);
    if (window.L.markerClusterGroup) {
      clusterLayer.current = window.L.markerClusterGroup({
        showCoverageOnHover: false,
        maxClusterRadius: 40,
        spiderfyOnMaxZoom: true,
        iconCreateFunction: function(cluster) {
          const count = cluster.getChildCount();
          return window.L.divIcon({
            html: `<div style="background-color: #b80048; border: 2px solid white; border-radius: 50%; width: 36px; height: 36px; display: flex; align-items: center; justify-content: center; color: white; font-weight: bold; font-size: 14px; box-shadow: 0 2px 6px rgba(0,0,0,0.35);">${count}</div>`,
            className: 'pecodex-cluster-icon',
            iconSize: [36, 36],
            iconAnchor: [18, 18]
          });
        }
      }).addTo(map.current);
    }
    setReady(true);
    return () => { map.current?.remove(); map.current = null; layer.current = null; clusterLayer.current = null; };
  }, []);

  useEffect(() => {
    if (!ready || !map.current || !layer.current || !window.L) return;
    const L = window.L;
    layer.current.clearLayers();
    if (clusterLayer.current) clusterLayer.current.clearLayers();
    const hasServer = server && isCoordinate(server.lat) && isCoordinate(server.lng);
    const serverPoint = hasServer ? [Number(server.lat), Number(server.lng)] : null;

    if (serverPoint) {
      // Palvelinpiste – sininen korostus
      L.circleMarker(serverPoint, {
        radius: 10, color: '#fff', weight: 3,
        fillColor: '#2563eb', fillOpacity: 1,
      }).bindTooltip(
        `<div class="pecodex-map-tooltip-inner"><strong>🛡 Suojattu palvelin</strong>${server.city ? `<br>${escapeHtml(server.city)}` : ''}</div>`,
        { direction: 'top', className: 'pecodex-map-tooltip' }
      ).addTo(layer.current);
    }

    // Helper to get Lucide SVG string based on status
    const getIconSvg = (status) => {
      const s = String(status).toLowerCase();
      if (s === 'blocked') return '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="m4.9 4.9 14.2 14.2"/></svg>'; // Ban
      if (s === 'killed' || s === 'expired') return '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="m15 9-6 6"/><path d="m9 9 6 6"/></svg>'; // XCircle
      if (s === 'critical') return '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m12.5 17-.5-1-.5 1h1z"/><path d="M15 22a1 1 0 0 0 1-1v-1a2 2 0 0 0 1.56-3.25 8 8 0 1 0-11.12 0A2 2 0 0 0 8 20v1a1 1 0 0 0 1 1z"/><circle cx="15" cy="12" r="1"/><circle cx="9" cy="12" r="1"/></svg>'; // Skull
      if (s === 'warning') return '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><path d="M12 9v4"/><path d="M12 17h.01"/></svg>'; // AlertTriangle
      return '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>'; // Activity (active)
    };

    const activeFlightMarkers = [];

    // Exact screen/map heading from point A to point B (0 deg = North, 90 deg = East)
    const getScreenHeading = (from, to) => {
      const dLat = to[0] - from[0];
      const dLng = to[1] - from[1];
      const angleRad = Math.atan2(dLat, dLng);
      return (90 - (angleRad * 180 / Math.PI) + 360) % 360;
    };

    events.forEach((event, idx) => {
      if (!isCoordinate(event.lat) || !isCoordinate(event.lng)) return;
      const point = [Number(event.lat), Number(event.lng)];
      const meta = statusMeta(event);
      const active = hoveredId === event.id;
      
      const s = (event.statusClass || event.status || 'active').toLowerCase();
      let animClass = 'radar-line-active';
      if (s === 'blocked') animClass = 'radar-line-blocked';
      if (s === 'killed' || s === 'expired') animClass = 'radar-line-killed';

      if (serverPoint) {
        // Draw the connection flight line
        const line = L.polyline([point, serverPoint], {
          color: meta.color,
          weight: active ? 3 : 1.5,
          opacity: active ? 0.95 : 0.55,
          className: animClass,
        }).addTo(layer.current);
        line.on('mouseover', () => onHover(event.id));
        line.on('mouseout', () => onHover(null));
        line.on('click', () => onSelect(event.ip));

        // If connection is active, add a moving Flight Head / Airplane marker
        if (s !== 'killed' && s !== 'expired') {
          const heading = getScreenHeading(point, serverPoint);
          // Standard FlightRadar Airplane (nose points 0° North, centered at 12,12)
          const planeSvg = `
            <div class="flight-plane-wrap" style="transform: rotate(${heading}deg); transform-origin: 50% 50%; width: 28px; height: 28px; display: flex; align-items: center; justify-content: center; filter: drop-shadow(0 2px 5px rgba(0,0,0,0.6)); pointer-events: none;">
              <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="${meta.color}" stroke="#ffffff" stroke-width="1.8" stroke-linejoin="round" style="filter: drop-shadow(0 0 3px ${meta.color});">
                <path d="M21 16v-2l-8-5V3.5c0-.83-.67-1.5-1.5-1.5S10 2.67 10 3.5V9l-8 5v2l8-2.5V19l-2 1.5V22l3.5-1 3.5 1v-1.5L13 19v-5.5l8 2.5z"/>
              </svg>
            </div>
          `;
          const flightMarker = L.marker(point, {
            icon: L.divIcon({
              html: planeSvg,
              className: 'flight-plane-marker',
              iconSize: [28, 28],
              iconAnchor: [14, 14] // Exact center
            }),
            interactive: false
          }).addTo(layer.current);

          activeFlightMarkers.push({
            marker: flightMarker,
            from: point,
            to: serverPoint,
            phase: (idx * 0.19) % 1.0, // Staggered flight departure times
            speed: 3500 // 3.5 seconds per flight across the world
          });
        }
      }

      const popup = `<div class="pecodex-map-popup"><strong>${escapeHtml(event.ip)}</strong><span class="pecodex-map-popup-meta">${escapeHtml(meta.label)} · ${escapeHtml(event.country || 'Tuntematon')}</span><span class="pecodex-map-popup-path">${escapeHtml(event.endpoint || event.attack || '')}</span></div>`;
      
      // Use L.divIcon with HTML content
      const isLost = s === 'killed' || s === 'expired' || s === 'blocked';
      const iconHtml = `
        <div style="position: relative; display: flex; align-items: center; justify-content: center;">
          ${isLost ? '<div class="radar-lost-ring"></div>' : ''}
          <div style="background-color: ${meta.color}; border: 2px solid white; border-radius: 50%; width: ${active ? '28px' : '24px'}; height: ${active ? '28px' : '24px'}; display: flex; align-items: center; justify-content: center; color: white; opacity: ${s === 'killed' ? 0.6 : 1}; box-shadow: 0 2px 6px rgba(0,0,0,0.35); transition: all 0.2s;">
            ${getIconSvg(s)}
          </div>
        </div>
      `;
      
      const marker = L.marker(point, {
        icon: L.divIcon({
          html: iconHtml,
          className: 'pecodex-marker-icon', // Empty class to remove default leaflet styles
          iconSize: [24, 24],
          iconAnchor: [12, 12]
        })
      }).bindTooltip(popup, { direction: 'top', className: 'pecodex-map-tooltip' });
      
      if (clusterLayer.current) {
        clusterLayer.current.addLayer(marker);
      } else {
        marker.addTo(layer.current);
      }
      
      marker.on('mouseover', () => onHover(event.id));
      marker.on('mouseout', () => onHover(null));
      marker.on('click', () => onSelect(event.ip));
    });

    // ── 60 FPS Silky Smooth Flight Radar Animation Loop (Smooth Fade In / Out & Dynamic Zoom Scaling) ──
    let animFrameId = null;
    if (activeFlightMarkers.length > 0) {
      const animateFlights = () => {
        const now = Date.now();
        const currentZoom = map.current ? map.current.getZoom() : 2;
        // Zoom scale multiplier: base zoom is 2. Zoom range 1 to 10 scales size gracefully from 0.75x to 1.35x
        const zoomScale = Math.min(1.4, Math.max(0.7, 0.7 + (currentZoom - 2) * 0.1));

        activeFlightMarkers.forEach((flight) => {
          const t = ((now / flight.speed) + flight.phase) % 1.0;
          const lat = flight.from[0] + (flight.to[0] - flight.from[0]) * t;
          const lng = flight.from[1] + (flight.to[1] - flight.from[1]) * t;
          flight.marker.setLatLng([lat, lng]);

          // Smooth Fade In on Takeoff (0.0 -> 0.15) & Smooth Fade Out on Landing (0.85 -> 1.0)
          let opacity = 1.0;
          if (t < 0.15) {
            opacity = t / 0.15; // Smooth takeoff fade in
          } else if (t > 0.85) {
            opacity = (1.0 - t) / 0.15; // Smooth landing fade out
          }
          flight.marker.setOpacity(Math.max(0, Math.min(1, opacity)));

          // Apply smooth zoom scaling to wrapper element
          const el = flight.marker.getElement();
          if (el) {
            const wrap = el.querySelector('.flight-plane-wrap');
            if (wrap) {
              wrap.style.scale = zoomScale;
            }
          }
        });
        animFrameId = requestAnimationFrame(animateFlights);
      };
      animFrameId = requestAnimationFrame(animateFlights);
    }

    return () => {
      if (animFrameId) cancelAnimationFrame(animFrameId);
    };
  }, [events, hoveredId, onHover, onSelect, ready, server, loading]);

  return (
    <section className="relative overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-md" style={{ boxShadow: '0 4px 24px 0 rgba(0,0,0,0.07)' }}>
      {loading && (
        <div className="absolute inset-0 z-[2000] bg-white/40 backdrop-blur-[1px] flex items-center justify-center">
          <div className="w-8 h-8 border-4 border-indigo-600 border-t-transparent rounded-full animate-spin"></div>
        </div>
      )}
      {loading && (
        <div className="absolute inset-0 z-[2000] bg-white/40 backdrop-blur-[1px] flex items-center justify-center">
          <div className="w-8 h-8 border-4 border-indigo-600 border-t-transparent rounded-full animate-spin"></div>
        </div>
      )}
      {/* Otsikkopaneeli */}
      <div className="absolute left-3 top-3 z-[500] rounded-xl border border-white/80 bg-white/90 px-3 py-2 text-xs backdrop-blur-sm shadow-sm">
        <div className="flex items-center gap-1.5 font-semibold text-slate-800">
          <Globe className="h-3.5 w-3.5 text-[#b80048]" />
            Yhteydet
          </div>
        <p className="mt-0.5 text-slate-500">{events.length} paikannettua tapahtumaa</p>
      </div>

      {/* Karttaelementti */}
      <div ref={mapElement} style={{ height: 420 }} className="w-full" />

      {mapError && (
        <p className="absolute inset-x-4 bottom-4 z-[500] rounded-lg bg-amber-50 border border-amber-200 p-3 text-sm text-amber-800 shadow-sm">
          {mapError}
        </p>
      )}
      {!server && !mapError && (
        <p className="absolute bottom-3 left-3 z-[500] rounded-lg bg-white/90 border border-slate-200 px-3 py-2 text-xs text-slate-500 shadow-sm backdrop-blur-sm">
          Palvelinsijaintia ei vielä ole saatavilla — tapahtumat näkyvät ilman yhteysviivoja.
        </p>
      )}
    </section>
  );
}

const radarAnimationStyles = `
/* Animoitu lentotutkaviiva (Data virtaa kohti palvelinta) */
.radar-line-active {
  stroke-dasharray: 8, 12;
  animation: radar-flow 1.5s linear infinite;
}
/* Estetty viiva jää paikoilleen katkoviivana ja punertavana */
.radar-line-blocked {
  stroke-dasharray: 4, 6;
  opacity: 0.85 !important;
}
/* Tapettu/vanhentunut viiva (harmaa) katkeaa */
.radar-line-killed {
  stroke-dasharray: 2, 8;
  opacity: 0.3 !important;
}

@keyframes radar-flow {
  0% {
    stroke-dashoffset: 20;
  }
  100% {
    stroke-dashoffset: 0;
  }
}

/* Yhteyden katkeamisen / eston pulssi (Connection Lost / Blocked) */
@keyframes pulse-lost {
  0% { transform: scale(1); opacity: 0.9; }
  50% { transform: scale(1.8); opacity: 0.4; }
  100% { transform: scale(2.6); opacity: 0; }
}

.radar-lost-ring {
  position: absolute;
  top: -4px;
  left: -4px;
  right: -4px;
  bottom: -4px;
  border-radius: 50%;
  border: 2px solid #ef4444;
  animation: pulse-lost 1.8s ease-out infinite;
  pointer-events: none;
}

.flight-plane-marker {
  background: transparent !important;
  border: none !important;
  overflow: visible !important;
}
`;

// Injektoidaan tyylit dokumenttiin (jos ei jo ole)
if (typeof document !== 'undefined') {
  if (!document.getElementById('radar-anim-styles')) {
    const styleEl = document.createElement('style');
    styleEl.id = 'radar-anim-styles';
    styleEl.innerHTML = radarAnimationStyles;
    document.head.appendChild(styleEl);
  }
}

/* ─── Stat Card ──────────────────────────────────────────────── */
function StatCard({ label, value, icon: Icon, tone, toneColor, onClick, active }) {
  return (
    <button
      onClick={onClick}
      className={`group relative overflow-hidden rounded-2xl border bg-white p-5 text-left transition-all duration-200
        hover:-translate-y-1 hover:shadow-lg
        ${active ? 'border-[#b80048] ring-2 ring-[#b80048]/20 shadow-md' : 'border-slate-200 shadow-sm'}`}
    >
      {/* Hienovarainen gradient */}
      <div className={`absolute inset-0 opacity-0 group-hover:opacity-100 transition-opacity duration-300 ${tone.replace('text-', 'from-').split(' ')[0].replace('from-', 'bg-gradient-to-br from-')}`}
        style={{ background: `linear-gradient(135deg, ${toneColor}08 0%, transparent 60%)` }} />

      <div className="relative flex items-start justify-between gap-3">
        <div>
          <p className="text-xs font-semibold uppercase tracking-wide text-slate-400">{label}</p>
          <p className="mt-2 text-3xl font-bold text-slate-900 tabular-nums">
            <AnimatedNumber value={value} />
          </p>
        </div>
        <span className={`rounded-xl p-2.5 ${tone} transition-transform duration-200 group-hover:scale-110`}>
          <Icon className="h-5 w-5" />
        </span>
      </div>

      {active && (
        <div className="mt-3 h-0.5 w-full rounded-full bg-gradient-to-r from-[#b80048] to-[#ff6b9d] opacity-80" />
      )}
    </button>
  );
}

/* ─── Filter Bar ─────────────────────────────────────────────── */
function FilterBar({ filters, setFilters, total, page, pageSize, onPageChange, onPageSizeChange }) {
  const update = (key, value) => setFilters((current) => ({ ...current, [key]: value }));
  const reset = () => setFilters({ status: 'all', source: 'all', score: 'all', query: '' });
  const active = filters.status !== 'all' || filters.source !== 'all' || filters.score !== 'all' || filters.query;
  const totalPages = Math.max(1, Math.ceil(total / pageSize));

  const selectClass = "rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 shadow-sm focus:outline-none focus:ring-2 focus:ring-[#b80048]/30 focus:border-[#b80048] transition-colors";

  return (
    <div className="flex flex-col gap-3 border-b border-slate-100 bg-slate-50/70 p-4">
      <div className="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
        <div>
          <h2 className="flex items-center gap-2 text-base font-bold text-slate-900">
            <Activity className="h-4.5 w-4.5 text-[#b80048]" />
            Liikennetapahtumat
            <span className="rounded-full bg-slate-200 px-2 py-0.5 text-xs font-semibold text-slate-600">{total}</span>
          </h2>
          <p className="mt-0.5 text-xs text-slate-500">Normaalit pyynnöt, epäilyttävä liikenne ja estot samassa näkymässä.</p>
        </div>

        {/* Quick Top Pagination */}
        <div className="flex flex-wrap items-center gap-2">
          <div className="flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 shadow-sm">
            <span className="text-xs text-slate-500 font-medium">Sivukoko:</span>
            <select
              value={pageSize}
              onChange={(e) => onPageSizeChange(Number(e.target.value))}
              className="text-xs font-bold text-slate-800 bg-transparent focus:outline-none cursor-pointer"
            >
              <option value={15}>15 kpl</option>
              <option value={30}>30 kpl</option>
              <option value={50}>50 kpl</option>
              <option value={100}>100 kpl</option>
              <option value={250}>250 kpl</option>
            </select>
          </div>

          <div className="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white p-1 shadow-sm text-xs">
            <button
              onClick={() => onPageChange(page - 1)}
              disabled={page <= 1}
              className="rounded px-2 py-1 font-semibold text-slate-600 hover:bg-slate-100 disabled:opacity-30 disabled:hover:bg-transparent transition-colors cursor-pointer disabled:cursor-not-allowed"
            >
              ← Edellinen
            </button>
            <span className="px-2 font-bold text-slate-900">
              Sivu {page} / {totalPages}
            </span>
            <button
              onClick={() => onPageChange(page + 1)}
              disabled={page >= totalPages}
              className="rounded px-2 py-1 font-semibold text-slate-600 hover:bg-slate-100 disabled:opacity-30 disabled:hover:bg-transparent transition-colors cursor-pointer disabled:cursor-not-allowed"
            >
              Seuraava →
            </button>
          </div>
        </div>
      </div>

      {/* Filters Row */}
      <div className="grid gap-2 sm:grid-cols-2 lg:flex lg:items-center">
        <select value={filters.status} onChange={(e) => update('status', e.target.value)} className={selectClass} aria-label="Tila">
          <option value="all">Kaikki tilat</option>
          <option value="normal">Normaalit</option>
          <option value="suspicious">Epäilyttävät</option>
          <option value="blocked">Estetyt</option>
        </select>
        <select value={filters.source} onChange={(e) => update('source', e.target.value)} className={selectClass} aria-label="Lähde">
          <option value="all">Kaikki lähteet</option>
          <option value="traffic">HTTP-liikenne</option>
          <option value="lockout">Turvatapahtumat</option>
        </select>
        <select value={filters.score} onChange={(e) => update('score', e.target.value)} className={selectClass} aria-label="Riskitaso">
          <option value="all">Kaikki riskit</option>
          <option value="high">Korkea riski (60+)</option>
          <option value="low">Alle 60</option>
        </select>
        <label className="relative block">
          <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
          <input
            value={filters.query}
            onChange={(e) => update('query', e.target.value)}
            className={`w-full py-2 pl-9 pr-3 lg:w-56 ${selectClass}`}
            placeholder="Hae IP, maa tai polku"
          />
        </label>
        {active && (
          <button onClick={reset} className="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-600 shadow-sm hover:bg-slate-50 hover:text-slate-900 transition-colors cursor-pointer">
            Tyhjennä
          </button>
        )}
      </div>
    </div>
  );
}

/* ──────────────── IpDetailsModal ──────────────── */
function IpDetailsModal({ isOpen, onClose, connectionDetails }) {
  const [loading, setLoading] = useState(true);
    const [clientLoading, setClientLoading] = useState(false);
  const [data, setData] = useState(null);

  useEffect(() => {
    if (!isOpen || !connectionDetails?.ip) return;
    setLoading(true);
    
    const formData = new FormData();
    formData.append('action', 'pmc_get_ip_details');
    formData.append('nonce', window.pmcSecurityConfig?.nonce || '');
    formData.append('ip', connectionDetails.ip);
    
    fetch(window.pmcSecurityConfig?.ajaxUrl, {
      method: 'POST',
      body: formData
    })
    .then(res => res.json())
    .then(res => {
      if (res.success) {
        setData(res.data);
      }
      setLoading(false);
    })
    .catch(() => setLoading(false));
  }, [isOpen, connectionDetails]);

  if (!isOpen || !connectionDetails) return null;

  return (
    <div className="fixed inset-0 z-[9999] flex items-center justify-center bg-slate-900/40 backdrop-blur-sm p-4 animate-in fade-in duration-200">
      <div className="bg-white rounded-xl shadow-2xl w-full max-w-4xl max-h-[90vh] flex flex-col overflow-hidden animate-in zoom-in-95 duration-200" onClick={(e) => e.stopPropagation()}>
        
        {/* Header */}
        <div className="flex items-center justify-between p-6 border-b border-slate-100 bg-white">
          <div className="flex items-center gap-4">
            <div className="h-12 w-12 rounded-xl bg-slate-50 flex items-center justify-center text-xl shadow-inner font-semibold text-slate-700 border border-slate-200">
              {connectionDetails.countryCode && connectionDetails.countryCode !== 'XX' ? (
                <img src={`https://flagcdn.com/24x18/${connectionDetails.countryCode.toLowerCase()}.png`} alt={connectionDetails.countryName} className="rounded-sm" />
              ) : <Globe className="w-6 h-6 text-slate-400" />}
            </div>
            <div>
              <h2 className="text-xl font-bold text-slate-800 tracking-tight">{connectionDetails.ip}</h2>
              <div className="text-sm text-slate-500 mt-0.5 flex items-center gap-2">
                <span>{connectionDetails.countryName || 'Tuntematon sijainti'}</span>
                {data && data.is_banned && (
                  <span className="px-2 py-0.5 bg-rose-100 text-rose-700 text-[11px] font-semibold rounded-full flex items-center gap-1 uppercase tracking-wider">
                    <ShieldAlert className="w-3 h-3" /> Estetty
                  </span>
                )}
              </div>
            </div>
          </div>
          <button onClick={onClose} className="p-2 text-slate-400 hover:text-slate-600 hover:bg-slate-100 rounded-lg transition-colors">
            <X className="w-5 h-5" />
          </button>
        </div>

        {/* Content */}
        <div className="flex-1 overflow-auto p-6 bg-slate-50">
          {loading ? (
            <div className="flex flex-col items-center justify-center h-48 space-y-4">
              <div className="w-8 h-8 border-2 border-indigo-600 border-t-transparent rounded-full animate-spin"></div>
              <div className="text-sm text-slate-500 font-medium animate-pulse">Ladataan historiaa...</div>
            </div>
          ) : data && data.history && data.history.length > 0 ? (
            <div className="space-y-4">
              <div className="flex items-center justify-between">
                <h3 className="text-sm font-semibold text-slate-700 uppercase tracking-wider flex items-center gap-2">
                  <Activity className="w-4 h-4 text-indigo-500" /> Tapahtumahistoria
                </h3>
                <div className="text-xs font-semibold text-slate-500 bg-white px-3 py-1.5 rounded-full border border-slate-200 shadow-sm">
                  Yhteensä {data.total_requests} tapahtumaa
                </div>
              </div>
              
              <div className="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
                <table className="w-full text-left text-sm">
                  <thead className="bg-slate-50 border-b border-slate-100 text-xs uppercase tracking-wider text-slate-500">
                    <tr>
                      <th className="px-5 py-3 font-semibold">Aika</th>
                      <th className="px-5 py-3 font-semibold">Tila</th>
                      <th className="px-5 py-3 font-semibold">Tapahtuma</th>
                      <th className="px-5 py-3 font-semibold">Pyyntö (URL)</th>
                      <th className="px-5 py-3 font-semibold text-right">Riski</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-slate-50">
                    {data.history.map((item, idx) => (
                      <tr key={idx} className="hover:bg-slate-50/80 transition-colors group">
                        <td className="px-5 py-3 text-slate-500 whitespace-nowrap">
                          {item.time.split(' ')[1]}
                        </td>
                        <td className="px-5 py-3">
                          <span className={`inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[11px] font-semibold border ${
                            item.status === 'critical' ? 'bg-rose-50 text-rose-700 border-rose-200' :
                            item.status === 'warning' ? 'bg-amber-50 text-amber-700 border-amber-200' :
                            'bg-emerald-50 text-emerald-700 border-emerald-200'
                          }`}>
                            <div className={`w-1.5 h-1.5 rounded-full ${
                              item.status === 'critical' ? 'bg-rose-500' :
                              item.status === 'warning' ? 'bg-amber-500' :
                              'bg-emerald-500'
                            }`} />
                            {item.status === 'critical' ? 'Estetty' : item.status === 'warning' ? 'Epäilyttävä' : 'Normaali'}
                          </span>
                        </td>
                        <td className="px-5 py-3 text-slate-700 font-medium">{item.attack}</td>
                        <td className="px-5 py-3 text-slate-500 font-mono text-xs group-hover:text-indigo-600 transition-colors">{item.endpoint}</td>
                        <td className="px-5 py-3 text-right">
                          <span className={`font-semibold ${
                            item.threat_score >= 70 ? 'text-rose-600' :
                            item.threat_score >= 40 ? 'text-amber-600' :
                            'text-emerald-600'
                          }`}>
                            {item.threat_score}/100
                          </span>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </div>
          ) : (
            <div className="flex flex-col items-center justify-center h-48 text-slate-500 bg-white border border-slate-200 border-dashed rounded-xl">
              <Info className="w-8 h-8 mb-3 text-slate-400" />
              <p>Historiaa ei löytynyt tälle IP-osoitteelle.</p>
            </div>
          )}
        </div>
      </div>
    </div>
  );
}

/* ─── Connection Table ───────────────────────────────────────── */
function ConnectionTable({ connections, currentIp, adminIps, hoveredId, onHover, onSelect, onAction, onViewDetails, loading }) {
  const ownIp = (currentIp || window.pmcSecurityConfig?.currentIp || '').trim();
  const knownAdmins = adminIps || window.pmcSecurityConfig?.adminIps || {};
  const isLocal = (ip) => ['127.0.0.1', '::1', 'localhost'].includes(ip);

  return (
    <div className="overflow-x-auto relative min-h-[300px]">
      {loading && (
        <div className="absolute inset-0 z-[20] bg-white/40 backdrop-blur-[1px] flex items-center justify-center">
          <div className="w-8 h-8 border-4 border-indigo-600 border-t-transparent rounded-full animate-spin"></div>
        </div>
      )}
      <table className="w-full min-w-[960px] text-left text-sm">
        <thead className="sticky top-0 bg-white/95 backdrop-blur-sm text-xs uppercase tracking-wider text-slate-400 border-b border-slate-100">
          <tr>
            <th className="px-4 py-3 font-semibold">Tila</th>
            <th className="px-4 py-3 font-semibold">Aika / tyyppi</th>
            <th className="px-4 py-3 font-semibold">IP-osoite</th>
            <th className="px-4 py-3 font-semibold">Pyyntö</th>
            <th className="px-4 py-3 font-semibold">Riski</th>
            <th className="px-4 py-3 font-semibold text-right">Hallinta</th>
          </tr>
        </thead>
        <tbody className="divide-y divide-slate-50">
          {connections.map((connection) => {
            const meta = statusMeta(connection);
            const Icon = meta.icon;
            const isHovered = hoveredId === connection.id;
            const isOwnIp = Boolean(ownIp && (connection.ip === ownIp || (isLocal(ownIp) && isLocal(connection.ip))));
            const isAdmin = Boolean(!isOwnIp && (connection.isAdmin || knownAdmins[connection.ip]));
            const adminName = connection.adminUser || (knownAdmins[connection.ip]?.user || '');

            return (
              <tr
                key={connection.id}
                onMouseEnter={() => onHover(connection.id)}
                onMouseLeave={() => onHover(null)}
                onClick={() => onSelect(connection.ip)}
                className={`cursor-pointer transition-colors duration-100 ${
                  isHovered
                    ? 'bg-rose-50/70'
                    : isOwnIp
                    ? 'bg-[#b80048]/[0.03] hover:bg-[#b80048]/[0.06]'
                    : isAdmin
                    ? 'bg-indigo-50/30 hover:bg-indigo-50/60'
                    : 'hover:bg-slate-50/80'
                }`}
              >
                <td className="px-4 py-3.5">
                  <span className={`inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-xs font-semibold ${meta.chip}`}>
                    <Icon className="h-3 w-3" />
                    {meta.label}
                  </span>
                </td>
                <td className="px-4 py-3.5">
                  <p className="font-mono text-xs text-slate-700">{connection.dateLabel || connection.timestamp}</p>
                  <p className="mt-0.5 text-xs text-slate-400">{connection.source === 'traffic' ? 'HTTP-loki' : 'Palomuuri'}</p>
                </td>
                <td className="px-4 py-3.5">
                  <div className="flex items-center gap-1.5 flex-wrap">
                    <p className={`font-mono text-xs ${
                      isOwnIp
                        ? 'font-bold text-[#b80048] text-[13px]'
                        : isAdmin
                        ? 'font-bold text-indigo-900 text-[13px]'
                        : 'font-semibold text-slate-900'
                    }`}>
                      {connection.ip}
                    </p>
                    {isOwnIp && (
                      <span className="inline-flex items-center gap-1 rounded-full bg-[#b80048]/10 border border-[#b80048]/30 px-2 py-0.5 text-[10px] font-extrabold text-[#b80048] tracking-wide shadow-xs">
                        <UserCheck className="h-3 w-3 text-[#b80048]" />
                        Oma IP
                      </span>
                    )}
                    {isAdmin && (
                      <span className="inline-flex items-center gap-1 rounded-full bg-indigo-50 border border-indigo-200 px-2 py-0.5 text-[10px] font-bold text-indigo-700 tracking-wide shadow-xs" title={adminName ? `Ylläpitäjä: ${adminName}` : 'Ylläpitäjän IP'}>
                        <Shield className="h-3 w-3 text-indigo-600" />
                        Admin {adminName ? `(${adminName})` : ''}
                      </span>
                    )}
                  </div>
                  <p className="mt-0.5 flex items-center gap-1 text-xs text-slate-400">
                    <Globe className="h-3 w-3 flex-shrink-0" />
                    {connection.country || 'Tuntematon'}{connection.city && connection.city !== 'Unknown' ? ` · ${connection.city}` : ''}
                  </p>
                </td>
                <td className="max-w-[340px] px-4 py-3.5">
                  <p className="truncate font-mono text-xs text-slate-700" title={connection.endpoint}>{connection.endpoint || '/'}</p>
                  <p className="mt-0.5 text-xs text-slate-400">{connection.type || connection.attack}</p>
                </td>
                <td className="px-4 py-3.5">
                  <RiskBar score={connection.threat_score} />
                </td>
                <td className="px-4 py-3.5 text-right">
                  <div className="flex justify-end gap-1.5" onClick={(event) => event.stopPropagation()}>
                    <button
                      onClick={() => onViewDetails(connection)}
                      className="rounded-lg border border-slate-200 p-2 text-slate-500 hover:border-indigo-300 hover:bg-indigo-50 hover:text-indigo-700 transition-all cursor-pointer"
                      title="Näytä IP-historia"
                    >
                      <Info className="h-3.5 w-3.5" />
                    </button>
                    <button
                      onClick={() => onAction(connection.isTracked ? 'untrack' : 'track', connection)}
                      className="rounded-lg border border-slate-200 p-2 text-slate-500 hover:border-blue-300 hover:bg-blue-50 hover:text-blue-700 transition-all"
                      title={connection.isTracked ? 'Lopeta seuranta' : 'Seuraa IP:tä'}
                    >
                      <Eye className="h-3.5 w-3.5" />
                    </button>
                    <button
                      onClick={() => onAction('allow', connection)}
                      className="rounded-lg border border-slate-200 p-2 text-slate-500 hover:border-emerald-300 hover:bg-emerald-50 hover:text-emerald-700 transition-all"
                      title="Salli IP"
                    >
                      <UserCheck className="h-3.5 w-3.5" />
                    </button>
                    {connection.isManualBanned ? (
                      <button
                        onClick={() => onAction('unban', connection)}
                        className="rounded-lg border border-amber-200 bg-amber-50 p-2 text-amber-700 hover:bg-amber-100 transition-all cursor-pointer"
                        title="Poista manuaalinen esto"
                      >
                        <X className="h-3.5 w-3.5" />
                      </button>
                    ) : isOwnIp || isAdmin ? (
                      <span
                        className="rounded-lg border border-emerald-200 bg-emerald-50/50 p-2 text-emerald-600 inline-flex items-center justify-center cursor-help"
                        title={isOwnIp ? "Et voi estää omaa IP-osoitettasi (Suojattu)" : `Ylläpitäjän IP on suojattu estoilta (${adminName || 'Admin'})`}
                      >
                        <ShieldCheck className="h-3.5 w-3.5 text-emerald-600" />
                      </span>
                    ) : (
                      <button
                        onClick={() => onAction('ban', connection)}
                        className="rounded-lg border border-red-200 bg-red-50 p-2 text-red-700 hover:bg-red-100 transition-all cursor-pointer"
                        title="Estä IP"
                      >
                        <Ban className="h-3.5 w-3.5" />
                      </button>
                    )}
                  </div>
                </td>
              </tr>
            );
          })}
          {connections.length === 0 && (
            <tr>
              <td colSpan="6" className="px-4 py-16 text-center">
                <Shield className="mx-auto h-10 w-10 text-slate-200" />
                <p className="mt-3 font-semibold text-slate-500">Tällä suodattimella ei ole tapahtumia.</p>
                <p className="mt-1 text-sm text-slate-400">Tutka näyttää uusia pyyntöjä, kun WordPress vastaanottaa niitä.</p>
              </td>
            </tr>
          )}
        </tbody>
      </table>
    </div>
  );
}

/* ─── Action Dialog ──────────────────────────────────────────── */
function ActionDialog({ pending, onCancel, onConfirm }) {
  if (!pending) return null;
  const labels = {
    ban: ['Estä IP-osoite', 'Esto lisätään Pecodex-palomuuriin. Se koskee tulevia pyyntöjä tältä IP-osoitteelta.', 'Estä IP'],
    unban: ['Poista IP-esto', 'Manuaalinen palomuurin esto poistetaan.', 'Poista esto'],
    allow: ['Salli IP-osoite', 'IP lisätään sallittujen listalle. Tämä ohittaa palomuurin estot tälle osoitteelle.', 'Salli IP'],
    track: ['Aloita seuranta', 'IP merkitään seurantaan radarissa.', 'Aloita seuranta'],
    untrack: ['Lopeta seuranta', 'IP poistetaan radariseurannasta.', 'Lopeta seuranta'],
  };
  const [title, message, confirm] = labels[pending.action];
  return (
    <div className="fixed inset-0 z-[9999] flex items-center justify-center bg-slate-900/40 p-4 backdrop-blur-sm">
      <div className="w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl ring-1 ring-slate-900/5">
        <div className="flex items-start gap-4">
          <div className="flex-shrink-0 rounded-xl bg-[#b80048]/10 p-2.5">
            <ShieldAlert className="h-6 w-6 text-[#b80048]" />
          </div>
          <div className="flex-1 min-w-0">
            <h2 className="text-lg font-bold text-slate-900">{title}</h2>
            <p className="mt-1.5 text-sm text-slate-600">{message}</p>
            <p className="mt-3 rounded-lg bg-slate-50 border border-slate-200 px-3 py-2 font-mono text-sm text-slate-800">{pending.connection.ip}</p>
          </div>
        </div>
        <div className="mt-6 flex justify-end gap-2.5">
          <button onClick={onCancel} className="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 transition-colors">
            Peruuta
          </button>
          <button onClick={onConfirm} className="rounded-lg bg-[#b80048] px-4 py-2 text-sm font-semibold text-white hover:bg-[#96003a] transition-colors shadow-sm">
            {confirm}
          </button>
        </div>
      </div>
    </div>
  );
}

/* ─── Pagination Bar ─────────────────────────────────────────── */
function Pagination({ total, page, pageSize, onPageChange, onPageSizeChange }) {
  const totalPages = Math.max(1, Math.ceil(total / pageSize));
  const start = total === 0 ? 0 : (page - 1) * pageSize + 1;
  const end = Math.min(total, page * pageSize);

  const getPageNumbers = () => {
    const pages = [];
    if (totalPages <= 7) {
      for (let i = 1; i <= totalPages; i++) pages.push(i);
    } else {
      pages.push(1);
      if (page > 3) pages.push('...');
      const startP = Math.max(2, page - 1);
      const endP = Math.min(totalPages - 1, page + 1);
      for (let i = startP; i <= endP; i++) {
        if (!pages.includes(i)) pages.push(i);
      }
      if (page < totalPages - 2) pages.push('...');
      if (!pages.includes(totalPages)) pages.push(totalPages);
    }
    return pages;
  };

  return (
    <div className="flex flex-col gap-3 border-t border-slate-100 bg-white px-4 py-3 sm:flex-row sm:items-center sm:justify-between text-xs text-slate-600">
      <div className="flex items-center gap-3">
        <span>
          Näytetään <strong className="text-slate-900">{start}–{end}</strong> / <strong className="text-slate-900">{total}</strong> tapahtumaa
        </span>
        <div className="flex items-center gap-1.5 border-l border-slate-200 pl-3">
          <label htmlFor="pageSizeSelect" className="text-slate-500">Rivejä per sivu:</label>
          <select
            id="pageSizeSelect"
            value={pageSize}
            onChange={(e) => onPageSizeChange(Number(e.target.value))}
            className="rounded border border-slate-200 bg-slate-50 px-2 py-1 text-xs font-semibold text-slate-700 focus:border-[#b80048] focus:outline-none"
          >
            <option value={15}>15</option>
            <option value={30}>30</option>
            <option value={50}>50</option>
            <option value={100}>100</option>
            <option value={250}>250</option>
          </select>
        </div>
      </div>

      <div className="flex items-center gap-1">
        <button
          onClick={() => onPageChange(1)}
          disabled={page <= 1}
          className="rounded p-1.5 text-slate-500 hover:bg-slate-100 disabled:opacity-30 disabled:hover:bg-transparent transition-colors cursor-pointer disabled:cursor-not-allowed"
          title="Ensimmäinen sivu"
        >
          <ChevronsLeft className="h-4 w-4" />
        </button>
        <button
          onClick={() => onPageChange(page - 1)}
          disabled={page <= 1}
          className="inline-flex items-center gap-1 rounded border border-slate-200 px-2.5 py-1 font-medium text-slate-700 hover:bg-slate-50 disabled:opacity-30 disabled:hover:bg-transparent transition-colors cursor-pointer disabled:cursor-not-allowed"
        >
          <ChevronLeft className="h-3.5 w-3.5" />
          <span>Edellinen</span>
        </button>

        <div className="flex items-center gap-1 px-1">
          {getPageNumbers().map((p, idx) =>
            p === '...' ? (
              <span key={`dots-${idx}`} className="px-1 text-slate-400">…</span>
            ) : (
              <button
                key={p}
                onClick={() => onPageChange(p)}
                className={`min-w-[28px] h-7 rounded text-xs font-bold transition-all cursor-pointer ${
                  page === p
                    ? 'bg-[#b80048] text-white shadow-sm'
                    : 'text-slate-700 hover:bg-slate-100'
                }`}
              >
                {p}
              </button>
            )
          )}
        </div>

        <button
          onClick={() => onPageChange(page + 1)}
          disabled={page >= totalPages}
          className="inline-flex items-center gap-1 rounded border border-slate-200 px-2.5 py-1 font-medium text-slate-700 hover:bg-slate-50 disabled:opacity-30 disabled:hover:bg-transparent transition-colors cursor-pointer disabled:cursor-not-allowed"
        >
          <span>Seuraava</span>
          <ChevronRight className="h-3.5 w-3.5" />
        </button>
        <button
          onClick={() => onPageChange(totalPages)}
          disabled={page >= totalPages}
          className="rounded p-1.5 text-slate-500 hover:bg-slate-100 disabled:opacity-30 disabled:hover:bg-transparent transition-colors cursor-pointer disabled:cursor-not-allowed"
          title="Viimeinen sivu"
        >
          <ChevronsRight className="h-4 w-4" />
        </button>
      </div>
    </div>
  );
}

/* ─── Main App ───────────────────────────────────────────────── */
export default function SecurityApp() {
  const [radar, setRadar] = useState(emptyRadar);
  const [filters, setFilters] = useState({ status: 'all', source: 'all', score: 'all', query: '' });
  const [page, setPage] = useState(1);
  const [pageSize, setPageSize] = useState(30);
  const [hoveredId, setHoveredId] = useState(null);
  const [selectedIpDetails, setSelectedIpDetails] = useState(null);
  const [pending, setPending] = useState(null);
  const [notice, setNotice] = useState('');
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(true);
    const [clientLoading, setClientLoading] = useState(false);
  const [updatedAt, setUpdatedAt] = useState(null);

  // Reset page to 1 on filter changes
  useEffect(() => {
    setPage(1);
  }, [filters, pageSize]);

  const loadRadar = async (quiet = false) => {
    if (!quiet) { setLoading(true); setPage(1); window.dispatchEvent(new Event('pmcRadarDataLoading')); }
    try {
      const offsetHours = window.pmcSecurityHistoryOffset || 0;
      const showAllDay = !!window.pmcSecurityShowAllDay;
      const timeRange = window.pmcSecurityTimeRange || '';
        const isHistorical = offsetHours > 0 || showAllDay || (timeRange && timeRange !== 'now');
        const actionName = isHistorical ? 'pmc_security_timelapse_data' : 'pmc_security_live_map_data';
      const requestData = {
          offset_hours: offsetHours,
          show_all_day: showAllDay ? 1 : 0,
          time_range: window.pmcSecurityTimeRange || ''
        };
      
      const data = await request(actionName, requestData);
      setRadar({ ...emptyRadar, ...data, stats: { ...emptyRadar.stats, ...(data.stats || {}) } });
      setUpdatedAt(new Date());

      // Reset one-time all day flag after fetch
      window.pmcSecurityShowAllDay = false;

      // Event dispatching to timeline is now handled by a useEffect
    } catch (requestError) {
      setError(requestError.message || 'Tietojen haku epäonnistui.');
    } finally {
      if (!quiet) setLoading(false);
    }
  };

  // Dispatch event to timeline (outside React) whenever data or filters change
  useEffect(() => {
    if (typeof window === 'undefined' || !radar.event_summary) return;
    
    const hasFilter = filters.status !== 'all' || filters.source !== 'all' || filters.score !== 'all' || filters.query.trim() !== '';
    const hasFocus = filters.query.trim() !== '';
    
    // Toggle tl-all-day-btn and tl-clear-focus-btn
    const rangeBtns = document.querySelectorAll('.tl-range-btn');
    const clearFocusBtn = document.getElementById('tl-clear-focus-btn');
    
    if (rangeBtns.length > 0) {
      if (hasFocus) {
        rangeBtns.forEach(btn => { btn.disabled = true; btn.style.opacity = '0.4'; btn.style.cursor = 'not-allowed'; });
        
        
        
      } else {
        rangeBtns.forEach(btn => { btn.disabled = false; btn.style.opacity = '1'; btn.style.cursor = 'pointer'; });
        
        
        
      }
    }
    
    if (clearFocusBtn) {
      if (hasFocus) {
        clearFocusBtn.style.display = 'flex';
      } else {
        clearFocusBtn.style.display = 'none';
      }
    }
    
    const filteredSummary = radar.event_summary.filter((event) => {
      const status = String(event.statusClass || event.status || '').toLowerCase();
      const normal = status === 'active' || status === 'tracked';
      const suspicious = status === 'warning' || status === 'suspicious';
      const blocked = status === 'critical' || status === 'blocked';
      
      if (filters.status === 'normal' && !normal) return false;
      if (filters.status === 'suspicious' && !suspicious) return false;
      if (filters.status === 'blocked' && !blocked) return false;
      
      if (filters.source !== 'all' && event.source && event.source !== filters.source) return false;
      if (filters.score === 'high' && Number(event.threat_score || 0) < 60) return false;
      if (filters.score === 'low' && Number(event.threat_score || 0) >= 60) return false;
      
      return [event.ip, event.country, event.city, event.endpoint, event.attack]
        .join(' ').toLowerCase().includes(filters.query.trim().toLowerCase());
    });

    window.dispatchEvent(new CustomEvent('pmcRadarDataLoaded', { 
      detail: { ...radar, event_summary: filteredSummary } 
    }));
  }, [filters, radar]);

  useEffect(() => {
    window.refreshSecurityMap = () => loadRadar(true);
    loadRadar();
    const refresh = window.setInterval(() => {
      if (!window.pmcSecurityHistoryPaused) {
        loadRadar(true);
      }
    }, 15000);

    const handleFilterIp = (e) => {
      if (e.detail && e.detail.ip !== undefined) {
        setFilters((current) => ({ ...current, query: e.detail.ip }));
      }
    };
    
    const handleClearFilters = () => {
      setFilters({ status: 'all', source: 'all', score: 'all', query: '' });
      setPage(1);
    };
    
    window.addEventListener('pmcFilterIp', handleFilterIp);
    window.addEventListener('pmcClearFilters', handleClearFilters);

    return () => {
      window.clearInterval(refresh);
      delete window.refreshSecurityMap;
      window.removeEventListener('pmcFilterIp', handleFilterIp);
      window.removeEventListener('pmcClearFilters', handleClearFilters);
    };
  }, []);

  useEffect(() => {
      setClientLoading(true);
      const t = setTimeout(() => setClientLoading(false), 200);
      return () => clearTimeout(t);
    }, [filters, page]);

    const filteredConnections = useMemo(() => radar.connections.filter((connection) => {
    const status = String(connection.statusClass || connection.status || '').toLowerCase();
    const normal = status === 'active' || status === 'tracked';
    const suspicious = status === 'warning' || status === 'suspicious';
    const blocked = status === 'critical' || status === 'blocked';
    
    if (filters.status === 'normal' && !normal) return false;
    if (filters.status === 'suspicious' && !suspicious) return false;
    if (filters.status === 'blocked' && !blocked) return false;
    
    if (filters.source !== 'all' && connection.source !== filters.source) return false;
    if (filters.score === 'high' && Number(connection.threat_score || 0) < 60) return false;
    if (filters.score === 'low' && Number(connection.threat_score || 0) >= 60) return false;
    return [connection.ip, connection.country, connection.city, connection.endpoint, connection.type, connection.attack]
      .join(' ').toLowerCase().includes(filters.query.trim().toLowerCase());
  }), [filters, radar.connections]);

  const paginatedConnections = useMemo(() => {
    const start = (page - 1) * pageSize;
    // Sort descending by date (newest first)
    const sorted = [...filteredConnections].sort((a, b) => new Date(b.date) - new Date(a.date));
    return sorted.slice(start, start + pageSize);
  }, [filteredConnections, page, pageSize]);

    const mapEvents = useMemo(() => radar.events.filter((event) => {
      if (!isCoordinate(event.lat) || !isCoordinate(event.lng)) return false;
      
      // JOS EVENT ON "Ei paikannettu" (fallback koordinaatti), NÄYTETÄÄN SE VAIN JOS KÄYTTÄJÄ ON FILTTERÖINYT IP:N!
      if (event.city === 'Ei paikannettu' && !filters.query.trim()) return false;

    
    const status = String(event.statusClass || event.status || '').toLowerCase();
    const normal = status === 'active' || status === 'tracked';
    const suspicious = status === 'warning' || status === 'suspicious';
    const blocked = status === 'critical' || status === 'blocked';
    
    if (filters.status === 'normal' && !normal) return false;
    if (filters.status === 'suspicious' && !suspicious) return false;
    if (filters.status === 'blocked' && !blocked) return false;
    
    if (filters.source !== 'all' && event.source !== filters.source) return false;
    if (filters.score === 'high' && Number(event.threat_score || 0) < 60) return false;
    if (filters.score === 'low' && Number(event.threat_score || 0) >= 60) return false;
    
    return [event.ip, event.country, event.city, event.endpoint, event.attack]
      .join(' ').toLowerCase().includes(filters.query.trim().toLowerCase());
  }), [filters, radar.events]);

  const executeAction = async () => {
    if (!pending) return;
    const actions = {
      ban: ['pmc_security_ban_ip', { ip: pending.connection.ip }, `IP ${pending.connection.ip} estettiin.`],
      unban: ['pmc_security_unban_ip', { ip: pending.connection.ip }, `IP-esto poistettiin: ${pending.connection.ip}.`],
      allow: ['pmc_security_firewall_allow_ip', { ip: pending.connection.ip }, `IP sallittiin: ${pending.connection.ip}.`],
      track: ['pmc_security_track_ip', { ip: pending.connection.ip, track_action: 'add' }, `IP lisättiin seurantaan: ${pending.connection.ip}.`],
      untrack: ['pmc_security_track_ip', { ip: pending.connection.ip, track_action: 'remove' }, `IP poistettiin seurannasta: ${pending.connection.ip}.`],
    };
    try {
      const [endpoint, values, success] = actions[pending.action];
      await request(endpoint, values);
      setNotice(success);
      setPending(null);
      await loadRadar(true);
    } catch (actionError) {
      setError(actionError.message || 'Toiminto epäonnistui.');
      setPending(null);
    }
  };

  const selectIp = (ip) => { setFilters((current) => ({ ...current, query: ip })); setPage(1); };
        const stats = useMemo(() => {
    let displayedTotal = 0;
    let normal = 0, suspicious = 0, blocked = 0;
    
    radar.connections.forEach(connection => {
      // Apply non-status filters
      if (filters.source !== 'all' && connection.source !== filters.source) return;
      if (filters.score === 'high' && Number(connection.threat_score || 0) < 60) return;
      if (filters.score === 'low' && Number(connection.threat_score || 0) >= 60) return;
      
      const matchSearch = [connection.ip, connection.country, connection.city, connection.endpoint, connection.type, connection.attack]
        .join(' ').toLowerCase().includes(filters.query.trim().toLowerCase());
      if (!matchSearch) return;

      const s = String(connection.statusClass || connection.status || '').toLowerCase();
      const isNormal = s === 'active' || s === 'tracked';
      const isSuspicious = s === 'warning' || s === 'suspicious';
      const isBlocked = s === 'critical' || s === 'blocked';
      
      // Update categorical counts (IGNORING the status filter so buttons don't zero out)
      if (isBlocked) blocked++;
      else if (isSuspicious) suspicious++;
      else normal++;
      
      // Update the total displayed count (RESPECTING the status filter)
      let matchesStatus = true;
      if (filters.status === 'normal' && !isNormal) matchesStatus = false;
      if (filters.status === 'suspicious' && !isSuspicious) matchesStatus = false;
      if (filters.status === 'blocked' && !isBlocked) matchesStatus = false;
      
      if (matchesStatus) {
          displayedTotal++;
      }
    });

    return {
      total_connections: displayedTotal,
      normal_connections: normal,
      suspicious_connections: suspicious,
      blocked_connections: blocked,
      request_rate: radar.stats?.request_rate || 0
    };
  }, [radar.connections, radar.stats, filters.status, filters.source, filters.score, filters.query]);

  return (
    <main className="min-h-full bg-slate-50 p-4 text-slate-900 md:p-6" style={{ fontFamily: 'Inter, sans-serif' }}>
      <div className="mx-auto max-w-[1680px]">

        {/* ── Header ── */}
        <header className="mb-6 flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
          <div>
            <div className="inline-flex items-center gap-2 rounded-full border border-[#b80048]/20 bg-[#b80048]/8 px-3 py-1 text-xs font-bold uppercase tracking-widest text-[#b80048]">
              <span className="relative flex h-2 w-2">
                <span className="absolute inline-flex h-full w-full animate-ping rounded-full bg-[#b80048] opacity-60" />
                <span className="relative inline-flex h-2 w-2 rounded-full bg-[#b80048]" />
              </span>
              Live telemetry
            </div>
            <h1 className="mt-2 text-2xl font-bold tracking-tight text-slate-950">Pecodex Security Radar</h1>
            <p className="mt-1 text-sm text-slate-500">Todelliset WordPress-pyynnöt, palomuuritapahtumat ja hallintatoimet.</p>
          </div>
          <div className="flex items-center gap-3">
            <span className="text-xs text-slate-400">
              {updatedAt ? `Päivitetty ${updatedAt.toLocaleTimeString('fi-FI')}` : 'Ladataan…'}
            </span>
            <button
              onClick={() => loadRadar()}
              disabled={loading}
              className="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 hover:shadow transition-all disabled:opacity-50 cursor-pointer"
            >
              <RefreshCw className={`h-4 w-4 ${loading ? 'animate-spin' : ''}`} />
              Päivitä
            </button>
          </div>
        </header>

        {/* ── Ilmoitukset ── */}
        {(error || notice) && (
          <div className={`mb-5 flex items-start justify-between gap-3 rounded-xl border p-3.5 text-sm shadow-sm ${error ? 'border-red-200 bg-red-50 text-red-800' : 'border-emerald-200 bg-emerald-50 text-emerald-800'}`}>
            <span>{error || notice}</span>
            <button onClick={() => { setError(''); setNotice(''); }} aria-label="Sulje ilmoitus" className="flex-shrink-0 rounded-md p-0.5 hover:bg-black/10 transition-colors cursor-pointer">
              <X className="h-4 w-4" />
            </button>
          </div>
        )}

        {/* ── Tilastokortit ── */}
        <section className="mb-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
          <StatCard label="Näytetyt tapahtumat" value={stats.total_connections} icon={Activity} tone="bg-blue-50 text-blue-600" toneColor="#2563eb" active={filters.status === 'all'} onClick={() => setFilters((f) => ({ ...f, status: 'all' }))} />
          <StatCard label="Normaali liikenne" value={stats.normal_connections} icon={CheckCircle} tone="bg-emerald-50 text-emerald-600" toneColor="#059669" active={filters.status === 'normal'} onClick={() => setFilters((f) => ({ ...f, status: 'normal' }))} />
          <StatCard label="Epäilyttävät" value={stats.suspicious_connections} icon={AlertTriangle} tone="bg-amber-50 text-amber-600" toneColor="#d97706" active={filters.status === 'suspicious'} onClick={() => setFilters((f) => ({ ...f, status: 'suspicious' }))} />
          <StatCard label="Estetyt" value={stats.blocked_connections} icon={Ban} tone="bg-red-50 text-red-600" toneColor="#dc2626" active={filters.status === 'blocked'} onClick={() => setFilters((f) => ({ ...f, status: 'blocked' }))} />
        </section>

        {/* ── Pyynnöt / minuutti ── */}
        <div className="relative overflow-hidden mb-5 flex items-center gap-3 rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm shadow-sm">
            {(loading || clientLoading) && (
              <div className="absolute inset-0 z-[10] bg-white/40 backdrop-blur-[1px] flex items-center justify-center">
                <div className="w-5 h-5 border-2 border-indigo-600 border-t-transparent rounded-full animate-spin"></div>
              </div>
            )}
          <div className="rounded-lg bg-blue-50 p-1.5">
            <Server className="h-4 w-4 text-blue-600" />
          </div>
          <span>
            <strong className="text-slate-900">{stats.request_rate || 0}</strong>
            <span className="text-slate-600"> pyyntöä viimeisen minuutin aikana</span>
          </span>
          <span className="text-slate-200 select-none">•</span>
          <span className="text-slate-400 text-xs">Kartalle lisätään vain IP:t, joille on välimuistissa maantieteellinen sijainti.</span>
        </div>

        {/* ── Kartta ── */}
        <RadarMap
            loading={loading || clientLoading}
          events={mapEvents}
          server={radar.server}
          hoveredId={hoveredId}
          onHover={setHoveredId}
          onSelect={selectIp}
        />

        {/* ── Timeline Mount Point ── */}
        <div id="ps-timeline-mount" className="mt-5"></div>

        {/* ── Taulukko & Paginointi ── */}
        <section className="mt-5 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
          <FilterBar
            filters={filters}
            setFilters={setFilters}
            total={filteredConnections.length}
            page={page}
            pageSize={pageSize}
            onPageChange={setPage}
            onPageSizeChange={setPageSize}
          />
          <ConnectionTable
              loading={loading || clientLoading}
            connections={paginatedConnections}
            currentIp={radar.current_ip || window.pmcSecurityConfig?.currentIp || ''}
            adminIps={radar.admin_ips || window.pmcSecurityConfig?.adminIps || {}}
            hoveredId={hoveredId}
            onHover={setHoveredId}
            onSelect={selectIp}
            onAction={(action, connection) => setPending({ action, connection })}
            onViewDetails={setSelectedIpDetails}
          />
          <Pagination
            total={filteredConnections.length}
            page={page}
            pageSize={pageSize}
            onPageChange={setPage}
            onPageSizeChange={setPageSize}
          />
        </section>
      </div>

      <ActionDialog pending={pending} onCancel={() => setPending(null)} onConfirm={executeAction} />
      <IpDetailsModal isOpen={!!selectedIpDetails} onClose={() => setSelectedIpDetails(null)} connectionDetails={selectedIpDetails} />
    </main>
  );
}
