<?php
if (!function_exists('renderMenu')) {
    function renderMenu($items, $isDropdown = false)
    {
        foreach ($items as $it) {
            $hasChildren = !empty($it['children']);
            if ($hasChildren) {
                echo '<li class="nav-item dropdown">';
                echo '<a class="nav-link dropdown-toggle" href="' . htmlspecialchars($it['link']) . '"' . (!empty($it['new_tab']) ? ' target="_blank"' : '') . ' role="button" data-bs-toggle="dropdown" aria-expanded="false">' . htmlspecialchars($it['label']) . '</a>';
                echo '<ul class="dropdown-menu">';
                renderMenu($it['children'], true);
                echo '</ul>';
            } else {
                echo '<li class="nav-item' . ($isDropdown ? '' : '') . '">';
                echo '<a class="nav-link" href="' . htmlspecialchars($it['link']) . '"' . (!empty($it['new_tab']) ? ' target="_blank"' : '') . '>' . htmlspecialchars($it['label']) . '</a>';
            }
            echo '</li>';
        }
    }
}

if (!function_exists('renderFooterMenu')) {
    function renderFooterMenu($items, $linkClass = 'nav-link text-light px-2')
    {
        foreach ($items as $it) {
            echo '<li class="nav-item">';
            echo '<a class="' . $linkClass . '" href="' . htmlspecialchars($it['link']) . '"' . (!empty($it['new_tab']) ? ' target="_blank"' : '') . '>' . htmlspecialchars($it['label']) . '</a>';
            echo '</li>';
        }
    }
}
