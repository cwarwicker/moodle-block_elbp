<?php
/**
 * BEDFORD COLLEGE ONLY * 
 */


namespace ELBP\Plugins\StudentProfile\Extras;

class enrolments
{
    
    public function __construct(){
        
       
        
    }
    
    public function getUserEnrolments($username){
        
        global $CFG;
        
        // Just to be safe incase it gets pushed up to some release code
        if (!isset($CFG->usebedcollcorechanges) || !$CFG->usebedcollcorechanges || !isset($CFG->moodleinstance) || in_array($CFG->moodleinstance, array('local', 'core', '6thform', 'HE'))){
            return false;
        }
        
        // Bedcoll
        if ( preg_match("/[a-z]/i", $username)){
            return array();
        }

        
        try {
            
            $MIS = \ELBP\MIS\Manager::instantiate( "ebs" );
            $MIS->connect();
            $query = $MIS->query("SELECT * FROM V_MIS_MOODLE_ENROLMENTS
                                  WHERE [Username] = CONVERT(int, CONVERT(varchar(max), ?))", array($username));

            $records = array();
            while($row = $MIS->fetch($query))
            {
                $records[] = $row;
            }

            return $records;
        
        } catch (\ELBP\ELBPException $e){
        
            echo $e->getException();
            return false;
            
        }
        
    }
    
    
}