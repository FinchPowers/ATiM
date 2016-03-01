<?php
/**
 * Application model for CakePHP.
 *
 * This file is application-wide model file. You can put all
 * application-wide model-related methods here.
 *
 * CakePHP(tm) : Rapid Development Framework (http://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 * @link          http://cakephp.org CakePHP(tm) Project
 * @package       app.Model
 * @since         CakePHP(tm) v 0.2.9
 * @license       MIT License (http://www.opensource.org/licenses/mit-license.php)
 */

App::uses('Model', 'Model');

/**
 * Application model for Cake.
 *
 * Add your application-wide methods in the class below, your models
 * will inherit them.
 *
 * @package       app.Model
 */
class AppModel extends Model {
	
	var $actsAs = array('Revision','SoftDeletable','MasterDetail');//It's important that MasterDetail be after Revision
	public static $auto_validation = null;//Validation for all models based on the table field length for char/varchar
	static public $accuracy_config = array();//tablename -> accuracy fields
	
	static public $writable_fields = array();//tablename -> flag suffix -> fields
	public $check_writable_fields = true;//whether to check writable fields or not (security check)
	public $writable_fields_mode = null;//add, edit, addgrid, editgrid, batchedit

	//The values in this array can trigger magic actions when applied to a field settings
	private static $magic_coding_icd_trigger_array = array(
			"CodingIcd10Who" => "/CodingIcd/CodingIcd10s/tool/who", 
			"CodingIcd10Ca" => "/CodingIcd/CodingIcd10s/tool/ca", 
			"CodingIcdo3Morpho" => "/CodingIcd/CodingIcdo3s/tool/morpho", 
			"CodingIcdo3Topo" => "/CodingIcd/CodingIcdo3s/tool/topo");
	
	public $pkey_safeguard = true;//whether to prevent data to be saved if the data array contains a pkey different than model->id
	
	private $registered_models;//use related to views
	
	/**
	 * @desc Used to store the previous model when a model is recreated for detail search
	 * @var SampleMaster
	 */
	var $previous_model = null;
	private static $locked_views_update = false;
	private static $cached_views_update = array();
	private static $cached_views_delete = array();
	private static $cached_views_insert = array();
	private static $cached_views_model = null; //some model, only provides accc
	
	const ACCURACY_REPLACE_STR = '%5$s(IF(%2$s = "c", %1$s, IF(%2$s = "d", CONCAT(SUBSTR(%1$s, 1, 7), %3$s), IF(%2$s = "m", CONCAT(SUBSTR(%1$s, 1, 4), %3$s), IF(%2$s = "y", CONCAT(SUBSTR(%1$s, 1, 4), %4$s), IF(%2$s = "h", CONCAT(SUBSTR(%1$s, 1, 10), %3$s), IF(%2$s = "i", CONCAT(SUBSTR(%1$s, 1, 13), %3$s), %1$s)))))))';
	
	/**
	 * @desc If $base_model_name and $detail_table are not null, a new hasOne relationship is created before calling the parent constructor.
	 * This is convenient for search based on master/detail detail table.
	 * @param unknown_type $id (see parent::__construct)
	 * @param unknown_type $table (see parent::__construct)
	 * @param unknown_type $ds (see parent::__construct) 
	 * @param string $base_model_name The base model name of a master/detail model
	 * @param string $detail_table The name of the table to use for detail
	 * @param AppModel $previous_model The previous model prior to that new creation (purely for convenience)
	 * @see parent::__construct
	 */
	function __construct($id = false, $table = null, $ds = null, $base_model_name = null, $detail_table = null, $previous_model = null) {
		if($detail_table != null && $base_model_name != null){
			$this->hasOne[$base_model_name.'Detail'] = array(
						'className'   => $detail_table,
					 	'foreignKey'  => strtolower($base_model_name).'_master_id',
					 	'dependent' => true);
			if($previous_model != null){
				$this->previous_model = $previous_model;
			}
		}
		parent::__construct($id, $table, $ds);
    }

    /**
     * Finds the uploaded files from the $data array. Update the $data array
     * with the name the stored file will have and returns the $mode_files
     * directive array to
     **/
    private function filter_move_files(&$data) {
        $move_files = array();
        if(!is_array($data)) {
            return $move_files;
        }
        
        //Keep data in memory to fix issue #3286: Unable to edit and save collection date when field 'acquisition_label' is hidden
		$this_data_tmp_backup = $this->data;
		
        $prev_data = $this->id ? $this->read() : null;
        $dir = Configure::read('uploadDirectory');
        foreach($data as $model_name => $fields){
            if (!is_array($fields)) {
                continue;
            }
            foreach ($fields as $field_name => $value) {
                if (is_array($value)) {
                    if (isset($value['name'])) {
                        if (!$value['size']) {
                            // no file
                            $data[$model_name][$field_name] = '';
                            continue;
                        }
                        if (!file_exists($value['tmp_name'])) {
                            die('Error with temporary file');
                        }
                        $target_name = $model_name.'.'.$field_name
                                       .'.%%key_increment%%.'.$value['name'];
                        
                        if ($prev_data[$model_name][$field_name]) {
                            // delete previous file
                            unlink($dir.'/'.$prev_data[$model_name][$field_name]);
                        }
                        $target_name = $this->getKeyIncrement('atim_internal_file', $target_name);
                        array_push($move_files, array('tmpName' => $value['tmp_name'],
                                                 'targetName' => $target_name));
                        $data[$model_name][$field_name] = $target_name;
                    }
                    else if (isset($value['option'])) {
                        if ($value['option'] == 'delete'
                            && $prev_data[$model_name][$field_name])
                        {
                            $data[$model_name][$field_name] = '';
                            unlink($dir.'/'.$prev_data[$model_name][$field_name]);
                        }
                        else {
                            unset($data[$model_name][$field_name]);
                        }
                    }
                }
            }
        }
		
        //Reset data to fix issue #3286: Unable to edit and save collection date when field 'acquisition_label' is hidden
        $this->data = $this_data_tmp_backup;	
        
        return $move_files;
    }
	
    /**
     * Takes the move_files array returned by filter_move_files and moves the
     * uploaded files to the configured directory with the set file name.
     **/
    private function move_files($move_files) {
		if($move_files) {
		    //make sure directory exists
		    $dir = Configure::read('uploadDirectory');
		    if(!is_dir($dir)) {
		        mkdir($dir);
		    }
    		foreach($move_files as $move_file) {
    		    $newName = $dir.'/'.$move_file['targetName'];
    		    move_uploaded_file($move_file['tmpName'], $newName);
    		}
        }
    }

	/**
	 * Override to prevent saving id directly with the array to avoid hacks
	 * @see Model::save()
	 */
	function save($data = null, $validate = true, $fieldList = array()){
		if($this->pkey_safeguard && ((isset($data[$this->name][$this->primaryKey]) && $this->id != $data[$this->name][$this->primaryKey])
				|| (isset($data[$this->primaryKey]) && $this->id != $data[$this->primaryKey]))
		){
			AppController::addWarningMsg('Pkey safeguard on model '.$this->name, true);
			AppController::getInstance()->redirect('/Pages/err_plugin_system_error?method='.__METHOD__.',line='.__LINE__, null, true);
			return false;
		}
		
		if(!$validate 
			&& !isset($data[$this->alias]['__validated__']) 
			&& !isset($data['__validated__'])
			&& !isset($data[$this->alias]['deleted'])
			&& !isset($data['deleted']) 
			&& Configure::read('debug') > 0
		){
			AppController::addWarningMsg('saving unvalidated data ['.$this->name.']', true);
		}
	
		if((!isset($data[$this->name]) || empty($data[$this->name])) 
		&& isset($this->Behaviors->MasterDetail->__settings[$this->name]['is_master_model']) 
		&& $this->Behaviors->MasterDetail->__settings[$this->name]['is_master_model']
		&& isset($data[$this->Behaviors->MasterDetail->__settings[$this->name]['detail_class']])) {
		    //Eventum 2619: When there is no master data, details aren't saved
		    //properly because cake core flushes them out.
		    //NL Comment See notes on eventum $data[$this->name]['-'] = "foo";
			$data[$this->name]['-'] = "foo";
		}
		
		$move_files = $this->filter_move_files($data);
		$result = parent::save($data, $validate, $fieldList);
		$this->move_files($move_files);

		return $result;
	}
	
