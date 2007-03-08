<?php

for ($i=0;$i<100;$i++) {
	$TYPO3_DB->exec_INSERTquery(
		'tx_ppforum_topics',
		array(
			'forum'=>7,
			'title'=>'Topic de test #'.($i+1),
			'message'=>'Apocalypsis Iesu Christi quam dedit illi Deus palam facere servis suis quae oportet fieri cito et significavit mittens per angelum suum servo suo Iohanni. 
	Qui testimonium perhibuit verbo Dei et testimonium Iesu Christi quaecumque vidit. 
	Beatus qui legit et qui audiunt verba prophetiae et servant ea quae in ea scripta sunt tempus enim prope est. 
	','crdate'=>time()+$i,'tstamp'=>time()+$i,'pid'=>2));

	$topicId=$TYPO3_DB->sql_insert_id();

	if (!$topicId) {
		t3lib_div::debug(mysql_error(), '');
		return ;
	}

	for ($j=0;$j<20;$j++) {
		$TYPO3_DB->exec_INSERTquery('tx_ppforum_messages',array('topic'=>$topicId,'message'=>'Message #'.($j+1).'
		Apocalypsis Iesu Christi quam dedit illi Deus palam facere servis suis quae oportet fieri cito et significavit mittens per angelum suum servo suo Iohanni. 
		Qui testimonium perhibuit verbo Dei et testimonium Iesu Christi quaecumque vidit. 
		Beatus qui legit et qui audiunt verba prophetiae et servant ea quae in ea scripta sunt tempus enim prope est. 
		','crdate'=>time()+$j+$i,'tstamp'=>time(),'pid'=>2));
	}
}


?>