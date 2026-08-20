<div id="ps-module-news" class="ps-module" style="display:none; background:#f0f2f5;">
	<div class="ps-module-header" style="background:#fff; position:sticky; top:0; z-index:10;">
		<h2><span class="material-symbols-outlined" style="color:#b80048; font-size:24px;">newspaper</span> Tietoturvauutiset ja Uhkatiedotteet</h2>
		<button class="ps-module-close"><span class="material-symbols-outlined">close</span></button>
	</div>

	<div class="ps-module-content" style="max-width: 1400px; margin: 0 auto; width:100%; padding: 40px 30px;">
		
		<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:24px;">
			<div class="ps-news-filters" style="display:flex; gap:8px;">
				<button class="ps-news-filter active" data-filter="all">Kaikki</button>
				<button class="ps-news-filter" data-filter="Wordfence">Wordfence</button>
				<button class="ps-news-filter" data-filter="Sucuri">Sucuri</button>
				<button class="ps-news-filter" data-filter="WP Core">WP Core</button>
			</div>
			<button id="ps-news-refresh-btn" style="background:#fff; border:1px solid #e2e8f0; border-radius:6px; padding:6px 12px; cursor:pointer; display:flex; align-items:center; gap:6px; font-weight:600; color:#475569; box-shadow:0 1px 2px rgba(0,0,0,0.05); transition:0.2s;">
				<span class="material-symbols-outlined" style="font-size:16px;">refresh</span> Päivitä
			</button>
		</div>

		<div id="ps-news-loading" style="text-align:center; padding:100px; color:#94a3b8;">
			<span class="material-symbols-outlined" style="font-size:48px; animation:spin 2s linear infinite;">autorenew</span>
			<div style="margin-top:16px; font-size:14px; font-weight:500;">Aggregoidaan tuoreimpia uhkatietoja...</div>
		</div>

		<div id="ps-news-grid" style="display:none; grid-template-columns: repeat(12, 1fr); gap:24px;">
			<!-- Injected by JS -->
		</div>

	</div>
</div>

