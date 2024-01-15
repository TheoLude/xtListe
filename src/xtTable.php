<?php

class xTable extends main {

	protected $bool_disable_all_filter = false;
	protected $bool_disable_cookie = false;
	protected $bool_is_cookie_setup = false;
	protected $str_default_sort = '';
	protected $str_ini_file = '';
	protected $str_query = '';
	protected $str_ajax_url = '';
	protected $str_default_where = '';
	protected $str_encrypt_key = '';
	protected $str_default_sort_field = '';
	protected $str_default_sort_dir = '';
	protected $str_js_function_binding_on_click = '';
	protected $str_sort_field = '';
	protected $str_sort_direction = '';
	protected $str_user_error = '';
	protected $int_nb_showed_row = 0;
	protected $int_nb_records = 0;
	protected $int_no_page = 1;
	protected $int_list_height = 0;
	protected $int_font_width_multiplicator = 7;
	protected $int_nb_records_by_page = 100;
	protected $tab_tables = array();
	protected $tab_fields = array();
	protected $tab_col_width = array();
	protected $tab_alias = array();
	protected $tab_filter_groups = array();
	protected $tab_crypted_filters = array();
	protected $tab_user_selected_filters = array();
	protected $tab_filter_group_id = array();
	protected $tab_binding = array();
	protected $tab_filter_values = array();
	protected $tab_search_fields = array();
	protected $tabFormatFuntionlist = array();
	protected $obj_sql;
	protected $obj_html;
	protected $boolXlsOutput;

	/**
	* Constructeur de la classe, initialisation des variables propres à la classe et analyse de la requête SQL
	*
	*
	*
	*/
	public function xTable($str_ini_file = '', $bool_disable_cookie = false){

		//if (constant('CST_BOOL_DEBUG')) $this -> logDebug(__FILE__);
		if (true) $this -> logDebug(__FILE__);

		// Récupération des propriétés générales et d'affichage
		if (! $str_ini_file) $str_ini_file = basename($_SERVER['SCRIPT_NAME'], '.php');
		$this -> tab_config = parse_ini_file(constant('XTABLE_SQL_PATH').$str_ini_file.'.sql', true);
		$this -> putInLog(__LINE__." -- ".constant('XTABLE_SQL_PATH').$str_ini_file.'.sql'." -- ".caliChrono()."<br>\r\n");

		$this -> str_ini_file = $str_ini_file;
		$this -> str_query = $this -> tab_config['query'];
		$this -> int_nb_showed_row = $this -> tab_config['rows'];
		$this -> int_nb_records_by_page = $this -> tab_config['records'];
		$this -> str_ajax_url = $this -> tab_config['ajaxurl'];
		$this -> int_font_width_multiplicator = $this -> tab_config['fontwidthmultiplicator'];
		if (! $this -> int_font_width_multiplicator) $this -> int_font_width_multiplicator = 10;
		$this -> bool_disable_cookie = $bool_disable_cookie;
		
		$this -> putInLog(__LINE__." -- ".$this -> str_query." -- ".caliChrono()."<br>\r\n");

		// Clef pour l'encryption
		$this -> str_encrypt_key = constant('SID');

		// Liaison à la BDD
		$this -> obj_sql = new dataBaseAbstractLayer();

		// Analyse de la requête SQL et récupération des propriétés des champs à afficher
		$str_regexp = "/^SELECT (?:DISTINCT){0,1}(?'select'.*?)FROM (?'from'.+?)(?'jointure'(INNER|LEFT OUTER) JOIN(?:.*?))? WHERE (?'where'.+)?/iS";
		preg_match_all($str_regexp, $this -> str_query, $tab_match_query);
		$this -> putInLog(__LINE__." -- ".$this -> str_query." -- ".caliChrono()."<br>\r\n");

		// Récupération du tri par défaut
		$str_regexp = "/(ORDER BY (?'order'.+)?)/iS";
		preg_match_all($str_regexp, $tab_match_query['where'][0], $tab_match_subquery);
		if ($tab_match_subquery['order'][0]) {
			$this -> str_default_sort = $tab_match_subquery['order'][0];

			if (! strpos($this -> str_default_sort, ',')){ //[ADD Théo 18/09/2013] Eviter les problèmes avec les tris initiaux paramétrés sur plusieurs champs
				list($this -> str_default_sort_field, $this -> str_default_sort_dir) = explode(' ', trim($this -> str_default_sort));
				$this -> str_default_sort_field = trim($this -> str_default_sort_field);
				$this -> str_default_sort_dir = trim($this -> str_default_sort_dir);
			}
			else { //[ADD Théo 18/09/2013] Gestion des tris paramétrés multiples
				$tab_sort = explode(',', $this -> str_default_sort);
				foreach($tab_sort as $i => $str_sort){
					list($str_tmp_sort_field, $str_tmp_sort_dir) = explode(' ', trim($str_sort));
					$this -> str_default_sort_field .= (($this -> str_default_sort_field)?',':'').$str_tmp_sort_field;
					$this -> str_default_sort_dir .= (($this -> str_default_sort_dir)?',':'').$str_tmp_sort_dir;
				}
			}
		}

		$this -> putInLog(__LINE__." -- ".$this -> str_query." -- ".$tab_match_subquery[0]." -- ".caliChrono()."<br>\r\n");
		$this -> str_query = str_replace($tab_match_subquery[0], '', $this -> str_query);
		$this -> putInLog(__LINE__." -- ".$this -> str_query." -- ".caliChrono()."<br>\r\n");

		// Récupération des critères de recherche par défaut
		if ($tab_match_query['where'][0]) $this -> str_default_where = str_replace($tab_match_subquery[0], '', $tab_match_query['where'][0]);

		$tab_tables[0] = $tab_match_query['from'][0];
		$this -> tab_fields = $this -> parseSelectFields($tab_match_query['select'][0]);

		$str_regexp = "/JOIN (?'table'[a-zA-Z_]+(?: AS)? [a-zA-Z_]+) ON/S";
		preg_match_all($str_regexp, $tab_match_query['jointure'][0], $tab_match_query);

		foreach($tab_match_query['table'] as $i => $str_table) if ($str_table) $tab_tables[count($tab_tables)] = $str_table;
		foreach($tab_tables as $i => $str_table){
			$str_table = str_ireplace(' AS ', '', trim($str_table));
			list($str_table_name, $str_alias) = explode(' ', $str_table);

			$this -> tab_tables[$i] = array('table' => $str_table_name, 'alias' => $str_alias);
			$this -> tab_alias[$str_alias] = $i;
		}

		foreach($this -> tab_fields as $i => $tab_properties){

			$tab_res = array();
			$str_type = 'any';
			if ($tab_properties['table']){
				$str_query = 'SHOW FIELDS FROM '.$this -> tab_tables[$this -> tab_alias[$tab_properties['table']]]['table'].' ';
				$str_query .= 'WHERE field = "'.$tab_properties['field'].'" ';
				$this -> putInLog( __LINE__." -- ".caliChrono()."<br>\r\n");
				$this -> obj_sql -> dbal_query($str_query);
				$this -> putInLog( __LINE__." -- ".caliChrono()."<br>\r\n");
				$this -> obj_sql -> dbal_fetch($tab_res);
				$str_type = strtolower(preg_replace('/\([0-9]+\)/', '', $tab_res['Type']));
			}

			$int_size = 10;
			if ($tab_res['Type'] == 'datetime') $int_size = 14;
			if ($tab_res['Type'] == 'time') $int_size = 8;
			if ($tab_res['Type'] == 'date') $int_size = 10;
			if (preg_match_all('/\(([0-9]+)\)/', $tab_res['Type'], $tab_match)) $int_size = $tab_match[1][0];

			$this -> tab_fields[$i]['type'] = $str_type;
			$this -> tab_fields[$i]['tableName'] = $this -> tab_tables[$this -> tab_alias[$tab_properties['table']]]['table'];
			$this -> tab_fields[$i]['size'] = $int_size;
			$this -> tab_fields[$i]['sort'] = 'anysort';

			$this -> tab_search_fields[$i] = '';
			$this -> tab_col_width[$i];
		}

		// Gestion des fonctions de mise en forme associées aux champs
		$this -> tabFormatFuntionlist = explode(',', $this -> tab_config['formatfunctionlist']);
		$this -> putInLog( __LINE__." -- ".$this -> tab_config['formatfunctionlist']."<br>\r\n");

		foreach($this -> tabFormatFuntionlist as $i => $strFunctiontag){
			$this -> putInLog( __LINE__." -- $strFunctiontag -- ".caliChrono()."<br>\r\n");

			if(isset($this -> tab_config[$strFunctiontag])){
				if (! $this -> tabFieldsName[$strFunctionTag]) {
					$this -> addError("<li> Le champ ".$strFunctiontag." est associé à une fonction, mais n'est pas déclaré dans la requête.");
					continue;
				}

				/* [DEL Théo 03/10/2022] Je ne sais plus pourquoi je faisais ce truc tordu ... ça ne sert à rien ...
				$objFunction = json_decode(str_replace("'", '"', utf8_encode($this -> tab_config[$strFunctiontag])));
				$this -> putInLog( __LINE__." -- $objFunction -- ".$this -> tab_config[$strFunctiontag]." -- ".caliChrono()."<br>\r\n");

				if (! is_object($objFunction)){
				$this -> addError("<li> Déclaration JSON de la fonction de formatage ".$strFunctiontag." non conforme.");
				continue;
				}*/

				if (! function_exists($this -> tab_config[$strFunctiontag])) {
					$this -> addError("<li> La fonction de formatage ".$objFunction -> formatFunction." associée au champ ".$strFunctiontag." n'a pas été déclarée / n'est pas connue.");
					continue;
				}
				else $this -> bindFieldValueWithFunction($strFunctiontag, $this -> tab_config[$strFunctiontag], $objFunction -> arguments[0], $objFunction -> arguments[1]);
			}
		}
		// Gestion des groupes de filtres
		//
		// filteringgrouplist = "grpcom,centrale"						<--- La liste des étiquettes de chacun des groupes de filtre
		// 	{'groupName': 'Commerciaux',								<--- Le libellé du groupe de filtre
		//	 'booloperand':'OR',										<--- L'opérateur associé à chacun des filtres de ce groupe de filtre
		//	 'values': ...
		//
		//
		//
		//
		$tab_group_list = explode(',', $this -> tab_config['filteringgrouplist']);
		foreach($tab_group_list as $i => $str_group_tag){

			if(isset($this -> tab_config[$str_group_tag])){
				$this -> tab_filter_group_id[$i] = $str_group_tag;
				$obj_group = json_decode(str_replace("'", '"', utf8_encode($this -> tab_config[$str_group_tag])));

				if (! is_object($obj_group)){
					$this -> addError("<li> Déclaration JSON du filtre ".$str_group_tag." non conforme.");
					continue;
				}

				$int_group_id = $this -> setFilterGroup($obj_group -> groupName, $obj_group -> booloperand);
				if (! $obj_group -> values) $this -> addError("<li> Attribut 'values' du groupe de filtre ".$str_group_tag." absent.");
				switch(strtolower($obj_group -> values -> src)){
					case 'fixed':
					/**
					* 'values':{
					'src':'fixed',
					'filters':[
					{'label':'test',						<--- L'élément qui va servir à construire le label du premier filter associé à ce groupe
					'sql':'AND a.dateCommande >= NOW()'}, 	<--- L'élément qui va servir à construire la requête SQL associée au premier filter associé à ce groupe

					{'label':'test2',
					'sql':'AND a.dateCommande < NOW()'},

					...]
					}}
					*
					*/
					$f = 0;
					for($f = 0; $f < count($obj_group -> values -> filters); $f ++){
						$obj_filt = $obj_group -> values -> filters[$f];
						$this -> addFilterToGroup($int_group_id, $obj_filt -> label, $obj_filt -> sql);
					}
					break;
					case 'sql':
					/**
					* 'values':{
					'src':'sql',
					'query': '*query*',      <------------------ La requête SQL qui va construire la liste des filtes
					'filters': [
					{'label': '*#1*',                <------ L'élément qui va servir à construire le label de chacun des filtres #n sera remplacé par la valeur du nième élément séléctionnés par la requête
					'sql': '*AND b.centrale = #2*' }<------ L'élément qui va servir à construire la requête SQL associée à chacun des filtres #n sera remplacé par la valeur du nième élément séléctionnés par la requête
					]
					}}
					*
					*/
					if (! $obj_group -> values -> query) $this -> addError("<li> Attribut SQL du groupe de filtre ".$str_group_tag." non conforme.");
					else {
						$this -> putInLog( __LINE__." -- ".caliChrono()."<br>\r\n");
						$this -> obj_sql -> dbal_query($obj_group -> values -> query);
						$this -> putInLog( __LINE__." -- ".caliChrono()."<br>\r\n");
						while ($this -> obj_sql -> dbal_fetch($tab_res)){
							$k = 1;
							$obj_filt = $obj_group -> values -> filters[0];
							$str_label = $obj_filt -> label;
							$str_sql = $obj_filt -> sql;
							foreach($tab_res as $str_key => $str_value){
								$str_label = str_replace('#'.$k, $str_value, $str_label);
								$str_sql = str_replace('#'.$k, $str_value, $str_sql);

								$k ++;
							}

							$this -> addFilterToGroup($int_group_id, $str_label, $str_sql);
						}
					}
					break;
					default:
					$this -> addError("<li> Attribut SRC du groupe de filtre ".$str_group_tag." non conforme.");
					continue;
				}
			}
		}

		$this -> putInLog(__LINE__." -- ".caliChrono()."<br>\r\n");

		//[ADD Théo 24/07/2013]
		// Intégration en premier lieu des éléments stockés dans le cookie associé à la liste
		$this -> getCookieAndFiltersValue();
	}

