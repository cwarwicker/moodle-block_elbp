<?php
/**
 * Comments Plugin
 * 
 * @copyright 2014 Bedford College
 * @package Bedford College Electronic Learning Blue Print (ELBP)
 * @version 1.0
 * @author Conn Warwicker <cwarwicker@bedford.ac.uk> <conn@cmrwarwicker.com>
 * 
 *  Licensed under the Apache License, Version 2.0 (the "License");
 *  you may not use this file except in compliance with the License.
 *  You may obtain a copy of the License at
 *
 *  http://www.apache.org/licenses/LICENSE-2.0
 *
 *  Unless required by applicable law or agreed to in writing, software
 *  distributed under the License is distributed on an "AS IS" BASIS,
 *  WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 *  See the License for the specific language governing permissions and
 *  limitations under the License.
 */

namespace ELBP\Plugins;

require_once 'Comment.class.php';

/**
 * 
 */
class Comments extends Plugin {
    
    protected $tables = array(
        'lbp_comments',
        'lbp_comment_attributes'
    );
    
    /**
     * Construct the plugin object
     * @param bool $install If true, we want to send the default info to the parent constructor, to install the record into the DB
     */
    public function __construct($install = false) {
        
        if ($install){
            parent::__construct( array(
                "name" => strip_namespace(get_class($this)),
                "title" => "Comments",
                "path" => null,
                "version" => \ELBP\ELBP::getBlockVersionStatic()
            ) );
        }
        else
        {
            parent::__construct( strip_namespace(get_class($this)) );
            $this->loadDefaultAttributes();
        }

    }
    
    /**
     * Yes
     * @return boolean
     */
    public function isUsingBlockProgress(){
        return true;
    }
    
    /**
     * Display config stuff
     */
    public function displayConfig() {
        parent::displayConfig();
        
        $output = "";
        
        $output .= "<h2>".get_string('commentsconfig', 'block_elbp')."</h2>";
        
        $output .= "<small><strong>".get_string('limitsummarylist', 'block_elbp')."</strong> - ".get_string('limitsummarylist:desc', 'block_elbp')."</small><br>";
        $output .= "<input class='elbp_small' type='type' name='limit_summary_list' value='{$this->getSetting('limit_summary_list')}' />";
        
        echo $output;
        
    }
    
    /**
     * Get the img path for the positive icon
     * @global type $CFG
     * @return type
     */
    public function getPositiveIconImage(){
        
        global $CFG;
        $img = $this->getSetting('positive_icon');
        return ($img && file_exists($CFG->dirroot . '/blocks/elbp/plugins/Comments/pix/' . $img)) ? $img : 'positive.png';
        
    }
    
    /**
     * Get the img path for the negative icon
     * @global \ELBP\Plugins\type $CFG
     * @return type
     */
    public function getNegativeIconImage(){
         
        global $CFG;
        $img = $this->getSetting('negative_icon');
        return ($img && file_exists($CFG->dirroot . '/blocks/elbp/plugins/Comments/pix/' . $img)) ? $img : 'negative.png';
        
    }
    
    /**
     * Get the comment categories
     * @return type
     */
    public function getCategories()
    {
        
        $categories = explode("\n", $this->getSetting('comment_categories'));
        return $categories;
        
    }
    
    public function getUserCommentsPublishedToPortal($limit = null){
        
        $comments = $this->getUserComments($limit);
        $return = array();
        
        if ($comments)
        {
            foreach($comments as $comment)
            {
                if ($comment->isPublished())
                {
                    $return[] = $comment;
                }
            }
        }
        
        return $return;
        
    }
        
    /**
     * Get the comments for a user
     * @global type $USER
     * @param type $limit
     * @return \ELBP\Plugins\Comments\Comment|boolean
     */
    public function getUserComments($limit = null)
    {
                
        global $USER;
        
        if (!$this->student) return false;
        
        // Academic Year
        $academicYearUnix = $this->getAcademicYearUnix();
        
        $results = array();
        $records = $this->DB->get_records('lbp_comments', array("studentid" => $this->student->id, "del" => 0), "commentdate DESC, id DESC", "id", 0, $limit);
                
        if ($records)
        {
            foreach($records as $record)
            {
                
                $obj = new \ELBP\Plugins\Comments\Comment($record->id);
                
                if ($obj->isValid())
                {
                    
                    if ($academicYearUnix && $obj->getSetTime() < $academicYearUnix){
                        continue;
                    }
                    
                    // If is hidden and we are the student, skip it
                    if ($USER->id == $this->student->id && $obj->isHidden()){
                        continue;
                    }
                    
                    $obj->setCommentsObj($this);
                    $results[] = $obj;
                    
                }
            }
        }
        
        return $results;
        
    }
    
    
    
