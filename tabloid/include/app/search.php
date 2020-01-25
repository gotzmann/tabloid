<?php

/*
	Wrapper functions and utilities for search modules
*/


/**
 * Returns $count search results for $query performed by $userid, starting at offset $start. Set $absoluteurls to true
 * to get absolute URLs for the results and $fullcontent if the results should include full post content. This calls
 * through to the chosen search module, and performs all the necessary post-processing to supplement the results for
 * display online or in an RSS feed.
 * @param $query
 * @param $start
 * @param $count
 * @param $userid
 * @param $absoluteurls
 * @param $fullcontent
 * @return
 */
function qa_get_search_results($query, $start, $count, $userid, $absoluteurls, $fullcontent)
{
	// Identify which search module should be used

	$searchmodules = qa_load_modules_with('search', 'process_search');

	if (!count($searchmodules))
		qa_fatal_error('No search engine is available');

	$module = reset($searchmodules); // use first one by default

	if (count($searchmodules) > 1) {
		$tryname = qa_opt('search_module'); // use chosen one if it's available

		if (isset($searchmodules[$tryname]))
			$module = $searchmodules[$tryname];
	}

	// Get the results

	$results = $module->process_search($query, $start, $count, $userid, $absoluteurls, $fullcontent);

	// Work out what additional information (if any) we need to retrieve for the results

	$keypostidgetfull = array();
	$keypostidgettype = array();
	$keypostidgetquestion = array();
	$keypageidgetpage = array();

	foreach ($results as $result) {
		if (isset($result['question_postid']) && !isset($result['question']))
			$keypostidgetfull[$result['question_postid']] = true;

		if (isset($result['match_postid'])) {
			if (!((isset($result['question_postid'])) || (isset($result['question']))))
				$keypostidgetquestion[$result['match_postid']] = true; // we can also get $result['match_type'] from this

			elseif (!isset($result['match_type']))
				$keypostidgettype[$result['match_postid']] = true;
		}

		if (isset($result['page_pageid']) && !isset($result['page']))
			$keypageidgetpage[$result['page_pageid']] = true;
	}

	// Perform the appropriate database queries

	list($postidfull, $postidtype, $postidquestion, $pageidpage) = qa_db_select_with_pending(
		count($keypostidgetfull) ? qa_db_posts_selectspec($userid, array_keys($keypostidgetfull), $fullcontent) : null,
		count($keypostidgettype) ? qa_db_posts_basetype_selectspec(array_keys($keypostidgettype)) : null,
		count($keypostidgetquestion) ? qa_db_posts_to_qs_selectspec($userid, array_keys($keypostidgetquestion), $fullcontent) : null,
		count($keypageidgetpage) ? qa_db_pages_selectspec(null, array_keys($keypageidgetpage)) : null
	);

	// Supplement the results as appropriate

	foreach ($results as $key => $result) {
		if (isset($result['question_postid']) && !isset($result['question']))
			if (@$postidfull[$result['question_postid']]['basetype'] == 'Q')
				$result['question'] = @$postidfull[$result['question_postid']];

		if (isset($result['match_postid'])) {
			if (!(isset($result['question_postid']) || isset($result['question']))) {
				$result['question'] = @$postidquestion[$result['match_postid']];

				if (!isset($result['match_type']))
					$result['match_type'] = @$result['question']['obasetype'];

			} elseif (!isset($result['match_type']))
				$result['match_type'] = @$postidtype[$result['match_postid']];
		}

		if (isset($result['question']) && !isset($result['question_postid']))
			$result['question_postid'] = $result['question']['postid'];

		if (isset($result['page_pageid']) && !isset($result['page']))
			$result['page'] = @$pageidpage[$result['page_pageid']];

		if (!isset($result['title'])) {
			if (isset($result['question']))
				$result['title'] = $result['question']['title'];
			elseif (isset($result['page']))
				$result['title'] = $result['page']['heading'];
		}

		if (!isset($result['url'])) {
			if (isset($result['question']))
				$result['url'] = qa_q_path($result['question']['postid'], $result['question']['title'],
					$absoluteurls, @$result['match_type'], @$result['match_postid']);
			elseif (isset($result['page']))
				$result['url'] = qa_path($result['page']['tags'], null, qa_opt('site_url'));
		}

		$results[$key] = $result;
	}

	// Return the results

	return $results;
}