	/**
	* Cette méthode permet d'intégrer dans la classe des valeurs extérieures provenant de formulaires
	*
	*/
	private function importArguments($str_var_name){
		if (preg_match('/->/', $str_var_name)) {
			$tab_tmp = explode('->', $str_var_name);
			eval('global $'.$tab_tmp[0].';');
			eval('$str_val = $'.$tab_tmp[0].' -> '.$tab_tmp[1].';');
			return $str_val;
		}
		global $$str_var_name;
		return $$str_var_name;
	}

	/**
	* Cette méthode permet de définir une valeur sélectionnée par défaut
	*
	*
	*/
	public function setDefaultSelection($strField, $strValue){
		$this -> strDefaultSelection[$strField] = $strValue;
	}

	/**
	* Positionne la variable pour une sortie au format XLS
	*
	*
	*/
	public function setXlsOutput($boolXlsOutput){
		$this -> boolXlsOutput = $boolXlsOutput;
	}

	/**
	* Cette méthode lit le cookie de configuration de la xTable ET construit les filtres à partir de l'url appelée
	*
	*
	*/
	private function getCookieAndFiltersValue(){

		$tab_cookie_values = json_decode(stripslashes($_COOKIE[$this -> str_ini_file]));
		$this -> bool_is_cookie_setup = (is_object($tab_cookie_values));

		$this -> putInLog(__LINE__." -- ".$this -> str_default_sort." -- ".caliChrono()."<br>\r\n");
		if (! $this -> bool_disable_cookie && $this -> bool_is_cookie_setup){

			if (is_array($tab_cookie_values -> cols)) foreach($tab_cookie_values -> cols as $int_col => $flo_width) $this -> setColWidth($int_col, $flo_width);
			$this -> setDataLimit($tab_cookie_values -> datalimit);
			$this -> goToPage($tab_cookie_values -> page);
			//if ($tab_cookie_values -> sort -> field != $this -> str_default_sort_field
			//	&& $tab_cookie_values -> sort -> direction != $this -> str_default_sort_dir) $this -> setSortBy($tab_cookie_values -> sort -> field, $tab_cookie_values -> sort -> direction);
			$this -> setSortBy($tab_cookie_values -> sort -> field, $tab_cookie_values -> sort -> direction);

			if (is_array($tab_cookie_values -> search)) foreach($tab_cookie_values -> search as $int_index => $str_value) $this -> addSearchCriteria($int_index, $str_value);
			$this -> setListHeight($tab_cookie_values -> height);
		}

		$this -> putInLog(__LINE__." -- ".$this -> str_default_sort." -- ".caliChrono()."<br>\r\n");
		// Intégration des filtres
		for ($i = 0; $i < count($this -> tab_filter_group_id); $i ++){
			if (! $this -> tab_filter_groups[$i + 1]['disable']) {

				if (! $this -> bool_disable_cookie && $this -> bool_is_cookie_setup) { // A partir des cookies (premier affichage)
					$str_group_id = $this -> tab_filter_group_id[$i];
					if ($tab_cookie_values -> filters -> $str_group_id) $this -> tab_filter_values[$i] = explode(',', $tab_cookie_values -> filters -> $str_group_id);
				}
				else $this -> tab_filter_values[$i] = explode(',', $this -> importArguments($this -> tab_filter_group_id[$i])); // A partir d'une URL (appel Ajax)
			}
		}
		$this -> putInLog(__LINE__." -- ".$this -> str_default_sort." -- ".caliChrono()."<br>\r\n");
	}

