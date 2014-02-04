<?php

class CollectionsController extends InventoryManagementAppController {
	
	var $components = array();
		
	var $uses = array(
		'InventoryManagement.Collection',
		'InventoryManagement.ViewCollection',
		'InventoryManagement.SampleMaster',
		'InventoryManagement.SampleControl',
		'InventoryManagement.AliquotMaster',
		'InventoryManagement.SpecimenReviewMaster',
		'InventoryManagement.ParentToDerivativeSampleControl',
		'InventoryManagement.SpecimenDetail',//here for collection template validation
		'InventoryManagement.DerivativeDetail',//here for collection template validation
		
		'ExternalLink'
	);
	
	var $paginate = array(
		'Collection' 		=> array('limit' => pagination_amount, 'order' => 'Collection.acquisition_label ASC'),
		'ViewCollection'	=> array('limit' => pagination_amount, 'order' => 'ViewCollection.acquisition_label ASC')
	);
	
	function search($search_id = 0, $is_ccl_ajax = false){
		if($is_ccl_ajax && $this->request->data){
			//custom result handling for ccl
			$view_collection = $this->Structures->get('form', 'view_collection');
			$this->set('atim_structure', $view_collection);
			$this->Structures->set('empty', 'empty_structure');
			$conditions = $this->Structures->parseSearchConditions($view_collection);
			$limit = 20;
			$conditions[] = "ViewCollection.participant_id IS NULL";
			$this->request->data = $this->ViewCollection->find('all', array('conditions' => $conditions, 'limit' => $limit + 1));
			foreach($this->request->data as &$d){
				unset($d['Collection']['id']);//to avoid auto selection
			}
			if(count($this->request->data) > $limit){
				unset($this->request->data[$limit]);
				$this->set("overflow", true);
			}
		}else if(isset($this->passedArgs['unlinkedParticipants'])){
			$group_model = AppModel::getInstance('', 'Group');
			$group = $group_model->find('first', array('conditions' => array('Group.id' => $this->Session->read('Auth.User.group_id'))));
			$collection_model = AppModel::getInstance('InventoryManagement', 'Collection');
			$conditions = array('ViewCollection.collection_property' => 'participant collection', 'ViewCollection.participant_id' => null);
			if($group['Group']['bank_id']){
				$this->set('bank_filter', true);
				$conditions['ViewCollection.bank_id'] = $group['Group']['bank_id'];
			}
			$this->Structures->set('view_collection');
			$this->request->data = $this->paginate($this->ViewCollection, $conditions);
		}else{
			$this->searchHandler($search_id, $this->ViewCollection, 'view_collection', '/InventoryManagement/Collections/search');
		}
		
		$help_url = $this->ExternalLink->find('first', array('conditions' => array('name' => 'inventory_elements_defintions')));
		$this->set("help_url", $help_url['ExternalLink']['link']);
		if($is_ccl_ajax){
			$this->set('is_ccl_ajax', $is_ccl_ajax);
		}
	
		// CUSTOM CODE: FORMAT DISPLAY DATA
		$hook_link = $this->hook('format');
		if( $hook_link ) { 
			require($hook_link); 
		}
		
		if(empty($search_id)){
			//index
			if(!isset($this->request->query['nolatest'])){
        		$this->request->data = $this->ViewCollection->find('all', 
        				array('conditions' => array('Collection.created_by' => $this->Session->read('Auth.User.id')),
        				      'order' => array('Collection.created DESC'), 
        					  'limit' => 5)
        		);
			}
			$this->render('index');
		}
	}
	
