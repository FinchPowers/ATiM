<?php
	
if(!$is_main_form) {
	
	// Sub Form to display either Aliquots, sub-storages or tma slides list
	
	$structure_settings = array(
		'actions'	=> false
	);
	
	$structure_links = array('index' => array('detail' => array('link' => $detail_url, 'icon' => $icon)));
	

	$final_atim_structure = $atim_structure;
	$final_options = array(
		'type'		=> 'index', 
		'settings'	=> $structure_settings,
		'links' => $structure_links
	);

	// CUSTOM CODE
	$hook_link = $this->Structures->hook('list');
	if( $hook_link ) { 
		require($hook_link); 
	}
		
	// BUILD FORM
	$this->Structures->build( $final_atim_structure, $final_options ); 

} else {

	// Main form to display one to many lists
	
	if(isset($add_links)) {
		$structure_links = array('bottom'=>array('add to storage' => $add_links));	
	} else {
		$structure_links = array();
	}
	
	$models_nbr = sizeof($models_to_dispay);
	$form_counter = 0;
	foreach($models_to_dispay as $model => $header) {
		$form_counter++;
		$structure_settings = array(
			'actions'	=> ($form_counter == $models_nbr)? true : false,
			'header' 	=> array('title' => __($header), 'description' => null)
		);
	
		$final_options = array(
			'type'		=> 'detail', 
			'data'		=> array(), 
			'settings'	=> $structure_settings,
			'links'		=> $structure_links,
			'extras'	=> $this->Structures->ajaxIndex('StorageLayout/StorageMasters/contentListView/'.$atim_menu_variables['StorageMaster.id'].'/'.$model)
		);
		$final_atim_structure = $empty_structure;
		
		// CUSTOM CODE
		$hook_link = $this->Structures->hook();
		if( $hook_link ) require($hook_link); 
		
		// BUILD FORM
		$this->Structures->build( $final_atim_structure, $final_options ); 
	}
}