	/**
	* Cette méthode crée le cookie de configuration et le dépose sur le client
	*
	*
	*/
	private function putCookieValues(){
		foreach($this -> tab_col_width as $int_col_index => $flo_width) $tab_cookie_values['cols'][$int_col_index] = $flo_width;
		$tab_cookie_values['datalimit'] = $this -> int_nb_records_by_page;
		$tab_cookie_values['page'] = $this -> int_no_page;
		$tab_cookie_values['sort']['field'] = utf8_encode($this -> str_sort_field);
		$tab_cookie_values['sort']['direction'] = utf8_encode($this -> str_sort_direction);
		foreach($this -> tab_search_fields as $int_index => $str_value) $tab_cookie_values['search'][$int_index] = utf8_encode($str_value);
		$tab_cookie_values['height'] = $this -> int_list_height;

		for ($i = 0; $i < count($this -> tab_filter_group_id); $i ++){
			if (! $this -> tab_filter_groups[$i + 1]['disable']) {
				if (is_array($this -> tab_filter_values[$i])) $tab_cookie_values['filters'][$this -> tab_filter_group_id[$i]] = implode(',', $this -> tab_filter_values[$i]);
				else $tab_cookie_values['filters'][$this -> tab_filter_group_id[$i]] = '';
			}
		}

		setcookie($this -> str_ini_file, json_encode($tab_cookie_values), time()+60*60*24*30, '/', 'deromafrance.com');
	}

	/**
	* Renvoie la liste des champs sélectionnés sous forme d'un tableau
	*
	*
	*/
	public function getSelectedFields(){
		return $this -> tab_fields;
	}

	/**
	* Spécifie la hauteur d'affichage de la liste en pixels
	*
	*
	*/
	public function setListHeight($int_height){
		$this -> int_list_height = $int_height;
	}

	/**
	* Associe un champ sélectionné dans la requête à une fonction PHP pour l'affichage du retour
	*
	*
	*/
	public function bindFieldValueWithFunction($str_field, $str_function_name, $int_size = 80, $str_align = 'left'){

		$boolOk = false;
		foreach($this -> tab_fields as $int_field_index => $tab_prop){
			$this -> putInLog(__LINE__." -- ".$tab_prop['field']." = $str_field<br>\r\n");
			if (strtolower($tab_prop['field']) == strtolower($str_field) || strtolower($tab_prop['alias']) == strtolower($str_field)) {
				$boolOk = true;
				break;
			}
		}
		$this -> putInLog(__LINE__." -- int_field_index = $int_field_index<br>\r\n");

		if (!$boolOk) return $this -> addError('<li> Binding avec un champ ('.$str_field.') inexistant dans le fichier SQL de définition de la table.');
		if (! function_exists($str_function_name)) return $this -> addError('<li> Binding avec une fonction ('.$str_function_name.') inexistante.');

		$this -> tab_fields[$int_field_index]['bindTo'] = $str_function_name;
		$this -> tab_fields[$int_field_index]['size'] = $int_size;
		$this -> tab_fields[$int_field_index]['align'] = $str_align;
		$this -> tab_binding[] = "{'k':'".$int_field_index."','f':'".$str_function_name."'}";
	}

	/**
	* Permet de masquer un champ tout en le faisant apparaitre dans la requête SQL
	* (pour gérer des clefs de tables mais qui seront masqués à l'utilisateur)
	*
	* @param string $str_fields - La liste du noms des champs à masquer, séparés par des virgules
	*
	*/
	public function disableFields($str_fields){
		$tab_fields = explode(',', $str_fields);
		for($i = 0; $i < count($tab_fields); $i ++){
			$str_field = $tab_fields[$i];
			if ($str_field){
				foreach($this -> tab_fields as $int_field_index => $tab_prop){
					if (strtolower($tab_prop['field']) == strtolower($str_field)) break;
				}

				if (! isset($int_field_index)) return $this -> addError("<li> Masquage d'un champ (".$str_field.') inexistant dans le fichier SQL de définition de la table.');
				$this -> tab_fields[$int_field_index]['hide'] = true;
			}
		}
	}

	/**
	* Reconstitue les binding avec la variable strBinding renvoyée par l'objet xtTable
	*
	*/
	public function setBinding($str_binding){
		$this -> tab_binding = json_decode(str_replace("'", '"', utf8_encode($str_binding)));
		if(is_array($this -> tab_binding)){
			foreach($this -> tab_binding as $int_i => $obj_binding) {
				$this -> tab_fields[$obj_binding -> k]['bindTo'] = $obj_binding -> f;
			}
		}
	}

	/**
	* Spécifie les largeurs de colonne
	*
	* @param integer $int_col_index - L'index de la colonne
	* @param integer $int_width - La largeur de la colonne en pixels
	*
	*/
	public function setColWidth($int_col_index, $int_width){
		$this -> tab_col_width[$int_col_index] = $int_width;
	}

	/**
	* Renvoie le path de l'image de tri par défaut
	*
	*
	*/
	public function getDefaultSortImagePath(){
		return constant('CST_ENV_IMG').$this -> tab_config['images']['anysort'];
	}

	/**
	* Renvoie la valeur du tri par défaut
	*
	*
	*/
	public function getDefaultSort(){
		return 'any';
	}

	/**
	* Associe une fonction javaScript à chaque sélection de ligne
	*
	*
	*/
	public function bindJsFunctionOnClick($str_function){
		$this -> str_js_function_binding_on_click = $str_function;
	}

	/**
	* Renvoie tout le contenu HTML du tableau
	*
	*
	*/
	public function getAllHtmlContent(){

		// Construction du HTML
		$this -> obj_html = new ModeliXe('xtTable.html');
		$this -> obj_html -> SetMxTemplatePath(constant('X_TEMPLATE_PATH'));
		$this -> obj_html -> SetMxFileParameter(constant('CST_ENV_MXP'));
		$this -> obj_html -> SetModeliXe(true);

		$this -> buildColumns();
		$this -> obj_html -> MxBloc('rowData', 'modify', $this -> buildRows(true));
		$this -> buildFilterBox();

		return $this -> obj_html -> MxWrite();
	}

	/**
	* Spécifie le nombre de lignes affichées par écran
	*
	*
	*
	*/
	public function setDataLimit($intQtyByScreen){
		$this -> int_nb_records_by_page = $intQtyByScreen;
	}

	/**
	* Spécifie le numéro d'écran à afficher
	*
	*
	*/
	public function goToPage($intGoToPage){
		$this -> int_no_page = $intGoToPage;
	}

	/**
	* Renvoie le nb total de pages de résultats
	*
	*
	*/
	public function getPageQty(){
		return $this -> int_nb_total_pages;
	}

	/**
	* Renvoie le nb total d'enregistrements trouvés
	*
	*
	*/
	public function getRecordQty(){
		return $this -> int_nb_total_records;
	}

	/**
	* Ajout d'un critère d'ordonnancement sur une colonne
	*
	* @param string $str_sort_argument - Le champ sur lequel l'ordonnancement est fait
	* @param string $str_sort_direction - La direction de l'ordonnancement
	*/
	public function setSortBy($str_sort_argument, $str_sort_direction){
		if ($str_sort_argument && $str_sort_direction){
			if (strpos($str_sort_argument, ',')) { //[ADD Théo 18/09/2013] Gestion des arguments de tris multiples
				$tab_sort = explode(',', $str_sort_argument);
				$tab_dir = explode(',', $str_sort_direction);
				foreach($tab_sort as $i => $str_sort){

					$str_tmp_sort_arg = $str_sort;
					$str_tmp_sort_dir = $tab_dir[$i];

					if (strtolower($str_tmp_sort_dir) != 'any'
					&& $str_tmp_sort_arg != $this -> str_default_sort_field
					&& strtoupper($str_tmp_sort_dir) != $this -> str_default_sort_dir) $this -> str_default_sort = ' '.$str_tmp_sort_arg.' '.strtoupper($str_tmp_sort_dir).(($this -> str_default_sort)?', '.$this -> str_default_sort:'');
				}
			}
			else {
				if (strtolower($str_sort_direction) != 'any') $this -> str_default_sort = ' '.$str_sort_argument.' '.strtoupper($str_sort_direction).(($this -> str_default_sort
				&& $this -> str_default_sort != $str_sort_argument.' '.strtoupper($str_sort_direction))?', '.$this -> str_default_sort:'');
				$this -> str_sort_field = $str_sort_argument;
				$this -> str_sort_direction = strtoupper($str_sort_direction);
			}
		}
		else $str_sort_direction = (($this -> str_default_sort_dir)?$this -> str_default_sort_dir:'any');

		return array('sort' => $str_sort_direction, 'image' => constant('CST_ENV_IMG').$this -> tab_config['images'][$str_sort_direction.'sort']);
	}

