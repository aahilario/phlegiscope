<!DOCTYPE html>
<html>
<head><?php wp_head(); ?></head>
<body>
  <!-- -->
  <div class="legiscope-debug legiscope-header-container">
    <!-- header-brick -->
    <div class="legiscope-debug" id="legiscope-header-brick">
      <h1>Boozeet!</h1>
      <span>Tracking Lawmakers' Work-in-Progress</span>
    </div>
    <!-- header-brick -->
  </div>
  <!-- -->
  <div class="legiscope-debug legiscope-main-content">
    <!-- -->
    <div class="legiscope-debug legiscope-main-panel-menubar" id="legiscope-main-menubar">
      <!-- -->
      <?php wp_nav_menu( array( 'theme_location' => 'legiscope-header-menu' ) ); ?>
      <!-- -->
      <form id="menubar-search" method="post" action="/search/keyword">
        <input type="text" class="menubar-input" id="search-string" name="s"/>
        <input type="hidden" id="menubar-search-meta" name="m" />
        <span id="search-wait"></span><span id="currenturl"></span>
      </form>
      <script type="text/javascript">
      jQuery(document).ready(function(){
        initialize_hot_search('[id=search-string]','/keyword/');
      });
      </script>
      <!-- -->
    </div>
    <!-- main-content -->
    <div class="legiscope-debug legiscope-main-panel" id="legiscope-main-content">

<div id="news-leads"></div>
<div id="subcontent"></div>

    </div>
    <!-- main-content -->
    <div class="legiscope-debug legiscope-main-panel" id="legiscope-sidebar">
      <!-- -->
      <div class="legiscope-debug legiscope-sidebar-widget">
        <div>
          <span>Recent Links</span>
<ul class="no-bullets">
<li>COA Report</li>
<li>Search results</li>
<li>Latest bills from the legislature.</li>
<li>Most recently pulled senate and house bills.</li>
<li>Tag cloud for most recent 100 bills.</li>
<li>RSS feed, iCal event service for committee meetings.</li>
</ul>
        </div>
      </div>
      <!-- -->
      <div class="legiscope-debug legiscope-sidebar-widget">
        <div>
          <span>Scope</span>
          <?php LegiscopeBase::emit_basemap(1.6); ?>
        </div>
      </div>
      <!-- -->
      <div class="legiscope-debug legiscope-sidebar-widget">
        <div>Widget Y</div>
      </div>
      <!-- -->
      <div class="legiscope-debug legiscope-sidebar-widget">
        <div>Widget Z</div>
      </div>
      <!-- -->
    </div>
    <!-- -->
  </div>
  <!-- -->
<?php wp_footer(); ?>
</body>
</html>
