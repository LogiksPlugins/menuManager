<?php
if(!defined('ROOT')) exit('No direct script access allowed');

$basePath = __DIR__."/panel/";
$report=$basePath."report.json";
$form=$basePath."form.json";

loadModule("datagrid");

if(!isset($_REQUEST['MENUID'])) $_REQUEST['MENUID']="default";

$dbkey = "app";
if(isAdminSite(CMS_SITENAME)) {
  $dbkey = true;
  $report=$basePath."report_cms.json";
  $form=$basePath."form_cms.json";
  if($_REQUEST['MENUID']=="default") {
    $_REQUEST['MENUID']="cms";
  }
}

$dataMenus=_db($dbkey)->_selectQ(_dbTable("links",$dbkey),"menuid as title,count(*) as max",["menuid"=>[",","notlike"]])->_groupBy("menuid")->_GET();

if(!function_exists("getGroupDropdown")) {
    function getGroupDropdown($autoSelect=true) {
        $html=[];
        $sql=_db(true)->_selectQ(_dbTable("users_group",true),"*");
    	
        $sqlData=$sql->_GET();
    
    	if($autoSelect) {
        foreach($sqlData as $p) {
          if($p['group_name']==$_SESSION['SESS_GROUP_NAME'])
            $html[]="<option value='{$p['group_name']}' selected>{$p['group_name']}</option>";
          else
            $html[]="<option value='{$p['group_name']}'>{$p['group_name']}</option>";
        }
    	} else {
        foreach($sqlData as $p) {
          $html[]="<option value='{$p['group_name']}'>{$p['group_name']}</option>";
        }
    	}
        
    	return implode("",$html);
    }
}

if(!function_exists("getPrivilegeDropdown")) {
  function getPrivilegeDropdown($id=false) {
        $list=getPrivilegeList();
        $html=[];
        if($id) {
            foreach($list as $p) {
                $html[]="<option value='{$p['id']}'>{$p['name']}</option>";
            }
        } else {
            foreach($list as $p) {
                $html[]="<option value='{$p['name']}'>{$p['name']}</option>";
            }
        }
        return implode("",$html);
    }
}

echo _css("menuManager");
echo _js("menuManager");
?>
<div class='col-xs-12 col-md-12 col-lg-12'>
	<div class='row'>
		<?php
			printDataGrid($report,$form,$form,["slug"=>"subtype/type/refid","glink"=>_link("modules/menuManager","a=2")."&MENUID={$_REQUEST['MENUID']}","add_record"=>"Add","add_class"=>'btn btn-info'],$dbkey);
		?>
	</div>
</div>
<div id='previewMenu' class='preview-menu container fade' onselectstart="return false;" style='right:-300px;'>
  <div class='row'>
    <select id='userRoleModelList' class='form-control select' onchange="loadMenuList()">
      <option value='<?=$_SESSION['SESS_PRIVILEGE_ID']?>'>My Privilege</option>
      <option value='-1'>No Permission Configured</option>
      <option value='-2'>Scope Controlled</option>
      <?=getPrivilegeDropdown(true)?>
    </select>
  </div>
  <div id='menuTree' class='menuTree'>
    
  </div>
</div>
<script>
var menuGroups=<?=json_encode($dataMenus)?>;
$(function() {
    $(".control-toolbar").prepend("<select id='menuGroups' class='form-control select pull-left'></select>");
    $.each(menuGroups,function(k,v) {
        if(v.title=="<?=$_REQUEST['MENUID']?>") {
            $("#menuGroups").append("<option value='"+v.title+"' selected>"+v.title.toUpperCase()+" ["+v.max+"]</option>");
        } else {
            $("#menuGroups").append("<option value='"+v.title+"'>"+v.title.toUpperCase()+" ["+v.max+"]</option>");
        }
    });
    $("#menuGroups").change(function(e) {
        uri="<?=_link("modules/menuManager","a=1")?>"+"&MENUID="+$(this).val();
        window.location=uri;
    });
});
function previewMenu() {
  if($("#previewMenu").hasClass("in")) {
    $("#previewMenu").removeClass("in").css("right","-300px");
  } else {
    $("#previewMenu").addClass("in").css("right","0px");
    loadMenuList();
  }
}
function loadMenuList() {
  $("#menuTree").html("<div class='text-center'><br><br><i class='fa fa-spinner fa-spin'></i></div>");
  $("#menuTree").load(_service("menuManager","previewMenu","html")+"&role="+$("#userRoleModelList").val()+"&menugroup="+$("#menuGroups").val(), function() {
    $("li.menuGroup>a","#menuTree").click(function(e) {
          e.preventDefault();
          $(this).closest("li.menuGroup").find(">ul.collapse").toggleClass("in");
          return false;
        });
  });
}
</script>