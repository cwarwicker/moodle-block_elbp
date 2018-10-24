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
require_once $CFG->dirroot . '/blocks/elbp/lib.php';

class block_elbp extends block_base
{
    
    private $elbp;
    private $imgdir;
    
    private $cron_queue_process = 50; # Number of queued messages to process every time the cron runs
    
    public function init()
    {
        $this->title = 'ELBP';
    }
    
    public function get_content()
    {
        
        global $SITE, $CFG, $USER, $COURSE, $DB;
        
        
        if ($this->content !== null){
            return $this->content;
        }
        
        $this->content = new stdClass();
		
        if (!$USER){
            return $this->content;
        }
        
        
        $check_block = $DB->get_record("block", array("name" => "bc_dashboard"));
        $this->bc_dashboard_installed = ($check_block !== false);
       
        $this->elbp = ELBP\ELBP::instantiate();
        $this->title = $this->elbp->getELBPFullName();    
        
        $this->imgdir = $CFG->wwwroot . '/blocks/elbp/pix/';
        $this->www = $CFG->wwwroot . '/blocks/elbp/';
        
        
        // Let's work out who we are and therefore what links we can see
        $courseID = optional_param('id', $SITE->id, PARAM_INT);
        
        $access = $this->elbp->getCoursePermissions($courseID);
        if(!$access)
        {
            return $this->content;
        }
                        
        $this->content->text = '';
        $this->content->text .= '<ul class="elbp elbp_list_none">';
                                
        if ($access['user'] && !$access['god'] && !$access['teacher'])
        {
            
            if ($COURSE->id > 0 && $COURSE->id <> SITEID){
                $this->content->text .= '<li><img src="'.$this->imgdir.'group_blue.png" alt="" /> <a href="'.$this->www.'view.php?courseid='.$courseID.'">'.$this->elbp->getELBPMyName().'</a></li>';
            } else {
                $this->content->text .= '<li><img src="'.$this->imgdir.'group_blue.png" alt="" /> <a href="'.$this->www.'view.php">'.$this->elbp->getELBPMyName().'</a></li>';
            }
            
            // User Guide
            if ($this->elbp->getSetting('student_user_guide') && (\file_exists($CFG->dataroot . '/ELBP/' . $this->elbp->getSetting('student_user_guide')))){
                $this->content->text .= '<li><img src="'.$CFG->wwwroot.'/blocks/elbp/pix/file_icons/'.\elbp_get_file_icon( $this->elbp->getSetting('student_user_guide') ).'" /> <a href="'.$CFG->wwwroot.'/blocks/elbp/download.php?f='.elbp_get_data_path_code( $CFG->dataroot . '/ELBP/' . $this->elbp->getSetting('student_user_guide') ).'" target="_blank">'.get_string('userguide', 'block_elbp').'</a></li>';
            }
            
            
            $plugins = $this->elbp->getPlugins();
            if ($plugins)
            {
                
                $this->content->text .= '<li><hr></li>';
                
                // If overall progress bar is enabled, put that here
                if ($this->elbp->getSetting('enable_student_progress_bar') == 'calculated')
                {
                    $this->elbp->loadStudent($USER->id);
                    $this->content->text .= '<li>'.$this->elbp->getStudentProgressBar(false).'</li>';
                }
                
                foreach($plugins as $plugin)
                {
                    if ($plugin->loadStudent($USER->id))
                    {
                        if (method_exists($plugin, '_getBlockProgress') && $plugin->getSetting('block_progress_enabled') == 1)
                        {
                            $plugin->setELBPObject($this->elbp);
                            $this->content->text .= '<li>'.$plugin->_getBlockProgress().'</li>';
                        }
                    }
                }
            }
            
        }
        else
        {
            
            if ($access['god'] || $access['teacher'])
            {
                $myStudentsLink = ($COURSE->id <> SITEID) ? 'index.php?Qs=/view/course/' . $COURSE->id : '';
                $this->content->text .= '<li><img src="'.$this->imgdir.'group_blue.png" alt="" /> <a href="'. $CFG->wwwroot . '/blocks/bc_dashboard/'.$myStudentsLink.'">'.get_string('mystudents', 'block_elbp').'</a></li>';
                $this->content->text .= '<li><img src="'.$this->imgdir.'settings.png" alt="" /> <a href="'. $CFG->wwwroot . '/blocks/elbp/settings.php">'.get_string('mysettings', 'block_elbp').'</a></li>';
            }
            
            // User Guide
            if ($this->elbp->getSetting('staff_user_guide') && (\file_exists($CFG->dataroot . '/ELBP/' . $this->elbp->getSetting('staff_user_guide')))){
                $this->content->text .= '<li><img src="'.$CFG->wwwroot.'/blocks/elbp/pix/file_icons/'.\elbp_get_file_icon( $this->elbp->getSetting('staff_user_guide') ).'" /> <a href="'.$CFG->wwwroot.'/blocks/elbp/download.php?f='.elbp_get_data_path_code( $CFG->dataroot . '/ELBP/' . $this->elbp->getSetting('staff_user_guide') ).'" target="_blank">'.get_string('userguide', 'block_elbp').'</a></li>';
            }

            // WLBP Manager link
            if ($access['god'] || $access['elbpadmin'])
            {
                if ($this->bc_dashboard_installed){
                    $this->content->text .= '<li><img src="'.$this->imgdir.'admin.png" alt="" /> <a href="'.$CFG->wwwroot.'/blocks/bc_dashboard/index.php?Qs=/view/admin">'.get_string('manager', 'block_elbp').'</a></li>';
                }
            }

            // Assign Tutors, Configuration links
            if ($access['god'])
            {
                $this->content->text .= '<li><img src="'.$this->imgdir.'contact_add.png" alt="" /> <a href="'.$this->www . 'assign_tutors.php' .'">'.get_string('assignroles', 'block_elbp').'</a></li>';
                $this->content->text .= '<li><img src="'.$this->imgdir.'settings.png" alt="" /> <a href="'.$this->www.'config.php">'.get_string('config', 'block_elbp').'</a></li>';
            }

            // Course settings link
            if ($COURSE->id > 0 && $COURSE->id <> SITEID && \has_capability('block/elbp:edit_course_settings', $access['context']))
            {
                $this->content->text .= '<li><img src="'.$this->imgdir.'settings.png" alt="" /> <a href="'.$this->www.'config.php?view=course&id='.$COURSE->id.'">'.get_string('coursesettings', 'block_elbp').'</a></li>';
            }

        }
                
        $this->content->text .= '</ul>';
         
        return $this->content;
        
    }
    
