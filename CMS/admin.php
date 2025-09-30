<?php
// File: admin.php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/data.php';
require_once __DIR__ . '/includes/settings.php';
require_login();

$pagesFile = __DIR__ . '/data/pages.json';
$pages = get_cached_json($pagesFile);

$settings = get_site_settings();
$adminFavicon = 'images/favicon.png';
$faviconSetting = $settings['favicon'] ?? '';
if (is_string($faviconSetting) && $faviconSetting !== '' && preg_match('#^https?://#i', $faviconSetting)) {
    $adminFavicon = $faviconSetting;
} elseif (!empty($settings['favicon'])) {
    $adminFavicon = ltrim($settings['favicon'], '/');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title><?php echo htmlspecialchars(($settings['site_name'] ?? 'SparkCMS') . ' Admin Dashboard'); ?></title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css">
    <script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>
    <script src="modal-utils.js"></script>
    <script src="notifications.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="spark-cms.css">
    <link rel="shortcut icon" href="<?php echo htmlspecialchars($adminFavicon); ?>" />

</head>
<body>
    <div class="admin-container">
        <!-- Sidebar -->
        <div class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <div class="sidebar-logo">
                <?php if (!empty($settings['logo'])): ?>
                    <img src="images/logo.png" alt="Logo" style="height:40px;">
                <?php else: ?>
                    <?php echo htmlspecialchars(($settings['site_name'] ?? 'SparkCMS') . ' Admin'); ?>
                <?php endif; ?>
                </div>
            </div>
            <nav class="sidebar-nav">
                <div class="nav-section">
                    <div class="nav-section-title">Overview</div>
                    <div class="nav-item active" data-section="dashboard">
                        <div class="nav-icon"><i class="fas fa-tachometer-alt"></i></div>
                        <div class="nav-text">Dashboard</div>
                    </div>
                </div>

                <div class="nav-section">
                    <div class="nav-section-title">Content</div>
                    <div class="nav-item" data-section="pages">
                        <div class="nav-icon"><i class="far fa-file-alt"></i></div>
                        <div class="nav-text">Pages</div>
                    </div>
                    <div class="nav-item" data-section="blogs">
                        <div class="nav-icon"><i class="fas fa-blog"></i></div>
                        <div class="nav-text">Blogs</div>
                    </div>
                    <div class="nav-item" data-section="calendar">
                        <div class="nav-icon"><i class="fas fa-calendar-alt"></i></div>
                        <div class="nav-text">Calendar</div>
                    </div>
                </div>

                <div class="nav-section">
                    <div class="nav-section-title">Site</div>
                    <div class="nav-item" data-section="media">
                        <div class="nav-icon"><i class="fas fa-images"></i></div>
                        <div class="nav-text">Media Library</div>
                    </div>
                    <div class="nav-item" data-section="menus">
                        <div class="nav-icon"><i class="fas fa-bars"></i></div>
                        <div class="nav-text">Menus</div>
                    </div>
                    <div class="nav-item" data-section="forms">
                        <div class="nav-icon"><i class="fas fa-clipboard"></i></div>
                        <div class="nav-text">Forms</div>
                    </div>
                </div>

                <div class="nav-section">
                    <div class="nav-section-title">E-commerce</div>
                    <div class="nav-item" data-section="ecommerce" data-view="dashboard" data-page-title="E-commerce · Dashboard">
                        <div class="nav-icon"><i class="fas fa-store"></i></div>
                        <div class="nav-text">Dashboard</div>
                    </div>
                    <div class="nav-item" data-section="ecommerce" data-view="products" data-page-title="E-commerce · Products">
                        <div class="nav-icon"><i class="fas fa-box"></i></div>
                        <div class="nav-text">Products</div>
                    </div>
                    <div class="nav-item" data-section="ecommerce" data-view="orders" data-page-title="E-commerce · Orders">
                        <div class="nav-icon"><i class="fas fa-receipt"></i></div>
                        <div class="nav-text">Orders</div>
                    </div>
                    <div class="nav-item" data-section="ecommerce" data-view="customers" data-page-title="E-commerce · Customers">
                        <div class="nav-icon"><i class="fas fa-user-friends"></i></div>
                        <div class="nav-text">Customers</div>
                    </div>
                    <div class="nav-item" data-section="ecommerce" data-view="reports" data-page-title="E-commerce · Reports">
                        <div class="nav-icon"><i class="fas fa-chart-column"></i></div>
                        <div class="nav-text">Reports</div>
                    </div>
                    <div class="nav-item" data-section="ecommerce" data-view="settings" data-page-title="E-commerce · Settings">
                        <div class="nav-icon"><i class="fas fa-sliders-h"></i></div>
                        <div class="nav-text">Settings</div>
                    </div>
                </div>

                <div class="nav-section">
                    <div class="nav-section-title">Administration</div>
                    <div class="nav-item" data-section="users">
                        <div class="nav-icon"><i class="fas fa-users"></i></div>
                        <div class="nav-text">Users</div>
                    </div>
                    <div class="nav-item" data-section="settings">
                        <div class="nav-icon"><i class="fas fa-cog"></i></div>
                        <div class="nav-text">Settings</div>
                    </div>
                    <div class="nav-item" data-section="import_export">
                        <div class="nav-icon"><i class="fas fa-file-import"></i></div>
                        <div class="nav-text">Import &amp; Export</div>
                    </div>
                </div>

                <div class="nav-section">
                    <div class="nav-section-title">System</div>
                    <div class="nav-item" data-section="analytics">
                        <div class="nav-icon"><i class="fas fa-chart-line"></i></div>
                        <div class="nav-text">Analytics</div>
                    </div>
                    <div class="nav-item" data-section="seo">
                        <div class="nav-icon"><i class="fas fa-search"></i></div>
                        <div class="nav-text">SEO</div>
                    </div>
                    <div class="nav-item" data-section="logs">
                        <div class="nav-icon"><i class="fas fa-clipboard-list"></i></div>
                        <div class="nav-text">Logs</div>
                    </div>
                    <div class="nav-item" data-section="speed">
                        <div class="nav-icon"><i class="fas fa-gauge-high"></i></div>
                        <div class="nav-text">Performance</div>
                    </div>
                    <div class="nav-item" data-section="accessibility">
                        <div class="nav-icon"><i class="fas fa-universal-access"></i></div>
                        <div class="nav-text">Accessibility</div>
                    </div>
                </div>

                <div class="nav-section">
                    <div class="nav-item" data-section="logout">
                        <div class="nav-icon"><i class="fas fa-sign-out-alt"></i></div>
                        <div class="nav-text"><a href="logout.php" style="color: inherit; text-decoration: none;">Logout</a></div>
                    </div>
                </div>
            </nav>
        </div>
        <div class="sidebar-overlay" id="sidebarOverlay"></div>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Top Bar -->
            <div class="top-bar">
                <button class="menu-toggle" id="menuToggle"><i class="fas fa-bars"></i></button>
                <div class="page-title" id="pageTitle">Dashboard</div>
                <div class="top-bar-actions">
                    <div class="search-box">
                        <input type="text" class="search-input" placeholder="Search...">
                        <div class="search-icon"><i class="fas fa-search"></i></div>
                    </div>
                    <div class="user-menu">
                        <div class="user-avatar">AD</div>
                        <div class="user-info">
                            <div class="user-name">Admin</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Content Area -->
            <div class="content-area" id="contentContainer"></div>
        </div>
    <script>
$(function(){
  function loadModule(section, params){
    var url = "modules/"+section+"/view.php";
    if(params){ url += "?" + params; }
    $("#contentContainer").load(url, function(){
      $("#contentContainer .content-section").addClass("active");
      $.getScript("modules/"+section+"/"+section+".js").fail(function(){});
    });
  }
  $(".nav-item").click(function(){
    var $item = $(this);
    var section=$item.data("section");
    if(typeof section === 'string'){ section = section.trim(); }
    if(section==="logout"){ window.location="logout.php"; return; }
    var view = $item.data("view");
    var params = null;
    if(typeof view === 'string' && view.trim() !== ''){
        params = $.param({view: view.trim()});
    }
    $(".nav-item").removeClass("active");
    $item.addClass("active");
    var customTitle = $item.data("page-title");
    if(typeof customTitle === 'string' && customTitle.trim() !== ''){
        $("#pageTitle").text(customTitle.trim());
    } else {
        $("#pageTitle").text($item.find(".nav-text").text());
    }
    loadModule(section, params);
    if(window.innerWidth <= 1024){
        $("#sidebar").removeClass("mobile-open");
        $("#sidebarOverlay").removeClass("active");
    }
  });

  $('.search-input').on('keypress', function(e){
    if(e.which===13){
      e.preventDefault();
      var q = $(this).val();
      if(q.trim()!==""){
        $(".nav-item").removeClass("active");
        loadModule('search','q='+encodeURIComponent(q));
      }
    }
  });

  $('#menuToggle').on('click', function(){
    $('#sidebar').toggleClass('mobile-open');
    $('#sidebarOverlay').toggleClass('active');
  });

  $('#sidebarOverlay').on('click', function(){
    $('#sidebar').removeClass('mobile-open');
    $(this).removeClass('active');
  });

  var moduleTitles = {
    search: 'Manage search index',
    sitemap: 'Review sitemap',
    import_export: 'Import & Export',
    calendar: 'Manage calendar data',
    ecommerce: 'E-commerce'
  };

  $(document).on('sparkcms:navigate', function(event, data){
    if(!data || typeof data.section !== 'string'){ return; }
    var section = data.section.trim();
    if(section === ''){ return; }

    var view = '';
    if(typeof data.view === 'string'){
      view = data.view.trim();
    }

    var selector = ".nav-item[data-section='" + section + "']";
    var $targetNav = view !== '' ? $(selector + "[data-view='" + view + "']") : $(selector).first();
    if(!$targetNav.length){
      $targetNav = $(selector).first();
    }

    var params = null;
    if(view !== ''){
      params = $.param({view: view});
    }

    if($targetNav.length){
      $(".nav-item").removeClass("active");
      $targetNav.addClass("active");

      var customTitle = $targetNav.data('page-title');
      if(typeof customTitle === 'string' && customTitle.trim() !== ''){
        $('#pageTitle').text(customTitle.trim());
      } else {
        $('#pageTitle').text($targetNav.find('.nav-text').text());
      }

      loadModule(section, params);

      if(window.innerWidth <= 1024){
        $('#sidebar').removeClass('mobile-open');
        $('#sidebarOverlay').removeClass('active');
      }
      return;
    }

    if(typeof loadModule !== 'function'){ return; }

    $(".nav-item").removeClass("active");

    var title = moduleTitles[section];
    if(!title){
      title = section.replace(/_/g, ' ');
      title = title.replace(/\b\w/g, function(char){ return char.toUpperCase(); });
    }
    $('#pageTitle').text(title);

    loadModule(section, params);

    if(window.innerWidth <= 1024){
      $('#sidebar').removeClass('mobile-open');
      $('#sidebarOverlay').removeClass('active');
    }
  });
  $(document).on('sparkcms:ecommerce:viewChange', function(event, payload){
    if(!payload || typeof payload.view !== 'string'){ return; }
    var view = payload.view.trim();
    if(view === ''){ return; }
    if($('#contentContainer #ecommerce').length === 0){ return; }
    var $navItems = $(".nav-item[data-section='ecommerce']");
    if(!$navItems.length){ return; }
    $navItems.removeClass('active');
    var $target = $(".nav-item[data-section='ecommerce'][data-view='" + view + "']");
    if($target.length){
      $target.addClass('active');
      var customTitle = $target.data('page-title');
      if(typeof customTitle === 'string' && customTitle.trim() !== ''){
        $('#pageTitle').text(customTitle.trim());
      } else {
        $('#pageTitle').text($target.find('.nav-text').text());
      }
    }
  });
  loadModule("dashboard");
});
    </script>
</div>

<footer class="footer">
    © 2025 SparkCMS
</footer>

</body>
</html>
