/*
* xtTable
* [NEW Théo 04/07/2013]
*/

var xtTable = {};

xtTable = (function(){

	// Méthodes et variables privées *******************************************************************************************


	// Méthodes et variables publiques *******************************************************************************************
	return {

		/**
		* Déclaration des variables internes
		*
		*/
		objRowSelected : null,
		strFunctionOnResize : null,
		strFunctionOnSelectRow : null,

		/**
		* Association d'une fonction lors d'un redimensionnement de la table
		*
		*/
		xTableOnResize : function(strFunction){
			this.strFunctionOnResize = strFunction;
		},

		/**
		* Association d'une fonction lors d'une sélection dans la table
		*
		*/
		xTableOnSelectRow : function(strFunction){
			this.strFunctionOnSelectRow = strFunction;
			},

		/**
		* Rafraichissement de la liste lancé de l'extérieur
		*
		*/
		xTableSubmit : function(){

			strUrl = this.xTableBuildUrl();
			new Ajax.Request(strUrl,
				{
					method: 'get',
					asynchronous: true,
					onFailure: function(transport, json){
						alert("Erreur réseau : " + transport.status + ' >> ' + transport.statusText + ".\nURL appelée : " + strUrl);
						},
					onSuccess: function(transport, json){
						this.xTableBuildRows(json)
						}.bind(this)
				});
			},

		/**
		* Formatage d'URL pour la requête vers le script Ajax de mise jour des lignes
		*
		*
		*/
		xTableBuildUrl : function(boolLoad){
			strHiddenFields = '';
			strUrl = '/' + $('xtTable').readAttribute('ajaxurl') + '?';
			strUrl += '&strIniFile=' + $('xtTable').readAttribute('iniFile');
			strUrl += '&strStrictFilters=' + $('xtTable').readAttribute('filters');
			strUrl += '&strSortDir=' + $('xtTable').readAttribute('actualsortdir');
			strUrl += '&strSortBy=' + $('xtTable').readAttribute('actualsortcol');
			strUrl += '&intQtyByScreen=' + $('intQtyByScreen').value;
			strUrl += '&intGoToPage=' + $('intGoToPage').value;
			strUrl += '&strBinding=' + encodeURIComponent($('xtTable').readAttribute('binding'));
			strUrl += '&intListHeight=' + $('dataFrame').getHeight();
			$$('#mainFrame .headLabel').each(function(objChi2) {if(objChi2.readAttribute('column'))strUrl += '&' + objChi2.readAttribute('column') + '=' + parseInt(objChi2.style.width)});
			$$('#mainFrame .headLabel').each(function(objChi2) {if(objChi2.style.display == 'none') strHiddenFields += objChi2.id.substr(objChi2.id.indexOf('.') + 1) + ','});
			if (strHiddenFields) strUrl += '&strHiddenField=' + strHiddenFields;
			$$("#mainFrame input[name*='strXSearch']").each(function(objChi2) {strUrl += '&' + objChi2.id + '=' + objChi2.value});
			$$("#filterFrame div[id*='filBox']").each(function(objChi2) {
				strName = objChi2.id.substring(6);

				if (strName) {
					if ($(objChi2.id.substring(6)).readAttribute('type') == 'radio') strUrl += '&' + strName  + '=' + $$('input:checked[type="radio"][name="' + strName + '"]').pluck('value');
					else {
						strUrl += '&' + strName  + '=';
						$$('input:checked[type="checkbox"][name="' + strName + '"]').each(function(obj_box){ strUrl += obj_box.value + ','});
						}
					}
				});

			strHtml = '<div style="text-align:center;width:' + $('dataFrame').getWidth() + 'px;height:' + $('dataFrame').getHeight() + 'px;';
			strHtml += 'line-height:' + $('dataFrame').getHeight() + 'px;vertical-align:middle">';
			strHtml += '<img src="images/loading2013.gif" align="center" valign="middle" /></div>';
			if (! boolLoad) $('dataFrame').innerHTML = strHtml;
//window.open(strUrl);
			return strUrl;
			},

		/**
		* Impression des données affichées
		*
		*
		*/
		xTablePrintData : function(){
			
			strUrl = this.xTableBuildUrl(true);
			strUrl += '&boolXls=1';
			window.open(strUrl);
			},


		/**
		* Affichage ou masquage des groupes de filtres
		*
		*/
		xTableFilterShow : function (){
			if ($('filterFrame').style.visibility != 'visible') $('filterFrame').style.visibility = 'visible';
			else {
				$('filterFrame').style.visibility = 'hidden';
				strUrl = this.xTableBuildUrl();
				new Ajax.Request(strUrl,
					{
						method: 'get',
						asynchronous: true,
						onFailure: function(transport, json){
							alert("Erreur réseau : " + transport.status + ' >> ' + transport.statusText + ".\nURL appelée : " + strUrl);
							},
						onSuccess: function(transport, json){
							this.xTableBuildRows(json);
							}.bind(this)
					});
				}
			},

		/**
		* Rend les lignes du tableau clickable
		*
		*
		*/
		xTableBuildSelectHandler : function(){
			
			//[ADD Théo 01/09/2016] Valeur sélectionnée par défaut
			
			console.log('buildSelectHandler');

			strCssSelector = "#mainFrame #dataFrame .rowData[default='1']";
			$$(strCssSelector).each(function(objChi) {
				this.objRowSelected = objChi;
				strOldColor = objChi.style.backgroundColor;
				objChi.style.backgroundColor = 'white';
				this.objRowSelected.oldBackgroundColor = strOldColor;
				
				if (this.strFunctionOnSelectRow) eval(this.strFunctionOnSelectRow + '()');
				if ($('xtTable').readAttribute('jsBindingOnClick')) eval($('xtTable').readAttribute('jsBindingOnClick') + '()');
				}, this);
				
			strCssSelector = '#mainFrame #dataFrame .rowData';
			$$(strCssSelector).each(function(objChi) {
				objChi.observe('click', function(xtMouseEvent) {
					if (this.objRowSelected) $(this.objRowSelected.id).style.backgroundColor = this.objRowSelected.oldBackgroundColor;

					strOldColor = objChi.getStyle('background-color');
					this.objRowSelected = objChi;
					objChi.style.backgroundColor = 'white';
					this.objRowSelected.oldBackgroundColor = strOldColor;

					if (this.strFunctionOnSelectRow) eval(this.strFunctionOnSelectRow + '()');
					if ($('xtTable').readAttribute('jsBindingOnClick')) eval($('xtTable').readAttribute('jsBindingOnClick') + '()');

					}.bind(this));
				}, this);
			},

		/**
		*
		*
		*
		*/
		xTableBuildRows : function(json, intPage){
  	
			if (json.strUserError) $('dataFrame').innerHTML = json.strUserError;
			else {				
	            if (json.intRecordQty == 0) {
	            	strEmpty = '<div class="emptyRecords" width="100%" >Aucun résultat ne correspond à votre recherche</div>';
					$('dataFrame').innerHTML = strEmpty;
	            }
	            else {
	            	 if (json.strHtml) $('dataFrame').innerHTML = json.strHtml;
		            else if (json.tabHtml.length > 0){
		            	var strHtml = ''; 	
		            	var strStyle = '';
		            	for (r = 0; r < json.tabHtml.length; r ++){
		            		
		            		strStyle = 'rowStyle="R' + (r % 2) + '" ';
		            		strStyle += 'id="xTableRow' + r + '" ';
		            		strStyle += 'style="width:' + json.tabHtml[r][0].intTotalWidth + 'px"';
		            		
		            		strHtml += '<div class="rowData" ' + strStyle + '>';
		            		
		            		for (c = 0; c < json.tabHtml[r].length; c ++){
		            			
			            		strStyle = 'style="width:' + json.tabHtml[r][c].width + 'px;';
			            		strStyle += 'text-align:' + json.tabHtml[r][c].align;
			            		strStyle += ((json.tabHtml[r][c].hide == 1)?';display:none':'') + '" ';
			            		strStyle += 'column="C' + c + '" ';
			            		strStyle += 'sqlValue="' + json.tabHtml[r][c].sqlValue + '" ';
			            		
		            			strHtml += '<div class="rowContent" ' + strStyle + '>';
		            			strHtml += json.tabHtml[r][c].value;
		            			strHtml += '</div>';
		            			}
		            		strHtml += '</div>';
		            		}
		            	$('dataFrame').innerHTML = strHtml;
		            	}
	            	}
	           
				$$('#mainFrame .headLabel img').each(function(objChi) {
					objChi.src = json.strDefaultSortImagePath;
					objChi.setAttribute('xTDirSort', json.strDefaultSort);
					});
				
				if (json.strSortByField){ //[ADD Théo 31/08/2020] Suggestion Mathis
					$$('#mainFrame .headLabel img[xTfieldSort="' + json.strSortByField + '"]')[0].src = json.strSortImagePath;
					$$('#mainFrame .headLabel img[xTfieldSort="' + json.strSortByField + '"]')[0].setAttribute('xTDirSort', json.strNewSort);
					}

				if (! intPage){
					$('intGoToPage').innerHTML = '';
					for (i = 1; i <= json.intPageQty; i ++) $('intGoToPage')[i] = new Option(i, i);
					$('intGoToPage').value = 1;
					}
				$('xTnbRecords').innerHTML = json.intRecordQty;
			}

			// Gestion de la sélection
			this.xTableBuildSelectHandler();
			},

		/**
		* Gestion du clic de tri
	 	*
		*/
		xTableSortEvent : function(objChi){

			strSortDirection = objChi.readAttribute('xTDirSort');
			if (strSortDirection == 'any') strSortDirection = 'asc';
			else if (strSortDirection == 'asc') strSortDirection = 'desc';
			else if (strSortDirection == 'desc') strSortDirection = 'any';

			$('xtTable').setAttribute('actualsortcol', objChi.readAttribute('xTfieldSort'));
			$('xtTable').setAttribute('actualsortdir', strSortDirection);

			strUrl = this.xTableBuildUrl();

			new Ajax.Request(strUrl,
				{
					method: 'get',
					asynchronous: true,
					onFailure: function(transport, json){
						alert("Erreur réseau : " + transport.status + ' >> ' + transport.statusText + ".\nURL appelée : " + strUrl);
						},
					onSuccess: function(transport, json){
						this.xTableBuildRows(json);
						}.bind(this)
				});
			},

		/**
		* Renvoie la valeur du champ spécifié sur la ligne cochée
		*
		*/
		xTableGetValue : function(strFieldName){
			if (! this.objRowSelected) return false;
			var tabSelectFields = new Array();
			$$('#mainFrame .headLabel').each(function(objChi) {
				tabSelectFields[objChi.id.substring(1)] = objChi.readAttribute('column');
				});
			if (! tabSelectFields[strFieldName]) return false;
			
			return $$('#' + this.objRowSelected.id + ' div[column="' + tabSelectFields[strFieldName] + '"]')[0].readAttribute('sqlValue');
			},

		/**
		* Initiateur de la classe à appeler sur un événement dom:loaded
		*
		*/
		xtTableCreate : function(){

			// Positionnement relatif des deux div filter et main
			zIndex = $('mainFrame').style.zIndex;
			if (!zIndex) zIndex ++;
			$('mainFrame').style.zIndex = zIndex;
			$('filterFrame').style.zIndex = ++ zIndex;
			$('mainFrame').makePositioned ();
			$('filterFrame').absolutize();
			$('filterFrame').clonePosition($('mainFrame'), {offsetTop:$('head').getHeight()});
			intFrameHeight = $('dataFrame').getHeight();
			intFrameWidth = $('dataFrame').getWidth();
			$('filterFrame').style.height = intFrameHeight + 'px';
			$('filterFrame').style.width = intFrameWidth + 'px';
			int_max_width = Math.floor((intFrameWidth - 60) / 3);
			$$('.groupFilter').each(function(objChi) {
				if (objChi.getWidth() > int_max_width) objChi.style.width = int_max_width + 'px';
				if (objChi.getHeight() > intFrameHeight) {
					objChi.style.height = intFrameHeight + 'px';
					$$('#' + objChi.id + ' .filterMembers')[0].style.height = (intFrameHeight - $$('#' + objChi.id + ' .filterTitle')[0].getHeight()) + 'px';
					}
				});
			$('mainFrame').undoPositioned();

			// Gestion des filtres
			$$("#filterFrame div[id*='filBox']").each(function(objChi2) {
				strName = objChi2.id.substring(6);
				$$('input[name="' + strName + '"]').each(function(objBox){
					if (objBox.value == 'xTableFilterReset') {
						objBox.observe('click', function(){
							$$('input[name="' + objBox.id + '"]').each(function(objT){objT.checked = false});
							});
						}
					});
				});

			// Gestion de la pagination
			$('intQtyByScreen').observe('change', function(xtMouseEvent) {
				$('intGoToPage').value = 1;
				strUrl = this.xTableBuildUrl();
				new Ajax.Request(strUrl,
					{
						method: 'get',
						asynchronous: true,
						onFailure: function(transport, json){
							alert("Erreur réseau : " + transport.status + ' >> ' + transport.statusText + ".\nURL appelée : " + strUrl);
							},
						onSuccess: function(transport, json){
							this.xTableBuildRows(json);
							}.bind(this)
					});
				}.bind(this));

			$('intGoToPage').observe('change', function(xtMouseEvent) {
				strUrl = this.xTableBuildUrl();
				new Ajax.Request(strUrl,
					{
						method: 'get',
						asynchronous: true,
						onFailure: function(transport, json){
							alert("Erreur réseau : " + transport.status + ' >> ' + transport.statusText + ".\nURL appelée : " + strUrl);
							},
						onSuccess: function(transport, json){
							this.xTableBuildRows(json, $('intGoToPage').value);
							}.bind(this)
					});
				}.bind(this));

			// Affichage des filtres
			$('xTableFilterCall').observe('click', this.xTableFilterShow.bind(this));

			// Gestion de l'impression
			$('xTablePrinterCall').observe('click', this.xTablePrintData.bind(this));

			// Gestion de la sélection
			this.xTableBuildSelectHandler();

			// Gestion du tri
			$$('#mainFrame .headLabel img').each(function(objChi) {
				objChi.observe('click', this.xTableSortEvent.bind(this, objChi))
				}, this);

			// Redimensionnement des colonnes et de la hauteur
			var objTarget = null;
			var intOriginalX = 0;
			var intOriginalY = 0;
			var intDataHeight = 0;
			var strOldDataFrameBorder = '';
			strCssSelector = '#mainFrame .headLabel';
			$$(strCssSelector).each(function(objChi) { // Colonnes
				objChi.observe('mousedown', function(xtMouseEvent) {
					if(objTarget == null) {
						objTarget = objChi;

						tabOffset = objChi.cumulativeOffset();
						intOriginalX = tabOffset[0];
						intOriginalY = 0;
						intCol = objChi.readAttribute('column');
						strCssSelector = '#mainFrame div[column="' + intCol +'"]';
						$$(strCssSelector).each(function(objChi2) {
							objChi2.style.borderLeft = '1px dashed red';
							objChi2.style.borderRight = '1px dashed red';
							});
						}
					});
				});
			$('xTableResizeCall').observe('mousedown', function(xtMouseEvent) {// Hauteur
				if(objTarget == null) {
					objTarget = $('xTableResizeCall');

					intDataHeight = $('dataFrame').getHeight();
					tabOffset = objTarget.cumulativeOffset();
					intOriginalY = tabOffset[1];
					intOriginalX = 0;

					strOldDataFrameBorder = $('dataFrame').style.border;
					$('dataFrame').style.border = '1px dashed red';
					}
				});
			$$('body')[0].observe('mousemove', function(xtMouseEvent) { // Colonnes
				if (intOriginalX && objTarget != null){
					intWidth = xtMouseEvent.pointerX() - intOriginalX;
					if (intWidth < 10) intWidth = 10;

					$$('#mainFrame div[column="' + objTarget.readAttribute('column') +'"]').each(function(objChi2) {objChi2.style.width = intWidth + 'px'});

					intTotalWidth = 0;
					$$('#mainFrame .headLabel').each(function(objChi2) {if(objChi2.style.display != "none") intTotalWidth += objChi2.getWidth()});

					$('head').style.width = (intTotalWidth + 18) + 'px';
					$('dataFrame').style.width = (intTotalWidth + 18) + 'px';
					$('foot').style.width = (intTotalWidth + 18) + 'px';
					$('subfoot').style.width = (intTotalWidth + 18) + 'px';
					$('bottomTable').style.width = (intTotalWidth + 18) + 'px';
					$('coverTable').style.width = (intTotalWidth + 18) + 'px';

					if (this.strFunctionOnResize) eval(this.strFunctionOnResize + '(xtMouseEvent, intTotalWidth)');
					}
				if (intOriginalY && objTarget != null){ // Hauteur
					intHeight = xtMouseEvent.pointerY() - intOriginalY;

					intNbAddingRows = Math.floor(intHeight / 14);
					$('dataFrame').style.height = intDataHeight + (intNbAddingRows * 14) + 'px';
					}
				}.bind(this));

			$$('body')[0].observe('mouseup', function(xtMouseEvent) {
				if (objTarget != null){
					if (intOriginalX) {
						intCol = objTarget.readAttribute('column');
						strCssSelector = '#mainFrame div[column="' + intCol +'"]';
						$$(strCssSelector).each(function(objChi2) {
							objChi2.style.borderLeft = 'solid 1px #5e532e';
							objChi2.style.borderRight = '';
							});
						}
					if (intOriginalY){
						$('dataFrame').style.border = strOldDataFrameBorder;
						}

					objTarget = null;
					intOriginalX = 0;
					intOriginalY = 0;
					}
				});

			// Gestion des recherches
			strCssSelector = "#mainFrame input[name*='strXSearch']";
			$$(strCssSelector).each(function(objChi) {
				objChi.observe('change', function(xtMouseEvent) {
					$('intGoToPage').value = 1;

					strUrl = this.xTableBuildUrl();
					new Ajax.Request(strUrl,
						{
							method: 'get',
							asynchronous: true,
							onFailure: function(transport, json){
								alert("Erreur réseau : " + transport.status + ' >> ' + transport.statusText + ".\nURL appelée : " + strUrl);
								},
							onSuccess: function(transport, json){
								this.xTableBuildRows(json);
								}.bind(this)
						});
					}.bind(this));
				}, this);
		    }
		}
	})();
