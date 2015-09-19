<?php 

	$structure_links = array();
	
	$structure_links['index'] = array(
		'aliquot details' => array(
				'link' => '/InventoryManagement/AliquotMasters/detail/%%AliquotMaster.collection_id%%/%%AliquotMaster.sample_master_id%%/%%AliquotMaster.id%%/',
				'icon' => 'aliquot'),
		'remove from order' => '/Order/OrderItems/delete/%%OrderItem.order_id%%/%%OrderItem.id%%/');
	if(!empty($atim_menu_variables['OrderLine.id'])){
		unset($structure_links['index']['order line details']); 
	}
	
	$structure_override = array();
	
	$final_atim_structure = $atim_structure; 
	$final_options = array(
		'type'=>'index',
		'links'=>$structure_links,
		'override'=>$structure_override,
		'settings'	=> array('batchset'	=> array('link' => '/Order/OrderItems/listall/'.$atim_menu_variables['Order.id'].'/'.$atim_menu_variables['OrderLine.id'], 'var' => 'aliquots_for_batchset'))
	);
	
	// CUSTOM CODE
	$hook_link = $this->Structures->hook();
	if( $hook_link ) { 
		require($hook_link); 
	}
		
	// BUILD FORM
	$this->Structures->build( $final_atim_structure, $final_options );
	
?>
