<?php
/*
Author: Duc Hoang Michel Pham
*/
class ScheduleBuilder extends MY_Controller {
    //DATABASE PROBLEMS
    /* Duplicated courses causes alot of prerequisite and associated lecture problems.
    */
    function __construct() {
        parent::__construct();
        $this->load->model('Course');
        $this->load->model('ScheduleBuilder_model');
        if (!$this->session->userdata('logged_in')) {
            // if is not logged in, redirect user to the login page
            redirect('pasta', 'refresh');
        }
    }
    public function index() {
        $data['title'] = "P.A.S.T.A. - Course Registration";
        // load the preference pane by default
        $this->put('course_registration_preference_view', $data);
        //$this->listAllAllowedCourses();
        
    }
    public function listAllCourses() {
        $this->form_validation->set_rules("time", "Time", "required");
        if ($this->form_validation->run() == FALSE) {
            $form['url'] = 'schedulebuilder/listAllCourses';
            $this->load->view('/scheduleBuilder_views/preference', $form);
        } else {
            $id = 3; //temporary, this should be retrieve from session.
            $form_data = $this->input->post(); //array( time => , longWeekend, season => , year =>
            $courses = $this->Course->get_all_courses();
            $courses = $this->ScheduleBuilder_model->filter_courses_by_season($courses, $form_data["season"]);
            $courses = $this->ScheduleBuilder_model->filter_courses_by_preference($courses, $form_data["time"], $form_data["long_weekend"], $form_data["season"]);
            $data['courseList'] = $courses;
            $data['season'] = $form_data["season"];
            $data['preference'] = $form_data;
            $this->load->view('/scheduleBuilder_views/listAllCourses.php', $data);
        }
    }
    public function listAllAllowedCourses() {
        $id = $this->session->userdata['student_id'];
        $form_data = $this->input->post();
        $form_data['time'] = $this->input->post('time');
        // compute season based on current time
        // before september, can only register for fall
        // after september, can only register for winter
        $form_data['season'] = (date('n') > '9' ? '4' : '2');
        $form_data['long_weekend'] = ($this->input->post('long_weekend') ? 1 : 0);
        //First get all courses that user have pre-requisite for
        //Then remove all courses that doesn't lecture in specified season
        //Last remove all courses that doesn't meet his time constraint
        $courses = $this->Course->get_all_courses_allowed($id);
        $courses = $this->ScheduleBuilder_model->filter_courses_by_season($courses, $form_data["season"]);
        $courses = $this->ScheduleBuilder_model->filter_courses_by_preference($courses, $form_data["time"], $form_data["long_weekend"], $form_data['season']);
        $courses = $this->ScheduleBuilder_model->sort_courses_by_type($courses);
        $data['course_list'] = $courses;
        $data['season'] = $form_data["season"];
        $data['preference'] = $form_data;
        $data['title'] = "P.A.S.T.A. - Course Registration";
        $this->put('course_registration_selection_view', $data);
    }
    public function generate_schedule() {
        $form_data = $this->input->post();
        //If no post data, use session stored data
        if (empty($form_data)) {
            $registered_courses = $this->session->userdata['registered_courses'];
            if (empty($registered_courses)) {
                redirect($_SERVER['HTTP_REFERER']);
            } else {
                $form_data = $registered_courses;
            }
        }
        $this->session->set_userdata("registered_courses", $form_data);
        $possible_sequence = $this->get_possible_sequence($form_data);
        $data = array("possible_sequence" => $possible_sequence, "season" => $form_data["season"]);
        $data['title'] = 'P.A.S.T.A. - Schedule Selection';
        $this->put("/scheduleBuilder_views/generated_schedule.php", $data);
    }
    public function save_schedule() {
        $this->load->model("schedule");
        $student_id = $this->session->userdata['student_id'];
        $schedule = $this->input->post("courses");
        $season = $this->input->post("season");
        $year = date("Y");
        if ($season == 2) {
            $year = (date('n') > '9' ? $year + 1 : $year);
        }
        $this->schedule->new_and_update_schedule($schedule, $student_id, $season, $year);
        redirect('profile', 'refresh');
    }
    private function get_possible_sequence($form_data) {
        $courses = array();
        foreach($form_data["registered_courses"] as $course_id):
            $the_course = $this->Course->find_by_id($course_id);
            array_push($courses, $the_course);
        endforeach;
        $courses = $this->ScheduleBuilder_model->filter_courses_by_preference($courses, $form_data["time"], $form_data["long_weekend"], $form_data["season"]);
        return $this->ScheduleBuilder_model->generate_possibility($courses);
    }
}
// End of ScheduleBuilder.php