	/**
	 * Checks Writable fields, sets trackability, manages floats ("," and ".") 
	 * and date strings.
	**/
	function beforeSave($options = array()){
		if($this->check_writable_fields){
			$this->checkWritableFields();
		}
		
		$this->setTrackability();
		$this->checkFloats();
		$this->registerModelsToCheck();
		
		return true;
	}
	
	/**
	 * Removes values not found into AppModel::$writable_fields[$this->table] 
	 * from the saved data set to prevent form hacking. Will use "add" or
	 * "edit" filter based on the presence (edit) or absence (add) of 
	 * $this->id. Use $this->writable_fields_mode to specify other modes.
	 */
	private function checkWritableFields(){
		if(isset(AppModel::$writable_fields[$this->table])){
			$writable_fields = null;
			if($this->writable_fields_mode){
				$writable_fields = isset(AppModel::$writable_fields[$this->table][$this->writable_fields_mode]) ? AppModel::$writable_fields[$this->table][$this->writable_fields_mode] : array();
			}else if($this->id){
				$writable_fields = isset(AppModel::$writable_fields[$this->table]['edit']) ? AppModel::$writable_fields[$this->table]['edit'] : array();
			}else{
				$writable_fields = isset(AppModel::$writable_fields[$this->table]['add']) ? AppModel::$writable_fields[$this->table]['add'] : array();
			}
			$writable_fields[] = 'modified';
			if($this->id){
				$writable_fields[] = $this->primaryKey;
			}else{
				$writable_fields[] = 'created';
			}
			if(isset(AppModel::$writable_fields[$this->table]['all'])){
				$writable_fields = array_merge(AppModel::$writable_fields[$this->table]['all'], $writable_fields);
			}
			if(isset(AppModel::$writable_fields[$this->table]['none'])){
				$writable_fields = array_diff($writable_fields, AppModel::$writable_fields[$this->table]['none']);
			}
			$schema_keys = array_keys($this->schema());
			$writable_fields = array_intersect($writable_fields, $schema_keys);
			$real_fields = isset($this->data[$this->name]) ? array_intersect(array_keys($this->data[$this->name]), $schema_keys) : array();
			$invalid_fields = array_diff($real_fields, $writable_fields);
			if(!empty($invalid_fields)){
				foreach($invalid_fields as $invalid_field){
					unset($this->data[$this->name][$invalid_field]);
				}
				if(Configure::read('debug') > 0){
					AppController::addWarningMsg('Non authorized fields have been removed from the data set prior to saving. ('.implode(',', $invalid_fields).')', true);
				}
			}
		}else if(Configure::read('debug') > 0 && isset($this->data[$this->name]) && !empty($this->data[$this->name])){
			AppController::addWarningMsg('No Writable fields defined for model '.$this->name.'.', true);
			$this->data[$this->name] = array();
		}
	}
	
	/**
	 * Sets created_by, modified_by, created and deleted fields.
	 */
	private function setTrackability(){
		if ( !isset($this->Session) || !$this->Session ){
			if( App::import('Model', 'CakeSession')){
				$this->Session = new CakeSession();
			}
		}
		
		if ( $this->id && $this->Session ) {
			// editing an existing entry with an existing session
			unset($this->data[$this->name]['created_by']);
			$this->data[$this->name]['modified_by'] = $this->Session->check('Auth.User.id') ? $this->Session->read('Auth.User.id') : 0;
		} else if ($this->Session ) {
			// creating a new entry with an existing session
			$this->data[$this->name]['created_by'] = $this->Session->check('Auth.User.id') ? $this->Session->read('Auth.User.id') : 0;
			$this->data[$this->name]['modified_by'] = $this->Session->check('Auth.User.id') ? $this->Session->read('Auth.User.id') : 0;
		} else if ( $this->id ) {
			// editing an existing entry with no session
			unset($this->data[$this->name]['created_by']);
			$this->data[$this->name]['modified_by'] = 0;
		} else {
			// creating a new entry with no session
			$this->data[$this->name]['created_by'] = 0;
			$this->data[$this->name]['modified_by'] = 0;
		}
		$this->data[$this->name]['modified'] = now();//CakePHP should do it... doens't work.
	}
	
	
	private function registerModelsToCheck(){
	    $this->registered_models = array();
	    if($this->registered_view && $this->id){
	        foreach($this->registered_view as $registered_view => $foreign_keys){
	            list($plugin_name, $model_name) = explode('.', $registered_view);
	            $model = AppModel::getInstance($plugin_name, $model_name);
	            $pkeys_to_check = array();
	            $pkeys_for_deletion = array();
	            foreach($foreign_keys as $foreign_key){
	                $at_least_one = false;
	                
	                foreach(explode("UNION ALL", $model::$table_query) as $query_part){
	                    if(strpos($query_part, $foreign_key) === false){
	                        continue;
	                    }
	                    $at_least_one = true;
	                    $table_query = str_replace('%%WHERE%%', 'AND '.$foreign_key.'='.$this->id, $query_part);
	
	                    $results = $this->tryCatchQuery($table_query);
	                    foreach($results as $result){
	                        $pkeys_for_deletion[] = current(current($result));
	                        if(method_exists($model, "getPkeyAndModelToCheck")){
	                            $pkeys_to_check[] = $model->getPkeyAndModelToCheck($result);
	                        }else{
	                            $pkeys_to_check[] = array(
	                                    'pkey' => current(current($result)),
	                                    'base_model' => $model->base_model);
	                        }
	                    }
	                }
	                if(!$at_least_one){
	                    throw new Exception("No queries part fitted with the foreign key ".$foreign_key);
	                }
	            }
	            if($pkeys_to_check){
	                $this->registered_models[] = array(
	                        'model' => $model,
	                        'pkeys_to_check' => $pkeys_to_check,
	                        'pkeys_for_deletion' => $pkeys_for_deletion,
	                );
	            }
	        }
	    }
	}
	
	
	private function updateRegisteredModels(){
	    foreach($this->registered_models as $registered_model){
	        //try to find the row
	        $model = $registered_model['model'];
			if(self::$locked_views_update){
				if(!isset(self::$cached_views_delete[$model->table])){
					self::$cached_views_delete[$model->table] = array();
				}
				if(!isset(self::$cached_views_delete[$model->table][$model->primaryKey])){
					self::$cached_views_delete[$model->table][$model->primaryKey] = array("pkeys_for_deletion" => array());
				}
				self::$cached_views_delete[$model->table][$model->primaryKey]["pkeys_for_deletion"] = array_merge(self::$cached_views_delete[$model->table][$model->primaryKey]["pkeys_for_deletion"], $registered_model['pkeys_for_deletion']);
			}else{
				$query = sprintf('DELETE FROM %s  WHERE %s IN (%s)', $model->table, $model->primaryKey, implode(',',$registered_model['pkeys_for_deletion']));	//To fix issue#2980: Edit Storage & View Update 
				$this->tryCatchquery($query);
			}
			foreach(explode("UNION ALL", $model::$table_query) as $query_part){
	            $ids = array();
	            $base_model = null;
	            for($i = count($registered_model['pkeys_to_check']) - 1; $i >= 0; -- $i){
                    $curr = $registered_model['pkeys_to_check'][$i];
	                if($base_model == null){
	                    //find the base model, once found don't get back in here
	                    if(strpos($query_part, $curr['base_model']) !== false){
	                        $base_model = $curr['base_model'];
	                    }else{
	                        continue;
	                    }
	                }
	                if($base_model == $curr['base_model']){
	                    array_push($ids, $curr['pkey']);
	                    $base_model = $curr['base_model'];
	                    unset($registered_model['pkeys_to_check'][$i]);
	                }
	            }
	            if($ids){
	            	if(self::$locked_views_update){
	            		if(!isset(self::$cached_views_insert[$model->table])){
	            			self::$cached_views_insert[$model->table] = array();
	            		}
	            		if(!isset(self::$cached_views_insert[$model->table][$base_model])){
	            			self::$cached_views_insert[$model->table][$base_model] = array();
	            		}
	            		if(!isset(self::$cached_views_insert[$model->table][$base_model][$query_part])){
	            			self::$cached_views_insert[$model->table][$base_model][$query_part] = array("ids" => array());
	            		}
	            		self::$cached_views_insert[$model->table][$base_model][$query_part]["ids"] = array_merge(self::$cached_views_insert[$model->table][$base_model][$query_part]["ids"], $ids);
	            	}else{
	            		$table_query = str_replace('%%WHERE%%', 'AND '.$base_model.'.id IN('.implode(", ", $ids).')', $query_part);
	          			$query = sprintf('INSERT INTO %s (%s)', $model->table, $table_query);
	       				$this->tryCatchquery($query);
	            	}
	                $registered_model['pkeys_to_check'] = array_values($registered_model['pkeys_to_check']); //reindex from 0
	            }
	        }
	    }
	}
	