    /**
     * 
     * @global type $ELBP
     * @param type $sessionID
     * @return boolean
     */
    public function printOut($commentID = null, $studentID = null)
    {
        
        global $ELBP;
                
        if (!is_null($commentID))
        {
         
            if (is_numeric($commentID))
            {
            
                $comment = new \ELBP\Plugins\Comments\Comment($commentID);
                if (!$comment->isValid()){
                    return false;
                }

                // Get our access for the student who this belongs to
                $access = $ELBP->getUserPermissions( $comment->getStudentID() );
                if (!elbp_has_capability('block/elbp:print_comment', $access)){
                    echo get_string('invalidaccess', 'block_elbp');
                    return false;
                }

                // Carry on
                $comment->setCommentsObj($this);
                $comment->printOut();
                return true;
            
            }
            elseif ($commentID == 'all' && !is_null($studentID))
            {
                
                
                // Get our access for the student who this belongs to
                $access = $ELBP->getUserPermissions( $studentID );
                if (!elbp_has_capability('block/elbp:print_comment', $access)){
                    echo get_string('invalidaccess', 'block_elbp');
                    return false;
                }
                
                $this->loadStudent($studentID);
                $comments = $this->getUserComments();
                $this->printOutAll($comments);
                return true;
                
                
            }
            
        }
        
    }
    
