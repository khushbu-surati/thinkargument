<?php
/**
 * Recommended way to include parent theme styles.
 * (Please see http://codex.wordpress.org/Child_Themes#How_to_Create_a_Child_Theme)
 *
 */  

add_action( 'wp_enqueue_scripts', 'hello_elementor_child_style' );
function hello_elementor_child_style() {
	wp_enqueue_style( 'parent-style', get_template_directory_uri() . '/style.css' );
	wp_enqueue_style( 'child-style', get_stylesheet_directory_uri() . '/style.css', array('parent-style'), time() );
    wp_enqueue_style( 'parent-style-min', get_stylesheet_directory_uri() . '/style.min.css',array(),time() );
    wp_enqueue_script( 'custom-js', get_stylesheet_directory_uri() . '/assets/js/custom.js', array('jquery-core'), time());
    wp_localize_script('custom-js', 'ajax_object', array(
        'ajax_url' => admin_url('admin-ajax.php')
    ));
}

function login_api_route(): void
{
    register_rest_route( 'course/thinkeranalytix/v1', '/login/', array(
        'methods'  => 'POST',
        'callback' => 'login_api_handler'
    ) );
    register_rest_route( 'course/thinkeranalytix/v1', '/groups/', array(
        'methods'  => 'POST',
        'callback' => 'get_teacher_groups'
    ) );
    register_rest_route( 'course/thinkeranalytix/v1', '/groups/add', array(
        'methods'  => 'POST',
        'callback' => 'create_group'
    ) );
    register_rest_route( 'course/thinkeranalytix/v1', '/groups/getcode', array(
        'methods'  => 'POST',
        'callback' => 'generate_group_code'
    ) );
    register_rest_route( 'course/thinkeranalytix/v1', '/groups/addstudent', array(
        'methods'  => 'POST',
        'callback' => 'add_user_in_group'
    ) );
    register_rest_route( 'course/thinkeranalytix/v1', '/groups/tags', array(
        'methods'  => 'POST',
        'callback' => 'get_student_group_tags'
    ) );
    register_rest_route( 'course/thinkeranalytix/v1', '/student/points', array(
        'methods'  => 'POST',
        'callback' => 'update_student_points'
    ) );
    //get_student_group_tags
}
add_action( 'rest_api_init', 'login_api_route' );