    /**
     * Cron function to run
     * This will take care of things like email alerts that can't be manually triggered by a user, e.g. when a target deadline passes its date
     */
    public function cron()
    {
        
        mtrace("");
        
        $ELBP = ELBP\ELBP::instantiate();
        
        // Run any plugin crons
        $plugins = $ELBP->getPlugins();
        if ($plugins)
        {
            foreach($plugins as $plugin)
            {
                if ($plugin->isCronEnabled())
                {
                    mtrace("Processing cron for plugin ({$plugin->getTitle()})");
                    $plugin->cron();
                }
            }
        }
        
        
        
        
        
        
        // Run garbage collection around midnight
        $hour = date('H');
        
        if ($hour == 0){
            
            // Clean up old alerts in the queue
            $cleaned = \ELBP\Alert::gc();
            mtrace("Deleted {$cleaned} old queued alerts from lbp_alert_queue");
            
            // Clear out old attendance tracking data
            
            
        }

        // RUn automated between midnight & 7am
        if ($hour < 7){
            
            // Process automatic events, such as checking that attendance has not dropped below a given percentage or that a target hasn't passewd its deadline, etc...
            // This could potentially take a while, as people who have the alert enabled for a course means we will have to loop through all users in that course and then potentially loop through all their targets or similar
            $processed = \ELBP\Alert::processAuto();
            mtrace("Processed {$processed} automated alerts");
            
        }
        
        // Send messages
        $sent = \ELBP\Alert::processQueue($this->cron_queue_process);
        mtrace("Processed {$sent} messages from the queue");
                
    }
    
}