<?php

class PreferencesController extends AdministrateAppController {
	
	var $name = 'Preferences';
	var $uses = array('User', 'Config');
	
	function index($group_id, $user_id ) {
		$this->Structures->set('preferences_lock,preferences');
		$this->set( 'atim_menu_variables', array('Group.id'=>$group_id,'User.id'=>$user_id) );
		
		// get USER data
		
			$this->request->data = $this->User->find('first',array('conditions'=>array('User.id'=>$user_id)));
			
		// get CONFIG data
		
			$config_results	= false;
		
			// get CONFIG for logged in user
			if ( $_SESSION['Auth']['User']['id'] ) {
				$config_results = $this->Config->find('first', array('conditions'=>'(bank_id="0" OR bank_id IS NULL) AND (group_id="0" OR group_id IS NULL) AND user_id="'.$user_id.'"'));
			}
			// if not logged in user, or user has no CONFIG, get CONFIG for GROUP level
			if ( $_SESSION['Auth']['User']['group_id'] && (!count($config_results) || !$config_results) ) {
				$config_results = $this->Config->find('first', array('conditions'=>'(bank_id="0" OR bank_id IS NULL) AND Config.group_id="'.$group_id.'" AND (user_id="0" OR user_id IS NULL)'));
			}
			// if not logged in user, or user has no CONFIG, get CONFIG for APP level
			if ( !count($config_results) || !$config_results ) {
				$config_results = $this->Config->find('first', array('conditions'=>'(bank_id="0" OR bank_id IS NULL) AND (group_id="0" OR group_id IS NULL) AND (user_id="0" OR user_id IS NULL)'));
			}
			
			$this->request->data['Config'] = $config_results['Config'];
	}
	
	function edit($group_id, $user_id ) {
		$this->Structures->set($_SESSION['Auth']['User']['id'] == $user_id ? 'preferences' : 'preferences_lock,preferences');
		$this->set( 'atim_menu_variables', array('Group.id'=>$group_id,'User.id'=>$user_id) );
		
		$config_id = NULL;
		
		// get CONFIG data
			
			$config_results	= false;
			
			// get CONFIG for logged in user
			if ( $user_id ) {
				$config_results = $this->Config->find('first', array('conditions'=>'(bank_id="0" OR bank_id IS NULL) AND (group_id="0" OR group_id IS NULL) AND user_id="'.$user_id.'"'));
				$config_id = $config_results['Config']['id']; // set ID for saves (add or edit)
			}
			// if not logged in user, or user has no CONFIG, get CONFIG for GROUP level
			if ( $group_id && (!$config_results || !count($config_results)) ) {
				$config_results = $this->Config->find('first', array('conditions'=>'(bank_id="0" OR bank_id IS NULL) AND Config.group_id="'.$group_id.'" AND (user_id="0" OR user_id IS NULL)'));
			}
			// if not logged in user, or user has no CONFIG, get CONFIG for APP level
			if ( (!$config_results || !count($config_results)) ) {
				$config_results = $this->Config->find('first', array('conditions'=>'(bank_id="0" OR bank_id IS NULL) AND (group_id="0" OR group_id IS NULL) AND (user_id="0" OR user_id IS NULL)'));
			}
		
		if ( !empty($this->request->data) ) {
			// Set the user and group to the id's of the selected user, not currently logged in user.
			$this->User->id = $user_id;
			$this->request->data['User']['id'] = $user_id; 
			$this->request->data['User']['group_id'] = $group_id; 
			$this->request->data['Group']['id'] = $group_id;
			
			$this->Config->id = $config_id;
			$this->request->data['Config']['bank_id'] = 0;
			$this->request->data['Config']['group_id'] = 0;
			$this->request->data['Config']['user_id'] = $user_id;

			$this->User->set($this->request->data);
			$this->Config->set($this->request->data);
			
			if($this->User->validates() && $this->Config->validates()) {
				if($this->User->save($this->request->data, false) && $this->Config->save($this->request->data, false)){
					$this->atimFlash( 'your data has been updated','/Administrate/Preferences/index/'.$group_id.'/'.$user_id );
				} else {
					$this->redirect( '/Pages/err_plugin_record_err?method='.__METHOD__.',line='.__LINE__, NULL, TRUE ); 
				}
			}
			
		} else {
			
			$this->request->data = $this->User->find('first',array('conditions'=>array('User.id'=>$user_id)));
			$this->request->data['Config'] = $config_results['Config'];
				
		}
	}
}

?>