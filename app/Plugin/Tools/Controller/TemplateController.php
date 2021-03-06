<?php
class TemplateController extends AppController {

	var $uses = array('Tools.Template', 'Tools.TemplateNode', 'Group');
	
	function beforeFilter() {
		parent::beforeFilter();
		$this->Auth->actionPath = 'controllers/';
	}
	
	function index(){
		$this->set('atim_menu', $this->Menus->get('/Tools/Template/index'));
		$this->Structures->set('template');
		
		$this->request->data = $this->Template->getTemplates('template edition');
		
		$hook_link = $this->hook('format');
		if( $hook_link ) {
			require($hook_link);
		}
	}
	
	/*
	 * Create the Collection Template setting properties
	*/
	function add() {
		$this->Structures->set('template');
		
		// CUSTOM CODE: FORMAT DISPLAY DATA		
		$hook_link = $this->hook('format');
		if( $hook_link ) { require($hook_link); }
		
		if ( !empty($this->request->data) ) {
			$submitted_data_validates = true;
			
			$this->Template->setOwnerAndVisibility($this->request->data);
			
			$hook_link = $this->hook('presave_process');
			if( $hook_link ) { 
				require($hook_link); 
			}	
			
			if ( $submitted_data_validates && $this->Template->save($this->request->data)){
				$template_id = $this->Template->getLastInsertId();
				$this->atimFlash(__('your data has been saved'),'/Tools/Template/edit/'.$template_id );
			}
		}
	}	
	
	/*
	 * Build The Collection Template
	 */ 
	function edit($template_id){
		//the following business rules apply to received data
		//controlId	= 0 -> collection root
		//			> 0 -> sample
		//			< 0 -> aliquot
		//
		//nodeId	= 0 -> collection root node
		//			< 0 -> node not in database
		//			> 0 -> node in database
	
		//validate access
		$tmp_template = $this->Template->getTemplates('template edition', $template_id);
		if(empty($tmp_template)) {
			$this->flash(__('you do not own that template'), '/Tools/Template/index/');
			return;
		}
		
		//js menus required data-------
		$sample_control_model = AppModel::getInstance("InventoryManagement", "SampleControl", true);
		$parent_to_derivative_sample_control_model = AppModel::getInstance("InventoryManagement", "ParentToDerivativeSampleControl", true);
		$sample_controls = $sample_control_model->find('all', array('fields' => array('id', 'sample_type'), 'recursive' => -1));
		$samples_relations = $parent_to_derivative_sample_control_model->find('all', array('conditions' => array('flag_active' => 1), 'recusrive' => -1));
		AppController::applyTranslation($sample_controls, 'SampleControl', 'sample_type');
	
		foreach($samples_relations as &$sample_relation){
			unset($sample_relation['ParentSampleControl']);
			unset($sample_relation['DerivativeControl']);
		}
		unset($sample_relation);
	
		$sample_controls = AppController::defineArrayKey($sample_controls, 'SampleControl', 'id', true);
		$samples_relations = AppController::defineArrayKey($samples_relations, 'ParentToDerivativeSampleControl', 'parent_sample_control_id');
	
		$aliquot_control_model = AppModel::getInstance("InventoryManagement", "AliquotControl", 1);
		$aliquot_controls = $aliquot_control_model->find('all', array('fields' => array('id', 'sample_control_id', 'aliquot_type'), 'conditions' => array('flag_active' => 1), 'recursive' => -1));
		AppController::applyTranslation($aliquot_controls, 'AliquotControl', 'aliquot_type');
		//-----------------------------
	
		$this->Structures->set('template_disabled');
	
		if(!empty($this->request->data)){
			//correct owner/visibility if needed
	
			//record the tree
			if($this->request->is('ajax')){
				//ajax request are made to save the template info
				//TODO validate this section is usefull or not
				Configure::write('debug', 0);
				$this->request->data['Template']['id'] = $template_id;
				$this->set('is_ajax', true);
			}else{
				//non ajax is made to save the tree
				$tree = json_decode('['.$this->request->data['tree'].']');
				array_shift($tree);//remove root
				$nodes_mapping = array();//for new nodes, key is the received node id, value is the db node
				$found_nodes = array();//already in db found nodes
	
				$this->TemplateNode->check_writable_fields = false;
				foreach($tree as $node){
					if($node->nodeId < 0){
						//create the node in Db
						$parent_id = null;
						if($node->parent_id < 0){
							$parent_id = $nodes_mapping[$node->parent_id];
						}else if($node->parent_id > 0){
							$parent_id = $node->parent_id;
						}
						$this->TemplateNode->data = array();
						$this->TemplateNode->id = null;
	
						$this->TemplateNode->save(array('TemplateNode' => array(
								'template_id'			=> $template_id,
								'parent_id'				=> $parent_id,
								'datamart_structure_id'	=> $node->datamart_structure_id,
								'control_id'			=> abs($node->controlId),
								'quantity'				=> $node->quantity
						)));
						$nodes_mapping[$node->nodeId] = $this->TemplateNode->id;
						$found_nodes[] = $this->TemplateNode->id;
					}else{
						$found_nodes[] = $node->nodeId;
						$this->TemplateNode->id = $node->nodeId;
						$this->TemplateNode->save(array('TemplateNode' => array('quantity' => $node->quantity)));
					}
				}
	
				$nodes_to_delete = $this->TemplateNode->find('list', array(
						'fields'		=> array('TemplateNode.id'),
						'conditions'	=> array('TemplateNode.template_id' => $template_id, 'NOT' => array('TemplateNode.id' => $found_nodes))
				));
				$nodes_to_delete = array_reverse($nodes_to_delete);
				foreach($nodes_to_delete as $node_to_delete){
					$this->TemplateNode->delete($node_to_delete);
				}
	
				$this->atimFlash(__('your data has been saved'), '/Tools/Template/edit/'.$template_id);
				return;
			}
		}
	
		//loading tree and setting variables
		$this->Template->id = $template_id;
		$this->request->data = $tmp_template;		
		$this->set('edit_properties', $tmp_template['Template']['allow_properties_edition']);
		
		$tree = $this->Template->init();
		$this->set('tree_data', $tree['']);
		$this->set('template_id', $template_id);
		$this->set('atim_menu', $this->Menus->get('/Tools/Template/index'));
		$js_data = array(
				'sample_controls' => $sample_controls,
				'samples_relations' => $samples_relations,
				'aliquot_controls' => AppController::defineArrayKey($aliquot_controls, "AliquotControl", "id", true),
				'aliquot_relations' => AppController::defineArrayKey($aliquot_controls, "AliquotControl", "sample_control_id")
		);
		$this->set('js_data', $js_data);
		$this->set('template_id', $template_id);
		$this->set('controls', 1);
		$this->set('collection_id', 0);
	
		$this->render('tree');
	}	
	
