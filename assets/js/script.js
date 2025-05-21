/**
 * Main script file for MySihat Appointment System
 */

// Wait for document to be fully loaded
document.addEventListener('DOMContentLoaded', function() {
    // Initialize Bootstrap tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function(tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // Initialize Bootstrap popovers
    var popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
    var popoverList = popoverTriggerList.map(function(popoverTriggerEl) {
        return new bootstrap.Popover(popoverTriggerEl);
    });
    
    // Initialize datepickers
    if (jQuery.fn.datepicker) {
        $('.datepicker').datepicker({
            format: 'yyyy-mm-dd',
            autoclose: true,
            todayHighlight: true,
            startDate: '+1d',  // Start from tomorrow
            endDate: '+30d'    // Allow booking up to 30 days in advance
        });
        
        // For date of birth fields, use different options
        $('.datepicker-dob').datepicker({
            format: 'yyyy-mm-dd',
            autoclose: true,
            endDate: '0d',     // No future dates for date of birth
            defaultViewDate: { year: 1990, month: 0, day: 1 }
        });
        
        // For doctor availability datepicker
        $('.datepicker-availability').datepicker({
            format: 'yyyy-mm-dd',
            autoclose: true,
            todayHighlight: true,
            startDate: '0d'    // Start from today
        });
    }
    
    // For appointment time slots
    $('.time-slot').on('click', function() {
        if (!$(this).hasClass('booked')) {
            $('.time-slot').removeClass('selected');
            $(this).addClass('selected');
            $('#selected_time').val($(this).data('time'));
        }
    });
    
    // Handle doctor selection change in booking form
    $('#doctor_id').on('change', function() {
        const doctorId = $(this).val();
        const dateField = $('#appointment_date');
        
        // Reset date field
        dateField.val('');
        
        // Enable/disable date field based on doctor selection
        if (doctorId) {
            dateField.prop('disabled', false);
        } else {
            dateField.prop('disabled', true);
        }
        
        // Clear time slots
        $('#time_slots').html('<p class="text-muted">Please select a date first.</p>');
    });
    
    // Handle appointment date change
    $('#appointment_date').on('change', function() {
        const date = $(this).val();
        const doctorId = $('#doctor_id').val();
        
        if (date && doctorId) {
            // Show loading indicator
            $('#time_slots').html('<div class="text-center"><i class="fas fa-spinner fa-spin"></i> Loading available time slots...</div>');
            
            // Fetch available time slots via AJAX
            $.ajax({
                url: SITE_URL + '/patient/get_available_slots.php',
                method: 'POST',
                data: { doctor_id: doctorId, date: date },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        const slots = response.slots;
                        let slotsHtml = '';
                        
                        if (slots.length > 0) {
                            slotsHtml = '<div class="row g-2">';
                            slots.forEach(function(slot) {
                                slotsHtml += `
                                    <div class="col-md-3 col-6">
                                        <div class="time-slot p-2 border rounded text-center" data-time="${slot.value}">
                                            ${slot.display}
                                        </div>
                                    </div>`;
                            });
                            slotsHtml += '</div>';
                        } else {
                            slotsHtml = '<p class="text-danger">No available time slots for the selected date.</p>';
                        }
                        
                        $('#time_slots').html(slotsHtml);
                        
                        // Reattach event handlers for time slots
                        $('.time-slot').on('click', function() {
                            $('.time-slot').removeClass('selected');
                            $(this).addClass('selected');
                            $('#selected_time').val($(this).data('time'));
                        });
                    } else {
                        $('#time_slots').html('<p class="text-danger">' + response.message + '</p>');
                    }
                },
                error: function() {
                    $('#time_slots').html('<p class="text-danger">Failed to load time slots. Please try again.</p>');
                }
            });
        } else {
            $('#time_slots').html('<p class="text-muted">Please select a doctor and date.</p>');
        }
    });
    
    // Doctor availability management
    $('.availability-toggle').on('change', function() {
        const dayId = $(this).data('day-id');
        const isAvailable = $(this).prop('checked');
        
        // Show/hide time inputs based on availability
        if (isAvailable) {
            $('#time_range_' + dayId).removeClass('d-none');
        } else {
            $('#time_range_' + dayId).addClass('d-none');
        }
    });
    
    // Handle appointment status changes
    $('.appointment-status-select').on('change', function() {
        const appointmentId = $(this).data('appointment-id');
        const newStatus = $(this).val();
        
        // Update appointment status via AJAX
        $.ajax({
            url: SITE_URL + '/includes/update_appointment_status.php',
            method: 'POST',
            data: { appointment_id: appointmentId, status: newStatus },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    // Show success message
                    alert('Appointment status updated successfully');
                    
                    // Update UI if needed
                    $('.appointment-card[data-id="' + appointmentId + '"]')
                        .removeClass('status-pending status-confirmed status-cancelled status-completed')
                        .addClass('status-' + newStatus);
                } else {
                    alert('Error: ' + response.message);
                }
            },
            error: function() {
                alert('Failed to update appointment status. Please try again.');
            }
        });
    });
    
    // Medical record form - add prescription
    $('#add_prescription').on('click', function(e) {
        e.preventDefault();
        
        // Clone the first prescription item
        const newItem = $('.prescription-item:first').clone();
        
        // Clear values
        newItem.find('input, textarea').val('');
        
        // Add remove button if doesn't exist
        if (newItem.find('.remove-prescription').length === 0) {
            newItem.append('<button type="button" class="btn btn-sm btn-danger remove-prescription mt-2"><i class="fas fa-trash"></i> Remove</button>');
        }
        
        // Append to container
        $('#prescriptions_container').append(newItem);
        
        // Initialize remove prescription handlers
        initializePrescriptionRemoval();
    });
    
    // Initialize prescription removal handlers
    function initializePrescriptionRemoval() {
        $('.remove-prescription').off('click').on('click', function() {
            $(this).closest('.prescription-item').remove();
        });
    }
    
    // Initialize on page load
    initializePrescriptionRemoval();
    
    // Search functionality
    $('#search_input').on('keyup', function() {
        const searchTerm = $(this).val().toLowerCase();
        
        $('.searchable-item').each(function() {
            const itemText = $(this).text().toLowerCase();
            if (itemText.indexOf(searchTerm) > -1) {
                $(this).show();
            } else {
                $(this).hide();
            }
        });
    });
});

// Define a global site URL for JavaScript usage
var SITE_URL = window.location.origin;