    /**
     * Print of an array of incidents on one page
     * @param type $incidents
     */
    private function printOutAll($comments)
    {
        
        global $CFG, $USER;
                                
        
        $attributes = $this->getAttributesForDisplay();
        
        $pageTitle = fullname($this->getStudent()) . ' (' . $this->student->username . ') - ' . get_string('comments', 'block_elbp');
        $title = get_string('comments', 'block_elbp');
        $heading = fullname($this->getStudent()) . ' (' . $this->student->username . ')';
        $logo = \ELBP\ELBP::getPrintLogo();
        
        $txt = "";
                        
        if ($comments)
        {
            
            foreach($comments as $comment)
            {
                
                $comment->loadObjectIntoAttributes($attributes);
                
                $txt .= "<table class='info'>";

                    if ($comment->getPositive() == 1) $posNeg = get_string('positive', 'block_elbp');
                    elseif ($comment->getPositive() == -1) $posNeg = get_string('negative', 'block_elbp');
                    else $posNeg = get_string('na', 'block_elbp');

                    $resolved = ($comment->isResolved()) ? get_string('resolved', 'block_elbp') : get_string('unresolved', 'block_elbp');

                    $txt .= "<tr><td colspan='3'>".$comment->getDate('D jS M Y')."</td></tr>";
                    $txt .= "<tr><td>".get_string('setby', 'block_elbp').": ".$comment->getSetByUserFullName()."</td><td>".get_string('dateset', 'block_elbp').": ".$comment->getSetDate('D jS M Y')."</td></tr>";
                    $txt .= "<tr><td>{$posNeg}</td><td>{$resolved}</td></tr>";
                    
                    // Side attributes
                    $sideAttributes = $this->getAttributesForDisplayDisplayType("side", $attributes);
                    if ($sideAttributes)
                    {
                        $n = 0;
                        $num = 0;
                        $cnt = count($sideAttributes);

                        foreach($sideAttributes as $attribute)
                        {

                            $n++;
                            $num++;

                            if ($n == 1){
                                $txt .= "<tr num='{$n}'>";
                            }

                            $txt .= "<td>{$attribute->name}: ".$attribute->displayValue(true). "</td>";

                            if ($n == 2 || $num == $cnt){
                                $txt .= "</tr>";
                                $n = 0;
                            }

                        }
                    }

                $txt .= "</table>";

                $txt .= "<hr>";


                // Main central elements
                $mainAttributes = $this->getAttributesForDisplayDisplayType("main", $attributes);

                if ($mainAttributes)
                {

                    foreach($mainAttributes as $attribute)
                    {
                        $txt .= "<div class='attribute-main'><p class='b'>{$attribute->name}</p><p>".$attribute->displayValue(true)."</p></div>";
                    }
                }

                $txt .= "<br><div class='divider'></div><br><br>";
                
            }
            
        }

        
        $TPL = new \ELBP\Template();
        $TPL->set("logo", $logo);
        $TPL->set("pageTitle", $pageTitle);
        $TPL->set("title", $title);
        $TPL->set("heading", $heading);
        $TPL->set("content", $txt);
        
        
        $TPL->load( $CFG->dirroot . '/blocks/elbp/tpl/print.html' );
        $TPL->display();
        exit;
        
    }
    
    
     /**
     * Install the plugin
     */
    public function install()
    {
        global $DB;
        
        $return = true;
        $pluginID = $this->createPlugin();
        $return = $return && $pluginID;
        
        // This is a core ELBP plugin, so the extra tables it requires are handled by the core ELBP install.xml
        
        
        // Default settings
        $settings = array();
        $settings['limit_summary_list'] = 5;
        $settings['attributes'] = '';
        $settings['incident_title_attribute'] = 'Category';
        $settings['block_progress_bars_enabled'] = 1;
        $settings['attributes'] = '[[Select: |name = "Category"| |label = "Category"| |options = {name = "Academic Performance", value = "Academic Performance"}{name = "Behaviour", value = "Behaviour"}{name = "Attendance", value = "Attendance"}{name = "Punctuality", value = "Punctuality"}{name = "Other", value = "Other"}|| |validation = {type = "NOT_EMPTY"}| |display = "main"]]
[[Textbox: |name = "Description"| |label = "Description"|| |validation = {type = "NOT_EMPTY"}| |display = "main"]]
[[Textbox: |name = "Action Taken"| |label = "Action Taken"|| |display = "main"]]';
        
        
        // Not 100% required on install, so don't return false if these fail
        foreach ($settings as $setting => $value){
            $DB->insert_record("lbp_settings", array("pluginid" => $pluginID, "setting" => $setting, "value" => $value));
        }
        
        // Alert events
        $DB->insert_record("lbp_alert_events", array("pluginid" => $pluginID, "name" => "Comment Added", "description" => "A new comment is added into the system", "auto" => 0, "enabled" => 1));
        $DB->insert_record("lbp_alert_events", array("pluginid" => $pluginID, "name" => "Comment Updated", "description" => "A comment is updated", "auto" => 0, "enabled" => 1));
        
        return $return;
        
    }
    