function login_api_handler( $request ): WP_Error|WP_User
{
    $creds = array(
        'user_login'    => $request->get_param( 'username' ),
        'user_password' => $request->get_param( 'password' ),
        'remember'      => true
    );

    $user = wp_signon( $creds, false );

    if ( is_wp_error( $user ) ) {
        return new WP_Error( 'login_failed', 'Invalid username or password', array( 'status' => 401 ) );
    } else {
        return $user;
    }
}
function get_teacher_groups( $request )
{

    $teacher_id = $request->get_param( 'user_id' );


    $args = array(
        'post_type' => 'groups',
        'meta_query' => array(
            array(
                'key' => 'groups_teachers',
                'value' => $teacher_id,
                'compare' => 'LIKE',
            ),
        ),
        'posts_per_page' => -1,
    );

    $query = new WP_Query($args);

    $groups = array();

    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();
            $teacher_ids = explode( ',',get_post_meta(get_the_ID(), 'groups_teachers', true) );
            if(in_array($teacher_id, $teacher_ids)) 
            {
                $group_data = array(
                        'id' => get_the_ID(),
                        'title' => get_the_title(),
                        'content' => get_the_content(),
                        'teachers' => get_post_meta(get_the_ID(), 'groups_teachers', true),
                        'groupcode' => get_post_meta( get_the_ID(), 'groups_groupcode', true),
                        'has_am_access' => get_post_meta(get_the_ID(), 'groups_has_am_access', true),
                        'students' => get_users_in_group(get_the_ID()),
                    // Add any other group data you want to include
                );
                $groups[] = $group_data;
            }   

            
        }
    }

    // Restore the original post data
    wp_reset_postdata();

    return $groups;
}
function get_users_in_group($groupid) {
    global $wpdb;

    $query = $wpdb->prepare("
        SELECT g.groupid, g.studentid
        FROM {$wpdb->prefix}pccoregroupstud AS g
        WHERE g.groupid = %d
    ", $groupid);
    $results = $wpdb->get_results($query);
    $users = array();

    foreach ($results as $result) {
        $user = get_user_by('ID', $result->studentid);
       
      
        if ($user) {
            $table_name = $wpdb->prefix . 'pccorestudpoints';
            $user_points = 'SELECT totalpoints FROM '.$table_name.' WHERE studentid = "'.$user->id.'"';
            $user_points = $wpdb->get_row( $user_points);
          
            $user = array(
                'id'=> $user->id,
                'first_name' => $user->first_name,
                'last_name'  => $user->last_name,
                'email'      => $user->user_email,
                'display_name' => $user->display_name,
                'username' => $user->user_login,
                'total_points' => $user_points->totalpoints
            );
            $users[] = $user;
        }
    }
    return $users;
}

function add_user_in_group($request) {
    $group_code = $request->get_param( 'group_code' );
    $student_id = $request->get_param( 'student_id' );
    
    $response = array(
        'message' => 'Invalid Perameter',
        'status'  => 422
    );
    if(empty($student_id)){
        $response['message'] = 'Please provide Student Id';
        return $response;
    }
    if(empty($group_code)){
        $response['message'] = 'Please provide Group Code';
        return $response;
    }
    if(function_exists('groupstud_add_data')){
      
        add_group_to_student($group_code, $student_id);
        $response['message'] = 'Student added in group successfully';
        $response['status'] = 200;
        return $response;
    }
    $response['message'] = 'Please activate pc core plugin.';
    $response['status'] = 500;
    return $response;

}

function add_group_to_student($groupcode, $studentid){
    global $wpdb;
    $table_name = $wpdb->prefix . 'postmeta';
    //echo $group_code;
    $meta_value = $groupcode;
  
    $u = new WP_User( $studentid );
    $u->add_role( 'student' );
    $prepare_guery = 
    $wpdb->prepare( "SELECT post_id FROM " . 
        $table_name . 
        " where meta_key ='groups_groupcode' and meta_value = '%s'", 	$meta_value );
    $get_values = $wpdb->get_col( $prepare_guery );   
   
    foreach ($get_values as $val){
        $group_id = $val;
       
        groupstud_add_data($group_id, $studentid);
        $meta_key_name = "groups_students"; 	
        //$meta_key_name = ( $role == "Student" ? echo "groups_students"; : echo "groups_teachers"; );
        $meta_key_value = get_post_meta( $group_id, $meta_key_name, true );
        if ( empty($meta_key_value) ) {
            $meta_key_value	= $studentid;
        } else {
            $meta_key_value = $meta_key_value . "," . $studentid;
        }
        //$meta_key_value  = ( empty($meta_key_value) ? echo $user; : echo ($meta_key_value . "," . $user); );
        update_post_meta( $group_id, $meta_key_name, $meta_key_value );
    }

}

function create_group($request){
    $response = array(
        'message' => 'Invalid Perameter',
        'status'  => 422
    );
    $groupstitle = ( null !== $request->get_param('groupstitle'))? esc_attr( $request->get_param('groupstitle') ) : '';
	$groupscode = ( null !==  $request->get_param('groupscode'))? esc_attr( $request->get_param('groupscode') ) : '';
	$groupsdescription = ( null !== $request->get_param('groupsdescription'))? esc_attr($request->get_param('groupsdescription')) : '';
    $user_id = ( null !==  $request->get_param('user_id'))? esc_attr($request->get_param('user_id')) : '';
    if(empty($user_id)){
        $response['message'] = 'Please provide User Id';
        return $response;
    }
    if(empty($groupstitle)){
        $response['message'] = 'Please provide Group Title';
        return $response;
    }
    if(empty($groupscode)){
        $response['message'] = 'Please provide Group Code';
        return $response;
    }
    $my_post = array();
	$my_post['post_title'] = $groupstitle;
	$my_post['post_type'] = 'groups'; 
		
	$my_post['post_status']   = 'publish';
	$my_post['post_author']   = 1;
	$my_post['tags_input'] = '';
	$post_id = wp_insert_post( $my_post );
    if ($post_id) {
        update_post_meta($post_id, 'groups_groupcode', $groupscode, true);
        $roletype = get_user_meta($user_id, 'roletype', true);
        $meta_key_name = "groups_teachers"; 					
        $meta_key_value = get_post_meta( $post_id, $meta_key_name, true );
        $meta_key_value = is_countable($meta_key_value) && sizeof($meta_key_value) > 0 ? $meta_key_value . "," . $user_id : $user_id;
        update_post_meta( $post_id, $meta_key_name, $meta_key_value );
        $response['message'] = 'Group added successfully';
        $response['status'] = 200;
        return $response;
    }
    $response['message'] = 'Internal server Error';
    $response['status'] = 500;
    return $response;
}

function generate_group_code($request){
    global $wpdb;
	$table_name = $wpdb->prefix . 'postmeta';
    $sqlstr = "SELECT meta_value FROM " . $table_name . " WHERE meta_key ='groups_groupcode'";	
	$codes = $wpdb->get_col($sqlstr);
	
	$newgroupcode = substr(md5(time()), 0, 8);
	$doesexit = true;
	while( $doesexit) {
		if ( !in_array($newgroupcode, $codes)  ) {
			$doesexit = false;
		} else {
			$newgroupcode = substr(md5(time()), 0, 8);		
		}
	}
    return array(
        'message' => 'New groupcode generated successfully',
        'code' => $newgroupcode,
        'status' => 200
    );
}

function get_student_group_tags($request){
    global $wpdb;
    $response = array(
        'message' => 'Invalid Parameter',
        'status'  => 422
    );
    $user_id = $request->get_param('user_id');
    
    if (empty($user_id)) {
        $response['message'] = 'Please provide User Id';
        return $response;
    }

    $user_data = get_userdata($user_id);
    
    if (!$user_data) {
        $response = array(
            'message' => 'Invalid User id',
            'status'  => 422
        );
        return $response;
    }

    $user_roles = $user_data->roles;
    $term_names = array();

    if (in_array('student', $user_roles)) {
        $query = $wpdb->prepare("
            SELECT g.groupid, g.studentid
            FROM {$wpdb->prefix}pccoregroupstud AS g
            WHERE g.studentid = %d
        ", $request->get_param('user_id'));

        $results = $wpdb->get_results($query);
        
        foreach ($results as $result) {
            $group_id = $result->groupid;
            $taxonomy = 'types';
            $terms = get_the_terms($group_id, $taxonomy);

            if ($terms && !is_wp_error($terms)) {
                foreach ($terms as $term) {
                    $term_names[] = $term->name;
                }
            }
        }
    }

    if (in_array('teacher', $user_roles)) {
        $query_args = array(
            'post_type' => 'groups',
            'meta_query' => array(
                array(
                    'key' => 'groups_teachers',
                    'value' => $user_id,
                    'compare' => 'LIKE',
                ),
            ),
        );

        $query = new WP_Query($query_args);

        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $group_id = get_the_ID(); // Get the group ID from the current post

                $taxonomy = 'types';
                $terms = get_the_terms($group_id, $taxonomy);

                if ($terms && !is_wp_error($terms)) {
                    foreach ($terms as $term) {
                        $term_names[] = $term->name;
                    }
                }
            }
            wp_reset_postdata();
        }
    }

    $response['message'] = 'Types fetched successfully';
    $response['data'] = $term_names;
    $response['status'] = 200;
    return $response;
}



