<?php
$file = 'pecodex-media-control.php';
$content = file_get_contents($file);

// 1. Add properties
if (strpos($content, '$attachment_public_copy_cache') === false) {
    $content = str_replace(
        'private $needs_public_upload_protection_rules_refresh = false;',
        "private \$needs_public_upload_protection_rules_refresh = false;\n\n\tprivate \$attachment_public_copy_cache = array();\n\tprivate \$attachment_private_copy_cache = array();",
        $content
    );
}

// 2. Modify attachment_has_public_upload_copy
$public_func = <<<'EOD'
private function attachment_has_public_upload_copy( $attachment_id ) {
		if ( isset( $this->attachment_public_copy_cache[ $attachment_id ] ) ) {
			return $this->attachment_public_copy_cache[ $attachment_id ];
		}

		$has_copy = false;
		foreach ( $this->attachment_relative_paths( $attachment_id ) as $relative_path ) {
			if ( $this->public_upload_file_path_if_exists( $relative_path ) || $this->public_converted_upload_copy_exists( $relative_path ) ) {
				$has_copy = true;
				break;
			}
		}
		
		$this->attachment_public_copy_cache[ $attachment_id ] = $has_copy;
		return $has_copy;
	}
EOD;

$content = preg_replace(
    '/private function attachment_has_public_upload_copy\(\s*\$attachment_id\s*\)\s*\{[^{}]*foreach[^}]*\}[^}]*\}[^}]*\}/i',
    $public_func,
    $content
);

// 3. Modify attachment_has_private_storage_copy
$private_func = <<<'EOD'
private function attachment_has_private_storage_copy( $attachment_id ) {
		if ( isset( $this->attachment_private_copy_cache[ $attachment_id ] ) ) {
			return $this->attachment_private_copy_cache[ $attachment_id ];
		}

		$has_copy = false;
		foreach ( $this->attachment_relative_paths( $attachment_id ) as $relative_path ) {
			if ( $this->private_file_path_if_exists( $relative_path ) ) {
				$has_copy = true;
				break;
			}
		}

		$this->attachment_private_copy_cache[ $attachment_id ] = $has_copy;
		return $has_copy;
	}

	private function clear_attachment_storage_cache( $attachment_id ) {
		unset( $this->attachment_public_copy_cache[ $attachment_id ] );
		unset( $this->attachment_private_copy_cache[ $attachment_id ] );
	}
EOD;

$content = preg_replace(
    '/private function attachment_has_private_storage_copy\(\s*\$attachment_id\s*\)\s*\{[^{}]*foreach[^}]*\}[^}]*\}[^}]*\}/i',
    $private_func,
    $content
);

// 4. In sync_attachment_storage_for_current_mode, clear cache
$content = preg_replace(
    '/private function sync_attachment_storage_for_current_mode\(\s*\$attachment_id,\s*\$metadata = null\s*\)\s*\{/i',
    "private function sync_attachment_storage_for_current_mode( \$attachment_id, \$metadata = null ) {\n\t\t\$this->clear_attachment_storage_cache( \$attachment_id );",
    $content
);

file_put_contents($file, $content);
echo "Done.";
