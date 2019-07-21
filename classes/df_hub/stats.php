<?php

require_once $CFG->dirroot . '/blocks/elbp/lib.php';

// Count Additional Support Sessions
$stats['addsup'] = $DB->count_records("lbp_add_sup_sessions", array("del" => 0));

// Count Attachments
$stats['attachments'] = $DB->count_records("lbp_attachments", array("del" => 0));

// COunt Challenges
$stats['challenges'] = $DB->count_records("lbp_user_challenges", array("del" => 0));

// Count Comments
$stats['comments'] = $DB->count_records("lbp_comments", array("del" => 0));

// Count Course Reports
$stats['coursereports'] = $DB->count_records("lbp_course_reports", array("del" => 0));

// Count Periodical Course Reports
$stats['periodicalcoursereports'] = $DB->count_records("lbp_termly_creports", array("del" => 0));

// COunt Targets
$stats['targets'] = $DB->count_records("lbp_targets", array("del" => 0));

// Count Tutorials
$stats['tutorials'] = $DB->count_records("lbp_tutorials", array("del" => 0));

// COunt Custom Plugins
$stats['customplugins'] = $DB->count_records("lbp_custom_plugins");
