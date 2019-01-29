<?php
if(!defined('ROOT')) exit('No direct script access allowed');

$basePath = __DIR__."/panel/";
$report=$basePath."report.json";
$form=$basePath."form.json";

loadModule("datagrid");

if(!isset($_REQUEST['MENUID'])) $_REQUEST['MENUID']="default";

$dataMenus=_db()->_selectQ(_dbTable("links"),"menuid as title,count(*) as max",["menuid"=>[",","notlike"]])->_groupBy("menuid")->_GET();

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
?>

<div class='col-xs-12 col-md-12 col-lg-12'>
	<div class='row'>
		<?php
			printDataGrid($report,$form,$form,["slug"=>"subtype/type/refid","glink"=>_link("modules/menuManager","a=2")."&MENUID={$_REQUEST['MENUID']}","add_record"=>"Add","add_class"=>'btn btn-info'],"app");
		?>
	</div>
</div>
<style>
.control-toolbar select.form-control {
    width: 200px;
    height: 97%;
    margin-right: 10px;
    margin-left: -10px;
}
</style>
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
</script>