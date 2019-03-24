<?php
if(!defined('ROOT')) exit('No direct script access allowed');

include_once __DIR__."/RoleModelSimulator.inc";

switch($_REQUEST["action"]) {
  case "previewMenu":
    loadModuleLib("navigator","api");
    
    $menuid="default";
    if(isset($_REQUEST['menugroup']) && $_REQUEST['menugroup']) {
      $menuid = $_REQUEST['menugroup'];
    }
    
    if(!isset($_REQUEST['role']) || strlen($_REQUEST['role'])<=0) {
      $_REQUEST['role'] = $_SESSION['SESS_PRIVILEGE_ID'];
    }
    
    if($_REQUEST['role']<0) {
      //$_REQUEST['role'] = -1;
      $_REQUEST['rolehash'] = -1;
      $_REQUEST['roleid'] = -1;

      echo "<ul class='tree'>";
      getMenuContent($menuid);
      echo "</ul>";
    } else {
      $roleData=_db(true)->_selectQ(_dbTable("privileges",true),"id,name,site,remarks,blocked")->_where(array(
          "blocked"=>'false',
          "id"=>$_REQUEST['role']
        ));
      //exit($sql->_SQL());
      $roleData = $roleData->_GET();

      if(!$roleData || count($roleData)<=0) {
        echo "<span>Role Level not found</span>";
      } else {
        $_REQUEST['role'] = $roleData[0]['name'];
        $_REQUEST['roleid'] = $roleData[0]['id'];
        $_REQUEST['rolehash'] = md5($roleData[0]['id'].$roleData[0]['name']);

        echo "<ul class='tree'>";
        getMenuContent($menuid);
        echo "</ul>";
      }
    }
    break;
}

function getMenuContent($menuSrc,$barType=false) {
    $menuTree = getMenuTree($menuSrc);
    if(!$menuTree) return;
  
    foreach ($menuTree as $category=>$menuSet) {
      if(count($menuSet)<=0 || strlen($category)<=0) continue;
      $hash=md5($category);
      $category = _ling($category);
      $html="<li class='menuGroup'>";
      $html.="<a href='#' aria-expanded='false'>$category (".count($menuSet).")<span class='fa arrow'></span></a>";
      $html.="<ul aria-expanded='false' class='secondary collapse'>";

      $html1="";
      foreach ($menuSet as $key => $menu) {
        //$menu['title']=_ling($menu['title']);
        if(is_numeric($key)) {
          $menu['title']=_ling($menu['title']);
          $menu['tips']=_ling($menu['tips']);
          $more=[];
          if($menu['target']!=null && strlen($menu['target'])>0) {
            $more[]="target='{$menu['target']}'";
          }
          if($menu['class']!=null && strlen($menu['class'])>0) {
            $more[]="class='menuItem {$menu['class']}'";
          } else {
            $more[]="class='menuItem'";
          }
          if($menu['category']!=null && strlen($menu['category'])>0) {
            $more[]="data-category='{$menu['category']}'";
          }
          if($menu['tips']!=null && strlen($menu['tips'])>0) {
            $more[]="title='{$menu['tips']}'";
          }

          if($menu['iconpath']!=null && strlen($menu['iconpath'])>0) {
            $html1.="<li><a href='{$menu['link']}' ".implode(" ", $more)."><i class='menuIcon {$menu['iconpath']}'></i>&nbsp; {$menu['title']}</a></li>";
          } else {
            $html1.="<li><a href='{$menu['link']}' ".implode(" ", $more).">{$menu['title']}</a></li>";
          }
        } else {
          $keyS=toTitle($key);
          $keyS=_ling($keyS);
          $html.="<li class='menuGroup'>";
          $html.="<a href='#' aria-expanded='false'>$keyS (".count($menu).")<span class='fa arrow'></span></a>";
          $html.="<ul aria-expanded='false' class='secondary collapse'>";

          foreach ($menu as $key1 => $menu1) {
            $menu1['title']=_ling($menu1['title']);
            $more=[];
            if($menu1['target']!=null && strlen($menu1['target'])>0) {
              $more[]="target='{$menu1['target']}'";
            }
            if($menu1['class']!=null && strlen($menu1['class'])>0) {
              $more[]="class='menuItem {$menu1['class']}'";
            } else {
              $more[]="class='menuItem'";
            }
            if($menu1['category']!=null && strlen($menu1['category'])>0) {
              $more[]="data-category='{$menu1['category']}'";
            }
            if($menu1['tips']!=null && strlen($menu1['tips'])>0) {
              $more[]="title='{$menu1['tips']}'";
            }

            if($menu1['iconpath']!=null && strlen($menu1['iconpath'])>0) {
              $html.="<li><a href='{$menu1['link']}' ".implode(" ", $more)."><i class='menuIcon {$menu1['iconpath']}'></i>&nbsp; {$menu1['title']}</a></li>";
            } else {
              $html.="<li><a href='{$menu1['link']}' ".implode(" ", $more).">{$menu1['title']}</a></li>";
            }
          }

          $html.="</ul>";
          $html.="</li>";
        }
      }
      $html.=$html1;
      $html.="</ul>";
      $html.="</li>";

      echo $html;
    }
}