	/*
		ATiM 2.0 function
		used instead of Model->delete, because SoftDelete Behaviour will always return a FALSE
	*/
	
	function atimDelete($model_id, $cascade = true){
		$this->id = $model_id;
		$this->registerModelsToCheck();
		
		// delete DATA as normal
		$this->addWritableField('deleted');
		$this->delete($model_id, $cascade);
		
		// do a FIND of the same DATA, return FALSE if found or TRUE if not found
		if($this->read()){
			return false; 
		}
		$this->updateRegisteredModels();
		return true; 
		
	}
	
	/*
		ATiM 2.0 function
		acts like find('all') but returns array with ID values as arrays key values
	*/
	
	function atim_list( $options=array() ) {
		
		$return = false;
		
		$defaults = array(
			'conditions'	=> NULL,
			'fields'			=> NULL,
			'order'			=> NULL,
			'group'			=> NULL,
			'limit'			=> NULL,
			'page'			=> NULL,
			'recursive'		=> 1,
			'callbacks'		=> true
		);
		
		$options = array_merge( $defaults, $options );
		
		$results = $this->find( 'all', $options );
		
		if ( $results ) {
			$return = array();
			
			foreach ( $results as $key=>$result ) {
				$return[ $result[$this->name]['id'] ] = $result;
			}
		}
		
		return $return;
		
	}
	
	function paginate($conditions, $fields, $order, $limit, $page, $recursive, $extra){
		$params = array(
			'fields'	=> $fields, 
			'conditions'=> $conditions, 
			'order'		=> $order, 
			'limit'		=> $limit, 
			'offset'	=> $limit * ($page > 0 ? $page - 1 : 0), 
			'recursive' => $recursive, 
			'extra'		=> $extra
		);
		
		if(isset($extra['joins'])) {
			$params['joins'] = $extra['joins'];
			unset($extra['joins']);
		}
		return $this->find('all', $params);
	}
	
/**
 * Deconstructs a complex data type (array or object) into a single field value. Copied from CakePHP core since alterations were required
 *
 * @param string $field The name of the field to be deconstructed
 * @param mixed $data An array or object to be deconstructed into a field
 * @param boolean $is_end (for a range search)
 * @param boolean $is_search If true, date/time will be patched as much as possible
 * @return mixed The resulting data that should be assigned to a field
 */
	function deconstruct($field, $data, $is_end = false, $is_search = false) {
		if (!is_array($data)) {
			return $data;
		}
		
		$type = $this->getColumnType($field);
		if(in_array($type, array('datetime', 'timestamp', 'date', 'time'))){
			$data = array_merge(array("year" => null, "month" => null, "day" => null, "hour" => null, "min" => null, "sec" => null), $data);
			if(strlen($data['year']) > 0 || strlen($data['month']) > 0 || strlen($data['day']) > 0 || strlen($data['hour']) > 0 || strlen($data['min']) > 0){
				$got_date = in_array($type, array('datetime', 'timestamp', 'date'));
				$got_time = in_array($type, array('datetime', 'timestamp', 'time'));
				if($is_search){
					//if search and leading field missing, return
					if($got_date && strlen($data['year']) == 0){
						return null;
					}
					if($type == 'time' && strlen($data['hour']) == 0){
						return null;
					}
				}
				
				//manage meridian
				if($is_end && isset($data['hour']) && strlen($data['hour']) > 0 && isset($data['meridian']) && strlen($data['meridian']) == 0){
					$data['meridian'] = 'pm';
				}
				if(is_numeric($data['hour'])){
					//do not alter an invalid hour
					if (isset($data['hour']) && isset($data['meridian']) && $data['hour'] != 12 && 'pm' == $data['meridian']) {
						$data['hour'] = $data['hour'] + 12;
					}
					if (isset($data['hour']) && isset($data['meridian']) && $data['hour'] == 12 && 'am' == $data['meridian']) {
						$data['hour'] = '00';
					}
				}
				
				
				//patch incomplete values
				if($is_search){
					if($got_date){
						if($is_end){
							if(strlen($data['day']) == 0){
								$data['day'] = 31;
								if(strlen($data['month']) == 0){
									//only patch month if date is patched
									$data['month'] = 12;
								}
							}
						}else{
							if(strlen($data['day']) == 0){
								$data['day'] = 1;
								if(strlen($data['month']) == 0){
									//only patch month if date is patched
									$data['month'] = 1;
								}
							}
						}
					}
				
					if(in_array($type, array('datetime', 'timestamp'))){
						if(strlen($data['hour']) == 0 && strlen($data['min']) == 0 && strlen($data['sec']) == 0){
							//only patch hour if min and sec are empty
							$data['hour'] = $is_end ? 23 : 0;
						}
					}

					if($got_time){
						if(strlen($data['min']) == 0){
							$data['min'] = $is_end ? 59 : 0;
						}
						if(!isset($data['sec']) || strlen($data['sec']) == 0){
							$data['sec'] = $is_end ? 59 : 0;
						}
					}
				}else{
					if(isset($data['year_accuracy'])){
						$data['year'] = '±'.$data['year'];
					}
					
					if(!isset($data['sec']) || strlen($data['sec']) == 0){
						$data['sec'] = '00';
					}
				}
				
				if($got_time){
					foreach(array('hour', 'min', 'sec') as $key){
						if(is_numeric($data[$key])){
							$data[$key] = sprintf("%s", $data[$key]);
						}
					}
				}
				
				$result = null;
				if($got_date && $got_time){
					$result = sprintf("%s-%s-%s %s:%s:%s", $data['year'], $data['month'], $data['day'], $data['hour'], $data['min'], $data['sec']);
				}else if($got_date){
					$result = sprintf("%s-%s-%s", $data['year'], $data['month'], $data['day']);
				}else{
					$result = sprintf("%s:%s:%s", $data['hour'], $data['min'], $data['sec']);
				}
				return $result;
			}
			return "";
		}

		return $data;
	}
	