	/**
	* Ajout d'un critère de recherche
	*
	* @param string $str_field_name - Le nom du champ sur lequel la recherche va s'opérer
	* @param string $str_value - La valeur recherchée
	*
	* @return void
	*/
	public function addSearchCriteria($str_field_name, $str_value){


		if ($str_value){
			$this -> str_query = str_replace($this -> str_default_where, '#whereCriteria#', $this -> str_query);
			$int_field_id = preg_replace('/[a-z]/i', '', $str_field_name);

			$this -> tab_search_fields[$int_field_id] = $str_value;

			if ($this -> tab_fields[$int_field_id]['table']) {
				if (preg_match('/date/i', $this -> tab_fields[$int_field_id]['type'])) { // Gestion des champs de type date

					if (preg_match('/^([=,>,<]{1,2})(.*)/i', $str_value, $tab_match)) {
						$str_operator = $tab_match[1];
						$str_value = $tab_match[2];
					}
					else $str_operator = '=';

					if (! $this -> xtCheckDate($str_value, 'jj/mm/aaaa')) {
						$this -> str_user_error .= 'FIELD: '.$this -> tab_fields[$int_field_id]['field']."\n".'VALUE:'.$str_value."\n".'ERROR:DATE INVALIDE '."\n\n";
						$str_value = '';
						$this -> tab_search_fields[$int_field_id] = 'DATE INVALIDE';
					}
					//[MOD Théo 04/04/2014]
					//if (! $this -> tab_fields[$int_field_id]['alias']) $this -> str_default_where .= ' AND '.$this -> tab_fields[$int_field_id]['table'].'.'.$this -> tab_fields[$int_field_id]['field'].' '.$str_operator.' "'.$this -> obj_sql -> dbal_date_to_db($str_value).'" ';
					//else $this -> str_default_where .= ' AND '.$this -> tab_fields[$int_field_id]['alias'].' '.$str_operator.' "'.$this -> obj_sql -> dbal_date_to_db($str_value).'" ';
					$this -> str_default_where .= ' AND '.$this -> tab_fields[$int_field_id]['table'].'.'.$this -> tab_fields[$int_field_id]['field'].' '.$str_operator.' "'.$this -> obj_sql -> dbal_date_to_db($str_value).'" ';
				}
				elseif (preg_match('/(decimal|float|int)/i', $this -> tab_fields[$int_field_id]['type'])) { // Gestion des champs de type nombres, entiers, etc.
					if (preg_match('/^([=,>,<]{1,2})(.*)/i', $str_value, $tab_match)) {
						$str_operator = $tab_match[1];
						$str_value = $tab_match[2];
					}
					else $str_operator = '=';

					if (preg_match('/[^0-9,\.]/', trim($str_value))) {
						$this -> str_user_error .= 'FIELD: '.$this -> tab_fields[$int_field_id]['field']."\n".'VALUE:'.$str_value."\n".'ERROR:VALEUR INVALIDE'."\n\n";
						$str_value = '';
						$this -> tab_search_fields[$int_field_id] = 'VALEUR INVALIDE';
					}
					//[MOD Théo 04/04/2014]
					//if (! $this -> tab_fields[$int_field_id]['alias'])  $this -> str_default_where .= ' AND '.$this -> tab_fields[$int_field_id]['table'].'.'.$this -> tab_fields[$int_field_id]['field'].' '.$str_operator.' "'.$str_value.'" ';
					//else $this -> str_default_where .= ' AND '.$this -> tab_fields[$int_field_id]['alias'].' '.$str_operator.' "'.$str_value.'" ';
					$this -> str_default_where .= ' AND '.$this -> tab_fields[$int_field_id]['table'].'.'.$this -> tab_fields[$int_field_id]['field'].' '.$str_operator.' "'.$str_value.'" ';
				}
				else {
					//[MOD Théo 04/04/2014]
					//if (! $this -> tab_fields[$int_field_id]['alias']) $this -> str_default_where .= ' AND '.$this -> tab_fields[$int_field_id]['table'].'.'.$this -> tab_fields[$int_field_id]['field'].' LIKE "%'.$str_value.'%" ';
					//else $this -> str_default_where .= ' AND '.$this -> tab_fields[$int_field_id]['alias'].' LIKE "%'.$str_value.'%" ';
					$this -> str_default_where .= ' AND '.$this -> tab_fields[$int_field_id]['table'].'.'.$this -> tab_fields[$int_field_id]['field'].' LIKE "%'.$str_value.'%" ';
				}
			}
			else $this -> str_default_where .= ' AND '.$this -> tab_fields[$int_field_id]['formula'].' LIKE "%'.$str_value.'%" ';

			$this -> str_query = str_replace('#whereCriteria#', $this -> str_default_where, $this -> str_query);
		}
	}

	/**
	* Permet de spécifier les valeurs de filtre sélectionnés en retour
	*
	* @param string JSON $str_json_selected_filters - Les filtres sélectionnés par l'utilisateur au format JSON
	*
	*/
	public function setSelectedFilters($str_json_selected_filters){
		$this -> tab_user_selected_filters = json_decode($str_json_selected_filters);
	}


	/**
	* Ajout d'un filtre SQL strict contraint par le programme
	* Le filtre doit être une expression SQL complète à intégrer dans le WHERE d'une requête SQL
	*
	* @param string $str_filter - Une expression SQL complète à intégrer tel quel après le WHERE d'une requête (opérateur OR ou AND inclus)
	*
	* @return integer - L'index du filtre crée
	*/
	public function setStrictFilter($str_filter){
		if (preg_match('/^~/', $str_filter)){ // Décryption, tout les filtres transportés commencent par un tilde
			$tab_filters = explode('~', $str_filter);

			foreach($tab_filters as $i => $str_filter){
				if ($str_filter){
					$this -> tab_crypted_filters[] = $str_filter;
					$this -> tab_strict_filters[] = $this -> encryptionTool('decrypt', $str_filter, $this -> str_encrypt_key);
				}
			}
		}
		else { // Encryption
			if (! preg_match('/^(OR|AND) (.*)/i', trim($str_filter))) return $this -> addError("<li> Filtre $str_filter invalide.");

			$this -> tab_strict_filters[] = $str_filter;
			$this -> tab_crypted_filters[] = $this -> encryptionTool('encrypt', $str_filter, $this -> str_encrypt_key); // pour le transport des filtres stricts
		}
		return count($this -> tab_strict_filters);
	}

	/**
	* Désactive un filtre le rendant inopérant, ça permet d'agir dynamiquement sur la définition de la table contenu dans le fichier ini.sql
	*
	*
	*
	*/
	public function disableFilter($int_filter_group = 0, $int_filter_id = 0){
		if (! $int_filter_group) return $this -> bool_disable_all_filter = true;
		if (! $int_filter_id) return $this -> tab_filter_groups[$int_filter_group]['disable'] = true;
		$this -> tab_filter_groups[$int_filter_group]['members'][$int_filter_id]['disable'] = true;
	}

	/**
	* Active un filtre d'un groupe de filtre par défaut, ce qui permet de positionner certains filtres utilisateurs par défaut
	*
	*
	*/
	public function enableFilter($int_filter_group, $int_filter_id){
		if (! $this -> bool_is_cookie_setup) $this -> tab_filter_groups[$int_filter_group]['members'][$int_filter_id]['enable'] = true;
	}

	/**
	* Création d'un groupe de filtre
	*
	* @param string $str_group_name - Le nom du groupe tel qu'il apparaitra dans la fenêtre des filtres
	* @param string $str_condition - L'opérateur associé aux différents filtre du groupe (OR ou AND)
	*
	* @return integer - L'index du groupe de filtre crée
	*/
	public function setFilterGroup($str_group_name, $str_condition = 'AND'){
		if ($str_condition != 'AND' && $str_condition != 'OR') return $this -> addError("<li> L'opérateur sur le groupe de filtre $str_group_name est invalide.");
		$this -> tab_filter_groups[count($this -> tab_filter_groups) + 1] = array('name' => $str_group_name, 'operator' => $str_condition, 'members' =>  array());

		return count($this -> tab_filter_groups);
	}

	/**
	* Déclare un filtre associé à un groupe
	*
	* @param integer $int_group - L'index du groupe de filtre auquel le filtre va être associé
	* @param string $str_label - Le libellé du filtre tel qu'il va apparaitre
	* @param string $str_expression - L'expression SQL complète associée au filtre et à intégrer tel quel après le WHERE d'une requête
	*
	* @return integer - L'index du filtre crée
	*/
	public function addFilterToGroup($int_group, $str_label, $str_expression){
		$this -> tab_filter_groups[$int_group]['members'][count($this -> tab_filter_groups[$int_group]['members']) + 1] = array('label' => $str_label, 'sql' => $str_expression);

		return count($this -> tab_filter_groups[$int_group]['members']);
	}