    /**
     * Wipe data from related tables and then uninstall plugin
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
        
        global $DB;
        
        $version = $this->version; # This is the current DB version we will be using to upgrade from     
        $dbman = $DB->get_manager();
        
        // [Upgrades here]
        if ($version < 2014030300)
        {
            
            
            // Rename table
            $table = new \xmldb_table('lbp_incidents');
            
            if ($dbman->table_exists($table))
            {

                // Launch rename table for lbp_comments
                $dbman->rename_table($table, 'lbp_comments');


                // Rename incidentdate to commentdate
                $table = new \xmldb_table('lbp_comments');
                $field = new \xmldb_field('incidentdate', XMLDB_TYPE_INTEGER, '20', null, XMLDB_NOTNULL, null, null, 'studentid');

                // Launch rename field commentdate
                $dbman->rename_field($table, $field, 'commentdate');
            
            }
            
            
            
            
            // Rename attributes table
            $table = new \xmldb_table('lbp_incident_attributes');
            
            if ($dbman->table_exists($table))
            {

                // Launch rename table for lbp_comments
                $dbman->rename_table($table, 'lbp_comment_attributes');

                $table = new \xmldb_table('lbp_comment_attributes');
                
                // Remove index if
                $index = new \xmldb_index('if', XMLDB_INDEX_NOTUNIQUE, array('incidentid', 'field'));

                // Conditionally launch drop index name-ind
                if ($dbman->index_exists($table, $index)) {
                    $dbman->drop_index($table, $index);
                }

                // Remove key iid_fk
                $key = new \xmldb_key('iid_fk', XMLDB_KEY_FOREIGN, array('incidentid'));
                $dbman->drop_key($table, $key);

                // Rename field
                $field = new \xmldb_field('incidentid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null, 'id');

                // Launch rename field commentid
                $dbman->rename_field($table, $field, 'commentid');
            
            }
            
            
            
            
            
            
            
            
            // Add key & index back to attributes table
            $table = new \xmldb_table('lbp_comment_attributes');
            $key = new \xmldb_key('cid_fk', XMLDB_KEY_FOREIGN, array('commentid'), 'lbp_comments', array('id'));

            // Launch add key cid_fk
            $dbman->add_key($table, $key);
            
            
            $index = new \xmldb_index('cid_fld_indx', XMLDB_INDEX_NOTUNIQUE, array('commentid', 'field'));

            // Conditionally launch add index cid_fld_indx
            if (!$dbman->index_exists($table, $index)) {
                $dbman->add_index($table, $index);
            }
            
            
            $this->version = 2014030300;
            $this->updatePlugin();
            \mtrace("## Altered some indexes/keys/table names/field names for plugin: {$this->title}");
            
        }
        
        
        if ($this->version < 2014042500)
        {
            
            // Update alert event names
            $alertEvents = $DB->get_records("lbp_alert_events", array("pluginid" => $this->id));
            if ($alertEvents)
            {
                foreach($alertEvents as $alertEvent)
                {
                    $alertEvent->name = str_replace("Incident", "Comment", $alertEvent->name);
                    $DB->update_record("lbp_alert_events", $alertEvent);
                }
            }
            $this->version = 2014042500;
            $this->updatePlugin();
            \mtrace("## Altered name of alert events for plugin: {$this->title}");
            
        }
        
        if ($this->version < 2014092600) {

            // Define field published to be added to lbp_comments.
            $table = new \xmldb_table('lbp_comments');
            $field = new \xmldb_field('published', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'hidden');

            // Conditionally launch add field published.
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }

            // Elbp savepoint reached.
            $this->version = 2014092600;
            $this->updatePlugin();
            \mtrace("## Added `published` column to `lbp_comments` table for plugin {$this->title}");
            
        }
        
        
        
    }
    
    /**
     * Load the summary box
     * @return type
     */
    public function getSummaryBox(){
        
        $TPL = new \ELBP\Template();
        
        $listLimit = $this->getSetting('limit_summary_list');
        $comments = $this->getUserComments($listLimit);
        $titleAttribute = $this->getSetting('comment_title_attribute');
        
        $TPL->set("comments", $comments);
        $TPL->set("obj", $this);
        $TPL->set("titleAttribute", $titleAttribute);
                
        try {
            return $TPL->load($this->CFG->dirroot . '/blocks/elbp/plugins/'.$this->name.'/tpl/summary.html');
        }
        catch (\ELBP\ELBPException $e){
            return $e->getException();
        }
        
    }
    
    /**
     * Get which attribute we are using as a title for each comment
     * @return type
     */
    public function getTitleAttribute(){
        return $this->getSetting('comment_title_attribute');
    }
    
