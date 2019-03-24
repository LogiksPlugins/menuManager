<?php
/*
 * This class contains the Privilege/Permission/Role Model based permission system
 * for all the logical inputs within Logiks Framework
 * 
 * Author: Bismay Kumar Mohapatra bismay4u@gmail.com
 * Version: 1.0
 */
if(!defined('ROOT')) exit('No direct script access allowed');

class RoleModelSimulator {
  
  protected $roleID = -1;
  protected $roleName = -1;
  
  protected $roleModel = [];
  protected $rolesGlobal = [];
  protected $scopeMap = [];
  protected $roleScope = [];
  
  protected $guid="guest";
  
	
  public function __construct($priviName, $priviID) {
    $this->roleID = $priviID;
    $this->roleName = $priviName;

    $this->guid = $_SESSION['SESS_GUID'];
  }
  
  
	//Load all scoperules into Session
	protected function loadScopeRules() {
		$this->rolesGlobal[SITENAME]=[];
		$this->scopeMap[SITENAME]=[];
		
		$scopeFiles=[
					ROOT.CFG_FOLDER."masters/roles.json"=>"GLOBALS",
					ROOT.CFG_FOLDER."jsonConfig/roles.json"=>"GLOBALS",
					APPROOT.APPS_CONFIG_FOLDER."roles.json"=>"SITE",
				];
		
		$scopeArr=[];$scopeMap=[];
		foreach($scopeFiles as $scf=>$typ) {
			if(file_exists($scf)) {
				try {
					$scArr=json_decode(file_get_contents($scf),true);
					if(is_array($scArr)) {
						if(!isset($scArr['GLOBALS'])) $scArr['GLOBALS']=[];
						if(!isset($scArr[SITENAME])) $scArr[SITENAME]=[];
						
						if(isset($scArr["scopemap"])) {
							$scopeMap=array_merge($scopeMap,$scArr["scopemap"]);
						}

						$scArr=array_merge($scArr['GLOBALS'],$scArr[SITENAME]);

						$scopeArr=array_merge($scopeArr,$scArr);
					}
				} catch(Exception $e) {

				}
			}
		}
		$this->scopeMap[SITENAME]=$scopeMap;
		$this->rolesGlobal[SITENAME]=$scopeArr;
	}
	
	//Gets the scope name for the given module
	protected function getScopeModule($module) {
		if(!isset($this->scopeMap[SITENAME])) {
			RoleModelSimulator::loadScopeRules();
		}
		if(isset($this->scopeMap[SITENAME][$module])) {
			return $this->scopeMap[SITENAME][$module];
		}
		return $module;
	}
	
	//Generates all role scopes for users, modules etc by default
	protected function getGlobalScopes() {
		if(!isset($this->rolesGlobal[SITENAME])) {
			RoleModelSimulator::loadScopeRules();
		}
		return $this->rolesGlobal[SITENAME];
	}
	
	//Checks the global policy that came with application and returns
	// -1 Not defined in the global scope
	//  0	Global scope is found, but no matching condition, so not allowed
	//  1	Allowed in global policy
	public function checkGlobals($policyStr,$reloadScopes=false) {
		if(isset($this->roleID) && $this->roleID<=ROLE_PRIME) {
			return 1;
		}

		if($reloadScopes) {
			unset($this->rolesGlobal[SITENAME]);
		}
		$gScopes=RoleModelSimulator::getGlobalScopes();

		$policyStr=str_replace(".MAIN","",$policyStr);
		$policyStr=str_replace(".MASTER","",$policyStr);
		$policyStr=str_replace(".INDEX","",$policyStr);
		$policyStr=str_replace(".CORE","",$policyStr);
		$policyStr=str_replace(".ACCESS","",$policyStr);
// 		$policyStr=str_replace(".PAGES","",$policyStr);
		
		if(isset($gScopes[strtolower($policyStr)])) {
			$policyStr=strtolower($policyStr);
		}

		if(isset($gScopes[current(explode(".",$policyStr))])) {
			$policyStr=current(explode(".",$policyStr));
		}

		if(isset($gScopes[$policyStr])) {
			if(!isset($gScopes[$policyStr]['access_control'])) {
				$gScopes[$policyStr]['access_control']="public";
			}
			switch($gScopes[$policyStr]['access_control']) {
				case "public":
					return 1;
				break;
				case "private":
					if(isset($gScopes[$policyStr]['users'])) {
						if(in_array($_SESSION['SESS_USER_ID'],$gScopes[$policyStr]['users'])) {
							return 1;
						}
					} elseif(isset($gScopes[$policyStr]['privileges'])) {
						if(in_array($this->roleName,$gScopes[$policyStr]['privileges'])) {
							return 1;
						} elseif(in_array(RoleModelSimulator::getPrivilegeHash(),$gScopes[$policyStr]['privileges'])) {
							return 1;
						}
					} else {
						return 0;
					}
				break;
			}
			return 0;
		} else {
			return -1;
		}
	}
	
