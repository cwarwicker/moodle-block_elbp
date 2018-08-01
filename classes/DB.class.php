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

namespace ELBP;

class DB
{
    
    private $DB;
    
    /**
     * Construct
     * @global type $CFG
     * @global type $DB
     */
    public function __construct()
    {
        
        global $CFG, $DB;
        
        $this->CFG = $CFG;
        $this->DB = $DB;
        
    }
    
    /**
     * Return the id of a role by its shortname
     * @param type $shortname
     */
    public function getRole($shortname)
    {
        $record = $this->DB->get_record("role", array("shortname" => $shortname));
        return ($record) ? $record->id : false;
    }
    
    /**
     * Get a course record from the mdl_course table
     * @param array $params
     */
    public function getCourse($params)
    {
             
        // "type" element used to define how we're getting the course. If it's not there, we assume by ID
        switch($params['type'])
        {
            case 'short':
                $record = $this->DB->get_record("course", array("shortname" => $params['val']));
            break;
        
            case 'full':
                $record = $this->DB->get_record("course", array("fullname" => $params['val']));
            break;
        
            case 'id':
            default:
                $record = $this->DB->get_record("course", array("id" => $params['val']));
            break;
        
        }
        
        return $record;
        
    }
    
    /**
     * Get a user from username or id
     * @param type $params
     * @return type
     */
    public function getUser($params)
    {
        
        // "type" element used to define how we're getting the course. If it's not there, we assume by ID
        
        switch($params['type'])
        {
            
            case 'username':
                $record = $this->DB->get_record("user", array("username" => $params['val']));
            break;
        
            case 'idnumber':
                $record = $this->DB->get_record("user", array("idnumber" => $params['val']));
            break;
          
            case 'id':
            default:
                $record = $this->DB->get_record("user", array("id" => $params['val']));
            break;
        
        }
        
        return $record;
        
    }
    
    /**
     * Get a list of all the courses a given student is on
     * @param type $userID
     */
    public function getStudentsCourses($userID)
    {
     
        $params = array();
        
        $excludeCourses = explode(",", \ELBP\Setting::getSetting('exclude_courses'));
        $includedCategories = explode(",", \ELBP\Setting::getSetting('specific_course_cats'));

        $exclude = array();
        $categories = array();
        
        // Get ids of valid courses to exclude
        if ($excludeCourses){
            
            // Check the courses provided are valid
            foreach($excludeCourses as $excludeCourse){
                if (ctype_digit($excludeCourse)){
                    $course = $this->getCourse( array("type" => "id", "val" => $excludeCourse) );
                    if ($course){
                        $exclude[] = $course->id;
                    }
                }
            }
            
        }
                
        if (!empty($exclude)){
            $excludeSQL = " AND c.id NOT IN (".implode(',', $exclude).") ";
        } else {
            $excludeSQL = "";
        }
        
        // Get ids of valid categories to specify
        if ($includedCategories){
            foreach($includedCategories as $catID){
                $category = $this->DB->get_record("course_categories", array("id" => $catID));
                if ($category){
                    $categories[] = $category->id;
                }
            }
        }
        
        if ($categories){
            $categorySQL = " AND c.category IN (".implode(',', $categories).") ";
        } else {
            $categorySQL = "";
        }
                
        
        $sql = "SELECT
                    DISTINCT c.*
                FROM
                    {course} c
                INNER JOIN
                    {context} cx ON cx.instanceid = c.id
                INNER JOIN
                    {role_assignments} r ON r.contextid = cx.id
                WHERE
                    cx.contextlevel = ?
                AND
                    r.userid = ?
                AND
                    r.roleid = ?
                {$excludeSQL}     
                {$categorySQL}  
                ORDER BY
                    c.shortname ASC";
                                            
        $params[] = CONTEXT_COURSE;
        $params[] = $userID;
        $params[] = $this->getRole("student");
        
        $records = $this->DB->get_records_sql($sql, $params);
                
        return $records;
        
    }
    
    
    /**
     * Get a list of all the groups a given student is in
     * @param type $userID
     */
    public function getStudentsGroups($userID)
    {
     
        $params = array();
        
        $sql = "SELECT
                    DISTINCT g.id, g.name, g.courseid
                FROM
                    {groups} g
                INNER JOIN
                    {groups_members} gm ON gm.groupid = g.id
                WHERE
                    gm.userid = ?";
                            
        $params[] = $userID;
        
        $records = $this->DB->get_records_sql($sql, $params);
                
        return $records;
        
    }
    
    
    /**
     * Get a simple recordset of all the courses a teacher is on
     * @param int $userID
     * @param bool $includeNonEditing Should we look for courses where they are non-editing teacher as well? or just editing?
     */
    public function getTeachersCourses($userID, $includeNonEditing=true)
    {
        
        $params = array();
        $params[] = $userID;
        $params[] = CONTEXT_COURSE;
        
        $excludeCourses = explode(",", \ELBP\Setting::getSetting('exclude_courses'));
        $includedCategories = explode(",", \ELBP\Setting::getSetting('specific_course_cats'));

        $exclude = array();
        $categories = array();
                        
        if ($excludeCourses){
            
            // Check the courses provided are valid
            foreach($excludeCourses as $excludeCourse){
                if (ctype_digit($excludeCourse)){
                    $course = $this->getCourse( array("type" => "id", "val" => $excludeCourse) );
                    if ($course){
                        $exclude[] = $course->id;
                    }
                }
            }
            
        }
                
        if (!empty($exclude)){
            $excludeSQL = " AND c.id NOT IN (".implode(',', $exclude).") ";
        } else {
            $excludeSQL = "";
        }
        
        
        // Get ids of valid categories to specify
        if ($includedCategories){
            foreach($includedCategories as $catID){
                $category = $this->DB->get_record("course_categories", array("id" => $catID));
                if ($category){
                    $categories[] = $category->id;
                }
            }
        }
        
        if ($categories){
            $categorySQL = " AND c.category IN (".implode(',', $categories).") ";
        } else {
            $categorySQL = "";
        }
        
        
        
        
        // Role SQL
        $roleSQL = " AND (";
        
        $roleSQL .= "rl.id = ? ";
        $params[] = $this->getRole("manager");
        
        $roleSQL .= "OR rl.id = ? ";
        $params[] = $this->getRole("coursecreator");
        
        $roleSQL .= "OR rl.id = ? ";
        $params[] = $this->getRole("editingteacher");
        
        if ($includeNonEditing){
            $roleSQL .= "OR rl.id = ? ";
            $params[] = $this->getRole("teacher");
        }
        
        $roleSQL .= " ) "; 
        
        
        
        
        $sql = "SELECT
                    DISTINCT c.id, c.fullname, c.shortname
                FROM
                    {course} c
                INNER JOIN
                    {context} cx ON cx.instanceid = c.id
                INNER JOIN
                    {role_assignments} r ON r.contextid = cx.id
                INNER JOIN
                    {role} rl ON rl.id = r.roleid
                INNER JOIN
                    {user} u ON u.id = r.userid
                WHERE
                    u.id = ? AND cx.contextlevel = ? 
                                        
                {$excludeSQL}   
                    
                {$categorySQL}
                    
                {$roleSQL}

                ORDER BY
                    c.fullname";
                            
        $records = $this->DB->get_records_sql($sql, $params);
                        
        return $records;
        
    }
    
