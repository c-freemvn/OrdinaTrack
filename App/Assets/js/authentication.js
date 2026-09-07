"use strict";

$(document).ready(function () {
    // Handle login form submission
    loginUser();
});

function loginUser() {
    
    $('#loginForm').click(function (event) {
        event.preventDefault(); // Prevent the default form submission  
        var username = $('#username').val();
        var password = $('#password').val();

        // Perform AJAX request to the server for authentication    
        const login = api.post('auth/login', { username: username, password: password });

        if (login) {
            login.then(function (response) {
                if (response.success) {
                    // Redirect to the dashboard or another page upon successful login
                    window.location.href = '/dashboard';
                }
                else {
                    // Display an error message for failed login
                    $('#loginError').text(response.message).show();
                }   
        }).catch(function (error) {
            console.error('Error during login:', error);
            $('#loginError').text('An error occurred during login. Please try again later.').show();
        });
        }
    });
}