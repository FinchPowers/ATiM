<?php  
/* SVN FILE: $Id: soft_deletable.php 38 2007-11-26 19:36:27Z mgiglesias $ */ 

/** 
 * SoftDeletable Behavior class file. 
 * 
 * @filesource 
 * @author Mariano Iglesias 
 * @link http://cake-syrup.sourceforge.net/ingredients/soft-deletable-behavior/ 
 * @version    $Revision: 38 $ 
 * @license    http://www.opensource.org/licenses/mit-license.php The MIT License 
 * @package app 
 * @subpackage app.models.behaviors 
 */ 

/** 
 * Model behavior to support soft deleting records. 
 * 
 * @package app 
 * @subpackage app.models.behaviors 
 */ 
class SoftDeletableBehavior extends ModelBehavior 
{ 
    /** 
     * Contain settings indexed by model name. 
     * 
     * @var array 
     * @access private 
     */ 
    var $__settings = array(); 

    /** 
     * Initiate behaviour for the model using settings. 
     * 
     * @param object $Model Model using the behaviour 
     * @param array $settings Settings to override for model. 
     * @access public 
     */ 
    public function setup(Model $model, $config = array()) 
    { 
        $default = array('field' => 'deleted', /*'field_date' => 'deleted_date',*/ 'delete' => true, 'find' => true); 

        if (!isset($this->__settings[$model->alias])) 
        { 
            $this->__settings[$model->alias] = $default; 
        } 

        $this->__settings[$model->alias] = am($this->__settings[$model->alias], is_array($config) ? $config : array()); 
    } 

    /** 
     * Run before a model is deleted, used to do a soft delete when needed. 
     * 
     * @param object $Model Model about to be deleted 
     * @param boolean $cascade If true records that depend on this record will also be deleted 
     * @return boolean Set to true to continue with delete, false otherwise 
     * @access public 
     */ 
    public function beforeDelete(Model $model, $cascade = true) 
    { 
        if ($this->__settings[$model->alias]['delete'] && $model->hasField($this->__settings[$model->alias]['field'])) 
        { 
            $attributes = $this->__settings[$model->alias]; 
            $id = $model->id; 

            $data = array($model->alias => array( 
                $attributes['field'] => 1 
            )); 

            if (isset($attributes['field_date']) && $model->hasField($attributes['field_date'])) 
            { 
                $data[$model->alias][$attributes['field_date']] = date('Y-m-d H:i:s'); 
            } 

            foreach(am(array_keys($data[$model->alias]), array('field', 'field_date', 'find', 'delete')) as $field) 
            { 
                unset($attributes[$field]); 
            } 

            if (!empty($attributes)) 
            { 
                $data[$model->alias] = am($data[$model->alias], $attributes); 
            } 

            $model->id = $id; 
            $deleted = $model->save($data, false, array_keys($data[$model->alias])); 

            if ($deleted && $cascade) 
            { 
                $this->_atimDeleteDependent($model, $id, $cascade); 
                $model->_deleteLinks($id); 
            } 

            return false; 
        } 

        return true; 
    }
    
    /**
     * Cascades model deletes through associated hasMany and hasOne child records.
     * Altered model._atimDeleteDependent to ignore tables withoud the "delete" field.
     *
     * @param string $id ID of record that was deleted
     * @param boolean $cascade Set to true to delete records that depend on this record
     * @return void
     */
    private function _atimDeleteDependent(Model $model, $id, $cascade) {
    	if (!empty($model->__backAssociation)) {
    		$savedAssociatons = $model->__backAssociation;
    		$model->__backAssociation = array();
    	}
    	if ($cascade === true) {
    		foreach (array_merge($model->hasMany, $model->hasOne) as $assoc => $data) {
    			if ($data['dependent'] === true) {
    
    				$assoc_model = $model->{$assoc};
    
    				if ($data['foreignKey'] === false && $data['conditions'] && in_array($model->name, $assoc_model->getAssociated('belongsTo'))) {
    					$assoc_model->recursive = 0;
    					$conditions = array($model->escapeField(null, $model->name) => $id);
    				} else {
    					$assoc_model->recursive = -1;
    					$conditions = array($assoc_model->escapeField($data['foreignKey']) => $id);
    					if ($data['conditions']) {
    						$conditions = array_merge((array)$data['conditions'], $conditions);
    					}
    				}
    
    				if (isset($data['exclusive']) && $data['exclusive']) {
    					$assoc_model->deleteAll($conditions);
    				} else {
    					$records = $assoc_model->find('all', array(
    							'conditions' => $conditions, 'fields' => $assoc_model->primaryKey
    					));
    
    					if (!empty($records)) {
    						foreach ($records as $record) {
    							$schema = $assoc_model->schema();
    							if(isset($schema["deleted"])){
    								$assoc_model->delete($record[$assoc_model->alias][$assoc_model->primaryKey]);
    							}
    						}
    					}
    				}
    			}
    		}
    	}
    	if (isset($savedAssociatons)) {
    		$model->__backAssociation = $savedAssociatons;
    	}
    }

