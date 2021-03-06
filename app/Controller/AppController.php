<?php
/**
 * Application level Controller
 *
 * This file is application-wide controller file. You can put all
 * application-wide controller-related methods here.
 *
 * CakePHP(tm) : Rapid Development Framework (http://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 * @link          http://cakephp.org CakePHP(tm) Project
 * @package       app.Controller
 * @since         CakePHP(tm) v 0.2.9
 * @license       MIT License (http://www.opensource.org/licenses/mit-license.php)
 */
App::uses('Controller', 'Controller');

/**
 * Application Controller
 *
 * Add your application-wide methods in the class below, your controllers
 * will inherit them.
 *
 * @package		app.Controller
 * @link		http://book.cakephp.org/2.0/en/controllers.html#the-app-controller
 */
class AppController extends Controller {
    private static $missing_translations = array();
	private static $me = NULL;
	private static $acl = null;
	public static $beignFlash = false;
	var $uses = array('Config', 'SystemVar');
	var $components	= array('Acl', 'Session', 'SessionAcl', 'Auth', 'Menus', 'RequestHandler', 'Structures', 'PermissionManager', 'Paginator', /*'DebugKit.Toolbar'*/ );
	public $helpers	= array('Session', 'Csv', 'Html', 'Js', 'Shell', 'Structures', 'Time', 'Form');
	
	//use AppController::getCalInfo to get those with translations
	private static $cal_info_short = array(1 => 'jan', 'feb', 'mar', 'apr', 'may', 'jun', 'jul', 'aug', 'sep', 'oct', 'nov', 'dec');
	private static $cal_info_long = array(1 => 'January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December');
	private static $cal_info_short_translated = false;
	private static $cal_info_long_translated = false;
	
	static $highlight_missing_translations = true;

    // Used as a set from the array keys
    public $allowed_file_prefixes = array();
	
    /**
     * This function is executed before every action in the controller. It’s a
     * handy place to check for an active session or inspect user permissions.
     **/
	function beforeFilter() {
		App::uses('Sanitize', 'Utility');
		AppController::$me = $this;
		if(Configure::read('debug') != 0){
			Cache::clear(false, "structures");
			Cache::clear(false, "menus");
		}
		
		if($this->Session->read('permission_timestamp') < $this->SystemVar->getVar('permission_timestamp')){
			$this->resetPermissions();
		}
		if(Configure::read('Config.language') != $this->Session->read('Config.language')){
			//set language
			//echo(Configure::read('Config.language'));
			$this->Session->write('Config.language', Configure::read('Config.language'));
		}
		
		$this->Auth->authorize = 'Actions';

		//Check password should be reset
		$lower_url_here = strtolower($this->request->here);
		if($this->Session->read('Auth.User.force_password_reset') && strpos($lower_url_here, '/users/logout') === false) {
			if(strpos($lower_url_here, '/customize/passwords/index') === false) {
				$this->redirect('/Customize/Passwords/index/');
			}
		}
		
		// record URL in logs
		$log_activity_data['UserLog']['user_id']  = $this->Session->read('Auth.User.id');
		$log_activity_data['UserLog']['url']  = $this->request->here;
		$log_activity_data['UserLog']['visited'] = now();
		$log_activity_data['UserLog']['allowed'] = 1;
		
		if ( isset($this->UserLog) ) {
			$log_activity_model =& $this->UserLog;
		} else {
			App::uses('UserLog', 'Model');
			$log_activity_model = new UserLog;
		}
			
		$log_activity_model->save($log_activity_data);
		
		// menu grabbed for HEADER
		if($this->request->is('ajax')){
			Configure::write ('debug', 0);
		}else{
			$atim_sub_menu_for_header = array();
			$menu_model = AppModel::getInstance("", "Menu", true);
			
			$main_menu_items = $menu_model->find('all', array('conditions' => array('Menu.parent_id' => 'MAIN_MENU_1')));
			foreach($main_menu_items as $item){
				$atim_sub_menu_for_header[$item['Menu']['id']] = $menu_model->find('all', array('conditions' => array('Menu.parent_id' => $item['Menu']['id'], 'Menu.is_root' => 1), 'order' => array('Menu.display_order')));
			}
		
			$this->set( 'atim_menu_for_header', $this->Menus->get('/menus/tools'));
			$this->set( 'atim_sub_menu_for_header', $atim_sub_menu_for_header);
				
			// menu, passed to Layout where it would be rendered through a Helper
			$this->set( 'atim_menu_variables', array() );
			$this->set( 'atim_menu', $this->Menus->get() );
		}
		// get default STRUCTRES, used for forms, views, and validation
		$this->Structures->set();
		if(isset($this->request->query['file'])) {
            pr($this->request->query['file']);
		}

            if (ini_get("max_input_vars") <= Configure::read('databrowser_and_report_results_display_limit')) {
                AppController::addWarningMsg(
                    __('PHP "max_input_vars" is <= than atim databrowser_and_report_results_display_limit, '
                       .'which will cause problems whenever you display more options than max_input_vars'));
            }
	}
	
	function hook( $hook_extension='' ) {
		if ($hook_extension){
			$hook_extension = '_'.$hook_extension;
		}
	
		$hook_file = APP . ($this->request->params['plugin'] ? 'Plugin' . DS . $this->request->params['plugin'] . DS : '') . 'Controller' . DS . 'Hook' . DS . $this->request->params['controller'].'_'.$this->request->params['action'].$hook_extension.'.php';
		
		if(!file_exists($hook_file)){
			$hook_file=false;
		}
	
		return $hook_file;
	}

    private function handleFileRequest() {
        $file = $this->request->query['file'];

        $redirect_invalid_file = function($case_type) use (&$file) {
            CakeLog::error("User tried to download invalid file (".$case_type."): ".$file);
            if ($case_type === 3) {
                AppController::getInstance()->redirect("/Pages/err_file_not_auth?p[]=".$file);
            } else {
                AppController::getInstance()->redirect("/Pages/err_file_not_found?p[]=".$file);
            }
        };

        $index = -1;
        foreach (range(0, 1) as $_) {
            $index = strpos($file, '.', $index + 1);
        }
        $prefix = substr($file, 0, $index);
        if ($prefix && array_key_exists($prefix, $this->allowed_file_prefixes)) {
		    $dir = Configure::read('uploadDirectory');
            // NOTE: Cannot use flash for errors because file is still in the
            // url and that would cause an infinite loop
            if (strpos($file, '/') > -1 || strpos($file, '\\') > -1) {
                $redirect_invalid_file(1);
            }
            $full_file = $dir.'/'.$file;
            if (!file_exists($full_file)) {
                $redirect_invalid_file(2);
            }
            $index = strpos($file, '.', $index + 1) + 1;
            $this->response->file($full_file,
                                  array('name' => substr($file, $index)));
            return $this->response;
        }
        $redirect_invalid_file(3);
    }
	
    /**
     * Called after controller action logic, but before the view is rendered.
     * This callback is not used often, but may be needed if you are calling
     * render() manually before the end of a given action.
     **/
	function beforeRender(){
        if (isset($this->request->query['file'])) {
            return $this->handleFileRequest();
        }
		//Fix an issue where cakephp 2.0 puts the first loaded model with the key model in the registry.
		//Causes issues on validation messages
		ClassRegistry::removeObject('model');
		
		if(isset($this->passedArgs['batchsetVar'])){
			//batchset handling
			$data = $this->viewVars[$this->passedArgs['batchsetVar']];
			if(empty($data)){
				unset($this->passedArgs['batchsetVar']);
				$this->flash(__('there is no data to add to a temporary batchset'), 'javascript:history.back()');
				return false;
			}
			if(isset($this->passedArgs['batchsetCtrl'])){
				$data = $data[$this->passedArgs['batchsetCtrl']];
			}
			$this->requestAction('/Datamart/BatchSets/add/0', array('_data' => $data));
		}
	}
	
	function afterFilter(){
// 		global $start_time;
// 		echo("Exec time (sec): ".(AppController::microtime_float() - $start_time));
	
		if(sizeof(AppController::$missing_translations) > 0){
			App::uses('MissingTranslation', 'Model');
			$mt = new MissingTranslation();
			foreach(AppController::$missing_translations as $missing_translation){
				$mt->set(array("MissingTranslation" => array("id" => $missing_translation)));
				$mt->save();//ignore errors, kind of insert ingnore
			}
		}
	}
	
	/**
	 * Simple function to replicate PHP 5 behaviour
	 */
	static function microtime_float(){
		list($usec, $sec) = explode(" ", microtime());
		return ((float)$usec + (float)$sec);
	}
	
	static function missingTranslation(&$word){
		if(!is_numeric($word) && strpos($word, "<span class='untranslated'>") === false){
			AppController::$missing_translations[] = $word;
			if(Configure::read('debug') == 2 && self::$highlight_missing_translations){
				$word = "<span class='untranslated'>".$word."</span>";
			}
		}
	}
	
	function atimFlash($message, $url){
		if(Configure::read('debug') > 0){
			$this->flash($message, $url);
		}else{
			$_SESSION['ctrapp_core']['confirm_msg'] = $message;
			$this->redirect($url);
		}
	}
	
	static function getInstance(){
		return AppController::$me;
	}
	
	static function init(){
		Configure::write('Config.language', 'eng');
		Configure::write('Acl.classname', 'AtimAcl');
		Configure::write('Acl.database', 'default');
		
		// ATiM2 configuration variables from Datatable
	
		define('VALID_INTEGER', '/^[-+]?[\\s]?[0-9]+[\\s]?$/');
		define('VALID_INTEGER_POSITIVE', '/^[+]?[\\s]?[0-9]+[\\s]?$/');
		define('VALID_FLOAT', '/^[-+]?[\\s]?[0-9]*[,\\.]?[0-9]+[\\s]?$/');
		define('VALID_FLOAT_POSITIVE', '/^[+]?[\\s]?[0-9]*[,\\.]?[0-9]+[\\s]?$/');
		define('VALID_24TIME', '/^([01][0-9]|2[0-3]):[0-5][0-9]:[0-5][0-9]$/');
	
		//ripped from validation.php date + time
		define('VALID_DATETIME_YMD', '%^(?:(?:(?:(?:1[6-9]|[2-9]\\d)?(?:0[48]|[2468][048]|[13579][26])|(?:(?:16|[2468][048]|[3579][26])00)))(-)(?:0?2\\1(?:29)))|(?:(?:(?:1[6-9]|2\\d)\\d{2})(-)(?:(?:(?:0?[13578]|1[02])\\2(?:31))|(?:(?:0?(1|[3-9])|1[0-2])\\2(29|30))|(?:(?:0?[1-9])|(?:1[0-2]))\\2(?:0?[1-9]|1\\d|2[0-8])))\s([0-1][0-9]|2[0-3])\:[0-5][0-9]\:[0-5][0-9]$%');
	
		// parse URI manually to get passed PARAMS
		global $start_time;
		$start_time = AppController::microtime_float();
	
		$request_uri_params = array();
	
		//Fix REQUEST_URI on IIS
		if (!isset($_SERVER['REQUEST_URI'])){
			$_SERVER['REQUEST_URI'] = substr($_SERVER['PHP_SELF'], 1);
			if (isset($_SERVER['QUERY_STRING'])){
				$_SERVER['REQUEST_URI'] .= '?'.$_SERVER['QUERY_STRING'];
			}
		}
		$request_uri = $_SERVER['REQUEST_URI'];
		$request_uri = explode('/',$request_uri);
		$request_uri = array_filter($request_uri);
	
		foreach ( $request_uri as $uri ) {
			$exploded_uri = explode(':',$uri);
			if ( count($exploded_uri)>1 ) {
				$request_uri_params[ $exploded_uri[0] ] = $exploded_uri[1];
			}
		}
	
		// import APP code required...
		App::import('Model', 'Config');
		$config_data_model = new Config();
	
		// get CONFIG data from table and SET
		$config_results	= false;
	
		App::uses('CakeSession', 'Model/Datasource');
		$user_id = CakeSession::read('Auth.User.id');
		$logged_in_user		= CakeSession::read('Auth.User.id');
		$logged_in_group	= CakeSession::read('Auth.User.group_id');
	
        $config_results = $config_data_model->getConfig(CakeSession::read('Auth.User.group_id'),
                                                        CakeSession::read('Auth.User.id'));
		// parse result, set configs/defines
		if ( $config_results ) {
			
			Configure::write('Config.language', $config_results['Config']['config_language']);
			foreach ( $config_results['Config'] as $config_key => $config_data ) {
				if ( strpos($config_key,'_')!==false ) {
						
					// break apart CONFIG key
					$config_key = explode('_',$config_key);
					$config_format = array_shift($config_key);
					$config_key = implode('_',$config_key);
						
					// if a DEFINE or CONFIG, set new setting for APP
					if ( $config_format=='define' ) {
	
						// override DATATABLE value with URI PARAM value
						if ( $config_key=='pagination_amount' && isset($request_uri_params['per']) ) {
							$config_data = $request_uri_params['per'];
						}
	
						define($config_key, $config_data);
					} else if ( $config_format=='config' ) {
						Configure::write($config_key, $config_data);
					}
				}
			}
		}
		
		define('CONFIDENTIAL_MARKER', __('confidential data')); // Moved here to allow translation
		
		if(Configure::read('debug') == 0){
			set_error_handler("myErrorHandler");
		}
	}
	