	/**
	 * Replace the %%key_increment%% part of a string with the key increment value
	 * @param string $key - The key to seek in the database
	 * @param string $str - The string where to put the value. %%key_increment%% will be replaced by the value. 
	 * @param int $pad_to_length - The min length of the key increment part. If the retrieved key is too short, 0 will be prepended. 
	 * @return string The string with the replaced value or false when SQL error happens
	 */
	function getKeyIncrement($key, $str, $pad_to_length = 0){
		$this->query('LOCK TABLE key_increments WRITE');
		$result = $this->query('SELECT key_value FROM key_increments WHERE key_name="'.$key.'"');
		if(empty($result)){
			$this->query('UNLOCK TABLES');
			return false;
		}
		try{
			$this->query('UPDATE key_increments set key_value = key_value + 1 WHERE key_name="'.$key.'"');
		}catch(Exception $e){
			$this->query('UNLOCK TABLES');
			return false; 
		}
		$this->query('UNLOCK TABLES');
		return str_replace("%%key_increment%%", str_pad($result[0]['key_increments']['key_value'], $pad_to_length, '0', STR_PAD_LEFT), $str);
	}
	
	static function getMagicCodingIcdTriggerArray(){
		return self::$magic_coding_icd_trigger_array;
	}
	
	public function buildAccuracyConfig(){
		$tmp_acc = array();
		if(!isset($this->_schema) && $this->table){
			$this->schema();
		}
		if(isset($this->_schema)){
			foreach($this->_schema as $field_name => $foo){
				if(strpos($field_name, "_accuracy") === strlen($field_name) - 9){
					$tmp_acc[substr($field_name, 0, strlen($field_name) - 9)] = $field_name;
				}
			}
		}else{
			AppController::addWarningMsg('failed to build accuracy config for model ['.$this->name.'] because there is no schema. '
				.'To avoid this warning message you can add an empty array as a schema to your model. Eg.: <code>$model->_schema = array();</code>');
		}
		self::$accuracy_config[$this->table] = $tmp_acc;
	}
	
	private function setDataAccuracy(){
		if(!array_key_exists($this->table, self::$accuracy_config)){
			//build accuracy settings for that table
			$this->buildAccuracyConfig();
		}
		
		foreach(self::$accuracy_config[$this->table] as $date_field => $accuracy_field){
			if(!isset($this->data[$this->name][$date_field])){
				continue;
			}
			
			$current = &$this->data[$this->name][$date_field];
			if(empty($current)){
				$this->data[$this->name][$accuracy_field] = '';
				$current = null;
			}else{
				list($year, $month, $day) = explode("-", trim($current));
				$hour = null;
				$minute = null;
				$time = null;
				if(strpos($day, ' ') !== false){
					list($day, $time) = explode(" ", $day);
					list($hour, $minute) = explode(":", $time);
				}
				
				//used to avoid altering the date when its invalid
				$go_to_next_field = false;
				$plus_minus = false;
				if(strpos($year, '±') === 0){
					$plus_minus = true;
					$year = substr($year, 2);
					$month = $day = $hour = $minute = null;
				}
				
				$empty_found = false;
				foreach(array($year, $month, $day, $hour, $minute) as $field){
					if(!empty($field) && !is_numeric($field)){
						$go_to_next_field = true;
						break;
					}
					if(strlen($field) == 0){
						$empty_found = true;
					}else if($empty_found){
						//example: Entered 2010--02 -> Invalid date is skiped here and get caught at validation level
						$go_to_next_field = true;
						break;
					}
				}
				if($go_to_next_field){
					continue;//if one of them is not empty AND not numeric
				}
				
				if(!empty($year)){
					if($plus_minus || (empty($month) && empty($day) && empty($hour) && empty($minute))){
						$month = '01';
						$day = '01';
						$hour = '00';
						$minute = '00';
						if($plus_minus){
							$this->data[$this->name][$accuracy_field] = 'y';
						}else{
							$this->data[$this->name][$accuracy_field] = 'm';
						}
					}else if(empty($day) && empty($hour) && empty($minute)){
						$day = '01';
						$hour = '00';
						$minute = '00';
						$this->data[$this->name][$accuracy_field] = 'd';
					}else if(empty($time)){
						$this->data[$this->name][$accuracy_field] = 'c';
					}else if(!strlen($hour) && !strlen($minute)){
						$hour = '00';
						$minute = '00';
						$this->data[$this->name][$accuracy_field] = 'h';
					}else if(!strlen($minute)){
						$minute = '00';
						$this->data[$this->name][$accuracy_field] = 'i';
					}else{
						$this->data[$this->name][$accuracy_field] = 'c';
					}
					$current = sprintf("%s-%02s-%02s", $year, $month, $day);
					if(!empty($time)){
						$current .= sprintf(" %02s:%02s:00", $hour, $minute);
					}
				}
			}
		}
	}
	
	function validates($options = array()){
		if(!$this->_schema){
			$this->schema();
		}
		if(!isset(self::$auto_validation[$this->name]) &&
			isset($this->Behaviors->MasterDetail) &&
			(strpos($this->name, 'Detail') === false ||
			!array_key_exists(str_replace('Detail', 'Master', $this->name), $this->Behaviors->MasterDetail->__settings))
		){
			//build master validation (detail validation are built within the validation function)
			self::buildAutoValidation($this->name, $this);
			if(array_key_exists($this->name, self::$auto_validation)){
				$this->validate = array_merge_recursive($this->validate, self::$auto_validation[$this->name]);
			}
		}
		$this->setDataAccuracy();
		
		if(isset($this->Behaviors->MasterDetail->__settings[$this->name]['is_master_model']) && $this->Behaviors->MasterDetail->__settings[$this->name]['is_master_model']){
			//master detail, validate the details part
			$settings = $this->Behaviors->MasterDetail->__settings[$this->name];
			$master_class		= $settings['master_class'];
			$control_foreign 	= $settings['control_foreign'];
			$control_class 		= $settings['control_class'];
			$detail_class		= $settings['detail_class'];
			$form_alias			= $settings['form_alias'];
			$detail_field		= $settings['detail_field'];

			$associated = NULL;
			if (isset($this->data[$master_class][$control_foreign]) && $this->data[$master_class][$control_foreign] ) {
				// use CONTROL_ID to get control row
				$associated = $this->$control_class->find('first',array('conditions' => array($control_class.'.id' => $this->data[$master_class][$control_foreign])));
			} else if(isset($this->id) && is_numeric($this->id)){
				// else, if EDIT, use MODEL.ID to get row and find CONTROL_ID that way...
				$associated = $this->find('first', array('conditions' => array($master_class.'.id' => $this->id)));
			}else if(isset($this->data[$master_class]['id']) && is_numeric($this->data[$master_class]['id'])){
				// else, (still EDIT), use use data[master_model][id] to get row and find CONTROL_ID that way...
				$associated = $this->find('first',array('conditions' => array($master_class.'.id' => $model->data[$this]['id'])));
			}
			
			if($associated == NULL || empty($associated)){
				//FAIL!, we ABSOLUTELY WANT validations
				AppController::getInstance()->redirect( '/Pages/err_internal?p[]='.__CLASS__." @ line ".__LINE__." (the detail control id was not found for ".$master_class.")", NULL, TRUE );
				exit;
			}
			
			$use_form_alias = $associated[$control_class][$form_alias];
			$use_table_name = $associated[$control_class][$detail_field];
			if($use_form_alias){
				$plugin_name = $this->getPluginName();
				$detail_class_instance = AppModel::getInstance($plugin_name, $detail_class);
				if($detail_class_instance->useTable == false){
					$detail_class_instance->useTable = $use_table_name;
				}else if($detail_class_instance->useTable != $use_table_name){
					ClassRegistry::removeObject($detail_class_instance->alias);
					$detail_class_instance = AppModel::getInstance($plugin_name, $detail_class);
					$detail_class_instance->useTable = $use_table_name;
				}
				assert($detail_class_instance->useTable == $use_table_name);
				if(isset(AppController::getInstance()->{$detail_class}) && (!isset($params['validate']) || $params['validate'])){
					//attach auto validation
					$auto_validation_name = $detail_class.$associated[$control_class]['id'];
					
					if(!isset(self::$auto_validation[$auto_validation_name])){
						//build detail validation on the fly
						$this->buildAutoValidation($auto_validation_name, $detail_class_instance);
					}
					
					$detail_class_instance->validate = AppController::getInstance()->{$detail_class}->validate;
					foreach(self::$auto_validation[$auto_validation_name] as $field_name => $rules){
						if(!isset($detail_class_instance->validate[$field_name])){
							$detail_class_instance->validate[$field_name] = array();
						}
						$detail_class_instance->validate[$field_name] = array_merge($detail_class_instance->validate[$field_name], $rules);
					}
					$detail_class_instance->set(isset($this->data[$detail_class]) ? $this->data[$detail_class] : array());
					$valid_detail_class = $detail_class_instance->validates();
					if($detail_class_instance->data){
						$this->data = array_merge($this->data, $detail_class_instance->data);
					}
					if(!$valid_detail_class){
						//put details validation errors in the master model
						$this->validationErrors = array_merge($this->validationErrors, $detail_class_instance->validationErrors);
					}
				}
			}
		}
		
		$this->checkFloats();
		
		parent::validates($options);
		if(count($this->validationErrors) == 0){
			$this->data[$this->alias]['__validated__'] = true;
			return true;
		}
		return false;
	}
	