	function editProperties($template_id) {
		$template_data = $this->Template->getTemplates('template edition', $template_id);
		if(empty($template_data)) $this->redirect('/Pages/err_plugin_no_data?method='.__METHOD__.',line='.__LINE__, null, true);
		if(!$template_data['Template']['allow_properties_edition']) {
			$this->flash(__('you do not own that template'), '/Tools/Template/index/');
			return;
		}
		
		$this->set( 'atim_menu_variables', array('Template.id'=>$template_id) );
		$this->Structures->set('template');
		
		$hook_link = $this->hook('format');
		if( $hook_link ) { require($hook_link); }	
		
		if ( empty($this->request->data) ) {
			$this->request->data = $template_data;
		} else { 			
			$submitted_data_validates = true;
			
			$this->Template->setOwnerAndVisibility($this->request->data, $template_data['Template']['created_by']);
				
			$hook_link = $this->hook('presave_process');
			if( $hook_link ) { 
				require($hook_link); 
			}			
			
			if($submitted_data_validates) {
				$this->Template->id = $template_id;
				if ( $this->Template->save($this->request->data) ) {
					$hook_link = $this->hook('postsave_process');
					if( $hook_link ) {
						require($hook_link);
					}
					$this->atimFlash(__('your data has been updated'),'/Tools/Template/edit/'.$template_id );
				}
			}
		}
	}
	
	function delete($template_id){
		$template_data = $this->Template->getTemplates('template edition', $template_id);
		if(empty($template_data)) $this->redirect('/Pages/err_plugin_no_data?method='.__METHOD__.',line='.__LINE__, null, true);
		if(!$template_data['Template']['allow_properties_edition']) {
			$this->flash(__('you do not own that template'), '/Tools/Template/index/');
			return;
		}
		
		$nodes_to_delete = $this->TemplateNode->find('list', array(
			'fields'		=> array('TemplateNode.id'),
			'conditions'	=> array('TemplateNode.template_id' => $template_id)
		));
		$nodes_to_delete = array_reverse($nodes_to_delete);
		foreach($nodes_to_delete as $node_to_delete){
			$this->TemplateNode->delete($node_to_delete);
		}
		$this->Template->delete($template_id);
		
		$this->flash(__('your data has been deleted'), '/Tools/Template/index/');
	}
}