    /** 
     * Permanently deletes a record. 
     * 
     * @param object $Model Model from where the method is being executed. 
     * @param mixed $id ID of the soft-deleted record. 
     * @param boolean $cascade Also delete dependent records 
     * @return boolean Result of the operation. 
     * @access public 
     */ 
    function hardDelete(&$Model, $id, $cascade = true) 
    { 
        $onFind = $this->__settings[$Model->alias]['find']; 
        $onDelete = $this->__settings[$Model->alias]['delete']; 
        $this->enableSoftDeletable($Model, false); 

        $deleted = $Model->del($id, $cascade); 

        $this->enableSoftDeletable($Model, 'delete', $onDelete); 
        $this->enableSoftDeletable($Model, 'find', $onFind); 

        return $deleted; 
    } 

    /** 
     * Permanently deletes all records that were soft deleted. 
     * 
     * @param object $Model Model from where the method is being executed. 
     * @param boolean $cascade Also delete dependent records 
     * @return boolean Result of the operation. 
     * @access public 
     */ 
    function purge(&$Model, $cascade = true) 
    { 
        $purged = false; 

        if ($Model->hasField($this->__settings[$Model->alias]['field'])) 
        { 
            $onFind = $this->__settings[$Model->alias]['find']; 
            $onDelete = $this->__settings[$Model->alias]['delete']; 
            $this->enableSoftDeletable($Model, false); 

            $purged = $Model->deleteAll(array($this->__settings[$Model->alias]['field'] => '1'), $cascade); 

            $this->enableSoftDeletable($Model, 'delete', $onDelete); 
            $this->enableSoftDeletable($Model, 'find', $onFind); 
        } 

        return $purged; 
    } 

    /** 
     * Restores a soft deleted record, and optionally change other fields. 
     * 
     * @param object $Model Model from where the method is being executed. 
     * @param mixed $id ID of the soft-deleted record. 
     * @param $attributes Other fields to change (in the form of field => value) 
     * @return boolean Result of the operation. 
     * @access public 
     */ 
    function undelete(&$Model, $id = null, $attributes = array()) 
    { 
        if ($Model->hasField($this->__settings[$Model->alias]['field'])) 
        { 
            if (empty($id)) 
            { 
                $id = $Model->id; 
            } 

            $data = array($Model->alias => array( 
                $Model->primaryKey => $id, 
                $this->__settings[$Model->alias]['field'] => '0' 
            )); 

            if (isset($this->__settings[$Model->alias]['field_date']) && $Model->hasField($this->__settings[$Model->alias]['field_date'])) 
            { 
                $data[$Model->alias][$this->__settings[$Model->alias]['field_date']] = null; 
            } 

            if (!empty($attributes)) 
            { 
                $data[$Model->alias] = am($data[$Model->alias], $attributes); 
            } 

            $onFind = $this->__settings[$Model->alias]['find']; 
            $onDelete = $this->__settings[$Model->alias]['delete']; 
            $this->enableSoftDeletable($Model, false); 

            $Model->id = $id; 
            $result = $Model->save($data, false, array_keys($data[$Model->alias])); 

            $this->enableSoftDeletable($Model, 'find', $onFind); 
            $this->enableSoftDeletable($Model, 'delete', $onDelete); 

            return ($result !== false); 
        } 

        return false; 
    } 

    /** 
     * Set if the beforeFind() or beforeDelete() should be overriden for specific model. 
     * 
     * @param object $Model Model about to be deleted. 
     * @param mixed $methods If string, method (find / delete) to enable on, if array array of method names, if boolean, enable it for find method
     * @param boolean $enable If specified method should be overriden. 
     * @access public 
     */ 
    function enableSoftDeletable(&$Model, $methods, $enable = true) 
    { 
        if (is_bool($methods)) 
        { 
            $enable = $methods; 
            $methods = array('find', 'delete'); 
        } 

        if (!is_array($methods)) 
        { 
            $methods = array($methods); 
        } 

        foreach($methods as $method) 
        { 
            $this->__settings[$Model->alias][$method] = $enable; 
        } 
    } 

