# 🛍️ ShopVerse — E-Commerce Web Application

ShopVerse is a full-stack e-commerce web application built with **PHP, MySQL, PDO, JavaScript, AJAX, and Bootstrap 5**. The project includes customer authentication, product and category management, shopping cart, wishlist, checkout, order management, and an admin dashboard.

## 🚀 Features

### 👤 Customer Features

- User registration and login
- Logout functionality
- Email verification
- Forgot password and password reset
- Secure session management
- Product browsing
- Product details
- Product categories
- Shopping cart
- Wishlist
- Checkout
- Order placement
- Order history
- Account management

### 🔐 Security Features

- PDO prepared statements
- SQL injection protection
- Password hashing using PHP password hashing functions
- CSRF protection
- Session security
- Input validation and sanitization
- Role-based access control
- Authentication middleware
- Secure database error handling
- Restricted access to sensitive directories using `.htaccess`
- Sensitive mail configuration excluded from Git
- Mail logs excluded from Git
- ZIP/archive files excluded from Git

### 👨‍💼 Admin Features

- Admin dashboard
- Product management
- Category management
- Customer management
- Order management
- Sales/report management
- Admin authentication and authorization

## 🛠️ Technologies Used

| Technology   | Purpose                       |
| ------------ | ----------------------------- |
| PHP          | Backend development           |
| MySQL        | Database                      |
| PDO          | Secure database communication |
| HTML5        | Page structure                |
| CSS3         | Styling                       |
| JavaScript   | Client-side functionality     |
| AJAX         | Asynchronous API requests     |
| Bootstrap 5  | Responsive UI                 |
| Git          | Version control               |
| GitHub       | Source code hosting           |
| Apache/XAMPP | Local development environment |

## 📂 Project Structure

```text
shopverse/
│
├── admin/                  # Admin dashboard and management
├── database/               # Database connection
├── includes/               # Authentication, security and mailer
├── css/                    # Stylesheets
├── js/                     # JavaScript files
├── Screenshots/            # Application screenshots
│
├── index.php               # Home page
├── login.php               # Login page
├── register.php            # Registration page
├── shop.php                # Shop page
├── cart.php                # Shopping cart
├── wishlist.php            # Wishlist
├── checkout.php            # Checkout
├── orders.php              # Customer orders
│
├── login_process.php       # Login processing
├── register_process.php    # Registration processing
├── Cart_process.php        # Cart processing
├── Checkout_process.php    # Checkout processing
│
├── .htaccess               # Apache configuration
├── .gitignore              # Git ignored files
└── README.md               # Project documentation
```

## 🗄️ Database

The application uses **MySQL** as its database system.

The database connection is implemented using **PDO** with:

- Exception-based error handling
- Associative fetch mode
- UTF-8 (`utf8mb4`) support
- Native prepared statements

For local development, create a database named:

```text
shopverse
```

Then import the project's database SQL file through **phpMyAdmin**.

## ⚙️ Installation

### 1. Install XAMPP

Install XAMPP with:

- Apache
- MySQL/MariaDB
- PHP

### 2. Clone the Repository

```bash
git clone https://github.com/Ahmedfaraz509/shopverse-ecommerce.git
```

### 3. Move the Project

Place the project inside:

```text
C:\xampp\htdocs\shopverse
```

### 4. Start XAMPP

Start:

```text
Apache
MySQL
```

### 5. Create the Database

Open phpMyAdmin and create:

```text
shopverse
```

Import the project's SQL database file.

### 6. Configure Database Connection

Update the database configuration according to your local environment.

Example:

```php
private string $host = "localhost";
private string $db_name = "shopverse";
private string $username = "root";
private string $password = "";
```

> Never commit real production database credentials or passwords to GitHub.

### 7. Open the Application

Visit:

```text
http://localhost/shopverse/
```

## 🔑 User Roles

### Customer

Customers can:

- Register
- Login
- Browse products
- Add products to cart
- Manage wishlist
- Checkout
- View orders
- Manage their account

### Admin

Administrators can:

- Manage products
- Manage categories
- Manage customers
- Manage orders
- View reports
- Access the admin dashboard

## 📸 Screenshots

## 📸 Screenshots

### Home Page

![Home Page](Screenshots/Screenshot%20%28522%29.png)

### Shop

![Shop](Screenshots/Screenshot%20%28523%29.png)

### Product Details

![Product Details](Screenshots/Screenshot%20%28524%29.png)

### Cart

![Cart](Screenshots/Screenshot%20%28525%29.png)

### Checkout

![Checkout](Screenshots/Screenshot%20%28526%29.png)

### Login

![Login](Screenshots/Screenshot%20%28527%29.png)

### Admin Dashboard

![Admin Dashboard](Screenshots/Screenshot%20%28528%29.png)

### Admin Management

![Admin Management](Screenshots/Screenshot%20%28529%29.png)

## 🔒 Security Notes

Sensitive configuration files and runtime logs are intentionally excluded from the repository using `.gitignore`.

The following files should remain local:

```text
includes/mail_config.php
logs/mail.log
```

Never upload:

- Gmail passwords
- SMTP app passwords
- API keys
- Database production passwords
- Session secrets
- Private credentials

## 🎯 Project Objectives

This project was developed to practice and demonstrate:

- PHP backend development
- Object-oriented programming concepts
- MySQL database management
- CRUD operations
- Authentication and authorization
- Secure database queries
- REST-style API endpoints
- AJAX communication
- E-commerce workflows
- Admin dashboard development
- Git and GitHub version control

## 🔮 Future Improvements

Possible future improvements include:

- Laravel migration
- Payment gateway integration
- Advanced product filtering
- Product reviews and ratings
- Inventory notifications
- Advanced analytics dashboard
- REST API documentation
- Automated testing
- Deployment to a production server

## 👨‍💻 Author

**Ahmed Faraz**

Bachelor of Computer Science

GitHub: **Ahmedfaraz509**

---

⭐ If you find this project useful, feel free to explore the source code and learn from it.
