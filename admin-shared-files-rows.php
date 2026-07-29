<?php if ( empty( $files ) ) : ?>
<tr>
	<td colspan="5" class="px-4 py-8 text-center text-gray-500">
		Ei jaettuja tiedostoja tällä hetkellä.
	</td>
</tr>
<?php endif; ?>

<?php foreach ( $files as $f ) : ?>
<tr class="hover:bg-blue-50/30 transition-colors group pgm-file-row" data-file-id="<?php echo esc_attr( $f['id'] ); ?>">
	<td class="px-4 py-4" style="border-bottom:1px solid #f3f4f6;">
		<div class="flex items-center gap-3">
			<div class="p-2 <?php echo esc_attr( $f['icon_bg'] . ' ' . $f['icon_text'] ); ?> rounded-lg shrink-0">
				<?php echo $f['icon_svg']; ?>
			</div>
			<div>
				<p class="font-medium text-gray-900 m-0 leading-tight"><?php echo esc_html( $f['title'] ); ?></p>
				<p class="text-xs text-gray-500 m-0 leading-tight pt-1">Päivitetty <?php echo esc_html( $f['date'] ); ?></p>
			</div>
		</div>
	</td>
	<td class="px-4 py-4" style="border-bottom:1px solid #f3f4f6;">
		<div class="flex items-center gap-2">
			<img class="h-6 w-6 rounded-full m-0" src="<?php echo esc_url( $f['avatar'] ); ?>" alt="">
			<span class="text-gray-700"><?php echo esc_html( $f['author'] ); ?></span>
		</div>
	</td>
	<td class="px-4 py-3 whitespace-normal" style="border-bottom:1px solid #f3f4f6;">
		<div class="flex flex-wrap gap-2 pgm-chips-container">
			<?php foreach ( $f['links'] as $link ) : 
				$email = ! empty( $link['email'] ) ? $link['email'] : 'Ulkoinen Vieras';
				$tok = $link['token'];
				$is_expired = isset( $link['expires'] ) && $link['expires'] && $link['expires'] < $now;
				$chip_bg = $is_expired ? 'bg-red-50 text-red-700 border-red-200' : 'bg-white text-gray-700 border-gray-300 shadow-sm';
			?>
			<div class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-xs font-medium <?php echo esc_attr( $chip_bg ); ?> border transition-colors pgm-chip" data-token="<?php echo esc_attr( $tok ); ?>">
				<span><?php echo esc_html( $email ); ?> <span class="opacity-70 font-normal">(<?php echo $is_expired ? 'Vanhentunut' : 'Katselija'; ?>)</span></span>
				<?php if ( current_user_can( 'manage_options' ) ) : ?>
				<button onclick="removeAccess(this, '<?php echo esc_js( $email ); ?>', <?php echo esc_js( $f['id'] ); ?>, '<?php echo esc_js( $tok ); ?>')" class="text-gray-400 hover:text-red-600 hover:bg-red-50 rounded p-0.5 transition-colors focus:outline-none" title="Poista oikeus">
					<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
				</button>
				<?php endif; ?>
			</div>
			<?php endforeach; ?>
		</div>
	</td>
	<td class="px-4 py-4" style="border-bottom:1px solid #f3f4f6;">
		<?php if ( $f['is_active'] ) : ?>
		<span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">
			<svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"></path></svg>
			Linkki aktiivinen
		</span>
		<?php else : ?>
		<span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium bg-red-100 text-red-800">
			Ei aktiivisia linkkejä
		</span>
		<?php endif; ?>
	</td>
	<td class="px-4 py-4 text-right" style="border-bottom:1px solid #f3f4f6;">
		<div class="relative inline-block text-left dropdown-container">
			<button onclick="toggleDropdown(this)" class="p-1.5 text-gray-500 hover:text-gray-800 hover:bg-gray-200 rounded-md transition-colors focus:outline-none bg-transparent border-none cursor-pointer">
				<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z"></path></svg>
			</button>
			<div class="hidden absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg z-30 border border-gray-100 py-1 action-dropdown">
				<?php 
				$first_tok = !empty($f['links']) ? $f['links'][0]['token'] : '';
				$share_url = add_query_arg( array( 'action' => 'pecodex_private_media', 'id' => $f['id'], 'share_token' => $first_tok ), admin_url( 'admin-post.php' ) );
				?>
				<button onclick="copyLink('<?php echo esc_js( $share_url ); ?>')" class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 bg-transparent border-none cursor-pointer block">Kopioi 1. linkki</button>
				<?php if ( current_user_can( 'manage_options' ) ) : ?>
				<hr class="my-1 border-gray-100">
				<button onclick="removeAllAccess(this, <?php echo esc_js( $f['id'] ); ?>)" class="w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-red-50 font-medium bg-transparent border-none cursor-pointer block">Poista kaikki jaot</button>
				<?php endif; ?>
			</div>
		</div>
	</td>
</tr>
<?php endforeach; ?>
