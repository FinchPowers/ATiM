<?php 
	$structure_links = array(
		'bottom'=>array(
			'edit'=>'/Study/StudyInvestigators/edit/'.$atim_menu_variables['StudySummary.id'].'/%%StudyInvestigator.id%%/',
			'delete'=>'/Study/StudyInvestigators/delete/'.$atim_menu_variables['StudySummary.id'].'/%%StudyInvestigator.id%%/'
		)
	);
	
	// Set form structure and option 
	$final_atim_structure = $atim_structure; 
	$final_options = array(
		'settings'	=> array(
			'header' => __('study investigator')),
		'links'=>$structure_links);
	
	// CUSTOM CODE
	$hook_link = $this->Structures->hook();
	if( $hook_link ) { require($hook_link); }
		
	// BUILD FORM
	$this->Structures->build( $final_atim_structure, $final_options );
?>