	function detail($collection_id, $hide_header = false){
		unset($_SESSION['InventoryManagement']['TemplateInit']);
		
		// MANAGE DATA
		$this->request->data = $this->ViewCollection->getOrRedirect($collection_id);
		
		// Set participant id
		$this->set('participant_id', $this->request->data['ViewCollection']['participant_id']);
		
		// Get all sample control types to build the add to selected button
		$controls = $this->SampleControl->getPermissibleSamplesArray(null);
		$this->set('specimen_sample_controls_list', $controls);	
		
		// MANAGE FORM, MENU AND ACTION BUTTONS
		
		$this->set('atim_menu_variables', array('Collection.id' => $collection_id));
		$this->Structures->set('view_collection');

		// Define if this detail form is displayed into the collection content tree view
		$this->set('is_ajax', $this->request->is('ajax'));
		$this->set('hide_header', $hide_header);
		
		$template_model = AppModel::getInstance("Tools", "Template", true);
		$templates = $template_model->getAddFromTemplateMenu($collection_id);
		$this->set('templates', $templates);
		
		if(!$this->request->is('ajax')){
			$this->Structures->set('sample_masters_for_collection_tree_view', 'sample_masters_for_collection_tree_view');
			$sample_data = $this->SampleMaster->find('all', array('conditions' => array('SampleMaster.collection_id' => $collection_id, 'SampleMaster.parent_id' => null), 'recursive' => 0));
			$ids = array();
			foreach($sample_data as $unit){
				$ids[] = $unit['SampleMaster']['id'];
			}
			$ids = array_flip($this->SampleMaster->hasChild($ids));//array_key_exists is faster than in_array
			foreach($sample_data as &$unit){
				$unit['children'] = array_key_exists($unit['SampleMaster']['id'], $ids);
			}
			$this->set('sample_data', $sample_data);
		}
		
		// CUSTOM CODE: FORMAT DISPLAY DATA
		
		$hook_link = $this->hook('format');
		if( $hook_link ) { 
			require($hook_link); 
		}
	}
	
	function add($collection_id = 0, $copy_source = 0) {
		$collection_data = null;
		if($collection_id > 0){
			$collection_data = $this->Collection->find('first', array('conditions' => array('Collection.id' => $collection_id, 'Collection.deleted' => 1), 'recursive' => '1'));
		}
		// MANAGE FORM, MENU AND ACTION BUTTONS
		
		if(!empty($collection_data)){
			$this->Structures->set('linked_collections');
		}
		
		$this->set('atim_variables', array('Collection.id' => $collection_id));
		$this->set('atim_menu', $this->Menus->get('/InventoryManagement/Collections/search'));
		$this->set('copy_source', $copy_source);
		
		// CUSTOM CODE: FORMAT DISPLAY DATA
		
		$need_to_save = !empty($this->request->data);
		if(!empty($this->request->data) && !array_key_exists('collection_property', $this->request->data['Collection'])) {
			// Set collection property to 'participant collection' if field collection property is hidden in add form (default value)
			$this->request->data['Collection']['collection_property'] = 'participant collection';
			$this->Collection->addWritableField('collection_property');
		}
		if(empty($this->request->data) || isset($this->request->data['FunctionManagement']['col_copy_binding_opt'])){
			if(!empty($copy_source)){
				if(empty($this->request->data)){
					$this->request->data = $this->Collection->getOrRedirect($copy_source);
				}
				if($this->request->data['Collection']['collection_property'] == 'participant collection'){
					$this->Structures->set('collections,col_copy_binding_opt');
				}
			}
			$this->request->data['Generated']['field1'] = (!empty($collection_data)) ? $collection_data['Participant']['participant_identifier'] : __('n/a');
		}
		
		$hook_link = $this->hook('format');
		if( $hook_link ) { 
			require($hook_link); 
		}
		
		if($need_to_save){
			
			$copy_src_data = null;
			if($copy_source){
				$copy_src_data = $this->Collection->getOrRedirect($copy_source);
			}
			
			$copy_links_option = isset($this->request->data['FunctionManagement']['col_copy_binding_opt']) ? (int)$this->request->data['FunctionManagement']['col_copy_binding_opt'] : 0;
			if($copy_source){
				if($copy_links_option > 0 && $this->request->data['Collection']['collection_property'] == 'independent collection'){
					AppController::addWarningMsg(__('links were not copied since the destination is an independant collection'));
				}else if($copy_links_option > 1){
					$classic_ccl_insert = false;
					$this->request->data['Collection']['participant_id'] = $copy_src_data['Collection']['participant_id'];
					$this->Collection->addWritableField('participant_id');
					if($copy_links_option == 6){
						$this->Collection->addWritableField(array('consent_master_id', 'diagnosis_master_id', 'treatment_master_id', 'event_master_id'));
						$this->request->data['Collection'] = array_merge($this->request->data['Collection'],
							array(
								'consent_master_id' 	=> $copy_src_data['Collection']['consent_master_id'],
								'diagnosis_master_id'	=> $copy_src_data['Collection']['diagnosis_master_id'],
								'treatment_master_id'	=> $copy_src_data['Collection']['treatment_master_id'],
								'event_master_id'	=> $copy_src_data['Collection']['event_master_id']
							)
						);
					}
				}
			}
			$this->request->data['Collection']['deleted'] = 0;
			$this->Collection->addWritableField('deleted');
			
			// LAUNCH SAVE PROCESS
			$submitted_data_validates = true;
			
			// HOOK AND VALIDATION
			$hook_link = $this->hook('presave_process');
			if( $hook_link ) { 
				require($hook_link); 
			}			
			
			if($submitted_data_validates) {

				//SAVE
				if($collection_data){
					$this->Collection->id = $collection_id;
				}else{
					$this->Collection->id = 0;
					$this->Collection->data = null;
				}
				if($this->Collection->save($this->request->data)){
					$hook_link = $this->hook('postsave_process');
					if( $hook_link ) {
						require($hook_link);
					}
					$collection_id = $collection_id ?: $this->Collection->getLastInsertId();
					$this->atimFlash(__('your data has been saved'), '/InventoryManagement/Collections/detail/' . $collection_id);
				}
			}
		}
	}
	
