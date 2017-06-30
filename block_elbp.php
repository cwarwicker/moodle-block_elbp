<?php
/**
 * ELBP core block
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
                if ($this->elbp->getSetting('enable_student_progress_bar') == 1)
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
                $this->content->text .= '<li><img src="'.$this->imgdir.'group_blue.png" alt="" /> <a href="'. $CFG->wwwroot . '/blocks/elbp/mystudents.php?courseid='.$COURSE->id.'">'.get_string('mystudents', 'block_elbp').'</a></li>';
                if ($this->bc_dashboard_installed){
                    $this->content->text .= '<li><img src="'.$this->imgdir.'settings.png" alt="" /> <a href="'. $CFG->wwwroot . '/blocks/bc_dashboard/'.$COURSE->id.'/elbp/settings">'.get_string('mysettings', 'block_elbp').'</a></li>';
                }
            }
            
            // User Guide
            if ($this->elbp->getSetting('staff_user_guide') && (\file_exists($CFG->dataroot . '/ELBP/' . $this->elbp->getSetting('staff_user_guide')))){
                $this->content->text .= '<li><img src="'.$CFG->wwwroot.'/blocks/elbp/pix/file_icons/'.\elbp_get_file_icon( $this->elbp->getSetting('staff_user_guide') ).'" /> <a href="'.$CFG->wwwroot.'/blocks/elbp/download.php?f='.elbp_get_data_path_code( $CFG->dataroot . '/ELBP/' . $this->elbp->getSetting('staff_user_guide') ).'" target="_blank">'.get_string('userguide', 'block_elbp').'</a></li>';
            }
            
            if ($access['god'] || $access['elbpadmin'] || $access['teacher'])
            {
                if ($this->bc_dashboard_installed){
                    $this->content->text .= '<li><img src="'.$this->imgdir.'report.png" alt="" /> <a href="'.$CFG->wwwroot.'/blocks/bc_dashboard/'.$COURSE->id.'/elbp/reports">'.get_string('reports', 'block_elbp').'</a></li>';
                }
            }
            
            if ($access['god'] || $access['elbpadmin'])
            {
                if ($this->bc_dashboard_installed){
                    $this->content->text .= '<li><img src="'.$this->imgdir.'admin.png" alt="" /> <a href="'.$CFG->wwwroot.'/blocks/bc_dashboard/'.$COURSE->id.'/elbp/admin">'.get_string('manager', 'block_elbp').'</a></li>';
                }
            }

            if ($access['god'])
            {
                $this->content->text .= '<li><img src="'.$this->imgdir.'contact_add.png" alt="" /> <a href="'.$this->www . 'assign_tutors.php' .'">'.get_string('assignroles', 'block_elbp').'</a></li>';
                $this->content->text .= '<li><img src="'.$this->imgdir.'settings.png" alt="" /> <a href="'.$this->www.'config.php">'.get_string('config', 'block_elbp').'</a></li>';
            }
            
            if ($COURSE->id > 0 && $COURSE->id <> SITEID && \has_capability('block/elbp:edit_course_settings', $access['context']))
            {
                $this->content->text .= '<li><img src="'.$this->imgdir.'settings.png" alt="" /> <a href="'.$this->www.'config.php?view=course&id='.$COURSE->id.'">'.get_string('coursesettings', 'block_elbp').'</a></li>';
            }
            
            // Bedford college only. I'll make this for everyone else when I have the time (lololololololololol)
            if (isset($CFG->moodleinstance))
            {
                if ($COURSE->id > 0 && $COURSE->id != SITEID && ($access['elbpadmin'] || $access['teacher']))
                {
                    $this->content->text .= '<li><a href="'.$CFG->wwwroot.'/admin/bedtools/groupprofile.php?cID='.$COURSE->id.'">Group Profile</a></li>';
                }
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
            mtrace("Proccessed {$processed} automated alerts");
            
        }
        
        // Send messages
        $sent = \ELBP\Alert::processQueue($this->cron_queue_process);
        mtrace("Processed {$sent} messages from the queue");
                
    }
    
}