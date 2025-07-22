# Healthcare Appointment Management System

A comprehensive web-based healthcare appointment management system built with PHP and SQLite. This system allows patients to book appointments, doctors to manage their schedules, and administrators to oversee the entire platform.

## 🌟 Features

### For Patients
- **User Registration & Login** - Create account and secure login
- **Appointment Booking** - Book appointments with available doctors
- **Appointment Management** - View, reschedule, and cancel appointments
- **Medical History** - Access complete medical records and prescriptions
- **Profile Management** - Update personal information
- **Notifications** - Receive updates about appointment status

### For Doctors
- **Schedule Management** - Set availability and working hours
- **Appointment Overview** - View and manage patient appointments
- **Medical Records** - Add diagnoses, treatments, and prescriptions
- **Patient History** - Access complete patient medical history
- **Status Updates** - Confirm, complete, or cancel appointments
- **Profile Management** - Update professional information

### For Administrators
- **User Management** - Manage patients, doctors, and admin accounts
- **Appointment Oversight** - View and manage all appointments
- **System Statistics** - Dashboard with key metrics and analytics
- **User Details** - View and edit user profiles
- **System Monitoring** - Track system usage and performance

## 🛠️ Technology Stack

- **Backend**: PHP 8.1+
- **Database**: SQLite
- **Frontend**: HTML5, CSS3, JavaScript
- **UI Framework**: Bootstrap 5
- **Icons**: Font Awesome
- **Server**: PHP Built-in Development Server

## 📋 Requirements

- PHP 8.1 or higher
- SQLite support (included in most PHP installations)
- Web browser with JavaScript enabled

## 🚀 Installation

1. **Clone the repository**
   ```bash
   git clone <repository-url>
   cd CSC577_clinic-app
   ```

2. **Initialize the database**
   ```bash
   cd database
   php init_db.php
   ```

3. **Start the development server**
   ```bash
   php -S 0.0.0.0:5000
   ```

4. **Access the application**
   Open your web browser and navigate to `http://localhost:5000`

## 👥 Default Login Credentials

The system comes with pre-configured test accounts:

### Administrator
- **Username**: `admin`
- **Password**: `Admin123!`
- **Access**: Full system administration

### Doctor
- **Username**: `doc1` -- doc1, doc2, doc3 #same password
- **Password**: `Doctor123!`
- **Specialization**: Physician

### Patient
- **Username**: `irfan` --qaiyum, atiqah, aizat #same password
- **Password**: `User123!`

## 📁 Project Structure

```
healthcare-appointment-system/
├── admin/                          # Admin interface files
│   ├── appointments.php            # Appointment management
│   ├── dashboard.php               # Admin dashboard
│   └── users.php                   # User management
├── assets/                         # Static assets
│   ├── css/style.css              # Custom styles
│   └── js/                        # JavaScript files
├── database/                       # Database files and scripts
│   ├── healthcare.sqlite          # SQLite database
│   ├── init_db.php               # Database initialization
│   └── schema_sqlite.sql          # Database schema
├── doctor/                         # Doctor interface files
│   ├── appointments.php           # Appointment management
│   ├── availability.php           # Schedule management
│   ├── dashboard.php              # Doctor dashboard
│   ├── patient_records.php        # Patient records
│   └── profile.php                # Profile management
├── includes/                       # Shared PHP files
│   ├── auth.php                   # Authentication functions
│   ├── config.php                 # Configuration settings
│   ├── db.php                     # Database connection
│   ├── functions.php              # Utility functions
│   └── *.php                      # Other includes
├── patient/                        # Patient interface files
│   ├── book_appointment.php       # Appointment booking
│   ├── dashboard.php              # Patient dashboard
│   ├── manage_appointments.php    # Appointment management
│   └── profile.php                # Profile management
├── index.php                       # Homepage
├── login.php                       # Login page
├── register.php                    # Registration page
└── README.md                       # This file
```

## 🔧 Configuration

### Database Configuration
The system uses SQLite by default. Database settings can be modified in `includes/config.php`:

```php
// Database configuration
define('DB_PATH', dirname(dirname(__FILE__)) . '/database/healthcare.sqlite');
```

### Application Settings
Key settings in `includes/config.php`:

```php
define('SITE_NAME', 'HealthCare Appointment System');
define('MAX_APPOINTMENTS_PER_DAY', 15);
define('APPOINTMENT_DURATION_MINUTES', 30);
define('ADVANCE_BOOKING_DAYS', 30);
```

## 🗄️ Database Schema

The system includes the following main tables:

- **users** - User accounts (patients, doctors, admins)
- **doctor_details** - Doctor professional information
- **doctor_availability** - Doctor schedule and availability
- **appointments** - Appointment bookings
- **medical_records** - Patient medical records
- **prescriptions** - Medication prescriptions
- **notifications** - System notifications

## 🔐 Security Features

- **Password Hashing** - Secure password storage using PHP's password_hash()
- **Session Management** - Secure session handling
- **Input Sanitization** - Protection against XSS attacks
- **SQL Injection Prevention** - Prepared statements for database queries
- **Role-based Access Control** - Different permissions for each user type
- **CSRF Protection** - Form token validation

## 🎨 User Interface

- **Responsive Design** - Works on desktop, tablet, and mobile devices
- **Bootstrap Framework** - Modern and clean interface
- **Interactive Elements** - Dynamic appointment booking and management
- **Status Indicators** - Visual appointment status tracking
- **Dashboard Analytics** - Charts and statistics for admins and doctors

## 🔄 Key Workflows

### Patient Appointment Booking
1. Patient registers/logs in
2. Selects doctor and preferred date
3. Chooses available time slot
4. Provides reason for visit
5. Submits appointment request
6. Receives confirmation notification

### Doctor Appointment Management
1. Doctor sets availability schedule
2. Reviews incoming appointment requests
3. Confirms or reschedules appointments
4. Conducts appointment and adds medical records
5. Prescribes medications if needed
6. Marks appointment as completed

### Admin System Oversight
1. Monitors system statistics
2. Manages user accounts
3. Oversees appointment scheduling
4. Handles system configuration
5. Reviews system performance

## 🤝 Contributing

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/AmazingFeature`)
3. Commit your changes (`git commit -m 'Add some AmazingFeature'`)
4. Push to the branch (`git push origin feature/AmazingFeature`)
5. Open a Pull Request

## 📝 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## 🐛 Known Issues

- Email notifications require SMTP configuration
- File upload functionality needs implementation
- Advanced reporting features pending

## 🚧 Future Enhancements

- [ ] Email notification system
- [ ] SMS notifications via Twilio
- [ ] Payment integration
- [ ] Telemedicine features
- [ ] Mobile app development
- [ ] Advanced reporting and analytics
- [ ] File upload for medical documents
- [ ] Multi-language support

## 📞 Support

For support, please open an issue in the GitHub repository or contact the development team.

## 🙏 Acknowledgments

- Bootstrap team for the excellent UI framework
- Font Awesome for the comprehensive icon library
- PHP community for the robust programming language
- SQLite team for the reliable database engine

---

**Note**: This is a demonstration project intended for educational purposes. For production use, additional security measures and testing are recommended.