function compareTotalPoints($a, $b) {
    return $b['total_points'] - $a['total_points'];
}

//Show leader Board
function show_point() {
    $current_user = wp_get_current_user();

    // Check if the current user is an administrator or instructor
    if (in_array('administrator', $current_user->roles) || in_array('teacher', $current_user->roles)) {

        // Fetch groups for the current user
        $groups_query = new WP_Query(array(
            'post_type'      => 'groups', // replace with your actual custom post type
            'posts_per_page' => -1,
            'meta_query'     => array(
                array(
                    'key'   => 'groups_teachers', // replace with your actual meta key for instructor
                    'value' => $current_user->ID,
                ),
            ),
        ));

        if ($groups_query->have_posts()) {
            while ($groups_query->have_posts()) {
                $groups_query->the_post();

                // Display students in the group
                show_students_in_group(get_the_ID());
            }

            wp_reset_postdata();
        } else {
            echo 'No groups found for the current user.';
        }
    } else {
        echo 'You do not have permission to view this information.';
    }
}

function show_students_in_group($group_id) {
    global $wpdb;

    // Fetch students for the given group code
    $meta_key = 'groups_groupcode'; // replace with your actual meta key for group code
    $group_code = get_post_meta($group_id, $meta_key, true);

    $point_data = get_users_in_group($group_id);

    if ($point_data) {
        ?>
        <div class="point-data">
            <table>
                <thead>
                    <th>Username</th>
                    <th>Email</th>
                    <th>Total Points</th>
                </thead>
                <tbody>
                    <?php
                    usort($point_data, 'compareTotalPoints');
                    foreach ($point_data as $user) {
                        echo "<tr>";
                        echo "<td>" . $user['username'] . "</td>";
                        echo "<td>" . $user['email'] . "</td>";
                        echo "<td>" . ($user['total_points'] ?? 0) . "</td>";
                        echo "</tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
        <?php
    } else {
        echo "<p>No Student's in group : $group_code</p>";
    }
}


add_shortcode('show_point_shortcode', 'show_point');

function update_student_points($request){
    $response = array(
        'message' => 'Invalid Perameter',
        'status'  => 422
    );
    if(function_exists('studreasonpoints_add_data')){
        $studentid = $request->get_param('student_id');
        if(empty($studentid)){
            $response['message'] = 'Please provide Student Id';
            return $response;
        }
        $pointreason = $request->get_param('pointreason'); 
        if(empty($pointreason)){
            $response['message'] = 'Please provide Point reason';
            return $response;
        }
        $totalpoints = $request->get_param('totalpoints');
        if(empty($totalpoints)){
            $response['message'] = 'Please provide total points';
            return $response;
        }
        $datesubmitted = $request->get_param('datesubmitted'); 
        if(empty($datesubmitted)){
            $response['message'] = 'Please provide date';
            return $response;
        }


        studreasonpoints_add_data($studentid, $pointreason, $totalpoints, $datesubmitted, 0);
        return array(
            'message' => 'Points added successfully.',
            'status' => 200
        );

    }
}

function send_invoice_email_shortcode($atts) {
    $atts = shortcode_atts( array(
        'group' => '',
        'total_students' => '',
        'email' => '',
        'notes' => '',
    ), $atts );

    // Extract the attribute values
    $group = $atts['group'];
    $total_students = $atts['total_students'];
    $email = $atts['email'];
    $notes = $atts['notes'];
    $current_user = wp_get_current_user();
    $teacher_id = $current_user->ID;

    $args = array(
        'post_type' => 'groups',
        'meta_query' => array(
            array(
                'key' => 'groups_teachers',
                'value' => $teacher_id,
                'compare' => 'LIKE',
            ),
        ),
        'posts_per_page' => -1,
    );

    $query = new WP_Query($args);

    $groups = array();

    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();
            $teacher_ids = explode(',', get_post_meta(get_the_ID(), 'groups_teachers', true));
            if (in_array($teacher_id, $teacher_ids)) {
                $group_data = array(
                    'id' => get_the_ID(),
                    'title' => get_the_title()
                );
                $groups[] = $group_data;
            }
        }
    }

    wp_reset_postdata();

    ob_start();
    ?>
    <form method="post" id="invoice-form">
        <label for="group">Select Group:</label>
        <select name="group" id="group">
            <?php foreach ($groups as $group) : ?>
                <option value="<?php echo esc_attr($group['title']); ?>"><?php echo esc_html($group['title']); ?></option>
            <?php endforeach; ?>
        </select>
        <div id="group-error" class="field-error"></div>
        
        <label for="total_students">Total number of students:</label>
        <input type="number" name="total_students" id="total_students">
        <div id="total_students-error" class="field-error"></div>
        
        <label for="email">Email for Invoice:</label>
        <input type="email" name="email" id="email">
        <div id="email-error" class="field-error"></div>
        
        <label for="notes">Notes:</label>
        <textarea name="notes" id="notes"></textarea>
        <div id="notes-error" class="field-error"></div>
        
        <input type="submit" value="Submit">
    </form>

    <div id="response-message"></div>

    <script>
        jQuery(document).ready(function($) {
            $('#invoice-form').submit(function(e) {
                e.preventDefault();

                // Clear previous error messages
                $('.field-error').empty();

                var formData = $(this).serialize();

                $.ajax({
                    type: 'POST',
                    url: '<?php echo admin_url('admin-ajax.php'); ?>',
                    data: {
                        action: 'send_invoice_email',
                        formData: formData
                    },
                    success: function(response) {
                        if (response.success) {
                            $('#response-message').html('<div class="success-message">' + response.data + '</div>');
                            $('#invoice-form')[0].reset();
                        } else {
                            if (response.data.group_error) {
                                $('#group-error').html(response.data.group_error);
                            }

                            if (response.data.total_students_error) {
                                $('#total_students-error').html(response.data.total_students_error);
                            }

                            if (response.data.email_error) {
                                $('#email-error').html(response.data.email_error);
                            }

                            if (response.data.notes_error) {
                                $('#notes-error').html(response.data.notes_error);
                            }
                        }
                    }
                });
            });
        });
    </script>
    <?php

    return ob_get_clean();
}
add_shortcode('request_invoice', 'send_invoice_email_shortcode');

