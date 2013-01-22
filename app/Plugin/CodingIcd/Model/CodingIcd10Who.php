<?php

class CodingIcd10Who extends CodingIcdAppModel{

	protected static $singleton = null;
	
    var $name = 'CodingIcd10Who';
	var $useTable = 'coding_icd10_who';

	var $validate = array();

	function __construct(){
		parent::__construct();
		self::$singleton = $this;
	}
	
	static function validateId($id){
		return self::$singleton->globalValidateId($id);
	}
	
	static function getSingleton(){
		return self::$singleton;
	}
}
