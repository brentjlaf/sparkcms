<?php
// File: picker.php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/data.php';
require_once __DIR__ . '/../../includes/sanitize.php';
require_login();

$base = rtrim(sanitize_text($_GET['base'] ?? ''), '/');
$mediaFile = __DIR__ . '/../../data/media.json';
$media = read_json_file($mediaFile);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Select Image</title>
    <link rel="stylesheet" href="<?php echo htmlspecialchars($base . '/CMS/spark-cms.css'); ?>">
</head>
<body>
    <div class="media-picker">
        <?php foreach ($media as $m): if (($m['type'] ?? '') !== 'images') continue; ?>
        <div class="media-picker__item" data-file="<?php echo htmlspecialchars($base . '/' . $m['file'], ENT_QUOTES); ?>">
            <img class="media-picker__thumbnail" src="<?php echo htmlspecialchars($base . '/' . ($m['thumbnail'] ?: $m['file'])); ?>" alt="<?php echo htmlspecialchars($m['name']); ?>">
            <div class="media-picker__name"><?php echo htmlspecialchars($m['name']); ?></div>
        </div>
        <?php endforeach; ?>
    </div>
    <script>
        document.addEventListener('click', function(e){
            var item = e.target.closest('.media-picker__item');
            if(item && window.opener && window.opener.__selectImageFromPicker){
                window.opener.__selectImageFromPicker(item.dataset.file);
                window.close();
            }
        });
    </script>
</body>
</html>