    /**
     * Count the number of students assigned to given user as reqwuireing additional support
     * @param type $userID
     */
    public function countAslStudents($userID)
    {
        
        $params = array();
        $sql = array();
        
        $role = \ELBP\Setting::getSetting('elbp_asl');
        if (!$role) $role = \ELBP\ASL::DEFAULT_ROLE;
        
        $sql['select'] = " SELECT COUNT(DISTINCT r.id) ";
        $sql['from']   = " FROM {role_assignments} r ";
        $sql['join']   = " INNER JOIN {context} c ON c.id = r.contextid ";
        $sql['join']  .= " INNER JOIN {user} u ON u.id = c.instanceid ";
        $sql['where']  = " WHERE r.userid = ? AND c.contextlevel = ? AND r.roleid = ? ";
        
        $fullSQL = implode(" ", $sql);
        
        $params[] = $userID;
        $params[] = CONTEXT_USER;
        $params[] = $this->getRole($role);
        
        $record = $this->DB->count_records_sql($fullSQL, $params);
                
        return $record;
        
    }
    
     /**
     * Count the number of asls assigned to given student
     * @param type $userID
     */
    public function countStudentAsls($userID)
    {
        
        $params = array();
        $sql = array();
        
        $role = \ELBP\Setting::getSetting('elbp_asl');
        if (!$role) $role = \ELBP\ASL::DEFAULT_ROLE;
        
        $sql['select'] = " SELECT COUNT(DISTINCT r.id) ";
        $sql['from']   = " FROM {role_assignments} r ";
        $sql['join']   = " INNER JOIN {context} c ON c.id = r.contextid ";
        $sql['join']  .= " INNER JOIN {user} u ON u.id = r.userid ";
        $sql['where']  = " WHERE c.instanceid = ? AND c.contextlevel = ? AND r.roleid = ? ";
        
        $fullSQL = implode(" ", $sql);
        
        $params[] = $userID;
        $params[] = CONTEXT_USER;
        $params[] = $this->getRole($role);
        
        $record = $this->DB->count_records_sql($fullSQL, $params);
                
        return $record;
        
    }
    
