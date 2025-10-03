<?php
if (!function_exists('renderMenu')) {
    function renderMenu($items, $isDropdown = false)
    {
        foreach ($items as $it) {
            $hasChildren = !empty($it['children']);

            $liClasses = $isDropdown ? ['dropdown-item'] : ['nav-item'];
            $linkClasses = $isDropdown ? ['dropdown-item'] : ['nav-link'];
            $linkAttributes = [];

            if ($hasChildren) {
                if ($isDropdown) {
                    $liClasses[] = 'dropdown-submenu';
                    $liClasses[] = 'dropend';
                    $linkClasses[] = 'dropdown-toggle';
                } else {
                    $liClasses[] = 'dropdown';
                    $linkClasses[] = 'dropdown-toggle';
                }
                $linkAttributes[] = 'role="button"';
                $linkAttributes[] = 'data-bs-toggle="dropdown"';
                $linkAttributes[] = 'aria-expanded="false"';
            }

            if (!empty($it['new_tab'])) {
                $linkAttributes[] = 'target="_blank"';
            }

            $liClassAttribute = ' class="' . implode(' ', $liClasses) . '"';
            $linkClassAttribute = ' class="' . implode(' ', $linkClasses) . '"';
            $linkAttributeString = empty($linkAttributes) ? '' : ' ' . implode(' ', $linkAttributes);

            echo '<li' . $liClassAttribute . '>';
            echo '<a' . $linkClassAttribute . ' href="' . htmlspecialchars($it['link']) . '"' . $linkAttributeString . '>' . htmlspecialchars($it['label']) . '</a>';

            if ($hasChildren) {
                echo '<ul class="dropdown-menu">';
                renderMenu($it['children'], true);
                echo '</ul>';
            }

            echo '</li>';
        }
    }
}

if (!function_exists('renderFooterMenu')) {
    function renderFooterMenu($items)
    {
        foreach ($items as $it) {
            echo '<li class="nav-item">';
            echo '<a class="nav-link text-light px-2" href="' . htmlspecialchars($it['link']) . '"' . (!empty($it['new_tab']) ? ' target="_blank"' : '') . '>' . htmlspecialchars($it['label']) . '</a>';
            echo '</li>';
        }
    }
}
