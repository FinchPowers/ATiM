<?php

	$structure_links = array(
		'bottom'=>array(
			'add'=>'/Drug/Drugs/add/'
		)
	);
	
	$final_atim_structure = $atim_structure; 
	$final_options = array(
		'type'=>'search',
		'links'=> array('top'=>array('search'=>'/Drug/Drugs/search/'.AppController::getNewSearchId())),
		'settings' => array('actions' => false, 'header' => __('search type', null).': '.__('drugs', null)),
	);
	
	$final_atim_structure2 = $empty_structure;
	$final_options2 = array(
		'links'		=> $structure_links,
		'extras'	=> '<div class="ajax_search_results"></div>'
	);
	
	// CUSTOM CODE
	$hook_link = $this->Structures->hook();
	if( $hook_link ) { 
		require($hook_link); 
	}
		
	// BUILD FORM
	$this->Structures->build( $final_atim_structure, $final_options );
	$this->Structures->build( $final_atim_structure2, $final_options2 );

?>