/****************************************************
 * Javascript functioons for the pp_forum extension
 * @see http://typo3.org
 *
 * @author: popy
 *
 */

var tx_ppforum = {
	/**
	 * "Open" a tool : it means the <div> tag containing a tool (identified by a class) will be displayed and the others hidden
	 *
	 * @param node caller = the button node
	 * @param string toolClass = the class wich identify the div
	 * @access public
	 * @return bool (always false) 
	 */
	showhideTool: function(caller,toolClass){
		var temp=caller.parentNode;
		while (temp && (temp.nodeName!='DIV' || !temp.attributes || !temp.attributes['class'] || temp.attributes['class'].value.indexOf('hiddentools')==-1)) temp=temp.nextSibling;
		if (!temp) return false;
		temp=temp.firstChild;
		while (temp) {
			if(!temp.attributes || !temp.attributes['class'] || temp.attributes['class'].value.indexOf(toolClass)==-1){
				temp.style.display='none';
			} else{
				if(temp.style.display=='none'){
					temp.style.display='block';
				} else{
					temp.style.display='none';
				}
			}
			temp=temp.nextSibling;
		}

		return false;
	},

	switchParserToolbar: function(ref,toolbarClass){
		var temp=ref.parentNode.nextSibling;
		while (temp && temp.nodeName!='DIV') temp=temp.nextSibling;
		if(!temp){
			return false;
		}

		temp=temp.firstChild;
		while(temp){
			if(temp.nodeName=='DIV') {
				if (toolbarClass && temp.className.indexOf(toolbarClass)!=-1){
					temp.style.display='';
				} else{
					temp.style.display='none';
				}
			}
			temp=temp.nextSibling;
		}

		return false;
	},


	getMessageFromToolbar: function(obj,datakey){
		var temp=obj;
		while (temp && temp.nodeName!='FORM') temp=temp.parentNode;
		if(!temp){
			return false;
		}

		temp=temp.elements['tx_ppforum_pi1['+datakey+'][message]'];
		if(!temp){
			return false;
		}

		return temp;
	},

	wrapSelected: function(starttag,endtag,obj,datakey){
		var textarea = tx_ppforum.getMessageFromToolbar(obj,datakey);

		var start=textarea.selectionStart;

		if(start==null){
			//IE, still IE...
			textarea.selRange.text=starttag+textarea.selRange.text+endtag;
		} else{
			var stop=textarea.selectionEnd;
			var scTop=textarea.scrollTop;
			text=textarea.value;

			text=text.substring(0,stop)+endtag+text.substring(stop);
			text=text.substring(0,start)+starttag+text.substring(start);

			textarea.value=text;

			if (textarea.setSelectionRange) textarea.setSelectionRange(start+starttag.length,stop+starttag.length);
			textarea.scrollTop=scTop;
		}

		textarea.focus();

		return false;
	},

	/**
	 * Disable autocomplete functionnality on the given form's fields by adding an "autocomplete" attribute
	 *
	 * @param formNode formObj   = The form object
	 * @param array execeptList = field names (fields to skip)
	 * @access public
	 * @return string 
	 */
	disableAutoComplete: function(formObj, execeptList){
		for(i = 0; i < formObj.elements.length; i++) {
			if(!execeptList || execeptList.indexOf(formObj[i].name) == -1){
				formObj[i].setAttribute('autocomplete', 'off');
			}
		}
	}
};

Event.observe(window, 'load', function(event){
	document.getElementsByClassName('tx-ppforum-pi1').each(function(forum){
		forum = $(forum);
		$A(forum.getElementsByTagName('form')).each(function(item){
			tx_ppforum.disableAutoComplete(item);
		});
		$A(forum.getElementsByTagName('textarea')).each(function(item){
			new Belink.Resizable(item);
		});
	});
});

delete Belink.Resizable.defaultOptions.cornerStyle.backgroundImage;