	/**
	 * @param boolean $short Wheter to return short or long month names
	 * @return an associative array containing the translated months names so that key = month_number and value = month_name
	 */
	static function getCalInfo($short = true){
		if($short){
			if(!AppController::$cal_info_short_translated){
				AppController::$cal_info_short_translated = true;
				AppController::$cal_info_short = array_map(create_function('$a', 'return __($a);'), AppController::$cal_info_short);
			}
			return AppController::$cal_info_short;
		}else{
			if(!AppController::$cal_info_long_translated){
				AppController::$cal_info_long_translated = true;
				AppController::$cal_info_long = array_map(create_function('$a', 'return __($a);'), AppController::$cal_info_long);
			}
			return AppController::$cal_info_long;
		}
	}
	
	/**
	 * @param int $year
	 * @param mixed int | string $month
	 * @param int $day
	 * @param boolean $nbsp_spaces True if white spaces must be printed as &nbsp;
	 * @param boolean $short_months True if months names should be short (used if $month is an int)
	 * @return string The formated datestring with user preferences
	 */
	static function getFormatedDateString($year, $month, $day, $nbsp_spaces = true, $short_months = true){
		$result = null;
		if(empty($year) && empty($month) && empty($day)){
			$result = "";
		}else{
			$divider = $nbsp_spaces ? "&nbsp;" : " ";
			if(is_numeric($month)){
				$month_str = AppController::getCalInfo($short_months);
				$month = $month > 0 && $month < 13 ? $month_str[(int)$month] : "-";
			}
			if(date_format == 'MDY') {
				$result = $month.(empty($month) ? "" : $divider).$day.(empty($day) ? "" : $divider).$year;
			}else if (date_format == 'YMD') {
				$result = $year.(empty($month) ? "" : $divider).$month.(empty($day) ? "" : $divider).$day;
			}else { // default of DATE_FORMAT=='DMY'
				$result = $day.(empty($day) ? "" : $divider).$month.(empty($month) ? "" : $divider).$year;
			}
		}
		return $result;
	}
	
	static function getFormatedTimeString($hour, $minutes, $nbsp_spaces = true){
		if(time_format == 12){
			$meridiem = $hour >= 12 ? "PM" : "AM";
			$hour %= 12;
			if($hour == 0){
				$hour = 12;
			}
			return $hour.(empty($minutes) ? '' : ":".$minutes.($nbsp_spaces ? "&nbsp;" : " ")).$meridiem;
		}else if(empty($minutes)){
			return $hour.__('hour_sign');
		}else{
			return $hour.":".$minutes;
		}
	}
	
	/**
	 *
	 * Enter description here ...
	 * @param $datetime_string String with format yyyy[-MM[-dd[ hh[:mm:ss]]]] (missing parts represent the accuracy
	 * @param boolean $nbsp_spaces True if white spaces must be printed as &nbsp;
	 * @param boolean $short_months True if months names should be short (used if $month is an int)
	 * @return string The formated datestring with user preferences
	 */
	static function getFormatedDatetimeString($datetime_string, $nbsp_spaces = true, $short_months = true){
		$month = null;
		$day = null;
		$hour = null;
		$minutes = null;
		if(strpos($datetime_string, ' ') === false){
			$date = $datetime_string;
		}else{
			list($date, $time) = explode(" ", $datetime_string);
			if(strpos($time, ":") === false){
				$hour = $time;
			}else{
				list($hour, $minutes, ) = explode(":", $time);
			}
		}
	
		$date = explode("-", $date);
		$year = $date[0];
		switch(count($date)){
			case 3:
				$day = $date[2];
			case 2:
				$month = $date[1];
				break;
		}
		$formated_date = self::getFormatedDateString($year, $month, $day, $nbsp_spaces);
		return $hour === null ? $formated_date : $formated_date.($nbsp_spaces ? "&nbsp;" : " ").self::getFormatedTimeString($hour, $minutes, $nbsp_spaces);
	}
	
	/**
	 * Return formatted date in SQL format from a date array returned by an application form.
	 *
	 * @param $datetime_array Array gathering date data into following structure:
	 * 	array('month' => string, '
	 * 		'day' => string,
	 * 		'year' => string,
	 * 		'hour' => string,
	 * 		'min' => string)
	 * @param $date_type  Specify the type of date ('normal', 'start', 'end')
	 * 		- normal => Will force function to build a date witout specific rules.
	 *      - start => Will force function to build date as a 'start date' of date range defintion
	 *    		(ex1: when just year is specified, will return a value like year-01-01 00:00)
	 *    		(ex2: when array is empty, will return a value like -9999-99-99 00:00)
	 *      - end => Will force function to build date as an 'end date' of date range defintion
	 *    		(ex1: when just year is specified, will return a value like year-12-31 23:59)
	 *    		(ex2: when array is empty, will return a value like 9999-99-99 23:59)
	 *
	 * @return string The formated SQL date having following format yyyy-MM-dd hh:mn
	 */
	static function getFormatedDatetimeSQL($datetime_array, $date_type = 'normal') {
	
		$formatted_date = '';
		switch($date_type) {
			case 'normal':
				if((!empty($datetime_array['year'])) && (!empty($datetime_array['month'])) && (!empty($datetime_array['day']))) {
					$formatted_date =  $datetime_array['year'].'-'.$datetime_array['month'].'-'.$datetime_array['day'];
				}
				if((!empty($formatted_date)) && (!empty($datetime_array['hour']))) {
					$formatted_date .= ' '.$datetime_array['hour'].':'.(empty($datetime_array['min'])? '00':$datetime_array['min']);
				}
				break;
	
			case 'start':
				if(empty($datetime_array['year'])) {
					$formatted_date = '-9999-99-99 00:00';
				} else {
					$formatted_date = $datetime_array['year'];
					if(empty($datetime_array['month'])) {
						$formatted_date .= '-01-01 00:00';
					} else {
						$formatted_date .= '-'.$datetime_array['month'];
						if(empty($datetime_array['day'])) {
							$formatted_date .= '-01 00:00';
						} else {
							$formatted_date .= '-'.$datetime_array['day'];
							if(empty($datetime_array['hour'])) {
								$formatted_date .= ' 00:00';
							} else {
								$formatted_date .= ' '.$datetime_array['hour'].':'.(empty($datetime_array['min'])?'00':$datetime_array['min']);
							}
						}
					}
				}
				break;
	
			case 'end':
				if(empty($datetime_array['year'])) {
					$formatted_date = '9999-12-31 23:59';
				} else {
					$formatted_date = $datetime_array['year'];
					if(empty($datetime_array['month'])) {
						$formatted_date .= '-12-31 23:59';
					} else {
						$formatted_date .= '-'.$datetime_array['month'];
						if(empty($datetime_array['day'])) {
							$formatted_date .= '-31 23:59';
						} else {
							$formatted_date .= '-'.$datetime_array['day'];
							if(empty($datetime_array['hour'])) {
								$formatted_date .= ' 23:59';
							} else {
								$formatted_date .= ' '.$datetime_array['hour'].':'.(empty($datetime_array['min'])?'59':$datetime_array['min']);
							}
						}
					}
				}
				break;
	
			default:
		}
	
		return $formatted_date;
	}
	
	/**
	 * Clones the first level of an array
	 * @param array $arr The array to clone
	 */
	static function cloneArray(array $arr){
		$result = array();
		foreach($arr as $k => $v){
			if(is_array($v)){
				$result[$k] = self::cloneArray($v);
			}else{
				$result[$k] = $v;
			}
		}
		return $result;
	}
	
	static function addWarningMsg($msg, $with_trace = false){
		if($with_trace){
			$_SESSION['ctrapp_core']['warning_trace_msg'][] = array('msg' => $msg, 'trace' => self::getStackTrace());
		}else{
			if(isset($_SESSION['ctrapp_core']['warning_no_trace_msg'][$msg])){
				$_SESSION['ctrapp_core']['warning_no_trace_msg'][$msg] ++;
			}else{
				$_SESSION['ctrapp_core']['warning_no_trace_msg'][$msg] = 1;
			}
		}
	}
	
	static function addInfoMsg($msg){
		if(isset($_SESSION['ctrapp_core']['info_msg'][$msg])){
			$_SESSION['ctrapp_core']['info_msg'][$msg] ++;
		}else{
			$_SESSION['ctrapp_core']['info_msg'][$msg] = 1;
		}
	}
	
	static function getStackTrace(){
		$bt = debug_backtrace();
		$result = array();
		foreach($bt as $unit){
			$result[] = (isset($unit['file']) ? $unit['file'] : '?').", ".$unit['function']." at line ".(isset($unit['line']) ? $unit['line'] : '?');
		}
		return $result;
	}
	
	/**
	 * Builds the value definition array for an updateAll call
	 * @param array They data array to build the values with
	 */
	static function getUpdateAllValues(array $data){
		$result = array();
		foreach($data as $model => $fields){
			foreach($fields as $name => $value){
				if(is_array($value)){
					if(strlen($value['year'])){
						$result[$model.".".$name] = "'".AppController::getFormatedDatetimeSQL($value)."'";
					}
				}else if(strlen($value)){
					$result[$model.".".$name] = "'".$value."'";
				}
			}
		}
		return $result;
	}
	
	/**
	 * @desc cookie manipulation to counter cake problems. see eventum #1032
	 */
	static function atimSetCookie($skip_expiration_cookie){
		$session_expiration = time() + Configure::read("Session.timeout");
		
		setcookie('last_request', time(), $session_expiration, '/');
		
		if(!$skip_expiration_cookie){
			setcookie('session_expiration', $session_expiration, $session_expiration, '/');
			if(isset($_COOKIE[Configure::read("Session.cookie")])){
				setcookie(Configure::read("Session.cookie"), $_COOKIE[Configure::read("Session.cookie")], $session_expiration, "/");
			}
		}
	}
	