	//Checks if a single activity exists for the provided scope/module
	//ie if the scope is required
	public function checkScope($module,$actionType="ACCESS") {
		$actionType=strtoupper($actionType);
		
		$activity="MAIN";
		$moduleOriginal=$module;
		$module=RoleModelSimulator::getScopeModule($module);
		$module=explode(".",$module);
		if(count($module)>1) {
			$activity=str_replace("{$moduleOriginal}.{$moduleOriginal}.","{$moduleOriginal}.",implode(".",array_slice($module,1)).".{$activity}");
			$module=$module[0];
		} else {
			$module=implode(".",$module);
		}
		
		$moduleArr=explode(".",$module);
		if(count($moduleArr)>1) {
			$module=$moduleArr[0];
			array_shift($moduleArr);
			$activity=strtoupper(implode(".",$moduleArr));
		}

		$actionType=strtoupper($actionType);
		$activity=strtoupper($activity);

		if(explode(".",$activity)>1) {
			$activity=str_replace(".MAIN","",$activity);
		}
		
		$policyStr=[$module,$activity,$actionType];
		
		$policyStr=implode(".",$policyStr);
		$globalStatus=RoleModelSimulator::checkGlobals($policyStr);
// 		var_dump($globalStatus);
    
    if($globalStatus>0) {
			return true;
		}
		
		$privilegehash=RoleModelSimulator::getPrivilegeHash();
		$roleHash=md5(strtoupper($module.$activity.$actionType.$privilegehash));
		
		if(isset($this->roleScope[$roleHash])) {
			return $this->roleScope[$roleHash];
		}

		$this->roleScope[$roleHash]=0;

		$privilegehash=RoleModelSimulator::getPrivilegeHash();
		$sql=_db(true)->_selectQ(_dbTable("rolemodel",true),"count(*) as cnt")
				->_where(array("site"=>SITENAME,"category"=>$actionType,"module"=>$module,"allow"=>"true", "privilegehash"=>$privilegehash))
				->_where(['guid'=>$this->guid]);
// 		exit($sql->_SQL());
		$r=_dbQuery($sql,true);
		$d=_dbData($r,true);
		_dbFree($r,true);

		if($d[0]['cnt']>0) {
			$this->roleScope[$roleHash]=2;
		} else {
			RoleModelSimulator::registerRole($module,$activity);
			$this->roleModel[$roleHash]=(getConfig("ROLEMODEL_DEFAULT_DENY")?false:true);
		}
		
		return $this->roleScope[$roleHash];
	}

	public function checkRole($module,$activity,$actionType="ACCESS") {
		$moduleOriginal=$module;
		$module=RoleModelSimulator::getScopeModule($module);
		$module=explode(".",$module);
		if(count($module)>1) {
			$activity=str_replace("{$moduleOriginal}.{$moduleOriginal}.","{$moduleOriginal}.",implode(".",array_slice($module,1)).".{$activity}");
			$module=$module[0];
		} else {
			$module=implode(".",$module);
		}
		$activity=preg_replace("/{$module}\./","",$activity);
		if($activity==null || strlen($activity)<=0) $activity="MAIN";

		$actionType=strtoupper($actionType);
		$activity=strtoupper($activity);

		if(explode(".",$activity)>1) {
			$activity=str_replace(".MAIN","",$activity);
		}

		$policyStr=[$module,$activity,$actionType];
		$policyStr=implode(".",$policyStr);
		$globalStatus=RoleModelSimulator::checkGlobals($policyStr);
// 		var_dump($globalStatus);
		
		if($globalStatus>0) {
			return true;
		}
		
		$privilegehash=RoleModelSimulator::getPrivilegeHash();
		$roleHash=md5(strtoupper($module.$activity.$actionType.$privilegehash));
		
		if(isset($this->roleModel[$roleHash])) {
			return $this->roleModel[$roleHash];
		}

		$sql=_db(true)->_selectQ(_dbTable("rolemodel",true),"id,allow,role_type,count(*) as cnt")
				->_where(array("site"=>SITENAME,"category"=>$actionType,"module"=>$module,"activity"=>$activity,"privilegehash"=>$privilegehash))
				->_where(['guid'=>$this->guid])
				->_orderby("id desc");

		$r=_dbQuery($sql,true);
		if($r) {
			$data=_dbData($r,true);
			_dbFree($r,true);
			$data=$data[count($data)-1];
			if($data['cnt']==0) {
				RoleModelSimulator::registerRole($module,$activity,$actionType);
				$this->roleModel[$roleHash]=(getConfig("ROLEMODEL_DEFAULT_DENY")?false:true);
				return (getConfig("ROLEMODEL_DEFAULT_DENY")?false:true);
			} else {
				$this->roleModel[$roleHash]=($data['allow']=="true")?true:false;
				return $this->roleModel[$roleHash];
			}
		}
		
		return (!getConfig("ROLEMODEL_DEFAULT_DENY"));
	}
	public function registerRole($module,$activity,$actionType="ACCESS") {
    return true;
	}
  
  protected function generateActivity($module, $activity) {
    $activity=preg_replace("/".strtolower($module)."\./","",strtolower($activity));
    return $activity;
  }
	
	//Clears all the cache in session
	public function cleanRoleModelCache() {
		if(isset($this->roleModel)) {
			unset($this->roleModel);
		}
		if(isset($this->roleScope)) {
			unset($this->roleScope);
		}
		if(isset($this->rolesGlobal)) {
			unset($this->rolesGlobal);
		}
	}
	
	//Generates and returns the Required Privilege Hash for the Current Loggedin User
	public function getPrivilegeHash() {
		return md5($this->roleID.$this->roleName);
	}
}
?>