function send_invoice_email_callback() {
    if (isset($_POST['formData'])) {
        parse_str($_POST['formData'], $formFields);

        // Retrieve form data
        $group = $formFields['group'];
        $total_students = $formFields['total_students'];
        $email = $formFields['email'];
        $notes = $formFields['notes'];

        $errors = array();

        // Validate form fields
        if (empty($group)) {
            $errors['group_error'] = 'Please select a group.';
        }

        if (empty($total_students)) {
            $errors['total_students_error'] = 'Please enter the total number of students.';
        }

        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email_error'] = 'Please enter a valid email address.';
        }

        if (empty($notes)) {
            $errors['notes_error'] = 'Please enter some notes.';
        }

        if (!empty($errors)) {
            // Return the error messages
            wp_send_json_error($errors);
        } else {
            // Email details
            $to = 'priten@thinkeranalytix.org';
            $subject = 'Invoice Request';

            // Build the HTML email body
            $body = '<html><body>';
            $body .= '<h2>Invoice Request</h2>';
            $body .= '<p><strong>Select Group:</strong> ' . $group . '</p>';
            $body .= '<p><strong>Total number of students:</strong> ' . $total_students . '</p>';
            $body .= '<p><strong>Email id:</strong> ' . $email . '</p>';
            $body .= '<p><strong>Notes:</strong> ' . $notes . '</p>';
            $body .= '</body></html>';
            $headers = array('Content-Type: text/html; charset=UTF-8');
            // Send the email
            $sent = wp_mail($to, $subject, $body, $headers);
            // Return a success or error message
            if ($sent) {
                wp_send_json_success('Email sent successfully.');
            } else {
                wp_send_json_error('Failed to send email.');
            }
        }
    }

    //wp_die();
}
add_action('wp_ajax_send_invoice_email', 'send_invoice_email_callback');
add_action('wp_ajax_nopriv_send_invoice_email', 'send_invoice_email_callback');

