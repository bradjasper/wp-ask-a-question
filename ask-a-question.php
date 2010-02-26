<?php
/*
Plugin Name: Ask A Question
Plugin URI: http://www.bradjasper.com/projects/ask-a-question/
Description: Ask A Question - Allow users to ask questions with a simple AJAX form. Simply add <strong>bjaq_form()</strong> anywhere you want the form to appear.
Author: Brad Jasper
Version: .1b
Author URI: http://www.bradjasper.com
*/

if (!isset($wpdb)) {
//	require('../../wp-blog-header.php');
	bjaq_init();
}

//	Let's define a few errors we're going to use throughout the script
$aErrors = array(
			"flood"	=> "You've reached the flood limit,"
						. " please slow down",
			"request"	=> "There was a problem completing your"
						. " request, please try again",
			"empty"	=> "The question you submitted was empty."
						. " Please fill out the form and try again",
			"default"	=> "There was an error submitting your"
						. " question, please try again"
);

$sScriptURI = '/wp-content/plugins/ask-a-question.php';

function bjaq_install() {
	global $wpdb;

	//	Create the table
	$result = mysql_query('CREATE TABLE `' . $wpdb->bjaq_questions .'` (
					`ID` INT( 11 ) NOT NULL AUTO_INCREMENT PRIMARY KEY ,
					`submitted` DATETIME NOT NULL ,
					`question` TEXT NOT NULL ,
					`ip` VARCHAR( 15 ) NOT NULL ,
					`host` VARCHAR( 255 ) NOT NULL ,
					`status` INT( 1 ) NOT NULL DEFAULT \'1\'
					) ENGINE = MYISAM ;') or die('Unable to create table');

	if (!$result) {
		return false;
	}

	return true;

}

function bjaq_form() {
	global $sError, $sSuccess;

	$sContent = '<div id="bjaq-div">
					<form id="bjaq-form">
						<textarea name="bjaq-question" id="bjaq-question-textarea"></textarea>
						<input type="button" onclick="bjaq_askQuestion();" value="Ask" />
					</form>
				 </div>
				 <div id="bjaq-result" style="display: none;">
				 </div>';
	
	//	This is a little hack to escape end of line characters
	//	so that javascript displays this correctly
	$aParts	  = explode("\r", $sContent);
	for ($i = 0; $i < count($aParts) - 1; $i++) {
		$aParts[$i] .= '\\';
	}
	$sContent = implode("\r", $aParts);

	$sContent = '<script type="text/javascript" language="Javascript">'
				. 'document.write(\'' . $sContent . '\');' .
				'</script>';

	//	Let's include a non AJAX version for users without Javascript
	$sContent .= '<noscript>';

	if ($sSuccess) {
		$sContent .=  '<div id="bjaq-result">' . $sSuccess . '</div>';

	} else {

		$sContent .= '<div id="bjaq-div">
						<form id="bjaq-form" method="post" action="' . $_SERVER['PHP_SELF'] .'">
							<input type="hidden" name="bjaq_post" value="1" />
							<textarea name="bjaq-question" id="bjaq-question-textarea"></textarea>
							<input type="Submit" value="Ask" />
						</form>
					 </div>
					 <div id="bjaq-result">
					 ' . ($sError ? $sError : '') . '
					 </div>';
	}

	$sContent .= '</noscript>';

	$sContent = '<h3>Ask A Question</h3>' . PHP_EOL . $sContent;

	echo($sContent);
}

function bjaq_head() {

	echo(PHP_EOL . '<script src="/' . PLUGINDIR
		. '/scriptaculous/lib/prototype.js" type="text/javascript"></script>');

	echo(PHP_EOL . '<script src="/' . PLUGINDIR
		. '/scriptaculous/src/scriptaculous.js?load=effects" type="text/javascript"></script>');

	echo(PHP_EOL . '<script src="/' . PLUGINDIR
		. '/ask-a-question.php?bjaq_js=true" type="text/javascript"></script>');
	
	echo(PHP_EOL . '<link rel="stylesheet" type="text/css" href="/' . PLUGINDIR 
		. '/ask-a-question.php?bjaq_css=true" />');

	wp_print_scripts(array('sack'));
}

