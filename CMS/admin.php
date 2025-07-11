<?php
// File: admin.php
require_once __DIR__ . '/includes/auth.php';
require_login();

$pagesFile = __DIR__ . '/data/pages.json';
$pages = [];
if (file_exists($pagesFile)) {
    $pages = json_decode(file_get_contents($pagesFile), true) ?: [];
}

$settingsFile = __DIR__ . '/data/settings.json';
$settings = [];
if (file_exists($settingsFile)) {
    $settings = json_decode(file_get_contents($settingsFile), true) ?: [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars(($settings['site_name'] ?? 'SparkCMS') . ' Admin Dashboard'); ?></title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css">
    <script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>
    <script src="modal-utils.js"></script>
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
                        <div class="nav-icon">📊</div>
                        <div class="nav-text">Dashboard</div>
                    </div>
                </div>

                <div class="nav-section">
                    <div class="nav-section-title">Content</div>
                    <div class="nav-item" data-section="pages">
                        <div class="nav-icon">📄</div>
                        <div class="nav-text">Pages</div>
                    </div>
                    <div class="nav-item" data-section="blogs">
                        <div class="nav-icon">📝</div>
                        <div class="nav-text">Blogs</div>
                    </div>
                </div>

                <div class="nav-section">
                    <div class="nav-section-title">Site</div>
                    <div class="nav-item" data-section="media">
                        <div class="nav-icon">🖼️</div>
                        <div class="nav-text">Media Library</div>
                    </div>
                    <div class="nav-item" data-section="menus">
                        <div class="nav-icon">📑</div>
                        <div class="nav-text">Menus</div>
                    </div>
                    <div class="nav-item" data-section="forms">
                        <div class="nav-icon">📋</div>
                        <div class="nav-text">Forms</div>
                    </div>
                    <div class="nav-item" data-section="maps">
                        <div class="nav-icon">🗺️</div>
                        <div class="nav-text">Maps</div>
                    </div>
                </div>

                <div class="nav-section">
                    <div class="nav-section-title">Administration</div>
                    <div class="nav-item" data-section="users">
                        <div class="nav-icon">👥</div>
                        <div class="nav-text">Users</div>
                    </div>
                    <div class="nav-item" data-section="settings">
                        <div class="nav-icon">⚙️</div>
                        <div class="nav-text">Settings</div>
                    </div>
                </div>

                <div class="nav-section">
                    <div class="nav-section-title">System</div>
                    <div class="nav-item" data-section="analytics">
                        <div class="nav-icon">📈</div>
                        <div class="nav-text">Analytics</div>
                    </div>
                    <div class="nav-item" data-section="logs">
                        <div class="nav-icon">📋</div>
                        <div class="nav-text">Logs</div>
                    </div>
                    <div class="nav-item" data-section="backup">
                        <div class="nav-icon">💾</div>
                        <div class="nav-text">Backup</div>
                    </div>
                    <div class="nav-item" data-section="import">
                        <div class="nav-icon">📥</div>
                        <div class="nav-text">Import/Export</div>
                    </div>
                </div>

                <div class="nav-section">
                    <div class="nav-item" data-section="logout">
                        <div class="nav-icon">🚪</div>
                        <div class="nav-text"><a href="logout.php" style="color: inherit; text-decoration: none;">Logout</a></div>
                    </div>
                </div>
            </nav>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Top Bar -->
            <div class="top-bar">
                <div class="page-title" id="pageTitle">Dashboard</div>
                <div class="top-bar-actions">
                    <div class="search-box">
                        <input type="text" class="search-input" placeholder="Search...">
                        <div class="search-icon">🔍</div>
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
    if(section==="logout"){ window.location="logout.php"; return; }
    $(".nav-item").removeClass("active");
    $(this).addClass("active");
    $("#pageTitle").text($(this).find(".nav-text").text());
    loadModule(section);
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
  loadModule("dashboard");
});
    </script>
</div>

<footer class="footer">
    © 2025 SparkCMS
</footer>

</body>
</html>
