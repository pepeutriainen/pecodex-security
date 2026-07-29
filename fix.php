<?php
$file = 'assets/media-organizer.js';
$content = file_get_contents($file);
$content = preg_replace('/draggable:\s*(false|\'false\')\s*,/i', '', $content);
$content = preg_replace("/\\$\\(\\s*'<span \/>',\\s*\\{([^}]*?'class':\\s*'pgm-mo-folder-drag-handle'[^}]*?)\\}\\s*\\)/is", "\$('<span />', { \$1 }).attr('draggable', 'false')", $content);
$content = preg_replace("/\\$\\(\\s*'<img \/>',\\s*\\{([^}]*?)\\}\\s*\\)/is", "\$('<img />', { \$1 }).attr('draggable', 'false')", $content);
file_put_contents($file, $content);
echo "Done.";
