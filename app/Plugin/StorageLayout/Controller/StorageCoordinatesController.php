<?php

class StorageCoordinatesController extends StorageLayoutAppController {
	
	var $components = array();
	
	var $uses = array(
		'StorageLayout.StorageControl',
		'StorageLayout.StorageCoordinate',
		'StorageLayout.StorageMaster',
		
		'InventoryManagement.AliquotMaster');
	
	var $paginate = array('StorageCoordinate' => array('order' => 'StorageCoordinate.order ASC'));

	/* --------------------------------------------------------------------------
	 * DISPLAY FUNCTIONS
	 * -------------------------------------------------------------------------- */	
	
	 function listAll($storage_master_id) {

		// MANAGE DATA
		
		// Get the storage data
		$storage_data = $this->StorageMaster->getOrRedirect($storage_master_id);
		
		if(!$storage_data['StorageControl']['is_tma_block']) {
			// Get all storage control types to build the add to selected button
			$this->set('storage_types_from_id', $this->StorageControl->getStorageTypePermissibleValues());
		}
		
		if(!$this->StorageControl->allowCustomCoordinates($storage_data['StorageControl']['id'], array('StorageControl' => $storage_data['StorageControl']))) {
			// Check storage supports custom coordinates
			$this->redirect('/Pages/err_plugin_system_error?method='.__METHOD__.',line='.__LINE__, null, true); 
		}
		
		// Get storage coordinates
		$this->request->data = $this->paginate($this->StorageCoordinate, array('StorageCoordinate.storage_master_id' => $storage_master_id));
			
		// MANAGE FORM, MENU AND ACTION BUTTONS
		
		$this->Structures->set('storage_coordinates');	
		$this->set('atim_menu_variables', array('StorageMaster.id' => $storage_master_id));

		// CUSTOM CODE: FORMAT DISPLAY DATA
		
		$hook_link = $this->hook('format');
		if( $hook_link ) { require($hook_link); }
	}
	
	function add($storage_master_id) {
		// MANAGE DATA
		
		// Get the storage data
		$storage_data = $this->StorageMaster->getOrRedirect($storage_master_id);

		if(!$this->StorageControl->allowCustomCoordinates($storage_data['StorageControl']['id'], array('StorageControl' => $storage_data['StorageControl']))) {
			// Check storage supports custom coordinates
			$this->redirect('/Pages/err_plugin_system_error?method='.__METHOD__.',line='.__LINE__, null, true); 
		}

		// MANAGE FORM, MENU AND ACTION BUTTONS
		
		$this->Structures->set('storage_coordinates');	
		$this->set('atim_menu_variables', array('StorageMaster.id' => $storage_master_id));

		// CUSTOM CODE: FORMAT DISPLAY DATA
		
		$hook_link = $this->hook('format');
		if( $hook_link ) { require($hook_link); }
		
		if (!empty($this->request->data)) {	
			// Set dimension
			$this->request->data['StorageCoordinate']['dimension'] = 'x';

			// Set storage id
			$this->request->data['StorageCoordinate']['storage_master_id'] = $storage_master_id;
			
			// Validates data
			$submitted_data_validates = true;
			
			if($this->StorageCoordinate->isDuplicatedValue($storage_master_id, $this->request->data['StorageCoordinate']['coordinate_value'])) {
				$submitted_data_validates = false;
			}
			
			if($this->StorageCoordinate->isDuplicatedOrder($storage_master_id, $this->request->data['StorageCoordinate']['order'])) {
				$submitted_data_validates = false;
			}
			
			// CUSTOM CODE: PROCESS SUBMITTED DATA BEFORE SAVE
			
			$hook_link = $this->hook('presave_process');
			if( $hook_link ) { 
				require($hook_link); 
			}		
			
			if($submitted_data_validates) {
				// Save data		
				$this->StorageCoordinate->addWritableField(array('dimension','storage_master_id'));
				if ($this->StorageCoordinate->save($this->request->data['StorageCoordinate'])) {
					$hook_link = $this->hook('postsave_process');
					if( $hook_link ) {
						require($hook_link);
					}
					$this->atimFlash(__('your data has been saved'), '/StorageLayout/StorageCoordinates/listAll/' . $storage_master_id);				
				}
			}
		}
	}
	 
	function delete($storage_master_id, $storage_coordinate_id) {

		// MANAGE DATA
		
		// Get the storage data
		$storage_data = $this->StorageMaster->getOrRedirect($storage_master_id);

		if(!$this->StorageControl->allowCustomCoordinates($storage_data['StorageControl']['id'], array('StorageControl' => $storage_data['StorageControl']))) {
			// Check storage supports custom coordinates
			$this->redirect('/Pages/err_plugin_system_error?method='.__METHOD__.',line='.__LINE__, null, true); 
		}
		
		// Get the coordinate data
		$storage_coordinate_data = $this->StorageCoordinate->find('first', array('conditions' => array('StorageCoordinate.id' => $storage_coordinate_id, 'StorageCoordinate.storage_master_id' => $storage_master_id)));
		if(empty($storage_coordinate_data)) { 
			$this->redirect('/Pages/err_plugin_no_data?method='.__METHOD__.',line='.__LINE__, null, true); 
		}		

		// Check deletion is allowed
		$arr_allow_deletion = $this->StorageCoordinate->allowDeletion($storage_master_id, $storage_coordinate_data);

		// CUSTOM CODE
		
		$hook_link = $this->hook('delete');
		if( $hook_link ) { require($hook_link); }
		
		$flash_url = '/StorageLayout/StorageCoordinates/listAll/' . $storage_master_id;
		
		if($arr_allow_deletion['allow_deletion']) {
			// Delete coordinate
			if($this->StorageCoordinate->atimDelete($storage_coordinate_id)) {
				$hook_link = $this->hook('postsave_process');
				if( $hook_link ) { 
					require($hook_link); 
				}
				$this->atimFlash(__('your data has been deleted'), $flash_url);
			} else {
				$this->flash(__('error deleting data - contact administrator'), $flash_url);
			}		
		
		} else {
			$this->flash(__($arr_allow_deletion['msg']), $flash_url);
		}			
	}
}

?>
