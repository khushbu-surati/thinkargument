<?php
// Enqueue the JavaScript file
function enqueue_update_user_script() {
    wp_enqueue_script('update-user', get_template_directory_uri() . '/update-user.js', array('jquery'), null, true);
    wp_localize_script('update-user', 'ajax_object', array('ajax_url' => admin_url('admin-ajax.php')));
}
add_action('wp_enqueue_scripts', 'enqueue_update_user_script');

// Shortcode to display the update form and response for students only
function update_user_form_shortcode() {
    if (is_user_logged_in()) {
        ob_start();
        $current_user = wp_get_current_user();
        ?>
        <div class="student-update">
            <form id="update-user-form" action="" method="post">
                <?php wp_nonce_field('update_user_nonce', 'update_user_nonce'); ?>
                
                <label for="new-email">Email:</label>
                <input type="email" name="new_email" id="new-email"  value="<?php echo esc_attr( get_userdata( get_current_user_id() )->user_email ); ?>">
                
                <label for="new-password">Password:</label>
                <input type="password" name="new_password" id="new-password">
                
                <label for="confirm-new-password">Confirm Password:</label>
                <input type="password" name="confirm_new_password" id="confirm-new-password">
                
                <?php
                    if (current_user_can('teacher')) {
                      
                     ?>
                        <label for="registrationusername">Username <strong>*</strong></label>
                        <input type="text" name="registrationusername" value="<?php echo esc_attr( $current_user->user_login ); ?>">

                        <label for="registrationfirstname">First Name</label>
                        <input type="text" name="registrationfname" value="<?php echo esc_attr( $current_user->first_name ); ?>">

                        <label for="registrationlname">Last Name</label>
                        <input type="text" name="registrationlname" value="<?php echo esc_attr( $current_user->last_name ); ?>">

                        <label for="roletype">Level<strong>*</strong></label>
                        <select name="roletype">
                            <option value="" disabled>Choose One</option>
                            <option value="college" <?php selected( get_user_meta( get_current_user_id(), 'roletype', true ), 'college' ); ?>>College & University</option>
                            <option value="highschool" <?php selected( get_user_meta( get_current_user_id(), 'roletype', true ), 'highschool' ); ?>>Middle & High School</option>
                            <option value="other" <?php selected( get_user_meta( get_current_user_id(), 'roletype', true ), 'other' ); ?>>Other</option>
                        </select>
                        <label for="registrationinstitution">Institution <strong>*</strong></label> 
                        <input type="text" name="registrationinstitution" value="<?php echo esc_attr( get_user_meta( get_current_user_id(), 'institution', true ) ); ?>">
                     <?php
                    }
                ?>
                <input type="submit" value="Update">
            </form>
            <div id="update-user-message"></div> <!-- AJAX response will be displayed here -->
            <div class="error"></div>
            <div class="success"></div>
        </div>
        <script>
            jQuery(document).ready(function($) {
                $('#update-user-form').on('submit', function(event) {
                    event.preventDefault();
                    $('.success').html('');
                    $('.error').html('');
                    // Validate email format

                    // Validate password match
                    var newPassword = $('#new-password').val();
                    var confirmNewPassword = $('#confirm-new-password').val();
                    if (newPassword !== confirmNewPassword) {
                        $('#update-user-message').html('Passwords do not match.');
                        return;
                    }

                    // Show loading indicator
                    $('#update-user-message').html('<div class="loading-indicator">Loading...</div>');

                    var formData = $(this).serialize();

                    $.ajax({
                        type: 'POST',
                        url: ajax_object.ajax_url,
                        data: formData + '&action=update_user_ajax',
                        success: function(response) {
                            $('#update-user-message .loading-indicator').remove();
                            if(response.status){
                                $('.success').html(response.message);
                            }else{
                                $('.error').html(response.message);
                            }
                           
                        }
                    });
                });

                // Validate email format function
                function isValidEmail(email) {
                    var emailRegex = /^[a-zA-Z0-9._-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,4}$/;
                    return emailRegex.test(email);
                }
            });
        </script>
        <?php
        return ob_get_clean();
    } else {
        return "This shortcode is only available for logged-in students.";
    }
}
add_shortcode('update_user_form', 'update_user_form_shortcode');

