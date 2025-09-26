<?php
// File: admin.php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/data.php';
require_login();

$pagesFile = __DIR__ . '/data/pages.json';
$pages = get_cached_json($pagesFile);

$settingsFile = __DIR__ . '/data/settings.json';
$settings = get_cached_json($settingsFile);
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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="spark-cms.css">
                <link rel="shortcut icon" href="images/favicon.png" />

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
                    <div class="nav-section-title">Administration</div>
                    <div class="nav-item" data-section="users">
                        <div class="nav-icon"><i class="fas fa-users"></i></div>
                        <div class="nav-text">Users</div>
                    </div>
                    <div class="nav-item" data-section="settings">
                        <div class="nav-icon"><i class="fas fa-cog"></i></div>
                        <div class="nav-text">Settings</div>
                    </div>
                </div>

                <div class="nav-section">
                    <div class="nav-section-title">System</div>
                    <div class="nav-item" data-section="analytics">
                        <div class="nav-icon"><i class="fas fa-chart-line"></i></div>
                        <div class="nav-text">Analytics</div>
                    </div>
                    <div class="nav-item" data-section="seo">
                        <div class="nav-icon"><i class="fas fa-magnifying-glass-chart"></i></div>
                        <div class="nav-text">SEO</div>
                    </div>
                    <div class="nav-item" data-section="logs">
                        <div class="nav-icon"><i class="fas fa-clipboard-list"></i></div>
                        <div class="nav-text">Logs</div>
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
    var section=$(this).data("section");
    if(typeof section === 'string'){ section = section.trim(); }
    if(section==="logout"){ window.location="logout.php"; return; }
    $(".nav-item").removeClass("active");
    $(this).addClass("active");
    $("#pageTitle").text($(this).find(".nav-text").text());
    loadModule(section);
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
  loadModule("dashboard");
});
    </script>
</div>

<footer class="footer">
    © 2025 SparkCMS
</footer>

</body>
</html>
