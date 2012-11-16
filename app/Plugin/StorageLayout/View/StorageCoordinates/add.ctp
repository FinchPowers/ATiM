<?php 
	
	$structure_links = array(
		'top' => '/StorageLayout/StorageCoordinates/add/' . $atim_menu_variables['StorageMaster.id'],
		'bottom' => array('cancel' => '/StorageLayout/StorageCoordinates/listAll/' . $atim_menu_variables['StorageMaster.id'])
	);
	
	$final_atim_structure = $atim_structure; 
	$final_options = array('links' => $structure_links);
	
	// CUSTOM CODE
	$hook_link = $this->Structures->hook();
	if( $hook_link ) { require($hook_link); }
		
	// BUILD FORM
	$this->Structures->build( $final_atim_structure, $final_options );	
?>