    /**
     * Handle AJAX requests sent to the plugin.
     * @global \ELBP\Plugins\type $CFG
     * @param type $action
     * @param int $params
     * @param \ELBP\Plugins\type $ELBP
     * @return boolean
     */
    public function ajax($action, $params, $ELBP){
                
        global $CFG;
        
        switch($action)
        {
            
            case 'load_display_type':
                                
                // Correct params are set?
                if (!$params || !isset($params['studentID']) || !$this->loadStudent($params['studentID'])) return false;
                
                // We have the permission to do this?
                $access = $ELBP->getUserPermissions($params['studentID']);
                if (!$ELBP->anyPermissionsTrue($access)) return false;
                
                if (!isset($params['type'])) return false;
                $page = $params['type'];
                                
                                
                $TPL = new \ELBP\Template();
                $TPL->set("obj", $this)
                    ->set("access", $access)
                    ->set("page", $page)
                    ->set("comments", $this->getUserComments())
                    ->set("title_attribute", $this->getSetting('comment_title_attribute'));
                
                
                 $commentID = false;
                
                 if ($page == 'edit'){
                     $commentID = $params['commentID'];
                     $page = 'new'; # Use the same form, just check for different capabilities
                 }
                 
                 // if new or edit target need the data
                 if ($page == 'new'){
                     $FORM = new \ELBP\ELBPForm();
                     $FORM->loadStudentID($this->student->id);
                     $TPL->set("data", \ELBP\Plugins\Comments\Comment::getDataForNewCommentForm($commentID));
                     $TPL->set("attributes", $this->getAttributesForDisplay());
                     $TPL->set("FORM", $FORM);
                     
                     $parentPortalInstalled = $ELBP->getPlugin("elbp_portal");
                     $parentPortalInstalled = ($parentPortalInstalled) ? true : false;
                     $TPL->set("parentPortalInstalled", $parentPortalInstalled);
                     
                 }
                
                
                try {
                    $TPL->load( $this->CFG->dirroot . '/blocks/elbp/plugins/'.$this->name.'/tpl/'.$page.'.html' );
                    $TPL->display();
                } catch (\ELBP\ELBPException $e){
                    echo $e->getException();
                }
                exit;                
                
            break;
            
            case 'save_comment':
                
                if (!$params || !isset($params['studentID']) || !$this->loadStudent($params['studentID'])) return false;
                                
                // We have the permission to do this?
                $access = $ELBP->getUserPermissions($params['studentID']);
                if (!$ELBP->anyPermissionsTrue($access)) return false;
                if (!elbp_has_capability('block/elbp:add_comment', $access)) return false;                
                                
                $comment = new \ELBP\Plugins\Comments\Comment($params);
                $comment->setCommentsObj($this);
                
                // If the record exists, check to make sure the student ID on it is the same as the one we specified
                if ($comment->getID() > 0 && $comment->getStudentID() <> $params['studentID']) return false;
                
                // Failed to save for some reason
                if (!$comment->save()){
                                                            
                    echo "$('#new_comment_output').html('<div class=\"elbp_err_box\" id=\"add_comment_errors\"></div>');";
                    
                    foreach($comment->getErrors() as $error){
                        
                        echo "$('#add_comment_errors').append('<span>{$error}</span><br>');";
                        
                    }
                    
                    exit;
                    
                }
                
                // Saved OK
                echo "$('#new_comment_output').html('<div class=\"elbp_success_box\" id=\"add_comment_success\"></div>');";
                echo "$('#add_comment_success').append('<span>".get_string('commentsaved', 'block_elbp')."</span><br>');";
                
                if ($params['comment_id'] <= 0){
                    echo "$('#new_comment_form')[0].reset();";
                }
                                
                
                exit;
                
                
            break;
            
            case 'delete_comment':
                
                if (!$params || !isset($params['studentID']) || !$this->loadStudent($params['studentID']) || !isset($params['commentID'])) return false;
                
                // We have the permission to do this?
                $access = $ELBP->getUserPermissions($params['studentID']);
                if (!$ELBP->anyPermissionsTrue($access)) return false;
                if (!elbp_has_capability('block/elbp:delete_comment', $access)) return false;                
                                
                $comment = new \ELBP\Plugins\Comments\Comment($params['commentID']);
                $comment->setCommentsObj($this);
                
                // If the record exists, check to make sure the student ID on it is the same as the one we specified
                if ($comment->getID() > 0 && $comment->getStudentID() <> $params['studentID']) return false;
                
                if (!$comment->delete()){
                    echo "$('#elbp_comments_output').html('<div class=\"elbp_err_box\" id=\"generic_err_box\"></div>');";
                    echo "$('#generic_err_box').append('<span>".get_string('errors:couldnotupdaterecord', 'block_elbp')."</span><br>');";
                    exit;
                }
                
                echo "$('#elbp_comments_output').html('<div class=\"elbp_success_box\" id=\"generic_success_box\"></div>');";
                echo "$('#generic_success_box').append('<span>".get_string('commentdeleted', 'block_elbp')."</span><br>');";
                echo "$('#elbp_comment_{$comment->getID()}').remove();";
                
                
            break;
            
            case 'mark_resolved':
                                
                if (!$params || !isset($params['studentID']) || !isset($params['commentID']) || !$this->loadStudent($params['studentID'])) return false;
                
                // We have the permission to do this?
                $access = $ELBP->getUserPermissions($params['studentID']);
                if (!$ELBP->anyPermissionsTrue($access)) return false;
                if (!elbp_has_capability('block/elbp:mark_comment_resolved', $access)) return false;    
                
                                
                $comment = new \ELBP\Plugins\Comments\Comment($params['commentID']);
                $comment->setCommentsObj($this);
                
                // If the record exists, check to make sure the student ID on it is the same as the one we specified
                if ($comment->getID() > 0 && $comment->getStudentID() <> $params['studentID']) return false;
                
                if ($params['val'] != 1 && $params['val'] != 0) $params['val'] = 0;
                
                // Failed to save for some reason
                if (!$comment->resolve($params['val'])){
                                                            
                    echo "$('#elbp_comments_output').html('<div class=\"elbp_err_box\" id=\"add_comment_errors\"></div>');";
                    
                    foreach($comment->getErrors() as $error){
                        
                        echo "$('#add_comment_errors').append('<span>{$error}</span><br>');";
                        
                    }
                    
                    exit;
                    
                }
                
                // Saved OK
                echo "$('#elbp_comments_output').html('<div class=\"elbp_success_box\" id=\"add_comment_success\"></div>');";
                echo "$('#add_comment_success').append('<span>".get_string('commentsaved', 'block_elbp')."</span><br>');";
                echo "$('#object_icon_comment_{$comment->getID()}').attr('src', '{$CFG->wwwroot}/blocks/elbp/pix/{$comment->getResolvedImage()}.png');";
                
                if ($comment->isResolved()){
                    echo "$('#resolve_link_resolve_{$comment->getID()}').hide();";
                    echo "$('#resolve_link_unresolve_{$comment->getID()}').show();";
                } else {
                    echo "$('#resolve_link_unresolve_{$comment->getID()}').hide();";
                    echo "$('#resolve_link_resolve_{$comment->getID()}').show();";
                }
                
                exit;
                
                
            break;
            
            
            
        }
        
    }
    
