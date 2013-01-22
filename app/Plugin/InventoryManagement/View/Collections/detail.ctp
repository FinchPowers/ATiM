<?php 

	$structure_links = array();
		
	$add_links = array();
	foreach ($specimen_sample_controls_list as $sample_control) {
		$add_links[__($sample_control['SampleControl']['sample_type'])] = '/InventoryManagement/SampleMasters/add/' . $atim_menu_variables['Collection.id'] . '/' . $sample_control['SampleControl']['id'];
	}
	ksort($add_links);
	
	$settings = array();
	if($is_ajax) $settings['header'] = __('collection');
	
	$bottom_links = array(
		'edit'						=> '/InventoryManagement/Collections/edit/' . $atim_menu_variables['Collection.id'],
		'delete'					=> '/InventoryManagement/Collections/delete/' . $atim_menu_variables['Collection.id'],
		'copy for new collection'	=> array('link' => '/InventoryManagement/Collections/add/0/'.$atim_menu_variables['Collection.id'], 'icon' => 'copy'),
		'print barcodes'			=> array('link' => '/InventoryManagement/AliquotMasters/printBarcodes/model:Collection/id:'.$atim_menu_variables['Collection.id'], 'icon' => 'barcode'),
		'add specimen'				=> $add_links,
		'add from template'			=> $templates,
	);
	if(empty($participant_id)){
		$bottom_links['participant data'] = '/underdevelopment/';
	}else{
		$bottom_links['participant data'] = array(
			'profile'		=> array(
				'icon'	=> 'participant',
				'link'	=> '/ClinicalAnnotation/Participants/profile/' . $participant_id),
			'participant inventory'	=> array(
				'icon'	=> 'participant',
				'link'	=> '/ClinicalAnnotation/ClinicalCollectionLinks/detail/' . $participant_id . '/' . $atim_menu_variables['Collection.id']
			) 
		);
	}
			
	$structure_links['bottom'] = $bottom_links;
		
	$final_atim_structure = $atim_structure; 
	$final_options = array('links' => $structure_links, 'settings' => $settings);
	
	if(!$is_ajax && !empty($sample_data)){
		$final_options['settings']['actions'] = false;
	}
	
	// CUSTOM CODE
	$hook_link = $this->Structures->hook();
	if( $hook_link ) { 
		require($hook_link); 
	}
		
	// BUILD FORM
	$this->Structures->build( $final_atim_structure, $final_options );

	if(!$is_ajax && !empty($sample_data)){
		$structure_settings = array(
			'tree'=>array(
				'SampleMaster'		=> 'SampleMaster'
			), 'header' => __('collection contents')
		);
		$structure_links['tree'] = array(
			'SampleMaster' => array(
				'detail' => array(
					'link' => '/InventoryManagement/SampleMasters/detail/%%SampleMaster.collection_id%%/%%SampleMaster.id%%/1/',
					'icon' => 'flask'
				), 'access to all data' => array(
					'link'=> '/InventoryManagement/SampleMasters/detail/%%SampleMaster.collection_id%%/%%SampleMaster.id%%/',
					'icon' => 'detail'
				)
			)
		);
		$structure_links['tree_expand'] = array(
			'SampleMaster' => '/InventoryManagement/SampleMasters/contentTreeView/%%SampleMaster.collection_id%%/%%SampleMaster.id%%/1/'
		);
		$structure_links['ajax'] = array(
			'index' => array(
				'detail' => array(
					'json' => array(
						'update' => 'frame',
						'callback' => 'set_at_state_in_tree_root'
					)
				)
			)
		);
		$final_options = array(
			'type' => 'tree',
				'data' => $sample_data		
		);
		
		
		
		
		$structure_extras = array();
		$structure_extras[10] = '<div id="frame"></div>';
		
		// BUILD
		$final_atim_structure = array('SampleMaster' => $sample_masters_for_collection_tree_view);
		$final_options = array(
			'type'		=> 'tree',
			'data'		=> $sample_data, 
			'settings'	=> $structure_settings, 
			'links'		=> $structure_links, 
			'extras'	=> $structure_extras
		);
		
		// CUSTOM CODE
		$hook_link = $this->Structures->hook();
		if( $hook_link ) {
			require($hook_link);
		}
		
		// BUILD FORM
		$this->Structures->build( $final_atim_structure, $final_options );
	}
	
?>