	static function getInstance($plugin_name, $class_name, $error_view_on_null = true){
		$instance = ClassRegistry::getObject($class_name);
		if($instance !== false && $instance instanceof $class_name){
			return $instance;
		}
		
		if($plugin_name != null && strlen($plugin_name) > 0){
			$plugin_name .= ".";
		}
		
		$import_name = (strlen($plugin_name) > 0 ? $plugin_name : "").$class_name;
		if(class_exists($class_name, false) || App::import('Model', $import_name)){
			$instance = ClassRegistry::init($plugin_name.$class_name, true);
			
			if(($bogus_model = ClassRegistry::getObject('Model')) != false && $bogus_model->alias == $class_name){
				//fix a cakephp2 issue where the "Model" model is bogusly created with the alias of the model we just initialized
				ClassRegistry::removeObject('Model');
			}
		}
		if($instance === false && $error_view_on_null){
			pr(AppController::getStackTrace());
			die('died in AppModel::getInstance ['.$plugin_name.$class_name.'] (If you are displaying a form with master & detail fields, please check structure_fields.plugin is not empty)');//TODO: remove me!
			AppController::getInstance()->redirect( '/Pages/err_model_import_failed?p[]='.$class_name, NULL, TRUE );
		}
		
		return $instance;
	}
	
	/**
	 * Use this function to instantiate extend models. It loads it based on the 
	 * table_name and and configures the shadow model
	 * @param class $class The class to instantiate
	 * @param string $table_name The table to use
	 * @return The instantiated class
	 */
	static function atimInstantiateExtend($class, $table_name){
		ClassRegistry::removeObject($class->name);
		$extend = new $class(false, $table_name);
		$extend->Behaviors->Revision->setup($extend);//activate shadow model
		return $extend;
	}
	
	/**
	 * @desc Builds automatic string length and float validations based on the field type 
	 * @param string $use_name The name under which to record the validations
	 * @param Model $model The model to base the validations on
	 */
	static function buildAutoValidation($use_name, Model $model){
		if(!is_array($model->_schema)){
			$model->schema();
		}
		if(is_array($model->_schema)){
			$auto_validation = array();
			foreach($model->_schema as $field_name => $field_data){
				switch($field_data['type']){
					case 'string':
						$auto_validation[$field_name][] = array(
							'rule' => array("maxLength", $field_data['length']),
							'allowEmpty' => true,
							'required' => null,
							'message' => __("the string length must not exceed %d characters", $field_data['length'])
						);
						break;
					case 'float':
						$min = "-1000000000";
						$max = "1000000000";//arbitrary limit
						if($field_data['length']){
							list($m, $d) = explode(',', $field_data['length']);
							$max = str_repeat('9', $m - $d).".".str_repeat('9', $d);
							$min = -1 * $max;
						}else if($field_data['atim_type'] == 'float unsigned'){
							$min = "0";
						}
						$auto_validation[$field_name][] = array(
							'rule' => array('range', (int)$min - 1, (int)$max + 1),
							'allowEmpty' => true,
							'required' => null,
							'message' => __("the value must be between %g and %g", $min, $max)
						);
						break;
					case 'integer':
						$rule = null;
						if(strpos($field_data['atim_type'], 'int') === 0){
							if(strpos($field_data['atim_type'], 'unsigned') === false){
								$rule = array('range', -2147483649, 2147483648);
							}else{
								$rule = array('range', -1, 4294967295);
							}
						}else if(strpos($field_data['atim_type'], 'tinyint') === 0){
							if(strpos($field_data['atim_type'], 'unsigned') === false){
								$rule = array('range', -129, 128);
							}else{
								$rule = array('range', -1, 256);
							}
						}else if(strpos($field_data['atim_type'], 'smallint') === 0){
							if(strpos($field_data['atim_type'], 'unsigned') === false){
								$rule = array('range', -32769, 32768);
							}else{
								$rule = array('range', -1, 65536);
							}
						}else if(strpos($field_data['atim_type'], 'mediumint') === 0){
							if(strpos($field_data['atim_type'], 'unsigned') === false){
								$rule = array('range', -8388609, 8388608);
							}else{
								$rule = array('range', -1, 16777216);
							}
						}else if(strpos($field_data['atim_type'], 'bigint') === 0){
							if(strpos($field_data['atim_type'], 'unsigned') === false){
								$rule = array('range', -9223372036854775809, 9223372036854775808);
							}else{
								$rule = array('range', -1, 18446744073709551615);
							}
						}else{
							AppController::addWarningMsg('Unknown integer type for field ['.$model->name.'.'.$field_name, true);
						}
						if($rule){
							$auto_validation[$field_name][] = array(
								'rule' => $rule,
								'allowEmpty' => true,
								'required' => null,
								'message' => __("the value must be between %g and %g", $rule[1] + 1, $rule[2] - 1)
							);
						}
						break;
					default:
						break;
				}
			}
			self::$auto_validation[$use_name] = $auto_validation;
		}
	}