    /**
     * Save the configuration
     * @global \ELBP\Plugins\type $CFG
     * @global type $MSGS
     * @param type $settings
     * @return boolean
     */
     public function saveConfig($settings) {
        
        global $CFG, $MSGS;
      
        if (isset($settings['saveCategories'])){
            $settings['comment_categories'] = implode("\n", $settings['comment_categories']);
            $this->updateSetting('comment_categories', $settings['comment_categories']);
            $MSGS['success'] = get_string('categoriesupdated', 'block_elbp');
            return true;
        }
        
        elseif(isset($_POST['submit_attributes']))
        {
            \elbp_save_attribute_script($this);
            return true;
        }
        
        elseif(isset($_POST['submit_title_attribute']))
        {
            if (!empty($settings['title_attribute'])){
                $this->updateSetting('comment_title_attribute', $settings['title_attribute']);
                $MSGS['success'] = get_string('attributesupdated', 'block_elbp');            
            }
            return true;
        }
        
        elseif (isset($_POST['submit_icons']))
        {
            
            // Positive
            if (isset($_FILES['positive_icon']) && $_FILES['positive_icon']['error'] == 0){
                
                 $fInfo = finfo_open(FILEINFO_MIME_TYPE);
                 $mime = finfo_file($fInfo, $_FILES['positive_icon']['tmp_name']);
                 finfo_close($fInfo);
                
                 $array = array('image/bmp', 'image/gif', 'image/jpeg', 'image/png', 'image/tiff', 'image/pjpeg');
                 if (in_array($mime, $array))
                 {
                      $_FILES['positive_icon']['name'] = "custom/" . $_FILES['positive_icon']['name'];
                      $result = move_uploaded_file($_FILES['positive_icon']['tmp_name'], $CFG->dirroot . '/blocks/elbp/plugins/Comments/pix/' . $_FILES['positive_icon']['name']);
                      if ($result)
                      {
                          $this->updateSetting('positive_icon', $_FILES['positive_icon']['name']);
                      }
                      else
                      {
                          $MSGS['errors'] = get_string('uploads:unknownerror', 'block_elbp');
                      }
                 }
                 else
                 {
                     $MSGS['errors'] = get_string('uploads:invalidmimetype', 'block_elbp');
                 }
                
            }
            
            
            // Negative
            if (isset($_FILES['negative_icon']) && $_FILES['negative_icon']['error'] == 0){
                
                 $fInfo = finfo_open(FILEINFO_MIME_TYPE);
                 $mime = finfo_file($fInfo, $_FILES['negative_icon']['tmp_name']);
                 finfo_close($fInfo);
                
                 $array = array('image/bmp', 'image/gif', 'image/jpeg', 'image/png', 'image/tiff', 'image/pjpeg');
                 if (in_array($mime, $array))
                 {
                      $_FILES['negative_icon']['name'] = "custom/" . $_FILES['negative_icon']['name'];
                      $result = move_uploaded_file($_FILES['negative_icon']['tmp_name'], $CFG->dirroot . '/blocks/elbp/plugins/Comments/pix/' . $_FILES['negative_icon']['name']);
                      if ($result)
                      {
                          $this->updateSetting('negative_icon', $_FILES['negative_icon']['name']);
                      }
                      else
                      {
                          $MSGS['errors'] = get_string('uploads:unknownerror', 'block_elbp');
                      }
                 }
                 else
                 {
                     $MSGS['errors'] = get_string('uploads:invalidmimetype', 'block_elbp');
                 }
                
            }
            
            $MSGS['success'] = get_string('settingsupdated', 'block_elbp');   
            return true;
            
            
        }
        
        parent::saveConfig($settings);
        
     }
     
