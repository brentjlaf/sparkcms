<?php
$faviconSetting = $settings['favicon'] ?? '';
if (is_string($faviconSetting) && $faviconSetting !== '' && preg_match('#^https?://#i', $faviconSetting)) {
    $favicon = $faviconSetting;
} elseif (!empty($settings['favicon'])) {
    $favicon = $scriptBase . '/CMS/' . ltrim($settings['favicon'], '/');
} else {
    $favicon = $themeBase . '/images/favicon.png';
}
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <!-- Metas & Morweb CMS Assets -->
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">

        <!-- Favicon -->
        <link rel="shortcut icon" href="<?php echo htmlspecialchars($favicon); ?>" type="image/x-icon"/>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,300;0,400;0,600;0,700;1,400&family=PT+Serif:ital,wght@0,400;0,700;1,400;1,700&display=swap" rel="stylesheet">

        <!-- Preload Vendor Stylesheets -->
        <link rel="preload" as="style" crossorigin="anonymous" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css"/>

        <!-- Vendor Stylesheets -->
        <link nocache="nocache" rel="stylesheet" crossorigin="anonymous" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css"/>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

        <!-- Stylesheets -->
        <link nocache="nocache" rel="stylesheet" href="<?php echo $themeBase; ?>/css/root.css?v=mw3.2"/>
        <link nocache="nocache" rel="stylesheet" href="<?php echo $themeBase; ?>/css/skin.css?v=mw3.2"/>
        <link nocache="nocache" rel="stylesheet" href="<?php echo $themeBase; ?>/css/override.css?v=mw3.2"/>
    </head>
    <body>