	/**
	 * Searches recursively for field in CakePHP SQL conditions
	 * @param string $field The field to look for
	 * @param array $conditions CakePHP SQL conditionnal array
	 * @return true if the field was found
	 */
	static function isFieldUsedAsCondition($field, array $conditions){
		foreach($conditions as $key => $value){
			$is_array = is_array($value);
			$pos1 = strpos($key, $field);
			$pos2 = strpos($key, " ");
			if($pos1 !== false && ($pos2 === false || $pos1 < $pos2)){
				return true;
			}
			if($is_array){
				if(self::isFieldUsedAsCondition($field, $value)){
					return true;
				}
			}else{
				$pos1 = strpos($value, $field);
				$pos2 = strpos($value, " ");
				if($pos1 !== false && ($pos2 === false || $pos1 < $pos2)){
					return true;
				}
			}
		}
		return false;
	}
	
	/**
	 * Return the spent time between 2 dates. 
	 * Notes: The supported date format is YYYY-MM-DD HH:MM:SS
	 * 
	 * @param $start_date Start date
	 * @param $end_date End date
	 * 
	 * @return Return an array that contains the spent time
	 * or an error message when the spent time can not be calculated.
	 * The sturcture of the array is defined below:
	 *	Array (
	 * 		'message' => '',
	 * 		'days' => '0',
	 * 		'hours' => '0',
	 * 		'minutes' => '0'
	 * 	)
	 * 
	 * @author N. Luc
	 * @since 2007-06-20
	 */
	 
	static function getSpentTime($start_date, $end_date){
		$arr_spent_time = array(
			'message'			=> null,
			'years'				=> '0',
			'days_mod_years'	=> '0',
			'days'				=> '0',
			'hours'				=> '0',
			'minutes'			=> '0'
		);
		
		$empty_date = '0000-00-00 00:00:00';
		
		// Verfiy date is not empty
		if(empty($start_date) || empty($end_date)
			|| (strcmp($start_date, $empty_date) == 0)
			|| (strcmp($end_date, $empty_date) == 0)
		){
			// At least one date is missing to continue
			$arr_spent_time['message'] = 'missing date';	
		} else {
			$start = AppModel::getTimeStamp($start_date);
			$end = AppModel::getTimeStamp($end_date);
			$spent_time = $end - $start;
			
			if(($start === false)||($end === false)){
				// Error in the date
				$arr_spent_time['message'] = 'error: unable to define date';
			} else if($spent_time < 0){
				// Error in the date
				$arr_spent_time['message'] = 'error in the date definitions';
			} else if($spent_time == 0){
				// Nothing to change to $arr_spent_time
				$arr_spent_time['message'] = '0';
			} else {
				// Return spend time
				$arr_spent_time['days'] = floor($spent_time / 86400);
				$diff_spent_time = $spent_time % 86400;
				$arr_spent_time['hours'] = floor($diff_spent_time / 3600);
				$diff_spent_time = $diff_spent_time % 3600;
				$arr_spent_time['minutes'] = floor($diff_spent_time / 60);
				if($arr_spent_time['minutes']<10) {
					$arr_spent_time['minutes'] = '0' . $arr_spent_time['minutes'];
				}
				
				$arr_spent_time['years'] = substr($end_date, 0, 4)  - substr($start_date, 0, 4);
				if(strtotime($end_date) < strtotime(substr($end_date, 0, 4).substr($start_date, 4))){
					$arr_spent_time['years'] --;
					$latest_year_mark = strtotime((substr($end_date, 0, 4) - 1).substr($start_date, 4));
				}else{
					$latest_year_mark = strtotime(substr($end_date, 0, 4).substr($start_date, 4));
				}
				$arr_spent_time['days_mod_years'] = floor(($end - $latest_year_mark) / 86400);
			}
			
		}
		
		return $arr_spent_time;
	}

	/**
	 * Return time stamp of a date. 
	 * Notes: The supported date format is YYYY-MM-DD HH:MM:SS
	 * 
	 * @param $date_string Date
	 * @param $end_date End date
	 * 
	 * @return Return time stamp of the date.
	 * 
	 * @author N. Luc
	 * @since 2007-06-20
	 */
	 
	static function getTimeStamp($date_string){
		list($date, $time) = explode(' ', $date_string);
		list($year, $month, $day) = explode('-', $date);
		list($hour, $minute, $second) = explode(':',$time);

		return mktime($hour, $minute, $second, $month, $day, $year);
	}	
	
	static function manageSpentTimeDataDisplay($spent_time_data, $with_time = true){
		$spent_time_msg = '';
		if(!empty($spent_time_data)) {	
			if(!is_null($spent_time_data['message'])) {
				if($spent_time_data['message'] == '0') {
					$spent_time_msg = $spent_time_data['message'];
				} else if(strcmp('error in the date definitions', $spent_time_data['message']) == 0) {
					$spent_time_msg = '<span class="red">'.__($spent_time_data['message']).'</span>';
				} else {
					$spent_time_msg = __($spent_time_data['message']);
				}
			} else {
				if($with_time){
					$spent_time_msg = AppModel::translateDateValueAndUnit($spent_time_data, 'days') 
						.AppModel::translateDateValueAndUnit($spent_time_data, 'hours') 
						.AppModel::translateDateValueAndUnit($spent_time_data, 'minutes');
				}else{
					$spent_time_data['days'] = $spent_time_data['days_mod_years'];
					$spent_time_msg = AppModel::translateDateValueAndUnit($spent_time_data, 'years')
						.AppModel::translateDateValueAndUnit($spent_time_data, 'days');
				}
			} 	
		}
		
		return $spent_time_msg;
	}
	
	static function translateDateValueAndUnit($spent_time_data, $time_unit) {
		if(array_key_exists($time_unit, $spent_time_data)) {
			return (((!empty($spent_time_data[$time_unit])) && ($spent_time_data[$time_unit] != '00'))? ($spent_time_data[$time_unit] . ' ' . ($spent_time_data[$time_unit] == 1 ? __(substr($time_unit, 0, -1)) : __($time_unit)) . ' ') : '');
		} 
		return  '#err#';
	}
	
	/**
	 * Uses the same url sorting options as cakephp paginator uses to sort a data array
	 * @param array $data The data to sort
	 * @param array $passed_args The controller passed arguments. (From the controller, $this->passedArgs)
	 * @return The data sorted if the passed_args were compatible with it
	 */
	static function sortWithUrl(array $data, array $passed_args){
		$order = array();
		if(isset($passed_args['sort'])){
			$result = array();
			list($sort_model, $sort_field) = explode(".", $passed_args['sort']);
			$i = 0;
			foreach($data as $data_unit){
				if(isset($data_unit[$sort_model]) && isset($data_unit[$sort_model][$sort_field])){
					$result[sprintf("%s%04d", $data_unit[$sort_model][$sort_field], ++ $i)] = $data_unit;
				}else{
					$result[sprintf("%04d", ++ $i)] = $data_unit;
				}
			}
			ksort($result);
			if(isset($passed_args['direction']) && $passed_args['direction'] == 'desc'){
				$result = array_reverse($result);
			}
			return $result;
		}
		return $data;
	}
	
	/**
	 * Generic function made to be overriden in model/custom models.
	 * @param int $id The db id of the element to allow the deletion of
	 * @return array with two keys, one being allow_detion, a boolean telling
	 * whether the element can be deleted or not and the second one being msg,
	 * a string that telles why, if relevant, the element cannot be deleted.
	 */
	function allowDeletion($id){
		return array('allow_deletion' => true, 'msg' => '');
	}
	
	/**
	 * Redirects to the missing data page if a model id cannot be fetched
	 * @param int $id
	 * @param string $method The method name to display in the error message
	 * @param string $line The line number to display in the error message
	 * @param bool $return Returns the data line if it exists
	 * @return null if $return is true and the data exists, the data, null otherwise
	 * @deprecated Use getOrRedirect instead. TODO: Remove in ATiM 2.6
	 */
	function redirectIfNonExistent($id, $method, $line, $return = false){
		$this->id = $id;
		if($result = $this->read()){
			if($return){
				return $result;
			}
		}else{
			AppController::getInstance()->redirect( '/Pages/err_plugin_no_data?method='.$method.',line='.$line, null, true );
		}
		return null;
	}
	
