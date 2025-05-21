/**
 * Form validation script for MySihat Appointment System
 */

document.addEventListener('DOMContentLoaded', function() {
    
    // Helper function to display validation error
    function showError(input, message) {
        const formControl = input.parentElement;
        const errorElement = formControl.querySelector('.invalid-feedback') || document.createElement('div');
        
        errorElement.className = 'invalid-feedback';
        errorElement.innerText = message;
        
        if (!formControl.querySelector('.invalid-feedback')) {
            formControl.appendChild(errorElement);
        }
        
        input.classList.add('is-invalid');
    }
    
    // Helper function to clear validation error
    function clearError(input) {
        input.classList.remove('is-invalid');
        input.classList.add('is-valid');
    }
    
    // Helper function to validate email format
    function isValidEmail(email) {
        const re = /^(([^<>()\[\]\\.,;:\s@"]+(\.[^<>()\[\]\\.,;:\s@"]+)*)|(".+"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/;
        return re.test(String(email).toLowerCase());
    }
    
    // Helper function to validate password strength
    function isStrongPassword(password) {
        // Password must be at least 8 characters and include at least one uppercase letter,
        // one lowercase letter, one number, and one special character
        const re = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/;
        return re.test(password);
    }
    
    // Login form validation
    const loginForm = document.getElementById('login_form');
    if (loginForm) {
        loginForm.addEventListener('submit', function(e) {
            let isValid = true;
            
            // Get form fields
            const username = document.getElementById('username');
            const password = document.getElementById('password');
            
            // Validate username/email
            if (username.value.trim() === '') {
                showError(username, 'Username or email is required');
                isValid = false;
            } else {
                clearError(username);
            }
            
            // Validate password
            if (password.value.trim() === '') {
                showError(password, 'Password is required');
                isValid = false;
            } else {
                clearError(password);
            }
            
            if (!isValid) {
                e.preventDefault();
            }
        });
    }
    
    // Registration form validation
    const registerForm = document.getElementById('register_form');
    if (registerForm) {
        registerForm.addEventListener('submit', function(e) {
            let isValid = true;
            
            // Get form fields
            const username = document.getElementById('username');
            const email = document.getElementById('email');
            const password = document.getElementById('password');
            const confirmPassword = document.getElementById('confirm_password');
            const firstName = document.getElementById('first_name');
            const lastName = document.getElementById('last_name');
            const role = document.getElementById('role');
            
            // Validate username
            if (username.value.trim() === '') {
                showError(username, 'Username is required');
                isValid = false;
            } else if (username.value.length < 4) {
                showError(username, 'Username must be at least 4 characters');
                isValid = false;
            } else {
                clearError(username);
            }
            
            // Validate email
            if (email.value.trim() === '') {
                showError(email, 'Email is required');
                isValid = false;
            } else if (!isValidEmail(email.value.trim())) {
                showError(email, 'Please enter a valid email address');
                isValid = false;
            } else {
                clearError(email);
            }
            
            // Validate password
            if (password.value === '') {
                showError(password, 'Password is required');
                isValid = false;
            } else if (!isStrongPassword(password.value)) {
                showError(password, 'Password must be at least 8 characters and include uppercase, lowercase, number, and special character');
                isValid = false;
            } else {
                clearError(password);
            }
            
            // Validate confirm password
            if (confirmPassword.value === '') {
                showError(confirmPassword, 'Please confirm your password');
                isValid = false;
            } else if (password.value !== confirmPassword.value) {
                showError(confirmPassword, 'Passwords do not match');
                isValid = false;
            } else {
                clearError(confirmPassword);
            }
            
            // Validate first name
            if (firstName.value.trim() === '') {
                showError(firstName, 'First name is required');
                isValid = false;
            } else {
                clearError(firstName);
            }
            
            // Validate last name
            if (lastName.value.trim() === '') {
                showError(lastName, 'Last name is required');
                isValid = false;
            } else {
                clearError(lastName);
            }
            
            // Validate role selection
            if (role.value === '') {
                showError(role, 'Please select a role');
                isValid = false;
            } else {
                clearError(role);
            }
            
            // If doctor role is selected, validate specialization and qualification
            if (role.value === 'doctor') {
                const specialization = document.getElementById('specialization');
                const qualification = document.getElementById('qualification');
                
                if (specialization && specialization.value.trim() === '') {
                    showError(specialization, 'Specialization is required for doctors');
                    isValid = false;
                } else if (specialization) {
                    clearError(specialization);
                }
                
                if (qualification && qualification.value.trim() === '') {
                    showError(qualification, 'Qualification is required for doctors');
                    isValid = false;
                } else if (qualification) {
                    clearError(qualification);
                }
            }
            
            if (!isValid) {
                e.preventDefault();
            }
        });
        
        // Show/hide doctor-specific fields based on role selection
        const roleSelect = document.getElementById('role');
        const doctorFields = document.getElementById('doctor_fields');
        
        if (roleSelect && doctorFields) {
            roleSelect.addEventListener('change', function() {
                if (this.value === 'doctor') {
                    doctorFields.classList.remove('d-none');
                } else {
                    doctorFields.classList.add('d-none');
                }
            });
        }
    }
    
    // Booking form validation
    const bookingForm = document.getElementById('booking_form');
    if (bookingForm) {
        bookingForm.addEventListener('submit', function(e) {
            let isValid = true;
            
            // Get form fields
            const doctorId = document.getElementById('doctor_id');
            const appointmentDate = document.getElementById('appointment_date');
            const selectedTime = document.getElementById('selected_time');
            
            // Validate doctor selection
            if (doctorId.value === '') {
                showError(doctorId, 'Please select a doctor');
                isValid = false;
            } else {
                clearError(doctorId);
            }
            
            // Validate appointment date
            if (appointmentDate.value === '') {
                showError(appointmentDate, 'Please select an appointment date');
                isValid = false;
            } else {
                clearError(appointmentDate);
            }
            
            // Validate time slot
            if (selectedTime.value === '') {
                document.getElementById('time_slots_error').textContent = 'Please select a time slot';
                document.getElementById('time_slots_error').classList.remove('d-none');
                isValid = false;
            } else {
                document.getElementById('time_slots_error').textContent = '';
                document.getElementById('time_slots_error').classList.add('d-none');
            }
            
            if (!isValid) {
                e.preventDefault();
            }
        });
    }
    
    // Medical record form validation
    const medicalRecordForm = document.getElementById('medical_record_form');
    if (medicalRecordForm) {
        medicalRecordForm.addEventListener('submit', function(e) {
            let isValid = true;
            
            // Get form fields
            const diagnosis = document.getElementById('diagnosis');
            const treatment = document.getElementById('treatment');
            
            // Validate diagnosis
            if (diagnosis.value.trim() === '') {
                showError(diagnosis, 'Diagnosis is required');
                isValid = false;
            } else {
                clearError(diagnosis);
            }
            
            // Validate treatment
            if (treatment.value.trim() === '') {
                showError(treatment, 'Treatment is required');
                isValid = false;
            } else {
                clearError(treatment);
            }
            
            // Validate prescriptions
            const medicationInputs = document.querySelectorAll('.medication-name');
            const dosageInputs = document.querySelectorAll('.medication-dosage');
            const frequencyInputs = document.querySelectorAll('.medication-frequency');
            const durationInputs = document.querySelectorAll('.medication-duration');
            
            for (let i = 0; i < medicationInputs.length; i++) {
                if (medicationInputs[i].value.trim() !== '') {
                    // If medication name is provided, check other fields
                    if (dosageInputs[i].value.trim() === '') {
                        showError(dosageInputs[i], 'Dosage is required');
                        isValid = false;
                    } else {
                        clearError(dosageInputs[i]);
                    }
                    
                    if (frequencyInputs[i].value.trim() === '') {
                        showError(frequencyInputs[i], 'Frequency is required');
                        isValid = false;
                    } else {
                        clearError(frequencyInputs[i]);
                    }
                    
                    if (durationInputs[i].value.trim() === '') {
                        showError(durationInputs[i], 'Duration is required');
                        isValid = false;
                    } else {
                        clearError(durationInputs[i]);
                    }
                }
            }
            
            if (!isValid) {
                e.preventDefault();
            }
        });
    }
});