	/**
	* Construction de la requête, gestion de la pagination, application des filtres, etc.
	*
	*
	*
	*/
	private function buildSqlQuery(){

		// Gestion des filtres
		$this -> str_query = str_replace($this -> str_default_where, '#whereCriteria#', $this -> str_query);
		$this -> putInLog(__LINE__." -- ".$this -> str_query." <br>\r\n");
		for ($i = 0; $i < count($this -> tab_strict_filters); $i ++) $this -> str_default_where .= ' '.$this -> tab_strict_filters[$i].' '; // Filtres stricts
		if (count($this -> tab_user_selected_filters)){
			sort($this -> tab_user_selected_filters);
			foreach($this -> tab_user_selected_filters as $int_filter_group => $tab_filters) {
				foreach($tab_filters as $int_filter => $bool) {
					if (! $this -> tab_filter_groups[$int_filter_group]['members'][$int_filter]['disable']) $this -> str_default_where .= ' AND '.$this -> tab_filter_groups[$int_filter_group]['members'][$int_filter]['sql'].' ';
				}
			}
		}
		$this -> putInLog(__LINE__." -- ".$this -> str_query." <br>\r\n");
		// Gestion des groupes de filtre
		for ($i = 0; $i < count($this -> tab_filter_group_id); $i ++){

			if (! $this -> tab_filter_groups[$i + 1]['disable']) {
				// [MOD Théo 24/07/2013] Import réalisé au moment de l'intégration des cookies
				//$this -> tab_filter_values[$i] = explode(',', $this -> importArguments($this -> tab_filter_group_id[$i]));

				// Ajout des filtres prédéfinis
				if (is_array($this -> tab_filter_groups[$i + 1]['members'])){
					foreach($this -> tab_filter_groups[$i + 1]['members'] as $int_filid => $tab_prop){
						if ($tab_prop['enable'] == true) $this -> tab_filter_values[$i][] = $int_filid;
					}
				}

				$pre = $str_where = '';
				if (count($this -> tab_filter_values[$i])) {
					for ($f = 0; $f < count($this -> tab_filter_values[$i]); $f ++){
						$this -> putInLog(__LINE__." -- ".$str_where." -- ".$this -> tab_filter_values[$i][$f]." -- ".print_r($this -> tab_filter_groups[$i + 1]['members'][$this -> tab_filter_values[$i][$f]], true)."<br>\r\n");
						if ($this -> tab_filter_groups[$i + 1]['members'][$this -> tab_filter_values[$i][$f]]['sql']){
							$str_where .= $pre.$this -> tab_filter_groups[$i + 1]['members'][$this -> tab_filter_values[$i][$f]]['sql'].' ';
							$pre = ' '.$this -> tab_filter_groups[$i + 1]['operator'].' ';
						}
					}
				}
			}
			$this -> putInLog(__LINE__." -- ".$str_where." -- ".$this -> tab_filter_values[$i][$f]."<br>\r\n");
			if ($str_where) $this -> str_default_where .= ' AND ('.$str_where.') ';
		}
		$this -> str_query = str_replace('#whereCriteria#', $this -> str_default_where, $this -> str_query);
		$this -> putInLog( __LINE__." -- ".$this -> str_query." -- ".caliChrono()." <br>\r\n");

		// Gestion de la pagination
		$this -> obj_sql -> dbal_query($this -> str_query.' LIMIT 0,5000'); //[ADD Théo 23/01/2019 LIMIT pour aller plus vite]
		$this -> putInLog(__LINE__." -- ".$this -> str_query." -- ".caliChrono()." <br>\r\n");

		// Gestion du tri
		$this -> str_query .= ' ORDER BY '.$this -> str_default_sort;
		$this -> putInLog( __LINE__." -- ".$this -> str_query." <br>\r\n");

		$this -> int_nb_total_records = $this -> obj_sql -> dbal_num_rows();
		$this -> int_nb_total_pages = ceil($this -> int_nb_total_records / $this -> int_nb_records_by_page);
		if (! $this -> int_no_page || $this -> int_no_page < 1) $this -> int_no_page = 1;
		//[MOD Théo 01/02/2019] Pas de limite pour les sorties XLS
		if (! $this -> boolXlsOutput) $this -> str_query .= ' LIMIT '.(($this -> int_no_page - 1) * $this -> int_nb_records_by_page).','.$this -> int_nb_records_by_page;

		$this -> putInLog( __LINE__." -- ".$this -> str_query." <br>\r\n");
	}

	/**
	* Construit les lignes du tableau à partir d'un template extérieur, cette méthode peut être appelée directement pour pouvoir ne reconstruire que les lignes sans rappeler toute la page
	*
	* @return string html
	*/
	public function buildRows($boolHtml = false){
		$this -> buildSqlQuery();
		$r = 0;

		// Construction de l'affichage
		if ($boolHtml && ! $this -> boolXlsOutput) {
			$obj_html = new ModeliXe('xtTableDataContent.html');
			$obj_html -> SetMxTemplatePath(constant('X_TEMPLATE_PATH'));
			$obj_html -> SetMxFileParameter(constant('CST_ENV_MXP'));
			$obj_html -> SetModeliXe(true);
		}
		$this -> putInLog( __LINE__." -- ".caliChrono()."<br>\r\n");
		$this -> obj_sql -> dbal_query($this -> str_query);
		$this -> putInLog( __LINE__." -- ".caliChrono()."<br>\r\n");

		if (! $this -> obj_sql -> dbal_num_rows() && $boolHtml) {
			$this -> putInLog( __LINE__." -- ".caliChrono()."<br>\r\n");
			$strEmpty = '<div class="emptyRecords" width="100%" >Aucun résultat ne correspond à votre recherche</div>';
			$obj_html -> MxBloc('rowData', 'modify', $strEmpty);
		}
		while ($this -> obj_sql -> dbal_fetch($tab_res)){
			$this -> putInLog( __LINE__." -- ".caliChrono()."<br>\r\n");

			$strDefaultValue = ''; //[ADD Théo 01/09/2016]
			for($i = 0; $i < count($this -> tab_fields); $i ++){
				$tab_properties = $this -> tab_fields[$i];

				if (! $tab_properties['alias']) $str_field_used_name = $tab_properties['field']; //[ADD Théo 04/04/2014]
				else $str_field_used_name = $tab_properties['alias'];

				$this -> putInLog(__LINE__." -- ".$str_field_used_name." -- ".$tab_properties['field']."<br>\r\n");
				$this -> putInLog(__LINE__." -- ".$str_field_used_name." -- ".$tab_properties['alias']."<br>\r\n");
				$this -> putInLog(__LINE__." -- ".print_r($tab_properties, true)."<br>\r\n");

				$str_sql_value = $tab_res[$str_field_used_name];
				if ($tab_properties['bindTo']) $str_value = call_user_func($tab_properties['bindTo'], $tab_res[$str_field_used_name], $tab_res);
				else $str_value = $tab_res[$str_field_used_name];

				$str_align = 'left';
				if (preg_match('/date/', $tab_properties['type']) && ! $tab_properties['bindTo']) {
					$str_value = $this -> obj_sql -> dbal_date_from_db($str_value, true);
					$str_align = 'center';
				}
				if (preg_match('/int|float|decimal/', $tab_properties['type'])) $str_align = 'right';
				if (preg_match('/char|text/', $tab_properties['type'])) $str_align = 'left';
				if (! $str_value) $str_value = '&nbsp;';

				$this -> putInLog(__LINE__." -- ".$str_value."<br>\r\n");

				if (isset($this -> tab_col_width[$i])) $int_width = $this -> tab_col_width[$i];
				else {
					$int_width = ($tab_properties['size'] * (($tab_properties['bindTo'])?1:$this -> int_font_width_multiplicator));
					if ($int_width > 250) $int_width = 250;
				}
				if ($tab_properties['align']) $str_align = $tab_properties['align'];

				if ($this -> strDefaultSelection[$tab_properties['field']] == $str_sql_value && $str_sql_value) $strDefaultValue = '1'; //[ADD Théo 01/09/2016]

				if ($boolHtml && ! $this -> boolXlsOutput) {
					$obj_html -> MxText('rowData.colData.label', $str_value);
					$obj_html -> MxAttribut('rowData.colData.style', 'width:'.$int_width.'px;text-align:'.$str_align.(($tab_properties['hide'])?';display:none':''));
					$obj_html -> MxAttribut('rowData.colData.column', 'C'.$i);
					$obj_html -> MxAttribut('rowData.colData.sqlValue', $str_sql_value); // Valeur renvoyée par le formulaire (peut être différente de la valeur affichée)

					$obj_html -> MxBloc('rowData.colData', 'loop');
				}
				else $tabRows[$r][$i] = array('value' => utf8_encode($str_value),
				'defaultValue' => utf8_encode($strDefaultValue), //[ADD Théo 01/09/2016]
				'align' => utf8_encode($str_align),
				'width' => utf8_encode($int_width),
				'hide' => utf8_encode((($tab_properties['hide'])?1:0)),
				'type' => utf8_encode($tab_properties['type']),
				'intTotalWidth' => utf8_encode($this -> int_total_width),
				'sqlValue' => utf8_encode($str_sql_value));
				$this -> putInLog( __LINE__." -- ".caliChrono()."<br>\r\n");
			}

			if ($boolHtml && ! $this -> boolXlsOutput) {
				$obj_html -> MxAttribut('rowData.rowStyle', 'R'.($r % 2));
				$obj_html -> MxAttribut('rowData.defaultValue', $strDefaultValue); //[ADD Théo 01/09/2016]
				$obj_html -> MxAttribut('rowData.id', 'xTableRow'.$r);
				$obj_html -> MxAttribut('rowData.width', 'width:'.($this -> int_total_width).'px');
				$obj_html -> MxBloc('rowData', 'loop');
			}

			$r ++;
			$this -> putInLog( __LINE__." -- ".caliChrono()."<br>\r\n");
		}

		// Affichage des éléments de pagination
		if (isset($this -> obj_html)){
			for ($i = 1; $i <= 20; $i ++) $tabQty[$i * 10] = $i * 10;
			$this -> obj_html -> MxSelect('qtyByScreen', 'intQtyByScreen', $this -> int_nb_records_by_page, $tabQty);
			for ($i = 1; $i <= $this -> int_nb_total_pages; $i ++) $tabPage[$i] = $i;
			$this -> obj_html -> MxSelect('goToPage', 'intGoToPage', $this -> int_no_page, $tabPage);
			$this -> obj_html -> MxText('nbRecords', (($this -> int_nb_total_records >= 5000)?"+ de ":"").$this -> int_nb_total_records);
			$this -> putInLog( __LINE__." -- ".caliChrono()."<br>\r\n");
		}

		//[ADD Théo 24/07/2013] On enregistre sur le client le paramétrage de sa liste
		$this -> putCookieValues();
		$this -> putInLog( __LINE__." -- ".caliChrono()."<br>\r\n");

		if ($this -> boolXlsOutput) return $this -> getXls($tabRows); // Sortie au format XLS
		elseif ($boolHtml) return $obj_html -> MxWrite();
		else return $tabRows;
	}