	/**
	 * Tries to fetch model data. If it doesn't exists, redirects to an error page.
	 * @param string $id The model primary key to fetch
	 * @return The model data if it succeeds
	 */
	function getOrRedirect($id){
		$this->id = $id;
		if($result = $this->read()){
			return $result;
		}
		$bt = debug_backtrace();
		AppController::getInstance()->redirect( '/Pages/err_plugin_no_data?method='.$bt[1]['function'].',line='.$bt[0]['line'], null, true );
		return null;
	}
	
	private function updateWritableField($field, $tablename = null, $add){
		$add_into = null;
		$remove_from = null;
		if($add){
			$add_into = 'all';
			$remove_from = 'none';
		}else{
			$add_into = 'none';
			$remove_from = 'all';
		}
		$tablename = $tablename ?: $this->table;
		if(!isset(AppModel::$writable_fields[$tablename][$add_into])){
			AppModel::$writable_fields[$tablename][$add_into] = array();
		}
		if(!is_array($field)){
			$field = array($field);
		}
		AppModel::$writable_fields[$tablename][$add_into] = array_unique(array_merge(AppModel::$writable_fields[$tablename][$add_into], $field));
		
		if(isset(AppModel::$writable_fields[$this->table][$remove_from])){
			AppModel::$writable_fields[$this->table][$remove_from] = array_diff(AppModel::$writable_fields[$this->table][$remove_from], $field);
		}
	}
	
	/**
	 * Add fields to the current model table Writable fields array.
	 * @param mixed $field A single field or an array of fields.
	 * @param string $tablename The tablename to allow the fields to be written to.
	 */
	public function addWritableField($field, $tablename = null){
		$this->updateWritableField($field, $tablename, true);
	}
	
	public function removeWritableField($field, $tablename = null){
		$this->updateWritableField($field, $tablename, false);
	}
	
	/**
	 * Called by structure builder to get the browsing filter dropdowns
	 * @return array (array formated for dropdown)
	 */
	function getBrowsingFilter(){
		$result = array();
		if(isset($this->browsing_search_dropdown_info['browsing_filter'])){
			foreach($this->browsing_search_dropdown_info['browsing_filter'] as $key => $details){
				$result[$key] = __($details['lang']);
			}
		}
		return $result;
	}
	
	/**
	 * Gets the model browsing_search_dropdown_info[field_name]. Searches into 
	 * base model when called from a view.
	 * @param string $field_name
	 * @return array if the field config is found, null otherwise
	 */
	function getBrowsingAdvSearchArray($field_name){
		if(isset($this->browsing_search_dropdown_info[$field_name])){
			return $this->browsing_search_dropdown_info[$field_name];
		}
		if(isset($this->base_model)){
			$base_model = AppModel::getInstance($this->base_plugin, $this->base_model, true);
			return $base_model->getBrowsingAdvSearchArray($field_name);
		}
		return null;
	}
	
	/**
	 * Called by structure builder to get the browsing advanced search fields.
	 * @param array $field_name
	 * @return array An array formated for dropdown use
	 */
	function getBrowsingAdvSearch($field_name){
		$field_name = $field_name[0];
		$result = array();
		if(isset($this->browsing_search_dropdown_info[$field_name]) && Browser::$cache['current_node_id'] != 0){
			$browser_model = AppModel::getInstance('Datamart', 'Browser', 'true');
			
			$datamart_structure = AppModel::getInstance('Datamart', 'DatamartStructure', true);
			$sfs_model = AppModel::getInstance('', 'Sfs', true);
			$dm_structures = $datamart_structure->find('all');
			$dm_structures = AppController::defineArrayKey($dm_structures, 'DatamartStructure', 'model', true);
			
			if(!isset(Browser::$cache['path'])){
				$browsing_result_model = AppModel::getInstance('Datamart', 'BrowsingResult', true);
				$path = $browsing_result_model->getPath(Browser::$cache['current_node_id'], null, 0);
				Browser::$cache['allowed_models'] = array();
				while($current = array_pop($path)){
					if($current['BrowsingResult']['raw']){
						if(array_key_exists($current['DatamartStructure']['model'], Browser::$cache['allowed_models'])){
							break;
						}
						Browser::$cache['allowed_models'][$current['DatamartStructure']['model']] = null;
					}
				}
			}
			foreach($this->browsing_search_dropdown_info[$field_name] as $key => $option){
				if(array_key_exists($option['model'], Browser::$cache['allowed_models'])){
					$sfs = $sfs_model->find('first', array('fields' => array('language_label'), 'conditions' => array('Sfs.field' => $option['field'], 'Sfs.model' => $option['model']), 'recursive' => -1));
					$result[$key] = $option['relation'].' '.__($dm_structures[$option['model']]['DatamartStructure']['display_name']).' '.__($sfs['Sfs']['language_label']);
				}
			}
		}
		
		return $result;
	}
	
	function getOwnershipConditions(){
		return array('OR' => array(
			array($this->name.'.sharing_status' => 'user', $this->name.'.user_id' => AppController::getInstance()->Session->read('Auth.User.id')),
			array($this->name.'.sharing_status' => 'group', $this->name.'.group_id' => AppController::getInstance()->Session->read('Auth.User.group_id')),
			array($this->name.'.sharing_status' => 'all')
		));
	}
	
	function afterFind($results, $primary = false) {
		if(isset($this->fields_replace) && isset($results[0][$this->name])){
			$current_fields_replace = $this->fields_replace;
			foreach($current_fields_replace as $field_name => $options){
				if(isset($results[0][$this->name]) && !array_key_exists($field_name, $results[0][$this->name])){
					unset($current_fields_replace[$field_name]);
				}
			} 
			foreach($results as &$result){
				foreach($current_fields_replace as $field_name => $options){
					if(isset($options['msg'][$result[$this->name][$field_name]])){
						$result[$this->name][$field_name] = $options['msg'][$result[$this->name][$field_name]];
					}else if($options['type'] == 'spentTime'){
						$remainder = $result[$this->name][$field_name];
						$time['minutes'] = $remainder % 60;
						$remainder = ($remainder - $time['minutes']) / 60;
						$time['hours'] = $remainder % 24;
						$time['days'] = ($remainder - $time['hours']) / 24;
						$spent_time = AppModel::translateDateValueAndUnit($time, 'days').'' 
								.AppModel::translateDateValueAndUnit($time, 'hours') 
								.AppModel::translateDateValueAndUnit($time, 'minutes');
						$result[$this->name][$field_name] = $spent_time ?: 0; 
					}
				}
			}
		}
		return $results;
	}
	