    /**
     * Count the number of mentees on a tutor
     * @param type $userID
     * @param type $filter
     * @return type
     */
    public function countTutorsMentees($userID, $filter=false)
    {
        
        $params = array();
        $sql = array();
        
        $sql['select'] = " SELECT COUNT(DISTINCT r.id) ";
        $sql['from']   = " FROM {role_assignments} r ";
        $sql['join']   = " INNER JOIN {context} c ON c.id = r.contextid ";
        $sql['join']  .= " INNER JOIN {user} u ON u.id = c.instanceid ";
        
        // Filtering by a specific course - If front page course given (id 1) just return all mentees
        if ($filter && isset($filter['course']) && $filter['course'] > 1){
            
            // What we need to do here is a subquery to select only those students in the main mentees list, who are enrolled on $courseID
            $sql['join'] .= " INNER JOIN
                            (
                                SELECT
                                    u.id
                                FROM
                                    {role_assignments} r
                                INNER JOIN
                                    {context} c ON r.contextid = c.id
                                INNER JOIN
                                    {user} u ON u.id = r.userid
                                WHERE
                                    c.instanceid = ?
                                AND
                                    c.contextlevel = ".CONTEXT_COURSE."
                                AND
                                    r.roleid = ?
                            ) AS students ON students.id = u.id ";
            
            $params[] = $filter['course'];
            $params[] = $this->getRole("student");
            
        }        
        
        
        
        // Filtering by a group as well
        if ($filter && isset($filter['course']) && $filter['course'] > 1 && isset($filter['group'])){
            
            
            // Also join it on the groups and group_members tables
            $sql['join'] .= " INNER JOIN
                             (
                                SELECT u.id
                                FROM {groups} g
                                INNER JOIN {groups_members} gm ON gm.groupid = g.id
                                INNER JOIN {user} u ON u.id = gm.userid
                                WHERE g.id = ?
                             ) AS groupstudents ON groupstudents.id = u.id ";
            
            $params[] = $filter['group'];
        }
        
        
        $sql['where']  = " WHERE r.userid = ? AND c.contextlevel = " . CONTEXT_USER . " ";
        
        $fullSQL = implode(" ", $sql);
        
        $params[] = $userID;
        
        $record = $this->DB->count_records_sql($fullSQL, $params);
                
        return $record;
        
    }
    
    /**
     * Count the number of students on a given course
     * @param type $courseID
     * @return type
     */   
    public function countStudentsOnCourse($courseID)
    {
        $records = $this->getStudentsOnCourse($courseID, array("COUNT(u.id) as ttl"), true);
        
        $record = reset($records); # getStudents uses get_records rather than get_record so need to reset to first element
                
        return ($record && isset($record->ttl)) ? $record->ttl : false;
    }
    
    /**
     * Get a recordset of all students linked to a additional support lecturer
     * @param type $tutorID
     */
    public function getStudentsOnAsl($tutorID)
    {
        
        $role = \ELBP\Setting::getSetting('elbp_asl');
        if (!$role) $role = \ELBP\ASL::DEFAULT_ROLE;
        
        $params = array($tutorID, CONTEXT_USER, $this->getRole($role));
        $sql = array();
        
        $sql['select'] = "SELECT ";
        $sql['select'] .= "DISTINCT u.*";
        $sql['from']  = " FROM {role_assignments} r ";
        $sql['join']  = " INNER JOIN {context} c ON c.id = r.contextid ";
        $sql['join'] .= " INNER JOIN {user} u ON u.id = c.instanceid ";
        $sql['where'] = " WHERE r.userid = ? AND c.contextlevel = ? AND r.roleid = ? ";
        $sql['order'] = " ORDER BY u.lastname, u.firstname ";
        
        $fullSQL = implode(" ", $sql);
                
        $records = $this->DB->get_records_sql($fullSQL, $params);
                
        return $records;      
        
    }
    
    
    
     /**
     * Get a recordset of all students linked to a additional support lecturer
     * @param type $tutorID
     */
    public function getAllStudentsWithAdditionalSupport()
    {
        
        $role = \ELBP\Setting::getSetting('elbp_asl');
        if (!$role) $role = \ELBP\ASL::DEFAULT_ROLE;
        
        $params = array(CONTEXT_USER, $this->getRole($role));
        $sql = array();
        
        $sql['select'] = "SELECT ";
        $sql['select'] .= "DISTINCT u.*";
        $sql['from']  = " FROM {role_assignments} r ";
        $sql['join']  = " INNER JOIN {context} c ON c.id = r.contextid ";
        $sql['join'] .= " INNER JOIN {user} u ON u.id = c.instanceid ";
        $sql['where'] = " WHERE c.contextlevel = ? AND r.roleid = ? ";
        $sql['order'] = " ORDER BY u.lastname, u.firstname ";
        
        $fullSQL = implode(" ", $sql);
                
        $records = $this->DB->get_records_sql($fullSQL, $params);
                
        return $records;      
        
    }
    
