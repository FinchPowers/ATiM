<?php

class ParentToDerivativeSampleControl extends InventoryManagementAppModel {
	
	var $belongsTo = array(
		'ParentSampleControl' => array(
			'className'   => 'InventoryManagement.SampleControl',
			 'foreignKey'  => 'parent_sample_control_id'),
		'DerivativeControl' => array(
			'className'   => 'InventoryManagement.SampleControl',
			 'foreignKey'  => 'derivative_sample_control_id'));  	
	
	function getActiveSamples(){
		$data = $this->find('all', array('conditions' => array('flag_active' => 1), 'recursive' => -1));
		$relations = array();
		//arrange the data
		foreach($data as $unit){
			$key = $unit["ParentToDerivativeSampleControl"]["parent_sample_control_id"];
			$value = $unit["ParentToDerivativeSampleControl"]["derivative_sample_control_id"];
			if(!isset($relations[$key])){
				$relations[$key] = array();
			}
			$relations[$key][] = $value;
		}
		
		//start from the top and find the active samples
		return self::getActiveIdsFromRelations($relations, "");
	}
	
	private static function getActiveIdsFromRelations($relations, $current_check){
		$active_ids = array('-1');	//If no sample
		if(array_key_exists($current_check, $relations)) {
			foreach($relations[$current_check] as $sample_id){
				if($current_check != $sample_id && $sample_id != 'already_parsed'){
					$active_ids[] = $sample_id;
					if(isset($relations[$sample_id]) && !in_array('already_parsed', $relations[$sample_id])){
						$relations[$sample_id][] = 'already_parsed';
						$active_ids = array_merge($active_ids, self::getActiveIdsFromRelations($relations, $sample_id));
					}
				}
			}
		}
		return array_unique($active_ids);
	}
	
	/**
	 * Gets the lab book control id that can be use by a derivative
	 * @param int $parent_sample_ctrl_id
	 * @param int $children_sample_ctrl_id
	 * return int lab book control id on success, false if it's not found
	 */
	public function getLabBookControlId($parent_sample_ctrl_id, $children_sample_ctrl_id){
		$lab_book_ctrl_id = array_values($this->find('list', array(
				'fields' => array('ParentToDerivativeSampleControl.lab_book_control_id'), 
				'conditions' => array(
					'ParentToDerivativeSampleControl.parent_sample_control_id' => $parent_sample_ctrl_id,
					'ParentToDerivativeSampleControl.derivative_sample_control_id' => $children_sample_ctrl_id
		))));
		return empty($lab_book_ctrl_id[0]) ? false : $lab_book_ctrl_id[0];	
	}
}

?>