	function edit($collection_id) {
		$this->Collection->unbindModel(array('hasMany' => array('SampleMaster')));		
		$collection_data = $this->Collection->getOrRedirect($collection_id);
				
		// MANAGE FORM, MENU AND ACTION BUTTONS
		$this->set('atim_menu_variables', array('Collection.id' => $collection_id));		
		
		if($collection_data['Collection']['participant_id']){
			// Linked collection: Set specific structure
			$this->Structures->set('linked_collections');	
		}

		// CUSTOM CODE: FORMAT DISPLAY DATA
		
		$hook_link = $this->hook('format');
		if( $hook_link ) { 
			require($hook_link); 
		}
		
		if(empty($this->request->data)) {
			$this->request->data = $collection_data;	

		}else{
			
			$submitted_data_validates = true;

			// CUSTOM CODE: PROCESS SUBMITTED DATA BEFORE SAVE
			
			$hook_link = $this->hook('presave_process');
			if( $hook_link ) { 
				require($hook_link); 
			}			
			
			if($submitted_data_validates) {
				
				// 4- SAVE
				$this->Collection->id = $collection_id;
				$this->Collection->data = array();
				if ($this->Collection->save($this->request->data)){
					$hook_link = $this->hook('postsave_process');
					if( $hook_link ) { 
						require($hook_link); 
					}
					$this->atimFlash(__('your data has been updated'), '/InventoryManagement/Collections/detail/' . $collection_id);
				}
			}
		}
	}
	
	function delete($collection_id) {
		// Get collection data
		$collection_data = $this->Collection->getOrRedirect($collection_id);
		
		// Check deletion is allowed
		$arr_allow_deletion = $this->Collection->allowDeletion($collection_id);
		
		// CUSTOM CODE
				
		$hook_link = $this->hook('delete');
		if( $hook_link ) { 
			require($hook_link); 
		}		
		
		if($arr_allow_deletion['allow_deletion']) {
			// Delete collection			
			if($this->Collection->atimDelete($collection_id, true)) {
				$this->atimFlash(__('your data has been deleted'), '/InventoryManagement/Collections/search/');
			} else {
				$this->flash(__('error deleting data - contact administrator'), '/InventoryManagement/Collections/search/');
			}		
		
		} else {
			$this->flash(__($arr_allow_deletion['msg']), '/InventoryManagement/Collections/detail/' . $collection_id);
		}		
	}
	
