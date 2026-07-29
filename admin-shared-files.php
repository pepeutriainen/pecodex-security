<?php
// admin-shared-files.php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! current_user_can( 'upload_files' ) ) {
	wp_die( 'Ei oikeuksia.' );
}

$data = $this->get_shared_files_data( 0, 30 );
$files = $data['files'];
$total = $data['total'];

$nonce = wp_create_nonce( 'pgm_admin_settings' );
$ajax  = esc_url( admin_url( 'admin-ajax.php' ) );
?>
<!DOCTYPE html>
<html lang="fi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edistynyt Hallintapaneeli - Tiedostot & Käyttäjät</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f1f5f9; /* Slate 100 */
        }
        
        .custom-scrollbar::-webkit-scrollbar { width: 8px; height: 8px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
        .custom-scrollbar::-webkit-scrollbar-thumb:hover { background: #94a3b8; }

        /* Row delete animation */
        .row-deleting {
            animation: slideOutRight 0.4s ease-in forwards;
        }
        @keyframes slideOutRight {
            to { opacity: 0; transform: translateX(50px); }
        }

        /* Toast animation */
        @keyframes slideDownFade {
            0% { opacity: 0; transform: translateY(-20px) translateX(-50%); }
            10% { opacity: 1; transform: translateY(0) translateX(-50%); }
            90% { opacity: 1; transform: translateY(0) translateX(-50%); }
            100% { opacity: 0; transform: translateY(-20px) translateX(-50%); }
        }
        .toast-animate {
            animation: slideDownFade 3s ease-in-out forwards;
        }

        /* Nav Tab Active State */
        .tab-active {
            border-bottom: 2px solid #2563eb; /* Blue 600 */
            color: #1e40af; /* Blue 800 */
        }
    </style>
	<!-- Ladataan WordPressin sisäänrakennettu jQuery iframeen, koska AJAX käyttää sitä -->
	<script src="<?php echo esc_url( includes_url( 'js/jquery/jquery.min.js' ) ); ?>"></script>
</head>
<body class="flex flex-col h-screen overflow-hidden text-gray-800 bg-white">
		<main class="flex-1 overflow-y-auto p-4 sm:p-6 lg:p-8 custom-scrollbar">
			<div class="max-w-[1400px] mx-auto space-y-4">

				<!-- Title & Filters Bar -->
				<div class="flex flex-col lg:flex-row gap-4 justify-between items-start lg:items-center bg-gray-50 p-4 rounded-lg border border-gray-200">
					<h1 class="text-lg font-semibold text-gray-800 flex-shrink-0" style="margin:0; padding:0; line-height:1;">Kaikki jaetut tiedostot</h1>
					<div class="flex flex-wrap gap-3 w-full lg:w-auto items-center">
						<div class="relative w-full sm:w-64">
							<div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
								<svg class="h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
							</div>
							<input type="text" id="pgmSearchInput" class="block w-full pl-9 pr-3 py-2 border border-gray-300 rounded-md leading-5 bg-white placeholder-gray-500 focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 sm:text-sm" placeholder="Hae tiedostoa tai käyttäjää...">
						</div>

						<select class="appearance-none bg-white border border-gray-300 text-gray-700 py-2 pl-3 pr-8 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
							<option>Kaikki tiedostotyypit</option>
							<option>Dokumentit</option>
							<option>Kuvat & Mediat</option>
						</select>

						<select class="appearance-none bg-white border border-gray-300 text-gray-700 py-2 pl-3 pr-8 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
							<option>Kaikki oikeustasot</option>
							<option>Sisäiset jaot</option>
							<option>Ulkoiset vieraat</option>
						</select>
					</div>
				</div>

				<!-- The Core Data Table -->
				<div class="bg-white rounded-lg shadow-sm border border-gray-200">
					<div>
						<table class="w-full text-left border-collapse pgm-files-table" style="border:none;">
							<thead>
								<tr class="bg-gray-50 border-b border-gray-200 text-xs uppercase tracking-wider text-gray-600 font-semibold">
									<th class="px-4 py-3" style="border:none;text-align:left;">Tiedoston nimi</th>
									<th class="px-4 py-3" style="border:none;text-align:left;">Omistaja</th>
									<th class="px-4 py-3 w-1/3" style="border:none;text-align:left;">Jaettu käyttäjille (Hallinnoi)</th>
									<th class="px-4 py-3" style="border:none;text-align:left;">Yleinen tila</th>
									<th class="px-4 py-3 text-right" style="border:none;">Lisätoiminnot</th>
								</tr>
							</thead>
							<tbody class="divide-y divide-gray-100 text-sm" id="pgmFilesTbody">
								<?php require __DIR__ . '/admin-shared-files-rows.php'; ?>
							</tbody>
						</table>
					</div>
				</div>
				
				<div class="text-sm text-gray-500 mt-2 flex justify-between items-center">
					<span id="pgmFileCountInfo">Näytetään <?php echo count($files); ?> / <?php echo $total; ?> jaettua kohdetta.</span>
					<button id="pgmLoadMoreBtn" class="text-blue-600 hover:underline bg-transparent border-none cursor-pointer p-0 m-0" style="<?php if ( $total <= count($files) ) echo 'display:none;'; ?>">Lataa lisää</button>
				</div>

			</div>
		</main>

		<!-- Global Toast Notification -->
		<div id="toast" class="fixed top-20 left-1/2 transform -translate-x-1/2 bg-gray-900 text-white px-6 py-3 rounded-full shadow-2xl flex items-center gap-3 opacity-0 pointer-events-none z-50 transition-all duration-300">
			<svg class="w-5 h-5 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
			<span id="toastMsg" class="font-medium">Toiminto suoritettu!</span>
		</div>

		<!-- Custom Confirm Modal -->
		<div id="pgmConfirmModal" class="fixed inset-0 z-50 hidden flex items-center justify-center">
			<div class="absolute inset-0 bg-gray-900 bg-opacity-50 transition-opacity"></div>
			<div class="relative bg-white rounded-lg shadow-xl max-w-md w-full mx-4 overflow-hidden transform transition-all">
				<div class="px-6 py-5">
					<div class="flex items-center gap-4 mb-3">
						<div class="flex-shrink-0 w-10 h-10 rounded-full bg-red-100 flex items-center justify-center text-red-600">
							<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
						</div>
						<h3 class="text-lg font-medium text-gray-900 m-0 leading-none" id="pgmConfirmTitle">Vahvista poisto</h3>
					</div>
					<p class="text-sm text-gray-500 ml-14 m-0" id="pgmConfirmMessage">Haluatko varmasti poistaa tämän oikeuden?</p>
				</div>
				<div class="px-6 py-4 bg-gray-50 flex justify-end gap-3 border-t border-gray-200">
					<button id="pgmConfirmCancel" class="px-4 py-2 bg-white border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none cursor-pointer">Peruuta</button>
					<button id="pgmConfirmProceed" class="px-4 py-2 bg-red-600 border border-transparent rounded-md text-sm font-medium text-white hover:bg-red-700 focus:outline-none shadow-sm cursor-pointer">Kyllä, poista</button>
				</div>
			</div>
		</div>

<script>
	function toggleDropdown(button) {
		event.stopPropagation();
		closeAllDropdowns();
		const dropdown = button.nextElementSibling;
		dropdown.classList.remove('hidden');
	}

	function closeAllDropdowns() {
		const dropdowns = document.querySelectorAll('.action-dropdown');
		dropdowns.forEach(dd => dd.classList.add('hidden'));
	}

	document.addEventListener('click', closeAllDropdowns);

	function showToast(message) {
		const toast = document.getElementById('toast');
		const msgSpan = document.getElementById('toastMsg');
		msgSpan.innerText = message;
		toast.classList.remove('toast-animate');
		void toast.offsetWidth;
		toast.classList.add('toast-animate');
	}

	function copyLink(url) {
		closeAllDropdowns();
		if (navigator.clipboard && window.isSecureContext) {
			navigator.clipboard.writeText(url).then(function() {
				showToast('Linkki kopioitu leikepöydälle!');
			}).catch(function() { fallbackCopyTextToClipboard(url); });
		} else {
			fallbackCopyTextToClipboard(url);
		}
	}

	function fallbackCopyTextToClipboard(text) {
		var textArea = document.createElement("textarea");
		textArea.value = text;
		textArea.style.top = "0";
		textArea.style.left = "0";
		textArea.style.position = "fixed";
		document.body.appendChild(textArea);
		textArea.focus();
		textArea.select();
		try {
			var successful = document.execCommand('copy');
			if (successful) {
				showToast('Linkki kopioitu leikepöydälle!');
			} else {
				prompt('Kopiointi estetty selaimessa. Kopioi tämä linkki:', text);
			}
		} catch (err) {
			prompt('Kopiointi epäonnistui. Kopioi tämä linkki:', text);
		}
		document.body.removeChild(textArea);
	}

	let confirmCallback = null;
	const confirmModal = document.getElementById('pgmConfirmModal');

	function showConfirmModal(title, message, callback) {
		closeAllDropdowns();
		document.getElementById('pgmConfirmTitle').innerText = title;
		document.getElementById('pgmConfirmMessage').innerText = message;
		confirmModal.classList.remove('hidden');
		confirmCallback = callback;
	}

	document.getElementById('pgmConfirmCancel').addEventListener('click', function() {
		confirmModal.classList.add('hidden');
		confirmCallback = null;
	});

	document.getElementById('pgmConfirmProceed').addEventListener('click', function() {
		confirmModal.classList.add('hidden');
		if (confirmCallback) {
			confirmCallback();
			confirmCallback = null;
		}
	});

	function removeAccess(btnElement, userName, fileId, token) {
		showConfirmModal(
			'Poista käyttöoikeus',
			`Haluatko varmasti poistaa oikeuden käyttäjältä ${userName}?`,
			function() {
				const chip = btnElement.closest('div.pgm-chip');
				const row = btnElement.closest('tr.pgm-file-row');
				
				jQuery.post("<?php echo $ajax; ?>", {
					action: "pgm_revoke_share_link",
					nonce: "<?php echo $nonce; ?>",
					attachment_id: fileId,
					token: token
				}).done(function(r) {
					if (r && r.success) {
						chip.style.transition = "all 0.2s ease";
						chip.style.transform = "scale(0.8)";
						chip.style.opacity = "0";
						setTimeout(() => {
							chip.remove();
							showToast(`Käyttäjän '${userName}' oikeus poistettu.`);
							if (row.querySelectorAll('.pgm-chip').length === 0) {
								row.classList.add('row-deleting');
								setTimeout(() => row.remove(), 400);
							}
						}, 200);
					} else {
						showToast('Virhe poistettaessa linkkiä.');
					}
				});
			}
		);
	}

	function removeAllAccess(btnElement, fileId) {
		showConfirmModal(
			'Poista kaikki jaot',
			'Haluatko varmasti poistaa KAIKKI jaot tästä tiedostosta? Linkit lakkaavat välittömästi toimimasta.',
			function() {
				const row = btnElement.closest('tr.pgm-file-row');
				
				jQuery.post("<?php echo $ajax; ?>", {
					action: "pgm_revoke_all_share_links",
					nonce: "<?php echo $nonce; ?>",
					attachment_id: fileId
				}).done(function(r) {
					if (r && r.success) {
						row.classList.add('row-deleting');
						setTimeout(() => {
							row.remove();
							showToast('Kaikki tiedoston jaot poistettu onnistuneesti.');
						}, 400);
					} else {
						showToast('Virhe poistettaessa jakoja.');
					}
				});
			}
		);
	}

	let currentOffset = <?php echo count($files); ?>;
	let currentSearch = '';
	let searchTimeout = null;

	const searchInput = document.getElementById('pgmSearchInput');
	const loadMoreBtn = document.getElementById('pgmLoadMoreBtn');
	const tbody = document.getElementById('pgmFilesTbody');
	const countInfo = document.getElementById('pgmFileCountInfo');

	function loadData(isLoadMore) {
		if (isLoadMore) {
			loadMoreBtn.innerText = 'Ladataan...';
			loadMoreBtn.disabled = true;
		} else {
			tbody.innerHTML = '<tr><td colspan="5" class="px-4 py-8 text-center text-gray-500">Ladataan...</td></tr>';
			currentOffset = 0;
		}

		jQuery.post("<?php echo $ajax; ?>", {
			action: "pgm_load_shared_files",
			nonce: "<?php echo $nonce; ?>",
			offset: currentOffset,
			limit: 30,
			search: currentSearch
		}).done(function(r) {
			if (r && r.success) {
				if (isLoadMore) {
					tbody.insertAdjacentHTML('beforeend', r.data.html);
					loadMoreBtn.innerText = 'Lataa lisää';
					loadMoreBtn.disabled = false;
				} else {
					tbody.innerHTML = r.data.html;
				}
				
				currentOffset += r.data.count;
				countInfo.innerText = `Näytetään ${currentOffset} / ${r.data.total} jaettua kohdetta.`;

				if (currentOffset >= r.data.total) {
					loadMoreBtn.style.display = 'none';
				} else {
					loadMoreBtn.style.display = '';
				}
			}
		});
	}

	searchInput.addEventListener('input', function(e) {
		clearTimeout(searchTimeout);
		currentSearch = e.target.value;
		searchTimeout = setTimeout(function() {
			loadData(false);
		}, 400);
	});

	if (loadMoreBtn) {
		loadMoreBtn.addEventListener('click', function() {
			loadData(true);
		});
	}
</script>

</body>
</html>