// Dark mode shortcode
function custom_dark_mode_short_code_func() {
    if(is_user_logged_in()){
        ?>
        <div class="toggle-dark-mode-wrapper">
            <input type="checkbox" class="toggle-dark-mode" <?= is_dark_mode() ? "checked": ''?>><label></label>
        </div>
    <?php
    }

    }
add_shortcode('cust-dark-mode','custom_dark_mode_short_code_func');

//save dark mode value
function save_dark_mode_settings_callback(){
    $checkbox_value = isset($_POST['data']['check_value']) ? $_POST['data']['check_value'] : false;
    $checkbox_value = $checkbox_value == "true" ? true : false;
    $is_user_loggedin = is_user_logged_in();
    $res = false;
    if($is_user_loggedin){
        $res = update_user_meta( get_current_user_id() ,'toggle_dark_mode',$checkbox_value );
    }else{
        $res = setcookie( 'toggle_dark_mode', $checkbox_value, time() + (86400 * 30));
    }
    if ($res) {
        wp_send_json_success('Updated Successfully.');
    } else {
        wp_send_json_error('Something Went Wrong.');
    }
    //wp_die();
}
add_action('wp_ajax_save_dark_mode_settings', 'save_dark_mode_settings_callback');
add_action('wp_ajax_nopriv_save_dark_mode_settings', 'save_dark_mode_settings_callback');

function is_dark_mode(){
    $is_user_loggedin = is_user_logged_in();
    $value = "";
    if($is_user_loggedin){
        $value = get_user_meta( get_current_user_id() ,'toggle_dark_mode',true);
    }else{
        $value = isset($_COOKIE['toggle_dark_mode']) ? $_COOKIE['toggle_dark_mode']: '';
    }
    return $value;
}

//add dark mode to body initially
function dark_mode_body_class($classes) {
    if (! is_front_page()) {
        if(is_dark_mode()){
            $classes[] = 'dark-mode';
        }    
    }
    return $classes;
}
add_filter('body_class', 'dark_mode_body_class');

if( function_exists('acf_add_options_page') ) {
    
    acf_add_options_page(array(
        'page_title'    => 'Theme General Settings',
        'menu_title'    => 'Theme Settings',
        'menu_slug'     => 'theme-general-settings',
        'capability'    => 'edit_posts',
        'redirect'      => false
    ));
    
}

require_once get_stylesheet_directory() . '/includes/post-meta.php';
require_once get_stylesheet_directory() . '/includes/update-student.php';