    /**
     * Get all the students a tutor has, across their courses and individual assignments
     * @param type $tutorID
     * @return type
     */
    public function getAllTutorsStudents($tutorID){
        
        $students = array();
        
        // Mentees
        $students = $students + $this->getMenteesOnTutor($tutorID);
        
        // Additional Support
        $students = $students + $this->getStudentsOnAsl($tutorID);
        
        // Courses
        $courses = $this->getTeachersCourses($tutorID);
        if ($courses)
        {
            foreach($courses as $course)
            {
                $students = $students + $this->getStudentsOnCourse($course->id);
            }
        }
        
        // Sort them
        uasort($students, function($a, $b){
            return ( strcmp( $a->lastname, $b->lastname ) == 0 ) ?
                     strcmp( $a->firstname, $b->firstname ) :
                     strcmp( $a->lastname, $b->lastname ) ;
        });
        
        return $students;

    }
    
    
    /**
     * Get a recordset of all asls linked to a given student
     * @param type $studentID
     */
    public function getAslOnStudent($studentID)
    {
        
        $role = \ELBP\Setting::getSetting('elbp_asl');
        if (!$role) $role = \ELBP\ASL::DEFAULT_ROLE;
        
        $params = array($studentID, CONTEXT_USER, $this->getRole($role));
        $sql = array();
        
        $sql['select'] = "SELECT ";
        $sql['select'] .= "DISTINCT u.id, u.username, u.firstname, u.lastname, u.picture, u.imagealt, u.email";
        $sql['from']  = " FROM {role_assignments} r ";
        $sql['join']  = " INNER JOIN {context} c ON c.id = r.contextid ";
        $sql['join'] .= " INNER JOIN {user} u ON u.id = r.userid ";
        $sql['where'] = " WHERE c.instanceid = ? AND c.contextlevel = ? AND r.roleid = ? ";
        $sql['order'] = " ORDER BY u.lastname, u.firstname ";
        
        $fullSQL = implode(" ", $sql);
                
        $records = $this->DB->get_records_sql($fullSQL, $params);
                
        return $records;      
        
    }
    
    
    /**
     * Get a recordset of all personal tutors linked to a given student
     * @param type $tutorID
     */
    public function getTutorsOnStudent($studentID)
    {
        
        $role = \ELBP\PersonalTutor::getPersonalTutorRole();
        $params = array($studentID, CONTEXT_USER, $this->getRole( $role ));
        $sql = array();
        
        $sql['select'] = "SELECT ";
        $sql['select'] .= "DISTINCT u.*";
        $sql['from']  = " FROM {role_assignments} r ";
        $sql['join']  = " INNER JOIN {context} c ON c.id = r.contextid ";
        $sql['join'] .= " INNER JOIN {user} u ON u.id = r.userid ";
        $sql['where'] = " WHERE c.instanceid = ? AND c.contextlevel = ? AND r.roleid = ? ";
        $sql['order'] = " ORDER BY u.lastname, u.firstname ";
        
        $fullSQL = implode(" ", $sql);
                
        $records = $this->DB->get_records_sql($fullSQL, $params);
                
        return $records;      
        
    }
    
