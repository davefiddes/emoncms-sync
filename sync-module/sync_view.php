<?php global $path,$settings,$route; ?>
<style>
.syncprogress {
    background-color:#7bc3e2;
    border: 1px solid #29abe2;
    color:#fff;
    font-size:10px;
    padding:2px;
}
</style>


  <h2>Emoncms Sync: <span id="page"></span></h2>

  <p>The module can be used to download or upload data to or from a remote emoncms installation.</p>
  <p>Start by entering the remote emoncms installation location in the <i>host</i> field (e.g https://emoncms.org). Then enter the <i>username</i> and <i>password</i> of the account you wish to link to.</p>
  <p>Download or upload specific feeds as required.</p>
  <p><b>Note:</b> Data upload is not yet enabled on emoncms.org.</p>
  <br>
  <?php if ($settings["redis"]["enabled"]) { ?>

  <div class="input-prepend input-append">
    <span class="add-on">Host</span><input id="remote-host" type="text" value="https://emoncms.org">
    <span class="add-on">Username</span><input id="remote-username" type="text" style="width:150px" >
    <span class="add-on">Password</span><input id="remote-password" type="password" style="width:150px" >
    <button id="remote-save" class="btn">Connect</button>
  </div>

  <!--
  <div class="input-prepend input-append" style="float:left">
  <span class="add-on">Select</span><button id="select-all" class="btn">All</button><button id="select-none" class="btn">None</button>
  </div>
  -->

  <div id="time" style="float:right; padding-top:10px; padding-right:20px">Next update: 0s</div>

  <br>
  <div class="input-prepend input-append">
  <button id="download-all" class="btn">Download All</button>
  <button id="upload-all" class="btn">Upload All</button>
  </div>

  <div class="alert alert-info"></div>

  <table class="table hide feed-view">
      <tr><th>Location</th><th>Feed Tag</th><th>Feed Name</th><th>Start time</th><th>Interval</th><th></th><th></th></tr>
      <tbody id="all_feed_datas"></tbody>
  </table>

  <div class="input-view hide">
      <p>Download remote emoncms inputs <button id="download-inputs" class="btn">Download</button></p>
      <pre id="input-output"></pre>
  </div>

  <div class="dashboard-view hide">
      <p>Download remote emoncms dashboards <button id="download-dashboards" class="btn">Download</button></p>
      <pre id="dashboard-output"></pre>
  </div>
  
  <?php } else { ?>

  <div class="alert alert-warning"><b>Error:</b> Redis is not installed or enabled. Please ensure that redis is installed on your system and then enabled in settings.php.</div>

  <?php } ?>

<script>

var subaction = "<?php echo $route->subaction; ?>";
if (!subaction || subaction=="") subaction = "feeds";

var redis_enabled = <?php echo $settings["redis"]["enabled"]; ?>;
var path = "<?php echo $path; ?>";
//is remote var used and usefull ?
//var remote = false;
var feeds = [];
var next_update = 0;

var feeds_to_upload = [];
var feeds_to_download = [];
var feed_list_refresh_interval = false;

//given the json feeds list, generate the html string
function jsonfeedsTohtml(feeds)
{
    var out = [];
    feeds_to_upload = [];
    feeds_to_download = [];
    for (var tagname in feeds) {
    
      var action = "";
      var status = "";
      var feedlocation = "";
      var interval = 0;
      var start_time = 0;
      var tr = "<tr>";
        
      if (!feeds[tagname].local.exists && feeds[tagname].remote.exists) {
        feedlocation = "Remote"; 
        action = "<button class='btn btn-small download' tagname='"+tagname+"'><i class='icon-arrow-left' ></i> Download</button>";
            
        start_time = feeds[tagname].remote.start_time;
        interval = feeds[tagname].remote.interval;
        feeds_to_download.push(tagname)
      }
        
      if (feeds[tagname].local.exists && !feeds[tagname].remote.exists) {
        feedlocation = "Local";
        action = "<button class='btn btn-small upload' tagname='"+tagname+"'><i class='icon-arrow-right' ></i> Upload</button>";
            
        start_time = feeds[tagname].local.start_time;
        interval = feeds[tagname].local.interval;
        feeds_to_upload.push(tagname)
      }
        
      if (feeds[tagname].local.start_time==feeds[tagname].remote.start_time && feeds[tagname].local.interval==feeds[tagname].remote.interval) {
        feedlocation = "Both";
            
        start_time = feeds[tagname].local.start_time;
        interval = feeds[tagname].local.interval;
      }
        
      if (feeds[tagname].local.start_time==feeds[tagname].remote.start_time && feeds[tagname].local.interval==feeds[tagname].remote.interval) {
        if (feeds[tagname].local.npoints>feeds[tagname].remote.npoints) {
          tr = "<tr class='info'>";
                
          status = "Local ahead of Remote by "+(feeds[tagname].local.npoints-feeds[tagname].remote.npoints)+" points";
          action = "<button class='btn btn-small upload' tagname='"+tagname+"'><i class='icon-arrow-right' ></i> Upload</button>";
          feeds_to_upload.push(tagname)
          
        } else if (feeds[tagname].local.npoints<feeds[tagname].remote.npoints) {
          tr = "<tr class='warning'>";
                
          status = "Local behind Remote by "+(feeds[tagname].remote.npoints-feeds[tagname].local.npoints)+" points";
          action = "<button class='btn btn-small download' tagname='"+tagname+"'><i class='icon-arrow-left' ></i> Download</button>";
          feeds_to_download.push(tagname)
               
        } else {
          tr = "<tr class='success'>";
                
          status = "Local and Remote are the same";
          action = "";
        }
      }
        
      //out += "<td><input class='feed-select-checkbox' type=checkbox></td>";
        
      out.push(tr);
      out.push("<td>"+feedlocation+"</td>");
      out.push("<td>"+feeds[tagname].local.tag+"</td>");
      out.push("<td>"+feeds[tagname].local.name+"</td>");
        
      if (interval!=undefined) {
        out.push("<td>"+timeConverter(start_time)+"</td>");
      } else {
        out.push("<td>n/a</td>");
      }
        
      if (interval!=undefined) {
        out.push("<td>"+interval+"s</td>");
      } else {
        out.push("<td>n/a</td>");
      } 
        
      out.push("<td class='status' tagname='"+tagname+"'>"+status+"</td>");
        
      //out += "<td>"+feeds[tagname].local.start_time+":"+feeds[tagname].local.interval+":"+feeds[tagname].local.npoints+"</td>";
      out.push("<td>"+action+"</td>");
       
      //out += "<td>"+feeds[z].remote.start_time+":"+feeds[z].remote.interval+":"+feeds[z].remote.npoints+"</td>";
      //out += "<td><div class='syncprogress' style='width:"+Math.round(feeds[tagname].remote.npoints*0.0001)+"px'>"+feeds[tagname].local.start_time+":"+feeds[tagname].local.interval+":"+feeds[tagname].remote.npoints+"</div></td>";
      out.push("</tr>");
    }
    return out.join("");
    
}

//interrogate the API and update the list
//update the gloabl variable new_update
//update the global variable feeds
function syncList()
{
    next_update = 10;

    $(".alert").html("Connected. Fetching emoncms feeds...");
    $.ajax({ 
      url: path+"sync/feed-list", 
      dataType: 'json', 
      async: true, 
      success(result){
        out=jsonfeedsTohtml(result);
        feeds=result;
        $("#all_feed_datas").html(out);
        $(".feed_view").show();
        $(".alert").hide();
      }
    });
}

// interrogate the API and Load all the remote details
function remoteLoad()
{
    $.ajax({ 
      url: path+"sync/remote-load", 
      dataType: 'json', 
      async: true, 
      success(result){
        if (result.success!=undefined && !result.success) {
            remote=false; 
            $(".alert").hide();
        } else {
            //remote=result;
            $(".alert").hide();
            $("#remote-host").val(result.host);
            $("#remote-username").val(result.username);
            if (subaction=="feeds") {
              $(".feed-view").show();
              syncList();
              
              clearInterval(feed_list_refresh_interval);
              feed_list_refresh_interval = setInterval(syncList,10000);
            }
            if (subaction=="inputs") {
              $(".input-view").show();
            }
            if (subaction=="dashboards") {
              $(".dashboard-view").show();
            }
        }
      },
      error(xhr) { 
        var errorMessage = xhr.status + ": " + xhr.statusText;
        alert("Error - " + errorMessage);
      }
    });
}

$("#page").html(subaction.charAt(0).toUpperCase() + subaction.slice(1));

if (redis_enabled) {
    $(".alert").show().html("Connecting to remote emoncms server...");
    remoteLoad();
    
}


$("#remote-save").click(function(){
    var host = $("#remote-host").val();
    var username = $("#remote-username").val();
    var password = encodeURIComponent($("#remote-password").val());
    
    $(".feed-view").hide();
    $(".alert").show().html("Connecting to remote emoncms server...");
    
    clearInterval(feed_list_refresh_interval);
    
    $.ajax({ 
        type: "POST",
        url: path+"sync/remove-save", 
        data: "host="+host+"&username="+username+"&password="+password,
        dataType: 'json',
        async: true, 
        success(result){
            if (result.success) {
                //remote = result;
                // feed list scan
                remoteLoad();
            } else {
                alert(result.message);
            }
        } 
    });
});

$("#select-all").click(function(){
    $(".feed-select-checkbox").each(function(){
        $(this)[0].checked = true;
    });
});

$("#select-none").click(function(){
    $(".feed-select-checkbox").each(function(){
        $(this)[0].checked = false;
    });
});

// ----------------------------------------------------------
// Download Feeds
// ----------------------------------------------------------

// Download single feed
$("#all_feed_datas").on("click",".download",function(){
    download_feed($(this).attr("tagname"));
});

// Download all feeds
$("#download-all").click(function(){
    for (var z in feeds_to_download) download_feed(feeds_to_download[z]);
});

function download_feed(tagname) {
    $(".status[tagname='"+tagname+"']").html("Downloading...");
    let f = feeds[tagname].remote;
    var request = "name="+f.name+"&tag="+f.tag+"&remoteid="+f.id+"&interval="+f.interval+"&engine="+f.engine;
    $.ajax({
        url: path+"sync/download", 
        data: request,
        dataType: 'json',
        async: true, 
        success(result){
            if (result.success) {
                // success
            } else { 
                alert(result.message); 
                $(".status[tagname='"+tagname+"']").html("");
            }
        } 
    });
}

// ----------------------------------------------------------
// Upload Feeds
// ----------------------------------------------------------

// Upload single feed
$("#all_feed_datas").on("click",".upload",function(){
    upload_feed($(this).attr("tagname"));
});

// Upload all feeds
$("#upload-all").click(function(){
    for (var z in feeds_to_upload) upload_feed(feeds_to_upload[z]);
});

function upload_feed(tagname){
    $(".status[tagname='"+tagname+"']").html("Uploading...");
    let f = feeds[tagname].local;
    var request = "name="+f.name+"&tag="+f.tag+"&localid="+f.id+"&interval="+f.interval+"&engine="+f.engine;
    $.ajax({
        url: path+"sync/upload", 
        data: request,
        dataType: 'json',
        async: true, 
        success(result){
            if (result.success) {
                // success
            } else { 
                alert(result.message); 
                $(".status[tagname='"+tagname+"']").html("");
            }
        } 
    });
}

// ----------------------------------------------------------

$("#download-inputs").click(function(){
    $.ajax({
        url: path+"sync/download-inputs", 
        dataType: 'text',
        async: true, 
        success(result){
            $("#input-output").html(result);
        } 
    });
});

$("#download-dashboards").click(function(){
    $.ajax({
        url: path+"sync/download-dashboards", 
        dataType: 'text',
        async: true, 
        success(result){
            $("#dashboard-output").html(result);
        } 
    });
});



function timeConverter(UNIX_timestamp){
  var a = new Date(UNIX_timestamp * 1000);
  var months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
  var year = a.getFullYear();
  var month = months[a.getMonth()];
  var date = a.getDate();
  var hour = a.getHours();
  if (hour<10) hour = "0"+hour;
  var min = a.getMinutes();
  if (min<10) min = "0"+min;
  var sec = a.getSeconds();
  if (sec<10) sec = "0"+sec;
  var time = date + ' ' + month + ' ' + year + ', ' + hour + ':' + min + ':' + sec ;
  return time;
}

setInterval(ticker,1000);

function ticker() {
    next_update --;
    $("#time").html("Next update: "+next_update+"s");
}
</script>