function bjaq_admin_head() {
	global $sScriptURI;

	bjaq_head();

	echo("
		<style type='text/css'>
			#bjaq_admin_table {
				width: 100%;
			}

			#bjaq_admin_table th {
				border-bottom: 1px solid #7D7D7D;
				padding: 6px;
			}

			#bjaq_admin_table td {
				text-align: center;
				padding: 5px;
			}

			#bjaq_admin_table .alt {
				background-color: #EFEFEF;
			}
		</style>

		<script language='javascript' type='text/javascript'>
		
			function bjaq_disable_question(question_id) {

				var url		= '" . $sScriptURI . "';
				var params	= 'bjaq_admin_ajax=true'
							  + '&bjaq_question_id=' + question_id;
				var request = url + '?' + params;

				
				new Ajax.Request(request,
					{
						method:'get',
						onSuccess: function(transport){
								bjaq_admin_checkRequest(transport)
						},
						onFailure: function(){ bjaq_admin_reportError() }
					}
				);
			}

			function bjaq_admin_checkRequest(transport) {
				var data = transport.responseText.evalJSON()

				if (data.result == 'success') {

					fadeTableRow('bjaq-id-' + data.category_id);

				} else {
					bjaq_admin_reportError();
				}
			}
			function bjaq_admin_reportError() {
				alert('failed');
			}


		</script>
	");
}

function bjaq_init() {
	global $wpdb, $table_prefix;

	$wpdb->bjaq_questions = $table_prefix . 'bjaq_questions';

	//	If they're activating the plugin
	if (@$_GET['activate'] == 'true') {
		
		//	Get the tables in the wordpress database
		$result = mysql_list_tables(DB_NAME);
		
		$tables = array();
		while ($row = mysql_fetch_row($result)) {
			$tables[] = $row[0];
		}

		//	Only install if the table doesn't exist
		if ( !in_array($wpdb->bjaq_questions, $tables) ) {
			bjaq_install();
		}
	}

}

function bjaq_flood_check() {
	global $wpdb;

	$sIp		= $_SERVER['REMOTE_ADDR'];
	$nAllowed	= 3;

	//	Let's check if they're flooding the form
	$result = mysql_query("SELECT COUNT(id) as num_submits FROM "
					. $wpdb->bjaq_questions . " WHERE submitted > (NOW() - 60)"
					. " AND ip = '" . $sIp . "'");

	$kRow	= mysql_fetch_array($result);


	return ($kRow['num_submits'] >= $nAllowed);
	
}

function bjaq_insert_question() {
	global $wpdb;

mysql_query("SELECT * FROM wp_posts");

	$sQuestion	= $_REQUEST['bjaq-question'];
	$sQuestion	= mysql_real_escape_string(trim($sQuestion));
	$sIp		= $_SERVER['REMOTE_ADDR'];
	$sHost		= gethostbyaddr($sIp);

	$result = mysql_query("INSERT INTO " . $wpdb->bjaq_questions . "
					(submitted, question, ip, host, status) VALUES
					(NOW(),
					'" . $sQuestion . "',
					'" . $sIp . "',
					'" . $sHost . "',
					1)"
	);

	return $result;
}