	/**
	* Construit les en-têtes et le bas du tableau, ajuste les propriétés d'affichage
	*
	* @return void
	*/
	private function buildColumns(){
		$int_css_padding = 3 + 3;
		$int_css_border = 1;
		$int_css_row_height = 14;

		$this -> putInLog( __LINE__." -- ".caliChrono()."<br>\r\n");

		for($i = 0; $i < count($this -> tab_fields); $i ++){
			$tab_properties = $this -> tab_fields[$i];								
			$this -> putInLog( __LINE__." -- ".$tab_properties['tableName']."<br>\r\n");
			if (! $tab_properties['tableName']) $tab_properties['tableName'] = 'formula';
			if (! $tab_properties['field']) $tab_properties['field'] = $tab_properties['alias'];
			$this -> putInLog( __LINE__." -- ".$tab_properties['tableName']."<br>\r\n");

			if (isset($this -> tab_col_width[$i])) $int_width = $this -> tab_col_width[$i];
			else {
				$int_width = ($tab_properties['size'] * (($tab_properties['bindTo'])?1:$this -> int_font_width_multiplicator));
				if ($int_width > 250) $int_width = 250;
			}

			// Gestion du tri par défaut
			if ($this -> str_default_sort_field){
				if (! strpos($this -> str_default_sort_field, ',')){ //[ADD Théo 18/09/2013] Eviter les problèmes avec les tris initiaux paramétrés sur plusieurs champs
					list($str_sort_table, $str_sort_field) = explode('.', $this -> str_default_sort_field);
					if ($tab_properties['table'].'.'.$tab_properties['field'] == $str_sort_table.'.'.$str_sort_field) $tab_properties['sort'] = strtolower($this -> str_default_sort_dir).'sort';
				}
				else { //[ADD Théo 18/09/2013] Gestion des tris initiaux paramétrés sur plusieurs champs
					$tab_sort = explode(',', $this -> str_default_sort_field);
					$tab_dir = explode(',', $this -> str_default_sort_dir);

					foreach($tab_sort as $s => $str_sort){
						list($str_sort_table, $str_sort_field) = explode('.', $str_sort);
						if ($tab_properties['table'].'.'.$tab_properties['field'] == $str_sort_table.'.'.$str_sort_field) {
							$tab_properties['sort'] = strtolower($tab_dir[$s]).'sort';
							break;
						}
					}
				}
			}

			// En-têtes
			$str_label = caliGetProfilValue('database', $tab_properties['tableName'].'.'.$tab_properties['field']);
			$this -> putInLog( __LINE__." -- field = ".$tab_properties['field']."<br>\r\n");
			$this -> putInLog( __LINE__." -- alias = ".$tab_properties['alias']."<br>\r\n");
			$this -> putInLog( __LINE__." -- tableName = ".$tab_properties['tableName']."<br>\r\n");
			$this -> putInLog( __LINE__." -- str_label = ".$str_label."<br>\r\n");

			if (! $str_label) $str_label = '&nbsp;';
			$this -> obj_html -> MxText('colHead.label', $str_label);
			$this -> obj_html -> MxAttribut('colHead.style', 'width:'.$int_width.'px'.(($tab_properties['hide'])?';display:none':''));
			$this -> obj_html -> MxAttribut('colHead.column', 'C'.$i);
			$this -> obj_html -> MxAttribut('colHead.id', 'H'.$tab_properties['tableName'].'.'.$tab_properties['field']);
			if ($tab_properties['alias']) $this -> obj_html -> MxAttribut('colHead.xTfieldSort', $tab_properties['alias']); //[ADD Théo 04/04/2014]
			else $this -> obj_html -> MxAttribut('colHead.xTfieldSort', (($tab_properties['table'])?$tab_properties['table'].'.':'').$tab_properties['field']); //[MOD Théo 04/04/2014]
			$this -> obj_html -> MxAttribut('colHead.xTDirSort', 'any');
			$this -> obj_html -> MxAttribut('colHead.src', constant('CST_ENV_IMG').$this -> tab_config['images'][$tab_properties['sort']]);

			$this -> obj_html -> MxBloc('colHead', 'loop');

			// Bas de liste
			if (isset($this -> tab_col_width[$i])) $int_size = $this -> tab_col_width[$i];
			else {
				$int_size = $tab_properties['size'];
				if ($int_size > 30) $int_size = 30;
			}

			$this -> obj_html -> MxFormField('colFoot.searchRowField', 'text', 'strXSearch'.$i, $this -> tab_search_fields[$i], ' size="'.$int_size.'" ');
			$this -> obj_html -> MxAttribut('colFoot.style', 'width:'.$int_width.'px'.(($tab_properties['hide'])?';display:none':''));
			$this -> obj_html -> MxAttribut('colFoot.column', 'C'.$i);
			$this -> obj_html -> MxAttribut('colFoot.id', 'F'.$tab_properties['tableName'].'.'.$tab_properties['field']);

			$this -> obj_html -> MxBloc('colFoot', 'loop');

			// Largeur globale d'une ligne
			if (! $tab_properties['hide']) $this -> int_total_width += $int_width + $int_css_padding + $int_css_border;
		}

		$pre = '';
		$str_binding = '[';
		foreach($this -> tab_binding as $i => $str_c) {
			$str_binding .= $pre.$str_c;
			$pre = ',';
		}
		$str_binding .= ']';

		$this -> obj_html -> MxAttribut('headWidth', 'width:'.($this -> int_total_width + 18).'px');
		$this -> obj_html -> MxAttribut('dataFrame', 'width:'.($this -> int_total_width + 18).'px;height:'.($this -> int_nb_showed_row * $int_css_row_height).'px');
		$this -> obj_html -> MxAttribut('iniFile', $this -> str_ini_file);
		$this -> obj_html -> MxAttribut('ajaxurl', $this -> str_ajax_url);
		$this -> obj_html -> MxAttribut('actualsortcol', $this -> str_default_sort_field);
		$this -> obj_html -> MxAttribut('actualsortdir', strtolower($this -> str_default_sort_dir));
		$this -> obj_html -> MxAttribut('binding', $str_binding);
		$this -> obj_html -> MxAttribut('jsBindingOnClick', $this -> str_js_function_binding_on_click);

		// Elements d'encryption et filtres cryptés
		$str_crypted_filters = '';
		foreach($this -> tab_crypted_filters as $i => $str_filter) $str_crypted_filters .= '~'.urlencode($str_filter);
		$this -> obj_html -> MxAttribut('filters', $str_crypted_filters);
	}

