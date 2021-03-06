<?php

class SpecimenReviewMaster extends InventoryManagementAppModel {
	var $belongsTo = array(        
		'SpecimenReviewControl' => array(            
			'className'		=> 'InventoryManagement.SpecimenReviewControl',            
			'foreignKey'	=> 'specimen_review_control_id',
			'type'			=> 'INNER'
		)
	);
	
	var $registered_view = array(
		'InventoryManagement.ViewAliquotUse' => array('SpecimenReviewMaster.id')
	);
	
	function allowSpecimeReviewDeletion($specimen_review_id){
		return array('allow_deletion' => true, 'msg' => '');
	}
}

?>
