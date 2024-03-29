<?php

/*
   Query, insertion and deletion of record related to course table
   Method to retrieve specific course.
  Authors:  Eric Rideough
            Duc Hoang Michel Pham
            Charles Yang
*/

class Course extends CI_Model {
    //NOTE: ADD TITLE COLUMN FOR COURSE TABLE
    function get_all_courses() {
        $this->db->select("id, code, number, credit, title");
        $query = $this->db->get('courses');
        return $query->result_array();
    }
    
    /**
     * Get all the courses that is not taken by a student
     * @param  $student_id
     * @return All the untaken courses as array
     */
    function get_all_untaken_courses($student_id) {
        $custom_query = "SELECT courses.* FROM courses LEFT JOIN (
                            SELECT * FROM completed_courses
                            WHERE student_id = $student_id)
                        AS course_taken_id 
                        ON course_taken_id.course_id = courses.id IS NULL
                        GROUP BY courses.id";
        return $this->db->query($custom_query)->result_array();
    }
    
    function find_by_id($id) {
        $query = $this->db->get_where('courses', array('id' => $id));
        // row_array returns a single result in a pure array.
        // Better for generating single results.
        return $query->row_array();
    }
    
    function find_by_code($code) {
        $query = $this->db->get_where('courses', array('code' => $code));
        return $query->result_array();
    }
    
    function find_by_code_number($code, $number) {
        $query = $this->db->get_where('courses', array('code' => $code, 'number' => $number));
        return $query->row_array();
    }
    
    // Overload find_by_code_number function taking course code
    // and number as an array
    function find_by_code_number_array($course) {
        // If $course array has "code" and "number" key, search for that
        // Else assume "0" is code and "1" is number.
        if (array_key_exists('code', $course) && array_key_exists('number', $course)) return $this->find_by_code_number($course['code'], $course['number']);
        else return $this->find_by_code_number($course['0'], $course['1']);
    }
    
    /**
     *
     * @param: course - Course information as array 'code' and 'number'
     * @return: course_id
     */
    function get_course_id($course) {
        $result = $this->find_by_code_number_array($course);
        return (isset($result['id']) ? $result['id'] : FALSE);
    }
    
    /*------------------------------------------------------*/
    /* Insert course information functions
    /*------------------------------------------------------*/
    function insert_course($course) {
        // Deprecated - They are defaulted in MySQL as NULL -- Charles
        // // initialize course variables
        // $course_variables = array(
        //     'code'    => NULL,
        //     'number'  => NULL,
        //     'title'   => NULL,
        //     'credit'  => NULL
        // );
        // since the course is assumed to be fed as an array, we check if particular
        // array key exists, if not, the value remains null
        foreach($course as $variable => $value) if (array_key_exists($variable, $course)) $course_variables[$variable] = $course[$variable];
        // insert the value into the table
        $this->db->insert('courses', $course_variables);
    }
    
    function get_all_courses_allowed($student_id) {
        $this->load->model("prerequisite", "prerequisite_model");
        $this->load->model('CompletedCourse', 'completed_courses');
        
        $courses = $this->get_all_untaken_courses($student_id);
        $completedCourses = $this->completed_courses->find_by_student_id($student_id);
        $completedCourses = $this->map_course_id($completedCourses);
        
        foreach($courses as $key => $course) {        
            // If the student has already completed the courses, they can't take it again. Remove it.
            if ( in_array( $course['id'], $completedCourses ) ) {
                unset( $courses[$key] );
            }
            else {
                // Check if the student has the prerequisites for the course.
                // If they don't have the prereqs, remove that course from the list.
                
                // Retrieve array of prerequisites for the current course.
                $prerequisites = $this->prerequisite_model->find_by_course_id($course["id"]);
                // Loops through each prequisite of the course.
                foreach($prerequisites as $prerequisite) {
                    if (isset($prerequisite["required_course_id"])) {
                        // Check prereqs against student completed courses. If the student doesn't have the prereqs, remove course from courses array.
                        if (!in_array($prerequisite["required_course_id"], $completedCourses)) {
                            unset($courses[$key]);
                        }
                    }
                }
            }
        }
        $this->sort_courses_by_type($courses);
        $courses = array_values($courses); // reindex them
        return $courses;
    }
    
    private function map_course_id($array) {
        //this function is called to return a 1-dimensional consisting course id only.
        function return_course_id($record) { // Function within a Function: A hack for array_map callback problem with models
            return $record["course_id"];
        }
        return $courseCompleted = array_map("return_course_id", $array);
    }
    
    function sort_courses_by_type($courses) {
        $this->config->load('pasta_constants/OPTION_COURSES');
        $this->config->load('pasta_constants/soft_eng_courses');
        $soft_eng_courses = $this->config->item('SOFT_ENG_COURSES');
        $option_courses = $this->config->item('OPTION_COURSES');
        //Core Soft.Eng
        $core = $this->map_core_courses($soft_eng_courses);
        //Basic Science
        $basicScience = $this->map_optional_courses($option_courses["Basic Science"]);
        //General Electives
        $socialScience = $this->map_optional_courses($option_courses["General Electives"]["Social Sciences"]);
        $humanities = $this->map_optional_courses($option_courses["General Electives"]["Humanities"]);
        $otherGeneral = $this->map_optional_courses($option_courses["General Electives"]["Others"]);
        //Technical Electives
        $CG = $this->map_optional_courses($option_courses["Technical Electives"]["Computer Games (CG)"]);
        $web = $this->map_optional_courses($option_courses["Technical Electives"]["Web Services and Applications"]);
        $REA = $this->map_optional_courses($option_courses["Technical Electives"]["Real-Time, Embedded, and Avionics Software (REA)"]);
        $otherElectives = $this->map_optional_courses($option_courses["Technical Electives"]["Others"]);
    }
    
    function map_core_courses($array) {
        //made specific to match for item SOFT_ENG_COURSES array structure
        $list = array();
        foreach($array as $year) {
            foreach($year as $season) {
                foreach($season as $course) {
                    array_push($list, $course[0] . "" . $course[1]);
                }
            }
        }
        return $list;
    }
    
    function map_optional_courses($array) {
        $list = array();
        foreach($array as $course) {
            array_push($list, $course[0] . "" . $course[1]);
        }
        return $list;
    }
}

?>