<?php 
	
	$structure_links = array(
		'index' => array(
			'edit' => '/StorageLayout/TmaSlideUses/edit/%%TmaSlideUse.id%%',
			'delete' => '/StorageLayout/TmaSlideUses/delete/%%TmaSlideUse.id%%')
	);	
	
	$final_atim_structure = $atim_structure; 
	$final_options = array('type' => 'index', 'links' => $structure_links);
	
	// CUSTOM CODE
	$hook_link = $this->Structures->hook();
	if( $hook_link ) { require($hook_link); }
		
	// BUILD FORM
	$this->Structures->build( $final_atim_structure, $final_options );			
?>
