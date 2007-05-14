/****************************************************
 * Javascript functioons for the pp_forum extension
 * @see http://typo3.org
 *
 * @author: popy
 *
 */


function ppforum_showhideTool(caller,toolClass){
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
}

function ppforum_switchParserToolbar(ref,toolbarClass){
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
}


function ppforum_getMessageFromToolbar(obj,datakey){
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
}

function ppforum_wrapSelected(starttag,endtag,obj,datakey){
	var textarea=ppforum_getMessageFromToolbar(obj,datakey);

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
}

function debug(obj){
	var str='';
	var c=0;

	for(i in obj){
		str+=i+' = '+obj[i]+"\n";c++;
		if(c>19){
			c=0;
			alert(str);
			str='';
		}
	}
}