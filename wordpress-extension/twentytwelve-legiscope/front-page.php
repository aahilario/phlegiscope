<!DOCTYPE html>
<html>
<head><?php wp_head(); ?></head>
<body>
  <!-- -->
  <div class="legiscope-debug legiscope-header-container">
    <!-- header-brick -->
    <div class="legiscope-debug" id="legiscope-header-brick">
      <h1>Legiscope</h1>
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
      <form id="menubar-search">
        <input type="text" class="menubar-input" id="menubar-search" />
      </form>
      <!-- -->
    </div>
    <!-- main-content -->
    <div class="legiscope-debug legiscope-main-panel" id="legiscope-main-content">

<h2>Republic Act 10175</h2>
<pre>
Latest bills from the legislature.
Most recently pulled senate and house bills.
Tag cloud for most recent 100 bills.
RSS feed, iCal event service for committee meetings.
</pre>

    </div>
    <!-- main-content -->
    <div class="legiscope-debug legiscope-main-panel" id="legiscope-sidebar">
      <!-- -->
      <div class="legiscope-debug legiscope-sidebar-widget">
        <div>
          <span>Recent Links</span>
          COA Report
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