    /**
     * Get a list of all the tutors teaching on courses that the student is on
     * This will only get users on the role Teacher, as non-editing teacher could be the personal tutors again,
     * Manager and such could be dept heads who don't actually teach them. We can only cater for so much
     * customisation, so if they want to change this, they will have to change the code.
     * @param type $studentID
     */
    public function getCourseTutorsOnStudent($studentID)
    {
        
        $users = array();
        
        // Get the student's courses
        $courses = $this->getStudentsCourses($studentID);
        if ($courses)
        {
            foreach($courses as $course)
            {
                
                $teachers = $this->getTeachersOnCourse($course->id);
                if ($teachers)
                {
                    foreach($teachers as $teacher)
                    {
                        if (!array_key_exists($teacher->id, $users))
                        {
                            $users[$teacher->id] = $teacher;
                        }
                    }
                }
                
            }
        }
        
        usort($users, function($a, $b){
            return ( strcasecmp($a->lastname, $b->lastname) === 0 ) ? strcasecmp($a->firstname, $b->firstname) : strcasecmp($a->lastname, $b->lastname);
        });
        
        return $users;
        
    }
    
    
    /**
     * Return a recordset of all the student linked to a particular tutor
     * Joins are done with following aliases:
     *  u - mdl_user
     *  a - mdl_lbp_tutor_assignments
     *  c - mdl_course
     *  g - mdl_groups
     * @param type $tutorID
     * @param type $fields If this is set, it defines what fields to bring back, e.g. "u.firstname", "u.lastname", etc...
     * @param type $overwrite If this is false it will append the additional fields to the default, if its true it will overwrite default
     * @param type $limit Default is false to say no we don't want to limit, else it expects an array of two elements for: LIMIT x,y
     */
    public function getMenteesOnTutor($tutorID, $fields=null, $overwrite=false, $limit=false)
    {
        
        $params = array();
        $sql = array();
        
         // Filter results
        $filterFirst = optional_param('filterFirst', false, PARAM_ALPHA);
        $filterLast = optional_param('filterLast', false, PARAM_ALPHA);
        $filterSearch = optional_param('filterSearch', false, PARAM_TEXT);
        $courseID = optional_param('courseid', false, PARAM_INT);
        $groupID = optional_param('groupid', false, PARAM_INT);
                
        $sql['select'] = "SELECT ";
        
        $default = "DISTINCT u.*, ";
        
        // Build select fields
        if (!$fields || ($fields && !$overwrite) )
        {
            $sql['select'] .= $default;
        }
        
        if (!is_null($fields))
        {
            foreach($fields as $field)
            {
                $sql['select'] .= $field . ", ";
            }
        }
        
        // Trim the last comma if we used specified fields
        $sql['select'] = rtrim($sql['select'], ", ") . " ";
        
        $sql['from']  = " FROM {role_assignments} r ";
        $sql['join']  = " INNER JOIN {context} c ON c.id = r.contextid ";
        $sql['join'] .= " INNER JOIN {user} u ON u.id = c.instanceid ";
        
        // Filtering by a specific course - If front page course given (id 1) just return all mentees
        if ($courseID && $courseID > 1){
            
            // What we need to do here is a subquery to select only those students in the main mentees list, who are enrolled on $courseID
            $sql['join'] .= " INNER JOIN
                            (
                                SELECT
                                    u.id
                                FROM
                                    {role_assignments} r
                                INNER JOIN
                                    {context} c ON r.contextid = c.id
                                INNER JOIN
                                    {user} u ON u.id = r.userid
                                WHERE
                                    c.instanceid = ?
                                AND
                                    c.contextlevel = ".CONTEXT_COURSE."
                                AND
                                    r.roleid = ?
                            ) AS students ON students.id = u.id ";
            
            $params[] = $courseID;
            $params[] = $this->getRole("student");
            
        }        
        
        
        
        // Filtering by a group as well
        if ($courseID && $courseID > 1 && $groupID){
            
            
            // Also join it on the groups and group_members tables
            $sql['join'] .= " INNER JOIN
                             (
                                SELECT u.id
                                FROM {groups} g
                                INNER JOIN {groups_members} gm ON gm.groupid = g.id
                                INNER JOIN {user} u ON u.id = gm.userid
                                WHERE g.id = ?
                             ) AS groupstudents ON groupstudents.id = u.id ";
            
            $params[] = $groupID;
        }
        
        
        
        
        $sql['where'] = " WHERE r.userid = ? AND c.contextlevel = ? AND r.roleid = ? ";
        $sql['order'] = " ORDER BY u.lastname, u.firstname ";
        
        $params[] = $tutorID;
        $params[] = CONTEXT_USER;
        $params[] = $this->getRole( \ELBP\PersonalTutor::getPersonalTutorRole() );
        
       
        
        // First Name
        if ($filterFirst){
            $sql['where'] .= " AND (u.firstname LIKE ?) ";
            $params[] = "{$filterFirst}%";
        }
        
        // Last Name
        if ($filterLast){
            $sql['where'] .= " AND (u.lastname LIKE ?) ";
            $params[] = "{$filterLast}%";
        }
        
        // Search box
        if ($filterSearch){
            $sql['where'] .= " AND ( u.username LIKE ? OR u.lastname LIKE ? OR u.firstname LIKE ? OR CONCAT(u.firstname, ' ', u.lastname) LIKE ? ) ";
            $params[] = "%{$filterSearch}%";
            $params[] = "%{$filterSearch}%";
            $params[] = "%{$filterSearch}%";
            $params[] = "%{$filterSearch}%";
        }
        
        
            
        
        // If there is a limit array
        $sqlLimit = array(0, 0);
        
        if ($limit){
            if (is_array($limit)){
                $sqlLimit = $limit;
            } else {
                $sqlLimit[0] = 0;
                $sqlLimit[1] = $limit;
            }
        }
        
        $fullSQL = implode(" ", $sql);
                
        $records = $this->DB->get_records_sql($fullSQL, $params, $sqlLimit[0], $sqlLimit[1]);
                
        return $records;        
        
    }
    
    /**
     * Get a list of groups on a course
     * @param type $courseID
     */
    public function getCourseGroups($courseID)
    {
        // todo
    }
    
    /**
     * For a given course category, get all the sub categories beneath it
     * @global \ELBP\type $DB
     * @param type $catID
     * @return boolean
     */
    public function getRecursiveCategories($catID = null)
    {
        
        global $DB;
        
        $results = array();
                
        if (!is_null($catID)){
            $cat = $DB->get_record("course_categories", array("id" => $catID));
            if (!$cat) return false;
            $subCats = $DB->get_records("course_categories", array("parent" => $catID));
        } else {
            $subCats = $DB->get_records("course_categories", array("parent" => 0), "id ASC");
        }
        
        if (!$subCats) return false;
        
        if ($subCats)
        {
            foreach($subCats as $subCat)
            {
                $subSubCats = $this->getRecursiveCategories($subCat->id);
                $results[$subCat->name] = $subSubCats;
            }
        }
        
        
        return $results;
        
    }
    
    
 
    
    /**
     * TODO
     * Get a list of all courses within a given category, including any sub categories that may be underneath it
     * @param type $catID
     */
    public function getCoursesInCategory($catID)
    {
        
        $results = array();
        
        // First 
        
        return $results;
        
    }

