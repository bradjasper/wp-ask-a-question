<?php
/*
Plugin Name: Ask A Question
Plugin URI: http://www.bradjasper.com/projects/ask-a-question/
Description: Ask A Question - Allow users to ask questions with a simple AJAX form. Simply add <strong>bjaq_form()</strong> anywhere you want the form to appear.
Author: Brad Jasper
Version: .1b
Author URI: http://www.bradjasper.com
*/

ini_set("error_reporting", E_ALL);

if (!isset($wpdb)) {
//	require('../../wp-blog-header.php');
	bjaq_init();
}

//	Let's define a few errors we're going to use throughout the script
$aErrors = array(
			"flood"	=> "You've reached the maximum limit,"
						. " please slow down",
			"request"	=> "There was a problem completing your"
						. " request, please try again",
			"empty"	=> "The question you submitted was empty."
						. " Please fill out the form and try again",
            "insert_error" => "There was an error while saving your message"
						. " Please fill out the form and try again",
            "unknown" => "An unknown error occured. Please try again",
			"default"	=> "There was an error submitting your"
						. " question, please try again"
);

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

function bjaq_form_html($action=".") {
	return '<div id="bjaq-div">
                <form id="bjaq-form" action="' . $action . '">
                        <input type="hidden" name="bjaq_action" value="bjaq_post" />
						<textarea name="bjaq_message" id="bjaq-question-textarea"></textarea>
						<input type="submit" onclick="bjaq_askQuestion(); return false;" value="Ask" />
					</form>
				 </div>';

}

function bjaq_form() {

    global $aErrors;

    $sContent = "";

	if (get_request_var("bjaq_action") == "bjaq_success") {
        $sContent .=  '<div id="bjaq-result">Successfully submitted message!
            <a href=".">Submit another</a>.</div>';
	} else {

        $bjaq_error = "";
        if (get_request_var("bjaq_action") == "bjaq_error") {
            $err_type = get_request_var("bjaq_error");

            if (!isset($aErrors[$err_type])) {
                $err_type = "unknown";
            }

            $sContent .= '<div id="bjaq-result">' . $aErrors[$err_type] . '</div>';
        }

        $sContent .= bjaq_form_html()
            . ' <div id="bjaq-result">' . ($bjaq_error ? $bjaq_error : '') . '</div>';
	}

	$sContent .= '</noscript>';

	$sContent = '<h3>Ask A Question</h3>' . PHP_EOL . $sContent;

	echo($sContent);
}

function bjaq_script($url) {
	echo(PHP_EOL . '<script src="' . get_bloginfo("url") . $url
		. '" type="text/javascript"></script>');
}

function bjaq_head() {

    $blogurl = get_bloginfo("url");

    wp_print_scripts(array("jquery"));

    echo('
        <script src="' . $blogurl . '?bjaq_action=bjaq_js" type="text/javascript"></script>
        <link rel="stylesheet" type="text/css" href="' . $blogurl . '?bjaq_action=bjaq_css" />
    ');


}

function bjaq_admin_head() {

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

	$sQuestion	= mysql_real_escape_string(get_request_var("bjaq_message"));

    if (!$sQuestion) {
        return false;
    }

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

function get_request_var($var) {
    if (isset($_REQUEST[$var]) && trim($_REQUEST[$var])) {
        return trim($_REQUEST[$var]);
    }
}

function redirect($url) {
    header("HTTP/1.1 301 Moved Permanently");
    header("Location: " . $url);
    die();
}

function blog_url($extension) {
    return get_bloginfo("url") . $extension;
}

function redirect_error($error_type) {
    redirect(blog_url("?bjaq_action=bjaq_error&bjaq_error=" . $error_type));
}


//	This is the part the non-AJAX script calls
function handle_bjaq_post() {
	bjaq_init();	

	if (bjaq_flood_check()) {
        return "flood";
	}

    if (!get_request_var("bjaq_message")) {
        return "empty";
    }

    if (bjaq_insert_question()) {
        return "success";
    }

    return "insert_error";
}

function bjaq_post() {
    $result = handle_bjaq_post();
    if ($result == "success") {
        redirect(blog_url("?bjaq_action=bjaq_success"));
    }

    redirect_error($result);
}

//	This is the request the ajax script calls
function bjaq_ajax_post() {
    die(handle_bjaq_post());
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

function bjaq_javascript() {
    global $aErrors;

	header("Content-type: text/javascript");
	?>
	function bjaq_askQuestion() {

        var params = "?bjaq_action=bjaq_ajax_post&"
             + jQuery("#bjaq-question-textarea").serialize();
        alert(params);

        jQuery.ajax({
            type: "POST",
            url: "<?php echo get_bloginfo("url"); ?>/"+params,
            success: function(data) {
				bjaq_updateResponse(data);
            },
            error: function(data) {
				bjaq_reportError("request");
            }
        });

		jQuery("#bjaq-result").hide();
		return false;
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

		jQuery('#bjaq-result').text(errMsg);
        jQuery("#bjaq-result").show();
	}

	function bjaq_updateResponse(response) {
		if (response == 'success') {

            jQuery("#bjaq-div").hide();

			jQuery('#bjaq-result').html('Successfully submitted question, '
				+ '<a href="javascript:bjaq_submitAnother();">submit another</a>');

            jQuery("#bjaq-result").show();

		} else {
			bjaq_reportError(response);
		}
	}

	function bjaq_submitAnother() {
		jQuery('#bjaq-question-textarea').val("");

        jQuery("#bjaq-result").hide();
        jQuery("#bjaq-div").show();

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

function bjaq_controller() {
    $action = get_query_var("bjaq_action");

    switch ($action) {

        case "bjaq_js":
            bjaq_javascript();
            break;
        case "bjaq_css":
            bjaq_css();
            break;
        case "bjaq_post":
            bjaq_post();
            break;
        case "bjaq_ajax_post":
            bjaq_ajax_post();
            break;
    }
}

function bjaq_form_vars($public_vars) {
    $public_vars[] = "bjaq_action";
    return $public_vars;
}

add_filter('query_vars', 'bjaq_form_vars');
add_action('wp_head', 'bjaq_head');
add_action('admin_head', 'bjaq_admin_head');
add_action('init', 'bjaq_init');
add_action('admin_menu', 'bjaq_admin_menu');
add_action('template_redirect', 'bjaq_controller');
?>