    /** 
     * Run before a model is about to be find, used only fetch for non-deleted records. 
     * 
     * @param object $Model Model about to be deleted. 
     * @param array $queryData Data used to execute this query, i.e. conditions, order, etc. 
     * @return mixed Set to false to abort find operation, or return an array with data used to execute query 
     * @access public 
     */ 
    public function beforeFind(Model $model, $query) 
    {
        if ($this->__settings[$model->alias]['find'] && $model->hasField($this->__settings[$model->alias]['field'])) 
        { 
            $Db = ConnectionManager::getDataSource($model->useDbConfig); 
            $include = false; 

            if (!empty($query['conditions']) && is_string($query['conditions'])) 
            { 
                $include = true; 

                $fields = array( 
                    $Db->name($model->alias) . '.' . $Db->name($this->__settings[$model->alias]['field']), 
                    $Db->name($this->__settings[$model->alias]['field']), 
                    $model->alias . '.' . $this->__settings[$model->alias]['field'], 
                    $this->__settings[$model->alias]['field'] 
                ); 

                foreach($fields as $field) 
                { 
                    if (preg_match('/^' . preg_quote($field) . '[\s=!]+/i', $query['conditions']) || preg_match('/\\x20+' . preg_quote($field) . '[\s=!]+/i', $query['conditions'])) 
                    { 
                        $include = false; 
                        break; 
                    } 
                } 
            } 
            else if (empty($query['conditions']) 
            	|| (!in_array($this->__settings[$model->alias]['field'], array_keys($query['conditions'])) && !in_array($model->alias . '.' . $this->__settings[$model->alias]['field'], array_keys($query['conditions'])))
            	|| isset($query['conditions'][0])) 
            { 
                $include = true; 
            } 

            if ($include) 
            { 
                if (empty($query['conditions'])) 
                { 
                    $query['conditions'] = array(); 
                } 

                if (is_string($query['conditions'])) 
                { 
                    $query['conditions'] = $Db->name($model->alias) . '.' . $Db->name($this->__settings[$model->alias]['field']) . '!= 1 AND ' . $query['conditions']; 
                } 
                else 
                {
                    array_push($query['conditions'], $model->alias . '.' . $this->__settings[$model->alias]['field'].' != 1'); 
                } 
            } 
            
            //Add the deleted != 1 condition for hasMany relationships
            foreach($model->hasMany as $key => &$hasMany){
	            if(isset($hasMany['conditions']) && $hasMany['conditions'] != ""){
	            	array_push($hasMany['conditions'], $key.".deleted != 1"); 
	            }else{
	            	$hasMany['conditions'] = array($key.".deleted != 1");
	            }
            }
        } 

        return $query; 
    } 

    /** 
     * Run before a model is saved, used to disable beforeFind() override. 
     * 
     * @param object $Model Model about to be saved. 
     * @return boolean True if the operation should continue, false if it should abort 
     * @access public 
     */ 
    public function beforeSave(Model $model) 
    { 
        if ($this->__settings[$model->alias]['find']) 
        { 
            if (!isset($this->__backAttributes)) 
            { 
                $this->__backAttributes = array($model->alias => array()); 
            } 
            else if (!isset($this->__backAttributes[$model->alias])) 
            { 
                $this->__backAttributes[$model->alias] = array(); 
            } 

            $this->__backAttributes[$model->alias]['find'] = $this->__settings[$model->alias]['find']; 
            $this->__backAttributes[$model->alias]['delete'] = $this->__settings[$model->alias]['delete']; 
            $this->enableSoftDeletable($model, false); 
        } 

        return true; 
    } 

    /** 
     * Run after a model has been saved, used to enable beforeFind() override. 
     * 
     * @param object $Model Model just saved. 
     * @param boolean $created True if this save created a new record 
     * @access public 
     */ 
    public function afterSave(Model $model, $created) 
    { 
        if (isset($this->__backAttributes[$model->alias]['find'])) 
        { 
            $this->enableSoftDeletable($model, 'find', $this->__backAttributes[$model->alias]['find']); 
            $this->enableSoftDeletable($model, 'delete', $this->__backAttributes[$model->alias]['delete']); 
            unset($this->__backAttributes[$model->alias]['find']); 
            unset($this->__backAttributes[$model->alias]['delete']); 
        } 
    } 
} 
?>