// Handle AJAX request for updating user data
// function update_user_ajax_handler() {
//     check_ajax_referer('update_user_nonce', 'update_user_nonce');

//     $response = array(); // Initialize response array

//     if (is_user_logged_in()) {
//         $current_user = wp_get_current_user();

//         $new_email = sanitize_email($_POST['new_email']);
//         $new_password = sanitize_text_field($_POST['new_password']);
//         $confirm_new_password = sanitize_text_field($_POST['confirm_new_password']);

//         if (!empty($new_email)) {
//             $existing_user = email_exists($new_email);
//             if ($existing_user && $existing_user !== $current_user->ID) {
//                 $response['email'] = "This email is already in use by another user.";
//             } else {
//                 wp_update_user(array('ID' => $current_user->ID, 'user_email' => $new_email));
//                 $response['email'] = "Email updated successfully.";
//             }
//         }

//         if (!empty($new_password) && !empty($confirm_new_password) && $new_password === $confirm_new_password) {
//             wp_set_password($new_password, $current_user->ID);
//             $response['password'] = "Password updated successfully.";
//         } elseif (!empty($new_password) || !empty($confirm_new_password)) {
//             $response['password'] = "Passwords do not match.";
//         }
//     } else {
//         $response['error'] = "You must be logged in to update your information.";
//     }

//     wp_send_json($response); // Send JSON response
// }
// add_action('wp_ajax_update_user_ajax', 'update_user_ajax_handler');
// add_action('wp_ajax_nopriv_update_user_ajax', 'update_user_ajax_handler'); // For non-logged-in users

// Handle AJAX request for updating user data
function update_user_ajax_handler() {
    check_ajax_referer('update_user_nonce', 'update_user_nonce');

    $response = array(); // Initialize response array

    if (is_user_logged_in()) {
        $current_user = wp_get_current_user();

        $new_email = sanitize_email($_POST['new_email']);
        $new_password = sanitize_text_field($_POST['new_password']);
        $confirm_new_password = sanitize_text_field($_POST['confirm_new_password']);
        $response['status'] = false;
        if (!empty($new_email)) {
            $existing_user = email_exists($new_email);
            if ($existing_user && $existing_user !== $current_user->ID) {
                
                $response['message'] = "This email is already in use by another user.";
            } else {
                wp_update_user(array('ID' => $current_user->ID, 'user_email' => $new_email));
              
            }
        }

        if (!empty($new_password) && !empty($confirm_new_password) && $new_password === $confirm_new_password) {
            wp_set_password($new_password, $current_user->ID);
            $response['status'] = true;
            $response['message'] = "User details Updated Successfully.";
        } elseif (!empty($new_password) || !empty($confirm_new_password)) {
            $response['message'] = "Passwords do not match.";
        }

        // Update user meta for teachers
        if (current_user_can('teacher')) {
            update_user_meta($current_user->ID, 'roletype', sanitize_text_field($_POST['roletype']));
            update_user_meta($current_user->ID, 'institution', sanitize_text_field($_POST['registrationinstitution']));
            wp_update_user(array('ID' => $current_user->ID, 'first_name' => sanitize_text_field($_POST['registrationfname']), 'last_name' => sanitize_text_field($_POST['registrationlname'])));
            $response['status'] = true;
            $response['message'] = "User details Updated Successfully.";
        }
    } else {
        $response['message'] = "You must be logged in to update your information.";
    }

    wp_send_json($response); // Send JSON response
}
add_action('wp_ajax_update_user_ajax', 'update_user_ajax_handler');
add_action('wp_ajax_nopriv_update_user_ajax', 'update_user_ajax_handler'); // For non-logged-in users