<style>
	.ps-news-filter {
		background: #fff;
		border: 1px solid #e2e8f0;
		color: #64748b;
		padding: 6px 16px;
		border-radius: 99px;
		font-size: 13px;
		font-weight: 600;
		cursor: pointer;
		transition: all 0.2s ease;
		box-shadow: 0 1px 2px rgba(0,0,0,0.05);
	}
	.ps-news-filter:hover { background: #f8fafc; color: #1e293b; }
	.ps-news-filter.active { background: #1e293b; border-color: #1e293b; color: #fff; }
	
	.ps-news-card {
		background: #fff;
		border: 1px solid #e2e8f0;
		border-radius: 12px;
		overflow: hidden;
		display: flex;
		flex-direction: column;
		transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
		box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
		text-decoration: none;
		color: inherit;
	}
	.ps-news-card:hover {
		transform: translateY(-4px);
		box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
		border-color: #cbd5e1;
	}
	.ps-news-hero {
		grid-column: span 12;
		flex-direction: row;
		min-height: 280px;
	}
	.ps-news-hero .ps-news-content {
		padding: 40px;
		flex: 1;
		display: flex;
		flex-direction: column;
		justify-content: center;
	}
	.ps-news-hero .ps-news-title { font-size: 28px; line-height: 1.2; margin-bottom: 16px; font-weight: 800; color: #0f172a; }
	.ps-news-hero .ps-news-desc { font-size: 16px; color: #475569; line-height: 1.6; }
	
	.ps-news-standard {
		grid-column: span 4;
	}
	.ps-news-standard .ps-news-content {
		padding: 20px;
		display: flex;
		flex-direction: column;
		flex: 1;
	}
	.ps-news-standard .ps-news-title { font-size: 16px; font-weight: 700; line-height: 1.4; color: #1e293b; margin-bottom: 12px; flex: 1; }
	.ps-news-standard .ps-news-desc { font-size: 13px; color: #64748b; line-height: 1.5; margin-bottom: 16px; }

	.ps-news-meta {
		display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;
	}
	.ps-news-source {
		font-size: 10px; font-weight: 800; text-transform: uppercase; padding: 4px 10px; border-radius: 6px; letter-spacing: 0.05em;
	}
	.source-wordfence { background: #fee2e2; color: #991b1b; }
	.source-sucuri { background: #e0e7ff; color: #3730a3; }
	.source-wpcore { background: #f1f5f9; color: #0f172a; }
	.source-default { background: #f3f4f6; color: #374151; }

	.ps-news-date { font-size: 12px; font-weight: 500; color: #94a3b8; }
	
	/* Responsiveness */
	@media (max-width: 1200px) { .ps-news-standard { grid-column: span 6; } }
	@media (max-width: 768px) { 
		.ps-news-hero { flex-direction: column; grid-column: span 12; } 
		.ps-news-standard { grid-column: span 12; } 
	}
	
	@keyframes spin { 100% { transform: rotate(360deg); } }
</style>

<script>
document.addEventListener('DOMContentLoaded', () => {
	const grid = document.getElementById('ps-news-grid');
	const loading = document.getElementById('ps-news-loading');
	const refreshBtn = document.getElementById('ps-news-refresh-btn');
	const filters = document.querySelectorAll('.ps-news-filter');
	
	let allNews = [];
	let currentFilter = 'all';
	let isFirstLoad = true;

	async function loadFullNews() {
		if (!grid || !loading) return;
		
		grid.style.display = 'none';
		loading.style.display = 'block';
		
		try {
			const formData = new FormData();
			formData.append('action', 'pmc_security_get_news');
			formData.append('limit', 24);
			if (typeof pmcSecurityConfig !== 'undefined' && pmcSecurityConfig.nonce) {
				formData.append('nonce', pmcSecurityConfig.nonce);
			}

			const res = await fetch(ajaxurl, { method: 'POST', body: formData });
			const data = await res.json();
			
			if (data.success && data.data && data.data.length > 0) {
				allNews = data.data;
				renderNews();
			} else {
				loading.innerHTML = '<div style="color:#64748b;">Ei uutisia saatavilla.</div>';
			}
		} catch (e) {
			console.error("Virhe ladattaessa uutisia:", e);
			loading.innerHTML = '<div style="color:#dc2626;">Virhe ladattaessa uutisia.</div>';
		}
	}

	function renderNews() {
		const filtered = currentFilter === 'all' 
			? allNews 
			: allNews.filter(n => n.source && n.source.toLowerCase().includes(currentFilter.toLowerCase()));
		
		loading.style.display = 'none';
		grid.style.display = 'grid';
		grid.innerHTML = '';
		
		if (filtered.length === 0) {
			grid.innerHTML = '<div style="grid-column: span 12; text-align:center; padding: 40px; color:#64748b;">Ei tuloksia tälle lähteelle.</div>';
			return;
		}

		filtered.forEach((item, index) => {
			// First item is always hero, unless filtered and there's only a few
			const isHero = index === 0 && currentFilter === 'all';
			const cardClass = isHero ? 'ps-news-hero' : 'ps-news-standard';
			
			let srcClass = 'source-default';
			const s = (item.source || '').toLowerCase();
			if (s.includes('wordfence')) srcClass = 'source-wordfence';
			if (s.includes('sucuri')) srcClass = 'source-sucuri';
			if (s.includes('wp')) srcClass = 'source-wpcore';
			
			const html = `
				<a href="${item.link}" target="_blank" class="ps-news-card ${cardClass}">
					<div class="ps-news-content">
						<div class="ps-news-meta">
							<span class="ps-news-source ${srcClass}">${item.source || 'Uutinen'}</span>
							<span class="ps-news-date">${item.date}</span>
						</div>
						<h3 class="ps-news-title">${item.title}</h3>
						<p class="ps-news-desc">${item.desc}</p>
						<div style="margin-top:auto; font-size:12px; font-weight:700; color:#b80048; display:flex; align-items:center; gap:4px;">
							Lue artikkeli <span class="material-symbols-outlined" style="font-size:14px;">arrow_forward</span>
						</div>
					</div>
				</a>
			`;
			grid.insertAdjacentHTML('beforeend', html);
		});
	}

	if (refreshBtn) refreshBtn.addEventListener('click', loadFullNews);
	
	filters.forEach(f => {
		f.addEventListener('click', (e) => {
			filters.forEach(btn => btn.classList.remove('active'));
			e.target.classList.add('active');
			currentFilter = e.target.getAttribute('data-filter');
			renderNews();
		});
	});

	// Check if this module is opened, then load news if not loaded yet
	const observer = new MutationObserver((mutations) => {
		mutations.forEach((mutation) => {
			if (mutation.type === 'attributes' && mutation.attributeName === 'style') {
				const mod = document.getElementById('ps-module-news');
				if (mod && mod.style.display !== 'none' && isFirstLoad) {
					isFirstLoad = false;
					loadFullNews();
				}
			}
		});
	});
	
	const modNews = document.getElementById('ps-module-news');
	if (modNews) {
		observer.observe(modNews, { attributes: true });
	}

});
</script>