	/**
	* Construit les fenêtres de sélection des groupes de filtres et filtres associés
	*
	*
	*
	*
	*/
	private function buildFilterBox(){
		foreach($this -> tab_filter_groups as $i => $tab_filter){
			if (! $tab_filter['disable']){
				$this -> obj_html -> MxText('filterGroup.title', utf8_decode($tab_filter['name']));
				$this -> obj_html -> MxAttribut('filterGroup.id', 'filBox'.$this -> tab_filter_group_id[$i - 1]);

				foreach($tab_filter['members'] as $m => $tab_properties){
					if (! $tab_properties['disable']){

						//$this -> obj_html -> MxCheckerField('filterGroup.filterMember.checker', (($tab_filter['operator'] == 'OR')?'radio':'checkbox'), $this -> tab_filter_group_id[$i - 1], $m, ((@in_array($m, $this -> tab_filter_values[$i - 1]) || $tab_properties['enable'])?true:false));
						$this -> obj_html -> MxCheckerField('filterGroup.filterMember.checker', 'checkbox', $this -> tab_filter_group_id[$i - 1], $m, ((@in_array($m, $this -> tab_filter_values[$i - 1]) || $tab_properties['enable'])?true:false));
						$this -> obj_html -> MxAttribut('filterGroup.filterMember.for', $this -> tab_filter_group_id[$i - 1]);
						$this -> obj_html -> MxText('filterGroup.filterMember.label', utf8_decode($tab_properties['label']));
						$this -> obj_html -> MxBloc('filterGroup.filterMember', 'loop');
					}
				}

				// Elément de reset
				$this -> obj_html -> MxCheckerField('filterGroup.filterMember.checker', 'checkbox', $this -> tab_filter_group_id[$i - 1], 'xTableFilterReset');
				$this -> obj_html -> MxAttribut('filterGroup.filterMember.for', $this -> tab_filter_group_id[$i - 1]);
				$this -> obj_html -> MxText('filterGroup.filterMember.label', '<mx:pref id="xTableResetFilterValue" />');
				$this -> obj_html -> MxBloc('filterGroup.filterMember', 'loop');

				$this -> obj_html -> MxBloc('filterGroup', 'loop');
			}
		}
	}

	/**
	* Renvoie la requête SQL utilisée pour construire la liste
	*
	* @return string
	*/
	public function getQuery(){
		return $this -> str_query;
	}


	/**
	* Analyse la chaîne du SELECT pour isoler les champs et extraire les informations sur les tables associées, les formules, etc.
	*
	* @param string $str_select - La chaine de sélection au format SQL
	*
	* @return array (string field, string alias, string formula, string table)
	*/
	private function parseSelectFields($str_select){

		$str_context = 'field';
		$int_count_field = 0;
		$str_alias = '';
		for ($c = 0; $c < strlen($str_select); $c ++){

			$str_char = $str_select{$c};
			//print(__LINE__." -- c = $c -- str_char = $str_char -- $str_context<br>\r\n");
			switch($str_context){
				case 'field':
				if ($str_char == ',') {
					$str_context = 'field';
					$bool_complete_alias_keyword = false;
					$str_char = '';
					if (! $str_alias && $str_key_word) $str_alias = $str_key_word;
					//if (! $str_field_name && $str_alias) $str_field_name = $str_alias; //[DEL Théo 24/03/2014]
					//if ($str_alias) $str_field_name = $str_alias; //[ADD Théo 24/03/2014]
					$tab_fields[$int_count_field] = array('field' => trim($str_field_name), 'alias' => trim($str_alias), 'table' => trim($str_table_alias), 'formula' => $str_formula);
					$this -> tabFieldsName[trim($str_field_name)] = $int_count_field;
					$int_count_field ++;
					$str_field_name = $str_alias = $str_table_alias = $str_key_word = $str_formula = '';
				}
				elseif ($str_char == '(') {
					$str_context = 'formula';
					$int_count_parenthesis = 1;
					$str_formula .= $str_field_name.$str_char;
					$str_field_name = '';
				}
				else {
					if ($str_char == '.') {
						$str_table_alias = $str_field_name;
						$str_char = $str_field_name = '';
						//print(__LINE__." -- c = $c -- str_char = $str_char --> $str_table_alias<br>\r\n");
					}
					elseif ($str_char == ' ' && $c == strlen($str_select) - 1) {
						$str_context = 'field';
						$bool_complete_alias_keyword = false;
						$str_char = '';
						if (! $str_alias && $str_key_word) $str_alias = $str_key_word;
						//if (! $str_field_name && $str_alias) $str_field_name = $str_alias; //[DEL Théo 24/03/2014]
						//if ($str_alias) $str_field_name = $str_alias; //[ADD Théo 24/03/2014]
						$tab_fields[$int_count_field] = array('field' => trim($str_field_name), 'alias' => trim($str_alias), 'table' => trim($str_table_alias), 'formula' => $str_formula);
						$this -> tabFieldsName[trim($str_field_name)] = $int_count_field;
						$int_count_field ++;
						$str_field_name = $str_alias = $str_table_alias = $str_key_word = $str_formula = '';
					}
					elseif ($str_char == ' ' && ($str_table_alias || $str_field_name)) $str_context = 'alias';
					else $str_field_name .= $str_char;
				}
				break;
				case 'formula':
				if ($str_char == '(') $int_count_parenthesis ++;
				if ($str_char == ')') $int_count_parenthesis --;
				if ($int_count_parenthesis == 0 && $str_char == ' ') $str_context = 'alias';
				$str_formula .= $str_char;
				break;
				case 'alias':
				if ($str_char == ',') {
					$str_context = 'field';
					$bool_complete_alias_keyword = false;
					$str_char = '';
					if (! $str_alias && $str_key_word) $str_alias = $str_key_word;
					//if (! $str_field_name && $str_alias) $str_field_name = $str_alias; //[DEL Théo 24/03/2014]
					//if ($str_alias) $str_field_name = $str_alias; //[ADD Théo 24/03/2014]
					$tab_fields[$int_count_field] = array('field' => trim($str_field_name), 'alias' => trim($str_alias), 'table' => trim($str_table_alias), 'formula' => $str_formula);
					$this -> tabFieldsName[trim($str_field_name)] = $int_count_field;
					$int_count_field ++;
					$str_field_name = $str_alias = $str_table_alias = $str_key_word = $str_formula = '';
				}
				elseif ($str_char == '(') {
					$str_context = 'formula';
					$str_alias = '';
					$int_count_parenthesis = 1;
					$str_formula .= $str_field_name.$str_key_word.$str_char;
					$str_field_name = $str_key_word = '';
				}
				else {
					if (! $bool_complete_alias_keyword) {
						$str_alias = '';
						$str_key_word .= $str_char;
					}
					if (trim(strtoupper($str_key_word)) == 'AS'){
						if ($str_char == ' ' && $str_alias){
							$str_context = 'field';
							$bool_complete_alias_keyword = false;
							$str_char = '';
							if (! $str_alias && $str_key_word) $str_alias = $str_key_word;
							//if (! $str_field_name && $str_alias) $str_field_name = $str_alias; //[DEL Théo 24/03/2014]
							//if ($str_alias) $str_field_name = $str_alias; //[ADD Théo 24/03/2014]
							$tab_fields[$int_count_field] = array('field' => trim($str_field_name), 'alias' => trim($str_alias), 'table' => trim($str_table_alias), 'formula' => $str_formula);
							$this -> tabFieldsName[trim($str_field_name)] = $int_count_field;
							$int_count_field ++;
							$str_field_name = $str_alias = $str_table_alias = $str_key_word = $str_formula = '';
						}
						else {
							if ($bool_complete_alias_keyword) $str_alias .= trim($str_char);
							$bool_complete_alias_keyword = true;
						}
					}
				}
				break;
				default:
				if ($str_char == ',') {
					$str_context = 'field';
					$bool_complete_alias_keyword = false;
					$str_char = '';
					if (! $str_alias && $str_key_word) $str_alias = $str_key_word;
					//if (! $str_field_name && $str_alias) $str_field_name = $str_alias; //[DEL Théo 24/03/2014]
					//if ($str_alias) $str_field_name = $str_alias; //[ADD Théo 24/03/2014]
					$tab_fields[$int_count_field] = array('field' => trim($str_field_name), 'alias' => trim($str_alias), 'table' => trim($str_table_alias), 'formula' => $str_formula);
					$this -> tabFieldsName[trim($str_field_name)] = $int_count_field;
					$int_count_field ++;
					$str_field_name = $str_alias = $str_table_alias = $str_key_word = $str_formula = '';
				}
				elseif ($str_char == ' ' && $c == strlen($str_select) - 1) {

					$str_context = 'field';
					$bool_complete_alias_keyword = false;
					$str_char = '';
					if (! $str_alias && $str_key_word) $str_alias = $str_key_word;
					//if (! $str_field_name && $str_alias) $str_field_name = $str_alias; //[DEL Théo 24/03/2014]
					//if ($str_alias) $str_field_name = $str_alias; //[ADD Théo 24/03/2014]
					$tab_fields[$int_count_field] = array('field' => trim($str_field_name), 'alias' => trim($str_alias), 'table' => trim($str_table_alias), 'formula' => $str_formula);
					$this -> tabFieldsName[trim($str_field_name)] = $int_count_field;
					$int_count_field ++;
					$str_field_name = $str_alias = $str_table_alias = $str_key_word = $str_formula = '';
				}
			}
		}
		//print(__LINE__." -- ".print_r($tab_fields)." <br>\r\n");

		return $tab_fields;
	}