	/**
	 * @desc Global function to initialize a batch action. Redirect/flashes on error.
	 * @param AppModek $model The model to work on
	 * @param string $data_model_name The model name used in $this->request->data
	 * @param string $data_key The data key name used in $this->request->data
	 * @param string $control_key_name The name of the control field used in the model table
	 * @param AppModel $possibilities_model The model to fetch the possibilities from
	 * @param string $possibilities_parent_key The possibilities parent key to base the search on
	 * @return An array with the ids and the possibilities
	 */
	function batchInit($model, $data_model_name, $data_key, $control_key_name, $possibilities_model, $possibilities_parent_key, $no_possibilities_msg){
		if(empty($this->request->data)){
			$this->redirect('/Pages/err_plugin_system_error?method='.__METHOD__.',line='.__LINE__, null, true);
		} else if(!is_array($this->request->data[$data_model_name][$data_key]) && strpos($this->request->data[$data_model_name][$data_key], ',')){
			return array('error' => "batch init - number of submitted records too big");
		}
		//extract valid ids
		$ids = $model->find('all', array('conditions' => array($model->name.'.id' => $this->request->data[$data_model_name][$data_key]), 'fields' => array($model->name.'.id'), 'recursive' => -1));
		if(empty($ids)){
			return array('error' => "batch init no data");
		}
		$model->sortForDisplay($ids, $this->request->data[$data_model_name][$data_key]);
		$ids = self::defineArrayKey($ids, $model->name, 'id');
		$ids = array_keys($ids);
	
		$controls = $model->find('all', array('conditions' => array($model->name.'.id' => $ids), 'fields' => array($model->name.'.'.$control_key_name), 'group' => array($model->name.'.'.$control_key_name), 'recursive' => -1));
		if(count($controls) != 1){
			return array('error' => "you must select elements with a common type");
		}
	
		$possibilities = $possibilities_model->find('all', array('conditions' => array($possibilities_parent_key => $controls[0][$model->name][$control_key_name], 'flag_active' => '1')));
	
		if(empty($possibilities)){
			return array('error' => $no_possibilities_msg);
		}
	
		return array('ids' => implode(',', $ids), 'possibilities' => $possibilities, 'control_id' => $controls[0][$model->name][$control_key_name]);
	}
	
	/**
	 * Replaces the array key (generally of a find) with an inner value
	 * @param array $in_array
	 * @param string $model The model ($in_array[$model])
	 * @param string $field The field (new key = $in_array[$model][$field])
	 * @param bool $unique If true, the array block will be directly under the model.field, not in an array.
	 * @return array
	 */
	static function defineArrayKey($in_array, $model, $field, $unique = false){
		$out_array = array();
		if($unique){
			foreach($in_array as $val){
				$out_array[$val[$model][$field]] = $val;
			}
		}else{
			foreach($in_array as $val){
				if(isset($val[$model])){
					$out_array[$val[$model][$field]][] = $val;
				}else{
					//the key cannot be foud
					$out_array[-1][] = $val;
				}
			}
				
		}
		return $out_array;
	}
	
	/**
	 * Recursively removes entries returning true on empty($value).
	 * @param array &$data
	 */
	static function removeEmptyValues(array &$data){
		foreach($data as $key => &$val){
			if(is_array($val)){
				self::removeEmptyValues($val);
			}
			if(empty($val)){
				unset($data[$key]);
			}
		}
	}
	
	static function getNewSearchId(){
		return AppController::getInstance()->Session->write('search_id', AppController::getInstance()->Session->read('search_id') + 1);
	}
	
	/**
	 * @param string $link The link to check
	 * @return True if the user can access that page, false otherwise
	 */
	static function checkLinkPermission($link){
		if(strpos($link, 'javascript:') === 0 || strpos($link, '#') === 0){
			return true;
		}

		$parts = Router::parse($link);
		if(empty($parts)){
			return false;
		}
		$aco_alias = 'Controller/'.($parts['plugin'] ? Inflector::camelize($parts['plugin']).'/' : '');
		$aco_alias .= ($parts['controller'] ? Inflector::camelize($parts['controller']).'/' : '');
		$aco_alias .= ($parts['action'] ? $parts['action'] : '');
		$instance = AppController::getInstance();
	
		return strpos($aco_alias,'Controller/Users') !== false
		|| strpos($aco_alias,'Controller/Pages') !== false
		|| $aco_alias == "Controller/Menus/index"
		|| $instance->SessionAcl->check('Group::'.$instance->Session->read('Auth.User.group_id'), $aco_alias);
	}
	
	static function applyTranslation(&$in_array, $model, $field){
		foreach($in_array as &$part){
			$part[$model][$field] = __($part[$model][$field]);
		}
	}
	
	/**
	 * Handles automatic pagination of model records Adding 
	 * the necessary bind on the model to fetch detail level, if there is a unique ctrl id
	 * @param Model|string $object Model to paginate (e.g: model instance, or 'Model', or 'Model.InnerModel')
	 * @param string|array $scope Conditions to use while paginating
	 * @param array $whitelist List of allowed options for paging
	 * @return array Model query results
	 */
	public function paginate($object = null, $scope = array(), $whitelist = array()) {
		$this->setControlerPaginatorSettings($object);
		$model_name = isset($object->base_model) ? $object->base_model : $object->name;		
		if(isset($object->Behaviors->MasterDetail->__settings[$model_name])){
			extract($object->Behaviors->MasterDetail->__settings[$model_name]);
			if($is_master_model && isset($scope[$model_name.'.'.$control_foreign]) && preg_match('/^[0-9]+$/', $scope[$model_name.'.'.$control_foreign])) {
				self::buildDetailBinding($object, array($model_name.'.'.$control_foreign => $scope[$model_name.'.'.$control_foreign]), $empty_structure_alias);
			}
		}
		return parent::paginate($object, $scope, $whitelist);
	}
		
	/**
	 * Finds and paginate search results. Stores search in cache.
	 * Handles detail level when there is a unique ctrl_id.
	 * Defines/updates the result structure.
	 * Sets 'result_are_unique_ctrl' as true if the results are based on a unique ctrl id,
	 * 	false otherwise. (Non master/detail models will return false)
	 * @param int $search_id The search id used by the pagination
	 * @param Object $model The model to search upon
	 * @param string $structure_alias The structure alias to parse the search conditions on
	 * @param string $url The base url to use in the pagination links (meaning without the search_id)
	 * @param bool $ignore_detail If true, even if the model is a master_detail ,the detail level won't be loaded
	 * @param mixed $limit If false, will make a paginate call, if an int greater than 0, will make a find with the limit
	 */
	function searchHandler($search_id, $model, $structure_alias, $url, $ignore_detail = false, $limit = false){
		//setting structure
		$structure = $this->Structures->get('form', $structure_alias);
		$this->set('atim_structure', $structure);
		if(empty($search_id)){
			$this->Structures->set('empty', 'empty_structure');
		}else{
			if($this->request->data){
				//newly submitted search, parse conditions and store in session
				$_SESSION['ctrapp_core']['search'][$search_id]['criteria'] = $this->Structures->parseSearchConditions($structure);
			}else if(!isset($_SESSION['ctrapp_core']['search'][$search_id]['criteria'])){
				self::addWarningMsg(__('you cannot resume a search that was made in a previous session'));
				$this->redirect('/menus');
				exit;
			}
	
			//check if the current model is a master/detail one or a similar view
			if(!$ignore_detail){
				self::buildDetailBinding($model, $_SESSION['ctrapp_core']['search'][$search_id]['criteria'], $structure_alias);
				$this->Structures->set($structure_alias);
			}
				
			if($limit){
				$this->request->data = $model->find('all', array('conditions' => $_SESSION['ctrapp_core']['search'][$search_id]['criteria'], 'limit' => $limit));
			}else{
				$this->setControlerPaginatorSettings($model);
				$this->request->data = $this->Paginator->paginate(
				    $model,
				    $_SESSION['ctrapp_core']['search'][$search_id]['criteria']);
			}
				
			// if SEARCH form data, save number of RESULTS and URL (used by the form builder pagination links)
			if($search_id == -1){
				//don't use the last search button if search id = -1
				unset($_SESSION['ctrapp_core']['search'][$search_id]);
			}else{
				$_SESSION['ctrapp_core']['search'][$search_id]['results'] = $this->request->params['paging'][$model->name]['count'];
				$_SESSION['ctrapp_core']['search'][$search_id]['url'] = $url;
			}
		}
	
		if($this->request->is('ajax')) {
			Configure::write ( 'debug', 0 );
			$this->set ( 'is_ajax', true );
		}
	}
	
	/**
	 * Set the Pagination settings based on user preferences and controller Pagination settings.
	 * @param Object $model The model to search upon
	 */
	function setControlerPaginatorSettings($model) {
		if(pagination_amount) $this->Paginator->settings = array_merge($this->Paginator->settings, array('limit' => pagination_amount));
		if($model && isset($this->paginate[$model->name])) {
			$this->Paginator->settings = array_merge($this->Paginator->settings, $this->paginate[$model->name]);
		}
	}
	
	/**
	 * Adds the necessary bind on the model to fetch detail level, if there is a unique ctrl id
	 * @param AppModel &$model
	 * @param array $conditions Search conditions
	 * @param string &$structure_alias
	 */
	static function buildDetailBinding(&$model, array $conditions, &$structure_alias){
		$controller = AppController::getInstance();
		$master_class_name = isset($model->base_model) ? $model->base_model : $model->name;
		if(!isset($model->Behaviors->MasterDetail->__settings[$master_class_name])){
			$controller->$master_class_name;//try to force lazyload
			if(!isset($model->Behaviors->MasterDetail->__settings[$master_class_name])){
				if(Configure::read('debug') != 0){
					AppController::addWarningMsg("buildDetailBinding requires you to force instanciation of model ".$master_class_name);
				}
				return;
			}
		}
		if($model->Behaviors->MasterDetail->__settings[$master_class_name]['is_master_model']){
		    $ctrl_ids = null;
		    $single_ctrl_id = $model->getSingleControlIdCondition(array('conditions' => $conditions));
		    $control_field = $model->Behaviors->MasterDetail->__settings[$master_class_name]['control_foreign'];
		    if($single_ctrl_id === false){
		        //determine if the results contain only one control id
		        $ctrl_ids = $model->find('all', array(
		                'fields'		=> array($model->name.'.'.$control_field),
		                'conditions'	=> $conditions,
		                'group'			=> array($model->name.'.'.$control_field),
		                'limit'			=> 2
		        ));
		        if(count($ctrl_ids) == 1){
		            $single_ctrl_id = current(current($ctrl_ids[0]));
		        }
		    }
			if($single_ctrl_id !== false){
				//only one ctrl, attach detail
				$has_one = array();
				extract($model->Behaviors->MasterDetail->__settings[$master_class_name]);
				$ctrl_model = isset($controller->$control_class) ? $controller->$control_class : AppModel::getInstance('', $control_class, false);
				if(!$ctrl_model){
					if(Configure::read('debug') != 0){
						AppController::addWarningMsg('buildDetailBinding requires you to force instanciation of model '.$control_class);
					}
					return;
				}
				$ctrl_data = $ctrl_model->findById($single_ctrl_id);
				$ctrl_data = current($ctrl_data);
				//put a new instance of the detail model in the cache
				ClassRegistry::removeObject($detail_class);//flush the old detail from cache, we'll need to reinstance it
				assert(strlen($ctrl_data['detail_tablename'])) or die("detail_tablename cannot be empty");
				new AppModel(array('table' => $ctrl_data['detail_tablename'],
				                   'name'  => $detail_class,
				                   'alias' => $detail_class));
				
				//has one and win
				$has_one[$detail_class] = array(
						'className' => $detail_class,
						'foreignKey' => $master_foreign
				);
				
				if($master_class_name == 'SampleMaster'){
					//join specimen/derivative details
					if($ctrl_data['sample_category'] == 'specimen'){
						$has_one['SpecimenDetail'] = array(
								'className' => 'SpecimenDetail',
								'foreignKey' => 'sample_master_id'
						);
					}else{
						//derivative
						$has_one['DerivativeDetail'] = array(
								'className' => 'DerivativeDetail',
								'foreignKey' => 'sample_master_id'
						);
					}
				}
					
				//persistent bind
				$model->bindModel(
					array(
						'hasOne' => $has_one,
						'belongsTo' => array(
							$control_class => array(
								'className' => $control_class
							)
						)
					), false
				);
				isset($model->{$detail_class});//triggers model lazy loading (See cakephp Model class)
					
				//updating structure
				if(($pos = strpos($ctrl_data['form_alias'], ',')) !== false){
					$structure_alias = $structure_alias.','.substr($ctrl_data['form_alias'], $pos + 1);
				}
                
                ClassRegistry::removeObject($detail_class);//flush the new model to make sure the default one is loaded if needed
                
			}else if(count($ctrl_ids) > 0){
				//more than one
				AppController::addInfoMsg(__("the results contain various data types, so the details are not displayed"));
			}
		}
	}
	
