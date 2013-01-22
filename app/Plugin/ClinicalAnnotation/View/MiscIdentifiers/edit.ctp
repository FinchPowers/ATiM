<?php 
	$structure_links = array(
		'top'=>'/ClinicalAnnotation/MiscIdentifiers/edit/'.$atim_menu_variables['Participant.id'].'/'.$atim_menu_variables['MiscIdentifier.id'].'/',
		'bottom'=>array('cancel'=>'/ClinicalAnnotation/Participants/profile/'.$atim_menu_variables['Participant.id'].'/')
	);
	
	$structure_override = array();

	// Set form structure and option
	$final_atim_structure = $atim_structure; 
	$final_options = array('links'=>$structure_links, 'override' => $structure_override);
	if($atim_structure['Structure'][0]['alias'] == "incrementedmiscidentifiers"){
		$final_options['settings']['header'] = __("generated identifier");
	}
	
	// CUSTOM CODE
	$hook_link = $this->Structures->hook();
	if( $hook_link ) { 
		require($hook_link); 
	}
		
	// BUILD FORM
	$this->Structures->build( $final_atim_structure, $final_options );
?>