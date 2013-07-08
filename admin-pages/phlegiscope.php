<?php 
$menu_title = get_option('phlegiscope_menutitle');
echo <<<EOH
<div class="wrap">
  <div id="icon-options-general" class="icon32"><br/></div>
  <h2 class="legiscope">{$menu_title} Curator's Page</h2>
  <div class="access-bar">
    <span class="access-bar-container">Search <input id="keywords" type="text"/></span>
    <span class="access-bar-container">&nbsp;&nbsp;Proxy <input id="proxy" type="checkbox" value="1" /></span>
    <span class="access-bar-container">&nbsp;&nbsp;Cache <input id="cache" type="checkbox" value="1" /></span>
    <span class="access-bar-container">&nbsp;&nbsp;Pull <input id="seek" type="checkbox" value="1" /></span>
    <span class="access-bar-container">&nbsp;&nbsp;Spider <input id="spider" type="checkbox" value="1" /></span>
    <span class="access-bar-container">&nbsp;&nbsp;Preload <input id="preload" type="checkbox" value="1" /></span>
    <span class="access-bar-container">&nbsp;&nbsp;Update <input id="update" type="checkbox" value="1" /></span>
    <span class="access-bar-container" id="search-wait"></span>
    <span class="access-bar-container" id="time-delta"></span>
    <div class="contenttabs" id="contenttabs"></div>
  </div>
</div>

<div class="wrap">
	<script type="text/javascript">
	jQuery(document).ready(function(){
		jQuery('#keywords').val('');
		initialize_spider();
	});
	</script>
	<div class="poc">

		<div id="controlpanel" class="panels">
			<input id="siteURL" type="text" />
			<div id="permalinks" class="linkset">
				<ul class="link-cluster">
					<li><a class="legiscope-remote" href="http://www.gov.ph/section/legis/">Official Gazette</a></li>
					<li><a class="legiscope-remote" href="http://www.gov.ph/the-philippine-constitutions/">O.G. - Constitutions</a></li>
					<li><a class="legiscope-remote" href="http://www.senate.gov.ph">Senate</a></li>
					<li><a class="legiscope-remote" href="http://www.congress.gov.ph">Congress</a></li> 
					<li><a class="legiscope-remote" href="https://ireport.sec.gov.ph/iview/client_login.jsp">SEC iReport Login</a></li>
					<li><a class="legiscope-remote" href="https://ireport.sec.gov.ph/iview/onlineview.sx?subaction=loadFilter">SEC iReport Filter</a></li>
					<li><a class="legiscope-remote" href="https://ireport.sec.gov.ph/iview/logoutClient.sx?subaction=logout">SEC iReport Logout</a></li>
				</ul>
				<hr/>
			</div>
			<div id="linkset" class="linkset"></div>
		</div> <!-- controlpanel -->

		<div id="sitecontent" class="panels">
			<span id="metalink" style="display:none"></span>
			<div class="contentwindow hidden" id="responseheader"></div>
			<div class="contentwindow hidden" id="original"></div>
		</div>

	</div> <!-- poc -->


</div>
EOH;

$extra_links = <<<EOH

			<div class="contentwindow hidden" id="markup"></div>

        <li>- - - - - -</li>
        <li><a class="legiscope-remote" href="http://www.gmanetwork.com/news/">GMA News</a></li>
        <li><a class="legiscope-remote" href="http://www.gmanetwork.com/news/eleksyon2013/results/senator">Senators</a></li>
        <li><a class="legiscope-remote" href="http://www.gmanetwork.com/news/eleksyon2013/results/partylist">Party List</a></li>
        <li><a class="legiscope-remote" href="http://www.gmanetwork.com/news/eleksyon2013/results/local">Local</a></li>
        <li>- - - - - -</li>
        <li><a class="legiscope-remote" href="http://www.sec.gov.ph">Securities and Exchange Commission</a></li> 
        <li><a class="legiscope-remote" href="https://ireport.sec.gov.ph/iview/login.jsp">SEC iView</a></li> 
        <li><a class="legiscope-remote" href="https://ireport.sec.gov.ph/iview/client_login.jsp">SEC iReport Login</a></li>
        <li><a class="legiscope-remote" href="https://ireport.sec.gov.ph/iview/onlineview.sx?subaction=loadFilter">SEC iReport Filter</a></li>
        <li><a class="legiscope-remote" href="https://ireport.sec.gov.ph/iview/logoutClient.sx?subaction=logout">SEC iReport Logout</a></li>
        <li>- - - - - -</li>
        <li><a class="legiscope-remote" href="http://www.dbm.gov.ph">Department of Budget and Management</a></li> 
        <li><a class="legiscope-remote" href="http://www.denr.gov.ph">Department of Environment and Natural Resources</a></li> 
        <li><a class="legiscope-remote" href="http://emb.gov.ph">Environment Management Bureau</a></li> 
        <li><a class="legiscope-remote" href="http://www.comelec.gov.ph">COMELEC</a></li>
        <li><a class="legiscope-remote" href="http://www.comelec.gov.ph/?r=laws/CompleteList">COMELEC - RL</a></li>

EOH;
