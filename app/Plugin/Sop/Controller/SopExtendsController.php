<?php

class SopExtendsController extends SopAppController {

	var $uses = array(
		'Sop.SopExtend',
		'Sop.SopMaster',
		'Sop.SopControl',
		'Material.Material');
	var $paginate = array('SopMaster'=>array('limit' => pagination_amount,'order'=>'SopMaster.id DESC'));
	
	function listall($sop_master_id){
		$this->redirect('/Pages/err_plugin_system_error?method='.__METHOD__.',line='.__LINE__, null, true);
		$this->set('atim_menu_variables', array('SopMaster.id'=>$sop_master_id));
		
		$sop_master_data = $this->SopMaster->find('first', array('conditions'=>array('SopMaster.id'=>$sop_master_id)));
		
		$this->SopExtend = AppModel::atimInstantiateExtend($this->SopExtend, $sop_master_data['SopControl']['extend_tablename']);
		$use_form_alias = $sop_master_data['SopControl']['extend_form_alias'];
		$this->Structures->set($use_form_alias);
		
		$this->hook();
		
		$this->request->data = $this->paginate($this->SopExtend, array('SopExtend.sop_master_id'=>$sop_master_id));
		
		$material_list = $this->Material->find('all', array('fields'=>array('Material.id', 'Material.item_name'), 'order'  => array('Material.item_name')));
		$this->set('material_list', $material_list);
	}
	
	function detail($sop_master_id=null, $sop_extend_id=null) {
		$this->redirect('/Pages/err_plugin_system_error?method='.__METHOD__.',line='.__LINE__, null, true);
		$this->set('atim_menu_variables', array('SopMaster.id'=>$sop_master_id, 'SopExtend.id'=>$sop_extend_id));
		
		// Get treatment master row for extended data
		$sop_master_data = $this->SopMaster->find('first',array('conditions'=>array('SopMaster.id'=>$sop_master_id)));
		
		// Set form alias/tablename to use
		$this->SopExtend = AppModel::atimInstantiateExtend($this->SopExtend, $sop_master_data['SopControl']['extend_tablename']);
		$use_form_alias = $sop_master_data['SopControl']['extend_form_alias'];
	    $this->Structures->set($use_form_alias );

		$this->hook();
		
	    $this->request->data = $this->SopExtend->find('first',array('conditions'=>array('SopExtend.id'=>$sop_extend_id)));
	    
		$material_list = $this->Material->find('all', array('fields'=>array('Material.id', 'Material.item_name'), 'order'  => array('Material.item_name')));
		$this->set('material_list', $material_list);
		
	}

	function add($sop_master_id=null) {
		$this->redirect('/Pages/err_plugin_system_error?method='.__METHOD__.',line='.__LINE__, null, true);
		$this->set('atim_menu_variables', array('SopMaster.id'=>$sop_master_id));
		
		// Get treatment master row for extended data
		$sop_master_data = $this->SopMaster->find('first',array('conditions'=>array('SopMaster.id'=>$sop_master_id)));

		// Set form alias/tablename to use
		$this->SopExtend = AppModel::atimInstantiateExtend($this->SopExtend, $sop_master_data['SopControl']['extend_tablename']);
		$use_form_alias = $sop_master_data['SopControl']['extend_form_alias'];
	    $this->Structures->set($use_form_alias );

		$material_list = $this->Material->find('all', array('fields'=>array('Material.id', 'Material.item_name'), 'order'  => array('Material.item_name')));
		$this->set('material_list', $material_list);
		
		$this->hook();
		
		if ( !empty($this->request->data) ) {
			$this->request->data['SopExtend']['sop_master_id'] = $sop_master_data['SopMaster']['id'];
			if ( $this->SopExtend->save( $this->request->data ) ) {
				$this->atimFlash( 'your data has been saved', '/Sop/SopExtends/listall/'.$sop_master_id );
			}
		} 
	}

	function edit($sop_master_id=null, $sop_extend_id=null) {
		$this->redirect('/Pages/err_plugin_system_error?method='.__METHOD__.',line='.__LINE__, null, true);
		$this->set('atim_menu_variables', array(
			'SopMaster.id'=>$sop_master_id,
			'SopExtend.id'=>$sop_extend_id
		));
		
		// Get treatment master row for extended data
		$sop_master_data = $this->SopMaster->find('first',array('conditions'=>array('SopMaster.id'=>$sop_master_id)));
				
		// Set form alias/tablename to use
		$this->SopExtend = AppModel::atimInstantiateExtend($this->SopExtend, $sop_master_data['SopControl']['extend_tablename']);
		$use_form_alias = $sop_master_data['SopControl']['extend_form_alias'];
	    $this->Structures->set($use_form_alias);

	    $material_list = $this->Material->find('all', array('fields'=>array('Material.id', 'Material.item_name'), 'order'  => array('Material.item_name')));
		$this->set('material_list', $material_list);
	    
	    $this_data = $this->SopExtend->find('first',array('conditions'=>array('SopExtend.id'=>$sop_extend_id)));

		$this->hook();
		
	    if (!empty($this->request->data)) {
			$this->SopExtend->id = $sop_extend_id;
			if ($this->SopExtend->save($this->request->data)) {
				$this->atimFlash( 'your data has been updated','/Sop/SopExtends/detail/'.$sop_master_id.'/'.$sop_extend_id);
			}
		} else {
			$this->request->data = $this_data;
		}
	}

	function delete($sop_master_id=null, $sop_extend_id=null) {
		$this->redirect('/Pages/err_plugin_system_error?method='.__METHOD__.',line='.__LINE__, null, true);
		$this->hook();
	
		$this->SopExtend->del( $sop_extend_id );
		$this->atimFlash( 'your data has been deleted', '/Sop/SopExtends/listall/'.$sop_master_id );

	}

}

?>