     /**
      * Get mass student actions - add comment to lots of students at once
      */
     public function getMassActions(){
        
         return array(
             'add_comment' => get_string('addcomment', 'block_elbp')
         );
         
     }
     
     /**
      * Mass acctions for this plugin in the dashboard students list
      * @global \ELBP\Plugins\type $CFG
      * @global \ELBP\Plugins\type $DB
      * @param type $action
      * @param type $students
      * @param string $params
      * @return boolean
      */
     public function massAction($action, $students, $params){
         
         global $CFG, $DB;
         
         $confirmed = (isset($_POST['confirmed'])) ? true : false;
         
         if (!isset($params['view']) || !$params['view']){
             $params['view'] = '';
         }
                  
         switch($action)
         {
             
             case 'add_comment':
                                                   
                 $data = \ELBP\Plugins\Comments\Comment::getDataForNewCommentForm(false, $this);

                 $errors = array();
                 $data2 = (isset($_POST['params'])) ? $_POST['params'] : false;
                                  
                 if ($confirmed)
                 {
                     
                     $successMsg = '';
                     
                     foreach($students as $studentID){

                        $user = $DB->get_record("user", array("id" => $studentID));
                        $data2['studentID'] = $studentID;
                        
                        // Parse text
                        foreach($data2 as &$d){
                            $d = \elbp_parse_text_code($d, array('student' => $user));
                        }

                        $comment = new \ELBP\Plugins\Comments\Comment($data2);
                        $comment->setCommentsObj($this);
                        
                        if ($comment->save()){
                                                         
                            $successMsg .= '<i class="icon-ok-sign"></i>' . get_string('commentaddedfor', 'block_elbp') . ": " . fullname($user) . " ({$user->username})" . "<br>";

                        } else {
                            
                            foreach($comment->getErrors() as $error){

                                $errors[] = $error;

                            }
                                                        
                        }
                       

                     }     
                        
                     if (!$errors){
                         
                        return array(
                            'result' => true,
                            'success' => $successMsg
                        );
                         
                     }
                     
                     
                 }
                 
                 
                 
                // Not confirmed, or errors 
                $FORM = new \ELBP\ELBPForm();
                //$FORM->loadStudentID($this->student->id);

                $output = "";
                $output .= get_string('students') . " : <br><br>";
                $hidden = "";

                foreach($students as $id){
                    $output .= bcdb_get_user_name($id) . ", ";
                    $hidden .= "<input type='hidden' name='students[]' value='{$id}' />";
                }

                $output = substr($output, 0, -2);
                $output .= "<br><br>";
                $output .= "<form id='massIncidentForm' action='' method='post'>{$hidden}<input type='hidden' name='students_action' value='{$this->getID()}:add_comment' />";
                
                if ($errors){

                    foreach($errors as $error){
                        $output .= "<span class='bcdb_error'>{$error}</span><br>";
                    }
                    
                    $output .= "<br>";
                    
                }
                
                $stickyDate = (isset($data2['comment_date'])) ? $data2['comment_date'] : $data['date'];
                $output .= "<small>".get_string('date')."</small><br><div class='input-append date datepicker' data-date='' data-date-format='dd-mm-yyyy'><input readonly type='text' value='{$stickyDate}' class='standard' name='params[comment_date]'><button class='btn light-blue inverse date-button' type='button'><i class='icon-th'></i></button></div><br>";

                $stickyPos = array();
                $stickyPos['pos'] = ($data2['comment_positive'] == 1) ? 'checked' : '';
                $stickyPos['neg'] = ($data2['comment_positive'] == -1) ? 'checked' : '';
                $stickyPos['na'] = ($data2['comment_positive'] == 0) ? 'checked' : '';
                
                $output .= "<small>".get_string('commentpositive', 'block_elbp')."</small> <label class='radio inline'><input type='radio' name='params[comment_positive]' value='1' {$stickyPos['pos']} /> <small>".get_string('positive', 'block_elbp')."</small></label> <label class='radio inline'><input type='radio' name='params[incident_positive]' value='-1' {$stickyPos['neg']} /> <small>".get_string('negative', 'block_elbp')."</small></label> <label class='radio inline'><input type='radio' name='params[incident_positive]' value='0' {$stickyPos['na']} /> <small>".get_string('na', 'block_elbp')."</small></label><br><br>";
                $output .= "<small>".get_string('commentfurtheraction', 'block_elbp')."</small> <label class='checkbox inline'><input type='checkbox' name='params[comment_resolved]' value='0' /></label><br><br>";
                $output .= "<small>".get_string('commentpublishedportal', 'block_elbp')."</small> <label class='checkbox inline'><input type='checkbox' name='params[comment_published_portal]' value='1' /></label><br><br>";
                
                
                foreach($data['atts'] as $attribute){
                    
                    // Texteditor doesn't work, fix it another time
                    if ($attribute->type == 'Moodle Text Editor'){
                        $attribute->type = 'Textbox';
                    }
                                        
                    $output .= "<small>{$attribute->name}</small><br>";
                    $attribute->usersValue = (isset($data2[$attribute->name])) ? $data2[$attribute->name] : $attribute->usersValue;
                    $output .= $attribute->convertToFormElement( null, array(
                        'wrap-name' => 'params'
                    ) );
                    $output .= "<br><br>";
                    
                }
                

                $output .= "<input type='hidden' name='confirmed' value='1' />";
                $output .= "<button onclick='validateFormSubmission( $(this.form) );return false;' class='btn light-blue inverse' type='submit'>".get_string('add')."</button></form>";
                $output .= "<form action='{$CFG->wwwroot}/blocks/bc_dashboard/{$params['courseID']}/elbp/mystudents/view/{$params['view']}' ><button class='btn light-blue inverse' type='submit'>".get_string('cancel')."</button></form>";

                $output .= "<script>
                                function validateFormSubmission(frm)
                                {
                                    if (ELBP.validate_form(frm) == true)
                                    {
                                        frm.submit();
                                    }
                                }
                            </script>";

                bcdb_confirmation_page(get_string('addcomment', 'block_elbp'), $output);
                
                return false;
                                      
             break;
             
         }
         
     }
     
     
     /**
     * Get the progress bar/info for the block content
     */
    public function _getBlockProgress()
    {
                
        global $CFG;
        
        $output = "";
        
        // Number of tutorials set
        $total = count($this->getUserComments());
        
        $output .= "<div>";
            $output .= "<img src='{$CFG->wwwroot}/blocks/elbp/pix/progress_bar.png' alt='progress_bar' /> {$total} " . get_string('comments', 'block_elbp');
        $output .= "</div>";
               
        return $output;
        
    }
    
    
     /**
     * Update attribute names in the actual user attributes data table
     * @param type $newNames
     * @param type $oldNames
     */
    public function updateChangedAttributeNames($newNames, $oldNames)
    {
        
        global $DB;
        
        // Loop through attribute names and see if any are different
        if ($newNames)
        {
            for ($i = 0; $i < count($newNames); $i++)
            {
                
                if (!isset($oldNames[$i])) continue;
                
                $newName = $newNames[$i];
                $oldName = $oldNames[$i];
                                
                // Name has changed
                if ($newName !== $oldName)
                {
                    
                    // Update all references to the old name to the new name
                    $DB->execute("UPDATE {lbp_comment_attributes} SET field = ? WHERE field = ?", array($newName, $oldName));
                    
                }
                
            }
        }
        
        return true;
        
    }
     
     
    
}