	/***
	*
	*
	*
	*/
	public function getUserError(){
		return $this -> str_user_error;
	}

	/**
	* Génère la sortie au format XLS de la liste
	*
	*
	*/
	private function getXls($tabRows){

		include_once('/var/extranet/www/tools/PHPExcel/Classes/PHPExcel.php');
		include_once('/var/extranet/www/tools/PHPExcel/Classes/PHPExcel/CachedObjectStorage/APC.php');
		include_once('/var/extranet/www/tools/PHPExcel/Classes/PHPExcel/Writer/Excel2007.php');

		$strFileName = basename($this -> str_ini_file).'.xlsx';
		$strFile = '/var/extranet/www/extranet/tmp/'.caliRandomString(7).'.'.$strFileName;

		// Localisation
		PHPExcel_Settings :: setLocale('fr');

		// Gestion du cache
		/*$objCacheMethod = PHPExcel_CachedObjectStorageFactory :: cache_to_phpTemp;
		$objCacheSettings = array('memoryCacheSize' => '128MB');
		PHPExcel_Settings :: setCacheStorageMethod($objCacheMethod, $objCacheSettings); */

		// Instanciation
		$objExcel = new PHPExcel();
		$objWorkSheet = new PHPExcel_WorkSheet($objExcel, utf8_encode(substr("Liste ".ucfirst(basename($this -> str_ini_file)), 0, 31)));
		$objSheet = $objExcel -> addSheet($objWorkSheet, 0);
		$objExcel -> removeSheetByIndex(1);

		// Déclaration des styles
		$tabStyleTitle = array (
		'borders' => array(
		'bottom' => array('style' => PHPExcel_Style_Border::BORDER_THIN),
		),
		'alignment' => array(
		'vertical' => PHPExcel_Style_Alignment::VERTICAL_TOP,
		'horizontal' => PHPExcel_Style_Alignment::HORIZONTAL_CENTER
		),
		'fill' => array(
		'type' => PHPExcel_Style_Fill::FILL_SOLID,
		'startcolor' => array(
		'argb' => '366092'
		)
		),
		'font' => array(
		'color' =>  array(
		'argb' => 'ffffff'
		),
		'bold' => true,
		'size' => 8,
		)
		);
		$tabStyleRows = array (
		'borders' => array(
		'bottom' => array('style' => PHPExcel_Style_Border::BORDER_THIN)
		),
		'alignment' => array(
		'vertical' => PHPExcel_Style_Alignment::VERTICAL_TOP,
		),
		'font' => array(
		'bold' => true,
		'size' => 8,
		)
		);


		// Construction des clefs de colonne
		for($c = 0, $i = 65, $j = 0; $c < count($this -> tab_fields) + 1; $c ++){

			$tabCol[$c] = chr($i).(($j >= 65)? chr($j):'');
			if ($j >= 65) $j ++;
			else $i ++;
			if ($j > 89) break;
			if ($i > 89) {
				$i = 65;
				$j = 65;
			}
		}

		// En-têtes de colonne et formatage général
		for ($c = 0, $intCol = 0; $c < count($this -> tab_fields); $c ++){

			$tab_properties = $this -> tab_fields[$c];
			if (! $tab_properties['tableName']) $tab_properties['tableName'] = 'formula';
			$strLabel = utf8_encode(caliGetProfilValue('database', $tab_properties['tableName'].'.'.$tab_properties['field']));
			if (! $strLabel) $strLabel = ' ';

			if (preg_match('/<img src="([a-z0-1\.\/]*)"/i', $tabRows[0][$c]['value'])) $int_style = PHPExcel_Style_NumberFormat::FORMAT_GENERAL;
			else {
				switch(strtolower($tabRows[0][$c]['type'])){
					case 'decimal':
					case 'float':
					$int_style = PHPExcel_Style_NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1;
					break;
					case 'boolean':
					case 'tinyint':
					case 'mediumint':
					case 'int':
					case 'bigint':
					$int_style = PHPExcel_Style_NumberFormat::FORMAT_NUMBER;
					break;
					case 'date':
					$int_style = PHPExcel_Style_NumberFormat::FORMAT_DATE_DMYMINUS;
					break;
					case 'datetime':
					$int_style = PHPExcel_Style_NumberFormat::FORMAT_DATE_DATETIME;
					break;
					default:
					$int_style = PHPExcel_Style_NumberFormat::FORMAT_GENERAL;
				}
			}

			if ($tabRows[0][$c]['hide'] == '0') {
				$objSheet -> setCellValueByColumnAndRow($intCol, 1, $strLabel);
				$objExcel -> getActiveSheet() -> getColumnDimension($intCol) -> setAutoSize(true);
				$objExcel -> getActiveSheet() -> getStyle('A2:'.$tabCol[count($this -> tab_fields)].(count($tabRows) + 1)) -> getNumberFormat() -> setFormatCode($int_style);
				$intCol ++;
			}
		}

		// Lignes
		for($r = 0, $intRow = 2; $r < count($tabRows); $r++, $intRow ++){
			for ($c = 0, $intCol = 0; $c < count($tabRows[$r]); $c ++){
				$strValue = $tabRows[$r][$c]['value'];
				$strType = $tabRows[$r][$c]['type'];
				$boolHide = $tabRows[$r][$c]['hide'];
				$strValue = str_replace('&nbsp;', ' ', $strValue);

				if ($boolHide == '0') {

					if (preg_match('/<img src="([a-z0-1\.\/]*)"/i', $strValue, $tabMatch)){
						$strImagePath = $tabMatch[1];
						if (! is_file($strImagePath)) $strImagePath = 'images/noPhoto.png';
						$strPhotoPath = main :: convertSize($strImagePath, 15, 15, '><');
						list($intImgWidth, $intImgHeight) = getimagesize($strPhotoPath);

						$objPhoto = new PHPExcel_Worksheet_Drawing();
						$objPhoto -> setName('Photo');
						$objPhoto -> setPath($strPhotoPath);
						$objPhoto -> setHeight($intImgHeight);
						$objPhoto -> setCoordinates($tabCol[$intCol].$intRow);
						$objPhoto -> setWorksheet($objExcel->getActiveSheet());

						$objExcel -> getActiveSheet() -> getRowDimension($intRow) -> setRowHeight($intImgHeight);
					}
					else $objSheet -> setCellValueByColumnAndRow($intCol, $intRow, $strValue);

					$intCol ++;
				}
			}
		}

		// Application des styles
		$objExcel -> getActiveSheet() -> getStyle('A1:'.$tabCol[count($this -> tab_fields)].'1') -> applyFromArray($tabStyleTitle);
		$objExcel -> getActiveSheet() -> getStyle('A1:'.$tabCol[count($this -> tab_fields)].'1') -> getAlignment() -> setTextRotation(90);
		$objExcel -> getActiveSheet() -> getStyle('A2:'.$tabCol[count($this -> tab_fields)].(count($tabRows) + 1)) -> applyFromArray($tabStyleRows);

		$objWriter = new PHPExcel_Writer_Excel2007($objExcel);
		$objWriter -> save($strFile);

		header("Content-Disposition: attachment; filename=".$strFileName);
		header("Content-type: application/vnd.ms-excel");

		$id = fopen($strFile, 'r');
		echo fread($id, filesize($strFile));
		fclose($id);

		exit();
	}

}



?>