	/**
	 * Builds menu options based on 1-display_order and 2-translation
	 * @param array $menu_options An array containing arrays of the form array('order' => #, 'label' => '', 'link' => '')
	 * The label must be translated already.
	 */
	static function buildBottomMenuOptions(array &$menu_options){
		$tmp = array();
		foreach($menu_options as $menu_option){
			$tmp[sprintf("%05d", $menu_option['order']).'-'.$menu_option['label']] = $menu_option['link'];
		}
		ksort($tmp);
		$menu_options = array();
		foreach($tmp as $label => $link){
			$menu_options[preg_replace('/^[0-9]+-/', '', $label)] = $link;
		}
	}
	
	/**
	 * Sets url_to_cancel based on $this->request->data['url_to_cancel']
	 * If nothing exists, javascript:history.go(-1) is used.
	 * If a similar entry exists, the value is decremented.
	 * Otherwise, url_to_cancel is uses as such.
	 */
	function setUrlToCancel(){
		if(isset($this->request->data['url_to_cancel'])){
			$pattern = '/^javascript:history.go\((-?[0-9]*)\)$/';
			$matches = array();
			if(preg_match($pattern, $this->request->data['url_to_cancel'], $matches)){
				$back = empty($matches[1]) ? -2 : $matches[1] - 1;
				$this->request->data['url_to_cancel'] = 'javascript:history.go('.$back.')';
			}
				
		}else{
			$this->request->data['url_to_cancel'] = 'javascript:history.go(-1)';
		}
	
		$this->set('url_to_cancel', $this->request->data['url_to_cancel']);
	}
	
	function resetPermissions(){
		if($this->Auth->user()){
			$user_model = AppModel::getInstance('', 'User', true);
			$user = $user_model->findById($this->Session->read('Auth.User.id'));
			$this->Session->write('Auth.User.group_id', $user['User']['group_id']);
			$this->Session->write('flag_show_confidential', $user['Group']['flag_show_confidential']);
			$this->Session->write('permission_timestamp', time());
			$this->SessionAcl->flushCache();
		}
	}
	
	function setForRadiolist(array &$list, $l_model, $l_key, array $data, $d_model, $d_key){
		foreach($list as &$unit){
			if($data[$d_model][$d_key] == $unit[$l_model][$l_key]){
				//we found the one that interests us
				$unit[$d_model] = $data[$d_model];
				return true;
			}
		}
		return false;
	}
	
	/**
	 * Builds a cancel link based on the passed data. Works for data send by batch sets and browsing.
	 * @param strint or null $data
	 */
	static function getCancelLink($data){
		$result = null;
		if(isset($data['node']['id'])){
			$result = '/Datamart/Browser/browse/'.$data['node']['id'];
		}else if(isset($data['BatchSet']['id'])){
			$result = '/Datamart/BatchSets/listall/'.$data['BatchSet']['id'];
		}else if(isset($data['cancel_link'])){
			$result = $data['cancel_link'];
		}
		
		return $result;
	}
	
