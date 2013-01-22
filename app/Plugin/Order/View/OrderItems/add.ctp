<?php
AppController::addInfoMsg(__('add_order_items_info')); 
	$structure_links = array(
		'top'=>'/Order/OrderItems/add/'.$atim_menu_variables['Order.id'].'/'.$atim_menu_variables['OrderLine.id'].'/',
		'bottom'=>array(
			'cancel'=>'/Order/OrderLines/detail/'.$atim_menu_variables['Order.id'].'/'.$atim_menu_variables['OrderLine.id'].'/'
		)
	);
	
	$final_atim_structure = $atim_structure; 
	$final_options = array('links'=>$structure_links, 'settings' => array('header' => __('line item', null)));
	
	// CUSTOM CODE
	$hook_link = $this->Structures->hook();
	if( $hook_link ) { require($hook_link); }
		
	// BUILD FORM
	$this->Structures->build( $final_atim_structure, $final_options );	
?>