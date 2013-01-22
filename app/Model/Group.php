<?php
class Group extends AppModel {

	var $actsAs = array('Acl' => array('requester'));
	
	var $hasMany = array('User'); 
	
	function parentNode() {    
		return null;
	}
	
	function summary( $variables=array() ) {
		$return = false;
		
		if ( isset($variables['Group.id']) ) {
			$result = $this->find('first', array('conditions'=>array('Group.id'=>$variables['Group.id'])));
			$return = array(
				'menu'				=> array( NULL, $result['Group']['name'] ),
				'title'				=> array( NULL, $result['Group']['name'] ),
				'data'				=> $result,
				'structure alias'	=> 'groups' 
			);
		}
		
		return $return;
	}

	function bindToPermissions(){
		$this->bindModel(array('hasOne' => array(
			'Aro' => array(
				'className' => 'Aro',
				'foreignKey' => 'foreign_key',
				'conditions' => 'Aro.model="Group"'
			)
		)));
		
		$this->Aro->unbindModel(
			array('hasAndBelongsToMany' => array('Aco'))
		);
		
		$this->Aro->bindModel(
			array('hasMany' => array(
				'Permission' => array(
					'className' => 'Permission',
					'foreign_key'	=>'aco_id'
					)
				)
			)
		);
	}
	
	/**
	 * Checks if at least one permission for that group is granted
	 * @param $group_id
	 */
	function hasPermissions($group_id){
		$data = $this->find('first', array('joins' => array(
			array(
				'table' => 'aros',
				'alias' => 'aros',
				'type' => 'inner',
				'conditions' => array('Group.id = aros.foreign_key', 'aros.model="Group"')
			),array(
				'table' => 'aros_acos',
					'alias' => 'aros_acos',
					'type' => 'inner',
					'conditions' => array('aros.id = aros_acos.aro_id', 'OR' => array('aros_acos._create' => 1, 'aros_acos._read' => 1, 'aros_acos._update' => 1, 'aros_acos._delete' => 1))		
			)), 'conditions' => array('Group.id' => $group_id)
		));
		return !empty($data);
	}
	
	function getList(){
		return $this->find('list', array('fields' => array('Group.name'), 'order' => array('Group.name')));
	}
}