function bjaq_admin_page() {
	global $wpdb;

	$result = mysql_query("SELECT * FROM " . $wpdb->bjaq_questions . "
							WHERE status = 1 ORDER BY submitted DESC");
	echo('
		<div class="wrap">
			<h2>'.__('Ask A Question - Queue').'</h2>
			<table cellspacing="3" id="bjaq_admin_table">
				<tr>
					<th>ID</th>
					<th>Date</th>
					<th>IP/Host</th>
					<th>Question</th>
					<th>Status</th>
				</tr>
	');

	if (mysql_num_rows($result)) {
		$i = -1;

		while ($kRow = mysql_fetch_array($result)) {
			echo('<tr id="bjaq-id-' . $kRow['ID'] . '"' . ($i == 1 ? ' class="alt"' : '') . '>
					<td>' . $kRow['ID'] . '</td>
					<td>' . $kRow['submitted'] . '</td>
					<td>' . $kRow['ip'] . ' 
						<a href="#" title="' . $kRow['host'] . '">?</a>
					</td>
					<td>' . $kRow['question'] . '</td>
					<td>
						<a href="javascript:bjaq_disable_question(' . $kRow['ID'] . ')">Disable</a>
					</td>
				</tr>
			');
			$i = $i * -1;
		}

	} else {
		echo('<tr>
				<td colspan="5" align="center">
					<br />Currently there are no questions
				</td>
			  </tr>
		');
	}

	echo('
			</table>
		</div>
	');
}

//	This is the request the ajax script calls
if (@$_REQUEST['bjaq_ajax']) {
	bjaq_init();
	$sResult	= "failed";

	if (bjaq_flood_check()) {
		die('flood');
	}

	if (trim($_REQUEST['bjaq-question'])) {

		if (bjaq_insert_question()) {
			$sResult = "success";
		}

	} else {
		$sResult = "empty";
	}

	die($sResult);
}

//	This is the part the non-AJAX script calls
if (@$_REQUEST['bjaq_post']) {
	bjaq_init();	
	$sError = '';

	if (bjaq_flood_check()) {
		$sError = $aErrors['flood'];
	
	} else {
		
		if (trim($_REQUEST['bjaq-question'])) {

			if (bjaq_insert_question()) {

				$sSuccess = 'Succefully submitted question,
					<a href="/">submit another</a>';
			}

		} else {
			$sError = $aErrors['empty'];
		}
	}
}

//	This is the request for the AJAX admin actions
if (@$_REQUEST['bjaq_admin_ajax']) {

	$sResult = 'failed';

	if (@$_REQUEST['bjaq_question_id']) {
		
		$result = mysql_query("UPDATE " . $wpdb->bjaq_questions . " SET status = 0
						WHERE ID = " . $_REQUEST['bjaq_question_id'] . " LIMIT 1");
		
		if ($result) {
			$sResult = 'success';
		}
	}

	die("{result: '" . $sResult . "',
		  category_id: " . $_REQUEST['bjaq_question_id'] . "}");
}

//	This is the request for the Javascript file
if (@$_REQUEST['bjaq_js']) {
	header("Content-type: text/javascript");
	?>
	function bjaq_askQuestion() {
		var url		= '<?php echo($sScriptURI); ?>';
		var params	= 'bjaq_ajax=true&' + $('bjaq-question-textarea').serialize(true);
		var request = url + '?' + params;
		
		var mysack = new sack( 
			"<?php bloginfo( 'wpurl' ); ?>/wp-admin/admin-ajax.php" );    

		mysack.execute = 1;
		mysack.method = 'POST';
		mysack.setVar("action", "ask_a_question");
		mysack.setVar("bjaq_ajax", $("bjaq-question-textarea"));
		mysack.onError = function() { bjaq_reportError('request') }
		mysack.onSuccess = function() { alert("success") }
		mysack.runAJAX();

		/*
		new Ajax.Request(request,
			{
				method:'get',
				onSuccess: function(transport){ bjaq_updateResponse(transport) },
				onFailure: function(){ bjaq_reportError('request') }
			}
		);
		*/

		$('bjaq-result').style.display = 'none';
	}

	function bjaq_reportError(errorType) {
		var errMsg;

		switch (errorType) {
			case 'flood':
				errMsg = "<?php echo($aErrors['flood']); ?>";
				break;
			
			case 'request':
				errMsg = "<?php echo($aErrors['request']); ?>";
				break;

			case 'empty':
				errMsg = "<?php echo($aErrors['empty']); ?>";
				break;
			
			default:
				errMsg = "<?php echo($aErrors['default']); ?>";
		}

		$('bjaq-result').innerHTML	= errMsg;

		Effect.Appear('bjaq-result');
	}

	function bjaq_updateResponse(transport) {
		if (transport.responseText == 'success') {

			Effect.SlideUp('bjaq-div');

			$('bjaq-result').innerHTML	= 'Successfully submitted question, \
				<a href="javascript:bjaq_submitAnother();">submit another</a>';

			Effect.Appear('bjaq-result', {queue: 'end'});

		} else {
			bjaq_reportError(transport.responseText);
		}
	}

	function bjaq_submitAnother() {
		$('bjaq-question-textarea').value = '';

		Effect.Fade('bjaq-result');
		Effect.SlideDown('bjaq-div', {queue: 'end'});
	}
	<?php
	die();
}

if (@$_REQUEST['bjaq_css']) {
	header("Content-type: text/css");
	?>
	#bjaq-question-textarea {
		width: 98%;
		height: 50px;
	}

	#bjaq-div {
		text-align: right;
		margin: 5px 5px;
		padding: 5px;
	}

	#bjaq-form input {
		margin: 5px 3px;
	}

	#bjaq-result {
		text-align: center;
		padding: 5px;
	}
	<?php
	die();
}

function bjaq_admin_menu() {
	add_options_page(
			__('Ask A Question', 'bradjasper.com')
			, __('Ask A Question', 'bradjasper.com')
			, 10
			, basename(__FILE__)
			, 'bjaq_admin_page'
		);
}

add_action('wp_head', 'bjaq_head');
add_action('admin_head', 'bjaq_admin_head');
add_action('init', 'bjaq_init');
add_action('admin_menu', 'bjaq_admin_menu');
?>