	/**
	 * Does multiple tasks related to having a version updated
	 * -Permissions
	 * -i18n version field
	 * -language files
	 * -cache
	 * -Delete all browserIndex > Limit
	 * -databrowser lft rght
	 */
	function newVersionSetup(){
		//new version installed!
		
		//------------------------------------------------------------------------------------------------------------------------------------------
		
		// *** 1 *** regen permissions
		
		$this->PermissionManager->buildAcl();
		AppController::addWarningMsg(__('permissions have been regenerated'));
			
		// *** 2 *** update the i18n string for version
		
		$storage_control_model = AppModel::getInstance('StorageLayout', 'StorageControl', true);
		$is_tma_block = $storage_control_model->find('count', array('condition' => array('StorageControl.flag_active' => '1', 'is_tma_block' => '1')));
		$this->Version->query("REPLACE INTO i18n (id,en,fr) (SELECT 'storage layout management - value generated by newVersionSetup function', en, fr FROM i18n WHERE id = '".($is_tma_block? 'storage layout & tma blocks management' : 'storage layout management')."')");
		$this->Version->query("REPLACE INTO i18n (id,en,fr) (SELECT 'storage layout management description - value generated by newVersionSetup function', en, fr FROM i18n WHERE id = '".($is_tma_block? 'storage layout & tma blocks management description' : 'storage layout management description')."')");
		$this->Version->query("REPLACE INTO i18n (id,en,fr) (SELECT 'storage (non tma block) - value generated by newVersionSetup function', en, fr FROM i18n WHERE id = '".($is_tma_block? 'storage (non tma block)' : 'storage')."')");
		
		$i18n_model = new Model(array('table' => 'i18n', 'name' => 0));
		$version_number = $this->Version->data['Version']['version_number'];
		$i18n_model->save(array('id' => 'core_app_version', 'en' => $version_number, 'fr' => $version_number));
		
		// *** 3 ***rebuild language files
		
		$filee = fopen("../../app/Locale/eng/LC_MESSAGES/default.po", "w+t") or die("Failed to open english file");
		$filef = fopen("../../app/Locale/fra/LC_MESSAGES/default.po", "w+t") or die("Failed to open french file");
		$i18n = $i18n_model->find('all');
		foreach ( $i18n as &$i18n_line){
			//Takes information returned by query and creates variable for each field
			$id = $i18n_line[0]['id'];
			$en = $i18n_line[0]['en'];
			$fr = $i18n_line[0]['fr'];
			if(strlen($en) > 1014){
				$error = "msgid\t\"$id\"\nen\t\"$en\"\n";
				$en = substr($en, 0, 1014);
			}
				
			if(strlen($fr) > 1014){
				if(strlen($error) > 2 ){
					$error = "$error\\nmsgstr\t\"$fr\"\n";
				}else{
					$error = "msgid\t\"$id\"\nmsgstr=\"$fr\"\n";
				}
				$fr = substr($fr, 0, 1014);
			}
			$english = "msgid\t\"$id\"\nmsgstr\t\"$en\"\n";
			$french = "msgid\t\"$id\"\nmsgstr\t\"$fr\"\n";
				
			//Writes output to file
			fwrite($filee, $english);
			fwrite($filef, $french);
		}
		fclose($filee);
		fclose($filef);
		AppController::addWarningMsg(__('language files have been rebuilt'));
		
		// *** 4 *** rebuilts lft rght in datamart_browsing_result if needed + delete all temporary browsing index if > $tmp_browsing_limit. Since v2.5.0.
		
		$browsing_index_model = AppModel::getInstance('Datamart', 'BrowsingIndex', true);
		$browsing_result_model = AppModel::getInstance('Datamart', 'BrowsingResult', true);
		$root_node_ids_to_keep = array();
		$user_root_node_counter = 0;
		$last_user_id = null;
		$force_rebuild_left_rght = false;
		$tmp_browsing = $browsing_index_model->find('all', array('conditions' => array('BrowsingIndex.temporary' => true), 'order' => array('BrowsingResult.user_id, BrowsingResult.created DESC')));
		foreach($tmp_browsing as $new_browsing_index) {
			if($last_user_id != $new_browsing_index['BrowsingResult']['user_id'] || $user_root_node_counter <  $browsing_index_model->tmp_browsing_limit) {
				if($last_user_id != $new_browsing_index['BrowsingResult']['user_id']) $user_root_node_counter = 0;
				$last_user_id = $new_browsing_index['BrowsingResult']['user_id'];
				$user_root_node_counter++;
				$root_node_ids_to_keep[$new_browsing_index['BrowsingIndex']['root_node_id']] = $new_browsing_index['BrowsingIndex']['root_node_id'];
			} else {
				//Some browsing index will be deleted
				$force_rebuild_left_rght = true;
			}
		}
		$result_ids_to_keep = $root_node_ids_to_keep;
		$new_parent_ids = $root_node_ids_to_keep;
		$loop_counter = 0;
		while(!empty($new_parent_ids)) {
			//Just in case
			$loop_counter++;
			if($loop_counter > 100) $this->redirect('/Pages/err_plugin_system_error?method='.__METHOD__.',line='.__LINE__, null, true);
			$new_parent_ids = $browsing_result_model->find('list', array('conditions' => array("BrowsingResult.parent_id" => $new_parent_ids), 'fields' => array('BrowsingResult.id')));
			$result_ids_to_keep = array_merge($result_ids_to_keep, $new_parent_ids);
		}
		if(!empty($result_ids_to_keep)) {
			$browsing_index_model->deleteAll("BrowsingIndex.root_node_id NOT IN (".implode(',',$root_node_ids_to_keep).")");
			$browsing_result_model->deleteAll("BrowsingResult.id NOT IN (".implode(',',$result_ids_to_keep).")");
		}
		$result = $browsing_result_model->find('first', array('conditions' => array('NOT' => array('BrowsingResult.parent_id' => NULL), 'BrowsingResult.lft' => NULL)));
		if($result || $force_rebuild_left_rght){
			self::addWarningMsg(__('rebuilt lft rght for datamart_browsing_results'));
			$browsing_result_model->recover('parent');
		}
		
		// *** 5 *** rebuild views
		
		$view_models = array(
				AppModel::getInstance('InventoryManagement', 'ViewCollection'),
				AppModel::getInstance('InventoryManagement', 'ViewSample'),
				AppModel::getInstance('InventoryManagement', 'ViewAliquot'),
				AppModel::getInstance('StorageLayout', 'ViewStorageMaster'),
				AppModel::getInstance('InventoryManagement', 'ViewAliquotUse')
		);
		foreach($view_models as $view_model){
			$this->Version->query('DROP TABLE IF EXISTS '.$view_model->table);
			$this->Version->query('DROP VIEW IF EXISTS '.$view_model->table);
			if(isset($view_model::$table_create_query)){
				//Must be done with multiple queries
				$this->Version->query($view_model::$table_create_query);
				$queries = explode("UNION ALL", $view_model::$table_query);
				foreach($queries as $query){
					$this->Version->query('INSERT INTO '.$view_model->table. '('.str_replace('%%WHERE%%', '', $query).')');
				}
				
			}else{
				$this->Version->query('CREATE TABLE '.$view_model->table. '('.str_replace('%%WHERE%%', '', $view_model::$table_query).')');
			}
			$desc = $this->Version->query('DESC '.$view_model->table);
			$fields = array();
			$field = array_shift($desc);
			$pkey = $field['COLUMNS']['Field'];
			foreach($desc as $field){
				if($field['COLUMNS']['Type'] != 'text'){
					$fields[] = $field['COLUMNS']['Field'];
				}
			}
			$this->Version->query('ALTER TABLE '.$view_model->table.' ADD PRIMARY KEY('.$pkey.'), ADD KEY ('.implode('), ADD KEY (', $fields).')');
		}
		
		AppController::addWarningMsg(__('views have been rebuilt'));

		// *** 6 *** Current Volume clean up
		
		$ViewAliquot_model = AppModel::getInstance("InventoryManagement", "ViewAliquot", false);	//To fix bug on table created on the fly (http://stackoverflow.com/questions/8167038/cakephp-pagination-using-temporary-table)
		$tmp_aliquot_model_cacheSources = $ViewAliquot_model->cacheSources;
		$ViewAliquot_model->cacheSources = false;
		$ViewAliquot_model->schema();
		$AliquotMaster_model = AppModel::getInstance("InventoryManagement", "AliquotMaster", true);
		$AliquotMaster_model->check_writable_fields = false;
		AppModel::acquireBatchViewsUpdateLock();
		//Current Volume
		$current_volumes_updated = array();
		//Search all aliquots having initial_volume but no current_volume
		$tmp_sql = "SELECT am.id AS aliquot_master_id, am.barcode, am.aliquot_label, am.initial_volume, am.current_volume
			FROM aliquot_masters am INNER JOIN aliquot_controls ac ON ac.id = am.aliquot_control_id
			WHERE am.deleted != 1 AND ac.volume_unit IS NOT NULL AND am.initial_volume IS NOT NULL AND am.current_volume IS NULL;";
		$aliquots_to_clean_up = $AliquotMaster_model->query($tmp_sql);
		foreach($aliquots_to_clean_up as $new_aliquot) {
			$AliquotMaster_model->data = array(); // *** To guaranty no merge will be done with previous AliquotMaster data ***
			$AliquotMaster_model->id = $new_aliquot['am']['aliquot_master_id'];
			if(!$AliquotMaster_model->save(array('AliquotMaster' => array('id' => $new_aliquot['am']['aliquot_master_id'], 'current_volume' => $new_aliquot['am']['initial_volume'])), false)) $this->redirect('/Pages/err_plugin_record_err?method='.__METHOD__.',line='.__LINE__, null, true);
			$current_volumes_updated[$new_aliquot['am']['aliquot_master_id']] = $new_aliquot['am']['barcode'];
		}
		//Search all aliquots having current_volume but no initial_volume
		$tmp_sql = "SELECT am.id AS aliquot_master_id, am.barcode, am.aliquot_label, am.initial_volume, am.current_volume
			FROM aliquot_masters am INNER JOIN aliquot_controls ac ON ac.id = am.aliquot_control_id
			WHERE am.deleted != 1 AND ac.volume_unit IS NOT NULL AND am.initial_volume IS NULL AND am.current_volume IS NOT NULL;";
		$aliquots_to_clean_up = $AliquotMaster_model->query($tmp_sql);
		foreach($aliquots_to_clean_up as $new_aliquot) {
			$AliquotMaster_model->data = array(); // *** To guaranty no merge will be done with previous AliquotMaster data ***
			$AliquotMaster_model->id = $new_aliquot['am']['aliquot_master_id'];
			if(!$AliquotMaster_model->save(array('AliquotMaster' => array('id' => $new_aliquot['am']['aliquot_master_id'], 'current_volume' => '')), false)) $this->redirect('/Pages/err_plugin_record_err?method='.__METHOD__.',line='.__LINE__, null, true);
			$current_volumes_updated[$new_aliquot['am']['aliquot_master_id']] = $new_aliquot['am']['barcode'];
		}
		//Search all aliquots having current_volume > 0 but a sum of used_volume (from view_aliquot_uses) > initial_volume
		$tmp_sql = "SELECT am.id AS aliquot_master_id, am.barcode, am.aliquot_label, am.initial_volume, am.current_volume, us.sum_used_volumes FROM aliquot_masters am INNER JOIN aliquot_controls ac ON ac.id = am.aliquot_control_id INNER JOIN (SELECT aliquot_master_id, SUM(used_volume) AS sum_used_volumes FROM view_aliquot_uses WHERE used_volume IS NOT NULL GROUP BY aliquot_master_id) AS us ON us.aliquot_master_id = am.id WHERE am.deleted != 1 AND ac.volume_unit IS NOT NULL AND am.initial_volume < us.sum_used_volumes AND am.current_volume != 0;";
		$aliquots_to_clean_up = $AliquotMaster_model->query($tmp_sql);
		foreach($aliquots_to_clean_up as $new_aliquot) {
			$AliquotMaster_model->data = array(); // *** To guaranty no merge will be done with previous AliquotMaster data ***
			$AliquotMaster_model->id = $new_aliquot['am']['aliquot_master_id'];
			if(!$AliquotMaster_model->save(array('AliquotMaster' => array('id' => $new_aliquot['am']['aliquot_master_id'], 'current_volume' => '0')), false)) $this->redirect('/Pages/err_plugin_record_err?method='.__METHOD__.',line='.__LINE__, null, true);
			$current_volumes_updated[$new_aliquot['am']['aliquot_master_id']] = $new_aliquot['am']['barcode'];
		}
		//Search all aliquots having current_volume != initial volume - used_volume (from view_aliquot_uses) > initial_volume
		$tmp_sql = "SELECT am.id AS aliquot_master_id, am.barcode, am.aliquot_label, am.initial_volume, am.current_volume, us.sum_used_volumes FROM aliquot_masters am INNER JOIN aliquot_controls ac ON ac.id = am.aliquot_control_id INNER JOIN (SELECT aliquot_master_id, SUM(used_volume) AS sum_used_volumes FROM view_aliquot_uses WHERE used_volume IS NOT NULL GROUP BY aliquot_master_id) AS us ON us.aliquot_master_id = am.id WHERE am.deleted != 1 AND ac.volume_unit IS NOT NULL AND am.initial_volume >= us.sum_used_volumes AND am.current_volume != (am.initial_volume - us.sum_used_volumes);";
		$aliquots_to_clean_up = $AliquotMaster_model->query($tmp_sql);
		foreach($aliquots_to_clean_up as $new_aliquot) {
			$AliquotMaster_model->data = array(); // *** To guaranty no merge will be done with previous AliquotMaster data ***
			$AliquotMaster_model->id = $new_aliquot['am']['aliquot_master_id'];
			if(!$AliquotMaster_model->save(array('AliquotMaster' => array('id' => $new_aliquot['am']['aliquot_master_id'], 'current_volume' => ($new_aliquot['am']['initial_volume'] - $new_aliquot['us']['sum_used_volumes']))), false)) $this->redirect('/Pages/err_plugin_record_err?method='.__METHOD__.',line='.__LINE__, null, true);
			$current_volumes_updated[$new_aliquot['am']['aliquot_master_id']] = $new_aliquot['am']['barcode'];
		}	
		if($current_volumes_updated) AppController::addWarningMsg(__('aliquot current volume has been corrected for following aliquots : ').(implode(', ', $current_volumes_updated)));
		//-C-Used Volume
		$used_volume_updated = array();
		//Search all aliquot internal use having used volume not null but no volume unit 
		$tmp_sql = "SELECT AliquotInternalUse.id AS aliquot_internal_use_id,
			AliquotMaster.id AS aliquot_master_id,
			AliquotMaster.barcode AS barcode,
			AliquotInternalUse.used_volume AS used_volume,
			AliquotControl.volume_unit
			FROM aliquot_internal_uses AS AliquotInternalUse
			JOIN aliquot_masters AS AliquotMaster ON AliquotMaster.id = AliquotInternalUse.aliquot_master_id
			JOIN aliquot_controls AS AliquotControl ON AliquotMaster.aliquot_control_id = AliquotControl.id
			WHERE AliquotInternalUse.deleted <> 1 AND AliquotControl.volume_unit IS NULL AND AliquotInternalUse.used_volume IS NOT NULL;";
		$aliquots_to_clean_up = $AliquotMaster_model->query($tmp_sql);
		if($aliquots_to_clean_up) {
			$AliquotInternalUse_model = AppModel::getInstance("InventoryManagement", "AliquotInternalUse", true);
			$AliquotInternalUse_model->check_writable_fields = false;
			foreach($aliquots_to_clean_up as $new_aliquot) {			
				$AliquotInternalUse_model->data = array(); // *** To guaranty no merge will be done with previous AliquotMaster data ***
				$AliquotInternalUse_model->id = $new_aliquot['AliquotInternalUse']['aliquot_internal_use_id'];
				if(!$AliquotInternalUse_model->save(array('AliquotInternalUse' => array('id' => $new_aliquot['AliquotInternalUse']['aliquot_internal_use_id'], 'used_volume' => '')), false)) $this->redirect('/Pages/err_plugin_record_err?method='.__METHOD__.',line='.__LINE__, null, true);
				$used_volume_updated[$new_aliquot['AliquotMaster']['aliquot_master_id']] = $new_aliquot['AliquotMaster']['barcode'];
			}
		}
		//Search all aliquot used as source aliquot, used volume not null but no volume unit 
		$tmp_sql = "SELECT SourceAliquot.id AS source_aliquot_id,
			AliquotMaster.id AS aliquot_master_id,
			AliquotMaster.barcode AS barcode,
			SourceAliquot.used_volume AS used_volume,
			AliquotControl.volume_unit AS aliquot_volume_unit
			FROM source_aliquots AS SourceAliquot
			JOIN aliquot_masters AS AliquotMaster ON AliquotMaster.id = SourceAliquot.aliquot_master_id
			JOIN aliquot_controls AS AliquotControl ON AliquotMaster.aliquot_control_id = AliquotControl.id
			WHERE SourceAliquot.deleted <> 1 AND AliquotControl.volume_unit IS NULL AND SourceAliquot.used_volume IS NOT NULL;";
		$aliquots_to_clean_up = $AliquotMaster_model->query($tmp_sql);
		if($aliquots_to_clean_up) {
			$SourceAliquot_model = AppModel::getInstance("InventoryManagement", "SourceAliquot", true);
			$SourceAliquot_model->check_writable_fields = false;
			foreach($aliquots_to_clean_up as $new_aliquot) {
				$SourceAliquot_model->data = array(); // *** To guaranty no merge will be done with previous AliquotMaster data ***
				$SourceAliquot_model->id = $new_aliquot['SourceAliquot']['source_aliquot_id'];
				if(!$SourceAliquot_model->save(array('SourceAliquot' => array('id' => $new_aliquot['SourceAliquot']['source_aliquot_id'], 'used_volume' => '')), false)) $this->redirect('/Pages/err_plugin_record_err?method='.__METHOD__.',line='.__LINE__, null, true);
				$used_volume_updated[$new_aliquot['AliquotMaster']['aliquot_master_id']] = $new_aliquot['AliquotMaster']['barcode'];
			}
		}		
		//Search all aliquot used as parent aliquot, used volume not null but no volume unit 
		$tmp_sql = "SELECT Realiquoting.id AS realiquoting_id,
			AliquotMaster.id AS aliquot_master_id,
			AliquotMaster.barcode AS barcode,
			Realiquoting.parent_used_volume AS used_volume,
			AliquotControl.volume_unit AS aliquot_volume_unit
			FROM realiquotings AS Realiquoting
			JOIN aliquot_masters AS AliquotMaster ON AliquotMaster.id = Realiquoting.parent_aliquot_master_id
			JOIN aliquot_controls AS AliquotControl ON AliquotMaster.aliquot_control_id = AliquotControl.id
			WHERE Realiquoting.deleted <> 1 AND AliquotControl.volume_unit IS NULL AND Realiquoting.parent_used_volume IS NOT NULL;";
		$aliquots_to_clean_up = $AliquotMaster_model->query($tmp_sql);
		if($aliquots_to_clean_up) {
			$Realiquoting_model = AppModel::getInstance("InventoryManagement", "Realiquoting", true);
			$Realiquoting_model->check_writable_fields = false;
			foreach($aliquots_to_clean_up as $new_aliquot) {
				$Realiquoting_model->data = array(); // *** To guaranty no merge will be done with previous AliquotMaster data ***
				$Realiquoting_model->id = $new_aliquot['Realiquoting']['realiquoting_id'];			
				if(!$Realiquoting_model->save(array('Realiquoting' => array('id' => $new_aliquot['Realiquoting']['realiquoting_id'], 'parent_used_volume' => '')), false)) $this->redirect('/Pages/err_plugin_record_err?method='.__METHOD__.',line='.__LINE__, null, true);
				$used_volume_updated[$new_aliquot['AliquotMaster']['aliquot_master_id']] = $new_aliquot['AliquotMaster']['barcode'];
			}
		}		
		//Search all aliquot used for quality conbtrol, used volume not null but no volume unit 
		$tmp_sql = "SELECT QualityCtrl.id AS quality_control_id,
			AliquotMaster.id AS aliquot_master_id,
			AliquotMaster.barcode AS barcode,
			QualityCtrl.used_volume AS used_volume,
			AliquotControl.volume_unit AS aliquot_volume_unit
			FROM quality_ctrls AS QualityCtrl
			JOIN aliquot_masters AS AliquotMaster ON AliquotMaster.id = QualityCtrl.aliquot_master_id
			JOIN aliquot_controls AS AliquotControl ON AliquotMaster.aliquot_control_id = AliquotControl.id
			WHERE QualityCtrl.deleted <> 1 AND AliquotControl.volume_unit IS NULL AND QualityCtrl.used_volume IS NOT NULL;";
		$aliquots_to_clean_up = $AliquotMaster_model->query($tmp_sql);
		if($aliquots_to_clean_up) {
			$QualityCtrl_model = AppModel::getInstance("InventoryManagement", "QualityCtrl", true);
			$QualityCtrl_model->check_writable_fields = false;
			foreach($aliquots_to_clean_up as $new_aliquot) {
				$QualityCtrl_model->data = array(); // *** To guaranty no merge will be done with previous AliquotMaster data ***
				$QualityCtrl_model->id = $new_aliquot['QualityCtrl']['quality_control_id'];
				if(!$QualityCtrl_model->save(array('QualityCtrl' => array('id' => $new_aliquot['QualityCtrl']['quality_control_id'], 'used_volume' => '')), false)) $this->redirect('/Pages/err_plugin_record_err?method='.__METHOD__.',line='.__LINE__, null, true);
				$used_volume_updated[$new_aliquot['AliquotMaster']['aliquot_master_id']] = $new_aliquot['AliquotMaster']['barcode'];
			}
		}		
		if($used_volume_updated) {	
			$ViewAliquotUse_model = AppModel::getInstance('InventoryManagement', 'ViewAliquotUse');
			foreach(explode("UNION ALL", $ViewAliquotUse_model::$table_query) as $query) {
				$ViewAliquotUse_model->query('REPLACE INTO '.$ViewAliquotUse_model->table. '('.str_replace('%%WHERE%%', 'AND AliquotMaster.id IN ('.implode(',',array_keys($used_volume_updated)).')', $query).')');
			}
			AppController::addWarningMsg(__('aliquot used volume has been removed for following aliquots : ').(implode(', ', $used_volume_updated)));
		}	
		$ViewAliquot_model->cacheSources = $tmp_aliquot_model_cacheSources;
		$ViewAliquot_model->schema();

		// *** 7 *** clear cache

		Cache::clear(false);
		Cache::clear(false, 'structures');
		Cache::clear(false, 'menus');
		Cache::clear(false, 'browser');
		Cache::clear(false, 'models');
		Cache::clear(false, '_cake_core_');
		Cache::clear(false, '_cake_model_');
		AppController::addWarningMsg(__('cache has been cleared'));
			
		// *** 8 *** Clean up parent to sample control + aliquot control
		
		$studied_sample_control_id = array();
		$active_sample_control_ids = array();
		$this->ParentToDerivativeSampleControl = AppModel::getInstance("InventoryManagement", "ParentToDerivativeSampleControl", true);
		
		$conditions = array(
				'ParentToDerivativeSampleControl.parent_sample_control_id' => NULL,
				'ParentToDerivativeSampleControl.flag_active' => true);
		while($active_parent_sample_types = $this->ParentToDerivativeSampleControl->find('all', array('conditions' => $conditions))) {
			foreach($active_parent_sample_types as $new_parent_sample_type) {
				$active_sample_control_ids[] = $new_parent_sample_type['DerivativeControl']['id'];
				$studied_sample_control_id[] = $new_parent_sample_type['DerivativeControl']['id'];
			}
			$conditions = array(
				'ParentToDerivativeSampleControl.parent_sample_control_id' => $active_sample_control_ids,
				'ParentToDerivativeSampleControl.flag_active' => true,
				'not' => array('ParentToDerivativeSampleControl.derivative_sample_control_id' => $studied_sample_control_id));
		}
		$this->Version->query('UPDATE parent_to_derivative_sample_controls SET flag_active = false WHERE parent_sample_control_id IS NOT NULL AND parent_sample_control_id NOT IN ('.implode(',',$active_sample_control_ids).')');
		$this->Version->query('UPDATE aliquot_controls SET flag_active = false WHERE sample_control_id NOT IN ('.implode(',',$active_sample_control_ids).')');
		
		// *** 9 *** Clean up structure_permissible_values_custom_controls counters values
		
		$StructurePermissibleValuesCustomControl = AppModel::getInstance('', 'StructurePermissibleValuesCustomControl');
		$has_many_details = array(
				'hasMany' => array(
						'StructurePermissibleValuesCustom' => array(
								'className' => 'StructurePermissibleValuesCustom',
								'foreignKey' => 'control_id')));
		$StructurePermissibleValuesCustomControl->bindModel($has_many_details);
		$all_cusom_lists_controls = $StructurePermissibleValuesCustomControl->find('all');
		foreach($all_cusom_lists_controls as $new_custom_list) {
			$values_used_as_input_counter = 0;
			$values_counter = 0;
			foreach($new_custom_list['StructurePermissibleValuesCustom'] as $new_custom_value) {
				if(!$new_custom_value['deleted']) {
					$values_counter++;
					if($new_custom_value['use_as_input']) $values_used_as_input_counter++;
				}
			}
			$StructurePermissibleValuesCustomControl->tryCatchQuery("UPDATE structure_permissible_values_custom_controls SET values_counter = $values_counter, values_used_as_input_counter = $values_used_as_input_counter WHERE id = ".$new_custom_list['StructurePermissibleValuesCustomControl']['id']);
		}
		
		// *** 10 *** rebuilts lft rght in storage_masters
		
		$storage_master_model = AppModel::getInstance('StorageLayout', 'StorageMaster', true);
		$result = $storage_master_model->find('first', array('conditions' => array('NOT' => array('StorageMaster.parent_id' => NULL), 'StorageMaster.lft' => NULL)));
		if($result){
			self::addWarningMsg(__('rebuilt lft rght for storage_masters'));
			$storage_master_model->recover('parent');
		}
		
		// *** 11 *** Disable unused treatment_extend_controls

		$this->Version->query("UPDATE treatment_extend_controls SET flag_active = 0 WHERE id NOT IN (select distinct treatment_extend_control_id from treatment_controls WHERE flag_active = 1 AND treatment_extend_control_id IS NOT NULL)");


		// *** 12 *** Check storage controls data
		
		$storage_ctrl_model = AppModel::getInstance('Administrate', 'StorageCtrl', true);
		$storage_ctrl_model->validatesAllStorageControls();
		
		// *** 12 *** Update structure_formats of 'shippeditems', 'orderitems', 'orderitems_returned' and 'orderlines' forms based on core variable 'order_item_type_config'
		
		$tmp_sql = "SELECT DISTINCT `flag_detail`
			FROM structure_formats
			WHERE structure_id=(SELECT id FROM structures WHERE alias='aliquot_masters')
			AND structure_field_id IN (SELECT id FROM structure_fields WHERE `model`='AliquotMaster' AND `tablename`='aliquot_masters' AND `field`='aliquot_label')";
		$flag_detail_result = $AliquotMaster_model->query($tmp_sql);
		$aliquot_label_flag_detail = '1';
		if($flag_detail_result) $aliquot_label_flag_detail = empty($flag_detail_result[0]['structure_formats']['flag_detail'])? '0' : '1';		
		
		$structure_formats_queries = array();
		switch(Configure::read('order_item_type_config')) {
			case '1':
				// both tma slide and aliquot could be added to order
				
				$structure_formats_queries = array(
					"UPDATE structure_formats SET `flag_edit`='1', `flag_edit_readonly`='1', `flag_editgrid`='1', `flag_editgrid_readonly`='1', `flag_index`='1', `flag_detail`='1'
						WHERE structure_field_id IN (SELECT id FROM structure_fields WHERE `plugin`='Order' AND `model`='Generated' AND `field`='type');",
					
					// 1 - 'shippeditems' structure
					"UPDATE structure_formats SET `flag_addgrid`=$aliquot_label_flag_detail, `flag_addgrid_readonly`=$aliquot_label_flag_detail, `flag_editgrid`=$aliquot_label_flag_detail, `flag_editgrid_readonly`=$aliquot_label_flag_detail, `flag_index`=$aliquot_label_flag_detail, `flag_detail`=$aliquot_label_flag_detail
						WHERE structure_id = (SELECT id FROM structures WHERE alias='shippeditems')
						AND structure_field_id IN (SELECT id FROM structure_fields WHERE `plugin`='InventoryManagement' AND `model`='AliquotMaster' AND `field`='aliquot_label');",
					"UPDATE structure_formats SET `flag_addgrid`='1', `flag_addgrid_readonly`='1', `flag_editgrid`='1', `flag_editgrid_readonly`='1', `flag_index`='1', `flag_detail`='1'
						WHERE structure_id = (SELECT id FROM structures WHERE alias='shippeditems')
						AND structure_field_id IN (SELECT id FROM structure_fields WHERE `plugin`='InventoryManagement' AND `model`='AliquotMaster' AND `field` NOT IN ('aliquot_label','barcode'));",
					"UPDATE structure_formats SET `flag_addgrid`='1', `flag_addgrid_readonly`='1', `flag_editgrid`='1', `flag_editgrid_readonly`='1', `flag_index`='1', `flag_detail`='1'
						WHERE structure_id = (SELECT id FROM structures WHERE alias='shippeditems')
						AND structure_field_id IN (SELECT id FROM structure_fields WHERE `plugin`='InventoryManagement' AND `model`='ViewAliquot' AND `field` NOT IN ('aliquot_label','barcode'));",
					"UPDATE structure_formats SET `flag_addgrid`='1', `flag_addgrid_readonly`='1', `flag_editgrid`='1', `flag_editgrid_readonly`='1', `flag_index`='1', `flag_detail`='1'
						WHERE structure_id = (SELECT id FROM structures WHERE alias='shippeditems')
						AND structure_field_id IN (SELECT id FROM structure_fields WHERE `plugin`='StorageLayout' AND `model`='TmaSlide' AND field != 'barcode');",
			
					// 2 - 'orderitems' structure
					"UPDATE structure_formats SET `flag_search`='1' 
						WHERE structure_id = (SELECT id FROM structures WHERE alias='orderitems') 
						AND structure_field_id IN (SELECT id FROM structure_fields WHERE `model`='AliquotMaster' AND `tablename`='aliquot_masters' AND `field`='barcode');",
					"UPDATE structure_formats SET `flag_search`='1' 
						WHERE structure_id = (SELECT id FROM structures WHERE alias='orderitems') 
						AND structure_field_id IN (SELECT id FROM structure_fields WHERE `model`='TmaSlide' AND `tablename`='tma_slides' AND `field`='barcode');",
					
					"UPDATE structure_formats SET `flag_edit`=$aliquot_label_flag_detail, `flag_edit_readonly`=$aliquot_label_flag_detail, `flag_editgrid`=$aliquot_label_flag_detail, `flag_editgrid_readonly`=$aliquot_label_flag_detail, `flag_index`=$aliquot_label_flag_detail, `flag_detail`=$aliquot_label_flag_detail 
						WHERE structure_id = (SELECT id FROM structures WHERE alias='orderitems') 
						AND structure_field_id IN (SELECT id FROM structure_fields WHERE `model`='AliquotMaster' AND `tablename`='aliquot_masters' AND `field`='aliquot_label');",
					"UPDATE structure_formats SET `flag_edit`='1', `flag_edit_readonly`='1', `flag_editgrid`='1', `flag_editgrid_readonly`='1', `flag_index`='1', `flag_detail`='1'
						WHERE structure_id = (SELECT id FROM structures WHERE alias='orderitems')
						AND structure_field_id IN (SELECT id FROM structure_fields WHERE `model`='AliquotMaster' AND `tablename`='aliquot_masters' AND `field` NOT IN ('aliquot_label','barcode'));",
					"UPDATE structure_formats SET `flag_edit`='1', `flag_edit_readonly`='1', `flag_editgrid`='1', `flag_editgrid_readonly`='1', `flag_index`='1', `flag_detail`='1'
						WHERE structure_id = (SELECT id FROM structures WHERE alias='orderitems')
						AND structure_field_id IN (SELECT id FROM structure_fields WHERE `plugin`='InventoryManagement' AND `model`='ViewAliquot' AND `field` NOT IN ('aliquot_label','barcode'));",
					"UPDATE structure_formats SET `flag_edit`='1', `flag_edit_readonly`='1', `flag_editgrid`='1', `flag_editgrid_readonly`='1', `flag_index`='1', `flag_detail`='1'
						WHERE structure_id = (SELECT id FROM structures WHERE alias='orderitems')
						AND structure_field_id IN (SELECT id FROM structure_fields WHERE `plugin`='StorageLayout' AND `model`='TmaSlide' AND field != 'barcode');",
					
					// 3 - 'orderitems_returned' structure
					"UPDATE structure_formats SET `flag_edit`=$aliquot_label_flag_detail, `flag_edit_readonly`=$aliquot_label_flag_detail, `flag_editgrid`=$aliquot_label_flag_detail, `flag_editgrid_readonly`=$aliquot_label_flag_detail
						WHERE structure_id = (SELECT id FROM structures WHERE alias='orderitems_returned')
						AND structure_field_id IN (SELECT id FROM structure_fields WHERE `model`='AliquotMaster' AND `tablename`='aliquot_masters' AND `field`='aliquot_label');",
					"UPDATE structure_formats SET `flag_edit`='1', `flag_edit_readonly`='1', `flag_editgrid`='1', `flag_editgrid_readonly`='1'
						WHERE structure_id = (SELECT id FROM structures WHERE alias='orderitems_returned')
						AND structure_field_id IN (SELECT id FROM structure_fields WHERE `model`='AliquotMaster' AND `tablename`='aliquot_masters' AND `field` NOT IN ('aliquot_label','barcode'));",
					"UPDATE structure_formats SET `flag_edit`='1', `flag_edit_readonly`='1', `flag_editgrid`='1', `flag_editgrid_readonly`='1'
						WHERE structure_id = (SELECT id FROM structures WHERE alias='orderitems_returned')
						AND structure_field_id IN (SELECT id FROM structure_fields WHERE `plugin`='InventoryManagement' AND `model`='ViewAliquot' AND `field` NOT IN ('aliquot_label','barcode'));",
					"UPDATE structure_formats SET `flag_edit`='1', `flag_edit_readonly`='1', `flag_editgrid`='1', `flag_editgrid_readonly`='1'
						WHERE structure_id = (SELECT id FROM structures WHERE alias='orderitems_returned')
						AND structure_field_id IN (SELECT id FROM structure_fields WHERE `plugin`='StorageLayout' AND `model`='TmaSlide' AND field != 'barcode');",
					
					// 4 - `orderlines` structure
					"UPDATE structure_formats SET `flag_search`='1' 
						WHERE structure_id = (SELECT id FROM structures WHERE alias='orderlines') 
						AND structure_field_id IN (SELECT id FROM structure_fields 
						WHERE `model`='OrderLine' AND `tablename`='order_lines' AND `field`='is_tma_slide' AND `structure_value_domain`  IS NULL  AND `flag_confidential`='0');",					
					"UPDATE structure_formats SET `flag_search`='1' 
						WHERE structure_id=(SELECT id FROM structures WHERE alias='orderlines') 
						AND structure_field_id=(SELECT id FROM structure_fields WHERE `model`='OrderLine' AND `tablename`='order_lines' AND `field`='sample_control_id' AND `structure_value_domain` =(SELECT id FROM structure_value_domains WHERE domain_name='sample_type_from_id') AND `flag_confidential`='0');",
					
					// 5 - `orderitems_plus` structure
					"UPDATE structure_formats SET `flag_index`='1'
						WHERE structure_id=(SELECT id FROM structures WHERE alias='orderitems_plus')
						AND structure_field_id=(SELECT id FROM structure_fields WHERE `model`='ViewAliquot' AND `tablename`='' AND `field`='sample_type' AND `structure_value_domain` =(SELECT id FROM structure_value_domains WHERE domain_name='sample_type') AND `flag_confidential`='0');",
					"UPDATE structure_formats SET `flag_index`='1'
						WHERE structure_id=(SELECT id FROM structures WHERE alias='orderitems_plus')
						AND structure_field_id=(SELECT id FROM structure_fields WHERE `model`='ViewAliquot' AND `tablename`='' AND `field`='aliquot_type' AND `structure_value_domain` =(SELECT id FROM structure_value_domains WHERE domain_name='aliquot_type') AND `flag_confidential`='0');");
				
				break;
		
			case '2':
				//  aliquot only could be added to order
				
				$structure_formats_queries = array(
					"UPDATE structure_formats SET `flag_edit`='0', `flag_edit_readonly`='0', `flag_editgrid`='0', `flag_editgrid_readonly`='0', `flag_index`='0', `flag_detail`='0'
						WHERE structure_field_id IN (SELECT id FROM structure_fields WHERE `plugin`='Order' AND `model`='Generated' AND `field`='type');",
					
					// 1 - 'shippeditems' structure
					"UPDATE structure_formats SET `flag_addgrid`=$aliquot_label_flag_detail, `flag_addgrid_readonly`=$aliquot_label_flag_detail, `flag_editgrid`=$aliquot_label_flag_detail, `flag_editgrid_readonly`=$aliquot_label_flag_detail, `flag_index`=$aliquot_label_flag_detail, `flag_detail`=$aliquot_label_flag_detail
						WHERE structure_id = (SELECT id FROM structures WHERE alias='shippeditems')
						AND structure_field_id IN (SELECT id FROM structure_fields WHERE `plugin`='InventoryManagement' AND `model`='AliquotMaster' AND `field`='aliquot_label');",
					"UPDATE structure_formats SET `flag_addgrid`='1', `flag_addgrid_readonly`='1', `flag_editgrid`='1', `flag_editgrid_readonly`='1', `flag_index`='1', `flag_detail`='1'
						WHERE structure_id = (SELECT id FROM structures WHERE alias='shippeditems')
						AND structure_field_id IN (SELECT id FROM structure_fields WHERE `plugin`='InventoryManagement' AND `model`='AliquotMaster' AND `field` NOT IN ('aliquot_label','barcode'));",
					"UPDATE structure_formats SET `flag_addgrid`='1', `flag_addgrid_readonly`='1', `flag_editgrid`='1', `flag_editgrid_readonly`='1', `flag_index`='1', `flag_detail`='1'
						WHERE structure_id = (SELECT id FROM structures WHERE alias='shippeditems')
						AND structure_field_id IN (SELECT id FROM structure_fields WHERE `plugin`='InventoryManagement' AND `model`='ViewAliquot' AND `field` NOT IN ('aliquot_label','barcode'));",
					"UPDATE structure_formats SET `flag_addgrid`='0', `flag_addgrid_readonly`='0', `flag_editgrid`='0', `flag_editgrid_readonly`='0', `flag_index`='0', `flag_detail`='0'
						WHERE structure_id = (SELECT id FROM structures WHERE alias='shippeditems')
						AND structure_field_id IN (SELECT id FROM structure_fields WHERE `plugin`='StorageLayout' AND `model`='TmaSlide');",
			
					// 2 - 'orderitems' structure
					"UPDATE structure_formats SET `flag_search`='1' 
						WHERE structure_id = (SELECT id FROM structures WHERE alias='orderitems') 
						AND structure_field_id IN (SELECT id FROM structure_fields WHERE `model`='AliquotMaster' AND `tablename`='aliquot_masters' AND `field`='barcode');",
					"UPDATE structure_formats SET `flag_search`='0' 
						WHERE structure_id = (SELECT id FROM structures WHERE alias='orderitems') 
						AND structure_field_id IN (SELECT id FROM structure_fields WHERE `model`='TmaSlide' AND `tablename`='tma_slides' AND `field`='barcode');",
					
					"UPDATE structure_formats SET `flag_edit`=$aliquot_label_flag_detail, `flag_edit_readonly`=$aliquot_label_flag_detail, `flag_editgrid`=$aliquot_label_flag_detail, `flag_editgrid_readonly`=$aliquot_label_flag_detail, `flag_index`=$aliquot_label_flag_detail, `flag_detail`=$aliquot_label_flag_detail 
						WHERE structure_id = (SELECT id FROM structures WHERE alias='orderitems') 
						AND structure_field_id IN (SELECT id FROM structure_fields WHERE `model`='AliquotMaster' AND `tablename`='aliquot_masters' AND `field`='aliquot_label');",
					"UPDATE structure_formats SET `flag_edit`='1', `flag_edit_readonly`='1', `flag_editgrid`='1', `flag_editgrid_readonly`='1', `flag_index`='1', `flag_detail`='1'
						WHERE structure_id = (SELECT id FROM structures WHERE alias='orderitems')
						AND structure_field_id IN (SELECT id FROM structure_fields WHERE `model`='AliquotMaster' AND `tablename`='aliquot_masters' AND `field` NOT IN ('aliquot_label','barcode'));",
					"UPDATE structure_formats SET `flag_edit`='1', `flag_edit_readonly`='1', `flag_editgrid`='1', `flag_editgrid_readonly`='1', `flag_index`='1', `flag_detail`='1'
						WHERE structure_id = (SELECT id FROM structures WHERE alias='orderitems')
						AND structure_field_id IN (SELECT id FROM structure_fields WHERE `plugin`='InventoryManagement' AND `model`='ViewAliquot' AND `field` NOT IN ('aliquot_label','barcode'));",
					"UPDATE structure_formats SET `flag_edit`='0', `flag_edit_readonly`='0', `flag_editgrid`='0', `flag_editgrid_readonly`='0', `flag_index`='0', `flag_detail`='0'
						WHERE structure_id = (SELECT id FROM structures WHERE alias='orderitems')
						AND structure_field_id IN (SELECT id FROM structure_fields WHERE `plugin`='StorageLayout' AND `model`='TmaSlide');",
					
					// 3 - 'orderitems_returned' structure
					"UPDATE structure_formats SET `flag_edit`=$aliquot_label_flag_detail, `flag_edit_readonly`=$aliquot_label_flag_detail, `flag_editgrid`=$aliquot_label_flag_detail, `flag_editgrid_readonly`=$aliquot_label_flag_detail
						WHERE structure_id = (SELECT id FROM structures WHERE alias='orderitems_returned')
						AND structure_field_id IN (SELECT id FROM structure_fields WHERE `model`='AliquotMaster' AND `tablename`='aliquot_masters' AND `field`='aliquot_label');",
					"UPDATE structure_formats SET `flag_edit`='1', `flag_edit_readonly`='1', `flag_editgrid`='1', `flag_editgrid_readonly`='1'
						WHERE structure_id = (SELECT id FROM structures WHERE alias='orderitems_returned')
						AND structure_field_id IN (SELECT id FROM structure_fields WHERE `model`='AliquotMaster' AND `tablename`='aliquot_masters' AND `field` NOT IN ('aliquot_label','barcode'));",
					"UPDATE structure_formats SET `flag_edit`='1', `flag_edit_readonly`='1', `flag_editgrid`='1', `flag_editgrid_readonly`='1'
						WHERE structure_id = (SELECT id FROM structures WHERE alias='orderitems_returned')
						AND structure_field_id IN (SELECT id FROM structure_fields WHERE `plugin`='InventoryManagement' AND `model`='ViewAliquot' AND `field` NOT IN ('aliquot_label','barcode'));",
					"UPDATE structure_formats SET `flag_edit`='0', `flag_edit_readonly`='0', `flag_editgrid`='0', `flag_editgrid_readonly`='0'
						WHERE structure_id = (SELECT id FROM structures WHERE alias='orderitems_returned')
						AND structure_field_id IN (SELECT id FROM structure_fields WHERE `plugin`='StorageLayout' AND `model`='TmaSlide');",
					
					// 4 - `orderlines` structure
					"UPDATE structure_formats SET `flag_search`='0' 
						WHERE structure_id = (SELECT id FROM structures WHERE alias='orderlines') 
						AND structure_field_id IN (SELECT id FROM structure_fields 
						WHERE `model`='OrderLine' AND `tablename`='order_lines' AND `field`='is_tma_slide' AND `structure_value_domain`  IS NULL  AND `flag_confidential`='0');",
					"UPDATE structure_formats SET `flag_search`='1' 
						WHERE structure_id=(SELECT id FROM structures WHERE alias='orderlines') 
						AND structure_field_id=(SELECT id FROM structure_fields WHERE `model`='OrderLine' AND `tablename`='order_lines' AND `field`='sample_control_id' AND `structure_value_domain` =(SELECT id FROM structure_value_domains WHERE domain_name='sample_type_from_id') AND `flag_confidential`='0');",
					
					// 5 - `orderitems_plus` structure
					"UPDATE structure_formats SET `flag_index`='1'
						WHERE structure_id=(SELECT id FROM structures WHERE alias='orderitems_plus')
						AND structure_field_id=(SELECT id FROM structure_fields WHERE `model`='ViewAliquot' AND `tablename`='' AND `field`='sample_type' AND `structure_value_domain` =(SELECT id FROM structure_value_domains WHERE domain_name='sample_type') AND `flag_confidential`='0');",
					"UPDATE structure_formats SET `flag_index`='1'
						WHERE structure_id=(SELECT id FROM structures WHERE alias='orderitems_plus')
						AND structure_field_id=(SELECT id FROM structure_fields WHERE `model`='ViewAliquot' AND `tablename`='' AND `field`='aliquot_type' AND `structure_value_domain` =(SELECT id FROM structure_value_domains WHERE domain_name='aliquot_type') AND `flag_confidential`='0');");
				
				break;
		
			case '3':
				// tma slide only could be added to order
				
				$structure_formats_queries = array(
					"UPDATE structure_formats SET `flag_edit`='0', `flag_edit_readonly`='0', `flag_editgrid`='0', `flag_editgrid_readonly`='0', `flag_index`='0', `flag_detail`='0'
						WHERE structure_field_id IN (SELECT id FROM structure_fields WHERE `plugin`='Order' AND `model`='Generated' AND `field`='type');",
					
					// 1 - 'shippeditems' structure
					"UPDATE structure_formats SET `flag_addgrid`='0', `flag_addgrid_readonly`='0', `flag_editgrid`='0', `flag_editgrid_readonly`='0', `flag_index`='0', `flag_detail`='0'
						WHERE structure_id = (SELECT id FROM structures WHERE alias='shippeditems')
						AND structure_field_id IN (SELECT id FROM structure_fields WHERE `plugin`='InventoryManagement' AND `model`='AliquotMaster');",
					"UPDATE structure_formats SET `flag_addgrid`='0', `flag_addgrid_readonly`='0', `flag_editgrid`='0', `flag_editgrid_readonly`='0', `flag_index`='0', `flag_detail`='0'
						WHERE structure_id = (SELECT id FROM structures WHERE alias='shippeditems')
						AND structure_field_id IN (SELECT id FROM structure_fields WHERE `plugin`='InventoryManagement' AND `model`='ViewAliquot');",
					"UPDATE structure_formats SET `flag_addgrid`='1', `flag_addgrid_readonly`='1', `flag_editgrid`='1', `flag_editgrid_readonly`='1', `flag_index`='1', `flag_detail`='1'
						WHERE structure_id = (SELECT id FROM structures WHERE alias='shippeditems')
						AND structure_field_id IN (SELECT id FROM structure_fields WHERE `plugin`='StorageLayout' AND `model`='TmaSlide' AND field != 'barcode');",
			
					// 2 - 'orderitems' structure
					"UPDATE structure_formats SET `flag_search`='0' 
						WHERE structure_id = (SELECT id FROM structures WHERE alias='orderitems') 
						AND structure_field_id IN (SELECT id FROM structure_fields WHERE `model`='AliquotMaster' AND `tablename`='aliquot_masters' AND `field`='barcode');",
					"UPDATE structure_formats SET `flag_search`='1' 
						WHERE structure_id = (SELECT id FROM structures WHERE alias='orderitems') 
						AND structure_field_id IN (SELECT id FROM structure_fields WHERE `model`='TmaSlide' AND `tablename`='tma_slides' AND `field`='barcode');",
					
					"UPDATE structure_formats SET `flag_edit`='0', `flag_edit_readonly`='0', `flag_editgrid`='0', `flag_editgrid_readonly`='0', `flag_index`='0', `flag_detail`='0' 
						WHERE structure_id = (SELECT id FROM structures WHERE alias='orderitems') 
						AND structure_field_id IN (SELECT id FROM structure_fields WHERE `model`='AliquotMaster' AND `tablename`='aliquot_masters');",
					"UPDATE structure_formats SET `flag_edit`='0', `flag_edit_readonly`='0', `flag_editgrid`='0', `flag_editgrid_readonly`='0', `flag_index`='0', `flag_detail`='0'
						WHERE structure_id = (SELECT id FROM structures WHERE alias='orderitems')
						AND structure_field_id IN (SELECT id FROM structure_fields WHERE `plugin`='InventoryManagement' AND `model`='ViewAliquot');",
					"UPDATE structure_formats SET `flag_edit`='1', `flag_edit_readonly`='1', `flag_editgrid`='1', `flag_editgrid_readonly`='1', `flag_index`='1', `flag_detail`='1'
						WHERE structure_id = (SELECT id FROM structures WHERE alias='orderitems')
						AND structure_field_id IN (SELECT id FROM structure_fields WHERE `plugin`='StorageLayout' AND `model`='TmaSlide' AND field != 'barcode');",
					
					// 3 - 'orderitems_returned' structure
					"UPDATE structure_formats SET `flag_edit`='0', `flag_edit_readonly`='0', `flag_editgrid`='0', `flag_editgrid_readonly`='0'
						WHERE structure_id = (SELECT id FROM structures WHERE alias='orderitems_returned')
						AND structure_field_id IN (SELECT id FROM structure_fields WHERE `model`='AliquotMaster' AND `tablename`='aliquot_masters');",
					"UPDATE structure_formats SET `flag_edit`='0', `flag_edit_readonly`='0', `flag_editgrid`='0', `flag_editgrid_readonly`='0'
						WHERE structure_id = (SELECT id FROM structures WHERE alias='orderitems_returned')
						AND structure_field_id IN (SELECT id FROM structure_fields WHERE `plugin`='InventoryManagement' AND `model`='ViewAliquot');",
					"UPDATE structure_formats SET `flag_edit`='1', `flag_edit_readonly`='1', `flag_editgrid`='1', `flag_editgrid_readonly`='1'
						WHERE structure_id = (SELECT id FROM structures WHERE alias='orderitems_returned')
						AND structure_field_id IN (SELECT id FROM structure_fields WHERE `plugin`='StorageLayout' AND `model`='TmaSlide' AND field != 'barcode');",
					
					// 4 - `orderlines` structure
					"UPDATE structure_formats SET `flag_search`='0' 
						WHERE structure_id = (SELECT id FROM structures WHERE alias='orderlines') 
						AND structure_field_id IN (SELECT id FROM structure_fields 
						WHERE `model`='OrderLine' AND `tablename`='order_lines' AND `field`='is_tma_slide' AND `structure_value_domain`  IS NULL  AND `flag_confidential`='0');",					
					"UPDATE structure_formats SET `flag_search`='0' 
						WHERE structure_id=(SELECT id FROM structures WHERE alias='orderlines') 
						AND structure_field_id=(SELECT id FROM structure_fields WHERE `model`='OrderLine' AND `tablename`='order_lines' AND `field`='sample_control_id' AND `structure_value_domain` =(SELECT id FROM structure_value_domains WHERE domain_name='sample_type_from_id') AND `flag_confidential`='0');",
				
				
					// 5 - `orderitems_plus` structure
					"UPDATE structure_formats SET `flag_index`='0' 
						WHERE structure_id=(SELECT id FROM structures WHERE alias='orderitems_plus') 
						AND structure_field_id=(SELECT id FROM structure_fields WHERE `model`='ViewAliquot' AND `tablename`='' AND `field`='sample_type' AND `structure_value_domain` =(SELECT id FROM structure_value_domains WHERE domain_name='sample_type') AND `flag_confidential`='0');",
					"UPDATE structure_formats SET `flag_index`='0' 
						WHERE structure_id=(SELECT id FROM structures WHERE alias='orderitems_plus') 
						AND structure_field_id=(SELECT id FROM structure_fields WHERE `model`='ViewAliquot' AND `tablename`='' AND `field`='aliquot_type' AND `structure_value_domain` =(SELECT id FROM structure_value_domains WHERE domain_name='aliquot_type') AND `flag_confidential`='0');");
				
				break;
		
			default:	
		}
		foreach($structure_formats_queries as $tmp_sql) $AliquotMaster_model->query($tmp_sql);
		AppController::addWarningMsg(__("structures 'shippeditems', 'orderitems', 'orderitems_returned' and 'orderlines' have been updated based on the core variable 'order_item_type_config'."));
				
		//------------------------------------------------------------------------------------------------------------------------------------------
				
		//update the permissions_regenerated flag and redirect
		$this->Version->data = array('Version' => array('permissions_regenerated' => 1));
		$this->Version->check_writable_fields = false;
		if($this->Version->save()){
			$this->redirect('/Users/login');
		}
	}
	
	function configureCsv($config){
		$this->csv_config = $config;
		$this->Session->write('Config.language', $config['config_language']);
	}
}
	
	AppController::init();
	
	function myErrorHandler($errno, $errstr, $errfile, $errline, $context = null){
		if(class_exists("AppController")){
			$controller = AppController::getInstance();
			if($errno == E_USER_WARNING && strpos($errstr, "SQL Error:") !== false && $controller->name != 'Pages'){
				$traceMsg = "<table><tr><th>File</th><th>Line</th><th>Function</th></tr>";
				try{
					throw new Exception("");
				}catch(Exception $e){
					$traceArr = $e->getTrace();
					foreach($traceArr as $traceLine){
						if(is_array($traceLine)){
							$traceMsg .= "<tr><td>"
							.(isset($traceLine['file']) ?
							$traceLine['file'] : "")
							."</td><td>"
							.(isset($traceLine['line']) ?
							$traceLine['line'] : "")
							."</td><td>".$traceLine['function']."</td></tr>";
						}else{
							$traceMsg .= "<tr><td></td><td></td><td></td></tr>";
						}
					}
				}
				$traceMsg .= "</table>";
				$controller->redirect('/Pages/err_query?err_msg='.urlencode($errstr.$traceMsg));
			}
		}
		return false;
	}
	
/**
 * Returns the date in a classic format (usefull for SQL)
 * @throws Exception
 */
function now(){
	return date("Y-m-d H:i:s");
}
