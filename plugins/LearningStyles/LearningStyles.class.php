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
class LearningStyles extends Plugin {
    
    const LEARNING_STYLE_AUDITORY = 1;
    const LEARNING_STYLE_VISUAL = 2;
    const LEARNING_STYLE_KINESTHETIC = 3;
    const LEARNING_STYLE_VISUAL_LINGUISTIC = 4;
    const LEARNING_STYLE_VISUAL_SPATIAL = 5;
    
    protected $tables = array(
        'lbp_learning_styles',
        'lbp_learning_style_questions',
        'lbp_learning_style_answers',
        'lbp_learning_style_answer_pt',
        'lbp_user_learn_style_answers'
    );
    
    /**
     * Construct the plugin object
     * @param bool $install If true, we want to send the default info to the parent constructor, to install the record into the DB
     */
    public function __construct($install = false) {
        
        if ($install){
            parent::__construct( array(
                "name" => strip_namespace(get_class($this)),
                "title" => "Learning Styles",
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

        global $DB;
        
        $this->id = $this->createPlugin();
        
        $dbman = $DB->get_manager();
        
        // Install tables if not installed by install.xml

        // Define table lbp_learning_styles to be created
        $table = new \xmldb_table('lbp_learning_styles');

        // Adding fields to table lbp_learning_styles
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('name', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL, null, null);
        $table->add_field('description', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
        $table->add_field('parent', XMLDB_TYPE_INTEGER, '10', null, null, null, null);

        // Adding keys to table lbp_learning_styles
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
        $table->add_key('p_fk', XMLDB_KEY_FOREIGN, array('parent'), 'lbp_learning_styles', array('id'));

        // Adding indexes to table lbp_learning_styles
        $table->add_index('nm_indx', XMLDB_INDEX_NOTUNIQUE, array('name'));

        // Conditionally launch create table for lbp_learning_styles
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }


        
        
        // Define table lbp_user_learning_styles to be created
        $table = new \xmldb_table('lbp_user_learning_styles');

        // Adding fields to table lbp_user_learning_styles
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('learningstyleid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('studentid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('score', XMLDB_TYPE_NUMBER, '5, 2', null, XMLDB_NOTNULL, null, null);

        // Adding keys to table lbp_user_learning_styles
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
        $table->add_key('lsid_fk', XMLDB_KEY_FOREIGN, array('learningstyleid'), 'lbp_learning_styles', array('id'));
        $table->add_key('sid_fk', XMLDB_KEY_FOREIGN, array('studentid'), 'user', array('id'));

        // Adding indexes to table lbp_user_learning_styles
        $table->add_index('lsid_sid_indx', XMLDB_INDEX_NOTUNIQUE, array('learningstyleid', 'studentid'));

        // Conditionally launch create table for lbp_user_learning_styles
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }
        
        
        
        
        // Define table lbp_learning_style_questions to be created
        $table = new \xmldb_table('lbp_learning_style_questions');

        // Adding fields to table lbp_learning_style_questions
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('question', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('ordernum', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);

        // Adding keys to table lbp_learning_style_questions
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));

        // Adding indexes to table lbp_learning_style_questions
        $table->add_index('odr_indx', XMLDB_INDEX_NOTUNIQUE, array('ordernum'));

        // Conditionally launch create table for lbp_learning_style_questions
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        
        
        
        // Define table lbp_learning_style_answers to be created
        $table = new \xmldb_table('lbp_learning_style_answers');

        // Adding fields to table lbp_learning_style_answers
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('questionid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('answer', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);

        // Adding keys to table lbp_learning_style_answers
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
        $table->add_key('qid_fk', XMLDB_KEY_FOREIGN, array('questionid'), 'lbp_learning_style_questions', array('id'));

        // Conditionally launch create table for lbp_learning_style_answers
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }
        
        
        
        
        // Define table lbp_user_learn_style_answers to be created
        $table = new \xmldb_table('lbp_user_learn_style_answers');

        // Adding fields to table lbp_user_learn_style_answers
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('studentid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('questionid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('answerid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);

        // Adding keys to table lbp_user_learn_style_answers
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
        $table->add_key('sid_fk', XMLDB_KEY_FOREIGN, array('studentid'), 'user', array('id'));
        $table->add_key('qid_fk', XMLDB_KEY_FOREIGN, array('questionid'), 'lbp_learning_style_questions', array('id'));
        $table->add_key('aid_fk', XMLDB_KEY_FOREIGN, array('answerid'), 'lbp_learning_style_answers', array('id'));

        // Conditionally launch create table for lbp_user_learn_style_answers
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }
        
        
        
        // Define table lbp_learning_style_answer_pt to be created
        $table = new \xmldb_table('lbp_learning_style_answer_pt');

        // Adding fields to table lbp_learning_style_answer_pt
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('answerid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('learningstyleid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('points', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);

        // Adding keys to table lbp_learning_style_answer_pt
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
        $table->add_key('aid_fk', XMLDB_KEY_FOREIGN, array('answerid'), 'lbp_learning_style_answers', array('id'));
        $table->add_key('sid_fk', XMLDB_KEY_FOREIGN, array('learningstyleid'), 'lbp_learning_styles', array('id'));

        // Conditionally launch create table for lbp_learning_style_answer_pt
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }
        

        
        // Styles
        $obj = new \stdClass();
        $obj->id = self::LEARNING_STYLE_AUDITORY;
        $obj->name = 'Auditory';
        $obj->description = get_string('learningstyles:auditory:desc', 'block_elbp');
        $DB->insert_record("lbp_learning_styles", $obj);
        
        $obj = new \stdClass();
        $obj->id = self::LEARNING_STYLE_VISUAL;
        $obj->name = 'Visual';
        $obj->description = get_string('learningstyles:visual:desc', 'block_elbp');
        $DB->insert_record("lbp_learning_styles", $obj);
        
        $obj = new \stdClass();
        $obj->id = self::LEARNING_STYLE_KINESTHETIC;
        $obj->name = 'Kinesthetic';
        $obj->description = get_string('learningstyles:kinesthetic:desc', 'block_elbp');
        $DB->insert_record("lbp_learning_styles", $obj);
        
        $obj = new \stdClass();
        $obj->id = self::LEARNING_STYLE_VISUAL_LINGUISTIC;
        $obj->name = 'Linguistic';
        $obj->description = get_string('learningstyles:visual:linguistic:desc', 'block_elbp');
        $obj->parent = self::LEARNING_STYLE_VISUAL;
        $DB->insert_record("lbp_learning_styles", $obj);
        
        $obj = new \stdClass();
        $obj->id = self::LEARNING_STYLE_VISUAL_SPATIAL;
        $obj->name = 'Spatial';
        $obj->description = get_string('learningstyles:visual:spatial:desc', 'block_elbp');
        $obj->parent = self::LEARNING_STYLE_VISUAL;
        $DB->insert_record("lbp_learning_styles", $obj);
        
        
        
        // Questions
        $questionArray = array(
                'When seeking travel instructions I prefer to...' => array(
                        'Check a map' => array(
                            self::LEARNING_STYLE_VISUAL => 10
                        ), 'Ask for directions' => array(
                            self::LEARNING_STYLE_AUDITORY => 10
                        ), 'Follow my instincts' => array(
                            self::LEARNING_STYLE_KINESTHETIC => 10
                        )
                ), 'I prefer to spend my spare time...' => array(
                        'Reading books/magazines/etc...' => array(
                            self::LEARNING_STYLE_VISUAL_LINGUISTIC => 10,
                        ), 'Watching television' => array(
                            self::LEARNING_STYLE_VISUAL_SPATIAL => 10
                        ), 'Listening to music' => array(
                            self::LEARNING_STYLE_AUDITORY => 10
                        ), 'Playing computer games' => array(
                            self::LEARNING_STYLE_AUDITORY => 3,
                            self::LEARNING_STYLE_VISUAL_SPATIAL => 3,
                            self::LEARNING_STYLE_KINESTHETIC => 3
                        ), 'Playing sports' => array(
                            self::LEARNING_STYLE_KINESTHETIC => 10
                        )
                ), 'When I anxious I tend to...' => array(
                        'Fidget' => array(
                            self::LEARNING_STYLE_KINESTHETIC => 10
                        ), 'Talk to someone' => array(
                            self::LEARNING_STYLE_AUDITORY => 10
                        ), 'Visualise worst-case scenarios' => array(
                            self::LEARNING_STYLE_VISUAL => 10
                        )
                ), 'When revising for a test I tend to...' => array(
                        'Make lots of notes' => array(
                            self::LEARNING_STYLE_VISUAL_LINGUISTIC => 10
                        ), 'Revise with other people/discuss the subjects' => array(
                            self::LEARNING_STYLE_AUDITORY => 10
                        ), 'Try to create images/scenarios in my head to remember the information' => array(
                            self::LEARNING_STYLE_VISUAL_SPATIAL => 5,
                            self::LEARNING_STYLE_KINESTHETIC => 5
                        )
                ), 'When I am angry I often...' => array(
                        'Replay events over and over in my head/imagine how I might have handled things differently' => array(
                            self::LEARNING_STYLE_VISUAL => 10,
                        ), 'Tell people how I feel/Shout a lot' => array(
                            self::LEARNING_STYLE_AUDITORY => 10
                        ), 'Stomp around/slam doors/throw things/etc...' => array(
                            self::LEARNING_STYLE_KINESTHETIC => 10
                        )
                ), 'I find it easiest to remember...' => array(
                        'Faces' => array(
                            self::LEARNING_STYLE_VISUAL => 10
                        ), 'Names' => array(
                            self::LEARNING_STYLE_AUDITORY => 10
                        ), 'Events' => array(
                            self::LEARNING_STYLE_KINESTHETIC => 10
                        )
                ), 'If I bought a faulty product and decided to complain, I would most likely...' => array(
                        'Write a letter/email' => array(
                            self::LEARNING_STYLE_VISUAL_LINGUISTIC => 10
                        ), 'Phone the customer helpline' => array(
                            self::LEARNING_STYLE_AUDITORY => 10
                        ), 'Take the product back to the shop' => array(
                            self::LEARNING_STYLE_KINESTHETIC => 10
                        )
                ), 'When choosing a dish at a restaurant, I tend to...' => array(
                        'Choose something that looks nice' => array(
                            self::LEARNING_STYLE_VISUAL => 10
                        ), 'Choose something that someone has recommended' => array(
                            self::LEARNING_STYLE_AUDITORY => 10
                        ), 'Choose something that you think will taste nice' => array(
                            self::LEARNING_STYLE_KINESTHETIC => 10
                        )
                ), 'I tend to remember things best by...' => array(
                        'Making lots of notes/reading instructions' => array(
                            self::LEARNING_STYLE_VISUAL_LINGUISTIC => 10
                        ), 'Repeating words/phrases over and over again' => array(
                            self::LEARNING_STYLE_AUDITORY => 10
                        ), 'Doing something or imagining it being done' => array(
                            self::LEARNING_STYLE_KINESTHETIC => 10
                        )
                ), 'When bored I often find myself...' => array(
                        'Drawing/doodling' => array(
                            self::LEARNING_STYLE_VISUAL => 10
                        ), 'Fiddling with things' => array(
                            self::LEARNING_STYLE_KINESTHETIC => 10
                        ), 'Humming/signing to myself' => array(
                            self::LEARNING_STYLE_AUDITORY => 10
                        )
                ), 'On a long journey I prefer to...' => array(
                        'Listen to music/talk to others' => array(
                            self::LEARNING_STYLE_AUDITORY => 10
                        ), 'Watch the scenery go by/read a book' => array(
                            self::LEARNING_STYLE_VISUAL => 10
                        ), 'Get out of the car whenever possible to move about/stretch' => array(
                            self::LEARNING_STYLE_KINESTHETIC => 10
                        )
                ), 'In class I get easily distracted by...' => array(
                        'Seeing something interesting' => array(
                            self::LEARNING_STYLE_VISUAL => 10
                        ), 'Talking to my friends' => array(
                            self::LEARNING_STYLE_AUDITORY => 10
                        ), 'Having to sit still and concentrate for long periods' => array(
                            self::LEARNING_STYLE_KINESTHETIC => 10
                        )
                ), 'If I were to do a puzzle, I would prefer a...' => array(
                        'Word search/Cross word' => array(
                            self::LEARNING_STYLE_VISUAL_LINGUISTIC => 10
                        ), 'Spot the difference' => array(
                            self::LEARNING_STYLE_VISUAL_SPATIAL => 10
                        ), 'Pub quiz' => array(
                            self::LEARNING_STYLE_AUDITORY => 10
                        ), 'Rubiks Cube' => array(
                            self::LEARNING_STYLE_KINESTHETIC => 10
                        )
                ), 'When cooking something new I prefer to...' => array(
                        'Follow a written recipe' => array(
                            self::LEARNING_STYLE_VISUAL_LINGUISTIC => 10
                        ), 'Watch someone else and copy what they do' => array(
                            self::LEARNING_STYLE_VISUAL_SPATIAL => 10
                        ), 'Follow verbal instructions' => array(
                            self::LEARNING_STYLE_AUDITORY => 10
                        ), 'Follow my instincts and test/taste as I go' => array(
                            self::LEARNING_STYLE_KINESTHETIC => 10
                        )
                ), 'I am often drawn to people by...' => array(
                        'What they look like' => array(
                            self::LEARNING_STYLE_VISUAL => 10
                        ), 'What they say' => array(
                            self::LEARNING_STYLE_AUDITORY => 10
                        ), 'How they make me feel' => array(
                            self::LEARNING_STYLE_KINESTHETIC => 10
                        )
                )
        );
        
        $i = 1;
        
        foreach($questionArray as $question => $answers)
        {
            
            $obj = new \stdClass();
            $obj->question = $question;
            $obj->ordernum = $i;
            
            $questionID = $DB->insert_record("lbp_learning_style_questions", $obj);
            
            foreach($answers as $answer => $points)
            {
                
                $obj = new \stdClass();
                $obj->questionid = $questionID;
                $obj->answer = $answer;
                $answerID = $DB->insert_record("lbp_learning_style_answers", $obj);
                
                foreach($points as $styleID => $score)
                {
                    
                    $obj = new \stdClass();
                    $obj->answerid = $answerID;
                    $obj->learningstyleid = $styleID;
                    $obj->points = $score;
                    $DB->insert_record("lbp_learning_style_answer_pt", $obj);
                    
                }
                
            }
            
            $i++;
            
        }
        
        
        
        
        
        
        
        return true;
    }
    
    /**
     * Truncate related tables and then uninstall plugin
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
        
        $result = true;
        $version = $this->version; # This is the current DB version we will be using to upgrade from     
        
        // [Upgrades here]
        
    }
    
    /**
     * Load the summary box
     * @return type
     */
    public function getSummaryBox(){
        
        $TPL = new \ELBP\Template();
        
        $TPL->set("obj", $this);
        $TPL->set("access", $this->access);      
        $TPL->set("scores", $this->calculateScores());
        $TPL->set("styles", $this->getLearningStyles());
                
        try {
            return $TPL->load($this->CFG->dirroot . '/blocks/elbp/plugins/'.$this->name.'/tpl/summary.html');
        }
        catch (\ELBP\ELBPException $e){
            return $e->getException();
        }
        
    }
    
    /**
     * Get the content for the summary box
     * @param type $params
     * @return type
     */
    public function getDisplay($params = array()) {
        
        $output = "";
        
        $TPL = new \ELBP\Template();
        $TPL->set("obj", $this);
        $TPL->set("access", $this->access);      
        $TPL->set("params", $params);
        $TPL->set("styles", $this->getLearningStyles());
        $TPL->set("scores", $this->calculateScores());
        $TPL->set("answers", $this->getListUserAnswers());
        
        try {
            $output .= $TPL->load($this->CFG->dirroot . '/blocks/elbp/plugins/'.$this->name.'/tpl/expanded.html');
        } catch (\ELBP\ELBPException $e){
            $output .= $e->getException();
        }

        return $output;
        
    }
    
    /**
     * Handle ajax requests sent to the plugin
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
                
                if ($params['type'] == 'questionnaire'){
                    $TPL->set("questions", $this->getQuestions());
                } elseif($params['type'] == 'expanded'){
                    $TPL->set("styles", $this->getLearningStyles());
                    $TPL->set("scores", $this->calculateScores());
                    $TPL->set("answers", $this->getListUserAnswers());
                }
                                
                try {
                    $TPL->load( $this->CFG->dirroot . '/blocks/elbp/plugins/'.$this->name.'/tpl/'.$params['type'].'.html' );
                    $TPL->display();
                } catch (\ELBP\ELBPException $e){
                    echo $e->getException();
                }
                exit;                
                
            break;
            
            case 'save':
                
                // Correct params are set?
                if (!$params || !isset($params['studentID']) || !$this->loadStudent($params['studentID'])) return false;
                
                // We have the permission to do this?
                $access = $ELBP->getUserPermissions($params['studentID']);
                if (!$ELBP->anyPermissionsTrue($access)) return false;
                
                $questions = $this->getQuestions();
                
                $numQuestions = count($questions);
                $numAnswered = 0;
                
                if ($questions)
                {
                    foreach($questions as $question)
                    {
                        if (isset($params['answer_q'.$question->id]) && !empty($params['answer_q'.$question->id]))
                        {
                            $numAnswered++;
                        }
                    }
                }
                
                // If not answered them all, stop
                if ($numQuestions <> $numAnswered){
                    echo "$('#elbp_learning_styles_output').html('<div class=\"elbp_err_box\"><span>".get_string('notallquestionsanswered', 'block_elbp')."</span></div>');";
                    exit;
                }
                
                // Clear previous answers/results
                $DB->delete_records("lbp_user_learn_style_answers", array("studentid" => $this->student->id));
                $DB->delete_records("lbp_user_learning_styles", array("studentid" => $this->student->id));
                
                // Insert answers
                if ($questions)
                {
                    foreach($questions as $question)
                    {
                        
                        $obj = new \stdClass();
                        $obj->studentid = $this->student->id;
                        $obj->questionid = $question->id;
                        $obj->answerid = $params['answer_q'.$question->id];
                        $DB->insert_record("lbp_user_learn_style_answers", $obj);
                        
                    }
                }
                                
                // Insert last taken attribute
                $this->updateSetting('last_taken', time(), $this->student->id);
                
                echo "$('#elbp_learning_styles_output').html('<div class=\"elbp_success_box\">".get_string('answerssaved', 'block_elbp')." ".get_string('loadingyourresults', 'block_elbp')."</div>');";
                echo "ELBP.LearningStyles.load_display('expanded');";
                exit;
                
                                
            break;
            
        }
        
    }
    
    /**
     * Calculate score based on answers given
     * @global \ELBP\Plugins\type $DB
     * @return boolean
     */
    private function calculateScores(){
        
        global $DB;
        
        if (!$this->student) return false;
        
        $answers = $DB->get_records_sql("SELECT p.*
                                         FROM {lbp_user_learn_style_answers} ua
                                         INNER JOIN {lbp_learning_style_answers} a ON a.id = ua.answerid
                                         INNER JOIN {lbp_learning_style_answer_pt} p ON p.answerid = a.id
                                         WHERE ua.studentid = ?", array($this->student->id));
        
        if (!$answers) return false;
        
        $allStyles = $this->getFlatLearningStyles();
        $results = array();
        $parents = array();
        
        $results['total'] = 0;
        
        if ($allStyles)
        {
            foreach($allStyles as $style)
            {
                $results[$style->id] = 0;
                if (!is_null($style->parent))
                {
                    $parents[$style->id] = $style->parent;
                }
            }
        }
        
        
        // Add up totals
        if ($answers)
        {
            foreach($answers as $answer)
            {
                
                // Add points to the total of this learning style
                $results[$answer->learningstyleid] += $answer->points;
                $results['total'] += $answer->points;
                
                // If this style has a parent, add to that as well
                if (isset($parents[$answer->learningstyleid]))
                {
                    $results[$parents[$answer->learningstyleid]] += $answer->points;
                    if (!isset($results['total:'.$parents[$answer->learningstyleid]]))
                    {
                        $results['total:'.$parents[$answer->learningstyleid]] = 0;
                    }
                    $results['total:'.$parents[$answer->learningstyleid]] += $answer->points;
                }
                
            }
        }
                
        return $results;        
        
    }
    
    /**
     * Get all the learning styles out of the db
     * @global \ELBP\Plugins\type $DB
     * @return type
     */
    public function getFlatLearningStyles(){
        global $DB;
        return $DB->get_records("lbp_learning_styles", null, "id ASC");
    }
    
    /**
     * Get the learning styles out of the db, including parent/child relationships
     * @global \ELBP\Plugins\type $DB
     * @return type
     */
    public function getLearningStyles(){
        
        global $DB;
        
        $return = array();
        
        $styles = $DB->get_records("lbp_learning_styles", array("parent" => null));
        if ($styles)
        {
            foreach($styles as $style)
            {
                
                // Check for children
                $children = $DB->get_records("lbp_learning_styles", array("parent" => $style->id));
                if ($children){
                    $style->children = $children;
                }
                
                $return[$style->id] = $style;
                
            }
        }
        
        return $return;
        
        
    }
    
    /**
     * Get all the questions out of the db
     * @global \ELBP\Plugins\type $DB
     * @return type
     */
    public function getQuestions(){
        
        global $DB;
        
        $questions = $DB->get_records("lbp_learning_style_questions", null, "ordernum ASC");
        
        if ($questions)
        {
            
            foreach($questions as $question)
            {
                
                $question->answers = $this->getAnswers($question->id);
                
            }
            
        }
        
        return $questions;
        
    }
    
    /**
     * Get a list of the questions and the student's answers to them
     * @global \ELBP\Plugins\type $DB
     * @return \stdClass|boolean
     */
    public function getListUserAnswers(){
        
        global $DB;
        
        $records = $DB->get_records_sql("SELECT p.id, a.questionid, a.answer, s.name, p.points, q.id as qnum, q.question
                                        FROM {lbp_user_learn_style_answers} ua
                                        INNER JOIN {lbp_learning_style_answers} a ON a.id = ua.answerid
                                        INNER JOIN {lbp_learning_style_questions} q ON q.id = a.questionid
                                        INNER JOIN {lbp_learning_style_answer_pt} p ON p.answerid = a.id
                                        INNER JOIN {lbp_learning_styles} s ON s.id = p.learningstyleid
                                        WHERE ua.studentid = ?
                                        ORDER BY q.id ASC", array($this->student->id));
        
        if (!$records) return false;
        
        $answers = array();
        
        foreach($records as $record)
        {
            
            if (!isset($answers[$record->questionid])){
                $answers[$record->questionid] = new \stdClass();
            }
            
            if (!isset($answers[$record->questionid]->styles)){
                $answers[$record->questionid]->styles = array();
            }
            
            $answers[$record->questionid]->qnum = $record->qnum;
            $answers[$record->questionid]->question = $record->question;
            $answers[$record->questionid]->answer = $record->answer;
            $answers[$record->questionid]->styles[] = $record->name;
            
        }
        
        
        return $answers;
        
        
        
    }
    
    /**
     * Get the relevant points for each answer the student gave
     * @global \ELBP\Plugins\type $DB
     * @param type $answerID
     * @return string
     */
    public function getAnswerPoints($answerID){
        
        global $DB;
        
        $return = array();
        
        $records = $DB->get_records_sql("SELECT pt.*, s.name
                                         FROM {lbp_learning_style_answer_pt} pt
                                         INNER JOIN {lbp_learning_styles} s ON s.id = pt.learningstyleid
                                         WHERE pt.answerid = ?", array($answerID));
        
        if ($records)
        {
            foreach($records as $record)
            {
                
                $return[] = $record->name . " ({$record->points})";
                
            }
        }
                
        return $return;
        
        
    }
    
    /**
     * Get the possible answers to a question
     * @global \ELBP\Plugins\type $DB
     * @param type $questionID
     * @return type
     */
    public function getAnswers($questionID){
        
        global $DB;
        
        $records = $DB->get_records("lbp_learning_style_answers", array("questionid" => $questionID));
        
        $answers = array();
        
        if ($records)
        {
            foreach($records as $record)
            {
                $answers[] = $record;
            }
        }
        
        shuffle($answers);
                
        return $answers;
        
    }
    
    
    /**
     * Check if the user has taken the questionnaire
     * @return type
     */
    public function userHasResults(){
                
        return ($this->getSetting('last_taken', $this->student->id) != false);
        
    }
    
    /**
     * Get the date the student last took the questionnaire
     * @return type
     */
    public function getLastTakenDate(){
        
        $unix = $this->getSetting('last_taken', $this->student->id);
        
        return ($unix > 0) ? date('D jS M Y, H:i', $unix) : false;
        
    }
    
    
}