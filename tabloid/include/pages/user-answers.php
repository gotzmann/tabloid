<?php
/*
	Controller for user page showing all user's answers
*/


require_once INCLUDE_DIR . 'db/selects.php';
require_once INCLUDE_DIR . 'app/format.php';


// $handle, $userhtml are already set by /qa-include/page/user.php - also $userid if using external user integration

$start = qa_get_start();


// Find the questions for this user

$loginuserid = qa_get_logged_in_userid();
$identifier = FINAL_EXTERNAL_USERS ? $userid : $handle;

list($useraccount, $userpoints, $questions) = qa_db_select_with_pending(
	FINAL_EXTERNAL_USERS ? null : qa_db_user_account_selectspec($handle, false),
	qa_db_user_points_selectspec($identifier),
	qa_db_user_recent_a_qs_selectspec($loginuserid, $identifier, qa_opt_if_loaded('page_size_activity'), $start)
);

if (!FINAL_EXTERNAL_USERS && !is_array($useraccount)) // check the user exists
	return include INCLUDE_DIR . 'page-not-found.php';


// Get information on user questions

$pagesize = qa_opt('page_size_activity');
$count = (int)@$userpoints['aposts'];
$questions = array_slice($questions, 0, $pagesize);
$usershtml = qa_userids_handles_html($questions, false);


// Prepare content for theme

$qa_content = qa_content_prepare(true);

if (count($questions))
	$qa_content['title'] = qa_lang_html_sub('profile/answers_by_x', $userhtml);
else
	$qa_content['title'] = qa_lang_html_sub('profile/no_answers_by_x', $userhtml);


// Recent questions by this user

$qa_content['q_list']['form'] = array(
	'tags' => 'method="post" action="' . qa_self_html() . '"',

	'hidden' => array(
		'code' => qa_get_form_security_code('vote'),
	),
);

$qa_content['q_list']['qs'] = array();

$htmldefaults = qa_post_html_defaults('Q');
$htmldefaults['whoview'] = false;
$htmldefaults['avatarsize'] = 0;
$htmldefaults['ovoteview'] = true;
$htmldefaults['answersview'] = false;

foreach ($questions as $question) {
	$options = qa_post_html_options($question, $htmldefaults);
	$options['voteview'] = qa_get_vote_view('A', false, false);

	$qa_content['q_list']['qs'][] = qa_other_to_q_html_fields($question, $loginuserid, qa_cookie_get(),
		$usershtml, null, $options);
}

$qa_content['page_links'] = qa_html_page_links(qa_request(), $start, $pagesize, $count, qa_opt('pages_prev_next'));


// Sub menu for navigation in user pages

$ismyuser = isset($loginuserid) && $loginuserid == (FINAL_EXTERNAL_USERS ? $userid : $useraccount['userid']);
$qa_content['navigation']['sub'] = qa_user_sub_navigation($handle, 'answers', $ismyuser);


return $qa_content;
