<?php
	
	$structure_links = array(
		'top'	=>'/Order/Shipments/addToShipment/'.$atim_menu_variables['Order.id'].'/'.$atim_menu_variables['Shipment.id'].'/',
		'bottom'=>array('cancel' => '/Order/Shipments/detail/'.$atim_menu_variables['Order.id'].'/'.$atim_menu_variables['Shipment.id'].'/'),
		'checklist' => array('OrderItem.id][' => '%%OrderItem.id%%')
	);
	
	$structure_settings = array('pagination' => false, 'header' => __('add items to shipment', null), 'actions' => false, 'form_inputs' => false, 'form_bottom' => false);
	
	$final_atim_structure = $atim_structure; 
	$final_atim_structure_with_order_lines =  $atim_structure_with_order_lines;
	$final_options = array(
		'type'		=> 'index', 
		'links'		=> $structure_links, 
		'settings'	=> $structure_settings, 
	);
	
	// CUSTOM CODE
	$hook_link = $this->Structures->hook();
	
	// BUILD FORM
	while($data = array_shift($this->request->data)){
		$linked_to_order_line = $data['order_line_id']? true : false;
		if(empty($this->request->data)){
			$final_options['settings']['actions'] = true;
			$final_options['settings']['form_bottom'] = true;
		}
		$final_options['settings']['language_heading'] = $linked_to_order_line? __('line').': '.$data['name'] : null;
		$final_options['data'] = $data['data'];
		if( $hook_link ) {
			require($hook_link);
		}
		$this->Structures->build( ($linked_to_order_line? $final_atim_structure_with_order_lines : $final_atim_structure), $final_options );
		
		$final_options['settings']['header'] = array();
	}
	
?>
