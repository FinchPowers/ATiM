<?php 

	$structure_links = array(
		'top' => '/StorageLayout/StorageMasters/edit/' . $atim_menu_variables['StorageMaster.id'],
		'bottom' => array('cancel' => '/StorageLayout/StorageMasters/detail/' . $atim_menu_variables['StorageMaster.id'])
	);

	$structure_override = array();
	
	if(isset($predefined_parent_storage_selection_label)) $structure_override['FunctionManagement.recorded_storage_selection_label'] = $predefined_parent_storage_selection_label;
	
	$final_atim_structure = $atim_structure; 
	$final_options = array('links'=>$structure_links, 'override'=>$structure_override);
	$final_options['settings']['no_sanitization']['StorageMaster'] = array('layout_description');
	
	// CUSTOM CODE
	$hook_link = $this->Structures->hook();
	if( $hook_link ) { require($hook_link); }
		
	// BUILD FORM
	$this->Structures->build( $final_atim_structure, $final_options );
	
?>