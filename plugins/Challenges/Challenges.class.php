<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Electronic Learning Blue Print
 *
 * ELBP is a moodle block plugin, which provides one singular place for all of a student's key academic information to be stored and viewed, such as attendance, targets, tutorials,
 * reports, qualification progress, etc... as well as unlimited custom sections.
 * 
 * @package     block_elbp
 * @copyright   2017-onwards Conn Warwicker
 * @author      Conn Warwicker <conn@cmrwarwicker.com>
 * @link        https://github.com/cwarwicker/moodle-block_elbp
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * Originally developed at Bedford College, now maintained by Conn Warwicker
 * 
 */

namespace ELBP\Plugins;

/**
 * 
 */
class Challenges extends Plugin {
    
    protected $tables = array(
        'lbp_challenges',
        'lbp_user_challenges'
    );
    
    /**
     * Construct the plugin object
     * @param bool $install If true, we want to send the default info to the parent constructor, to install the record into the DB
     */
    public function __construct($install = false) {
        
        if ($install){
            parent::__construct( array(
                "name" => strip_namespace(get_class($this)),
                "title" => "Challenges",
                "path" => null,
                "version" => \ELBP\ELBP::getBlockVersionStatic()
            ) );
        }
        else
        {
            parent::__construct( strip_namespace(get_class($this)) );
        }

    }
    
    
     /**
     * Install the plugin
     */
    public function install()
    {
        
        global $CFG, $DB;
        
        $dbman = $DB->get_manager();
        
        $return = true;
        $this->id = $this->createPlugin();
        
        
        // Install tables - This plugin came later, so won't necessarily have installed the tables in the block xml
         
        
        // Define table lbp_challenges to be created
        $table = new \xmldb_table('lbp_challenges');

        // Adding fields to table lbp_challenges
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('challenge', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('parent', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('img', XMLDB_TYPE_CHAR, '255', null, null, null, null);

        // Adding keys to table lbp_challenges
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
        $table->add_key('pid_fk', XMLDB_KEY_FOREIGN, array('parent'), 'lbp_challenges', array('id'));

        // Adding indexes to table lbp_challenges
        $table->add_index('c_indx', XMLDB_INDEX_NOTUNIQUE, array('challenge'));

        // Conditionally launch create table for lbp_challenges
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        
        
        
        
        // Define table lbp_user_challenges to be created
        $table = new \xmldb_table('lbp_user_challenges');

        // Adding fields to table lbp_user_challenges.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('studentid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('challengeid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('dateset', XMLDB_TYPE_INTEGER, '20', null, XMLDB_NOTNULL, null, null);
        $table->add_field('setbyuserid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('comments', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('confidentialityid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '1');
        $table->add_field('del', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');

        // Adding keys to table lbp_user_challenges.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
        $table->add_key('sid_fk', XMLDB_KEY_FOREIGN, array('studentid'), 'user', array('id'));
        $table->add_key('setby_fk', XMLDB_KEY_FOREIGN, array('setbyuserid'), 'user', array('id'));
        $table->add_key('cid_fk', XMLDB_KEY_FOREIGN, array('challengeid'), 'lbp_challenges', array('id'));
        $table->add_key('confid', XMLDB_KEY_FOREIGN, array('confidentialityid'), 'lbp_confidentiality', array('id'));

        // Adding indexes to table lbp_user_challenges.
        $table->add_index('main_indx', XMLDB_INDEX_NOTUNIQUE, array('studentid', 'del', 'dateset'));

        // Conditionally launch create table for lbp_user_challenges.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }
        
        
        // Insert default challenges
        $array = array(
            'Child/Adult Care' => 'child-care.png',
            'Computer Skills' => 'computer-skills.png',
            'Stress' => 'stress.png',
            'Communication Skills' => 'language-skills.png',
            'Finances' => 'finances.png',
            'Grief/Loss' => 'grief.png',
            'Motivation' => 'motivation.png',
            'Health' => 'health.png',
            'Transportation' => 'transport.png',
            'Disability' => 'disability.png',
            'Family Life' => 'family.png',
            'Time Management' => 'time-management.png',
            'Alcohol/Substance Abuse' => 'drink-drugs.png',
            'Exam Anxiety' => 'exam-anxiety.png',
            'Cultural Issues' => 'globe.png',
            'English Skills' => 'abc.png',
            'Unclear Goals/Career Choices' => 'ambition.png',
            'Relationship Problems' => 'relationships.png',
            'Housing/Shelter' => 'housing.png',
            'Legal Issues' => 'legal.png',
            'Concentration' => 'concentration.png',
            'Study Resources' => 'resources.png',
            'Other' => 'other.png',
            'Confidence' => 'confidence.png'
        );
        
        foreach($array as $chal => $pic)
        {
            $obj = new \stdClass();
            $obj->challenge = $chal;
            $obj->img = $CFG->wwwroot . '/blocks/elbp/plugins/Challenges/pix/' . $pic;
            $DB->insert_record("lbp_challenges", $obj);
        }
               
        // Alerts
        $DB->insert_record("lbp_alert_events", array("pluginid" => $this->id, "name" => "Challenges Updated", "description" => "Possible challenges to academic succcess updated", "auto" => 0, "enabled" => 1));        
        
        return $return;
        
    }
    
    /**
     * Truncate the related tables and uninstall plugin
     * @global type $DB
     */
    public function uninstall() {
        
        global $DB;
        
        if ($this->tables){
            foreach($this->tables as $table){
                $DB->execute("TRUNCATE {{$table}}");
            }
        }
        
        parent::uninstall();
        
    }
    
    /**
     * Upgrade the plugin from an older version to newer
     */
    public function upgrade(){
        
        global $CFG, $DB;
        
        $dbman = $this->DB->get_manager();
        $result = true;
        $version = $this->version; # This is the current DB version we will be using to upgrade from     
        
        // [Upgrades here]
        if ($version < 2014030500)
        {
            $DB->insert_record("lbp_alert_events", array("pluginid" => $this->id, "name" => "Challenges Updated", "description" => "Possible challenges to academic succcess updated", "auto" => 0, "enabled" => 1));
            $this->version = 2014030500;
            $this->updatePlugin();
        }
        
        
        if ($version < 2014102900)
        {
            
            // Insert challenges
            $array = array(
                'Concentration' => 'concentration.png',
                'Study Resources' => 'resources.png',
                'Other' => 'other.png',
                'Confidence' => 'confidence.png'
            );

            foreach($array as $chal => $pic)
            {
                $obj = new \stdClass();
                $obj->challenge = $chal;
                $obj->img = $CFG->wwwroot . '/blocks/elbp/plugins/Challenges/pix/' . $pic;
                $DB->insert_record("lbp_challenges", $obj);
            }
            
            $this->version = 2014102900;
            $this->updatePlugin();
            
        }
        
        if ($version < 2015032500) {

            // Define field confidentialityid to be added to lbp_user_challenges.
            $table = new \xmldb_table('lbp_user_challenges');
            $field = new \xmldb_field('confidentialityid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '1', 'comments');

            // Conditionally launch add field confidentialityid.
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
            
            $key = new \xmldb_key('confid', XMLDB_KEY_FOREIGN, array('confidentialityid'), 'lbp_confidentiality', array('id'));

            // Launch add key confid.
            $dbman->add_key($table, $key);

            // Elbp savepoint reached.
            $this->version = 2015032500;
            $this->updatePlugin();
            
        }
        
        
        
    }
    
    /**
     * Get the expanded view
     * @param type $params
     * @return type
     */
    public function getDisplay($params = array()){
                
        $output = "";
        
        $challenges = $this->getAllPossibleChallenges();
        if ($challenges)
        {
            
            foreach($challenges as &$challenge)
            {
                $challenge->userChallenge = $this->getUserChallenge($challenge->id);
            }
            
        }
        
        $confidentiality = new \ELBP\Confidentiality();
        $cLevels = $confidentiality->getAllLevels();
        
        $TPL = new \ELBP\Template();
        $TPL->set("obj", $this);
        $TPL->set("access", $this->access);      
        $TPL->set("params", $params);
        $TPL->set("challenges", $challenges);
        $TPL->set("cLevels", $cLevels);
        $TPL->set("CONF", $confidentiality);
        
        try {
            $output .= $TPL->load($this->CFG->dirroot . '/blocks/elbp/plugins/'.$this->name.'/tpl/expanded.html');
        } catch (\ELBP\ELBPException $e){
            $output .= $e->getException();
        }

        return $output;
        
    }
    
    /**
     * Load the summary box
     * @return type
     */
    public function getSummaryBox(){
        
        $TPL = new \ELBP\Template();
        
        $TPL->set("obj", $this);
        $TPL->set("access", $this->access);      
        $TPL->set("challenges", $this->getUserChallenges());
        $TPL->set("CONF", new \ELBP\Confidentiality());
                        
        try {
            return $TPL->load($this->CFG->dirroot . '/blocks/elbp/plugins/'.$this->name.'/tpl/summary.html');
        }
        catch (\ELBP\ELBPException $e){
            return $e->getException();
        }
        
    }
    
    /**
     * Handle AJAX requests sent to the plugin
     * @global \ELBP\Plugins\type $DB
     * @global type $USER
     * @param type $action
     * @param type $params
     * @param type $ELBP
     * @return boolean
     */
    public function ajax($action, $params, $ELBP){
        
        global $DB, $USER;
        
        switch($action)
        {
            
            case 'load_display_type':
                                
                // Correct params are set?
                if (!$params || !isset($params['studentID']) || !$this->loadStudent($params['studentID'])) return false;
                
                // We have the permission to do this?
                $access = $ELBP->getUserPermissions($params['studentID']);
                if (!$ELBP->anyPermissionsTrue($access)) return false;
                                
                $TPL = new \ELBP\Template();
                $TPL->set("obj", $this)
                    ->set("access", $access);
                
                try {
                    $TPL->load( $this->CFG->dirroot . '/blocks/elbp/plugins/'.$this->name.'/tpl/'.$params['type'].'.html' );
                    $TPL->display();
                } catch (\ELBP\ELBPException $e){
                    echo $e->getException();
                }
                exit;                
                
            break;
            
            case 'save':
                                
                if (!$params || !isset($params['studentID']) || !$this->loadStudent($params['studentID']) || !isset($params['challengeID']) || !isset($params['challengeComments'])) return false;
                                
                // We have the permission to do this?
                $access = $ELBP->getUserPermissions($params['studentID']);
                if (!$ELBP->anyPermissionsTrue($access)) return false;
                if (!elbp_has_capability('block/elbp:add_challenge', $access)) return false;
                                                
                $challengeID = $params['challengeID'];
                $comments = trim($params['challengeComments']);
                $confidentialityLevel = (isset($params['challenge_confidentiality'])) ? $params['challenge_confidentiality'] : ELBP_CONFIDENTIALITY_GLOBAL;
                
                $check = $DB->get_record("lbp_user_challenges", array("studentid" => $this->student->id, "challengeid" => $challengeID));
                
                // If comments are empty, remove challenge
                if ($check && empty($comments))
                {
                    $check->del = 1;
                    $DB->update_record("lbp_user_challenges", $check);
                }
                else
                {
                    
                    // Check if already exists
                    if ($check){

                        // Update
                        $check->comments = $comments;
                        $check->confidentialityid = $confidentialityLevel;
                        $check->del = 0;
                        $DB->update_record("lbp_user_challenges", $check);

                    } else {

                        // Insert
                        $ins = new \stdClass();
                        $ins->studentid = $this->student->id;
                        $ins->challengeid = $challengeID;
                        $ins->dateset = time();
                        $ins->setbyuserid = $USER->id;
                        $ins->comments = $comments;
                        $ins->confidentialityid = $confidentialityLevel;
                        $ins->del = 0;
                        $DB->insert_record("lbp_user_challenges", $ins);

                    }

                    // Log Action
                    elbp_log(LOG_MODULE_ELBP, LOG_ELEMENT_ELBP_CHALLENGES, LOG_ACTION_ELBP_CHALLENGES_UPDATED_CHALLENGES, $this->student->id, array(
                        "challengeID" => $challengeID,
                        "comments" => $comments
                    ));                        

                    
                }
                
                
                
                
                $challenge = $DB->get_record("lbp_challenges", array("id" => $challengeID));
                
                
                // Alerts
                $alertContent = get_string('alerts:challengesupdated', 'block_elbp') . $this->getInfoForEventTrigger($challenge);

                // Trigger student alert - always
                elbp_event_trigger_student("Challenges Updated", $this->id, $this->student->id, $alertContent, nl2br($alertContent));

                // Trigger staff alerts - if we want them
                elbp_event_trigger("Challenges Updated", $this->id, $this->student->id, $alertContent, nl2br($alertContent), $confidentialityLevel);
                
                
                
                
                
                
                // Saved OK
                echo "$('#challenges_output_{$challengeID}').html('".get_string('challengesupdated', 'block_elbp')."');";                                               
                echo "$('#challenges_output_{$challengeID}').show();";
                echo "setTimeout( function(){
                        $('#challenges_output_{$challengeID}').fadeOut('slow');
                      }, 3000 );";
                echo "$('#challenges_output').html('');";
                        
                exit;
                
                
            break;
            
        }
        
    }
    
    /**
     * Save the configuration settings
     * @global \ELBP\Plugins\type $DB
     * @global type $MSGS
     * @param type $settings
     * @return boolean
     */
    public function saveConfig($settings) {
        
        global $DB, $MSGS;
        
        if (isset($_POST['save_challenges'])){
            
            $challenges = $_POST['challenges'];
            $icons = $_POST['icons'];
            $ids = @$_POST['challenge_ids'];
            if ($challenges)
            {
                
                foreach($challenges as $id => $challenge)
                {
                    
                    $challenge = trim($challenge);
                    $icon = trim($icons[$id]);      
                    if (empty($icon)) $icon = null;
                    
                    // Update existing one
                    if (isset($ids[$id]) && is_numeric($ids[$id])){
                        
                        // Delete existing
                        if (empty($challenge)){
                            
                            $DB->delete_records("lbp_challenges", array("id" => $ids[$id]));
                            continue;
                            
                        }
                        
                        
                        
                        $upd = new \stdClass();
                        $upd->id = $ids[$id];
                        $upd->challenge = $challenge;
                        $upd->img = $icon;
                        $DB->update_record("lbp_challenges", $upd);
                        
                    } else {
                        
                        if (empty($challenge)) continue;
                        
                        // New one
                        $ins = new \stdClass();
                        $ins->challenge = $challenge;
                        $ins->img = $icon;
                        $DB->insert_record("lbp_challenges", $ins);
                        
                    }
                    
                }
                
            }

            
            $MSGS['success'] = get_string('challengesupdated', 'block_elbp');
            unset($settings);
            return true;
            
        }
        
        parent::saveConfig($settings);
        
    }
    
    /**
     * Get all the possible challenges we can choose from
     * @global \ELBP\Plugins\type $DB
     * @return type
     */
    public function getAllPossibleChallenges(){
        
        global $DB;
        
        $records = $DB->get_records("lbp_challenges", null, "challenge ASC");
        
        $other = false;
        
        // Move "Other" to the end
        if ($records)
        {
            foreach($records as $key => $record)
            {
                if ($record->challenge == 'Other')
                {
                    $other = $record;
                    unset($records[$key]);
                }
            }
        }
        
        // If we found it and removed it, append to end
        if ($other)
        {
            $records[$other->id] = $other;
        }
        
        return $records;
        
    }
    
    /**
     * Get the challenges for the student
     * @global \ELBP\Plugins\type $DB
     * @return boolean
     */
    private function getUserChallenges(){
        
        global $DB;
        
        if (!$this->student) return false;
        
        return $DB->get_records_sql("SELECT uc.*, c.challenge
                                     FROM {lbp_user_challenges} uc
                                     INNER JOIN {lbp_challenges} c ON c.id = uc.challengeid
                                     WHERE uc.studentid = ? AND uc.del = 0
                                     ORDER BY c.challenge ASC", array($this->student->id));
        
    }
    
    /**
     * Get a specific challenge record for the student
     * @global \ELBP\Plugins\type $DB
     * @param type $challengeID
     * @return boolean
     */
    public function getUserChallenge($challengeID){
        
        global $DB;
        
        if (!$this->student) return false;
        
        return $DB->get_record("lbp_user_challenges", array("studentid" => $this->student->id, "challengeid" => $challengeID, "del" => 0));
        
    }
    
    
    
    
    /**
     * Get the content for the event triggered emails
     * @global type $CFG
     * @global \ELBP\Plugins\type $USER
     * @return string
     */
    public function getInfoForEventTrigger($challenge)
    {
        global $CFG, $USER;
            
        $output = "";
        
        $output .= "\n----------\n";
        $output .= get_string('student', 'block_elbp') . ": " . fullname($this->getStudent()) . " ({$this->getStudent()->username})\n";
        
        
        if ($challenge)
        {
            
            $userChallenge = $this->getUserChallenge($challenge->id);
            
            if ($userChallenge)
            {
                $output .= $challenge->challenge . " : " . $userChallenge->comments . "\n";
            }
            
        }

        $output .= "----------\n";
        $output .= get_string('updatedby', 'block_elbp') . ": " . fullname($USER) . "\n";
        $output .= get_string('link', 'block_elbp') . ": " . "{$CFG->wwwroot}/blocks/elbp/view.php?id={$this->student->id}\n";

        
        return $output;
        
    }
    
    
    
    
}