    /**
     * 
     * @global \ELBP\type $DB
     * @param type $courseID
     * @return type
     */
    public function getChildCourses($courseID)
    {
        
        global $DB;
        
        $sql = "SELECT DISTINCT c.*
                FROM {course} c
                INNER JOIN {enrol} e ON c.id = e.customint1
                WHERE e.enrol = 'meta' AND e.courseid = ?";
        
        return $DB->get_records_sql($sql, array($courseID));
        
    }
    

    public function isUserOnCourse($userID, $courseID)
    {
        
        global $DB;
        
        return $DB->get_record_sql("SELECT r.id 
                                    FROM {context} cx
                                    INNER JOIN {role_assignments} r ON r.contextid = cx.id
                                    WHERE cx.contextlevel = ? AND cx.instanceid = ? AND r.userid = ?", array(CONTEXT_COURSE, $courseID, $userID), IGNORE_MULTIPLE);
        
    }
    
    /**
     * Return a recordset of students on a course
     * Joins will be done with the following aliases:
     *  u = mdl_user
     *  c = mdl_course
     *  r = mdl_role_assignments
     *  cx = mdl_context
     * @param type $courseID
     * @param array $fields If this is set, it defines what fields to bring back, e.g. "u.firstname", "u.lastname", etc...
     * @param bool $overwrite If this is false it will append the additional fields to the default, if its true it will overwrite default
     * @param mixed $limit Default is false to say no we don't want to limit, else it expects an array of two elements for: LIMIT x,y
     */
    public function getStudentsOnCourse($courseID, $fields=null, $overwrite = false, $limit = false)
    {
        return $this->getUsersOnCourse($courseID, $this->getRole("student"), $fields, $overwrite, $limit);
    }
    
    public function getTeachersOnCourse($courseID, $fields=null, $overwrite = false, $limit = false)
    {
        return $this->getUsersOnCourse($courseID, $this->getRole("editingteacher"), $fields, $overwrite, $limit);
    }
    
    /**
     * Get users on a course
     * @param type $courseID
     * @param type $roleID
     * @param type $fields
     * @param type $overwrite
     * @param type $limit
     * @return type
     */
    public function getUsersOnCourse($courseID, $roleID, $fields=null, $overwrite = false, $limit = false)
    {
                       
        $params = array();
        $sql = array();
        $sql['select'] = "SELECT ";
        
        $default = "DISTINCT u.*, ";
        
        // Build select fields
        if (!$fields || ($fields && !$overwrite) )
        {
            $sql['select'] .= $default;
        }
        
        if (!is_null($fields))
        {
            foreach($fields as $field)
            {
                $sql['select'] .= $field . ", ";
            }
        }
        
        // Trim the last comma if we used specified fields
        $sql['select'] = rtrim($sql['select'], ", ") . " ";
        $sql['from'] = " FROM {user} u ";
        $sql['join'] = " INNER JOIN {role_assignments} r ON r.userid = u.id ";
        $sql['join'] .= " INNER JOIN {context} cx ON cx.id = r.contextid ";
        $sql['where'] = " WHERE cx.instanceid = ? AND r.roleid = ? ";
        
        // Don't order if we are doing a COUNT, doesn't like it
        if ( stripos($sql['select'], "COUNT") === false ){
            $sql['order'] = " ORDER BY u.lastname, u.firstname ";
        }
        
        $params[] = $courseID;
        $params[] = $roleID;
        
        
        // Are we filtering the results at all?
        $filterFirst = optional_param('filterFirst', false, PARAM_ALPHA);
        $filterLast = optional_param('filterLast', false, PARAM_ALPHA);
        $filterSearch = optional_param('filterSearch', false, PARAM_TEXT);
        
        // First Name
        if ($filterFirst){
            $sql['where'] .= " AND (u.firstname LIKE ?) ";
            $params[] = "{$filterFirst}%";
        }
        
        // Last Name
        if ($filterLast){
            $sql['where'] .= " AND (u.lastname LIKE ?) ";
            $params[] = "{$filterLast}%";
        }
        
        // Search box
        if ($filterSearch){
            $sql['where'] .= " AND ( u.username LIKE ? OR u.lastname LIKE ? OR u.firstname LIKE ? OR CONCAT(u.firstname, ' ', u.lastname) LIKE ? ) ";
            $params[] = "%{$filterSearch}%";
            $params[] = "%{$filterSearch}%";
            $params[] = "%{$filterSearch}%";
            $params[] = "%{$filterSearch}%";
        }
        
        
        
        // If there is a limit array        
        $sqlLimit = array(0, 0);
        
        if ($limit){
            if (is_array($limit)){
                $sqlLimit = $limit;
            } else {
                $sqlLimit[0] = 0;
                $sqlLimit[1] = $limit;
            }
        }
        
        $fullSQL = implode(" ", $sql);
                        
        $records = $this->DB->get_records_sql($fullSQL, $params, $sqlLimit[0], $sqlLimit[1]);
                        
        return $records;
        
    }
    
    
    
    
    
//    
//    /**
//     * Get the courses a personal tutor is assigned to (in the sense that they have been assigned to all students on given course)
//     * @param int $userID
//     */
//    public function getTutorsAssignedCourses($userID = null)
//    {
//     
//        global $USER;
//        
//        if (is_null($userID)) $userID = $USER->id;
//        
//        $sql = array();
//        $params = array();
//        
//        $sql['select'] = " SELECT DISTINCT c.id, c.shortname, c.fullname ";
//        $sql['from']   = " FROM {lbp_tutor_assignments} a ";
//        $sql['join']   = " INNER JOIN {course} c ON c.id = a.courseid ";
//        $sql['where']  = " WHERE a.tutorid = ? AND a.courseid IS NOT NULL ";
//        $sql['order']  = " ORDER BY c.fullname, c.shortname ";
//        
//        $params[] = $userID;
//        $fullSQL = implode(" ", $sql);
//        
//        $records = $this->DB->get_records_sql($fullSQL, $params);
//        
//        return $records;
//        
//    }
//    
//    /**
//     * Get a list of the groups on a course, which a given tutor is assigned to
//     * @param int $tutorID
//     * @param int $courseID
//     */
//    public function getTutorsAssignedGroups($userID, $courseID)
//    {
//        
//        global $USER;
//        
//        if (is_null($userID)) $userID = $USER->id;
//        
//        $sql = array();
//        $params = array();
//        
//        $sql['select'] = " SELECT g.id, g.name ";
//        $sql['from'] = "   FROM {lbp_tutor_assignments} a ";
//        $sql['join'] = "   INNER JOIN {groups} g ON g.id = a.groupid ";
//        $sql['where'] = "  WHERE a.tutorid = ? AND a.courseid = ? ";
//        $sql['order'] = "  ORDER BY g.name ";
//        
//        $params[] = $userID;
//        $params[] = $courseID;
//        $fullSQL = implode(" ", $sql);
//        
//        $records = $this->DB->get_records_sql($fullSQL, $params);
//                
//        return $records;
//    }
//    
//    
//    
    
    
    
    /**
     * Is a given user a mentee of the teacher?
     * @param type $menteeID
     */
    public function hasTutorSpecificMentee($menteeID, $tutorID = null)
    {
                
        if (is_null($tutorID)) $tutorID = $USER->id;
        
        $role = $this->getRole( \ELBP\PersonalTutor::getPersonalTutorRole() );
        
        $params = array($tutorID, CONTEXT_USER, $role, $menteeID);
        $sql = array();
        
        $sql['select'] = "SELECT ";
        $sql['select'] .= "COUNT(DISTINCT u.id)";
        $sql['from']  = " FROM {role_assignments} r ";
        $sql['join']  = " INNER JOIN {context} c ON c.id = r.contextid ";
        $sql['join'] .= " INNER JOIN {user} u ON u.id = c.instanceid ";
        $sql['where'] = " WHERE r.userid = ? AND c.contextlevel = ? AND r.roleid = ? AND u.id = ?";
        
        $fullSQL = implode(" ", $sql);
                
        $records = $this->DB->count_records_sql($fullSQL, $params);
                
        return ($records > 0);    
        
        
    }
    
    
    /**
     * Check if a given teacher is assigned (as a teacher) to any courses
     * @param type $userID
     * @return int
     */
    public function hasTeacherCourses($userID = null)
    {
        
        global $USER;
        
        if (is_null($userID)) $userID = $USER->id;
        
        $records = $this->getTeachersCourses($userID);
                
        return (count($records) > 0) ? true : false;
                
    }
    
    /**
     * Check if a given tutor is assigned to any mentees
     * @param type $userID
     * @return int
     */
    public function hasTutorMentees($userID = null)
    {
        
        global $USER;
        
        if (is_null($userID)) $userID = $USER->id;
        
        // This could potentially be a much longer list than checking the courses, so going to do a count SQL instead of counting array records
        $count = $this->countTutorsMentees($userID);
                
        return ($count > 0) ? true : false;
        
    }
    
    /**
     * Check if a given user is assigned to any students as an Additioanl Support Lecturer
     * @global type $USER
     * @param type $userID
     * @return type
     */
    public function hasAslStudents($userID = null)
    {
        
        global $USER;
        
        if (is_null($userID)) $userID = $USER->id;
        
        $count = $this->countAslStudents($userID);
                
        return ($count > 0) ? true : false;
    }
        
    
    
    /**
     * Search for a staff member with the given search term
     * @param type $search
     */
    public function searchUser($search, $where = null, $limit = null)
    {
        
        // Explode by semi colon
        $explode = explode(";", $search);        
        $results = array();
        
        foreach($explode as $search)
        {
        
            $whereClause = '';
            
            $params = array();
            $params[] = "%{$search}%";
            $params[] = "{$search}%";
            $params[] = "%{$search}%";
            $params[] = "{$search}%";

            if (!is_null($where)){
                $whereClause = " AND {$where}";
            }
            
            $limitFrom = (is_null($limit)) ? null : 0;
            $limitTo = (is_null($limit)) ? null : $limit;

            $records = $this->DB->get_records_select("user", 
                    "(username LIKE ? OR lastname LIKE ? OR CONCAT(firstname, ' ', lastname) LIKE ? OR firstname LIKE ?)
                     AND deleted = 0 {$whereClause}", $params, "lastname, firstname", "*", $limitFrom, $limitTo);

            if ($records)
            {
                foreach($records as $record)
                {
                    $results[$record->id] = $record;
                }
            }
                     
        }
                 
                 
        return $results;
        
    }
    
    /**
     * Return a whole list of users, not filtered by anything
     * @param type $limit
     */
    public function getUsers($limit = null)
    {
        
        $limitFrom = (is_null($limit)) ? null : 0;
        $limitTo = (is_null($limit)) ? null : $limit;
        
        return $this->DB->get_records("user", array("deleted" => 0), "lastname ASC, firstname ASC, username ASC", "*", $limitFrom, $limitTo);
        
    }
    
    /**
     * Get the username of a given userid
     * @param type $userID
     * @return type
     */
    public function getUserName($userID)
    {
        $record = $this->DB->get_record_select("user", "id = ?", array($userID), "username");
        return ($record) ? $record->username : false;
    }
    
    
    /**
     * Get the fullname of a given userid
     * @param type $userID
     * @return type
     */
    public function getFullName($userID, $by = 'id')
    {
        $record = $this->DB->get_record_select("user", "{$by} = ?", array($userID), "CONCAT(firstname, ' ', lastname) as fullname");
        return ($record) ? $record->fullname : false;
    }
        
    
    /**
     * Check if a given userID is a valid user in the mdl_user table
     * @param type $userID
     * @return type
     */
    public function isValidUser($userID)
    {
        return $this->DB->get_record_select("user", "id = ? AND deleted = 0 ", array($userID), "id");
    }
    
    /**
     * Search for a course with a given search term
     * @param type $search
     */
    public function searchCourse($search, $catID = null)
    {
        
        $explode = explode(";", $search);
        
        $results = array();
        
        foreach($explode as $search)
        {
        
            $or = '';

            $params = array();
            $params[] = "%{$search}%";
            $params[] = "%{$search}%"; 

            // If we search with a hash at the beginning, we can also search for a course id, e.g. #4
            if (substr($search, 0, 1) == '#'){
                $or = "OR id = ?";
                $params[] = substr($search, 1);
            }        

            if (is_null($catID)){

                $records = $this->DB->get_records_select("course", 
                                                    "shortname LIKE ? OR fullname LIKE ? {$or}", 
                                                    $params, 
                                                    "fullname, shortname",
                                                    "id, shortname, fullname");

            } else {

                array_unshift($params, $catID);
                $records = $this->DB->get_records_select("course", 
                                                    "category = ? AND (shortname LIKE ? OR fullname LIKE ? {$or})", 
                                                    $params, 
                                                    "fullname, shortname",
                                                    "id, shortname, fullname");

            }
            
            
            if ($records)
            {
                foreach($records as $record)
                {
                    $results[$record->id] = $record;
                }
            }
            
        
        }
        
        return $results;
        
    }
    
        
    
    /**
     * Get a list of all the plugin groups in the DB.
     * @param type $onlyEnabled Only return those which are enabled
     */
    public function getPluginGroups($layoutID, $onlyEnabled = true)
    {
        
        if ($onlyEnabled){
            $records = $this->DB->get_records_select("lbp_plugin_groups", "layoutid = ? AND enabled = 1", array($layoutID), "ordernum ASC");
        }
        else
        {
            $records = $this->DB->get_records_select("lbp_plugin_groups", "layoutid = ?", array($layoutID), "ordernum ASC");
        }
        
        return $records;
        
    }
    
    /**
     * Get all the plugins
     * @param int $groupID If supplied, only get plugins in this group
     * @return type
     */
    public function getPlugins($groupID = null)
    {
        
        $params = array();
        if (!is_null($groupID)){ 
            $where = "groupID = ?";
            $params[] = $groupID;
        }
        else
        {
            $where = "groupID IS NULL";
        }
        
        return $this->DB->get_records_select("lbp_plugins", "{$where}", $params, "ordernum ASC");
        
    }
    
    
    
    
}