<?php
class StructurePermissibleValuesCustom extends AppModel {

	var $name = 'StructurePermissibleValuesCustom';

	var $belongsTo = array(       
		'StructurePermissibleValuesCustomControl' => array(           
			'className'    => 'StructurePermissibleValuesCustomControl',            
			'foreignKey'    => 'control_id'));
	
	private static $instance = null;
	protected static $lang = null;
	
	static protected function getLanguage(){
		if(self::$lang == null){
			$tmp_l10n = new L10n();
			$tmp_l10n_map = $tmp_l10n->map();
			self::$lang = isset($tmp_l10n_map[$_SESSION['Config']['language']]) ? $tmp_l10n_map[$_SESSION['Config']['language']]: '';
		}
		return self::$lang;
	}
		
	function getCustomDropdown(array $args){
		$control_name = null;
		if(sizeof($args) == 1) { 
			$control_name = $args['0'];
		}

		$lang = self::getLanguage();
		if(!$lang) $lang = 'en';
		
		if(self::$instance == null){
			self::$instance = new StructurePermissibleValuesCustom();
			self::$instance->cacheQueries = true;
		}
		$conditions = array('StructurePermissibleValuesCustomControl.name' => $control_name);
		$data = self::$instance->find('all', array('conditions' => $conditions, 'order' => array('StructurePermissibleValuesCustom.display_order', 'StructurePermissibleValuesCustom.'.$lang)));
		$result = array("defined" => array(), "previously_defined" => array());
		if(empty($data)){ 
			return $result; 
		}
		
		$result = array("defined" => array(), "previously_defined" => array());
		foreach($data as $data_unit){
			$value = $data_unit['StructurePermissibleValuesCustom']['value'];
			$translated_value = (isset($data_unit['StructurePermissibleValuesCustom'][$lang]) && (!empty($data_unit['StructurePermissibleValuesCustom'][$lang])))? $data_unit['StructurePermissibleValuesCustom'][$lang]: $value;
			if($data_unit['StructurePermissibleValuesCustom']['use_as_input']){
				$result['defined'][$value] = $translated_value;
			}else{
				$result['previously_defined'][$value] = $translated_value;
			}
		}
		if($data[0]['StructurePermissibleValuesCustom']['display_order'] == 0){
			//sort alphabetically
			natcasesort($result['defined']);
			natcasesort($result['previously_defined']);
		}
		
		return $result;
	}
	
	function getTranslatedCustomDropdownValue($control_name, $value){
		$lang = self::getLanguage();
	
		if(self::$instance == null){
			self::$instance = new StructurePermissibleValuesCustom();
			self::$instance->cacheQueries = true;
		}
		$conditions = array(
			'StructurePermissibleValuesCustomControl.name' => $control_name,
			'StructurePermissibleValuesCustom.value' => $value
		);
		$data = self::$instance->find('first', array('conditions' => $conditions));
		if(empty($data)){
			return false;
		}
		return (isset($data['StructurePermissibleValuesCustom'][$lang]) && (!empty($data['StructurePermissibleValuesCustom'][$lang])))? $data['StructurePermissibleValuesCustom'][$lang]: $value;
	}
	
	function afterSave($created, $options = Array()){
		$control_id = null;
		if(isset($this->data['StructurePermissibleValuesCustom']['control_id'])){
			$control_id = $this->data['StructurePermissibleValuesCustom']['control_id'];
		} else if($this->id) {
			$control_id = $this->find('first', array('conditions' => array('StructurePermissibleValuesCustom.id' => $this->id), 'fields' => array('StructurePermissibleValuesCustom.control_id')));
			$control_id = $control_id['StructurePermissibleValuesCustom']['control_id'];
		}
		if($control_id){
			$values_counter = $this->find('count', array('conditions' => array('StructurePermissibleValuesCustom.control_id' => $control_id)));
			$values_used_as_input_counter = $this->find('count', array('conditions' => array('StructurePermissibleValuesCustom.control_id' => $control_id, 'StructurePermissibleValuesCustom.use_as_input' => '1')));
			$StructurePermissibleValuesCustomControl = AppModel::getInstance('', 'StructurePermissibleValuesCustomControl');
			$this->tryCatchQuery("UPDATE structure_permissible_values_custom_controls SET values_counter = $values_counter, values_used_as_input_counter = $values_used_as_input_counter WHERE id = $control_id;");
		}
		parent::afterSave($created, $options);
	}
	
}