function getMenuTree($menuid, $menuFolder=false) {
  if($menuid=="suites") $menuid="default";
  
  $menuTree1=_db()->_selectQ("do_links","*",[
    "blocked"=>"false"
  ])->_whereRAW("FIND_IN_SET('default',menuid) AND site IN ('".SITENAME."','*') AND device IN ('*','pc')")
    ->_orderBy("weight ASC");
  
  switch($_REQUEST['role']) {
    case -1://no permission control at all
      $menuTree1=$menuTree1->_whereRAW("(privilege='*' AND (to_check IS NULL OR length(to_check)<=0))");
      break;
    case -2://scope controlled
      $menuTree1=$menuTree1->_whereRAW("((to_check IS NOT NULL AND length(to_check)>0) OR link='#')");
      break;
    default:
      $menuTree1=$menuTree1->_whereRAW("(find_in_set('{$_REQUEST['role']}',privilege) OR find_in_set('{$_REQUEST['rolehash']}',privilege) OR privilege='*')");
  }

  $menuTree1=$menuTree1->_GET();

  $menuTree1 = processMenuArray($menuTree1);

  if($menuFolder) {
    $menuTree2=generateNavigationFromDir(APPROOT."misc/menus/","app");
  } else {
    $menuTree2 = [];
  }

  $menuTree=array_merge_recursive($menuTree1,$menuTree2);

  foreach ($menuTree as $category=>$menuSet) {
    foreach ($menuSet as $key => $menu) {
      if(isset($menu['category']) && $menu['category']!=null && strlen($menu['category'])>0) {
        unset($menuTree[$category][$key]);
        $menuTree[$category][$menu['category']][$key]=$menu;
      }
    }
  }
  return $menuTree;
}

function processMenuArray($data) {
		if(!is_array($data) || empty($data)) return [];
		//printArray($data);exit();
  
    $data=processMenuRules($data);
//     printArray($data);exit();

		$menuList=[];
		$fData=[];
		foreach ($data as $id => $item) {
			$menuList[$item['id']]=$item;
			if($item['menugroup']=="/" || strlen($item['menugroup'])==0) {
				if(!isset($fData[$item['title']])) {
					$fData[$item['title']]=[];
				}
			}
		}

		foreach ($menuList as $id => $item) {
			$group=$item['menugroup'];
			if(is_numeric($group)) {
				if(isset($menuList[$group])) $group=$menuList[$group]['title'];
				else $group="/";
			}

			if(strlen($item['link'])>0 && ($item['link']!="#" || $item['link']!="##")) {
				$item['link']=_link($item['link']);
			}

			if($group!="/") {
				if(!isset($fData[$group])) $fData[$group]=[];
				$fData[$group][$item['id']]=$item;
			}
		}
//     printArray($fData);exit();
		return $fData;
}

function processMenuRules($data) {
		if(!is_array($data) || empty($data)) return [];
		//"module","dbtable",(TODO "page","dbcolumn")
		foreach ($data as $key => $menuData) {
			if(!isset($menuData['rules']) && isset($menuData['to_check'])) {
				$menuData['rules']=$menuData['to_check'];
			}
			if(isset($menuData['rules'])) {
				$flds=explode(",",$menuData['rules']);
				foreach($flds as $toCheck) {
					$ar=explode("#",$toCheck);
					if(count($ar)>=2) {
						switch ($ar[0]) {
							case 'module':
								if(!checkModule($ar[1])) {
									unset($data[$key]);
									break;
								}
								if(!checkMenuUserScope($ar[1])) {
									unset($data[$key]);
									break;
								}
								break;
							case 'scope':case 'policy':case 'privileges':case 'users':
								$arr=explode(".",$ar[1]);
								if(count($arr)>=2) {
									if(!checkMenuUserRoles($arr[0],$ar[1])) {
										unset($data[$key]);
										break;
									}
								} else {
									if(!checkMenuUserScope($ar[1])) {
										unset($data[$key]);
										break;
									}
								}
								break;
							case 'dbtable':
								if(!array_key_exists($ar[1], $this->dataSources)) {
									unset($data[$key]);
									break;
								}
								break;
						}
					}
				}
			}
		}
		return $data;
}

function checkMenuUserScope($module) {
  if($_REQUEST['role']==-1) return false;
  elseif($_REQUEST['role']==-2) return true;
  else {
    $rms = new RoleModelSimulator($_REQUEST['role'],$_REQUEST['roleid']);
    return $rms->checkScope($module);
  }
}
  
function checkMenuUserRoles($module,$activity,$actionType="ACCESS") {
  if($_REQUEST['role']==-1) return false;
  elseif($_REQUEST['role']==-2) return true;
  else {
    $rms = new RoleModelSimulator($_REQUEST['role'],$_REQUEST['roleid']);
    return $rms->checkRole($module,$activity,$actionType);
  }
}
?>