	private function updateRegisteredViews(){
	    if($this->registered_view){
	        foreach($this->registered_view as $registered_view => $foreign_keys){
	            list($plugin_name, $model_name) = explode('.', $registered_view);
	            $model = AppModel::getInstance($plugin_name, $model_name);
	            foreach($foreign_keys as $foreign_key){
	                $at_least_one = false;
	                foreach(explode("UNION ALL", $model::$table_query) as $query_part){
	                    if(strpos($query_part, $foreign_key) === false){
	                        continue;
	                    }
	                    $at_least_one = true;
	                    if(self::$locked_views_update){
	                        if(!isset(self::$cached_views_update[$model->table])){
	                            self::$cached_views_update[$model->table] = array();
	                        }
	                        if(!isset(self::$cached_views_update[$model->table][$foreign_key])){
	                            self::$cached_views_update[$model->table][$foreign_key] = array();
	                        }
	                        if(!isset(self::$cached_views_update[$model->table][$foreign_key][$query_part])){
	                            self::$cached_views_update[$model->table][$foreign_key][$query_part] = array("ids" => array());
	                        }
	                        array_push(self::$cached_views_update[$model->table][$foreign_key][$query_part]["ids"], $this->id);
	                    }else{
	                        $table_query = str_replace('%%WHERE%%', 'AND '.$foreign_key.'='.$this->id, $query_part);
	                        $query = sprintf('REPLACE INTO %s (%s)', $model->table, $table_query);
	                        $this->tryCatchquery($query);
	                    }
	                }
	                if(!$at_least_one){
	                    throw new Exception("No queries part fitted with the foreign key ".$foreign_key);
	                }
	            }
	        }
	    }
	}
	
	function afterSave($created, $options = Array()){
	    $this->updateRegisteredViews();
	    $this->updateRegisteredModels();
	}

	function makeTree(array &$in){
		if(!empty($in)){
			$starting_pkey = $in[0][$this->name][$this->primaryKey];
			$in = AppController::defineArrayKey($in, $this->name, $this->primaryKey, true);
			$to_remove = array();
			foreach($in as $key => &$part){
				if($part[$this->name]['parent_id'] != null && isset($in[$part[$this->name]['parent_id']])){
					//parent exists, create link
					if(!isset($in[$part[$this->name]['parent_id']][$this->name]['children'])){
						$in[$part[$this->name]['parent_id']][$this->name]['children'] = array();
					}
					$in[$part[$this->name]['parent_id']][$this->name]['children'][] = &$part;
					$to_remove[] = $key;
				}
			}
			foreach($to_remove as &$key){
				unset($in[$key]);
			}
		}
	}
	
	function getPluginName(){
		$class = new ReflectionClass($this);
		$matches = array();
		if(preg_match('#'.str_replace('/','[\\\/]','/app/Plugin/([\w\d]+)/').'#', $class->getFileName(), $matches)){
			return $matches[1];
		}
		return null;
	}
	
	/**
	 * Updates floats to make them db friendly, based on their db field type.
	 * -commas become dots
	 * -plus signs are removed
	 * -0 is appened to a direct float (eg.: ".52 => 0.52", "-.42 => -0.42")
	 * -white spaces are trimmed
	 */
	function checkFloats(){
		foreach($this->_schema as $field_name => $field_properties) {
			$tmp_type = $field_properties['type'];
			if($tmp_type == "float" || $tmp_type == "number" || $tmp_type == "float_positive"){
				// Manage float record
				if(isset($this->data[$this->alias][$field_name])) {
					$this->data[$this->alias][$field_name] = str_replace(",", ".", $this->data[$this->alias][$field_name]);
					$this->data[$this->alias][$field_name] = str_replace(" ", "", $this->data[$this->alias][$field_name]);
					$this->data[$this->alias][$field_name] = str_replace("+", "", $this->data[$this->alias][$field_name]);
					if(is_numeric($this->data[$this->alias][$field_name])) {
						if(strpos($this->data[$this->alias][$field_name], ".") === 0) $this->data[$this->alias][$field_name] = "0".$this->data[$this->alias][$field_name];
						if(strpos($this->data[$this->alias][$field_name], "-.") === 0) $this->data[$this->alias][$field_name] = "-0".substr($this->data[$this->alias][$field_name], 1);
					}
				}
			}
		}
	}
	
	function tryCatchQuery($sql, $cache = false){
		try{
			return parent::query($sql, $cache);
		}catch(Exception $e){
			if(Configure::read('debug') > 0){
				pr('QUERY ERROR:');
				pr($sql);
				pr(AppController::getStackTrace());
				exit;
			} else {
				AppController::getInstance()->redirect( '/Pages/err_plugin_system_error?method='.__METHOD__.',line='.__LINE__, null, true );
			}
		}
	}
	
	/**
	 * Will sort data based on the given primary key order.
	 * @param array $data The data to sort.
	 * @param array|string $order The ordered pkeys in either an array or a comma separated string.
	 */
	function sortForDisplay(array &$data, $order){
		$tmp_data = AppController::defineArrayKey($data, $this->name, $this->primaryKey, true);
		if(is_string($order)){
			$order = explode(',', $order);
		}
		$order = array_unique(array_filter($order));
		$data = array();
		foreach($order as $key){
			$data[] = $tmp_data[$key];
		}
		unset($tmp_data);
	}
	
	static function acquireBatchViewsUpdateLock(){
        if(self::$locked_views_update){
            throw new Exception('Deadlock in acquireBatchViewsUpdateLock');
        }
        self::$locked_views_update = true;
    }
    
    static function manageViewUpdate($model_table, $foreign_key, $ids, $query_part){
    	if(self::$locked_views_update){
    		if(!isset(self::$cached_views_update[$model_table])){
    			self::$cached_views_update[$model_table] = array();
    		}
    		if(!isset(self::$cached_views_update[$model_table][$foreign_key])){
    			self::$cached_views_update[$model_table][$foreign_key] = array();
    		}
    		if(!isset(self::$cached_views_update[$model_table][$foreign_key][$query_part])){
    			self::$cached_views_update[$model_table][$foreign_key][$query_part] = array("ids" => array());
    		}
    		self::$cached_views_update[$model_table][$foreign_key][$query_part]["ids"] = array_merge(self::$cached_views_update[$model_table][$foreign_key][$query_part]["ids"], $ids);
    	}else{
    		$table_query = str_replace('%%WHERE%%', 'AND '.$foreign_key.' IN ('.implode(',',$ids).')', $query_part);
    		$query = sprintf('REPLACE INTO %s (%s)', $model_table, $table_query);
    		$pages = AppModel::getInstance("", "Page");
    		$pages->tryCatchquery($query);
    	}
    }
    
    static function releaseBatchViewsUpdateLock(){
    	//just "some" model to do the work
    	$pages = AppModel::getInstance("", "Page");
        foreach(self::$cached_views_update as $model_table => $models){
            foreach($models as $foreign_key => $query_parts){
                foreach($query_parts as $query_part => $details){
                    $table_query = str_replace('%%WHERE%%', 'AND '.$foreign_key.' IN('.implode(",", array_unique($details['ids'])).')', $query_part);
                    $query = sprintf('REPLACE INTO %s (%s)', $model_table, $table_query);
					$pages->tryCatchquery($query);
                }
            }
        }
        foreach(self::$cached_views_delete as $model_table => $models){
        	foreach($models as $primary_key => $details){
        		$query = sprintf('DELETE FROM %s  WHERE %s IN (%s)', $model_table, $primary_key, implode(',',array_unique($details['pkeys_for_deletion'])));	//To fix issue#2980: Edit Storage & View Update 
				$pages->tryCatchquery($query);
        	}
		}
		foreach(self::$cached_views_insert as $model_table => $base_models){
			foreach($base_models as $base_model => $query_parts){
				foreach($query_parts as $query_part => $details){
					$table_query = str_replace('%%WHERE%%', 'AND '.$base_model.'.id IN('.implode(", ", array_unique($details['ids'])).')', $query_part);
					$query = sprintf('INSERT INTO %s (%s)', $model_table, $table_query);
					$pages->tryCatchquery($query);
				}
			}
		}		
		self::$cached_views_update = array();
		self::$cached_views_delete = array();
		self::$cached_views_insert = array();
        self::$locked_views_update = false;
	}
}
