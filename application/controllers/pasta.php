<?php 

/**
 * Script to prevent direct URL access to this file.
 * Should include at every beginning of file.
 */
if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Pasta main controller
 * Author: Charles
 */
class Pasta extends MY_Controller {
	function Pasta() {
		parent::__construct();	
		
		// load the login model
		$this->load->model('User', 'logins_table');	
	}	
	
	public function index() {			
		/**
		 * Main site title
		 *-----------------*/
		$data['title'] = 'Welcome to P.A.S.T.A.';


		// check if the user is already logged in
		if ($this->session->userdata('logged_in')) {
			// if user is already logged in, do an immediate redirect
			redirect('profile', 'refresh');
		} else {
			// display the main view
			$this->put('main', $data);
		}
	}
	
	// Registration function
	public function register() {
		// setup and commit registration login validations criteria
		$this->form_validation->set_rules('student_id', 'Student ID', 
					'required|exact_length[7]|numeric|trim|xss_clean');

		$this->form_validation->set_rules('password', 'Password', 
					'required|min_length[6]|alpha_numeric');

		$this->form_validation->set_rules('password_confirm', 'Password Confirm',
					'required|min_length[6]|alpha_numeric|matches[password]');

		$this->form_validation->set_rules('first_name', 'First Name', 
					'callback_alpha_whitespace');
		$this->form_validation->set_rules('last_name', 'Last Name', 
					'callback_alpha_whitespace');
		
		// validate the form with the registration rules
		if ($this->form_validation->run() == FALSE) {
			$this->index();
			//$this->load->view('/scrape_views/form');
		} else {
			// query the database to check if there 
			// is an user with this POST student id
			$query = $this->db->get_where('logins', 
							array('student_id' => $this->input->post('student_id')));
			// echo $query->num_rows();

			if ($query->result()) {
				echo "USER ALREADY REGISTERED";
			} else {
				echo "USER SUCCESFULLY REGISTERED";
				$this->db->insert('logins', 
							array(
								'student_id' => $this->input->post('student_id'),
								// store user password in sha1
								'password' =>  $this->encrypt->sha1(
													$this->input->post('password')),
								'first_name' => $this->input->post('first_name'),
								'last_name' => $this->input->post('last_name'),
								'program' => $this->input->post('program')
								));
				
				// update session with appropriate information and
				// set user to be logged in
				$this->session->set_userdata(array(
					'student_id' => $this->input->post('student_id'),
					'first_name' => $this->input->post('first_name'),
					'last_name'  => $this->input->post('last_name'),
					'logged_in'  => true
				));
			}
		}
	}

	public function logout() {
		// unassign logged_in boolean
		$this->session->set_userdata('logged_in', FALSE);
		// redirect to pasta main
		redirect('pasta', 'refresh');
	}

	public function user_login() {
		// setup login form validation
		$this->form_validation->set_rules(
			'login_student_id', 
			'Student ID', 
			'required|trim|xss_clean|exact_length[7]|numeric'
		); 

		$this->form_validation->set_rules(
			'login_password', 
			'Password', 
			'required|trim|xss_clean|required|min_length[6]|alpha_numeric'
		);

		if ($this->form_validation->run() == FALSE) {
			$this->index();
		} else {
			// from http://www.haughin.com/2008/02/handling-passwords-in-codeigniter/
			if ($this->logins_table->find_by_login_info(
					$this->input->post('login_student_id'),
					$this->encrypt->sha1($this->input->post('login_password')))) {

				// ------------------------------------
				// initialize sessions
				// ------------------------------------

				$user_data = $this->logins_table->find_by_student_id(
					$this->input->post('login_student_id'));

				$this->session->set_userdata(array(
					'student_id' => $user_data['student_id'],
					'first_name' => $user_data['first_name'],
					'last_name'  => $user_data['last_name'],
					'logged_in'  => true
				));

				// redirect to user profile page
				redirect('profile', 'redirect');
			} else {
				echo "Sorry, we could not find you in our records. "
				 	 . ", should you "
				 	 . anchor(site_url('pasta'), 'register first')
					 . "?";
			}
		}
	}

	/**
	 * Callback function for a alpha_whitespace form validation
	 */
	public function alpha_whitespace($str) {
		$this->form_validation->set_message(
				'alpha_whitespace', 'Your mom gave me this custom error message.');

		if (preg_match("/^([-a-z-\s])+$/i", $str)) {	
			return TRUE;
		} else {
			return FALSE;
		}
	}
}

/* End of file Pasta.php */
/* Location: ./application/controllers/welcome.php */