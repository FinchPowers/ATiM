<?php
require_once('Cake/Controller/Component/Acl/DbAcl.php');

class AtimAcl extends DbAcl {
/**
 * Constructor
 *
 */
	function __construct() {
		parent::__construct();
		if (!class_exists('AclNode')) {
			uses('model' . DS . 'db_acl');
		}
		$this->Aro = ClassRegistry::init(array('class' => 'AtimAro', 'alias' => 'Aro'));
		$this->Aco = ClassRegistry::init(array('class' => 'AtimAco', 'alias' => 'Aco'));
	}
	
/**
 * Checks if the given $aro has access to action $action in $aco
 *
 * @param string $aro ARO
 * @param string $aco ACO
 * @param string $action Action (defaults to *)
 * @return boolean Success (true if ARO has access to action in ACO, false otherwise)
 * @access public
 */
	function check($aro, $aco, $action = "*") {
		if ($aro == null || $aco == null) {
			return false;
		}

		$permKeys = $this->_getAcoKeys($this->Aro->Permission->schema());
		$aroPath = $this->Aro->node($aro);
		$acoPath = $this->Aco->node($aco);
		
		if (empty($aroPath) || empty($acoPath)) {
			//trigger_error("DbAcl::check() - Failed ARO/ACO node lookup in permissions check.  Node references:\nAro: " . print_r($aro, true) . "\nAco: " . print_r($aco, true), E_USER_WARNING);
			return false;
		}
		
		if ($acoPath == null || $acoPath == array()) {
			//trigger_error("DbAcl::check() - Failed ACO node lookup in permissions check.  Node references:\nAro: " . print_r($aro, true) . "\nAco: " . print_r($aco, true), E_USER_WARNING);
			return false;
		}

		$aroNode = $aroPath[0];
		$acoNode = $acoPath[0];

		if ($action != '*' && !in_array('_' . $action, $permKeys)) {
			//trigger_error(sprintf(__("ACO permissions key %s does not exist in DbAcl::check()"), $action), E_USER_NOTICE);
			return false;
		}

		$inherited = array();
		$acoIDs = Set::extract($acoPath, '{n}.' . $this->Aco->alias . '.id');

		$count = count($aroPath);
		for ($i = 0 ; $i < $count; $i++) {
			$permAlias = $this->Aro->Permission->alias;

			$perms = $this->Aro->Permission->find('all', array(
				'conditions' => array(
					"{$permAlias}.aro_id" => $aroPath[$i][$this->Aro->alias]['id'],
					"{$permAlias}.aco_id" => $acoIDs
				),
				'order' => array($this->Aco->alias . '.lft' => 'desc'),
				'recursive' => 0
			));

			if (empty($perms)) {
				continue;
			} else {
				$perms = Set::extract($perms, '{n}.' . $this->Aro->Permission->alias);
				foreach ($perms as $perm) {
					if ($action == '*') {

						foreach ($permKeys as $key) {
							if (!empty($perm)) {
								if ($perm[$key] == -1) {
									return false;
								} elseif ($perm[$key] == 1) {
									$inherited[$key] = 1;
								}
							}
						}

						if (count($inherited) === count($permKeys)) {
							return true;
						}
					} else {
						switch ($perm['_' . $action]) {
							case -1:
								return false;
							case 0:
								continue;
							break;
							case 1:
								return true;
							break;
						}
					}
				}
			}
		}
		return false;
	}

}
