<?php

class ViewStorageMaster extends StorageLayoutAppModel {
    var $primaryKey = 'id';
	var $base_model = "StorageMaster";
	var $base_plugin = 'StorageLayout';
	
	var $belongsTo = array(
		'StorageControl' => array(
			'className'    => 'StorageLayout.StorageControl',
			'foreignKey'    => 'storage_control_id'
		),'StorageMaster'	=> array(
			'className'    => 'StorageLayout.StorageMaster',
			'foreignKey'    => 'id',
			'type'			=> 'INNER')
	);
	
	var $alias = 'ViewStorageMaster';
	
	static $table_query = '
		SELECT StorageMaster.*, 
		StorageControl.is_tma_block,
		IF(coord_x_size IS NULL AND coord_y_size IS NULL, NULL, IFNULL(coord_x_size, 1) * IFNULL(coord_y_size, 1) - COUNT(AliquotMaster.id) - COUNT(TmaSlide.id) - COUNT(ChildStorageMaster.id)) AS empty_spaces 
		FROM storage_masters AS StorageMaster
		INNER JOIN storage_controls AS StorageControl ON StorageMaster.storage_control_id=StorageControl.id
		LEFT JOIN aliquot_masters AS AliquotMaster ON AliquotMaster.storage_master_id=StorageMaster.id AND AliquotMaster.deleted=0
		LEFT JOIN tma_slides AS TmaSlide ON TmaSlide.storage_master_id=StorageMaster.id AND TmaSlide.deleted=0
		LEFT JOIN storage_masters AS ChildStorageMaster ON ChildStorageMaster.parent_id=StorageMaster.id AND ChildStorageMaster.deleted=0
		WHERE StorageMaster.deleted=0 %%WHERE%% 
		GROUP BY StorageMaster.id, 
		StorageMaster.code, 
		StorageMaster.storage_control_id, 
		StorageMaster.parent_id, 
		StorageMaster.lft, 
		StorageMaster.rght, 
		StorageMaster.barcode, 
		StorageMaster.short_label, 
		StorageMaster.selection_label, 
		StorageMaster.storage_status, 
		StorageMaster.parent_storage_coord_x, 
		StorageMaster.parent_storage_coord_y, 
		StorageMaster.temperature, 
		StorageMaster.temp_unit, 
		StorageMaster.notes,
		StorageMaster.created,
		StorageMaster.created_by,
		StorageMaster.modified,
		StorageMaster.modified_by,
		StorageMaster.deleted,
		StorageControl.is_tma_block,
		StorageControl.coord_x_size,
		StorageControl.coord_y_size';
}

?>