	function template($collection_id, $template_id){
		$this->set('atim_menu_variables', array('Collection.id' => $collection_id));
		$template_model = AppModel::getInstance("Tools", "Template", true);
		$template_model->id = $template_id;
		$template = $template_model->read();
		$tree = $template_model->init();
		$this->set('tree_data', $tree['']);
		
		$sample_controls = $this->SampleControl->find('all');
		$sample_controls = AppController::defineArrayKey($sample_controls, 'SampleControl', 'id', true);
		AppController::applyTranslation($sample_controls, 'SampleControl', 'sample_type');
		
		$aliquot_control_model = AppModel::getInstance('InventoryManagement', 'AliquotControl', true);
		$aliquot_controls = $aliquot_control_model->find('all', array('fields' => array('id', 'sample_control_id', 'aliquot_type'), 'conditions' => array('flag_active' => 1), 'recursive' => -1));
		$aliquot_controls = AppController::defineArrayKey($aliquot_controls, 'AliquotControl', 'id', true);
		AppController::applyTranslation($aliquot_controls, 'AliquotControl', 'aliquot_type');
		
		$parent_to_derivative_sample_control_model = AppModel::getInstance("InventoryManagement", "ParentToDerivativeSampleControl", true);
		$samples_relations = $parent_to_derivative_sample_control_model->find('all', array('conditions' => array('flag_active' => 1), 'recusrive' => -1));
		foreach($samples_relations as &$sample_relation){
			unset($sample_relation['ParentSampleControl']);
			unset($sample_relation['DerivativeControl']);
		}
		unset($sample_relation);
		$samples_relations = AppController::defineArrayKey($samples_relations, 'ParentToDerivativeSampleControl', 'parent_sample_control_id');
		
		
		$js_data = array(
			'sample_controls' => $sample_controls,
			'samples_relations' => $samples_relations,
			'aliquot_controls' => AppController::defineArrayKey($aliquot_controls, 'AliquotControl', 'id', true),
			'aliquot_relations' => AppController::defineArrayKey($aliquot_controls, "AliquotControl", "sample_control_id")
		);
		
		
		$this->set('js_data', $js_data);
		$this->set('template_id', empty($template)? null : $template['Template']['id']);
		$this->set('controls', 0);
		$this->set('collection_id', $collection_id);
		$this->set('flag_system', empty($template)? null : $template['Template']['flag_system']);
		$this->set('structure_header', array('title' => __('samples and aliquots creation from template'), 'description' => empty($template)? null : __('collection template') .': '.__($template['Template']['name'])));
		$this->Structures->set('template');
		$this->request->data = empty($template)? null : $template;
		$this->render('/../../Tools/View/Template/tree');
	}
	
	function templateInit($collection_id, $template_id){
		$template = null;
		if($template_id != 0){
			$template_model = AppModel::getInstance("Tools", "Template", true);
			$template = $template_model->findById($template_id);
			$template_model->init($template_id);
		}
		$this->set('template', $template);
		$this->set('template_id', $template_id);
		
		$this->TemplateInit = AppModel::getInstance('InventoryManagement', 'TemplateInit');
		$to_begin_msg = true;//can be overriden in hooks
		$this->Structures->set('template_init_structure', 'template_init_structure');
		
		$this->set('collection_id', $collection_id);
		
		$hook_link = $this->hook('format');
		if( $hook_link ) { 
			require($hook_link); 
		}
		
		$template_init_id = null;
		if(!empty($this->request->data)){
			//validate and stuff
			$data_validates = true;
			
			if(isset($this->request->data['template_init_id'])){
				$template_init_id = $this->request->data['template_init_id'];
			}
			
			$this->TemplateInit->set($this->request->data);
			if(!$this->TemplateInit->validates()){
				$data_validates = false;						
			}else{
				$this->request->data = $this->TemplateInit->data['TemplateInit']; 
			}

			//hook
			$hook_link = $this->hook('validate_and_set');
			if( $hook_link ) { 
				require($hook_link); 
			}
			
			if($data_validates && $template_init_id){
				$this->Session->write('Template.init_data.'.$template_init_id, $this->request->data); 
				$this->set('goToNext', true);
			}
		}
		
		if($template_init_id == null){
			if($template_init_id = $this->Session->read('Template.init_id')){
				++ $template_init_id;
			}else{
				$template_init_id = 1;
			}
			$this->Session->write('Template.init_id', $template_init_id);
		}
		$this->set('template_init_id', $template_init_id);
		
		if($to_begin_msg){
			AppController::addInfoMsg(__('to begin, click submit'));
		}
	}
}

?>